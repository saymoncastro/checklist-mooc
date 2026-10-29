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
 * Rule CL-07: Data de término correta (31/12 do ano da reoferta).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl07 extends base {
    /** @var string */
    const ID = 'CL-07';
    /** @var string */
    const CATEGORY = category::OFFER_ENROL;
    /** @var string */
    const ORIGIN = 'checklist:7';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = ['reoffer_year', 'allowed_end_dates'];
    /** @var string */
    const LOCUS = self::LOCUS_COURSE_SETTINGS;
    /** @var string */
    const SOURCE = 'Checklist item 7; decisão institucional 3 (término 31/12)';
    /** @var bool Depends on the dates/category/enrolment of the offer (specs/003). */
    const OFFER_CYCLE = true;

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $ed = $s['course']['enddate'];
        if (!$ed) {
            return [$this->f(
                status::NAO_CONFORME,
                'Configurações do curso > Data de término',
                'não definida (0)',
                ['check' => 'enddate']
            )];
        }
        $dm = userdate($ed, '%d/%m', 99, false);
        $year = (int) userdate($ed, '%Y', 99, false);
        $ok = in_array($dm, $p['allowed_end_dates']) && $year === (int) $p['reoffer_year'];
        return [$this->f(
            $ok ? status::CONFORME : status::NAO_CONFORME,
            'Configurações do curso > Data de término',
            'término=' . helpers::d($ed),
            ['esperado' => implode('|', $p['allowed_end_dates']) . "/{$p['reoffer_year']}", 'orientacao' => $this->guidance(),
            'check' => 'enddate']
        )];
    }
}
