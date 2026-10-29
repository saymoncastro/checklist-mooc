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
 * X-AV-01: the statement's points agree with the maximum grade.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xav01
 */
final class xav01_test extends rule_testcase {

    /**
     * "Valor: 50 pontos" with grademax 50 → C; with 40 → NC; no mention → C.
     */
    public function test_statement_points(): void {
        $course = $this->course();
        $this->quiz($course, 'Q1', 2, 50, 0, '<p>Valor: 50 pontos.</p>');
        $this->quiz($course, 'Q3', 2, 30, 0, '<p>Sem menção.</p>');
        $this->assertAll(status::CONFORME, $this->rule('X-AV-01', $course));

        $q = $this->quiz($course, 'Q2', 2, 40, 0, '<p>Valor: 50 pontos.</p>');
        $r = $this->rule('X-AV-01', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame($q->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('declara 50 pontos; nota máxima=40', $r[0]['evidencia']);
        $this->assertNcComplete($r);
    }
}
