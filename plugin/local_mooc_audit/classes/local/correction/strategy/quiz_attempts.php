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
 * CL-13 on a quiz: raise the number of allowed attempts to the policy minimum (category A).
 *
 * Never lowers the number of attempts (policy "exact" with more attempts, or unlimited), never touches the grading
 * method. Refused when students already attempted the quiz or have a grade. When only the statement is inconsistent, the
 * proposal is guided (edit the statement).
 *
 * Writing: partial update of quiz.attempts (and timemodified) followed by the cache refresh and course_module_updated,
 * as core does when it changes a single quiz setting outside the form (mod_quiz_core_calendar_event_timestart_updated).
 * quiz_update_instance() is not used: it expects form data and would reset review options and feedback (specs/002
 * research R1).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_attempts extends base {
    /** @var string */
    const RULE = 'CL-13';

    /**
     * Quizzes only.
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
        $policy = $ctx['policy'];
        $min = (int) $policy['min_quiz_attempts'];
        $exact = $policy['attempts_rule'] === 'exact';
        $a = (int) ($m['extra']['attempts'] ?? 0);
        $p->field = 'attempts';
        $p->current = $a;
        $p->currentdisplay = $a === 0 ? $this->str('cl13_unlimited') : (string) $a;
        $p->editurl = $this->edit_url($p->cmid, 'id_modstandardgrade');
        $p->justification = $this->str($exact ? 'cl13_justification_exact' : 'cl13_justification', $min);
        $p->dependencies[] = $this->str('cl13_grademethod');

        $attemptsok = $exact ? $a === $min : ($a === 0 || $a >= $min);
        if ($attemptsok) {
            // Only the statement disagrees with the configuration: a text change, guided.
            $p->category = proposal::CATEGORY_GUIDED;
            $p->field = null;
            $p->proposeddisplay = $this->str('cl13_intro_proposed', $p->currentdisplay);
            $p->effect = $this->str('cl13_intro_effect');
            $p->editurl = $this->edit_url($p->cmid, 'id_general');
            return;
        }
        if ($exact && ($a === 0 || $a > $min)) {
            // Lowering the number of attempts is never automatic.
            $p->category = proposal::CATEGORY_GUIDED;
            $p->field = null;
            $p->proposeddisplay = (string) $min;
            $p->effect = $this->str('cl13_reduce_effect');
            $p->blockers['noreduce'] = $this->str('cl13_noreduce');
            return;
        }

        $p->category = proposal::CATEGORY_EXECUTABLE;
        $p->proposed = $min;
        $p->proposeddisplay = (string) $min;
        $p->effect = $this->str('cl13_effect', (object) ['from' => $a, 'to' => $min]);

        $quizid = (int) $m['instanceid'];
        if ($n = facts::quiz_attempts($quizid)) {
            $p->blockers['attempts'] = $this->str('blocker_attempts', $n);
        }
        if ($n = facts::grades($m)) {
            $p->blockers['grades'] = $this->str('blocker_grades', $n);
        }
        if ($n = facts::learners($p->cmid, 'mod/quiz:attempt')) {
            $p->risks[] = $this->str('risk_learners', $n);
        }
        if ($n = facts::quiz_overrides($quizid, 'attempts')) {
            $p->risks[] = $this->str('risk_overrides', $n);
        }
        if (!empty($m['extra']['completionattemptsexhausted'])) {
            $p->risks[] = $this->str('cl13_risk_exhausted', $min);
        }
        $p->state['timemodified'] = facts::instance_timemodified('quiz', $quizid);
    }

    /**
     * Write quiz.attempts.
     *
     * @param proposal $p
     * @param \cm_info $cm
     */
    public function apply(proposal $p, \cm_info $cm): void {
        global $DB;
        $this->assert_current('quiz', (int) $cm->instance, 'attempts', $p->current);
        $this->assert_no_student_data($cm);
        $DB->update_record('quiz', (object) ['id' => $cm->instance, 'attempts' => (int) $p->proposed, 'timemodified' => time()]);
    }

    /**
     * Moodle reads the new number of attempts through the quiz settings API.
     *
     * @param proposal $p
     * @param \cm_info $cm
     * @return bool
     */
    public function persisted(proposal $p, \cm_info $cm): bool {
        $quiz = \mod_quiz\quiz_settings::create((int) $cm->instance)->get_quiz();
        return (int) $quiz->attempts === (int) $p->proposed;
    }
}
