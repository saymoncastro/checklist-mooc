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
 * Characterisation tests: the statuses produced per rule for the reference courses A and B must stay identical to
 * the recorded expectations (tests/fixtures/expected_A.json and expected_B.json).
 *
 * To (re)record the expectations after an intentional, documented change of behaviour run the suite with the
 * environment variable LOCAL_MOOC_AUDIT_WRITE_EXPECTED=1.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\engine
 */
final class characterization_test extends \advanced_testcase {

    /** @var int Reoffer year used by the fixtures and the policy. */
    const YEAR = 2027;

    /**
     * Reduce a report to rule => sorted list of "status|gravidade".
     *
     * @param array $report
     * @return array
     */
    public static function signature(array $report): array {
        $sig = [];
        foreach ($report['results'] as $r) {
            $sig[$r['rule']][] = $r['status'] . '|' . $r['gravidade'];
        }
        ksort($sig);
        foreach ($sig as &$list) {
            sort($list);
        }
        unset($list);
        return $sig;
    }

    /**
     * Audit a fixture course and compare with (or record) the expectation file.
     *
     * @param string $which 'A' or 'B'
     * @return array the report
     */
    protected function check(string $which): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$course] = $which === 'A' ? $generator->create_course_a(self::YEAR) : $generator->create_course_b(self::YEAR);
        $report = engine::audit($course->id, ['reoffer_year' => self::YEAR]);
        $actual = self::signature($report);
        $file = __DIR__ . "/fixtures/expected_$which.json";
        if (getenv('LOCAL_MOOC_AUDIT_WRITE_EXPECTED')) {
            file_put_contents($file, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->assertFileExists($file);
            return $report;
        }
        $this->assertFileExists($file, 'Expectation file missing: run once with LOCAL_MOOC_AUDIT_WRITE_EXPECTED=1');
        $expected = json_decode(file_get_contents($file), true);
        $this->assertSame($expected, $actual, "Statuses per rule changed for course $which");
        return $report;
    }

    /**
     * Course A: everything CONFORME.
     */
    public function test_course_a(): void {
        $report = $this->check('A');
        $this->assertSame(['CONFORME'], array_keys($report['summary']['por_status']));
    }

    /**
     * Course B: the recorded set of non conformities.
     */
    public function test_course_b(): void {
        $report = $this->check('B');
        $this->assertArrayHasKey('NAO_CONFORME', $report['summary']['por_status']);
        $this->assertArrayHasKey('Bloqueante', $report['summary']['nao_conformes_por_gravidade']);
    }
}
