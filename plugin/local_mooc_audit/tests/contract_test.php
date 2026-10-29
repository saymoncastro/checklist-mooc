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
 * JSON report contract 1.0: the reports of courses A and B validate against schema/report-v1.0.schema.json, keep
 * every field of the PoC, and page and CLI share one encoding.
 *
 * The validator below covers only the JSON Schema subset the contract uses (type, const, enum, pattern, required,
 * properties, additionalProperties, items, $ref to $defs, allOf/if/then, minLength); no external library.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\engine
 */
final class contract_test extends \advanced_testcase {
    /** @var array Decoded schema. */
    private array $schema;

    /**
     * Load the schema shipped with the plugin.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->schema = json_decode(file_get_contents($CFG->dirroot . '/local/mooc_audit/schema/report-v1.0.schema.json'), true);
        $this->assertIsArray($this->schema);
    }

    /**
     * Validate a value against a (sub)schema, collecting errors with their JSON path.
     *
     * @param mixed $value decoded JSON (arrays for objects and lists)
     * @param array $schema
     * @param string $path
     * @param array $errors by reference
     */
    private function validate($value, array $schema, string $path, array &$errors): void {
        if (isset($schema['$ref'])) {
            $ref = explode('/', ltrim($schema['$ref'], '#/'));
            $target = $this->schema;
            foreach ($ref as $k) {
                $target = $target[$k];
            }
            $this->validate($value, $target, $path, $errors);
            return;
        }
        if (isset($schema['const']) && $value !== $schema['const']) {
            $errors[] = "$path: expected const " . json_encode($schema['const']) . ', got ' . json_encode($value);
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "$path: " . json_encode($value) . ' not in enum';
        }
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $ok = false;
            foreach ($types as $t) {
                $ok = $ok || match ($t) {
                    'string' => is_string($value),
                    'integer' => is_int($value),
                    'number' => is_int($value) || is_float($value),
                    'boolean' => is_bool($value),
                    'null' => $value === null,
                    'array' => is_array($value) && array_is_list($value),
                    'object' => is_array($value) && (!array_is_list($value) || $value === []),
                    default => false,
                };
            }
            if (!$ok) {
                $errors[] = "$path: type " . json_encode($value) . ' is not ' . implode('|', $types);
                return;
            }
        }
        if (
            isset($schema['pattern']) && is_string($value) && !preg_match('/' . str_replace('/', '\/', $schema['pattern'])
            . '/u', $value)
        ) {
            $errors[] = "$path: \"$value\" does not match {$schema['pattern']}";
        }
        if (isset($schema['minLength']) && is_string($value) && mb_strlen($value) < $schema['minLength']) {
            $errors[] = "$path: shorter than {$schema['minLength']}";
        }
        if (is_array($value)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) {
                    $errors[] = "$path: missing required \"$key\"";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $sub) {
                if (array_key_exists($key, $value)) {
                    $this->validate($value[$key], $sub, "$path.$key", $errors);
                }
            }
            if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                foreach ($value as $key => $v) {
                    if (!isset($schema['properties'][$key])) {
                        $this->validate($v, $schema['additionalProperties'], "$path.$key", $errors);
                    }
                }
            }
            if (isset($schema['items']) && array_is_list($value)) {
                foreach ($value as $i => $v) {
                    $this->validate($v, $schema['items'], "{$path}[$i]", $errors);
                }
            }
        }
        foreach ($schema['allOf'] ?? [] as $sub) {
            if (isset($sub['if'])) {
                $cond = [];
                $this->validate($value, $sub['if'], $path, $cond);
                if (!$cond && isset($sub['then'])) {
                    $this->validate($value, $sub['then'], $path, $errors);
                }
            } else {
                $this->validate($value, $sub, $path, $errors);
            }
        }
    }

    /**
     * Reports of A and B are valid instances of the contract and keep the PoC fields.
     */
    public function test_reports_validate_against_schema(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$a] = $generator->create_course_a(2027);
        [$b] = $generator->create_course_b(2027);
        $poc = ['engine', 'policy_version', 'reoffer_year', 'executed_at', 'course', 'viewer_userid', 'timing', 'inventory',
            'summary', 'results', 'warnings'];
        foreach ([$a, $b] as $course) {
            $report = json_decode(engine::to_json(engine::audit($course->id, ['reoffer_year' => 2027])), true);
            $errors = [];
            $this->validate($report, $this->schema, '$', $errors);
            $this->assertSame([], $errors, $course->shortname);
            foreach ($poc as $key) {
                $this->assertArrayHasKey($key, $report, $course->shortname);
            }
            $this->assertArrayNotHasKey('snapshot', $report);
        }
        // With the snapshot included it is still valid.
        $report = json_decode(engine::to_json(engine::audit($b->id, ['reoffer_year' => 2027], 0, true)), true);
        $errors = [];
        $this->validate($report, $this->schema, '$', $errors);
        $this->assertSame([], $errors);
        $this->assertArrayHasKey('snapshot', $report);
    }

    /**
     * The validator itself rejects a broken report (so a green run above means something).
     */
    public function test_validator_detects_violations(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = json_decode(engine::to_json(engine::audit($course->id)), true);
        $report['contract_version'] = '0.9';
        $report['summary']['overall_status'] = 'OK';
        $report['results'][0]['locus'] = 'somewhere';
        $report['results'][0]['rule'] = 'bad';
        unset($report['results'][1]['link']);
        $nc = null;
        foreach ($report['results'] as $i => $r) {
            if ($r['status'] === 'NAO_CONFORME') {
                $nc = $i;
                break;
            }
        }
        $this->assertNotNull($nc);
        $report['results'][$nc]['orientacao'] = '';
        $errors = [];
        $this->validate($report, $this->schema, '$', $errors);
        $joined = implode("\n", $errors);
        $this->assertStringContainsString('$.contract_version: expected const', $joined);
        $this->assertStringContainsString('$.summary.overall_status', $joined);
        $this->assertStringContainsString('$.results[0].locus', $joined);
        $this->assertStringContainsString('$.results[0].rule', $joined);
        $this->assertStringContainsString('$.results[1]: missing required "link"', $joined);
        $this->assertStringContainsString("\$.results[$nc].orientacao: shorter than 1", $joined);
        $this->assertCount(6, $errors);
    }

    /**
     * Page and CLI serialise through the same function: decoding the JSON gives back the report array.
     */
    public function test_json_roundtrip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = engine::audit($course->id);
        $json = engine::to_json($report);
        $this->assertStringContainsString('"contract_version": "1.0"', $json);
        $this->assertStringContainsString('/course/edit.php?id=', $json, 'slashes must not be escaped');
        $this->assertStringContainsString('Média', $json, 'unicode must not be escaped');
        $this->assertSame($report, json_decode($json, true));
    }
}
