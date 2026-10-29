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
 * CL-09: institutional forum present, without activity completion.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl09
 */
final class cl09_test extends rule_testcase {

    /**
     * Canonical forum without completion → C; with completion → NC; absent → NC (import); two homonyms → first only.
     */
    public function test_forum(): void {
        $course = $this->course();
        $r = $this->rule('CL-09', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame('import', $r[0]['locus']);
        $this->assertNcComplete($r);

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'name' => 'Espaço de interação', 'completion' => COMPLETION_TRACKING_NONE]);
        $r = $this->rule('CL-09', $course);
        $this->assertAll(status::CONFORME, $r);
        $this->assertSame($forum->cmid, $r[0]['cmid']);

        $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'name' => 'Espaço de Interação (cópia)', 'completion' => COMPLETION_TRACKING_MANUAL]);
        $r = $this->rule('CL-09', $course);
        $this->assertCount(1, $r, 'only the first matching forum is evaluated');
        $this->assertSame($forum->cmid, $r[0]['cmid']);

        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('forum', ['course' => $course2->id, 'name' => 'Espaço de interação', 'completion' => COMPLETION_TRACKING_MANUAL]);
        $r = $this->rule('CL-09', $course2);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertSame('conclusão desabilitada', $r[1]['esperado']);
        $this->assertNcComplete($r);
    }
}
