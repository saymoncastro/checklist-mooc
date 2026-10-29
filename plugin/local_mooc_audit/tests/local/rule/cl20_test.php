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
 * CL-20: hidden sections invisible; no empty or generically named sections.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl20
 */
final class cl20_test extends rule_testcase {
    /**
     * hiddensections=1 with one named, populated section → C only; 0 → NC; empty section → ATENCAO; "Topic 3" →
     * ATENCAO; hidden section with a completion activity → ATENCAO Média; section with labels only → ATENCAO Baixa.
     */
    public function test_sections(): void {
        global $DB;
        $course = $this->course(['numsections' => 1]);
        $sec1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        course_update_section($course, $sec1, ['name' => 'Conteúdos']);
        $this->page($course, 'P1');
        $r = $this->rule('CL-20', $course);
        $this->assertCount(1, $r);
        $this->assert_all_status(status::CONFORME, $r);
        $this->assertSame('course_settings', $r[0]['locus']);

        $course2 = $this->course(['shortname' => 'cl20b', 'numsections' => 3, 'hiddensections' => 0]);
        $this->page($course2, 'P1', '<p>x</p>', ['section' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1]);
        $s1 = $DB->get_record('course_sections', ['course' => $course2->id, 'section' => 1]);
        course_update_section($course2, $s1, ['name' => 'Oculta', 'visible' => 0]);
        $s3 = $DB->get_record('course_sections', ['course' => $course2->id, 'section' => 3]);
        course_update_section($course2, $s3, ['name' => 'Topic 3']);
        $this->gen()->add_module($course2, 'label', 3, ['introeditor' => ['text' => '<p>Só rótulo</p>', 'format' => FORMAT_HTML,
            'itemid' => 0]]);
        $r = $this->rule('CL-20', $course2);
        $this->assertSame(status::NAO_CONFORME, $r[0]['status']);
        $this->assertStringContainsString('hiddensections', $r[0]['evidencia']);
        $this->assert_nc_complete($r);
        $byev = [];
        foreach (array_slice($r, 1) as $x) {
            $byev[$x['evidencia']] = $x;
        }
        $this->assertArrayHasKey('seção oculta com atividades que têm conclusão', $byev);
        $this->assertSame(status::MEDIA, $byev['seção oculta com atividades que têm conclusão']['gravidade']);
        $this->assertSame(1, $byev['seção oculta com atividades que têm conclusão']['section']);
        $this->assertArrayHasKey('seção sem módulos', $byev);
        $this->assertSame(2, $byev['seção sem módulos']['section']);
        $this->assertArrayHasKey('nome genérico "Topic 3"', $byev);
        $this->assertArrayHasKey('seção só com rótulos', $byev);
        $this->assertSame(status::BAIXA, $byev['seção só com rótulos']['gravidade']);
        foreach ($byev as $x) {
            $this->assertSame(status::ATENCAO, $x['status']);
            $this->assertSame('section', $x['locus']);
            $this->assertStringContainsString('/course/section.php?id=', $x['link']);
        }
    }
}
