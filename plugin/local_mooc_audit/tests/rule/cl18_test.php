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
 * CL-18: glossary forbidden when collaborative; empty when used as a game source.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl18
 */
final class cl18_test extends rule_testcase {

    /**
     * No glossary → C; glossary without game → NC; empty glossary feeding a game → NC Bloqueante; with entries →
     * ATENCAO (game scenarios skipped when mod_game is absent).
     */
    public function test_glossary(): void {
        global $DB;
        $course = $this->course();
        $r = $this->rule('CL-18', $course);
        $this->assertAll(status::CONFORME, $r);
        $this->assertStringContainsString('nenhum módulo glossary', $r[0]['evidencia']);

        $gl = $this->getDataGenerator()->create_module('glossary', ['course' => $course->id, 'name' => 'Glossário']);
        $r = $this->rule('CL-18', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame($gl->cmid, $r[0]['cmid']);
        $this->assertStringContainsString('sem uso por jogos', $r[0]['evidencia']);
        $this->assertNcComplete($r);

        if (!\local_mooc_audit_generator::has_module('game')) {
            $this->markTestIncomplete('mod_game not installed: game-source scenarios not covered');
        }
        $this->gen()->add_module($course, 'game', 1, ['name' => 'Palavras cruzadas', 'gamekind' => 'cross', 'sourcemodule' => 'glossary',
            'glossaryid' => $gl->id, 'glossarycategoryid' => 0, 'grade' => 10, 'grademethod' => 1, 'param1' => 0, 'param2' => 0, 'shuffle' => 1,
            'timeopen' => 0, 'timeclose' => 0]);
        $r = $this->rule('CL-18', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame(status::BLOQUEANTE, $r[0]['gravidade']);
        $this->assertStringContainsString('glossário com 0 entradas', $r[0]['evidencia']);
        $this->assertSame('module_view', $r[0]['locus']);

        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($gl, ['concept' => 'Termo', 'definition' => 'Def']);
        $r = $this->rule('CL-18', $course);
        $this->assertAll(status::ATENCAO, $r);
        $this->assertSame(status::MEDIA, $r[0]['gravidade']);
        $this->assertStringContainsString('1 entradas', $r[0]['evidencia']);
    }
}
