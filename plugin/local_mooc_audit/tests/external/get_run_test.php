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
use local_mooc_audit\local\history\evolution;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * One run by id through the external API (specs/004 US3).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\external\get_run
 * @covers     \local_mooc_audit\local\api\service
 */
final class get_run_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * Call the function as the Web Services layer does.
     *
     * @param int $runid
     * @param bool $report
     * @param bool $items
     * @return array run
     */
    private function call(int $runid, bool $report = true, bool $items = false): array {
        $out = external_api::clean_returnvalue(get_run::execute_returns(), get_run::execute($runid, $report, $items));
        $this->assertSame('1.0', $out['api_version']);
        return $out['run'];
    }

    /**
     * Complete run with the items of the history page, without report, failed, queued, base removed; nothing written.
     */
    public function test_get_run(): void {
        global $DB;
        $this->setup_site();
        [$b] = $this->gen()->create_course_b(self::YEAR);
        $first = $this->audit_now((int) $b->id);
        $second = $this->audit_now((int) $b->id);
        $failed = run_store::create((int) $b->id, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        recorder::fail($failed, 'exception', 'Falha de teste');
        $queued = recorder::queue((int) $b->id, 2, 0, run_store::ORIGIN_API);
        $this->setUser($this->integration_user());
        $before = $this->writes();

        $run = $this->call((int) $second->id, true, true);
        $this->assertSame(run_store::get((int) $second->id)->report, $run['report']);
        $expected = array_map(fn($i) => array_filter($i, fn($v) => $v !== null), evolution::for_run($second)['items']);
        $this->assertEquals($expected, $run['evolution']['items'], 'same items as history.php');
        $this->assertArrayNotHasKey('report', $this->call((int) $second->id, false));
        $f = $this->call($failed);
        $this->assertSame(['failed', [['code' => 'exception', 'message' => 'Falha de teste']]], [$f['status'], $f['errors']]);
        $this->assertArrayNotHasKey('report', $f);
        $q = $this->call($queued);
        $this->assertSame(['queued', 'api'], [$q['status'], $q['origin']]);
        $this->assertArrayNotHasKey('report', $q);
        $this->assertSame($before, $this->writes(), 'a read writes nothing (FR-020)');

        $this->setAdminUser();
        $DB->delete_records(run_store::TABLE, ['id' => $first->id]);
        $this->setUser($this->integration_user());
        $gone = $this->call((int) $second->id, false, true);
        $this->assertTrue($gone['evolution']['items_unavailable']);
        $this->assertArrayHasKey('counts', $gone['evolution']);
    }

    /**
     * A missing run and a run out of reach give the same error; a removed course is readable with viewdashboard only.
     */
    public function test_not_found_and_removed_course(): void {
        global $DB;
        $this->setup_site();
        $cat = $this->getDataGenerator()->create_category();
        $mine = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $other = $this->getDataGenerator()->create_course();
        $minerun = $this->audit_now((int) $mine->id);
        $otherrun = $this->audit_now((int) $other->id);
        $user = $this->integration_user(\context_coursecat::instance($cat->id));
        $this->setUser($user);
        $this->assertSame((int) $minerun->id, $this->call((int) $minerun->id)['id']);

        $errors = [];
        foreach ([(int) $otherrun->id, 999999, 0] as $id) {
            try {
                get_run::execute($id);
                $errors[] = 'no error';
            } catch (\moodle_exception $e) {
                $errors[] = [$e->errorcode, $e->getMessage()];
            }
        }
        $this->assertSame([['runnotfound', get_string('runnotfound', 'local_mooc_audit')]], array_values(array_unique(
            $errors,
            SORT_REGULAR
        )));

        $this->setAdminUser();
        set_config('categorybinenable', 0, 'tool_recyclebin');
        delete_course($mine, false);
        $this->setUser($user);
        $this->assertSame('runnotfound', $this->error_of(fn() => get_run::execute((int) $minerun->id)));
        $viewer = $this->integration_user();
        $roleid = create_role('Painel', 'painel', '');
        assign_capability('local/mooc_audit:viewdashboard', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $viewer->id, \context_system::instance()->id);
        $this->setUser($viewer);
        $this->assertSame((int) $minerun->id, $this->call((int) $minerun->id, false)['id']);
    }
}
