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
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/schema_validator.php');

/**
 * Last recorded audit of a course through the external API (specs/004 US1): the result the audit page shows, without
 * running nor writing anything.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\external\get_latest_audit
 * @covers     \local_mooc_audit\local\api\service
 */
final class get_latest_audit_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * Call the function as the Web Services layer does.
     *
     * @param int $courseid
     * @param bool $report
     * @param bool $items
     * @return array
     */
    private function call(int $courseid, bool $report = true, bool $items = false): array {
        return external_api::clean_returnvalue(
            get_latest_audit::execute_returns(),
            get_latest_audit::execute($courseid, $report, $items)
        );
    }

    /**
     * Recorded audit: the stored report, valid in the schema and equal to the JSON of the audit page; options; nothing
     * written.
     */
    public function test_latest_audit(): void {
        $this->setup_site();
        [$b] = $this->gen()->create_course_b(self::YEAR);
        $this->audit_now((int) $b->id);
        $run = $this->audit_now((int) $b->id);
        $this->setUser($this->integration_user());
        $before = $this->writes();

        $out = $this->call((int) $b->id);
        $this->assertSame('1.0', $out['api_version']);
        $this->assertTrue($out['found']);
        $this->assertSame((int) $run->id, $out['run']['id']);
        $this->assertSame([], $out['stale_reasons']);
        $this->assertArrayNotHasKey('last_failure', $out);
        $this->assertArrayNotHasKey('pending', $out);
        $report = json_decode($out['run']['report'], true);
        $this->assertSame(run_store::json(run_store::get((int) $run->id), 'report'), $report, 'same as index.php?format=json');
        $validator = new \local_mooc_audit\tests\schema_validator(__DIR__ . '/../../schema/report-v1.0.schema.json');
        $this->assertSame([], $validator->errors(json_decode($out['run']['report'], true)));
        $this->assertArrayNotHasKey('items', $out['run']['evolution']);

        $light = $this->call((int) $b->id, false, true);
        $this->assertArrayNotHasKey('report', $light['run']);
        $this->assertSame(array_sum($light['run']['evolution']['counts']), count($light['run']['evolution']['items']));
        $this->assertSame($before, $this->writes(), 'a read writes nothing (FR-020)');
    }

    /**
     * Never audited (with and without a pending run), outdated result, newer failure.
     */
    public function test_not_found_stale_and_failure(): void {
        global $DB;
        $this->setup_site();
        [$a] = $this->gen()->create_course_a(self::YEAR);
        $user = $this->integration_user();
        $this->setUser($user);
        $this->assertSame(['api_version' => '1.0', 'found' => false], $this->call((int) $a->id));

        $pending = recorder::queue((int) $a->id, (int) $user->id, 0, run_store::ORIGIN_API);
        $out = $this->call((int) $a->id);
        $this->assertFalse($out['found']);
        $this->assertSame(['id' => $pending, 'status' => 'queued', 'origin' => 'api'], array_intersect_key(
            $out['pending'],
            array_flip(['id', 'status', 'origin'])
        ));
        $this->run_queue();

        $this->waitForSecond();
        $DB->set_field('course', 'timemodified', time(), ['id' => $a->id]);
        $failed = run_store::create((int) $a->id, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        recorder::fail($failed, 'exception', 'Falha de teste');
        $out = $this->call((int) $a->id, false);
        $this->assertTrue($out['found']);
        $this->assertSame($pending, $out['run']['id']);
        $this->assertSame('api', $out['run']['origin']);
        $this->assertSame(['course_changed'], $out['stale_reasons']);
        $this->assertSame($failed, $out['last_failure']['id']);
        $this->assertSame([['code' => 'exception', 'message' => 'Falha de teste']], $out['last_failure']['errors']);
    }
}
