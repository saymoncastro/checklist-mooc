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
use local_mooc_audit\local\category;

/**
 * Rule X-STR-01: Módulos ocultos, invisíveis ao usuário ou com nome vazio.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xstr01 extends base {
    /** @var string */
    const ID = 'X-STR-01';
    /** @var string */
    const CATEGORY = category::STRUCTURE_BLOCKS;
    /** @var string */
    const ORIGIN = 'extra:6.7';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::BAIXA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.7';

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
            if ($m['visible'] == 0) {
                $out[] = $this->f(status::ATENCAO, helpers::label($m), 'módulo oculto' . ($m['visibleoncoursepage'] == 0
                    ? ' (stealth)' : ''), ['cmid' => $m['cmid'], 'orientacao' => $this->guidance(),
                    'check' => 'visibility']);
            } else if ($m['uservisible'] === false && $s['viewer_userid']) {
                $out[] = $this->f(status::ATENCAO, helpers::label($m), 'indisponível para o usuário ' . $s['viewer_userid']
                    . ': ' . helpers::text($m['availableinfo']), ['cmid' => $m['cmid'], 'gravidade' => status::INFORMATIVA,
                    'check' => 'visibility']);
            }
            if ($m['modname'] !== 'label' && helpers::text($m['name']) === '') {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'nome vazio', ['cmid' => $m['cmid'],
                    'orientacao' => $this->g('name'), 'check' => 'name']);
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Estrutura', 'sem módulos ocultos ou sem nome', ['check' => 'summary'])];
    }
}
