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

use local_mooc_audit\local\catalog;
use local_mooc_audit\local\status;

/**
 * Recorded audits against real courses (specs/005 US1, US2): first and second audit, resolution, new problem, policy
 * change, rule error, removed course, immutability and the assisted corrections of the period.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\recorder
 * @covers     \local_mooc_audit\local\history\run_store
 * @covers     \local_mooc_audit\local\history\policy_store
 */
final class recorder_test extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;
    /** @var array Policy override of the fixtures. */
    const POLICY = ['reoffer_year' => self::YEAR];

    /**
     * Course B (many problems).
     *
     * @return \stdClass
     */
    protected function course_b(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        return $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(self::YEAR)[0];
    }

    /**
     * Record an audit.
     *
     * @param int $courseid
     * @param array $policy
     * @param array|null $rules
     * @return array [run, report, exception]
     */
    protected function record(int $courseid, array $policy = self::POLICY, ?array $rules = null): array {
        global $USER;
        return recorder::record_now($courseid, run_store::ORIGIN_PAGE, (int) $USER->id, $policy, $rules);
    }

    /**
     * First audit, then a second one without changes.
     */
    public function test_first_and_unchanged_second_audit(): void {
        global $DB, $USER;
        $b = $this->course_b();
        [$run, $report, $e] = $this->record($b->id);
        $this->assertNull($e);
        $this->assertSame(run_store::COMPLETE, $run->status);
        $this->assertSame(status::OVERALL_BLOCKED, $run->overall);
        $this->assertEquals($USER->id, $run->userid);
        $this->assertSame('page', $run->origin);
        $this->assertEquals(self::YEAR, $run->reofferyear);
        $this->assertEquals(0, $run->previousrunid);
        $this->assertNull($run->evolution);
        $this->assertNotEmpty($run->timestarted);
        $this->assertNotEmpty($run->timefinished);
        $this->assertStringStartsWith(\local_mooc_audit\engine::VERSION . ' (', $run->pluginversion);
        $stored = run_store::json($run, 'report');
        $this->assertSame($report['results'], $stored['results']);
        $this->assertArrayNotHasKey('snapshot', $stored);
        $details = run_store::json($run, 'details');
        $this->assertCount(catalog::count(), $details['rules']);
        $this->assertNotEmpty($details['modules']);
        $this->assertContains(
            (int) $DB->get_field('course_sections', 'id', ['course' => $b->id, 'section' => 0]),
            $details['sections']
        );
        $this->assertArrayHasKey('peak_mb', $details['metrics']);
        $policy = policy_store::get((int) $run->policyid);
        $this->assertEquals(self::YEAR, json_decode($policy->policy, true)['reoffer_year']);
        $this->assertArrayNotHasKey('reference_timestamp', json_decode($policy->policy, true));

        [$second] = $this->record($b->id);
        $evo = run_store::json($second, 'evolution');
        $problems = count(array_filter($report['results'], fn($r) => evolution::is_problem($r['status'])));
        $this->assertEquals($run->id, $second->previousrunid);
        $this->assertSame($problems, $evo['counts']['persistent']);
        $this->assertSame($problems, array_sum($evo['counts']));
        $this->assertSame(1, $DB->count_records(policy_store::TABLE), 'Same policy stored once');
        $this->assertSame($run->policyid, $second->policyid);
    }

    /**
     * Fixing one problem resolves exactly its occurrence; hiding an activity introduces a new one.
     */
    public function test_resolution_and_new_problem(): void {
        global $DB;
        $b = $this->course_b();
        [, $report] = $this->record($b->id);
        $quiz = array_values(array_filter($report['results'], fn($r) => $r['rule'] === 'CL-13'
            && $r['status'] === status::NAO_CONFORME && str_ends_with($r['occurrence'], '|attempts')))[0];
        $cm = get_coursemodule_from_id('quiz', $quiz['cmid'], 0, false, MUST_EXIST);
        $DB->set_field('quiz', 'attempts', 0, ['id' => $cm->instance]);
        $page = array_values(array_filter(get_fast_modinfo($b)->get_cms(), fn($c) => $c->modname === 'page'
            && $c->visible))[0];
        set_coursemodule_visible($page->id, 0);
        rebuild_course_cache($b->id, true);

        [$run] = $this->record($b->id);
        $evo = evolution::compare(
            ['report' => $report, 'details' => run_store::json(run_store::get((int) $run->previousrunid), 'details'),
                'pluginversion' => $run->pluginversion],
            ['report' => run_store::json($run, 'report'), 'details' => run_store::json($run, 'details'),
                'pluginversion' => $run->pluginversion]
        );
        $classes = array_column($evo['items'], 'class', 'key');
        $this->assertSame('resolved', $classes[$quiz['occurrence']]);
        $this->assertSame('new', $classes["X-STR-01|cm:{$page->id}|visibility"]);
        $this->assertSame($evo['counts'], run_store::json($run, 'evolution')['counts']);
        $this->assertSame(1, $evo['counts']['resolved']);
    }

    /**
     * A policy change read by CL-13 only: its problems are not comparable, nothing is "new" or "resolved".
     */
    public function test_policy_change_is_not_new(): void {
        $b = $this->course_b();
        $this->record($b->id);
        [$run] = $this->record($b->id, self::POLICY + ['min_quiz_attempts' => 1]);
        $counts = run_store::json($run, 'evolution')['counts'];
        $this->assertSame(0, $counts['new']);
        $this->assertSame(0, $counts['resolved']);
        $this->assertGreaterThan(0, $counts['not_comparable']);
    }

    /**
     * A rule that throws makes the run partial (the others run); a deleted course makes it fail.
     */
    public function test_partial_and_failed_runs(): void {
        $b = $this->course_b();
        $broken = new class extends \local_mooc_audit\local\rule\cl13 {
            /**
             * Always fails.
             *
             * @param array $s
             * @param array $p
             * @return array
             */
            public function evaluate(array $s, array $p): array {
                throw new \coding_exception('regra quebrada');
            }
        };
        $rules = catalog::instances();
        $rules['CL-13'] = $broken;
        [$run, $report] = $this->record($b->id, self::POLICY, $rules);
        $this->assertSame(run_store::PARTIAL, $run->status);
        $this->assertNotNull($report);
        $errors = run_store::json($run, 'errors');
        $this->assertSame('rule_error', $errors[0]['code']);
        $this->assertSame('CL-13', $errors[0]['rule']);

        // Course B has a file without content on purpose: the recycle bin backup would fail.
        set_config('categorybinenable', 0, 'tool_recyclebin');
        delete_course($b, false);
        [$failed, $none, $e] = $this->record($b->id);
        $this->assertSame(run_store::FAILED, $failed->status);
        $this->assertNull($none);
        $this->assertNull($e);
        $this->assertSame('course_missing', run_store::json($failed, 'errors')[0]['code']);
        $this->assertNull($failed->report);
        // A failed run never serves as base: the latest valid one is still the partial run.
        $this->assertEquals($run->id, run_store::latest_valid($b->id)->id);
    }

    /**
     * Final runs are immutable: no transition, no rewrite.
     */
    public function test_final_runs_are_immutable(): void {
        $b = $this->course_b();
        [$run] = $this->record($b->id);
        $this->assertFalse(run_store::start((int) $run->id));
        $this->assertFalse(run_store::finish((int) $run->id, run_store::FAILED, ['errors' => [['code' => 'x']]]));
        $this->assertFalse(recorder::fail((int) $run->id, 'stale'));
        $this->assertEquals($run, run_store::get((int) $run->id));
    }

    /**
     * Assisted corrections recorded between two audits are listed from the trail, nothing is copied nor attributed.
     */
    public function test_corrections_between_runs(): void {
        global $DB;
        $b = $this->course_b();
        [$first] = $this->record($b->id);
        $DB->insert_record('local_mooc_audit_fix', (object) ['courseid' => $b->id, 'cmid' => 1, 'ruleid' => 'CL-13',
            'strategy' => 'quiz_attempts', 'field' => 'attempts', 'oldvalue' => '1', 'newvalue' => '2', 'outcome' => 'applied',
            'reaudit' => 'resolved', 'policyversion' => 'x', 'engineversion' => '0.5.0', 'userid' => 2,
            'timecreated' => $first->timefinished]);
        $this->waitForSecond();
        [$second] = $this->record($b->id);
        $list = evolution::corrections_between($b->id, (int) $first->timefinished, (int) $second->timestarted);
        $this->assertCount(1, $list);
        $this->assertSame('CL-13', $list[0]->ruleid);
        $this->assertSame(1, $DB->count_records('local_mooc_audit_fix'));
    }

    /**
     * Inserting a section above a problematic one keeps its occurrence (sections are keyed by id, not by number):
     * nothing is "resolved", only the new empty section is "new" (review finding 1).
     */
    public function test_section_insertion_keeps_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3, 'enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 3]);
        [$first, $report] = $this->record((int) $course->id);
        $sec2 = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 2]);
        $this->assertContains("CL-20|sec:$sec2|content", array_column($report['results'], 'occurrence'));

        course_create_section($course, 1);
        [$second] = $this->record((int) $course->id);
        $this->assertEquals(3, $DB->get_field('course_sections', 'section', ['id' => $sec2]), 'section moved down');
        $evo = evolution::compare(
            ['report' => $report, 'details' => run_store::json($first, 'details'), 'pluginversion' => $first->pluginversion],
            ['report' => run_store::json($second, 'report'), 'details' => run_store::json($second, 'details'),
                'pluginversion' => $second->pluginversion]
        );
        $classes = array_column($evo['items'], 'class', 'key');
        $this->assertSame('persistent', $classes["CL-20|sec:$sec2|content"]);
        $this->assertSame(0, $evo['counts']['resolved']);
        $this->assertSame(1, $evo['counts']['new'], 'the inserted empty section');
    }

    /**
     * The base of a run is the latest run finished before it started, whatever the ids: a batch run queued before a
     * page audit and executed after it compares with the page audit (review finding 3).
     */
    public function test_base_is_chosen_by_time(): void {
        global $DB, $USER;
        $b = $this->course_b();
        set_config('policyjson', json_encode(self::POLICY), 'local_mooc_audit');
        [$first] = $this->record($b->id);
        $queued = recorder::queue((int) $b->id, (int) $USER->id, 0);
        [$page] = $this->record($b->id);
        // Explicit times instead of waiting for the clock (the container clock is not reliable under load).
        $now = time();
        $DB->update_record(run_store::TABLE, (object) ['id' => $first->id, 'timestarted' => $now - 300,
            'timefinished' => $now - 300]);
        $DB->update_record(run_store::TABLE, (object) ['id' => $page->id, 'timestarted' => $now - 100,
            'timefinished' => $now - 100]);
        ob_start();
        $this->runAdhocTasks(\local_mooc_audit\task\audit_course::class);
        ob_end_clean();
        $this->assertLessThan((int) $page->id, $queued, 'the batch run has the smaller id');
        $this->assertEquals($page->id, run_store::get($queued)->previousrunid);
        $this->assertEquals($queued, run_store::latest_valid((int) $b->id)->id, 'latest = last finished, not highest id');
    }
}
