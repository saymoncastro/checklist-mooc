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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * Last recorded audit of a course (shared by the audit page and the external API, specs/004 US1).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\latest
 */
final class latest_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * Never audited, valid and current, changed course, old result, policy changed, newer failure, alive pending run.
     */
    public function test_for_course(): void {
        global $DB;
        $this->setup_site();
        [$a] = $this->gen()->create_course_a(self::YEAR);
        $course = fn() => $DB->get_record('course', ['id' => $a->id]);

        $none = latest::for_course($course());
        $this->assertSame(['valid' => null, 'stale' => [], 'lastfailure' => null, 'pending' => null], $none);

        $run = $this->audit_now((int) $a->id);
        $now = latest::for_course($course());
        $this->assertSame((int) $run->id, (int) $now['valid']->id);
        $this->assertSame([], $now['stale']);
        $this->assertNull($now['lastfailure']);

        $this->assertSame(['age'], latest::for_course($course(), time() + (dashboard::stale_days() + 1) * DAYSECS)['stale']);
        set_config(
            'policyjson',
            json_encode(['reoffer_year' => self::YEAR, 'min_quiz_attempts' => 3]),
            'local_mooc_audit'
        );
        $this->assertContains('policy_changed', latest::for_course($course())['stale']);
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
        $this->waitForSecond();
        $DB->set_field('course', 'timemodified', time(), ['id' => $a->id]);
        $this->assertSame(['course_changed'], latest::for_course($course())['stale']);

        $this->waitForSecond();
        $failed = run_store::create((int) $a->id, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        recorder::fail($failed, 'exception', 'x');
        $pending = recorder::queue((int) $a->id, 2, 0, run_store::ORIGIN_API);
        $after = latest::for_course($course());
        $this->assertSame((int) $run->id, (int) $after['valid']->id, 'a failure never hides the last result');
        $this->assertSame($failed, (int) $after['lastfailure']->id);
        $this->assertSame($pending, (int) $after['pending']->id);
    }
}
