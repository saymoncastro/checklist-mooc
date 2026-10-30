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

use local_mooc_audit\local\correction\facts;
use local_mooc_audit\local\correction\proposal;

/**
 * CL-14 on a quiz: set the close date to the course end date (category A).
 *
 * The course end date is the reference of the rule (CL-14 warns when the close date differs from it). The proposal is
 * executable only when that end date is defined, conformant to the policy (CL-07), in the future and after the quiz
 * opening, when the change does not move an existing close date later (an earlier deadline may be intentional), and
 * when no student attempted the quiz or has a grade. The value written is the same instant as the course
 * end date: no timezone conversion.
 *
 * Writing mirrors core's own single-setting update of a quiz date (mod_quiz_core_calendar_event_timestart_updated):
 * partial update of quiz.timeclose, quiz_update_open_attempts(), quiz_update_events() (calendar), cache refresh and
 * course_module_updated. Teacher previews are kept.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_timeclose extends base {
    /** @var string */
    const RULE = 'CL-14';

    /**
     * Quizzes only (other types: close_date_guidance).
     *
     * @param array $module
     * @return bool
     */
    public function supports(array $module): bool {
        return $module['modname'] === 'quiz';
    }

    /**
     * Describe.
     *
     * @param proposal $p
     * @param array $ctx
     */
    public function describe(proposal $p, array $ctx): void {
        $m = $ctx['module'];
        $course = $ctx['snapshot']['course'];
        $end = (int) $course['enddate'];
        $close = (int) ($m['extra']['timeclose'] ?? 0);
        $open = (int) ($m['extra']['timeopen'] ?? 0);

        $p->category = proposal::CATEGORY_EXECUTABLE;
        $p->field = 'timeclose';
        $p->current = $close;
        $p->currentdisplay = $this->date($close);
        $p->proposed = $end ?: null;
        $p->proposeddisplay = $this->date($end);
        $p->editurl = $this->edit_url($p->cmid, 'id_timing');
        $p->justification = $this->str('cl14_justification');
        $p->effect = $this->str($close ? 'cl14_effect_change' : 'cl14_effect_set', $this->date($end));
        $p->dependencies[] = $this->str('cl14_period', (object) ['start' => $this->date((int) $course['startdate']),
            'end' => $this->date($end)]);
        $p->dependencies[] = $this->str('cl14_dep_cl07');

        if (!$end) {
            $p->blockers['noenddate'] = $this->str('cl14_noenddate');
        } else {
            if ($this->enddate_nonconformant($ctx)) {
                $p->blockers['enddate_policy'] = $this->str('cl14_enddate_policy');
                $p->proposeddisplay = $this->str('cl14_proposed_after_cl07', $this->date($end));
            }
            if ($end <= time()) {
                $p->blockers['enddate_past'] = $this->str('cl14_enddate_past');
            }
            if ($open && $open >= $end) {
                $p->blockers['open_after'] = $this->str('cl14_open_after', $this->date($open));
            }
            if ($close && $close < $end) {
                // An earlier close date may be a pedagogical choice: moving the deadline later is never automatic.
                $p->blockers['later'] = $this->str('cl14_later', $this->date($close));
            }
        }
        $quizid = (int) $m['instanceid'];
        if ($n = facts::quiz_attempts($quizid)) {
            $p->blockers['attempts'] = $this->str('blocker_attempts', $n);
        }
        if ($n = facts::grades($m)) {
            $p->blockers['grades'] = $this->str('blocker_grades', $n);
        }
        if ($n = facts::learners($p->cmid, 'mod/quiz:attempt')) {
            $p->risks[] = $this->str('cl14_risk_learners', $n);
        }
        if ($n = facts::quiz_overrides($quizid, 'timeclose')) {
            $p->risks[] = $this->str('risk_overrides', $n);
        }
        $p->state['timemodified'] = facts::instance_timemodified('quiz', $quizid);
        $p->state['enddate'] = $end;
    }

    /**
     * Write quiz.timeclose and refresh what depends on it (open attempts, calendar).
     *
     * @param proposal $p
     * @param \cm_info $cm
     */
    public function apply(proposal $p, \cm_info $cm): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->assert_current('quiz', (int) $cm->instance, 'timeclose', $p->current);
        $this->assert_no_student_data($cm);
        $DB->update_record('quiz', (object) ['id' => $cm->instance, 'timeclose' => (int) $p->proposed, 'timemodified' => time()]);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->coursemodule = $cm->id;
        quiz_update_open_attempts(['quizid' => $quiz->id]);
        quiz_update_events($quiz);
    }

    /**
     * Moodle reads the new close date through the quiz settings API and the calendar has the close event.
     *
     * @param proposal $p
     * @param \cm_info $cm
     * @return bool
     */
    public function persisted(proposal $p, \cm_info $cm): bool {
        global $DB;
        $quiz = \mod_quiz\quiz_settings::create((int) $cm->instance)->get_quiz();
        return (int) $quiz->timeclose === (int) $p->proposed
            && $DB->record_exists('event', ['modulename' => 'quiz', 'instance' => $cm->instance, 'eventtype' => 'close',
                'courseid' => $cm->course, 'groupid' => 0, 'timestart' => (int) $p->proposed]);
    }
}
