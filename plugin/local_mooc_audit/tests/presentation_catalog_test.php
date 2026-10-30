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

use local_mooc_audit\local\catalog as rules;
use local_mooc_audit\local\presentation\catalog;
use local_mooc_audit\local\status;

/**
 * Message catalogue of the report (specs/006, SC-011): every verified condition has its texts in both languages.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\presentation\catalog
 */
final class presentation_catalog_test extends \advanced_testcase {
    /** @var string[] Database-like markers the main texts must never contain (FR-007). */
    const FORBIDDEN = ['cmid=', 'completion=', 'status=', 'aggregateonlygraded', 'hiddensections=', '{"', '@@PLUGINFILE@@'];

    /**
     * Every variant of the catalogue (and its no-plugin variant) has problem, why and fix in en and pt_br, with no
     * database-like markers.
     */
    public function test_every_variant_has_texts_in_both_languages(): void {
        $sm = get_string_manager();
        $langs = ['en' => $sm->load_component_strings('local_mooc_audit', 'en'),
            'pt_br' => $sm->load_component_strings('local_mooc_audit', 'pt_br')];
        foreach (catalog::all() as [$rule, $check, $variant]) {
            $variants = [$variant];
            if (catalog::entry($rule, $check, $variant)['plugin']) {
                $variants[] = 'noplugin';
            }
            foreach ($variants as $v) {
                foreach (['problem', 'why', 'fix'] as $part) {
                    $key = catalog::key($rule, $check, $v) . '_' . $part;
                    foreach ($langs as $lang => $strings) {
                        $this->assertArrayHasKey($key, $strings, "$lang $key");
                        foreach (self::FORBIDDEN as $marker) {
                            $this->assertStringNotContainsString($marker, $strings[$key], "$lang $key");
                        }
                    }
                }
            }
        }
        // Both languages have the same message keys.
        $msg = fn($strings) => array_values(array_filter(array_keys($strings), fn($k) => str_starts_with($k, 'msg_')));
        $this->assertSame($msg($langs['en']), $msg($langs['pt_br']));
    }

    /**
     * Every check a rule declares for a problem, warning or unverifiable result is in the catalogue (the "summary" and
     * "applicability" checks only report conformity or non applicability).
     */
    public function test_every_declared_check_is_in_the_catalogue(): void {
        global $CFG;
        foreach (rules::instances() as $id => $rule) {
            $file = $CFG->dirroot . '/local/mooc_audit/classes/local/rule/' . \local_mooc_audit\local\rule\base::key($id) . '.php';
            preg_match_all("/'check' => '([a-z_]+)'/", file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $check) {
                if (in_array($check, ['summary', 'applicability'], true) && !isset(catalog::ENTRIES[$id][$check])) {
                    continue;
                }
                $this->assertArrayHasKey($check, catalog::ENTRIES[$id] ?? [], "$id $check");
            }
        }
    }

    /**
     * On the reference courses A and B, every problem, warning and unverifiable result gets a message (no fallback to
     * the stored text).
     */
    public function test_reference_courses_are_fully_covered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$a] = $gen->create_course_a(2027);
        [$b] = $gen->create_course_b(2027);
        $seen = 0;
        foreach ([$a, $b] as $course) {
            $report = engine::audit($course->id, ['reoffer_year' => 2027]);
            foreach ($report['results'] as $r) {
                if (!in_array($r['status'], [status::NAO_CONFORME, status::ATENCAO, status::NAO_VERIFICAVEL], true)) {
                    continue;
                }
                $occ = catalog::parse_occurrence($r['occurrence']);
                $this->assertNotNull(catalog::variant($r, $occ['check'], $occ['subject']), $r['occurrence']);
                $seen++;
            }
        }
        $this->assertGreaterThan(20, $seen);
    }

    /**
     * The occurrence key is split into rule, entity, check and subject; the duplicate suffix is ignored.
     */
    public function test_parse_occurrence(): void {
        $this->assertSame(
            ['rule' => 'X-DEP-02', 'entity' => 'cm:43', 'check' => 'target_completion', 'subject' => 'cm:38'],
            catalog::parse_occurrence('X-DEP-02|cm:43|target_completion|cm:38#2')
        );
        $this->assertSame(
            ['rule' => 'CL-06', 'entity' => 'course', 'check' => 'startdate', 'subject' => null],
            catalog::parse_occurrence('CL-06|course|startdate')
        );
        $this->assertSame('', catalog::parse_occurrence(null)['check']);
    }
}
