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
 * Rule CL-09: Fórum institucional "Espaço de interação" presente e sem conclusão.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl09 extends base {
    /** @var string */
    const ID = 'CL-09';
    /** @var string */
    const CATEGORY = category::INSTITUTIONAL_RESOURCES;
    /** @var string */
    const ORIGIN = 'checklist:9';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['required_forum_name'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 9; decisão institucional 10 (nome canônico, pendente)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $f = helpers::mods($s, 'forum', $p['required_forum_name']);
        if (!$f) {
            $names = array_map(fn($m) => $m['name'], helpers::mods($s, 'forum'));
            return [$this->f(
                status::NAO_CONFORME,
                'Fóruns do curso',
                'nenhum fórum com nome ~ "' . $p['required_forum_name'] . '"; existentes: ' . json_encode(
                    $names,
                    JSON_UNESCAPED_UNICODE
                ),
                ['orientacao' => $this->g('import'), 'locus' => self::LOCUS_IMPORT, 'check' => 'forum_presence']
            )];
        }
        $m = $f[0];
        $out = [$this->f(status::CONFORME, helpers::label($m), 'presente', ['cmid' => $m['cmid'],
            'check' => 'forum_presence'])];
        if ($m['completion'] != 0) {
            $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), "completion={$m['completion']}", ['cmid' => $m['cmid'],
                'esperado' => 'conclusão desabilitada',
                'orientacao' => $this->guidance(), 'check' => 'forum_completion']);
        }
        return $out;
    }
}
