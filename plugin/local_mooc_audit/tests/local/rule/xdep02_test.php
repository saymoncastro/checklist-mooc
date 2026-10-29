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
 * X-DEP-02: completion conditions on modules without completion, hidden, or without grade for pass/fail.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xdep02
 */
final class xdep02_test extends rule_testcase {
    /**
     * Valid chain → C; target without completion → NC; e=2 on a target without grade → NC; hidden target → ATENCAO Alta.
     */
    public function test_impossible_conditions(): void {
        global $DB;
        $course = $this->course();
        $p1 = $this->page($course, 'P1', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p2 = $this->page($course, 'P2', '<p>x</p>', ['completion' => COMPLETION_TRACKING_NONE]);
        $quiz = $this->quiz($course, 'Q');
        $this->gen()->set_availability($quiz->coursemodule, $this->completion_tree([$p1->coursemodule]));
        $this->assert_all_status(status::CONFORME, $this->rule('X-DEP-02', $course));

        $this->gen()->set_availability($quiz->coursemodule, $this->completion_tree([$p2->coursemodule]));
        $r = $this->rule('X-DEP-02', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame($quiz->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('NÃO tem acompanhamento de conclusão', $r[0]['evidencia']);
        $this->assert_nc_complete($r);

        // Pass/fail (e=2) on a page, which has no grade.
        $this->gen()->set_availability($quiz->coursemodule, $this->completion_tree([$p1->coursemodule], '&', 2));
        $r = $this->rule('X-DEP-02', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('aprovação/reprovação de módulo sem nota', $r[0]['evidencia']);

        $this->gen()->set_availability($quiz->coursemodule, $this->completion_tree([$p1->coursemodule]));
        $DB->set_field('course_modules', 'visible', 0, ['id' => $p1->coursemodule]);
        $r = $this->rule('X-DEP-02', $course);
        $this->assert_all_status(status::ATENCAO, $r);
        $this->assertSame(status::ALTA, $r[0]['gravidade']);
        $this->assertStringContainsString('OCULTO', $r[0]['evidencia']);
    }
}
