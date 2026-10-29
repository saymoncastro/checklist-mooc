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

namespace local_mooc_audit\local\history;

/**
 * Access rules of the history pages (specs/005 FR-029).
 *
 * The history of a course is read with local/mooc_audit:audit in that course. The stored history of a course that no
 * longer exists is readable only with local/mooc_audit:viewdashboard (system). A run id is always checked against the
 * course of the page, so that changing the id in the URL never reveals another course.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Check access to the history of a course.
     *
     * @param int $courseid
     * @param int|null $userid default: current user
     * @return array [course record or null when removed, context of the page]
     * @throws \required_capability_exception|\moodle_exception
     */
    public static function require_history(int $courseid, ?int $userid = null): array {
        global $DB;
        $course = $courseid != SITEID ? $DB->get_record('course', ['id' => $courseid]) : false;
        if ($course) {
            $context = \context_course::instance($course->id);
            require_capability('local/mooc_audit:audit', $context, $userid);
            return [$course, $context];
        }
        $context = \context_system::instance();
        require_capability('local/mooc_audit:viewdashboard', $context, $userid);
        if ($courseid == SITEID || !run_store::count_for_course($courseid)) {
            throw new \moodle_exception('invalidcourseid');
        }
        return [null, $context];
    }

    /**
     * A run of the given course.
     *
     * @param int $runid
     * @param int $courseid
     * @return \stdClass
     * @throws \moodle_exception when the run does not exist or belongs to another course
     */
    public static function run_of_course(int $runid, int $courseid): \stdClass {
        $run = run_store::get($runid);
        if (!$run || (int) $run->courseid !== $courseid) {
            throw new \moodle_exception('invalidrecord', 'error', '', run_store::TABLE);
        }
        return $run;
    }
}
