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
 * The comparison never modifies either course (specs/003 FR-025, constitution III) and contains no personal data
 * (FR-026): same tables, timemodified and cacherev of both courses before and after comparing; no user id, name,
 * attempt or grade in the result.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\comparator
 */
final class compare_readonly_test extends \advanced_testcase {
    /**
     * Hash of every record of the tables that describe a course (same tables as readonly_test).
     *
     * @param int $courseid
     * @return string[]
     */
    private function hashes(int $courseid): array {
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
        $out = [];
        foreach ($queries as $table => [$where, $params]) {
            $out[$table] = md5(json_encode($DB->get_records_select($table, $where, $params, 'id')));
        }
        return $out;
    }

    /**
     * Course B and its import, with an enrolled student who has an attempt and a grade, compared through run(), the
     * text output, the JSON and the report renderer: nothing changes and no personal data leaks.
     */
    public function test_comparison_is_read_only_and_has_no_personal_data(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$b, $ids] = $gen->create_course_b(2026);
        $t = $gen->import_into_new_course($b, 'B_2027', 2027);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Estudante', 'lastname' => 'Sigiloso',
            'email' => 'sigiloso@example.com']);
        $this->getDataGenerator()->enrol_user($student->id, $t->id, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $b->id, 'student');
        $quizcm = get_coursemodule_from_id('quiz', $ids['quiz1']);
        $DB->insert_record('quiz_attempts', (object) ['quiz' => $quizcm->instance, 'userid' => $student->id, 'attempt' => 1,
            'uniqueid' => 987654, 'layout' => '1,0', 'currentpage' => 0, 'preview' => 0, 'state' => 'finished',
            'timestart' => time(), 'timefinish' => time(), 'timemodified' => time(), 'sumgrades' => 3.0]);
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quizcm->instance,
            'courseid' => $b->id]);
        $item->update_final_grade($student->id, 37.5, 'test');
        \context_helper::build_all_paths(true);
        foreach ([$b, $t] as $course) {
            foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                \context_module::instance($cm->id);
            }
        }
        $before = [$this->hashes($b->id), $this->hashes($t->id)];
        $courses = $DB->get_records_list('course', 'id', [$b->id, $t->id], '', 'id, timemodified, cacherev');

        $this->waitForSecond();
        $sink = $this->redirectEvents();
        $r = comparator::run($b->id, $t->id, [], ['reoffer_year' => 2027]);
        $text = comparator::render_text($r);
        $json = comparator::to_json($r);
        $html = $PAGE->get_renderer('local_mooc_audit')->render(new output\comparison_report(
            $r,
            new \moodle_url('/local/mooc_audit/compare.php', ['source' => $b->id, 'target' => $t->id, 'confirm' => 1])
        ));
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame($before, [$this->hashes($b->id), $this->hashes($t->id)]);
        $this->assertEquals($courses, $DB->get_records_list('course', 'id', [$b->id, $t->id], '', 'id, timemodified, cacherev'));
        $this->assertSame([], $events);
        foreach ([$text, $json, $html] as $out) {
            $this->assertStringNotContainsString('Sigiloso', $out);
            $this->assertStringNotContainsString('sigiloso@example.com', $out);
            $this->assertStringNotContainsString('37.5', $out);
            $this->assertStringNotContainsString('987654', $out);
        }
        $this->assertStringNotContainsString('"userid"', $json);
        $this->assertStringNotContainsString('viewer_userid', $json);
        $this->assertStringNotContainsString('correction.php', $html, 'no correction action in the comparison');
    }
}
