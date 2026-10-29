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
 * Rule CL-05: Categoria do curso = categoria de inscrições abertas.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl05 extends base {
    /** @var string */
    const ID = 'CL-05';
    /** @var string */
    const CATEGORY = category::OFFER_ENROL;
    /** @var string */
    const ORIGIN = 'checklist:5';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::BAIXA;
    /** @var string[] */
    const POLICY_KEYS = ['target_category_name'];
    /** @var string */
    const LOCUS = self::LOCUS_COURSE_SETTINGS;
    /** @var string */
    const SOURCE = 'Checklist item 5; decisão institucional 3';
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
        $cat = $s['course']['category']['name'];
        $ok = helpers::norm($cat) === helpers::norm($p['target_category_name']);
        return [$this->f(
            $ok ? status::CONFORME : status::NAO_CONFORME,
            'Configurações do curso > Categoria',
            "categoria=\"$cat\"",
            ['esperado' => $p['target_category_name'], 'orientacao' => $this->guidance()]
        )];
    }
}
