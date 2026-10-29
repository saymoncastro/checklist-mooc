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
 * CL-05: the course sits in the open-enrolment category.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl05
 */
final class cl05_test extends rule_testcase {
    /**
     * Target category → CONFORME; another category → NAO_CONFORME with the expected name.
     */
    public function test_category(): void {
        $course = $this->course();
        $this->assert_all_status(status::CONFORME, $this->rule('CL-05', $course));

        $other = $this->getDataGenerator()->create_category(['name' => 'Arquivo']);
        $course2 = $this->getDataGenerator()->create_course(['category' => $other->id]);
        $r = $this->rule('CL-05', $course2);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('Inscrições Abertas', $r[0]['esperado']);
        $this->assertStringContainsString('Arquivo', $r[0]['evidencia']);
        $this->assert_nc_complete($r);
    }
}
