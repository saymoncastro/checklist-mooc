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
namespace local_mooc_audit;

use local_mooc_audit\local\catalog;
use local_mooc_audit\local\category;
use local_mooc_audit\local\locator;
use local_mooc_audit\local\rule\base;
use local_mooc_audit\local\rule\rule_interface;
use local_mooc_audit\local\status;

/**
 * Runs the rule catalogue over a snapshot, isolating failures per rule, and aggregates the report.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /** @var string Engine version recorded in every report. */
    const VERSION = '0.2.0';
    /** @var string Version of the JSON report contract (bumped only on incompatible changes). */
    const CONTRACT_VERSION = '1.0';

    /**
     * Audit a course.
     *
     * @param int $courseid
     * @param array|null $policyoverride
     * @param int $userid viewer for uservisible (0 = current user)
     * @param bool $includesnapshot include the full snapshot in the report
     * @return array report
     */
    public static function audit(int $courseid, ?array $policyoverride = null, int $userid = 0, bool $includesnapshot = false): array {
        $policy = policy::load($policyoverride);
        $t0 = microtime(true);
        $snapshot = snapshot::build($courseid, $userid);
        $tsnap = microtime(true) - $t0;
        $results = self::run_rules($snapshot, $policy);
        foreach ($results as &$r) {
            $url = locator::url_for($r, $courseid, $snapshot['modules'], $snapshot['sections']);
            $r['link'] = $url ? $url->out(false) : null;
        }
        unset($r);
        $report = [
            'contract_version' => self::CONTRACT_VERSION,
            'engine' => 'local_mooc_audit ' . self::VERSION,
            'policy_version' => $policy['policy_version'],
            'policy_warnings' => $policy['warnings'] ?? [],
            'reoffer_year' => $policy['reoffer_year'],
            'executed_at' => date('c'),
            'course' => ['id' => $snapshot['course']['id'], 'shortname' => $snapshot['course']['shortname'], 'fullname' => $snapshot['course']['fullname']],
            'viewer_userid' => $userid,
            'timing' => ['snapshot_s' => round($tsnap, 3), 'total_s' => round(microtime(true) - $t0, 3)],
            'inventory' => self::inventory($snapshot),
            'summary' => self::summarize($results),
            'results' => $results,
            'warnings' => $snapshot['warnings'],
        ];
        if ($includesnapshot) {
            $report['snapshot'] = $snapshot;
        }
        return $report;
    }

    /**
     * Run every rule; an exception in one rule becomes an ERRO result and never aborts the audit.
     *
     * When completion tracking or access restrictions are disabled at site level, the rules of the completion and
     * certification categories cannot be verified and are replaced by a single NAO_VERIFICAVEL result each.
     *
     * @param array $snapshot
     * @param array $policy
     * @param rule_interface[]|null $rules rules to run keyed by id (default: the catalogue; tests may inject others)
     * @return array
     */
    public static function run_rules(array $snapshot, array $policy, ?array $rules = null): array {
        $results = [];
        $sitedisabled = empty($snapshot['site']['enablecompletion']) || empty($snapshot['site']['enableavailability']);
        foreach ($rules ?? catalog::instances() as $id => $rule) {
            $meta = $rule->meta();
            if ($sitedisabled && in_array($meta['category'], [category::COMPLETION_RESTRICTIONS, category::CERTIFICATION], true)) {
                $findings = [['status' => status::NAO_VERIFICAVEL, 'local' => null, 'evidencia' => get_string('site_completion_disabled', 'local_mooc_audit'),
                    'gravidade' => null, 'locus' => base::LOCUS_NONE]];
            } else {
                try {
                    $findings = $rule->evaluate($snapshot, $policy);
                } catch (\Throwable $e) {
                    $findings = [['status' => status::ERRO, 'local' => null,
                        'evidencia' => get_string('rule_error', 'local_mooc_audit', get_class($e) . ': ' . $e->getMessage()), 'gravidade' => null,
                        'locus' => base::LOCUS_NONE]];
                }
            }
            foreach ($findings as $f) {
                $f = array_merge(['local' => null, 'evidencia' => null, 'encontrado' => null, 'esperado' => null, 'orientacao' => null,
                    'gravidade' => null, 'cmid' => null, 'locus' => null, 'section' => null], $f);
                $f['rule'] = $id;
                $f['titulo'] = $meta['title'];
                $f['origem'] = $meta['origin'];
                $f['classe'] = 'A';
                $f['category'] = $meta['category'];
                $f['tipo'] = $meta['type'];
                $f['gravidade'] = $f['gravidade'] ?? $meta['severity'];
                $f['locus'] = $f['locus'] ?? $meta['locus'];
                if (empty($f['cmid']) && in_array($f['locus'], [base::LOCUS_MODULE_EDIT, base::LOCUS_MODULE_VIEW], true)) {
                    $f['locus'] = base::LOCUS_NONE;
                }
                if ($f['status'] === status::NAO_CONFORME && ($f['orientacao'] === null || $f['orientacao'] === '')) {
                    $f['orientacao'] = $meta['guidance'];
                }
                $results[] = $f;
            }
        }
        return $results;
    }

    /**
     * Compact inventory for the report header.
     *
     * @param array $s
     * @return array
     */
    public static function inventory(array $s): array {
        $bytype = [];
        foreach ($s['modules'] as $m) {
            $bytype[$m['modname']] = ($bytype[$m['modname']] ?? 0) + 1;
        }
        ksort($bytype);
        return [
            'startdate' => $s['course']['startdate'] ? userdate($s['course']['startdate'], '%Y-%m-%d %H:%M', 99, false) : null,
            'enddate' => $s['course']['enddate'] ? userdate($s['course']['enddate'], '%Y-%m-%d %H:%M', 99, false) : null,
            'category' => $s['course']['category'],
            'visible' => $s['course']['visible'],
            'sections' => count($s['sections']),
            'modules' => count($s['modules']),
            'modules_by_type' => $bytype,
            'grade_items' => $s['grade']['items_total'],
            'enrols' => array_map(fn($e) => $e['enrol'] . ($e['status'] ? '(off)' : ''), $s['enrols']),
            'blocks' => array_map(fn($b) => $b['name'] . ($b['inherited'] ? '*' : ''), $s['blocks']),
            'plugins_used' => $s['plugins']['used'],
            'files' => array_sum(array_map(fn($m) => $m['files']['count'], $s['modules'])),
            'files_missing_in_storage' => array_sum(array_map(fn($m) => count($m['files']['missing_in_storage']), $s['modules'])),
            'site' => $s['site'],
        ];
    }

    /**
     * Count results by status and severity and derive the overall status of the course.
     *
     * Only NAO_CONFORME results influence the overall status: BLOQUEADA when one of them is Bloqueante,
     * REQUER_CORRECOES when there is any, SEM_INCONFORMIDADES otherwise.
     *
     * @param array $results
     * @return array
     */
    public static function summarize(array $results): array {
        $bystatus = [];
        $bysev = [];
        foreach ($results as $r) {
            $bystatus[$r['status']] = ($bystatus[$r['status']] ?? 0) + 1;
            if ($r['status'] === status::NAO_CONFORME) {
                $bysev[$r['gravidade']] = ($bysev[$r['gravidade']] ?? 0) + 1;
            }
        }
        if (!empty($bysev[status::BLOQUEANTE])) {
            $overall = status::OVERALL_BLOCKED;
        } else if ($bysev) {
            $overall = status::OVERALL_NEEDS_FIX;
        } else {
            $overall = status::OVERALL_CLEAN;
        }
        return ['por_status' => $bystatus, 'nao_conformes_por_gravidade' => $bysev, 'regras' => catalog::count(), 'overall_status' => $overall];
    }

    /**
     * Plain-text rendering of a report (CLI): header, inventory, overall status, every non conformant result first,
     * conformant rules compacted, then the policy and data collection notices. Labels come from the language pack.
     *
     * @param array $report
     * @return string
     */
    public static function render_text(array $report): string {
        $str = fn(string $key, $a = null) => get_string($key, 'local_mooc_audit', $a);
        $results = $report['results'];
        usort($results, fn($a, $b) => [status::status_rank($a['status']), status::severity_rank($a['gravidade']), $a['rule']]
            <=> [status::status_rank($b['status']), status::severity_rank($b['gravidade']), $b['rule']]);
        $inv = $report['inventory'];
        $lines = [];
        $lines[] = "=== {$report['course']['fullname']} [{$report['course']['shortname']}] (id {$report['course']['id']}) — {$report['engine']}"
            . " / {$str('label_policy')} {$report['policy_version']} ({$str('label_reoffer_year')} {$report['reoffer_year']})";
        $lines[] = "{$str('label_period')}: {$inv['startdate']} → {$inv['enddate']} | {$str('label_category')}: {$inv['category']['name']}"
            . " | {$str('label_visible')}: {$inv['visible']} | {$str('label_time')}: {$report['timing']['total_s']}s";
        $lines[] = "{$str('label_sections')}: {$inv['sections']} | {$str('label_modules')}: {$inv['modules']} " . json_encode($inv['modules_by_type'])
            . " | {$str('label_files')}: {$inv['files']} ({$str('label_files_missing')}: {$inv['files_missing_in_storage']})";
        $lines[] = "{$str('label_blocks')}: " . json_encode($inv['blocks']) . " | {$str('label_enrols')}: " . json_encode($inv['enrols']);
        $lines[] = "{$str('label_overall')}: " . $str('overall_' . strtolower($report['summary']['overall_status']))
            . ' | ' . json_encode($report['summary']['por_status'])
            . " | {$str('label_bysev')}: " . json_encode($report['summary']['nao_conformes_por_gravidade'], JSON_UNESCAPED_UNICODE);
        foreach ($results as $r) {
            if ($r['status'] === status::CONFORME) {
                continue;
            }
            $lines[] = sprintf('  [%-15s] %-9s %-11s %s', $r['status'], $r['rule'], $r['gravidade'], $r['titulo']);
            $lines[] = "      {$str('label_local')}: " . $r['local'] . ($r['link'] ? ' → ' . $r['link'] : '');
            $lines[] = "      {$str('label_evidence')}: " . $r['evidencia'];
            if (!empty($r['encontrado'])) {
                $lines[] = "      {$str('label_found')}: " . $r['encontrado'];
            }
            if (!empty($r['esperado'])) {
                $lines[] = "      {$str('label_expected')}: " . $r['esperado'];
            }
            if (!empty($r['orientacao'])) {
                $lines[] = "      {$str('label_guidance')}: " . $r['orientacao'];
            }
        }
        $notconf = array_unique(array_column(array_filter($results, fn($r) => $r['status'] !== status::CONFORME), 'rule'));
        $conf = array_values(array_diff(array_unique(array_column($results, 'rule')), $notconf));
        sort($conf);
        $lines[] = "  ({$str('label_conformant_omitted')}: " . implode(', ', $conf) . ')';
        foreach ($report['policy_warnings'] as $w) {
            $lines[] = "  {$str('label_policy')}: " . $w;
        }
        foreach ($report['warnings'] as $w) {
            $lines[] = "  {$str('label_collection')}: " . (is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE));
        }
        return implode("\n", $lines) . "\n";
    }
}
