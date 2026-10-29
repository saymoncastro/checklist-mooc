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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/schema_validator.php');

/**
 * Contract comparison 1.0 (schema/comparison-v1.0.schema.json): valid output, the validator rejects violations
 * (including "problematic" inconsistent with "relevance"), one encoding, and the individual audit contract 1.0 is
 * untouched. The scenario outputs are validated in comparator_test.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\comparator::to_json
 */
final class comparison_contract_test extends \advanced_testcase {
    /**
     * Validator of the comparison schema.
     *
     * @return tests\schema_validator
     */
    private function validator(): tests\schema_validator {
        global $CFG;
        return new tests\schema_validator($CFG->dirroot . '/local/mooc_audit/schema/comparison-v1.0.schema.json');
    }

    /**
     * Two courses with one changed activity: valid; broken copies are rejected with their paths.
     */
    public function test_output_validates_and_violations_are_detected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $src = $gen->create_course();
        $dst = $gen->create_course();
        $gen->create_module('page', ['course' => $src->id, 'name' => 'Leitura', 'content' => '<p>a</p>']);
        $gen->create_module('page', ['course' => $dst->id, 'name' => 'Leitura', 'content' => '<p>b</p>']);
        $gen->create_module('page', ['course' => $src->id, 'name' => 'Só origem', 'content' => '<p>c</p>']);
        $report = json_decode(comparator::to_json(comparator::run($src->id, $dst->id)), true);
        $this->assertSame([], $this->validator()->errors($report));
        $this->assertSame(['comparison', '1.0'], [$report['contract'], $report['contract_version']]);
        $this->assertCount(1, $report['missing_in_target']);

        $report['contract_version'] = '1.1';
        $report['summary']['verdict'] = 'OK';
        $report['matches'][0]['problematic'] = true; // Relevance is "informativa": inconsistent.
        $report['matches'][0]['confidence'] = 'certa';
        unset($report['audit']['counts']);
        $errors = implode("\n", $this->validator()->errors($report));
        $this->assertStringContainsString('$.contract_version: expected const', $errors);
        $this->assertStringContainsString('$.summary.verdict', $errors);
        $this->assertStringContainsString('$.matches[0].problematic: expected const false', $errors);
        $this->assertStringContainsString('$.matches[0].confidence', $errors);
        $this->assertStringContainsString('$.audit: missing required "counts"', $errors);
    }

    /**
     * One encoding: unicode and slashes unescaped, decoding gives the report back.
     */
    public function test_json_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $src = $this->getDataGenerator()->create_course(['fullname' => 'Acessibilidade e Tecnologia — 2026']);
        $dst = $this->getDataGenerator()->create_course(['fullname' => 'Acessibilidade e Tecnologia — 2027']);
        $r = comparator::run($src->id, $dst->id);
        $json = comparator::to_json($r);
        $this->assertStringContainsString('Tecnologia — 2026', $json);
        $this->assertStringContainsString('"contract": "comparison"', $json);
        $this->assertSame(json_decode(json_encode($r), true), json_decode($json, true));
    }

    /**
     * The individual audit contract is still 1.0 and has no comparison field.
     */
    public function test_audit_contract_unchanged(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = engine::audit($course->id);
        $this->assertSame('1.0', $report['contract_version']);
        $this->assertSame('1.0', engine::CONTRACT_VERSION);
        $this->assertArrayNotHasKey('contract', $report);
        $this->assertArrayNotHasKey('matches', $report);
    }
}
