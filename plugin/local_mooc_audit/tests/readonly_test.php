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

/**
 * The audit never modifies the course (FR-005): table contents before and after an audit are identical.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\engine
 */
final class readonly_test extends \advanced_testcase {

    /**
     * Hash of every record of the tables that describe the course.
     *
     * @param int $courseid
     * @return string[] table => hash
     */
    private function hashes(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $cmcontexts = $DB->get_fieldset_select('context', 'id', 'contextlevel = ? AND ' . $DB->sql_like('path', '?'), [CONTEXT_MODULE, $context->path . '/%']);
        $contexts = array_merge([$context->id], $cmcontexts);
        [$insql, $inparams] = $DB->get_in_or_equal($contexts);
        $queries = [
            'course' => ['id = ?', [$courseid]],
            'course_sections' => ['course = ?', [$courseid]],
            'course_modules' => ['course = ?', [$courseid]],
            'course_format_options' => ['courseid = ?', [$courseid]],
            'grade_items' => ['courseid = ?', [$courseid]],
            'grade_categories' => ['courseid = ?', [$courseid]],
            'enrol' => ['courseid = ?', [$courseid]],
            'block_instances' => ["parentcontextid $insql", $inparams],
            'files' => ["contextid $insql", $inparams],
            'quiz' => ['course = ?', [$courseid]],
            'feedback' => ['course = ?', [$courseid]],
            'page' => ['course = ?', [$courseid]],
            'forum' => ['course = ?', [$courseid]],
            'resource' => ['course = ?', [$courseid]],
            'course_completion_criteria' => ['course = ?', [$courseid]],
            'context' => ["id $insql", $inparams],
        ];
        if ($DB->get_manager()->table_exists('glossary')) {
            $queries['glossary'] = ['course = ?', [$courseid]];
        }
        $out = [];
        foreach ($queries as $table => [$where, $params]) {
            $records = $DB->get_records_select($table, $where, $params, 'id');
            $out[$table] = md5(json_encode($records));
        }
        return $out;
    }

    /**
     * Course B (every rule firing) audited twice, with and without a student viewer: nothing changes, including
     * course.timemodified and cacherev.
     */
    public function test_audit_does_not_modify_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $student = $this->getDataGenerator()->create_and_enrol($b, 'student');
        // Moodle completes context paths lazily on first use; do that before the baseline so that only the audit
        // itself is measured.
        \context_helper::build_all_paths(true);
        foreach (get_fast_modinfo($b)->get_cms() as $cm) {
            \context_module::instance($cm->id);
        }
        $before = $this->hashes($b->id);
        $course = $DB->get_record('course', ['id' => $b->id]);

        $this->waitForSecond();
        engine::audit($b->id, ['reoffer_year' => 2027]);
        engine::audit($b->id, ['reoffer_year' => 2027], $student->id, true);
        engine::render_text(engine::audit($b->id));

        $after = $this->hashes($b->id);
        $this->assertSame($before, $after);
        $this->assertCount(17, $before);
        $this->assertSame($course->timemodified, $DB->get_field('course', 'timemodified', ['id' => $b->id]));
        $this->assertSame($course->cacherev, $DB->get_field('course', 'cacherev', ['id' => $b->id]));
    }
}
