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

namespace local_mooc_audit;

use local_mooc_audit\local\history\access;
use local_mooc_audit\local\history\dashboard;
use local_mooc_audit\local\history\run_store;

/**
 * Access to the history, the dashboard and the batches (specs/005 FR-029, FR-030, SC-007): editing teachers read the
 * history of their courses only; managers read everything; a run id of another course is refused; the stored history
 * of a removed course is for dashboard viewers; nobody but managers gets the new capabilities by default.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\access
 */
final class history_access_test extends \advanced_testcase {
    /**
     * Whether a call throws an access exception.
     *
     * @param callable $fn
     * @return bool
     */
    private function denied(callable $fn): bool {
        try {
            $fn();
            return false;
        } catch (\required_capability_exception | \moodle_exception $e) {
            return true;
        }
    }

    /**
     * Teacher, student, manager and a removed course.
     */
    public function test_history_access(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_course();
        $b = $gen->create_course();
        $teacher = $gen->create_and_enrol($a, 'editingteacher');
        $student = $gen->create_and_enrol($a, 'student');
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id, \context_system::instance()->id);
        $runa = run_store::create((int) $a->id, 'page', (int) $teacher->id);
        $runb = run_store::create((int) $b->id, 'page', (int) $manager->id);

        $this->setUser($teacher);
        $this->assertFalse($this->denied(fn() => access::require_history((int) $a->id)));
        $this->assertTrue($this->denied(fn() => access::require_history((int) $b->id)), 'teacher of A only');
        $this->assertSame($runa, (int) access::run_of_course($runa, (int) $a->id)->id);
        $this->assertTrue($this->denied(fn() => access::run_of_course($runb, (int) $a->id)), 'run id of another course');
        $this->assertTrue($this->denied(fn() => access::run_of_course(999999, (int) $a->id)));

        $this->setUser($student);
        $this->assertTrue($this->denied(fn() => access::require_history((int) $a->id)));

        $this->setUser($manager);
        $this->assertFalse($this->denied(fn() => access::require_history((int) $b->id)));

        // Removed course: stored history for dashboard viewers only.
        delete_course($b, false);
        $this->assertSame([null, \context_system::instance()], access::require_history((int) $b->id));
        $this->setUser($teacher);
        $this->assertTrue($this->denied(fn() => access::require_history((int) $b->id)));
        // A course id without history and the site are refused.
        $this->setUser($manager);
        $this->assertTrue($this->denied(fn() => access::require_history(999999)));
        $this->assertTrue($this->denied(fn() => access::require_history(SITEID)));
        $this->assertSame(1, $DB->count_records(run_store::TABLE, ['courseid' => $b->id]));
    }

    /**
     * New capabilities: managers only by default; the dashboard lists only courses the viewer may audit (a prohibition
     * in one course hides it).
     */
    public function test_dashboard_capabilities_and_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_course();
        $b = $gen->create_course();
        $teacher = $gen->create_and_enrol($a, 'editingteacher');
        $manager = $gen->create_user();
        $gen->role_assign('manager', $manager->id, \context_system::instance()->id);
        $system = \context_system::instance();
        foreach (['viewdashboard', 'batchaudit'] as $cap) {
            $this->assertTrue(has_capability("local/mooc_audit:$cap", $system, $manager), $cap);
            $this->assertFalse(has_capability("local/mooc_audit:$cap", $system, $teacher), $cap);
        }
        $archetypes = array_keys(get_roles_with_capability('local/mooc_audit:batchaudit', CAP_ALLOW, $system));
        $roles = $DB->get_records_list('role', 'id', $archetypes, '', 'id, shortname');
        $this->assertSame(['manager'], array_values(array_map(fn($r) => $r->shortname, $roles)));

        $ids = fn(int $userid) => array_map('intval', array_keys(dashboard::scope($userid, dashboard::clean_filters([]))));
        $this->assertEqualsCanonicalizing([(int) $a->id, (int) $b->id], $ids((int) $manager->id));
        $this->assertSame([(int) $a->id], $ids((int) $teacher->id));
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        assign_capability('local/mooc_audit:audit', CAP_PROHIBIT, $managerrole, \context_course::instance($b->id)->id);
        $this->assertSame([(int) $a->id], $ids((int) $manager->id));
    }
}
