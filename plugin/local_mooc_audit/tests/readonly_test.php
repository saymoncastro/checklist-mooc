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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/course_tables.php');

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
        $before = tests\course_tables::hashes($b->id);
        $course = $DB->get_record('course', ['id' => $b->id]);

        $this->waitForSecond();
        engine::audit($b->id, ['reoffer_year' => 2027]);
        engine::audit($b->id, ['reoffer_year' => 2027], $student->id, true);
        engine::render_text(engine::audit($b->id));

        $after = tests\course_tables::hashes($b->id);
        $this->assertSame($before, $after);
        $this->assertGreaterThanOrEqual(18, count($before));
        $this->assertSame($course->timemodified, $DB->get_field('course', 'timemodified', ['id' => $b->id]));
        $this->assertSame($course->cacherev, $DB->get_field('course', 'cacherev', ['id' => $b->id]));
    }

    /**
     * Opening every correction proposal of course B (preview) and cancelling (going back to the report) change nothing:
     * same tables, same cacherev, no calendar event, no trail row, no log event (specs/002 SC-002).
     *
     * @covers \local_mooc_audit\local\correction\planner
     */
    public function test_correction_preview_does_not_modify_the_course(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $this->getDataGenerator()->create_and_enrol($b, 'student');
        \context_helper::build_all_paths(true);
        foreach (get_fast_modinfo($b)->get_cms() as $cm) {
            \context_module::instance($cm->id);
        }
        $before = tests\course_tables::hashes($b->id);
        $cacherev = $DB->get_field('course', 'cacherev', ['id' => $b->id]);
        $events = $DB->count_records('event');

        $this->waitForSecond();
        $sink = $this->redirectEvents();
        $planner = new local\correction\planner();
        $report = engine::audit($b->id, ['reoffer_year' => 2027]);
        $planned = 0;
        $output = $PAGE->get_renderer('local_mooc_audit');
        $url = new \moodle_url('/local/mooc_audit/correction.php');
        foreach ($report['results'] as $r) {
            if (local\correction\planner::eligible($r)) {
                $p = $planner->plan($b->id, $r['rule'], (int) $r['cmid'], ['reoffer_year' => 2027]);
                $output->render(new output\correction_proposal($p, $url, $url));
                $planned++;
            }
        }
        // The report page with the correction actions.
        $output->render(new output\report(engine::audit($b->id), $url, true));
        $logged = $sink->get_events();
        $sink->close();

        $this->assertGreaterThanOrEqual(6, $planned);
        $this->assertSame($before, tests\course_tables::hashes($b->id));
        $this->assertSame($cacherev, $DB->get_field('course', 'cacherev', ['id' => $b->id]));
        $this->assertSame($events, $DB->count_records('event'));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_fix'));
        $this->assertSame([], $logged);
    }

    /**
     * Recorded audits (page and background batch) write only to the plugin's own tables (specs/005 FR-031).
     *
     * @covers \local_mooc_audit\local\history\recorder
     * @covers \local_mooc_audit\task\audit_course
     */
    public function test_recorded_and_batch_audits_do_not_modify_the_course(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $this->getDataGenerator()->create_and_enrol($b, 'student');
        \context_helper::build_all_paths(true);
        foreach (get_fast_modinfo($b)->get_cms() as $cm) {
            \context_module::instance($cm->id);
        }
        $before = tests\course_tables::hashes($b->id);
        $course = $DB->get_record('course', ['id' => $b->id]);
        $events = $DB->count_records('event');

        $this->waitForSecond();
        local\history\recorder::record_now((int) $b->id, 'page', (int) $USER->id, ['reoffer_year' => 2027]);
        local\history\batch_manager::create(['mode' => 'ids', 'courseids' => [$b->id]], (int) $USER->id);
        ob_start();
        $this->runAdhocTasks(\local_mooc_audit\task\audit_course::class);
        ob_end_clean();

        $this->assertSame(2, $DB->count_records('local_mooc_audit_run', ['courseid' => $b->id, 'status' => 'complete']));
        $this->assertSame($before, tests\course_tables::hashes($b->id));
        $this->assertSame($course->timemodified, $DB->get_field('course', 'timemodified', ['id' => $b->id]));
        $this->assertSame($course->cacherev, $DB->get_field('course', 'cacherev', ['id' => $b->id]));
        $this->assertSame($events, $DB->count_records('event'));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_fix'));
    }

    /**
     * An audit requested through the external API (specs/004) and run by the queue does not modify the course either
     * (principle III).
     */
    public function test_api_request_does_not_modify_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        set_config('policyjson', json_encode(['reoffer_year' => 2027]), 'local_mooc_audit');
        \context_helper::build_all_paths(true);
        foreach (get_fast_modinfo($b)->get_cms() as $cm) {
            \context_module::instance($cm->id);
        }
        $role = create_role('API', 'apiro', '');
        assign_capability('local/mooc_audit:api', CAP_ALLOW, $role, \context_system::instance()->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->getDataGenerator()->enrol_user($user->id, $b->id, 'editingteacher');
        $before = tests\course_tables::hashes($b->id);
        $course = $DB->get_record('course', ['id' => $b->id]);

        $this->waitForSecond();
        $this->setUser($user);
        $out = external\request_audit::execute((int) $b->id);
        ob_start();
        $this->runAdhocTasks(\local_mooc_audit\task\audit_course::class);
        ob_end_clean();

        $this->assertSame('complete', $DB->get_field('local_mooc_audit_run', 'status', ['id' => $out['runid']]));
        $this->assertSame($before, tests\course_tables::hashes($b->id));
        $this->assertSame($course->timemodified, $DB->get_field('course', 'timemodified', ['id' => $b->id]));
        $this->assertSame($course->cacherev, $DB->get_field('course', 'cacherev', ['id' => $b->id]));
    }
}
