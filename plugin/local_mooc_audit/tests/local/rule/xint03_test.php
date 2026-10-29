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

use local_mooc_audit\engine;
use local_mooc_audit\local\status;

/**
 * X-INT-03: plugins used by the course must be installed on the site.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xint03
 */
final class xint03_test extends \advanced_testcase {
    /**
     * Results of the rule for a course.
     *
     * @param int $courseid
     * @return array
     */
    private function results(int $courseid): array {
        return array_values(array_filter(engine::audit($courseid)['results'], fn($r) => $r['rule'] === 'X-INT-03'));
    }

    /**
     * Empty course: nothing to check.
     */
    public function test_empty_course_is_not_applicable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $r = $this->results($course->id);
        $this->assertCount(1, $r);
        $this->assertSame(status::NAO_APLICAVEL, $r[0]['status']);
        $this->assertNotEmpty($r[0]['evidencia']);
    }

    /**
     * Standard module installed: CONFORME, informative; a condition of a non-existent availability plugin:
     * NAO_CONFORME, high severity, with the explanation that such conditions are silently ignored.
     */
    public function test_installed_and_missing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $course->id]);
        $r = $this->results($course->id);
        $this->assertCount(1, $r);
        $this->assertSame(status::CONFORME, $r[0]['status']);
        $this->assertSame(status::INFORMATIVA, $r[0]['gravidade']);
        $this->assertStringContainsString('page', $r[0]['local']);

        $DB->set_field(
            'course_modules',
            'availability',
            json_encode(['op' => '&', 'c' => [['type' => 'naoexiste', 'x' => 1]], 'showc' => [true]]),
            ['id' => $page->cmid]
        );
        rebuild_course_cache($course->id, true);
        $r = $this->results($course->id);
        $missing = array_values(array_filter($r, fn($x) => $x['status'] === status::NAO_CONFORME));
        $this->assertCount(1, $missing);
        $this->assertSame(status::ALTA, $missing[0]['gravidade']);
        $this->assertStringContainsString('naoexiste', $missing[0]['local']);
        $this->assertStringContainsString('availability_naoexiste', $missing[0]['evidencia']);
        $this->assertNotEmpty($missing[0]['orientacao']);
        // The module itself is still reported as installed.
        $this->assertContains(status::CONFORME, array_column($r, 'status'));
    }
}
