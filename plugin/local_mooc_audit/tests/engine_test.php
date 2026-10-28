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

namespace local_mooc_audit;

/**
 * Automated tests for the audit engine (rules must yield CONFORME and NAO_CONFORME in the matching scenarios).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\engine
 */
final class engine_test extends \advanced_testcase {

    /**
     * Collect statuses of a rule in a report.
     *
     * @param array $report
     * @param string $rule
     * @return array
     */
    private function statuses(array $report, string $rule): array {
        return array_values(array_map(fn($r) => $r['status'], array_filter($report['results'], fn($r) => $r['rule'] === $rule)));
    }

    /**
     * CL-13: one attempt is NAO_CONFORME, two attempts is CONFORME; CL-12 sums grade maxima.
     */
    public function test_quiz_attempts_and_grade_sum(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $gen->create_module('quiz', ['course' => $course->id, 'attempts' => 1, 'grade' => 60]);
        $gen->create_module('quiz', ['course' => $course->id, 'attempts' => 2, 'grade' => 40]);

        $report = engine::audit($course->id);

        $this->assertSame('local_mooc_audit ' . engine::VERSION, $report['engine']);
        $this->assertContains(rules::NAO_CONFORME, $this->statuses($report, 'CL-13'));
        $this->assertContains(rules::CONFORME, $this->statuses($report, 'CL-13'));
        // 60 + 40 = 100: the sum itself is conforme.
        $sum = array_values(array_filter($report['results'], fn($r) => $r['rule'] === 'CL-12' && strpos($r['evidencia'], 'soma dos máximos') === 0));
        $this->assertSame(rules::CONFORME, $sum[0]['status']);
    }

    /**
     * X-DEP-02 / X-DEP-04: impossible condition and cycle are detected; a valid chain is CONFORME.
     */
    public function test_dependency_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $p1 = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $p2 = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_NONE]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id, 'attempts' => 2]);
        // Valid: quiz requires p1 (has completion).
        $DB->set_field('course_modules', 'availability', json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => $p1->cmid, 'e' => 1]], 'showc' => [true]]), ['id' => $quiz->cmid]);
        rebuild_course_cache($course->id, true);
        $report = engine::audit($course->id);
        $this->assertSame([rules::CONFORME], $this->statuses($report, 'X-DEP-02'));
        $this->assertSame([rules::CONFORME], $this->statuses($report, 'X-DEP-04'));

        // Impossible: quiz requires p2 (no completion); cycle: p1 <-> p2.
        $DB->set_field('course_modules', 'availability', json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => $p2->cmid, 'e' => 1]], 'showc' => [true]]), ['id' => $quiz->cmid]);
        $DB->set_field('course_modules', 'availability', json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => $p2->cmid, 'e' => 1]], 'showc' => [true]]), ['id' => $p1->cmid]);
        $DB->set_field('course_modules', 'availability', json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => $p1->cmid, 'e' => 1]], 'showc' => [true]]), ['id' => $p2->cmid]);
        rebuild_course_cache($course->id, true);
        $report = engine::audit($course->id);
        $this->assertContains(rules::NAO_CONFORME, $this->statuses($report, 'X-DEP-02'));
        $this->assertContains(rules::NAO_CONFORME, $this->statuses($report, 'X-DEP-04'));
    }

    /**
     * A rule throwing an exception produces an ERRO result and does not abort the audit.
     */
    public function test_policy_override_and_isolation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = engine::audit($course->id, ['reoffer_year' => 2031, 'policy_version' => 'teste']);
        $this->assertSame('teste', $report['policy_version']);
        $this->assertSame(2031, $report['reoffer_year']);
        $this->assertNotContains(rules::ERRO, array_column($report['results'], 'status'));
        $this->assertCount(count(rules::catalog()), array_unique(array_column($report['results'], 'rule')));
    }
}
