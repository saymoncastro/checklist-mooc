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
 * Alive pending runs per requester and origin (specs/004 FR-011).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\run_store
 */
final class run_store_test extends \advanced_testcase {
    /**
     * Only alive queued or running runs of the requester and origin are counted; alive_pending() is unchanged.
     */
    public function test_count_alive_pending_by_origin(): void {
        global $DB;
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $queued = recorder::queue((int) $c1->id, (int) $u->id, 0, run_store::ORIGIN_API);
        $running = run_store::create((int) $c2->id, run_store::ORIGIN_API, (int) $u->id, 0, run_store::RUNNING);
        // Dead: queued without its task, and running for too long.
        run_store::create((int) $c2->id, run_store::ORIGIN_API, (int) $u->id);
        $old = run_store::create((int) $c1->id, run_store::ORIGIN_API, (int) $u->id, 0, run_store::RUNNING);
        $DB->set_field(run_store::TABLE, 'timestarted', time() - run_store::STALE_RUNNING - 1, ['id' => $old]);
        // Other origin, other requester, final.
        recorder::queue((int) $c1->id, (int) $u->id, 0);
        recorder::queue((int) $c1->id, (int) $other->id, 0, run_store::ORIGIN_API);
        $done = run_store::create((int) $c2->id, run_store::ORIGIN_API, (int) $u->id, 0, run_store::RUNNING);
        run_store::finish($done, run_store::FAILED);

        $this->assertSame(2, run_store::count_alive_pending_by_origin((int) $u->id, run_store::ORIGIN_API));
        $this->assertSame(1, run_store::count_alive_pending_by_origin((int) $u->id, run_store::ORIGIN_BATCH));
        $this->assertSame(1, run_store::count_alive_pending_by_origin((int) $other->id, run_store::ORIGIN_API));
        $this->assertSame(0, run_store::count_alive_pending_by_origin((int) $u->id, run_store::ORIGIN_PAGE));
        $this->assertSame(1, run_store::count_alive_pending_by_origin(
            (int) $u->id,
            run_store::ORIGIN_API,
            time() + run_store::STALE_RUNNING + 1
        ), 'the running one dies with time');

        $pending = run_store::alive_pending([(int) $c1->id, (int) $c2->id]);
        $this->assertSame($queued, (int) $pending[$c1->id]->id, 'oldest alive run of the course');
        $this->assertSame($running, (int) $pending[$c2->id]->id);
    }
}
