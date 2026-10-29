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

namespace local_mooc_audit\local\correction\strategy;

use local_mooc_audit\local\correction\proposal;

/**
 * CL-14 on graded activities other than quizzes: guided proposal (category B).
 *
 * Each type stores its close date differently (assignment: due date and cut-off date; forum: due and cut-off dates;
 * choice, feedback and game: close date; database: "available to"), with side effects of their own (assignment
 * extensions and grading due date, third-party game plugin). None has a validated single-setting update routine in this
 * delivery, so the proposal explains which field to set and links to the settings page.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_date_guidance extends base {
    /** @var string */
    const RULE = 'CL-14';

    /** @var array Close-date columns read by CL-14, per type (first non empty wins). */
    const FIELDS = [
        'assign' => ['cutoffdate', 'duedate'],
        'forum' => ['cutoffdate', 'duedate'],
        'choice' => ['timeclose'],
        'feedback' => ['timeclose'],
        'game' => ['timeclose'],
        'data' => ['timeavailableto'],
    ];

    /**
     * Every graded type except quizzes.
     *
     * @param array $module
     * @return bool
     */
    public function supports(array $module): bool {
        return $module['modname'] !== 'quiz';
    }

    /**
     * Describe.
     *
     * @param proposal $p
     * @param array $ctx
     */
    public function describe(proposal $p, array $ctx): void {
        $m = $ctx['module'];
        $end = (int) $ctx['snapshot']['course']['enddate'];
        $type = $m['modname'];
        $close = 0;
        foreach (self::FIELDS[$type] ?? [] as $f) {
            $close = $close ?: (int) ($m['extra'][$f] ?? 0);
        }
        $p->category = proposal::CATEGORY_GUIDED;
        $p->currentdisplay = $this->date($close);
        $p->proposeddisplay = $this->date($end);
        $p->editurl = $this->edit_url($p->cmid);
        $p->justification = $this->str('cl14_justification');
        $known = isset(self::FIELDS[$type]);
        $p->effect = $this->str($known ? 'cl14_guided_field_' . $type : 'cl14_guided_field_unknown');
        $p->blockers[] = $this->str('cl14_guided_reason', $type);
        $start = (int) $ctx['snapshot']['course']['startdate'];
        $p->dependencies[] = $this->str('cl14_period', (object) ['start' => $this->date($start), 'end' => $this->date($end)]);
        $p->dependencies[] = $this->str('cl14_dep_cl07');
        if ($type === 'assign') {
            $p->risks[] = $this->str('cl14_risk_assign');
        } else if ($type === 'game') {
            $p->risks[] = $this->str('cl14_risk_thirdparty');
        }
    }
}
