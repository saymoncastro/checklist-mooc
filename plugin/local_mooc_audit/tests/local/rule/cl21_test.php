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
 * CL-21: standard blocks on the course page.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl21
 */
final class cl21_test extends rule_testcase {
    /**
     * Add the three institutional HTML blocks.
     *
     * @param \stdClass $course
     * @param bool $accessibility
     */
    private function html_blocks(\stdClass $course, bool $accessibility): void {
        $this->gen()->add_block($course, 'html', ['title' => 'Atenção ao prazo!!!', 'text' => '<p>x</p>', 'format' => FORMAT_HTML]);
        $this->gen()->add_block($course, 'html', ['title' => 'Dúvidas técnicas? Podemos ajudar!', 'text' => '<p>x</p>',
            'format' => FORMAT_HTML]);
        if ($accessibility) {
            $this->gen()->add_block($course, 'html', ['title' => 'Acessibilidade', 'text' => '<p>x</p>', 'format' => FORMAT_HTML]);
        }
    }

    /**
     * Progress block + three HTML blocks → C ×4; missing progress → NC; missing "Acessibilidade" → NC.
     */
    public function test_blocks(): void {
        global $DB;
        $hasprogress = $DB->record_exists('block', ['name' => 'completion_progress']);
        $course = $this->course();
        if ($hasprogress) {
            $this->gen()->add_block($course, 'completion_progress');
        }
        $this->html_blocks($course, true);
        $r = $this->rule('CL-21', $course);
        $this->assertCount(4, $r);
        $this->assertSame('blocks', $r[0]['locus']);
        $this->assert_all_status(status::CONFORME, array_slice($r, 1));
        $this->assertSame($hasprogress ? status::CONFORME : status::NAO_CONFORME, $r[0]['status']);

        $course2 = $this->course(['shortname' => 'cl21b']);
        $this->html_blocks($course2, false);
        $r = $this->rule('CL-21', $course2);
        $this->assertSame(status::NAO_CONFORME, $r[0]['status'], 'progress block missing');
        $this->assertStringContainsString('Progresso', $r[0]['esperado']);
        $this->assertSame([status::CONFORME, status::CONFORME, status::NAO_CONFORME], $this->statuses(array_slice($r, 1)));
        $this->assertStringContainsString('Acessibilidade', $r[3]['evidencia']);
        $this->assert_nc_complete($r);
    }
}
