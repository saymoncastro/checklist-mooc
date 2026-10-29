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

namespace local_mooc_audit\local\correction;

/**
 * Read-only facts the correction strategies need and the audit snapshot does not collect: student data on an activity,
 * enrolments, overrides, course completion criteria, course revision. Counts only; no personal data leaves this class.
 *
 * Kept outside the snapshot on purpose: the audit collector (specs/001) stays unchanged.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class facts {
    /**
     * Course cache revision: incremented by Moodle whenever the course, a section or an activity is changed.
     *
     * @param int $courseid
     * @return int
     */
    public static function cacherev(int $courseid): int {
        global $DB;
        return (int) $DB->get_field('course', 'cacherev', ['id' => $courseid]);
    }

    /**
     * Last modification time of a module instance (0 when the table has no such column or the row is missing).
     *
     * @param string $modname
     * @param int $instanceid
     * @return int
     */
    public static function instance_timemodified(string $modname, int $instanceid): int {
        global $DB;
        $columns = $DB->get_columns($modname);
        if (!isset($columns['timemodified'])) {
            return 0;
        }
        return (int) $DB->get_field($modname, 'timemodified', ['id' => $instanceid]);
    }

    /**
     * Quiz attempts of students (previews by teachers excluded), in any state.
     *
     * @param int $quizid
     * @return int
     */
    public static function quiz_attempts(int $quizid): int {
        global $DB;
        return $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'preview' => 0]);
    }

    /**
     * Quiz user or group overrides.
     *
     * @param int $quizid
     * @param string|null $field only overrides that set this column (attempts, timeclose...)
     * @return int
     */
    public static function quiz_overrides(int $quizid, ?string $field = null): int {
        global $DB;
        if ($field === null) {
            return $DB->count_records('quiz_overrides', ['quiz' => $quizid]);
        }
        return $DB->count_records_select('quiz_overrides', "quiz = :quiz AND $field IS NOT NULL", ['quiz' => $quizid]);
    }

    /**
     * Grades recorded for the grade items of an activity.
     *
     * @param array $module snapshot module (with grade_items)
     * @return int
     */
    public static function grades(array $module): int {
        global $DB;
        $ids = array_column($module['grade_items'] ?? [], 'id');
        if (!$ids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return $DB->count_records_select('grade_grades', "itemid $insql AND finalgrade IS NOT NULL", $params);
    }

    /**
     * Actively enrolled users who hold a capability in the activity (e.g. students who can attempt a quiz).
     *
     * @param int $cmid
     * @param string $capability
     * @return int
     */
    public static function learners(int $cmid, string $capability): int {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        return count_enrolled_users(\context_module::instance($cmid), $capability, 0, true);
    }

    /**
     * Activity completion records of users (completed, passed or failed).
     *
     * @param int $cmid
     * @return int
     */
    public static function completion_records(int $cmid): int {
        global $DB;
        return $DB->count_records_select(
            'course_modules_completion',
            'coursemoduleid = :cmid AND completionstate > 0',
            ['cmid' => $cmid]
        );
    }

    /**
     * Course completion criteria that require the completion of the activity.
     *
     * @param int $courseid
     * @param int $cmid
     * @return int
     */
    public static function course_completion_criteria(int $courseid, int $cmid): int {
        global $DB;
        return $DB->count_records('course_completion_criteria', ['course' => $courseid, 'moduleinstance' => $cmid]);
    }

    /**
     * Localised names of the roles that hold every given capability in a context.
     *
     * @param \context $context
     * @param string[] $capabilities
     * @return string[]
     */
    public static function roles_with(\context $context, array $capabilities): array {
        $names = get_role_names_with_caps_in_context($context, $capabilities);
        return array_values(array_map(fn($n) => format_string($n), $names));
    }
}
