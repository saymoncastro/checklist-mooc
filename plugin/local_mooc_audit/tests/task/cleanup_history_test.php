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
use local_mooc_audit\local\history\policy_store;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

/**
 * Retention of the history (specs/005 FR-022, FR-034): old runs deleted except the latest valid run of each existing
 * course, runs of removed courses deleted, stale runs failed, orphan batches and policies deleted; bounds of the setting.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\task\cleanup_history
 */
final class cleanup_history_test extends \advanced_testcase {
    /**
     * A finished run with a given end time.
     *
     * @param int $courseid
     * @param string $status
     * @param int $finished
     * @param int $batchid
     * @return int
     */
    protected function run_at(int $courseid, string $status, int $finished, int $batchid = 0): int {
        global $DB;
        $id = run_store::create($courseid, run_store::ORIGIN_BATCH, 0, $batchid, run_store::RUNNING);
        run_store::finish($id, $status, ['policyid' => policy_store::ensure(\local_mooc_audit\policy::load())]);
        $DB->set_field(run_store::TABLE, 'timefinished', $finished, ['id' => $id]);
        $DB->set_field(run_store::TABLE, 'timecreated', $finished - 10, ['id' => $id]);
        return $id;
    }

    /**
     * Apply the retention.
     */
    public function test_purge(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $old = $now - 400 * DAYSECS;
        $course = $this->getDataGenerator()->create_course();
        $removed = $this->getDataGenerator()->create_course();
        $batch = $DB->insert_record(batch_manager::TABLE, (object) ['userid' => 0, 'name' => 'old', 'criteria' => '{}',
            'timecreated' => $old]);

        $older = $this->run_at((int) $course->id, run_store::COMPLETE, $old - DAYSECS, $batch);
        $latestvalid = $this->run_at((int) $course->id, run_store::COMPLETE, $old);
        $failed = $this->run_at((int) $course->id, run_store::FAILED, $old + DAYSECS);
        $recent = $this->run_at((int) $course->id, run_store::PARTIAL, $now - DAYSECS);
        $gone = $this->run_at((int) $removed->id, run_store::COMPLETE, $old);
        delete_course($removed, false);
        // Queued two days ago without a task (the task was lost) / with a task (still waiting for cron).
        $lost = run_store::create((int) $course->id, run_store::ORIGIN_BATCH, 0);
        $DB->set_field(run_store::TABLE, 'timecreated', $now - 2 * DAYSECS, ['id' => $lost]);
        $waiting = recorder::queue((int) $course->id, 2, 0);
        $DB->set_field(run_store::TABLE, 'timecreated', $now - 2 * DAYSECS, ['id' => $waiting]);
        $orphan = $DB->insert_record(policy_store::TABLE, (object) ['fingerprint' => str_repeat('a', 40),
            'policyversion' => 'x', 'policy' => '{}', 'timecreated' => $now - 2 * DAYSECS]);

        $out = cleanup_history::purge($now);
        $this->assertSame(1, $out['stale']);
        $this->assertSame(run_store::FAILED, run_store::get($lost)->status);
        $this->assertSame('stale', run_store::json(run_store::get($lost), 'errors')[0]['code']);
        $this->assertSame(run_store::QUEUED, run_store::get($waiting)->status);
        // The latest valid run of an existing course is the current state: kept, even if old; the newer partial run is
        // valid too, so the old complete one goes.
        $this->assertNull(run_store::get($older));
        $this->assertNull(run_store::get($latestvalid));
        $this->assertNull(run_store::get($failed));
        $this->assertNull(run_store::get($gone));
        $this->assertNotNull(run_store::get($recent));
        $this->assertSame(4, $out['runs']);
        $this->assertFalse($DB->record_exists(batch_manager::TABLE, ['id' => $batch]));
        $this->assertFalse($DB->record_exists(policy_store::TABLE, ['id' => $orphan]));
        $this->assertTrue($DB->record_exists(policy_store::TABLE, []));

        // Only one old valid run: kept as the current state.
        $alone = $this->getDataGenerator()->create_course();
        $keep = $this->run_at((int) $alone->id, run_store::COMPLETE, $old);
        cleanup_history::purge($now);
        $this->assertNotNull(run_store::get($keep));
    }

    /**
     * The retention setting is bounded: never indefinite, never below a month.
     */
    public function test_retention_bounds(): void {
        $this->resetAfterTest();
        $this->assertSame(365, cleanup_history::retention_days());
        set_config('history_retention_days', 5, 'local_mooc_audit');
        $this->assertSame(30, cleanup_history::retention_days());
        set_config('history_retention_days', 100000, 'local_mooc_audit');
        $this->assertSame(1825, cleanup_history::retention_days());
        set_config('history_retention_days', 0, 'local_mooc_audit');
        $this->assertSame(365, cleanup_history::retention_days());
    }
}
