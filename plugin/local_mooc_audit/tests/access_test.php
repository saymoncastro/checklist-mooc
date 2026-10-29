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
 * Access control: who may audit a course, and the course navigation entry.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_mooc_audit_extend_navigation_course
 */
final class access_test extends \advanced_testcase {
    /**
     * Enrolled student and teacher do not hold local/mooc_audit:audit by default; a manager does.
     */
    public function test_capability_by_archetype(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $context = \context_course::instance($course->id);
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $manager = $gen->create_and_enrol($course, 'manager');
        $guest = $gen->create_user();

        $this->assertFalse(has_capability('local/mooc_audit:audit', $context, $student));
        $this->assertFalse(has_capability('local/mooc_audit:audit', $context, $teacher));
        $this->assertFalse(has_capability('local/mooc_audit:audit', $context, $guest));
        $this->assertTrue(has_capability('local/mooc_audit:audit', $context, $manager));
        $this->assertTrue(has_capability('local/mooc_audit:audit', $context, get_admin()));
    }

    /**
     * The capability declares no risk and is read-only (research R11).
     */
    public function test_capability_definition(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (['local/mooc_audit:audit', 'local/mooc_audit:compare'] as $name) {
            $cap = $DB->get_record('capabilities', ['name' => $name], '*', MUST_EXIST);
            $this->assertSame('read', $cap->captype, $name);
            $this->assertSame(0, (int) $cap->riskbitmask, $name);
        }
    }

    /**
     * The course navigation entry is added only for users holding the capability.
     */
    public function test_navigation_node(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mooc_audit/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $manager = $gen->create_and_enrol($course, 'manager');

        $this->setUser($teacher);
        $node = \navigation_node::create('courseadmin', null, \navigation_node::TYPE_COURSE);
        local_mooc_audit_extend_navigation_course($node, $course, $context);
        $this->assertFalse($node->find('local_mooc_audit', \navigation_node::TYPE_SETTING));

        $this->setUser($manager);
        $node = \navigation_node::create('courseadmin', null, \navigation_node::TYPE_COURSE);
        local_mooc_audit_extend_navigation_course($node, $course, $context);
        $found = $node->find('local_mooc_audit', \navigation_node::TYPE_SETTING);
        $this->assertNotFalse($found);
        $this->assertSame(get_string('auditmenu', 'local_mooc_audit'), $found->text);
        $this->assertStringContainsString('/local/mooc_audit/index.php?courseid=' . $course->id, $found->action->out(false));
    }
}
