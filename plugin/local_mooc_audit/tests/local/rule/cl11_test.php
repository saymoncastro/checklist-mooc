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
 * CL-11: profile survey present, completing on submit, required by the certificate.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl11
 */
final class cl11_test extends rule_testcase {
    /**
     * Absent → NC (import); present without completionsubmit → NC; certificate not requiring it → NC; required → C.
     */
    public function test_profile_survey(): void {
        $course = $this->course();
        $r = $this->rule('CL-11', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame('import', $r[0]['locus']);

        $fb = $this->feedback($course, 'Pesquisa de Perfil', COMPLETION_TRACKING_AUTOMATIC, 0);
        $r = $this->rule('CL-11', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString('completionsubmit=0', $r[1]['evidencia']);
        $this->assertStringContainsString('nenhum certificado', $r[2]['evidencia']);
        $this->assert_nc_complete($r);

        $course = $this->course(['shortname' => 'cl11b']);
        $fb = $this->feedback($course, 'Pesquisa de Perfil');
        $cert = $this->certificate($course);
        $r = $this->rule('CL-11', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame($cert->coursemodule, $r[1]['cmid']);
        $this->assertStringContainsString('Pesquisa de Perfil', $r[1]['esperado']);

        $this->gen()->set_availability($cert->coursemodule, $this->completion_tree([$fb->coursemodule]));
        $r = $this->rule('CL-11', $course);
        $this->assert_all_status(status::CONFORME, $r);
        $this->assertCount(2, $r);
    }
}
