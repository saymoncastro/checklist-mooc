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
 * X-DEP-04: cycles in the completion dependency graph.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xdep04
 */
final class xdep04_test extends rule_testcase {

    /**
     * Acyclic chain → C with counts; cycle P1 ↔ P2 → NC listing the cycle; no destination (locus none).
     */
    public function test_cycles(): void {
        $course = $this->course();
        $p1 = $this->page($course, 'P1', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p2 = $this->page($course, 'P2', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p3 = $this->page($course, 'P3', '<p>x</p>', ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $this->gen()->set_availability($p2->coursemodule, $this->completion_tree([$p1->coursemodule]));
        $this->gen()->set_availability($p3->coursemodule, $this->completion_tree([$p1->coursemodule, $p2->coursemodule]));
        $r = $this->rule('X-DEP-04', $course);
        $this->assertAll(status::CONFORME, $r);
        $this->assertStringContainsString('2 módulos com condições, 3 arestas', $r[0]['evidencia']);

        $this->gen()->set_availability($p1->coursemodule, $this->completion_tree([$p2->coursemodule]));
        $r = $this->rule('X-DEP-04', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertCount(1, $r);
        $this->assertStringContainsString("{$p1->coursemodule} → {$p2->coursemodule} → {$p1->coursemodule}", $r[0]['evidencia']);
        $this->assertSame('none', $r[0]['locus']);
        $this->assertNull($r[0]['link']);
        $this->assertNcComplete($r);
    }
}
