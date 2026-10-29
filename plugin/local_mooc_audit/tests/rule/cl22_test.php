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
 * CL-22: training licence components (label, survey, declaration).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl22
 */
final class cl22_test extends rule_testcase {

    /**
     * Nothing → NC ×3 (import); label in section 0 → first C; survey → second C; declaration → third C.
     */
    public function test_licence_components(): void {
        $course = $this->course();
        $r = $this->rule('CL-22', $course);
        $this->assertCount(3, $r);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame(['import', 'import', 'import'], array_column($r, 'locus'));
        $this->assertNcComplete($r);

        $this->gen()->add_module($course, 'label', 0, ['introeditor' => ['text' => '<p>Licença para capacitação: servidores...</p>', 'format' => FORMAT_HTML, 'itemid' => 0]]);
        $r = $this->rule('CL-22', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame('none', $r[0]['locus']);

        $this->feedback($course, 'Licença para capacitação', COMPLETION_TRACKING_NONE, 0);
        $r = $this->rule('CL-22', $course);
        $this->assertSame([status::CONFORME, status::CONFORME, status::NAO_CONFORME], $this->statuses($r));

        $this->certificate($course, 'Declaração licença para capacitação');
        $r = $this->rule('CL-22', $course);
        $this->assertAll(status::CONFORME, $r);

        // A label with the text outside section 0 does not count.
        $course2 = $this->course(['shortname' => 'cl22b']);
        $this->gen()->add_module($course2, 'label', 1, ['introeditor' => ['text' => '<p>Licença para capacitação</p>', 'format' => FORMAT_HTML, 'itemid' => 0]]);
        $this->assertSame(status::NAO_CONFORME, $this->rule('CL-22', $course2)[0]['status']);
    }
}
