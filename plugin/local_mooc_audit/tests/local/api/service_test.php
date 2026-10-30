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

namespace local_mooc_audit\local\api;

use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * Requests of the external API (specs/004 FR-008–FR-011): reuse of the pending run, limit per requester, lock.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\api\service
 */
final class service_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * An alive pending run of a batch is reused; a dead one is not; two requests in a row return the same run.
     */
    public function test_reuse(): void {
        $this->setup_site();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $batch = recorder::queue((int) $c1->id, 2, 0);
        $out = service::request((int) $c1->id, (int) $user->id);
        $this->assertSame([$batch, true, 'queued'], [$out['runid'], $out['reused'], $out['status']]);

        $dead = run_store::create((int) $c2->id, run_store::ORIGIN_BATCH, 2);
        $first = service::request((int) $c2->id, (int) $user->id);
        $this->assertFalse($first['reused']);
        $this->assertNotSame($dead, $first['runid']);
        $this->assertSame(run_store::ORIGIN_API, run_store::get($first['runid'])->origin);
        $again = service::request((int) $c2->id, (int) $user->id);
        $this->assertSame([$first['runid'], true], [$again['runid'], $again['reused']]);
    }

    /**
     * Limit of pending API runs per requester: another course is refused, the same course is reused; out of range values
     * of the setting.
     */
    public function test_limit(): void {
        $this->setup_site();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        set_config('api_max_pending', 1, 'local_mooc_audit');
        $first = service::request((int) $c1->id, (int) $user->id);
        $this->assertSame('queuefull', $this->error_of(fn() => service::request((int) $c2->id, (int) $user->id)));
        $this->assertFalse(run_store::reusable_pending((int) $c2->id) !== null, 'nothing queued');
        $this->assertTrue(service::request((int) $c1->id, (int) $user->id)['reused']);
        $other = $this->getDataGenerator()->create_user();
        $this->assertFalse(service::request((int) $c2->id, (int) $other->id)['reused'], 'the limit is per requester');
        $this->assertNotSame($first['runid'], run_store::reusable_pending((int) $c2->id)->id);

        foreach (['' => 100, '0' => 100, '-5' => 100, '1' => 1, '2000' => 2000, '5000' => 2000] as $value => $max) {
            set_config('api_max_pending', $value, 'local_mooc_audit');
            $this->assertSame($max, service::max_pending(), "setting '$value'");
        }
    }

    /**
     * Another request of the same course holds the lock: busy, nothing created.
     */
    public function test_busy(): void {
        global $CFG, $DB;
        $this->setup_site();
        // Advisory locks of PostgreSQL are re-entrant within one connection: use row locks to hold one in the test.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $course = $this->getDataGenerator()->create_course();
        $lock = \core\lock\lock_config::get_lock_factory('local_mooc_audit')->get_lock('request_course_' . $course->id, 0);
        $this->assertNotFalse($lock);
        $before = $DB->count_records(run_store::TABLE);
        try {
            $this->assertSame('busy', $this->error_of(fn() => service::request((int) $course->id, 2)));
        } finally {
            $lock->release();
        }
        $this->assertSame($before, $DB->count_records(run_store::TABLE));
        $this->assertFalse(service::request((int) $course->id, 2)['reused']);
    }
}
