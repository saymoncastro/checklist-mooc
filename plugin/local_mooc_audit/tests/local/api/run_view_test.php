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

use core_external\external_api;
use local_mooc_audit\local\history\policy_store;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * Projection of a run for the external API (specs/004 RunView).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\api\run_view
 */
final class run_view_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * Complete, partial, failed and queued runs: lists instead of maps, the stored report untouched, no requester, and
     * the structure accepts every state.
     */
    public function test_export_every_state(): void {
        $this->setup_site();
        [$b] = $this->gen()->create_course_b(self::YEAR);
        $first = $this->audit_now((int) $b->id);
        $second = $this->audit_now((int) $b->id);
        $partial = run_store::create((int) $b->id, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        run_store::finish($partial, run_store::PARTIAL, ['overall' => 'REQUER_CORRECOES',
            'policyid' => policy_store::ensure(\local_mooc_audit\policy::load()), 'report' => '{"x":1}',
            'errors' => [['code' => 'rule_error', 'rule' => 'CL-04', 'message' => 'boom']]]);
        $failed = run_store::create((int) $b->id, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        recorder::fail($failed, 'exception', 'Falhou');
        $queued = recorder::queue((int) $b->id, 2, 0, run_store::ORIGIN_API);

        $view = run_view::export(run_store::get((int) $second->id), true, false);
        $this->assertSame('complete', $view['status']);
        $this->assertSame('BLOQUEADA', $view['overall']);
        $this->assertSame(self::YEAR, $view['reofferyear']);
        $this->assertSame(run_store::get((int) $second->id)->report, $view['report'], 'stored report, byte by byte');
        $this->assertSame(json_decode($second->summary, true)['regras'], $view['summary']['rules']);
        $nc = json_decode($second->summary, true)['por_status']['NAO_CONFORME'];
        $this->assertContains(['status' => 'NAO_CONFORME', 'count' => $nc], $view['summary']['by_status']);
        $this->assertSame((int) $first->id, $view['evolution']['baserunid']);
        $this->assertSame(['new', 'changed', 'persistent', 'replaced', 'unverifiable', 'not_comparable', 'removed',
            'resolved'], array_keys($view['evolution']['counts']));
        $this->assertArrayNotHasKey('items', $view['evolution']);
        $this->assertSame(policy_store::get((int) $second->policyid)->fingerprint, $view['policy']['fingerprint']);

        $withitems = run_view::export(run_store::get((int) $second->id), false, true);
        $this->assertArrayNotHasKey('report', $withitems);
        $this->assertSame(array_sum($view['evolution']['counts']), count($withitems['evolution']['items']));

        $p = run_view::export(run_store::get($partial), true, false);
        $this->assertSame('{"x":1}', $p['report']);
        $this->assertSame([['code' => 'rule_error', 'rule' => 'CL-04', 'message' => 'boom']], $p['errors']);
        $f = run_view::export(run_store::get($failed), true, true);
        $this->assertNull($f['overall']);
        $this->assertArrayNotHasKey('report', $f);
        $this->assertArrayNotHasKey('summary', $f);
        $this->assertSame([['code' => 'exception', 'message' => 'Falhou']], $f['errors']);
        $q = run_view::export(run_store::get($queued), true, true);
        $this->assertSame(['queued', 'api', 0, 0], [$q['status'], $q['origin'], $q['timestarted'], $q['timefinished']]);
        $this->assertArrayNotHasKey('policy', $q);

        foreach ([$view, $withitems, $p, $f, $q] as $v) {
            $this->assertArrayNotHasKey('userid', $v);
            $this->assertEquals($v, external_api::clean_returnvalue(run_view::structure(), $v));
        }
    }

    /**
     * A base removed by the retention: the stored counts remain, the items are reported as unavailable.
     */
    public function test_items_unavailable_without_base(): void {
        global $DB;
        $this->setup_site();
        [$b] = $this->gen()->create_course_b(self::YEAR);
        $first = $this->audit_now((int) $b->id);
        $second = $this->audit_now((int) $b->id);
        $DB->delete_records(run_store::TABLE, ['id' => $first->id]);
        $view = run_view::export(run_store::get((int) $second->id), false, true);
        $this->assertTrue($view['evolution']['items_unavailable']);
        $this->assertArrayHasKey('counts', $view['evolution']);
        $this->assertArrayNotHasKey('items', $view['evolution']);
    }
}
