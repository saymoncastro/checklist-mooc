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

/**
 * Who may compare offers (specs/003 FR-004, decision kept: managers only): the capability is required in BOTH course
 * contexts; editing teachers audit but do not compare; the course search lists only allowed courses.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\comparator::require_access
 */
final class compare_access_test extends \advanced_testcase {
    /**
     * Whether require_access passes for the current user.
     *
     * @param int $source
     * @param int $target
     * @return bool
     */
    private function allowed(int $source, int $target): bool {
        try {
            comparator::require_access($source, $target);
            return true;
        } catch (\required_capability_exception $e) {
            return false;
        }
    }

    /**
     * Site manager, category manager, editing teacher, student and the archetype of the capability.
     */
    public function test_capability_in_both_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cat1 = $gen->create_category();
        $cat2 = $gen->create_category();
        $old = $gen->create_course(['category' => $cat1->id]);
        $new = $gen->create_course(['category' => $cat1->id]);
        $other = $gen->create_course(['category' => $cat2->id]);
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);

        $sitemanager = $gen->create_user();
        role_assign($managerrole, $sitemanager->id, \context_system::instance()->id);
        $catmanager = $gen->create_user();
        role_assign($managerrole, $catmanager->id, \context_coursecat::instance($cat1->id)->id);
        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $old->id, 'editingteacher');
        $gen->enrol_user($teacher->id, $new->id, 'editingteacher');
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $old->id, 'student');
        $gen->enrol_user($student->id, $new->id, 'student');

        $this->assertSame('site', comparator::page_access((int) $sitemanager->id));
        $this->assertSame('courses', comparator::page_access((int) $catmanager->id));
        $this->assertNull(comparator::page_access((int) $teacher->id));
        $this->assertNull(comparator::page_access((int) $student->id));

        $this->setUser($sitemanager);
        $this->assertTrue($this->allowed($old->id, $new->id));
        $this->assertTrue($this->allowed($old->id, $other->id));

        $this->setUser($catmanager);
        $this->assertTrue($this->allowed($old->id, $new->id));
        $this->assertFalse($this->allowed($old->id, $other->id), 'allowed on the source only');
        $this->assertFalse($this->allowed($other->id, $new->id), 'allowed on the target only');

        $this->setUser($teacher);
        $this->assertTrue(has_capability('local/mooc_audit:audit', \context_course::instance($old->id)));
        $this->assertFalse($this->allowed($old->id, $new->id), 'editing teachers audit but do not compare');

        $this->setUser($student);
        $this->assertFalse($this->allowed($old->id, $new->id));

        $cap = $DB->get_record('capabilities', ['name' => 'local/mooc_audit:compare'], '*', MUST_EXIST);
        $this->assertSame(['read', CONTEXT_SYSTEM, 0], [$cap->captype, (int) $cap->contextlevel, (int) $cap->riskbitmask]);
    }

    /**
     * The course pickers list only the courses where the user holds the capability.
     */
    public function test_course_search_is_restricted(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cat1 = $gen->create_category();
        $cat2 = $gen->create_category();
        $mine = $gen->create_course(['category' => $cat1->id]);
        $gen->create_course(['category' => $cat2->id]);
        $catmanager = $gen->create_user();
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $catmanager->id,
            \context_coursecat::instance($cat1->id)->id
        );
        $this->setUser($catmanager);
        $courses = get_user_capability_course('local/mooc_audit:compare', null, true, '', 'id');
        $this->assertSame([(int) $mine->id], array_map(fn($c) => (int) $c->id, array_values($courses)));

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $this->setUser($teacher);
        $this->assertEmpty(get_user_capability_course('local/mooc_audit:compare', null, true, '', 'id'));
    }

    /**
     * A missing course gets the same answer as a course without permission (course ids are not revealed).
     */
    public function test_missing_course_same_answer(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($this->allowed($course->id, 999999));
        $this->assertFalse($this->allowed(SITEID, $course->id));
    }

    /**
     * The course pickers show short name, full name, start year and id whatever the site setting, and list only the
     * courses the user may compare (never the site course).
     *
     * @covers \local_mooc_audit\form\compare_form::course_options
     */
    public function test_course_options_labels(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/mooc_audit/classes/form/compare_form.php');
        $this->resetAfterTest();
        set_config('courselistshortnames', 0);
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category();
        $tz = new \DateTimeZone(\core_date::get_server_timezone());
        $old = $gen->create_course(['category' => $cat->id, 'fullname' => 'Acessibilidade e Tecnologia', 'shortname' => 'AT_2026',
            'startdate' => (new \DateTime('2026-01-01 00:00', $tz))->getTimestamp()]);
        $new = $gen->create_course(['category' => $cat->id, 'fullname' => 'Acessibilidade e Tecnologia', 'shortname' => 'AT_2027',
            'startdate' => (new \DateTime('2027-01-01 00:00', $tz))->getTimestamp()]);
        $gen->create_course(['fullname' => 'Fora da categoria']);
        $catmanager = $gen->create_user();
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $catmanager->id,
            \context_coursecat::instance($cat->id)->id
        );
        $this->setUser($catmanager);
        $options = form\compare_form::course_options();
        $this->assertSame([(int) $new->id, (int) $old->id], array_keys($options), 'most recent start first, own courses only');
        $this->assertSame("AT_2027 — Acessibilidade e Tecnologia (start 2027, id {$new->id})", $options[$new->id]);
        $this->assertSame("AT_2026 — Acessibilidade e Tecnologia (start 2026, id {$old->id})", $options[$old->id]);
        $this->setAdminUser();
        $this->assertArrayNotHasKey(SITEID, form\compare_form::course_options());
    }
}
