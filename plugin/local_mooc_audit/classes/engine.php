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
    const VERSION = '0.5.0';
    /** @var string Version of the JSON report contract (bumped only on incompatible changes). */
    const CONTRACT_VERSION = '1.0';

    /**
     * Audit a course.
     *
     * @param int $courseid
     * @param array|null $policyoverride
     * @param int $userid test viewer whose availability is evaluated (0 = none: rules check the configuration only)
     * @param bool $includesnapshot include the full snapshot in the report
     * @return array report
     */
    public static function audit(
        int $courseid,
        ?array $policyoverride = null,
        int $userid = 0,
        bool $includesnapshot = false
    ): array {
        return self::audit_with_policy($courseid, policy::load($policyoverride), $userid, $includesnapshot);
    }

    /**
     * Audit a course with an already loaded policy (the history stores exactly the policy it applied).
     *
     * @param int $courseid
     * @param array $policy loaded policy (policy::load())
     * @param int $userid test viewer (0 = none)
     * @param bool $includesnapshot include the full snapshot in the report
     * @param rule_interface[]|null $rules rules to run (default: the catalogue; tests may inject others)
     * @return array report
     */
    public static function audit_with_policy(
        int $courseid,
        array $policy,
        int $userid = 0,
        bool $includesnapshot = false,
        ?array $rules = null
    ): array {
        $t0 = microtime(true);
        $snapshot = snapshot::build($courseid, $userid);
        $tsnap = microtime(true) - $t0;
        $results = self::run_rules($snapshot, $policy, $rules);
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
            'course' => ['id' => $snapshot['course']['id'], 'shortname' => $snapshot['course']['shortname'],
                'fullname' => $snapshot['course']['fullname']],
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

    /** @var string Check code of the single result that replaces a rule which could not run (ERRO or NAO_VERIFICAVEL). */
    const RULE_CHECK = '_rule';

    /**
     * Run every rule; an exception in one rule becomes an ERRO result and never aborts the audit.
     *
     * A rule that reads completion tracking (or access restrictions) cannot be verified when that feature is disabled
     * at site level: it is replaced by a single NAO_VERIFICAVEL result with the reason. Rules declare the dependency
     * through REQUIRES_COMPLETION / REQUIRES_AVAILABILITY.
     *
     * Every result gets an occurrence key (see occurrence()) built from the check code and subject the rule declares;
     * those two auxiliary fields are not kept in the result.
     *
     * @param array $snapshot
     * @param array $policy
     * @param rule_interface[]|null $rules rules to run keyed by id (default: the catalogue; tests may inject others)
     * @return array
     */
    public static function run_rules(array $snapshot, array $policy, ?array $rules = null): array {
        $results = [];
        $seen = [];
        // Section numbers change when a section is inserted or moved: occurrences use the section id.
        $sectionids = [];
        foreach ($snapshot['sections'] ?? [] as $sec) {
            $sectionids[(int) $sec['number']] = (int) $sec['id'];
        }
        $nocompletion = empty($snapshot['site']['enablecompletion']);
        $noavailability = empty($snapshot['site']['enableavailability']);
        foreach ($rules ?? catalog::instances() as $id => $rule) {
            $meta = $rule->meta();
            $reason = null;
            if ($nocompletion && !empty($meta['requires_completion'])) {
                $reason = 'site_completion_disabled';
            } else if ($noavailability && !empty($meta['requires_availability'])) {
                $reason = 'site_availability_disabled';
            }
            if ($reason) {
                $findings = [['status' => status::NAO_VERIFICAVEL, 'local' => null,
                    'evidencia' => get_string($reason, 'local_mooc_audit'),
                    'gravidade' => null, 'locus' => base::LOCUS_NONE, 'check' => self::RULE_CHECK]];
            } else {
                try {
                    $findings = $rule->evaluate($snapshot, $policy);
                } catch (\Throwable $e) {
                    $findings = [['status' => status::ERRO, 'local' => null,
                        'evidencia' => get_string('rule_error', 'local_mooc_audit', get_class($e) . ': ' . $e->getMessage()),
                            'gravidade' => null,
                        'locus' => base::LOCUS_NONE, 'check' => self::RULE_CHECK]];
                }
            }
            foreach ($findings as $f) {
                $f = array_merge(['local' => null, 'evidencia' => null, 'encontrado' => null, 'esperado' => null,
                    'orientacao' => null,
                    'gravidade' => null, 'cmid' => null, 'locus' => null, 'section' => null, 'check' => null,
                    'subject' => null], $f);
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
                $key = self::occurrence($id, $f, $sectionids);
                // Safety net only: a collision is a defect of the rule (occurrence_test fails on the reference courses).
                $seen[$key] = ($seen[$key] ?? 0) + 1;
                $f['occurrence'] = $seen[$key] > 1 ? $key . '#' . $seen[$key] : $key;
                unset($f['check'], $f['subject']);
                $results[] = $f;
            }
        }
        return $results;
    }

    /**
     * Stable occurrence key of a finding: rule|entity|check[|subject] (specs/005 research R2).
     *
     * The entity is the activity (cm:<cmid>), else the section (sec:<section id>, stable when sections are inserted or
     * moved; the number when the id is unknown), else the course. The check is the code of
     * the verified condition declared by the rule (the same whether the condition passes or fails) and the subject a
     * semantic discriminator when the check repeats on the same entity. Never derived from the evidence text, the
     * language or the position of the result.
     *
     * @param string $rule
     * @param array $f finding with cmid, section (number), check and subject
     * @param array $sectionids section number => section id
     * @return string
     */
    public static function occurrence(string $rule, array $f, array $sectionids = []): string {
        if (!empty($f['cmid'])) {
            $entity = 'cm:' . (int) $f['cmid'];
        } else if ($f['section'] !== null && $f['section'] !== '') {
            $entity = 'sec:' . ($sectionids[(int) $f['section']] ?? (int) $f['section']);
        } else {
            $entity = 'course';
        }
        $key = $rule . '|' . $entity . '|' . ($f['check'] ?? 'main');
        if ($f['subject'] !== null && $f['subject'] !== '') {
            $key .= '|' . str_replace('|', '/', (string) $f['subject']);
        }
        return $key;
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
        return ['por_status' => $bystatus, 'nao_conformes_por_gravidade' => $bysev, 'regras' => catalog::count(),
            'overall_status' => $overall];
    }

    /**
     * JSON serialisation of a report (contract 1.0): the single encoding used by the page and the CLI.
     *
     * @param array $report
     * @return string
     */
    public static function to_json(array $report): string {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        $c = $report['course'];
        $lines[] = "=== {$c['fullname']} [{$c['shortname']}] (id {$c['id']}) — {$report['engine']}"
            . " / {$str('label_policy')} {$report['policy_version']}"
            . " ({$str('label_reoffer_year')} {$report['reoffer_year']})";
        $lines[] = "{$str('label_period')}: {$inv['startdate']} → {$inv['enddate']}"
            . " | {$str('label_category')}: {$inv['category']['name']}"
            . " | {$str('label_visible')}: {$inv['visible']} | {$str('label_time')}: {$report['timing']['total_s']}s";
        $lines[] = "{$str('label_sections')}: {$inv['sections']} | {$str('label_modules')}: {$inv['modules']} "
            . json_encode($inv['modules_by_type'])
            . " | {$str('label_files')}: {$inv['files']} ({$str('label_files_missing')}: {$inv['files_missing_in_storage']})";
        $lines[] = "{$str('label_blocks')}: " . json_encode($inv['blocks']) . " | {$str('label_enrols')}: "
            . json_encode($inv['enrols']);
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
            if ((string) $r['encontrado'] !== '') {
                $lines[] = "      {$str('label_found')}: " . $r['encontrado'];
            }
            if ((string) $r['esperado'] !== '') {
                $lines[] = "      {$str('label_expected')}: " . $r['esperado'];
            }
            if ((string) $r['orientacao'] !== '') {
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
