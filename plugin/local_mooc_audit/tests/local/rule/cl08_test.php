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
 * CL-08: self enrolment enabled, open to new enrolments, with the course dates.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl08
 */
final class cl08_test extends rule_testcase {
    /**
     * The self enrolment instance of a course (created by default).
     *
     * @param \stdClass $course
     * @return \stdClass|null
     */
    private function self_instance(\stdClass $course): ?\stdClass {
        foreach (enrol_get_instances($course->id, false) as $inst) {
            if ($inst->enrol === 'self') {
                return $inst;
            }
        }
        return null;
    }

    /**
     * Update the self instance.
     *
     * @param \stdClass $course
     * @param array $data
     */
    private function update_self(\stdClass $course, array $data): void {
        $inst = $this->self_instance($course);
        enrol_get_plugin('self')->update_instance($inst, (object) array_merge(['status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => $course->startdate,
            'enrolenddate' => $course->enddate, 'customint6' => 1, 'roleid' => 5, 'name' => '', 'expirynotify' => 0,
                'expirythreshold' => $inst->expirythreshold,
            'notifyall' => 0], $data));
    }

    /**
     * Enabled with the course dates and new enrolments allowed → C; disabled without dates → NC with the reasons;
     * dates different from the course → NC; no instance → NC; guest access enabled → ATENCAO Informativa.
     */
    public function test_self_enrolment(): void {
        global $DB;
        $course = $this->course();
        $this->assertNotNull($this->self_instance($course), 'self enrolment is added by default in the test site');
        $this->update_self($course, []);
        $r = $this->rule('CL-08', $course);
        $this->assert_all_status(status::CONFORME, $r);
        $this->assertSame('enrol', $r[0]['locus']);

        $this->update_self($course, ['status' => ENROL_INSTANCE_DISABLED, 'enrolstartdate' => 0, 'enrolenddate' => 0,
            'customint6' => 0]);
        $r = $this->rule('CL-08', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('método desabilitado', $r[0]['encontrado']);
        $this->assertStringContainsString('novas inscrições', $r[0]['encontrado']);
        $this->assertStringContainsString('datas de inscrição não definidas', $r[0]['encontrado']);

        $this->update_self($course, ['enrolstartdate' => $course->startdate + DAYSECS,
            'enrolenddate' => $course->enddate - DAYSECS]);
        $r = $this->rule('CL-08', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('início da inscrição ≠ início do curso', $r[0]['encontrado']);
        $this->assertStringContainsString('fim da inscrição ≠ fim do curso', $r[0]['encontrado']);
        $this->assert_nc_complete($r);

        // Guest access enabled: informative warning in addition.
        $this->update_self($course, []);
        $guest = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $guest->id]);
        $r = $this->rule('CL-08', $course);
        $this->assertSame([status::CONFORME, status::ATENCAO], $this->statuses($r));
        $this->assertSame(status::INFORMATIVA, $r[1]['gravidade']);
        $this->assertStringContainsString('visitante', $r[1]['local']);

        // No self instance at all.
        enrol_get_plugin('self')->delete_instance($this->self_instance($course));
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $guest->id]);
        $r = $this->rule('CL-08', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertStringContainsString('nenhuma instância', $r[0]['evidencia']);
        $this->assert_nc_complete($r);
    }
}
