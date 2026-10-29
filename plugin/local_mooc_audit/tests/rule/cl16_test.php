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

require_once(__DIR__ . "/../helper/rule_testcase.php");

/**
 * CL-16: the course evaluation requires completion of every graded activity.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl16
 */
final class cl16_test extends rule_testcase {

    /**
     * Absent → NC; requires every graded cmid → C; one missing → NC with the names; operator | → ATENCAO.
     */
    public function test_evaluation_restrictions(): void {
        $course = $this->course();
        $q1 = $this->quiz($course, 'Q1');
        $q2 = $this->quiz($course, 'Q2');
        $r = $this->rule('CL-16', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('Avaliação do curso', $r[0]['evidencia']);
        $this->assertNcComplete($r);

        $ev = $this->feedback($course, 'Avaliação do curso');
        $this->gen()->set_availability($ev->coursemodule, $this->completion_tree([$q1->coursemodule, $q2->coursemodule]));
        $r = $this->rule('CL-16', $course);
        $this->assertAll(status::CONFORME, $r);
        $this->assertStringContainsString('todas as 2', $r[0]['encontrado']);

        $this->gen()->set_availability($ev->coursemodule, $this->completion_tree([$q1->coursemodule]));
        $r = $this->rule('CL-16', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('faltam: quiz "Q2"', $r[0]['encontrado']);
        $this->assertSame($ev->coursemodule, $r[0]['cmid']);
        $this->assertNcComplete($r);

        $this->gen()->set_availability($ev->coursemodule, $this->completion_tree([$q1->coursemodule, $q2->coursemodule], '|'));
        $r = $this->rule('CL-16', $course);
        $this->assertSame([status::CONFORME, status::ATENCAO], $this->statuses($r));
        $this->assertStringContainsString('operador raiz=|', $r[1]['evidencia']);
    }
}
