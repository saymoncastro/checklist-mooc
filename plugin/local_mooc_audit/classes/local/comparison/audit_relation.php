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

namespace local_mooc_audit\local\comparison;

use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;

/**
 * Relates the audit results of the source and the target course (specs/003 data-model, AuditItem).
 *
 * Each result gets a location key without internal ids: rule + matched pair (results with a cmid), rule + section
 * number, or rule + location text with ids removed. Results sharing a key are grouped (worst state wins). A "problem"
 * is NAO_CONFORME or ATENCAO; a problem is persistent only when the source was at least as bad (an ATENCAO that became
 * NAO_CONFORME is new in the target). Rules of the offer cycle never produce "persistent" items: their problems in the
 * target are pending items of the new offer, whatever the source state was. No class blames the import mechanism.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_relation {
    /** @var string[] Classes in display order (target side first, then the previous offer). */
    const CLASSES = ['pendencia_oferta', 'novo_no_destino', 'novo_em_atividade_adicional', 'persistente', 'indeterminado',
        'nao_verificavel_no_destino', 'ausente_no_destino', 'resolvido_no_destino', 'nao_aplicavel_no_destino'];

    /**
     * Whether a state is a problem.
     *
     * @param string|null $state
     * @return bool
     */
    public static function is_problem(?string $state): bool {
        return $state === status::NAO_CONFORME || $state === status::ATENCAO;
    }

    /**
     * Location key of a result.
     *
     * @param array $r result (engine format)
     * @param string $side 'source' or 'target'
     * @param array $pairs source cmid => target cmid
     * @param array $reverse target cmid => source cmid
     * @return string
     */
    public static function key(array $r, string $side, array $pairs, array $reverse): string {
        $rule = $r['rule'];
        if (!empty($r['cmid'])) {
            $cmid = (int) $r['cmid'];
            if ($side === 'source') {
                return isset($pairs[$cmid]) ? "$rule|m:$cmid" : "$rule|ms:$cmid";
            }
            return isset($reverse[$cmid]) ? "$rule|m:{$reverse[$cmid]}" : "$rule|mt:$cmid";
        }
        if ($r['section'] !== null && $r['section'] !== '') {
            return "$rule|s:" . (int) $r['section'];
        }
        $local = preg_replace(['/\(id \d+\)/u', '/\(?cmid=\d+\)?/u', '/\bid \d+\b/u'], '', (string) $r['local']);
        return "$rule|t:" . trim(helpers::norm($local));
    }

    /**
     * Group results by key: worst state, its result as representative, evidences joined.
     *
     * @param array $results
     * @param string $side
     * @param array $pairs
     * @param array $reverse
     * @return array key => group
     */
    protected static function group(array $results, string $side, array $pairs, array $reverse): array {
        $out = [];
        foreach ($results as $r) {
            $k = self::key($r, $side, $pairs, $reverse);
            if (!isset($out[$k]) || status::status_rank($r['status']) < status::status_rank($out[$k]['status'])) {
                $ev = $out[$k]['evidences'] ?? [];
                $out[$k] = ['status' => $r['status'], 'result' => $r, 'evidences' => $ev];
            }
            if ($r['evidencia'] !== null && $r['evidencia'] !== '' && $r['status'] !== status::CONFORME) {
                $out[$k]['evidences'][$r['evidencia']] = true;
            }
        }
        return $out;
    }

    /**
     * Relate the two audits.
     *
     * @param array $srcresults results of the source course (its own offer context)
     * @param array $dstresults results of the target course (policy of the new offer)
     * @param array $match matcher::match() output
     * @param string[] $cyclerules ids of the offer-cycle rules
     * @return array [items, counts]
     */
    public static function relate(array $srcresults, array $dstresults, array $match, array $cyclerules): array {
        $pairs = array_map(fn($p) => $p['target'], $match['pairs']);
        $reverse = array_flip($pairs);
        $ambsrc = [];
        $ambtgt = [];
        foreach ($match['ambiguities'] as $amb) {
            foreach ($amb['source'] as $x) {
                $ambsrc[$x['cmid']] = true;
            }
            foreach ($amb['target'] as $x) {
                $ambtgt[$x['cmid']] = true;
            }
        }
        $missing = array_flip($match['missing']);
        $extra = array_flip($match['extra']);

        $gs = self::group($srcresults, 'source', $pairs, $reverse);
        $gt = self::group($dstresults, 'target', $pairs, $reverse);
        $items = [];

        foreach ($gt as $k => $t) {
            $r = $t['result'];
            $s = $gs[$k] ?? null;
            $cycle = in_array($r['rule'], $cyclerules, true);
            $tcmid = $r['cmid'] ? (int) $r['cmid'] : null;
            $class = null;
            if (self::is_problem($t['status'])) {
                if ($cycle) {
                    $class = 'pendencia_oferta';
                } else if ($tcmid && isset($ambtgt[$tcmid])) {
                    $class = 'indeterminado';
                } else if ($tcmid && isset($extra[$tcmid])) {
                    $class = 'novo_em_atividade_adicional';
                } else if (
                    $s && self::is_problem($s['status'])
                        && status::status_rank($s['status']) <= status::status_rank($t['status'])
                ) {
                    $class = 'persistente';
                } else {
                    $class = 'novo_no_destino';
                }
            } else if ($t['status'] === status::NAO_VERIFICAVEL || $t['status'] === status::ERRO) {
                $class = 'nao_verificavel_no_destino';
            } else if ($s && self::is_problem($s['status']) && !$cycle) {
                $class = $t['status'] === status::NAO_APLICAVEL ? 'nao_aplicavel_no_destino' : 'resolvido_no_destino';
            }
            if ($class) {
                $items[] = self::item(
                    $class,
                    $k,
                    $s,
                    $t,
                    $tcmid !== null && isset($reverse[$tcmid]) ? $reverse[$tcmid] : null,
                    $tcmid
                );
            }
        }
        foreach ($gs as $k => $s) {
            $r = $s['result'];
            if (isset($gt[$k]) || !self::is_problem($s['status']) || in_array($r['rule'], $cyclerules, true)) {
                continue;
            }
            $scmid = $r['cmid'] ? (int) $r['cmid'] : null;
            if ($scmid && isset($ambsrc[$scmid])) {
                $class = 'indeterminado';
            } else if ($scmid && isset($missing[$scmid])) {
                $class = 'ausente_no_destino';
            } else {
                $class = 'resolvido_no_destino';
            }
            $items[] = self::item($class, $k, $s, null, $scmid, $scmid && isset($pairs[$scmid]) ? $pairs[$scmid] : null);
        }

        $order = array_flip(self::CLASSES);
        usort($items, fn($x, $y) => [$order[$x['classification']], status::severity_rank($x['severity']), $x['rule'], $x['key']]
            <=> [$order[$y['classification']], status::severity_rank($y['severity']), $y['rule'], $y['key']]);
        $counts = array_fill_keys(self::CLASSES, 0);
        foreach ($items as $i) {
            $counts[$i['classification']]++;
        }
        return [$items, $counts];
    }

    /**
     * One item of the relation.
     *
     * @param string $class
     * @param string $key
     * @param array|null $s source group
     * @param array|null $t target group
     * @param int|null $scmid
     * @param int|null $tcmid
     * @return array
     */
    protected static function item(string $class, string $key, ?array $s, ?array $t, ?int $scmid, ?int $tcmid): array {
        $r = ($t ?? $s)['result'];
        $ev = fn($g) => $g ? (implode(' | ', array_keys($g['evidences'])) ?: $g['result']['evidencia']) : null;
        return [
            'classification' => $class,
            'rule' => $r['rule'],
            'title' => $r['titulo'],
            'category' => $r['category'],
            'severity' => $r['gravidade'],
            'status_source' => $s['status'] ?? null,
            'status_target' => $t['status'] ?? null,
            'local' => $r['local'],
            'cmid_source' => $scmid,
            'cmid_target' => $tcmid,
            'link' => $t ? ($t['result']['link'] ?? null) : null,
            'evidence_source' => $ev($s),
            'evidence_target' => $ev($t),
            'guidance' => $t ? ($t['result']['orientacao'] ?? null) : null,
            'key' => $key,
        ];
    }
}
