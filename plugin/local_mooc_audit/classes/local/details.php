<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mooc_audit\local;

use local_mooc_audit\local\presentation\catalog as messages;
use local_mooc_audit\snapshot;

/**
 * Structured facts of a result (report contract 1.1, specs/006 FR-043): values found and required, related activities
 * and the kind of evidence.
 *
 * Computed by the engine from the snapshot and the policy for a closed list of checks, so that the rules themselves do
 * not change (their code fingerprint, which flags outdated runs, stays the same). The values read here are the same
 * snapshot fields the rules evaluate; they never decide a state.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class details {
    /** @var string The result states a fact read from the course configuration. */
    const PROVEN = 'proven';
    /** @var string The result interprets a text (description of an activity, name of a section). */
    const HEURISTIC = 'heuristic';
    /** @var string The result depends on finding an activity by its name. */
    const NAME_MATCH = 'name_match';
    /** @var string No static impediment found: actual behaviour still needs a test with a learner. */
    const STATIC = 'static';

    /**
     * Optional contract 1.1 fields of a result.
     *
     * @param string $rule rule id
     * @param array $f finding (with check and subject)
     * @param array $s course snapshot
     * @param array $p policy
     * @return array evidence_level and, when known, params and subjects
     */
    public static function for_result(string $rule, array $f, array $s, array $p): array {
        $check = (string) ($f['check'] ?? '');
        $out = ['evidence_level' => self::level($rule, $check, (string) $f['status'])];
        $cmid = (int) ($f['cmid'] ?? 0);
        $m = $s['modules'][$cmid] ?? null;
        $subject = (string) ($f['subject'] ?? '');
        switch ($rule . '|' . $check) {
            case 'CL-06|startdate':
                $out['params'] = ['found' => (int) $s['course']['startdate'],
                    'required' => ($p['required_start_date'] ?: '01/01') . '/' . $p['reoffer_year']];
                break;
            case 'CL-07|enddate':
                $out['params'] = ['found' => (int) $s['course']['enddate'],
                    'required' => array_map(fn($d) => $d . '/' . $p['reoffer_year'], (array) $p['allowed_end_dates'])];
                break;
            case 'CL-08|self_enrol':
                foreach ($s['enrols'] as $e) {
                    if ($subject === 'enrol:' . $e['id']) {
                        $out['params'] = ['enabled' => (int) $e['status'] === 0, 'newenrols' => $e['customint6'] === 1,
                            'start' => (int) $e['enrolstartdate'], 'end' => (int) $e['enrolenddate'],
                            'coursestart' => (int) $s['course']['startdate'], 'courseend' => (int) $s['course']['enddate']];
                    }
                }
                break;
            case 'CL-12|total_sum':
                $out['params'] = ['found' => self::num(array_sum(array_map(
                    fn($x) => (float) $x[1]['grademax'],
                    helpers::graded($s)
                ))), 'required' => self::num((float) $p['course_total_max'])];
                break;
            case 'CL-13|attempts':
                if ($m) {
                    $out['params'] = ['found' => (int) ($m['extra']['attempts'] ?? 0), 'required' => (int) $p['min_quiz_attempts'],
                        'rule' => (string) $p['attempts_rule']];
                }
                break;
            case 'CL-14|close_date':
                if ($m && ($close = helpers::close_date($m)) !== null) {
                    $out['params'] = ['found' => $close, 'courseend' => (int) $s['course']['enddate']];
                }
                break;
            case 'CL-23|min_grade':
                if ($m) {
                    $mins = [];
                    foreach (snapshot::walk_conditions($m['availability']) as $c) {
                        if (($c['type'] ?? '') === 'grade' && isset($c['min'])) {
                            $mins[] = (float) $c['min'];
                        }
                    }
                    $out['params'] = ['found' => $mins ? self::num(max($mins)) : null,
                        'required' => self::num((float) $p['certificate_min_grade'])];
                }
                break;
            case 'CL-16|requires_graded':
                if ($m) {
                    $required = [];
                    foreach (snapshot::walk_conditions($m['availability']) as $c) {
                        if (($c['type'] ?? '') === 'completion') {
                            $required[(int) $c['cm']] = true;
                        }
                    }
                    $missing = [];
                    foreach (helpers::graded($s) as [$gm]) {
                        if (!isset($required[(int) $gm['cmid']])) {
                            $missing[(int) $gm['cmid']] = true;
                        }
                    }
                    $out['subjects'] = array_keys($missing);
                }
                break;
            case 'X-CERT-01|chain':
                $out['subjects'] = $cmid ? self::chain_problems($cmid, $s) : [];
                break;
            case 'X-DEP-02|target_completion':
            case 'X-DEP-02|target_grade':
                $out['subjects'] = preg_match('/^cm:(\d+)$/', $subject, $mm) ? [(int) $mm[1]] : [];
                break;
            case 'X-DEP-04|cycle':
                $out['subjects'] = array_map('intval', array_filter(explode('>', $subject), 'strlen'));
                break;
        }
        return $out;
    }

    /**
     * A number that survives the JSON round trip unchanged: integer when whole, otherwise rounded to two decimals.
     *
     * @param float $x
     * @return int|float
     */
    protected static function num(float $x) {
        $x = round($x, 2);
        return floor($x) === $x ? (int) $x : $x;
    }

    /**
     * Kind of evidence of a result.
     *
     * @param string $rule
     * @param string $check
     * @param string $status
     * @return string
     */
    public static function level(string $rule, string $check, string $status): string {
        if ($rule === 'X-CERT-01' && $check === 'chain' && $status === status::CONFORME) {
            return self::STATIC;
        }
        $kind = messages::evidence($rule, $check);
        return $kind === 'text' ? self::HEURISTIC : ($kind === 'name' ? self::NAME_MATCH : self::PROVEN);
    }

    /**
     * Activities of the completion chain of a certificate that keep it from being released: missing, without completion
     * or hidden (the same walk as X-CERT-01).
     *
     * @param int $certcmid
     * @param array $s snapshot
     * @return int[]
     */
    protected static function chain_problems(int $certcmid, array $s): array {
        $stack = [$certcmid];
        $seen = [];
        $chain = [];
        while ($stack) {
            $u = array_pop($stack);
            if (isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            foreach (snapshot::walk_conditions($s['modules'][$u]['availability'] ?? null) as $cond) {
                if (($cond['type'] ?? '') === 'completion') {
                    $chain[(int) $cond['cm']] = true;
                    $stack[] = (int) $cond['cm'];
                }
            }
        }
        $out = [];
        foreach (array_keys($chain) as $v) {
            $t = $s['modules'][$v] ?? null;
            if (!$t || (int) $t['completion'] === 0 || (int) $t['visible'] === 0) {
                $out[] = $v;
            }
        }
        return $out;
    }
}
