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

namespace local_mooc_audit\task;

use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\run_store;
use local_mooc_audit\local\status;

/**
 * Batches through the real Moodle task queue (specs/005 US3): conformant and non conformant courses, partial failure,
 * removed course, revoked permission, duplicated and interrupted tasks, duplicate requests, limits.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\task\audit_course
 * @covers     \local_mooc_audit\local\history\batch_manager
 */
final class audit_course_test extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;

    /**
     * Run every queued adhoc task of the plugin, as cron would.
     *
     * @return string task output
     */
    protected function run_queue(): string {
        ob_start();
        $this->runAdhocTasks(audit_course::class);
        return (string) ob_get_clean();
    }

    /**
     * Courses A and B in the offer category and a manager.
     *
     * @return array [A, B, manager]
     */
    protected function setup_courses(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$a] = $gen->create_course_a(self::YEAR);
        [$b] = $gen->create_course_b(self::YEAR);
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_system::instance()->id);
        return [$a, $b, $manager];
    }

    /**
     * Batch with a conformant course, a non conformant one, a missing id and the site: two runs processed in the
     * background with their own results, two refusals with reasons.
     */
    public function test_batch_conformant_and_non_conformant(): void {
        [$a, $b, $manager] = $this->setup_courses();
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => "{$a->id}, {$b->id} 999999 " . SITEID], $manager->id);
        $this->assertNull($out['error']);
        $this->assertSame(2, $out['queued']);
        $this->assertEqualsCanonicalizing(['not_found', 'site'], array_column($out['skipped'], 'reason'));
        $batch = batch_manager::get($out['batchid']);
        $this->assertSame(2, batch_manager::progress($batch)[run_store::QUEUED]);

        $this->run_queue();
        $runs = batch_manager::runs($batch);
        $this->assertSame(run_store::COMPLETE, $runs[$a->id]->status);
        $this->assertSame(status::OVERALL_CLEAN, $runs[$a->id]->overall);
        $this->assertSame(status::OVERALL_BLOCKED, $runs[$b->id]->overall);
        $this->assertEquals($manager->id, $runs[$b->id]->userid);
        $this->assertSame(2, batch_manager::progress($batch)[run_store::COMPLETE]);
        // The runs are the same as an audit of the page would record (same report contract).
        $report = json_decode(run_store::get((int) $runs[$b->id]->id)->report, true);
        $this->assertSame('1.0', $report['contract_version']);
    }

    /**
     * Partial failure: a course removed after the request fails alone; the batch goes on; "re-run failures" queues it
     * again only when it can be audited.
     */
    public function test_partial_failure_and_retry(): void {
        [$a, $b, $manager] = $this->setup_courses();
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id, $b->id]], $manager->id);
        // Course B has a file without content on purpose: the recycle bin backup would fail.
        set_config('categorybinenable', 0, 'tool_recyclebin');
        delete_course($a, false);
        $this->run_queue();
        $batch = batch_manager::get($out['batchid']);
        $runs = batch_manager::runs($batch);
        $this->assertSame(run_store::FAILED, $runs[$a->id]->status);
        $this->assertSame('course_missing', json_decode($runs[$a->id]->errors, true)[0]['code']);
        $this->assertSame(run_store::COMPLETE, $runs[$b->id]->status);
        $this->setUser($manager);
        $this->assertSame(0, batch_manager::retry_failed($batch, $manager->id), 'Removed course is not queued again');
    }

    /**
     * Permission revoked between the request and the execution: the run fails and the course is not audited.
     */
    public function test_permission_revoked_before_execution(): void {
        global $DB;
        [$a, , $manager] = $this->setup_courses();
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        assign_capability('local/mooc_audit:audit', CAP_PROHIBIT, $managerrole, \context_course::instance($a->id)->id);
        $this->run_queue();
        $run = batch_manager::runs(batch_manager::get($out['batchid']))[$a->id];
        $this->assertSame(run_store::FAILED, $run->status);
        $this->assertSame('permission', json_decode($run->errors, true)[0]['code']);
        $this->assertNull(run_store::get((int) $run->id)->report);
    }

    /**
     * A course already queued is not queued twice; a duplicated task delivery and a task executed after the run finished
     * change nothing.
     */
    public function test_no_duplicates(): void {
        global $DB;
        [$a, , $manager] = $this->setup_courses();
        $first = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $second = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $this->assertSame(0, $second['queued']);
        $this->assertSame('duplicate', $second['skipped'][0]['reason']);
        $runid = (int) batch_manager::runs(batch_manager::get($first['batchid']))[$a->id]->id;
        $this->assertSame($runid, $second['skipped'][0]['runid']);
        $this->assertSame(1, $DB->count_records(run_store::TABLE));
        // The second batch follows the reused run.
        $this->assertSame($runid, (int) batch_manager::runs(batch_manager::get($second['batchid']))[$a->id]->id);

        // Duplicated delivery: a second task for the same run.
        $dup = new audit_course();
        $dup->set_custom_data(['runid' => $runid]);
        $dup->set_userid($manager->id);
        \core\task\manager::queue_adhoc_task($dup);
        $this->run_queue();
        $done = run_store::get($runid);
        $this->assertSame(run_store::COMPLETE, $done->status);
        $this->assertEquals(1, $done->attempts);

        // Executed again later: nothing changes.
        $again = new audit_course();
        $again->set_custom_data(['runid' => $runid]);
        $again->set_userid($manager->id);
        ob_start();
        $again->execute();
        $this->assertStringContainsString('nothing to do', ob_get_clean());
        $this->assertEquals($done, run_store::get($runid));
    }

    /**
     * A run left "running" by a dead process is resumed; after MAX_ATTEMPTS it fails as interrupted. A lock held by
     * another audit of the course makes the task retry later.
     */
    public function test_interrupted_runs(): void {
        global $DB;
        [$a, $b, $manager] = $this->setup_courses();
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id, $b->id]], $manager->id);
        $runs = batch_manager::runs(batch_manager::get($out['batchid']));
        // A: died once while running → resumed.
        $DB->update_record(run_store::TABLE, (object) ['id' => $runs[$a->id]->id, 'status' => run_store::RUNNING,
            'attempts' => 1, 'timestarted' => time() - 600]);
        // B: died three times → given up.
        $DB->update_record(run_store::TABLE, (object) ['id' => $runs[$b->id]->id, 'status' => run_store::RUNNING,
            'attempts' => audit_course::MAX_ATTEMPTS, 'timestarted' => time() - 600]);
        $this->run_queue();
        $ra = run_store::get((int) $runs[$a->id]->id);
        $rb = run_store::get((int) $runs[$b->id]->id);
        $this->assertSame(run_store::COMPLETE, $ra->status);
        $this->assertEquals(2, $ra->attempts);
        $this->assertSame(run_store::FAILED, $rb->status);
        $this->assertSame('interrupted', json_decode($rb->errors, true)[0]['code']);

        // Lock held: the task throws so that the queue retries it. PostgreSQL advisory locks are re-entrant within one
        // connection, so the test uses the record lock factory to simulate a second process.
        global $CFG;
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $run = batch_manager::runs(batch_manager::get($out['batchid']))[$a->id];
        $lock = \core\lock\lock_config::get_lock_factory('local_mooc_audit')->get_lock('course_' . $a->id, 0);
        $task = new audit_course();
        $task->set_custom_data(['runid' => (int) $run->id]);
        try {
            $task->execute();
            $this->fail('The task must not run while another audit of the course holds the lock');
        } catch (\moodle_exception $e) {
            $this->assertSame('locktimeout', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertSame(run_store::QUEUED, run_store::get((int) $run->id)->status);
    }

    /**
     * Selection by category (with and without subcategories) and by start year; refusals by permission; limits.
     */
    public function test_selection_modes_and_limits(): void {
        [$a, $b, $manager] = $this->setup_courses();
        $sub = $this->getDataGenerator()->create_category(['parent' => $a->category]);
        $c = $this->getDataGenerator()->create_course(['category' => $sub->id, 'startdate' => mktime(0, 0, 0, 3, 1, 2031)]);
        $teacher = $this->getDataGenerator()->create_and_enrol($c, 'editingteacher');

        $flat = batch_manager::resolve(['mode' => 'category', 'categoryid' => $a->category], $manager->id);
        $deep = batch_manager::resolve(
            ['mode' => 'category', 'categoryid' => $a->category, 'subcategories' => 1],
            $manager->id
        );
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_keys($flat['accepted']));
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], array_keys($deep['accepted']));
        $year = batch_manager::resolve(['mode' => 'startyear', 'startyear' => 2031], $manager->id);
        $this->assertEquals([$c->id], array_keys($year['accepted']));

        // An editing teacher of C may audit C but has no batch capability, and may not audit A.
        $mine = batch_manager::resolve(['mode' => 'ids', 'courseids' => [$a->id, $c->id]], $teacher->id);
        $this->assertEquals([$c->id], array_keys($mine['accepted']));
        $this->assertSame('permission', $mine['skipped'][0]['reason']);
        try {
            batch_manager::create(['mode' => 'ids', 'courseids' => [$c->id]], $teacher->id);
            $this->fail('A teacher cannot request batches');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        set_config('batch_max_courses', 1, 'local_mooc_audit');
        $toomany = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id, $b->id]], $manager->id);
        $this->assertSame('toomany', $toomany['error']);
        $this->assertSame(0, $toomany['batchid']);
        $none = batch_manager::create(['mode' => 'ids', 'courseids' => [999999]], $manager->id);
        $this->assertSame('nothing_to_audit', $none['error']);
    }

    /**
     * The concurrency limit of the task follows the plugin setting.
     */
    public function test_concurrency_limit(): void {
        $this->resetAfterTest();
        $task = new audit_course();
        $this->assertSame(2, $task->get_concurrency_limit());
        set_config('batch_concurrency', 4, 'local_mooc_audit');
        $this->assertSame(4, $task->get_concurrency_limit());
    }

    /**
     * A dead pending run (queued without task, or running for too long) is not reused by a new batch; a failed run that
     * a batch had reused is queued again by "re-run failures" (review finding 4).
     */
    public function test_dead_pending_runs_are_not_reused(): void {
        global $DB;
        [$a, $b, $manager] = $this->setup_courses();
        $lost = run_store::create((int) $a->id, run_store::ORIGIN_BATCH, (int) $manager->id);
        $old = run_store::create((int) $b->id, run_store::ORIGIN_PAGE, (int) $manager->id, 0, run_store::RUNNING);
        $DB->set_field(run_store::TABLE, 'timestarted', time() - run_store::STALE_RUNNING - 60, ['id' => $old]);
        $out = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id, $b->id]], $manager->id);
        $this->assertSame(2, $out['queued']);
        $this->assertSame(0, $out['reused']);

        // A live queued run is reused; when it fails, the batch that reused it can queue the course again.
        $this->run_queue();
        $first = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $second = batch_manager::create(['mode' => 'ids', 'courseids' => [$a->id]], $manager->id);
        $this->assertSame(1, $second['reused']);
        $reused = (int) $second['skipped'][0]['runid'];
        \local_mooc_audit\local\history\recorder::fail($reused, 'exception', 'simulated');
        $DB->delete_records('task_adhoc', ['classname' => '\\' . audit_course::class]);
        $this->setUser($manager);
        $batch = batch_manager::get($second['batchid']);
        $this->assertSame(run_store::FAILED, batch_manager::runs($batch)[$a->id]->status);
        $this->assertSame(1, batch_manager::retry_failed($batch, (int) $manager->id));
        $this->assertSame(run_store::QUEUED, run_store::get($lost)->status, 'left for the daily cleanup (stale)');
        $this->assertNotEquals($first['batchid'], $second['batchid']);
    }
}
