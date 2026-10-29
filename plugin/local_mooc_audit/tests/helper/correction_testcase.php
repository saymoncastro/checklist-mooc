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

namespace local_mooc_audit;

use local_mooc_audit\local\correction\planner;
use local_mooc_audit\local\correction\proposal;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/rule_testcase.php');

/**
 * Base class of the assisted correction tests: courses dated in the next reoffer year (so that the course end date is
 * always in the future), planning with a fixed policy and access to the stored quiz settings.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class correction_testcase extends rule_testcase {
    /**
     * Reoffer year of the scenarios: the year after the current one.
     *
     * @return int
     */
    protected function year(): int {
        return (int) date('Y') + 1;
    }

    /**
     * Policy override of the scenarios.
     *
     * @return array
     */
    protected function policy(): array {
        return ['reoffer_year' => $this->year()];
    }

    /**
     * A course dated 01/01 00:00 – 31/12 23:59 of the reoffer year (conformant to CL-06/CL-07).
     *
     * @param array $extra
     * @return stdClass
     */
    protected function ccourse(array $extra = []): stdClass {
        $y = $this->year();
        return $this->course(array_merge(
            ['startdate' => $this->ts("$y-01-01 00:00"), 'enddate' => $this->ts("$y-12-31 23:59")],
            $extra
        ));
    }

    /**
     * Plan the correction of a rule on an activity.
     *
     * @param stdClass $course
     * @param string $rule
     * @param int $cmid
     * @return proposal|null
     */
    protected function plan(stdClass $course, string $rule, int $cmid): ?proposal {
        return (new planner())->plan($course->id, $rule, $cmid, $this->policy());
    }

    /**
     * The quiz row.
     *
     * @param stdClass $quizinfo create_module info
     * @return stdClass
     */
    protected function quizrow(stdClass $quizinfo): stdClass {
        global $DB;
        return $DB->get_record('quiz', ['id' => $quizinfo->instance], '*', MUST_EXIST);
    }

    /**
     * A student enrolled in the course who started an attempt of the quiz.
     *
     * @param stdClass $course
     * @param stdClass $quizinfo
     * @return stdClass the student
     */
    protected function student_attempt(stdClass $course, stdClass $quizinfo): stdClass {
        global $USER;
        $current = $USER;
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // As the student: an attempt started by a teacher or an admin is a preview.
        $this->setUser($student);
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quizinfo->instance, $student->id);
        $this->setUser($current);
        return $student;
    }

    /**
     * Rows of the correction trail.
     *
     * @return array
     */
    protected function trail(): array {
        global $DB;
        return array_values($DB->get_records('local_mooc_audit_fix', null, 'id'));
    }
}
