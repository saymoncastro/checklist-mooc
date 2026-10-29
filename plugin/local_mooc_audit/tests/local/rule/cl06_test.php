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
 * CL-06: start date = 01/01 of the reoffer year, at least 30 days before the end.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl06
 */
final class cl06_test extends rule_testcase {
    /**
     * 01/01/year → C; other year → NC; 0 → NC; start less than 30 days before the end → NC.
     */
    public function test_start_date(): void {
        $course = $this->course();
        $this->assert_all_status(status::CONFORME, $this->rule('CL-06', $course));

        $gen = $this->getDataGenerator();
        $r = $this->rule('CL-06', $gen->create_course(['startdate' => $this->ts('2026-01-01 00:00'),
            'enddate' => $this->ts('2027-12-31 23:59')]));
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('ano=2026', $r[0]['encontrado']);
        $this->assertSame('01/01/2027', $r[0]['esperado']);

        global $DB;
        $nostart = $gen->create_course(['startdate' => $this->ts('2027-01-01 00:00'), 'enddate' => $this->ts('2027-12-31 23:59')]);
        $DB->set_field('course', 'startdate', 0, ['id' => $nostart->id]);
        $r = $this->rule('CL-06', $nostart);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('não definida', $r[0]['evidencia']);

        $r = $this->rule('CL-06', $gen->create_course(['startdate' => $this->ts('2027-12-15 00:00'),
            'enddate' => $this->ts('2027-12-31 23:59')]));
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('dia/mês=15/12', $r[0]['encontrado']);
        $this->assertStringContainsString('menos de 30 dias', $r[0]['encontrado']);
        $this->assert_nc_complete($r);
    }
}
