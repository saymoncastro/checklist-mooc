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

namespace local_mooc_audit\tests;

/**
 * Hashes of the tables that describe a course (course, sections, modules, format options, grade items, categories and
 * grades, enrolment, blocks, files, module tables, attempts, completion criteria, contexts), shared by the read-only
 * tests of the audit, the correction preview and the comparison.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_tables {
    /**
     * Hash of every record of the tables that describe a course.
     *
     * @param int $courseid
     * @return string[]
     */
    public static function hashes(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $contexts = array_merge([$context->id], $DB->get_fieldset_select(
            'context',
            'id',
            'contextlevel = ? AND ' . $DB->sql_like('path', '?'),
            [CONTEXT_MODULE, $context->path . '/%']
        ));
        [$insql, $inparams] = $DB->get_in_or_equal($contexts);
        $queries = [
            'course' => ['id = ?', [$courseid]], 'course_sections' => ['course = ?', [$courseid]],
            'course_modules' => ['course = ?', [$courseid]], 'course_format_options' => ['courseid = ?', [$courseid]],
            'grade_items' => ['courseid = ?', [$courseid]], 'grade_categories' => ['courseid = ?', [$courseid]],
            'grade_grades' => ["itemid IN (SELECT id FROM {grade_items} WHERE courseid = ?)", [$courseid]],
            'enrol' => ['courseid = ?', [$courseid]], 'block_instances' => ["parentcontextid $insql", $inparams],
            'files' => ["contextid $insql", $inparams], 'quiz' => ['course = ?', [$courseid]],
            'quiz_attempts' => ['quiz IN (SELECT id FROM {quiz} WHERE course = ?)', [$courseid]],
            'feedback' => ['course = ?', [$courseid]], 'page' => ['course = ?', [$courseid]],
            'forum' => ['course = ?', [$courseid]], 'resource' => ['course = ?', [$courseid]],
            'course_completion_criteria' => ['course = ?', [$courseid]], 'context' => ["id $insql", $inparams],
        ];
        foreach (['glossary', 'game'] as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $queries[$table] = ['course = ?', [$courseid]];
            }
        }
        $out = [];
        foreach ($queries as $table => [$where, $params]) {
            $out[$table] = md5(json_encode($DB->get_records_select($table, $where, $params, 'id')));
        }
        return $out;
    }
}
