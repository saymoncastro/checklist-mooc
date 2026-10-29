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
 * CL-04: the course summary carries the institutional buttons (links).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl04
 */
final class cl04_test extends rule_testcase {
    /**
     * Both links present → CONFORME ×2; one missing → one NAO_CONFORME.
     */
    public function test_summary_links(): void {
        $links = ['https://mooc.cefor.ifes.edu.br/moodle/login/signup.php',
            'https://mooc.cefor.ifes.edu.br/moodle/course/index.php?categoryid=7'];
        $course = $this->course(['summary' => '<p><a href="' . $links[0] . '">Criar conta</a></p><p><a href="' . $links[1]
            . '">Turmas</a></p>', 'summaryformat' => FORMAT_HTML]);
        $r = $this->rule('CL-04', $course);
        $this->assertCount(2, $r);
        $this->assert_all_status(status::CONFORME, $r);

        $course2 = $this->getDataGenerator()->create_course(['summary' => '<p><a href="' . $links[0] . '">Criar conta</a></p>',
            'summaryformat' => FORMAT_HTML]);
        $r = $this->rule('CL-04', $course2);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString($links[1], $r[1]['evidencia']);
        $this->assertSame('course_settings', $r[1]['locus']);
        $this->assert_nc_complete($r);
    }
}
