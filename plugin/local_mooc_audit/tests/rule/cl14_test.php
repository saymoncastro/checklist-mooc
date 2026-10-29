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
 * CL-14: graded activities have a closing date.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl14
 */
final class cl14_test extends rule_testcase {

    /**
     * No graded activity → NA; quiz closing at the course end → C; without closing → NC; different date → ATENCAO
     * Baixa; assignment with cut-off → C.
     */
    public function test_closing_dates(): void {
        $course = $this->course();
        $this->assertAll(status::NAO_APLICAVEL, $this->rule('CL-14', $course));

        $this->quiz($course, 'Q fim', 2, 50, $course->enddate);
        $this->assertAll(status::CONFORME, $this->rule('CL-14', $course));

        $q = $this->quiz($course, 'Q sem', 2, 50, 0);
        $r = $this->rule('CL-14', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame($q->coursemodule, $r[1]['cmid']);
        $this->assertNcComplete($r);

        $this->quiz($course, 'Q outro', 2, 50, $course->enddate - 10 * DAYSECS);
        $r = $this->rule('CL-14', $course);
        $this->assertSame(status::ATENCAO, $r[2]['status']);
        $this->assertSame(status::BAIXA, $r[2]['gravidade']);
        $this->assertStringContainsString('≠ fim do curso', $r[2]['evidencia']);

        $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa', 'grade' => 100, 'cutoffdate' => $course->enddate, 'duedate' => $course->enddate]);
        $r = $this->rule('CL-14', $course);
        $assign = array_values(array_filter($r, fn($x) => strpos($x['local'], 'assign') === 0));
        $this->assertCount(1, $assign);
        $this->assertSame(status::CONFORME, $assign[0]['status'], $assign[0]['evidencia']);
    }
}
