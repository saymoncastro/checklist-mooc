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
 * CL-12: grade book sums 100, natural aggregation, empty grades not excluded.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl12
 */
final class cl12_test extends rule_testcase {

    /**
     * Set the root category of the course.
     *
     * @param \stdClass $course
     * @param int $aggregation
     * @param int $onlygraded
     */
    private function root(\stdClass $course, int $aggregation, int $onlygraded): void {
        $root = \grade_category::fetch_course_category($course->id);
        $root->aggregation = $aggregation;
        $root->aggregateonlygraded = $onlygraded;
        $root->update();
    }

    /**
     * Sum 100 + natural + onlygraded=0 → C ×3; sum 90 → NC; onlygraded=1 → NC; aggregation ≠ 13 → NC; item in a
     * subcategory → ATENCAO.
     */
    public function test_gradebook(): void {
        $course = $this->course();
        $this->quiz($course, 'Q1', 2, 60);
        $q2 = $this->quiz($course, 'Q2', 2, 40);
        $this->root($course, 13, 0);
        $r = $this->rule('CL-12', $course);
        $this->assertCount(3, $r);
        $this->assertAll(status::CONFORME, $r);
        $this->assertSame('gradebook', $r[0]['locus']);
        $this->assertStringContainsString('soma dos máximos=100', $r[0]['evidencia']);

        $this->root($course, 13, 1);
        $r = $this->rule('CL-12', $course);
        $this->assertSame([status::CONFORME, status::CONFORME, status::NAO_CONFORME], $this->statuses($r));
        $this->assertStringContainsString('aggregateonlygraded=1', $r[2]['evidencia']);

        $this->root($course, 10, 0);
        $r = $this->rule('CL-12', $course);
        $this->assertSame([status::CONFORME, status::NAO_CONFORME, status::CONFORME], $this->statuses($r));
        $this->assertStringContainsString('aggregation=10', $r[1]['evidencia']);
        $this->assertNcComplete($r);

        $this->root($course, 13, 0);
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => $q2->instance]);
        $item->grademax = 30;
        $item->update();
        $r = $this->rule('CL-12', $course);
        $this->assertSame(status::NAO_CONFORME, $r[0]['status']);
        $this->assertStringContainsString('soma dos máximos=90', $r[0]['evidencia']);
        $this->assertSame('100', $r[0]['esperado']);

        // Item moved to a subcategory: ATENCAO.
        $item->grademax = 40;
        $item->update();
        $sub = new \grade_category(['courseid' => $course->id, 'fullname' => 'Sub'], false);
        $sub->insert();
        $item->set_parent($sub->id);
        $r = $this->rule('CL-12', $course);
        $this->assertContains(status::ATENCAO, $this->statuses($r));
        $this->assertStringContainsString('subcategorias', end($r)['evidencia']);
    }
}
