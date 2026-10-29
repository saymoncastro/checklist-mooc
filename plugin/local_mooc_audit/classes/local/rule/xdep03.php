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
 * Rule X-DEP-03: Condições de nota apontando para itens inexistentes.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xdep03 extends base {
    /** @var string */
    const ID = 'X-DEP-03';
    /** @var string */
    const CATEGORY = category::COMPLETION_RESTRICTIONS;
    /** @var string */
    const ORIGIN = 'extra:6.4';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.4';
    /** @var bool Reads access restrictions. */
    const REQUIRES_AVAILABILITY = true;

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') === 'grade' && !isset($s['grade']['all_items'][(int) ($c['id'] ?? 0)])) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        'condição de nota aponta para item inexistente id=' . ($c['id'] ?? 'null'),
                        ['cmid' => $m['cmid']]
                    );
                }
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Restrições de acesso', 'todas as condições de nota resolvidas')];
    }
}
