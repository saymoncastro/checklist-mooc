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
 * Rule X-AV-01: Enunciado × configuração (pontos).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xav01 extends base {
    /** @var string */
    const ID = 'X-AV-01';
    /** @var string */
    const CATEGORY = category::ASSESSMENT_GRADES;
    /** @var string */
    const ORIGIN = 'extra:6.3';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.3';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach (helpers::graded($s) as [$m, $gi]) {
            $intro = helpers::norm(helpers::text($m['texts']['intro'] ?? ''));
            if (preg_match('/valor\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*pontos?/u', $intro, $mm)) {
                $declared = (float) str_replace(',', '.', $mm[1]);
                if (abs($declared - $gi['grademax']) > 0.001) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        "enunciado declara {$mm[1]} pontos; nota máxima={$gi['grademax']}",
                        ['cmid' => $m['cmid'],
                        'orientacao' => $this->guidance(), 'check' => 'intro_grade',
                        'subject' => 'item:' . (int) $gi['itemnumber']]
                    );
                }
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Enunciados', 'sem divergências entre enunciado e pontuação', [
            'check' => 'summary'])];
    }
}
