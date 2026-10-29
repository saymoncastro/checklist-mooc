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
 * CL-07: end date = 31/12 of the reoffer year.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl07
 */
final class cl07_test extends rule_testcase {
    /**
     * 31/12/year → C; 30/06 → NC; 0 → NC; other allowed date through the policy → C.
     */
    public function test_end_date(): void {
        $course = $this->course();
        $this->assert_all_status(status::CONFORME, $this->rule('CL-07', $course));

        $gen = $this->getDataGenerator();
        $mid = $gen->create_course(['startdate' => $this->ts('2027-01-01 00:00'), 'enddate' => $this->ts('2027-06-30 23:59')]);
        $r = $this->rule('CL-07', $mid);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('31/12/2027', $r[0]['esperado']);

        $r = $this->rule('CL-07', $gen->create_course(['startdate' => $this->ts('2027-01-01 00:00'), 'enddate' => 0]));
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('não definida', $r[0]['evidencia']);
        $this->assert_nc_complete($r);

        $this->assert_all_status(status::CONFORME, $this->rule('CL-07', $mid, 0, ['allowed_end_dates' => ['30/06', '31/12']]));
    }
}
