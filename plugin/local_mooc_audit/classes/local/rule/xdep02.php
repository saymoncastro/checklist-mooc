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
 * Rule X-DEP-02: Condição exige conclusão de módulo sem conclusão ou oculto.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xdep02 extends base {
    /** @var string */
    const ID = 'X-DEP-02';
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
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.4';
    /** @var bool Reads completion tracking settings. */
    const REQUIRES_COMPLETION = true;
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
                if (($c['type'] ?? '') !== 'completion' || !isset($s['modules'][(int) $c['cm']])) {
                    continue;
                }
                $t = $s['modules'][(int) $c['cm']];
                if ($t['completion'] == 0) {
                    $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'exige conclusão de ' . helpers::label($t)
                        . ' que NÃO tem acompanhamento de conclusão', ['cmid' => $m['cmid'],
                        'orientacao' => $this->guidance(), 'check' => 'target_completion', 'subject' => 'cm:' . $t['cmid']]);
                } else if ($t['visible'] == 0) {
                    $out[] = $this->f(status::ATENCAO, helpers::label($m), 'exige conclusão de módulo OCULTO '
                        . helpers::label($t), ['cmid' => $m['cmid'], 'gravidade' => status::ALTA,
                        'check' => 'target_completion', 'subject' => 'cm:' . $t['cmid']]);
                }
                if (in_array($c['e'] ?? 1, [2, 3]) && !$t['grade_items']) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        'exige conclusão com aprovação/reprovação de módulo sem nota ' . helpers::label($t),
                        ['cmid' => $m['cmid'], 'check' => 'target_grade', 'subject' => 'cm:' . $t['cmid']]
                    );
                }
            }
        }
        return $out ?: [$this->f(
            status::CONFORME,
            'Restrições de acesso',
            'todas as condições de conclusão apontam para módulos com conclusão habilitada e visíveis',
            ['check' => 'summary']
        )];
    }
}
