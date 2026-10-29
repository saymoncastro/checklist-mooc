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
 * X-STR-01: hidden modules, modules unavailable to the viewer, empty names.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xstr01
 */
final class xstr01_test extends rule_testcase {
    /**
     * Everything visible and named → C; hidden module → ATENCAO; empty name → NC; unavailable to a student viewer →
     * ATENCAO Informativa.
     */
    public function test_structure(): void {
        global $DB;
        $course = $this->course();
        $p1 = $this->page($course, 'P1');
        $this->assert_all_status(status::CONFORME, $this->rule('X-STR-01', $course));

        $hidden = $this->page($course, 'Oculta', '<p>x</p>', ['visible' => 0]);
        $r = $this->rule('X-STR-01', $course);
        $this->assert_all_status(status::ATENCAO, $r);
        $this->assertSame($hidden->coursemodule, $r[0]['cmid']);
        $this->assertSame('módulo oculto', $r[0]['evidencia']);

        // Moodle drops a module whose name is the empty string from modinfo altogether, so the realistic case is a
        // name made of whitespace or markup only.
        $noname = $this->page($course, 'Temp');
        $DB->set_field('page', 'name', ' ', ['id' => $noname->instance]);
        $r = $this->rule('X-STR-01', $course);
        $this->assertSame([status::ATENCAO, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame($noname->coursemodule, $r[1]['cmid']);
        $this->assertSame('nome vazio', $r[1]['evidencia']);
        $this->assert_nc_complete($r);

        // Student viewer: a module behind a future date restriction is reported as unavailable (informative).
        $this->gen()->set_availability($p1->coursemodule, ['op' => '&', 'c' => [['type' => 'date', 'd' => '>=',
            't' => time() + 30 * DAYSECS]], 'showc' => [true]]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $r = $this->rule('X-STR-01', $course, $student->id);
        $unavail = array_values(array_filter($r, fn($x) => $x['cmid'] === $p1->coursemodule));
        $this->assertCount(1, $unavail);
        $this->assertSame(status::ATENCAO, $unavail[0]['status']);
        $this->assertSame(status::INFORMATIVA, $unavail[0]['gravidade']);
        $this->assertStringContainsString('indisponível para o usuário ' . $student->id, $unavail[0]['evidencia']);
    }
}
