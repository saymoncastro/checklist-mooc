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
 * CL-23: certificate conditioned on the course evaluation and the minimum total grade.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl23
 */
final class cl23_test extends rule_testcase {
    /**
     * No certificate → NC (import); evaluation + grade ≥ 60 on the course total → C ×2; without grade → NC; grade on
     * another item → NC; root operator | → NC.
     */
    public function test_certificate_conditions(): void {
        $course = $this->course();
        $r = $this->rule('CL-23', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('import', $r[0]['locus']);

        $q = $this->quiz($course, 'Q1', 2, 100);
        $ev = $this->feedback($course, 'Avaliação do curso');
        $cert = $this->certificate($course);
        $total = \grade_item::fetch_course_item($course->id);
        $ok = ['op' => '&', 'c' => [['type' => 'completion', 'cm' => $ev->coursemodule, 'e' => 1], ['type' => 'grade',
            'id' => (int) $total->id, 'min' => 60]], 'showc' => [true, true]];
        $this->gen()->set_availability($cert->coursemodule, $ok);
        $r = $this->rule('CL-23', $course);
        $this->assertCount(2, $r);
        $this->assert_all_status(status::CONFORME, $r);

        $this->gen()->set_availability($cert->coursemodule, $this->completion_tree([$ev->coursemodule]));
        $r = $this->rule('CL-23', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString('nota ≥ 60', $r[1]['esperado']);

        $quizitem = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => $q->instance]);
        $wrong = $ok;
        $wrong['c'][1]['id'] = (int) $quizitem->id;
        $this->gen()->set_availability($cert->coursemodule, $wrong);
        $r = $this->rule('CL-23', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString('item ' . $quizitem->id, $r[1]['evidencia']);
        $this->assertStringContainsString('Total do curso', $r[1]['esperado']);

        $or = $ok;
        $or['op'] = '|';
        unset($or['showc']);
        $or['show'] = true;
        $this->gen()->set_availability($cert->coursemodule, $or);
        $r = $this->rule('CL-23', $course);
        $this->assertSame([status::CONFORME, status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString('operador raiz=|', $r[2]['evidencia']);
        $this->assert_nc_complete($r);

        // Without the evaluation feedback in the course: first result NC.
        $course2 = $this->course(['shortname' => 'cl23b']);
        $cert2 = $this->certificate($course2);
        $this->gen()->set_availability($cert2->coursemodule, ['op' => '&', 'c' => [['type' => 'grade',
            'id' => (int) \grade_item::fetch_course_item($course2->id)->id, 'min' => 60]], 'showc' => [true]]);
        $r = $this->rule('CL-23', $course2);
        $this->assertSame([status::NAO_CONFORME, status::CONFORME], $this->statuses($r));
        $this->assertStringContainsString('inexistente no curso', $r[0]['esperado']);
    }
}
