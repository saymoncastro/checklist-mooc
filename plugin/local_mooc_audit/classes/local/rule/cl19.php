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
 * Rule CL-19: Rótulos sem conclusão de atividade.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl19 extends base {
    /** @var string */
    const ID = 'CL-19';
    /** @var string */
    const CATEGORY = category::COMPLETION_RESTRICTIONS;
    /** @var string */
    const ORIGIN = 'checklist:19';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::BAIXA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 19';
    /** @var bool Reads completion tracking settings. */
    const REQUIRES_COMPLETION = true;

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach (helpers::mods($s, 'label') as $m) {
            if ($m['completion'] != 0) {
                $out[] = $this->f(
                    status::NAO_CONFORME,
                    helpers::label($m),
                    "completion={$m['completion']}",
                    ['cmid' => $m['cmid'], 'esperado' => '0', 'orientacao' => $this->guidance(), 'check' => 'completion']
                );
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Rótulos', 'nenhum rótulo com conclusão habilitada', [
            'check' => 'summary'])];
    }
}
