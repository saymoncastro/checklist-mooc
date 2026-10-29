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

use local_mooc_audit\local\comparison\audit_relation;
use local_mooc_audit\local\comparison\differ;
use local_mooc_audit\local\comparison\matcher;
use local_mooc_audit\local\locator;
use local_mooc_audit\local\status;

/**
 * Comparison between two offers of a course: source (previous offer) and target (new offer). Read-only.
 *
 * Works on the snapshots of the two courses and on the results of the existing rule catalogue (no second audit
 * engine): modules are matched without internal ids (local\comparison\matcher), differences are classified as
 * expected, relevant or informative (local\comparison\differ) and the two audits are related (local\comparison\
 * audit_relation). The target is audited with the current policy, exactly as the individual audit; the source with
 * the same policy but the reoffer year of its own start date, so that a course correct for its year is not reported
 * as carrying problems of the next offer. Result: contract "comparison" 1.0 (schema/comparison-v1.0.schema.json).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparator {
    /** @var string Name of the contract. */
    const CONTRACT = 'comparison';
    /** @var string Version of the comparison contract (bumped only on incompatible changes). */
    const CONTRACT_VERSION = '1.0';

    /**
     * Ids of the offer-cycle rules (rule metadata OFFER_CYCLE): their problems in the target are pending items of the
     * new offer, whatever the source state was.
     *
     * @return string[]
     */
    public static function cycle_rules(): array {
        $out = [];
        foreach (local\catalog::RULES as $class) {
            if ($class::OFFER_CYCLE) {
                $out[] = $class::ID;
            }
        }
        return $out;
    }

    /**
     * Who may open the comparison page: 'site' (capability at system level: page in the site administration),
     * 'courses' (capability in some course only, e.g. a category manager: plain admin page) or null (refused).
     *
     * @param int|null $userid default: current user
     * @return string|null
     */
    public static function page_access(?int $userid = null): ?string {
        if (has_capability('local/mooc_audit:compare', \context_system::instance(), $userid)) {
            return 'site';
        }
        return get_user_capability_course('local/mooc_audit:compare', $userid, true, '', '', 1) ? 'courses' : null;
    }

    /**
     * Require the comparison capability in BOTH course contexts (allowed on one room only is not enough). A missing
     * course gets the same exception, so that the answer does not reveal whether a course id exists.
     *
     * @param int $sourceid
     * @param int $targetid
     * @throws \required_capability_exception
     */
    public static function require_access(int $sourceid, int $targetid): void {
        global $DB;
        foreach ([$sourceid, $targetid] as $id) {
            if ($id == SITEID || !$DB->record_exists('course', ['id' => $id])) {
                // Same answer as a missing capability: the page does not reveal which course ids exist.
                throw new \required_capability_exception(
                    \context_system::instance(),
                    'local/mooc_audit:compare',
                    'nopermissions',
                    ''
                );
            }
            require_capability('local/mooc_audit:compare', \context_course::instance($id));
        }
    }

    /**
     * Compare two courses by id: snapshots, audits of both sides, structural comparison and relation.
     *
     * @param int $sourceid course of the previous offer
     * @param int $targetid course of the new offer
     * @param array $manual list of [source cmid, target cmid] chosen by the user (not persisted)
     * @param array|null $policyoverride policy values applied to both sides (the source year still comes from its start)
     * @return array contract comparison 1.0
     */
    public static function run(int $sourceid, int $targetid, array $manual = [], ?array $policyoverride = null): array {
        $t0 = microtime(true);
        $src = snapshot::build($sourceid, 0);
        $t1 = microtime(true);
        $dst = snapshot::build($targetid, 0);
        $t2 = microtime(true);

        $tpolicy = policy::load($policyoverride);
        $syear = $src['course']['startdate'] ? (int) userdate(
            $src['course']['startdate'],
            '%Y',
            \core_date::get_server_timezone(),
            false
        ) : null;
        $spolicy = policy::load(array_merge($policyoverride ?? [], ['reoffer_year' => $syear ?? (int) $tpolicy['reoffer_year']]));
        $sresults = self::results($src, $spolicy);
        $tresults = self::results($dst, $tpolicy);
        $t3 = microtime(true);

        $report = self::compare($src, $dst, $manual);
        [$items, $counts] = audit_relation::relate(
            $syear === null ? array_values(array_filter(
                $sresults,
                fn($r) => !in_array($r['rule'], self::cycle_rules(), true)
            )) : $sresults,
            $tresults,
            $report['match'],
            self::cycle_rules()
        );
        unset($report['match']);
        $t4 = microtime(true);

        $report['source']['overall_status'] = engine::summarize($sresults)['overall_status'];
        $report['target']['overall_status'] = engine::summarize($tresults)['overall_status'];
        $report['policy'] = [
            'version' => $tpolicy['policy_version'],
            'target_reoffer_year' => (int) $tpolicy['reoffer_year'],
            'source_reoffer_year' => $syear,
            'source_basis' => $syear === null ? 'sem_contexto' : 'startdate',
            'cycle_rules' => self::cycle_rules(),
            'note' => get_string(
                $syear === null ? 'cmp_policy_note_nocontext' : 'cmp_policy_note',
                'local_mooc_audit',
                (object) ['source' => $syear, 'target' => $tpolicy['reoffer_year'], 'version' => $tpolicy['policy_version']]
            ),
        ];
        $report['audit'] = ['items' => $items, 'counts' => $counts, 'source_policy_version' => $spolicy['policy_version'],
            'target_policy_version' => $tpolicy['policy_version']];
        foreach (array_unique(array_merge($tpolicy['warnings'], $spolicy['warnings'])) as $w) {
            $report['warnings'][] = get_string('label_policy', 'local_mooc_audit') . ': ' . $w;
        }
        foreach (['source' => $src, 'target' => $dst] as $side => $s) {
            foreach ($s['warnings'] as $w) {
                $report['warnings'][] = get_string("cmp_side_$side", 'local_mooc_audit') . ': '
                    . (is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE));
            }
        }
        $report['summary'] = self::summary($report);
        unset($report['expected_ids'], $report['possibly_inverted']);
        $report['timing'] = [
            'snapshot_source_s' => round($t1 - $t0, 3),
            'snapshot_target_s' => round($t2 - $t1, 3),
            'rules_s' => round($t3 - $t2, 3),
            'compare_s' => round($t4 - $t3, 3),
            'total_s' => round(microtime(true) - $t0, 3),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ];
        return $report;
    }

    /**
     * Audit results of a snapshot with links to the course (same as engine::audit, without building it again).
     *
     * @param array $s snapshot
     * @param array $policy
     * @return array
     */
    protected static function results(array $s, array $policy): array {
        $results = engine::run_rules($s, $policy);
        foreach ($results as &$r) {
            $url = locator::url_for($r, $s['course']['id'], $s['modules'], $s['sections']);
            $r['link'] = $url ? $url->out(false) : null;
        }
        return $results;
    }

    /**
     * Structural comparison of two snapshots (no audit): matches, missing, extra, ambiguities, course differences.
     *
     * @param array $src
     * @param array $dst
     * @param array $manual list of [source cmid, target cmid]
     * @return array partial contract plus 'match' (raw matcher output, used by run())
     */
    public static function compare(array $src, array $dst, array $manual = []): array {
        $match = matcher::match($src, $dst, $manual);
        $map = array_map(fn($p) => $p['target'], $match['pairs']);
        $ctx = differ::context($src, $dst, $map);

        $matches = [];
        foreach ($match['pairs'] as $scm => $p) {
            $a = $src['modules'][$scm];
            $b = $dst['modules'][$p['target']];
            [$diffs, $expected] = differ::module_pair($a, $b, $ctx);
            usort($diffs, fn($x, $y) => differ::KIND_ORDER[$x['kind']] <=> differ::KIND_ORDER[$y['kind']]);
            $relevance = differ::relevance($diffs);
            $matches[] = [
                'source_cmid' => (int) $scm,
                'target_cmid' => (int) $p['target'],
                'modname' => $a['modname'],
                'source_name' => (string) $a['name'],
                'target_name' => (string) $b['name'],
                'source_section' => (int) $a['sectionnumber'],
                'target_section' => (int) $b['sectionnumber'],
                'strategy' => $p['strategy'],
                'confidence' => $p['confidence'],
                'state' => in_array($relevance, [differ::RELEVANT, differ::INFO], true) ? 'modificada' : 'preservada',
                'relevance' => $relevance,
                'problematic' => $relevance === differ::RELEVANT,
                'diffs' => $diffs,
                'expected_count' => $expected,
            ];
        }
        usort($matches, fn($x, $y) => [$x['source_section'], matcher::position($src, $src['modules'][$x['source_cmid']])]
            <=> [$y['source_section'], matcher::position($src, $src['modules'][$y['source_cmid']])]);

        $ref = fn(array $m) => ['cmid' => (int) $m['cmid'], 'modname' => $m['modname'], 'name' => (string) $m['name'],
            'section' => (int) $m['sectionnumber']];
        $missing = array_map(fn($c) => $ref($src['modules'][$c]), $match['missing']);
        $pairedbyname = [];
        foreach ($match['pairs'] as $p) {
            $pairedbyname[matcher::key_name($dst['modules'][$p['target']])] = (int) $p['target'];
        }
        $extra = [];
        foreach ($match['extra'] as $c) {
            $m = $dst['modules'][$c];
            [$kind, $code] = differ::extra_kind($m);
            $e = $ref($m) + ['kind' => $kind, 'code' => $code];
            if (isset($pairedbyname[matcher::key_name($m)])) {
                $e['duplicate_of'] = $pairedbyname[matcher::key_name($m)];
            }
            $extra[] = $e;
        }
        $ambiguities = array_map(fn($a) => $a + ['explanation' => get_string(
            'cmp_amb_' . $a['reason'],
            'local_mooc_audit',
            (object) ['n' => count($a['source']), 'm' => count($a['target'])]
        )], $match['ambiguities']);

        [$coursediffs, $ids] = differ::course($src, $dst, $ctx);
        $warnings = [];
        foreach ($match['manual'] as $mp) {
            if (!$mp['accepted']) {
                $warnings[] = get_string('cmp_manual_rejected', 'local_mooc_audit', (object) ['source' => $mp['source_cmid'],
                    'target' => $mp['target_cmid'], 'reason' => get_string('cmp_manual_' . $mp['reason'], 'local_mooc_audit')]);
            }
        }
        [$sstart, $tstart] = [$src['course']['startdate'], $dst['course']['startdate']];
        $inverted = $sstart && $tstart && $sstart > $tstart;
        if ($inverted) {
            $warnings[] = get_string('cmp_inverted', 'local_mooc_audit');
        }

        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'engine' => 'local_mooc_audit ' . engine::VERSION,
            'executed_at' => date('c'),
            'timing' => [],
            'source' => self::side($src),
            'target' => self::side($dst),
            'policy' => null,
            'summary' => [],
            'matches' => $matches,
            'missing_in_target' => $missing,
            'extra_in_target' => $extra,
            'ambiguities' => $ambiguities,
            'course_diffs' => $coursediffs,
            'audit' => ['items' => [], 'counts' => [], 'source_policy_version' => '', 'target_policy_version' => ''],
            'manual_pairs' => $match['manual'],
            'warnings' => $warnings,
            'expected_ids' => $ids,
            'possibly_inverted' => $inverted,
            'match' => $match,
        ];
    }

    /**
     * Identification of one side.
     *
     * @param array $s snapshot
     * @return array
     */
    protected static function side(array $s): array {
        $inv = engine::inventory($s);
        return [
            'id' => (int) $s['course']['id'],
            'shortname' => (string) $s['course']['shortname'],
            'fullname' => (string) $s['course']['fullname'],
            'startdate' => $inv['startdate'],
            'enddate' => $inv['enddate'],
            'category' => $s['course']['category']['name'],
            'sections' => $inv['sections'],
            'modules' => $inv['modules'],
            'modules_by_type' => (object) $inv['modules_by_type'],
            'overall_status' => status::OVERALL_CLEAN,
        ];
    }

    /**
     * Counts and verdict.
     *
     * @param array $r report
     * @return array
     */
    protected static function summary(array $r): array {
        $byconf = [];
        $modified = 0;
        $relevant = 0;
        foreach ($r['matches'] as $m) {
            $byconf[$m['confidence']] = ($byconf[$m['confidence']] ?? 0) + 1;
            $modified += $m['state'] === 'modificada' ? 1 : 0;
            $relevant += $m['problematic'] ? 1 : 0;
        }
        $coursediffs = count(array_filter($r['course_diffs'], fn($d) => $d['kind'] === differ::RELEVANT));
        $extrarelevant = count(array_filter($r['extra_in_target'], fn($e) => $e['kind'] === differ::RELEVANT));
        $duplicates = count(array_filter($r['ambiguities'], fn($a) => $a['reason'] === 'duplicado_no_destino'))
            + count(array_filter($r['extra_in_target'], fn($e) => isset($e['duplicate_of'])));
        $ambsrc = array_sum(array_map(fn($a) => count($a['source']), $r['ambiguities']));

        $tofix = false;
        $tocheck = false;
        foreach ($r['audit']['items'] as $i) {
            $target = in_array($i['classification'], ['pendencia_oferta', 'novo_no_destino', 'novo_em_atividade_adicional',
                'persistente', 'indeterminado'], true);
            $tofix = $tofix || $i['classification'] === 'pendencia_oferta' || ($target
                && $i['status_target'] === status::NAO_CONFORME && $i['classification'] !== 'indeterminado');
            $tocheck = $tocheck || $target || $i['classification'] === 'nao_verificavel_no_destino';
        }
        $informative = (bool) array_filter($r['matches'], fn($m) => $m['relevance'] === differ::INFO)
            || (bool) array_filter($r['course_diffs'], fn($d) => $d['kind'] === differ::INFO) || $r['extra_in_target'];
        if ($r['missing_in_target'] || $relevant || $coursediffs || $extrarelevant || $tofix) {
            $verdict = 'REVISAR';
        } else if ($r['ambiguities'] || $tocheck || $informative) {
            $verdict = 'CONFERIR';
        } else {
            $verdict = 'SEM_DIFERENCAS_RELEVANTES';
        }
        ksort($byconf);
        return [
            'source_modules' => $r['source']['modules'],
            'target_modules' => $r['target']['modules'],
            'preserved' => count($r['matches']) - $modified,
            'modified' => $modified,
            'modified_relevant' => $relevant,
            'missing_in_target' => count($r['missing_in_target']),
            'extra_in_target' => count($r['extra_in_target']),
            'ambiguous_groups' => count($r['ambiguities']),
            'ambiguous_source_modules' => $ambsrc,
            'duplicates_in_target' => $duplicates,
            'matches_by_confidence' => (object) $byconf,
            'course_diffs_relevant' => $coursediffs,
            'expected_identifier_changes' => ($r['expected_ids'] ?? 0) + array_sum(array_column($r['matches'], 'expected_count')),
            'audit_counts' => (object) $r['audit']['counts'],
            'possibly_inverted' => (bool) ($r['possibly_inverted'] ?? false),
            'verdict' => $verdict,
        ];
    }

    /**
     * Parse manual pairs given as "SRC-TGT" strings (URL or CLI); malformed entries are dropped.
     *
     * @param string[] $raw
     * @return array list of [source cmid, target cmid]
     */
    public static function parse_pairs(array $raw): array {
        $out = [];
        foreach ($raw as $p) {
            if (is_string($p) && preg_match('/^(\d+)-(\d+)$/', trim($p), $m)) {
                $out[] = [(int) $m[1], (int) $m[2]];
            }
        }
        return $out;
    }

    /**
     * JSON serialisation (contract comparison 1.0): the single encoding used by the page and the CLI.
     *
     * @param array $report
     * @return string
     */
    public static function to_json(array $report): string {
        unset($report['expected_ids'], $report['possibly_inverted']);
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Plain-text rendering (CLI), sections in the order of the page. Labels come from the language pack.
     *
     * @param array $r
     * @return string
     */
    public static function render_text(array $r): string {
        $str = fn(string $key, $a = null) => get_string($key, 'local_mooc_audit', $a);
        $j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $s = $r['source'];
        $t = $r['target'];
        $l = [];
        $l[] = "=== {$str('comparemenu')}: {$s['fullname']} [{$s['shortname']}] (id {$s['id']}) → {$t['fullname']}"
            . " [{$t['shortname']}] (id {$t['id']}) — {$r['engine']}";
        $l[] = "{$str('cmp_side_source')}: {$s['startdate']} → {$s['enddate']} | {$s['category']} | {$s['modules']}"
            . " {$str('label_modules')} | " . $str('overall_' . strtolower($s['overall_status']));
        $l[] = "{$str('cmp_side_target')}: {$t['startdate']} → {$t['enddate']} | {$t['category']} | {$t['modules']}"
            . " {$str('label_modules')} | " . $str('overall_' . strtolower($t['overall_status']));
        $l[] = "{$str('label_policy')}: {$r['policy']['note']}";
        $l[] = "{$str('cmp_verdict')}: " . $str('cmp_verdict_' . strtolower($r['summary']['verdict'])) . ' | '
            . $j(array_diff_key($r['summary'], ['verdict' => 1]));
        $l[] = "--- {$str('cmp_section_pending')}";
        foreach ($r['audit']['items'] as $i) {
            $l[] = sprintf(
                '  [%s] %-9s %-11s %s',
                $str('cmp_class_' . $i['classification']),
                $i['rule'],
                $i['severity'] ?? '',
                $i['title']
            );
            $l[] = "      {$str('label_local')}: {$i['local']}" . ($i['link'] ? ' → ' . $i['link'] : '');
            $l[] = "      {$str('cmp_status')}: " . ($i['status_source'] ?? '—') . ' → ' . ($i['status_target'] ?? '—');
            if ($i['evidence_source'] !== null) {
                $l[] = "      {$str('cmp_evidence_source')}: {$i['evidence_source']}";
            }
            if ($i['evidence_target'] !== null) {
                $l[] = "      {$str('cmp_evidence_target')}: {$i['evidence_target']}";
            }
        }
        $l[] = "--- {$str('cmp_section_missing')}";
        foreach ($r['missing_in_target'] as $m) {
            $l[] = "  {$m['modname']} \"{$m['name']}\" ({$str('label_section')} {$m['section']}, cmid {$m['cmid']})";
        }
        $l[] = "--- {$str('cmp_section_ambiguous')}";
        foreach ($r['ambiguities'] as $a) {
            $l[] = "  {$a['modname']} \"{$a['name']}\": {$a['explanation']} " . $j(['origem' => array_column($a['source'], 'cmid'),
                'destino' => array_column($a['target'], 'cmid'), 'configuracoes_iguais' => $a['settings_identical']]);
        }
        $l[] = "--- {$str('cmp_section_modified')}";
        foreach ($r['matches'] as $m) {
            if ($m['state'] !== 'modificada') {
                continue;
            }
            $l[] = "  [{$str('cmp_kind_' . $m['relevance'])}] {$m['modname']} \"{$m['source_name']}\" ({$m['source_cmid']} → "
                . "{$m['target_cmid']}, {$str('cmp_strategy_' . $m['strategy'])}, {$str('cmp_conf_' . $m['confidence'])})";
            foreach ($m['diffs'] as $d) {
                $l[] = "      [{$str('cmp_kind_' . $d['kind'])}] {$d['field']}: " . differ::show($d['source']) . ' → '
                    . differ::show($d['target']) . " — {$d['explanation']}";
            }
        }
        $l[] = "--- {$str('cmp_section_extra')}";
        foreach ($r['extra_in_target'] as $m) {
            $l[] = "  [{$str('cmp_kind_' . $m['kind'])}] {$m['modname']} \"{$m['name']}\" ({$str('label_section')} "
                . "{$m['section']}, cmid {$m['cmid']})" . (isset($m['duplicate_of']) ? ' ' . $str(
                    'cmp_duplicate_of',
                    $m['duplicate_of']
                ) : '');
        }
        $l[] = "--- {$str('cmp_section_course')}";
        foreach ($r['course_diffs'] as $d) {
            $l[] = "  [{$str('cmp_kind_' . $d['kind'])}] {$d['scope']}.{$d['field']}: " . differ::show($d['source']) . ' → '
                . differ::show($d['target']) . " — {$d['explanation']}";
        }
        $l[] = "--- {$str('cmp_section_preserved')}";
        $hows = [];
        foreach ($r['matches'] as $m) {
            $k = $str('cmp_strategy_' . $m['strategy']) . ' / ' . $str('cmp_conf_' . $m['confidence']);
            $hows[$k] = ($hows[$k] ?? 0) + 1;
        }
        $l[] = '  ' . $j($hows);
        foreach ($r['warnings'] as $w) {
            $l[] = "  {$str('cmp_section_warnings')}: $w";
        }
        return implode("\n", $l) . "\n";
    }
}
