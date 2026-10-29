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

namespace local_mooc_audit\local\rule;

use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;
use local_mooc_audit\snapshot;
use local_mooc_audit\local\category;

/**
 * Rule X-DEP-04: Ciclos no grafo de dependências de conclusão.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xdep04 extends base {
    /** @var string */
    const ID = 'X-DEP-04';
    /** @var string */
    const CATEGORY = category::COMPLETION_RESTRICTIONS;
    /** @var string */
    const ORIGIN = 'extra:6.4';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::BLOQUEANTE;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_NONE;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.4';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $g = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') === 'completion') {
                    $g[$m['cmid']][] = (int) $c['cm'];
                }
            }
        }
        $color = [];
        $cycles = [];
        $dfs = function ($u, $path) use (&$dfs, &$color, &$cycles, $g) {
            $color[$u] = 1;
            $path[] = $u;
            foreach ($g[$u] ?? [] as $v) {
                if (($color[$v] ?? 0) === 1) {
                    $cycles[] = array_merge(array_slice($path, array_search($v, $path)), [$v]);
                } else if (($color[$v] ?? 0) === 0) {
                    $dfs($v, $path);
                }
            }
            $color[$u] = 2;
        };
        foreach (array_keys($g) as $u) {
            if (($color[$u] ?? 0) === 0) {
                $dfs($u, []);
            }
        }
        if ($cycles) {
            return array_map(fn($c) => $this->f(
                status::NAO_CONFORME,
                'Grafo de restrições',
                'ciclo: ' . implode(' → ', $c),
                ['orientacao' => $this->guidance()]
            ), $cycles);
        }
        $edges = array_sum(array_map('count', $g));
        return [$this->f(status::CONFORME, 'Grafo de restrições', count($g)
            . " módulos com condições, $edges arestas, sem ciclos")];
    }
}
