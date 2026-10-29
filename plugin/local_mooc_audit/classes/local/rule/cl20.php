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
 * Rule CL-20: Seções: ocultas invisíveis, sem seções vazias ou com nome genérico.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl20 extends base {
    /** @var string */
    const ID = 'CL-20';
    /** @var string */
    const CATEGORY = category::STRUCTURE_BLOCKS;
    /** @var string */
    const ORIGIN = 'checklist:20';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::BAIXA;
    /** @var string[] */
    const POLICY_KEYS = ['generic_section_names'];
    /** @var string */
    const LOCUS = self::LOCUS_COURSE_SETTINGS;
    /** @var string */
    const SOURCE = 'Checklist item 20; relatório de investigação §6.7 (X-STR-01/02)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $hs = $s['course']['formatoptions']['hiddensections'] ?? null;
        $out[] = $this->f(
            (string) $hs === '1' ? status::CONFORME : status::NAO_CONFORME,
            'Configurações do curso > Formato > Seções escondidas',
            "hiddensections=" . var_export($hs, true),
            ['esperado' => '1 (completamente invisíveis)']
        );
        foreach ($s['sections'] as $sec) {
            if ($sec['number'] == 0) {
                continue;
            }
            $mods = array_filter($sec['sequence'], fn($c) => isset($s['modules'][$c]));
            $lbl = "Seção {$sec['number']} \"{$sec['name']}\"";
            if (!$mods) {
                $out[] = $this->f(status::ATENCAO, $lbl, 'seção sem módulos', ['orientacao' => $this->g('emptysection'),
                    'locus' => self::LOCUS_SECTION, 'section' => $sec['number']]);
            } else if (count(array_filter($mods, fn($c) => $s['modules'][$c]['modname'] !== 'label')) === 0) {
                $out[] = $this->f(status::ATENCAO, $lbl, 'seção só com rótulos', ['gravidade' => status::BAIXA,
                    'locus' => self::LOCUS_SECTION, 'section' => $sec['number']]);
            }
            if ($sec['rawname'] !== null && preg_match($p['generic_section_names'], helpers::norm($sec['rawname']))) {
                $out[] = $this->f(
                    status::ATENCAO,
                    $lbl,
                    'nome genérico "' . $sec['rawname'] . '"',
                    ['orientacao' => $this->g('genericname'), 'locus' => self::LOCUS_SECTION, 'section' => $sec['number']]
                );
            }
            if (!$sec['visible'] && array_filter($mods, fn($c) => $s['modules'][$c]['completion'])) {
                $out[] = $this->f(
                    status::ATENCAO,
                    $lbl,
                    'seção oculta com atividades que têm conclusão',
                    ['gravidade' => status::MEDIA, 'locus' => self::LOCUS_SECTION, 'section' => $sec['number']]
                );
            }
        }
        return $out;
    }
}
