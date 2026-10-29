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

namespace local_mooc_audit\local\rule;

use local_mooc_audit\local\status;
use local_mooc_audit\rule_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/rule_testcase.php');

/**
 * X-DEP-01: access conditions pointing to modules that do not exist.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xdep01
 */
final class xdep01_test extends rule_testcase {
    /**
     * Valid references → C; unknown cm in a module → NC; unknown cm in a section → NC with section locus.
     */
    public function test_orphan_conditions(): void {
        global $DB;
        $course = $this->course();
        $p1 = $this->page($course, 'P1', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p2 = $this->page($course, 'P2');
        $this->gen()->set_availability($p2->coursemodule, $this->completion_tree([$p1->coursemodule]));
        $this->assert_all_status(status::CONFORME, $this->rule('X-DEP-01', $course));

        $this->gen()->set_availability($p2->coursemodule, $this->completion_tree([999999]));
        $r = $this->rule('X-DEP-01', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame($p2->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('cm=999999', $r[0]['evidencia']);
        $this->assert_nc_complete($r);

        $this->gen()->set_availability($p2->coursemodule, null);
        $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('course_sections', 'availability', json_encode($this->completion_tree([999998])), ['id' => $sec->id]);
        $r = $this->rule('X-DEP-01', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('section', $r[0]['locus']);
        $this->assertSame(1, $r[0]['section']);
        $this->assertStringContainsString('/course/section.php?id=' . $sec->id, $r[0]['link']);
    }
}
