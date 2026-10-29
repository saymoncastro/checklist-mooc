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
 * X-DEP-03: grade conditions pointing to non-existent grade items.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xdep03
 */
final class xdep03_test extends rule_testcase {
    /**
     * Grade condition on an existing item → C; on an unknown id → NC.
     */
    public function test_grade_conditions(): void {
        $course = $this->course();
        $quiz = $this->quiz($course, 'Q');
        $page = $this->page($course, 'P');
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => $quiz->instance]);
        $this->gen()->set_availability($page->coursemodule, ['op' => '&', 'c' => [['type' => 'grade', 'id' => (int) $item->id,
            'min' => 50]], 'showc' => [true]]);
        $this->assert_all_status(status::CONFORME, $this->rule('X-DEP-03', $course));

        $this->gen()->set_availability($page->coursemodule, ['op' => '&', 'c' => [['type' => 'grade', 'id' => 999999,
            'min' => 50]], 'showc' => [true]]);
        $r = $this->rule('X-DEP-03', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame($page->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('id=999999', $r[0]['evidencia']);
        $this->assert_nc_complete($r);
    }
}
