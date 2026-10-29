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

require_once(__DIR__ . "/../helper/rule_testcase.php");

/**
 * X-CONT-01: empty resources.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xcont01
 */
final class xcont01_test extends rule_testcase {

    /**
     * Page with text → C; empty page → NC; quiz without questions → NC Alta (module page); feedback without items →
     * NC; resource without file → NC Alta; book without chapters → NC.
     */
    public function test_empty_resources(): void {
        $course = $this->course();
        $this->page($course, 'Cheia', '<p>Texto.</p>');
        $this->quiz($course, 'Q com questões', 2, 50, 0, '', 2);
        $this->assertAll(status::CONFORME, $this->rule('X-CONT-01', $course));

        $empty = $this->page($course, 'Vazia', '<p></p>');
        $quiz = $this->quiz($course, 'Q vazio', 2, 50, 0, '', 0);
        $fb = $this->feedback($course, 'Pesquisa vazia', COMPLETION_TRACKING_NONE, 0, false);
        $res = $this->gen()->add_module($course, 'resource', 1, ['name' => 'Sem arquivo', 'display' => RESOURCELIB_DISPLAY_AUTO, 'printintro' => 0, 'files' => 0]);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id, 'name' => 'Livro vazio']);
        $r = $this->rule('X-CONT-01', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $bycm = array_column($r, null, 'cmid');
        $this->assertStringContainsString('sem texto', $bycm[$empty->coursemodule]['evidencia']);
        $this->assertSame('module_edit', $bycm[$empty->coursemodule]['locus']);
        $this->assertSame('questionário sem questões', $bycm[$quiz->coursemodule]['evidencia']);
        $this->assertSame(status::ALTA, $bycm[$quiz->coursemodule]['gravidade']);
        $this->assertSame('module_view', $bycm[$quiz->coursemodule]['locus']);
        $this->assertSame('pesquisa sem itens', $bycm[$fb->coursemodule]['evidencia']);
        $this->assertStringContainsString('sem nenhum arquivo', $bycm[$res->coursemodule]['evidencia']);
        $this->assertSame(status::ALTA, $bycm[$res->coursemodule]['gravidade']);
        $this->assertSame('livro sem capítulos', $bycm[$book->cmid]['evidencia']);
        $this->assertCount(5, $r);
        $this->assertNcComplete($r);

        // A page with only an image is not empty.
        $course2 = $this->course(['shortname' => 'xcont01b']);
        $this->page($course2, 'Imagem', '<p><img src="https://example.com/a.png" alt="a"></p>');
        $this->assertAll(status::CONFORME, $this->rule('X-CONT-01', $course2));
    }
}
