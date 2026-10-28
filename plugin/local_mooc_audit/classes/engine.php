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

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the rule catalogue over a snapshot, isolating failures per rule, and aggregates the report.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /** @var string Engine version recorded in every report. */
    const VERSION = '0.1.0-poc';

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
        $report = [
            'engine' => 'local_mooc_audit ' . self::VERSION,
            'policy_version' => $policy['policy_version'],
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
     * @param array $snapshot
     * @param array $policy
     * @return array
     */
    public static function run_rules(array $snapshot, array $policy): array {
        $results = [];
        foreach (rules::catalog() as $id => [$titulo, $origem, $classe, $gravidade, $method]) {
            try {
                $findings = rules::$method($snapshot, $policy);
            } catch (\Throwable $e) {
                $findings = [['status' => rules::ERRO, 'local' => null, 'evidencia' => get_class($e) . ': ' . $e->getMessage(), 'gravidade' => null]];
            }
            foreach ($findings as $f) {
                $f['rule'] = $id;
                $f['titulo'] = $titulo;
                $f['origem'] = $origem;
                $f['classe'] = $classe;
                $f['gravidade'] = $f['gravidade'] ?? $gravidade;
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
     * Count results by status and severity.
     *
     * @param array $results
     * @return array
     */
    public static function summarize(array $results): array {
        $bystatus = [];
        $bysev = [];
        foreach ($results as $r) {
            $bystatus[$r['status']] = ($bystatus[$r['status']] ?? 0) + 1;
            if ($r['status'] === rules::NAO_CONFORME) {
                $bysev[$r['gravidade']] = ($bysev[$r['gravidade']] ?? 0) + 1;
            }
        }
        return ['por_status' => $bystatus, 'nao_conformes_por_gravidade' => $bysev, 'regras' => count(rules::catalog())];
    }

    /**
     * Plain-text rendering of a report (non conforming first).
     *
     * @param array $report
     * @return string
     */
    public static function render_text(array $report): string {
        $order = [rules::NAO_CONFORME => 0, rules::ATENCAO => 1, rules::ERRO => 2, rules::NV => 3, rules::NA => 4, rules::CONFORME => 5];
        $sev = ['Bloqueante' => 0, 'Alta' => 1, 'Média' => 2, 'Baixa' => 3, 'Informativa' => 4];
        $results = $report['results'];
        usort($results, fn($a, $b) => [$order[$a['status']] ?? 9, $sev[$a['gravidade']] ?? 9, $a['rule']] <=> [$order[$b['status']] ?? 9, $sev[$b['gravidade']] ?? 9, $b['rule']]);
        $inv = $report['inventory'];
        $lines = [];
        $lines[] = "=== {$report['course']['fullname']} [{$report['course']['shortname']}] (id {$report['course']['id']}) — {$report['engine']} / política {$report['policy_version']} (ano {$report['reoffer_year']})";
        $lines[] = "Período: {$inv['startdate']} → {$inv['enddate']} | categoria: {$inv['category']['name']} | visível: {$inv['visible']} | tempo: {$report['timing']['total_s']}s";
        $lines[] = 'Seções: ' . $inv['sections'] . ' | módulos: ' . $inv['modules'] . ' ' . json_encode($inv['modules_by_type']) . ' | arquivos: ' . $inv['files'] . ' (sem conteúdo: ' . $inv['files_missing_in_storage'] . ')';
        $lines[] = 'Blocos: ' . json_encode($inv['blocks']) . ' | inscrição: ' . json_encode($inv['enrols']);
        $lines[] = 'Resumo: ' . json_encode($report['summary']['por_status']) . ' | não conformes por gravidade: ' . json_encode($report['summary']['nao_conformes_por_gravidade'], JSON_UNESCAPED_UNICODE);
        foreach ($results as $r) {
            if ($r['status'] === rules::CONFORME) {
                continue;
            }
            $lines[] = sprintf('  [%-15s] %-9s %-11s %s', $r['status'], $r['rule'], $r['gravidade'], $r['titulo']);
            $lines[] = '      local: ' . $r['local'];
            $lines[] = '      evidência: ' . mb_substr((string) $r['evidencia'], 0, 400);
            if (!empty($r['encontrado'])) {
                $lines[] = '      encontrado: ' . mb_substr((string) $r['encontrado'], 0, 300);
            }
            if (!empty($r['esperado'])) {
                $lines[] = '      esperado: ' . $r['esperado'];
            }
            if (!empty($r['orientacao'])) {
                $lines[] = '      orientação: ' . $r['orientacao'];
            }
        }
        $conf = array_unique(array_map(fn($r) => $r['rule'], array_filter($results, fn($r) => $r['status'] === rules::CONFORME)));
        sort($conf);
        $lines[] = '  (conformes omitidos: ' . implode(', ', $conf) . ')';
        if ($report['warnings']) {
            $lines[] = '  avisos: ' . json_encode($report['warnings'], JSON_UNESCAPED_UNICODE);
        }
        return implode("\n", $lines) . "\n";
    }
}
