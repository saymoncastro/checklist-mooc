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
 * CL-17: automatic completion with a criterion suited to the module type.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl17
 */
final class cl17_test extends rule_testcase {
    /**
     * Page with completion on view → only the informative synthesis, CONFORME; manual → NC and synthesis ATENCAO;
     * automatic without criterion → ATENCAO; criterion unexpected for the type → ATENCAO Baixa.
     */
    public function test_completion_criteria(): void {
        $course = $this->course();
        $this->page($course, 'P1', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $r = $this->rule('CL-17', $course);
        $this->assertCount(1, $r);
        $this->assertSame(status::CONFORME, $r[0]['status']);
        $this->assertSame(status::INFORMATIVA, $r[0]['gravidade']);
        $this->assertStringContainsString('automática=1', $r[0]['evidencia']);
        $this->assertSame('none', $r[0]['locus']);

        $manual = $this->page($course, 'P2', '<p>x</p>', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $r = $this->rule('CL-17', $course);
        $this->assertSame([status::ATENCAO, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame($manual->coursemodule, $r[1]['cmid']);
        $this->assertStringContainsString('manual=1', $r[0]['evidencia']);
        $this->assert_nc_complete($r);

        $course2 = $this->course(['shortname' => 'cl17b']);
        $this->page($course2, 'P3', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 0]);
        $r = $this->rule('CL-17', $course2);
        $this->assertSame([status::CONFORME, status::ATENCAO], $this->statuses($r));
        $this->assertStringContainsString('sem nenhum critério', $r[1]['evidencia']);

        // A quiz completing on view only: expected criterion is "grade".
        $this->quiz($course2, 'Q', 2, 50, 0, '', 1, ['completionview' => 1, 'completiongradeitemnumber' => null,
            'completionusegrade' => 0]);
        $r = $this->rule('CL-17', $course2);
        $this->assertSame(status::ATENCAO, end($r)['status']);
        $this->assertSame(status::BAIXA, end($r)['gravidade']);
        $this->assertStringContainsString("critério 'grade' para quiz", end($r)['esperado']);
    }
}
