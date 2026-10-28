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
 * Rule CL-06: Data de início correta (01/01 do ano da reoferta).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl06 extends base {
    /** @var string */
    const ID = 'CL-06';
    /** @var string */
    const CATEGORY = category::OFFER_ENROL;
    /** @var string */
    const ORIGIN = 'checklist:6';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = ['reoffer_year', 'required_start_date', 'min_days_between_start_and_end'];
    /** @var string */
    const LOCUS = self::LOCUS_COURSE_SETTINGS;
    /** @var string */
    const SOURCE = 'Checklist item 6; decisões institucionais 3 e 6 (início 01/01 do ano da reoferta)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $sd = $s['course']['startdate'];
        $ed = $s['course']['enddate'];
        if (!$sd) {
            return [$this->f(status::NAO_CONFORME, 'Configurações do curso > Data de início', 'não definida')];
        }
        $probs = [];
        $year = (int) userdate($sd, '%Y', 99, false);
        $dm = userdate($sd, '%d/%m', 99, false);
        if ($year !== (int) $p['reoffer_year']) {
            $probs[] = "ano=$year ≠ {$p['reoffer_year']}";
        }
        if ($p['required_start_date'] && $dm !== $p['required_start_date']) {
            $probs[] = "dia/mês=$dm ≠ {$p['required_start_date']}";
        }
        if ($ed && ($ed - $sd) < $p['min_days_between_start_and_end'] * DAYSECS) {
            $probs[] = 'menos de ' . $p['min_days_between_start_and_end'] . ' dias antes do fim';
        }
        return [$this->f($probs ? status::NAO_CONFORME : status::CONFORME, 'Configurações do curso > Data de início',
            'início=' . helpers::d($sd) . ' fim=' . helpers::d($ed), ['encontrado' => implode('; ', $probs) ?: 'ok',
            'esperado' => "{$p['required_start_date']}/{$p['reoffer_year']}", 'orientacao' => $this->guidance()])];
        }
}
