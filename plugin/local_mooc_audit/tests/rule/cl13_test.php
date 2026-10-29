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
 * CL-13: quizzes allow at least two attempts and the statement agrees.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl13
 */
final class cl13_test extends rule_testcase {

    /**
     * No quiz → NA; 2 and 0 attempts → C; 1 → NC; statement "2" with 1 → extra NC; statement silent → ATENCAO Baixa.
     */
    public function test_attempts(): void {
        $course = $this->course();
        $r = $this->rule('CL-13', $course);
        $this->assertAll(status::NAO_APLICAVEL, $r);

        $this->quiz($course, 'Q2', 2, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $this->quiz($course, 'Q0', 0, 50, 0, '<p>Tentativas: ilimitadas (0).</p>');
        $r = $this->rule('CL-13', $course);
        $this->assertCount(3, $r, json_encode($this->statuses($r)));
        $this->assertSame([status::CONFORME, status::CONFORME, status::ATENCAO], $this->statuses($r));
        $this->assertSame(status::BAIXA, $r[2]['gravidade'], 'the statement of Q0 does not state a number of attempts');

        $course2 = $this->course(['shortname' => 'cl13b']);
        $q1 = $this->quiz($course2, 'Q1', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $this->quiz($course2, 'Q3', 2, 50, 0, '<p>Olá.</p>');
        $r = $this->rule('CL-13', $course2);
        $this->assertSame([status::NAO_CONFORME, status::NAO_CONFORME, status::CONFORME, status::ATENCAO], $this->statuses($r));
        $this->assertSame($q1->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('tentativas permitidas=1', $r[0]['evidencia']);
        $this->assertStringContainsString('enunciado declara 2', $r[1]['evidencia']);
        $this->assertSame(status::BAIXA, $r[3]['gravidade']);
        $this->assertSame('module_edit', $r[0]['locus']);
        $this->assertNcComplete($r);

        // Policy: exact number of attempts.
        $r = $this->rule('CL-13', $course2, 0, ['attempts_rule' => 'exact', 'min_quiz_attempts' => 1]);
        $this->assertSame(status::CONFORME, $r[0]['status']);
    }
}
