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

namespace local_mooc_audit\external;

use core_external\external_api;
use local_mooc_audit\local\history\run_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * Requesting an audit through the external API and following it to the end (specs/004 US2).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\external\request_audit
 * @covers     \local_mooc_audit\local\api\service
 */
final class request_audit_test extends \local_mooc_audit\tests\api_testcase {
    /** @var string Enrolment key set in the course: never part of a response (FR-029). */
    const SECRET = 'Chave-Secreta-4321';

    /**
     * Request: a queued API run and its task, no rule run in the call; the queue audits it with the policy in force and
     * the evolution against the previous result; no response carries the enrolment key.
     */
    public function test_request_and_follow(): void {
        global $DB;
        $this->setup_site();
        [$b] = $this->gen()->create_course_b(self::YEAR);
        $DB->set_field('enrol', 'password', self::SECRET, ['courseid' => $b->id, 'enrol' => 'self']);
        $previous = $this->audit_now((int) $b->id);
        $user = $this->integration_user();
        $this->setUser($user);
        $policies = $DB->count_records('local_mooc_audit_policy');

        $out = external_api::clean_returnvalue(request_audit::execute_returns(), request_audit::execute((int) $b->id));
        $this->assertSame(
            ['api_version' => '1.0', 'courseid' => (int) $b->id, 'status' => 'queued', 'reused' => false],
            array_intersect_key($out, array_flip(['api_version', 'courseid', 'status', 'reused']))
        );
        $run = run_store::get($out['runid']);
        $this->assertSame(
            [run_store::ORIGIN_API, 0, (int) $user->id, run_store::QUEUED],
            [$run->origin, (int) $run->batchid, (int) $run->userid, $run->status]
        );
        $this->assertSame((int) $run->timecreated, $out['timecreated']);
        $this->assertNotNull(run_store::reusable_pending((int) $b->id), 'its background task exists');
        $this->assertSame(0, $DB->count_records_select(
            run_store::TABLE,
            'id <> :id AND status IN (:q, :r)',
            ['id' => $run->id, 'q' => run_store::QUEUED, 'r' => run_store::RUNNING]
        ));
        $this->assertSame($policies, $DB->count_records('local_mooc_audit_policy'), 'no rule ran in the call');

        $this->run_queue();
        $done = run_store::get($out['runid']);
        $this->assertSame(run_store::COMPLETE, $done->status);
        $this->assertSame((int) $previous->id, (int) $done->previousrunid);
        $this->assertSame((int) $previous->id, run_store::json($done, 'evolution')['base']);
        $this->assertSame(self::YEAR, (int) $done->reofferyear, 'policy in force, no batch override');

        $responses = [$out, get_run::execute($out['runid'], true, true),
            get_latest_audit::execute((int) $b->id, true, true)];
        $this->assertStringNotContainsString(self::SECRET, json_encode($responses));
        $this->assertStringNotContainsString(self::SECRET, (string) $done->report);
    }
}
