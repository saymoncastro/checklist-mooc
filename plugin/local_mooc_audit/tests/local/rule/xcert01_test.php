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
 * X-CERT-01: static reachability of the certificate through its completion chain.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xcert01
 */
final class xcert01_test extends rule_testcase {
    /**
     * No certificate → NC; satisfiable chain → C; link without completion → NC; hidden link → NC; hidden certificate
     * → extra NC; a student without access to a link → NC for that viewer only.
     */
    public function test_reachability(): void {
        $course = $this->course();
        $r = $this->rule('X-CERT-01', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);

        $p1 = $this->page($course, 'P1', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p2 = $this->page($course, 'P2', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $this->gen()->set_availability($p2->coursemodule, $this->completion_tree([$p1->coursemodule]));
        $cert = $this->certificate($course);
        $this->gen()->set_availability($cert->coursemodule, $this->completion_tree([$p2->coursemodule]));
        $r = $this->rule('X-CERT-01', $course);
        $this->assert_all_status(status::CONFORME, $r);
        $this->assertStringContainsString('2 módulos', $r[0]['evidencia']);
        $this->assertSame($cert->coursemodule, $r[0]['cmid']);

        // The transitive link P1 loses completion.
        $cm = get_coursemodule_from_id('page', $p1->coursemodule, $course->id, false, MUST_EXIST);
        $cm->completion = COMPLETION_TRACKING_NONE;
        $cm->completionview = 0;
        $DB = $this->db();
        $DB->update_record('course_modules', $cm);
        $r = $this->rule('X-CERT-01', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('sem conclusão', $r[0]['encontrado']);
        $this->assert_nc_complete($r);

        // Restored, but hidden.
        $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
        $cm->completionview = 1;
        $cm->visible = 0;
        $DB->update_record('course_modules', $cm);
        $r = $this->rule('X-CERT-01', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('oculto', $r[0]['encontrado']);

        // Visible again; the certificate itself hidden → extra NC.
        $cm->visible = 1;
        $DB->update_record('course_modules', $cm);
        $DB->set_field('course_modules', 'visible', 0, ['id' => $cert->coursemodule]);
        $r = $this->rule('X-CERT-01', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame('certificado oculto', $r[1]['evidencia']);
        $DB->set_field('course_modules', 'visible', 1, ['id' => $cert->coursemodule]);

        // A date restriction in the future on P1: the admin view is fine, a student cannot reach it.
        $this->gen()->set_availability($p1->coursemodule, ['op' => '&', 'c' => [['type' => 'date', 'd' => '>=',
            't' => time() + 30 * DAYSECS]], 'showc' => [true]]);
        $this->assert_all_status(status::CONFORME, $this->rule('X-CERT-01', $course));
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Without a test viewer the result does not depend on who runs the audit (page opened by any auditor).
        $this->setUser($student);
        $this->assert_all_status(status::CONFORME, $this->rule('X-CERT-01', $course));
        $this->setAdminUser();
        $r = $this->rule('X-CERT-01', $course, $student->id);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('indisponível para o usuário', $r[0]['encontrado']);
    }

    /**
     * Global DB.
     *
     * @return \moodle_database
     */
    private function db(): \moodle_database {
        global $DB;
        return $DB;
    }
}
