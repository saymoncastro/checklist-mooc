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
 * Rule CL-18: Glossário: proibido se colaborativo; vazio se fonte de jogos.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl18 extends base {
    /** @var string */
    const ID = 'CL-18';
    /** @var string */
    const CATEGORY = category::STRUCTURE_BLOCKS;
    /** @var string */
    const ORIGIN = 'checklist:18';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['forbidden_modules'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 18; decisão institucional 4 (glossário vazio fonte de jogos = bloqueante)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach ($p['forbidden_modules'] as $mod) {
            $gs = helpers::mods($s, $mod);
            if (!$gs) {
                $out[] = $this->f(status::CONFORME, 'Atividades do curso', "nenhum módulo $mod", [
                    'check' => 'forbidden', 'subject' => $mod]);
            }
            foreach ($gs as $g) {
                $deps = array_filter(helpers::mods($s, 'game'), fn($m) => ($m['extra']['sourcemodule'] ?? '') === 'glossary'
                    && (int) ($m['extra']['glossaryid'] ?? 0) === $g['instanceid']);
                $entries = (int) ($g['extra']['entries'] ?? 0);
                if ($deps) {
                    $names = array_map(fn($m) => helpers::label($m) . ' [entradas na categoria: '
                        . ($m['extra']['glossary_category_entries'] ?? 'n/a') . ']', $deps);
                    $st = $entries === 0 ? status::NAO_CONFORME : status::ATENCAO;
                    $out[] = $this->f(
                        $st,
                        helpers::label($g),
                        "glossário com $entries entradas; fonte de " . count($deps) . ' jogos: ' . implode('; ', $names),
                        ['cmid' => $g['cmid'], 'gravidade' => $entries === 0 ? status::BLOQUEANTE : status::MEDIA,
                            'esperado' => 'entradas disponíveis para os jogos',
                        'orientacao' => $this->g('repopulate'),
                        'locus' => self::LOCUS_MODULE_VIEW, 'check' => 'forbidden']
                    );
                } else {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($g),
                        "módulo $mod presente ($entries entradas) sem uso por jogos",
                        ['cmid' => $g['cmid'],
                        'orientacao' => $this->guidance(), 'check' => 'forbidden']
                    );
                }
            }
        }
        return $out;
    }
}
