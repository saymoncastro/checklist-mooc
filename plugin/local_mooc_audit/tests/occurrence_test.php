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
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/snapshot_builder.php');

use local_mooc_audit\local\status;
use local_mooc_audit\tests\snapshot_builder;

/**
 * Stable occurrence keys (specs/005 FR-008, FR-009, SC-002): unique per audit, declared by every rule, independent of
 * the order of the modules and of the interface language, identical whether the verified condition passes or fails.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\engine::occurrence
 */
final class occurrence_test extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;

    /**
     * Occurrence keys of a list of results.
     *
     * @param array $results
     * @return string[]
     */
    protected static function keys(array $results): array {
        return array_column($results, 'occurrence');
    }

    /**
     * Every reference and scenario course: keys are unique, every result has one, no rule falls back to the default
     * check and no collision suffix is needed.
     */
    public function test_keys_unique_and_declared_on_reference_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        $courses = [$gen->create_course_a(self::YEAR)[0], $gen->create_course_b(self::YEAR)[0],
            $gen->create_files_course(self::YEAR)[0], $gen->create_repeated_names_course(self::YEAR)[0]];
        if (\local_mooc_audit_generator::has_module('game')) {
            $courses[] = $gen->create_glossary_game_course(self::YEAR)[0];
        }
        $rulesseen = [];
        foreach ($courses as $course) {
            $report = engine::audit($course->id, ['reoffer_year' => self::YEAR]);
            $keys = self::keys($report['results']);
            $this->assertCount(count($report['results']), array_filter($keys), "Result without key in {$course->shortname}");
            $this->assertSame($keys, array_values(array_unique($keys)), "Duplicate key in {$course->shortname}");
            foreach ($keys as $k) {
                $this->assertStringNotContainsString('#', $k, "Collision fallback used: $k");
                $this->assertDoesNotMatchRegularExpression('/\|main(\||$)/', $k, "Rule without check code: $k");
                $this->assertMatchesRegularExpression('/^(CL-\d{2}|X-[A-Z]+-\d{2})\|(course|cm:\d+|sec:\d+)\|[a-z_]+/', $k);
                $rulesseen[explode('|', $k)[0]] = true;
            }
        }
        $this->assertCount(local\catalog::count(), $rulesseen, 'Every rule produced at least one keyed result');
    }

    /**
     * Course B audited with the modules and sections in reverse order and in English: same key → state/severity map.
     */
    public function test_keys_independent_of_order_and_language(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(self::YEAR);
        $policy = policy::load(['reoffer_year' => self::YEAR]);
        $snapshot = snapshot::build($b->id);
        $map = fn(array $results) => array_combine(
            self::keys($results),
            array_map(fn($r) => $r['status'] . '|' . $r['gravidade'], $results)
        );
        $base = $map(engine::run_rules($snapshot, $policy));
        ksort($base);

        $reversed = $snapshot;
        $reversed['modules'] = array_reverse($snapshot['modules'], true);
        $reversed['sections'] = array_reverse($snapshot['sections'], true);
        foreach ($reversed['sections'] as &$sec) {
            $sec['sequence'] = array_reverse($sec['sequence']);
        }
        unset($sec);
        $other = $map(engine::run_rules($reversed, $policy));
        ksort($other);
        $this->assertSame($base, $other);

        force_current_language('en');
        $english = $map(engine::run_rules($snapshot, $policy));
        force_current_language('');
        ksort($english);
        $this->assertSame($base, $english);
        $this->assertContains('NAO_CONFORME|' . status::BLOQUEANTE, $base);
    }

    /**
     * The same verification keeps its key when it passes and when it fails (CL-13 attempts), and the ERRO that replaces
     * a whole rule has a key of its own.
     */
    public function test_same_key_for_pass_and_fail(): void {
        $this->resetAfterTest();
        $policy = policy::load(['reoffer_year' => self::YEAR]);
        $build = function (int $attempts) {
            $b = new snapshot_builder(10, 100);
            $b->add('quiz', 'Questionário 1', 1, ['extra' => ['attempts' => $attempts, 'slots' => 2]]);
            return $b->get();
        };
        $rules = ['CL-13' => new local\rule\cl13()];
        $fail = engine::run_rules($build(1), $policy, $rules);
        $pass = engine::run_rules($build(2), $policy, $rules);
        $this->assertSame('NAO_CONFORME', $fail[0]['status']);
        $this->assertSame('CONFORME', $pass[0]['status']);
        $this->assertSame('CL-13|cm:100|attempts', $fail[0]['occurrence']);
        $this->assertSame($fail[0]['occurrence'], $pass[0]['occurrence']);
        // Contract 1.1 (specs/006): check and subject stay in the result, equal for pass and fail.
        $this->assertSame('attempts', $fail[0]['check']);
        $this->assertSame($fail[0]['check'], $pass[0]['check']);
        $this->assertNull($fail[0]['subject']);

        $broken = new class extends local\rule\cl13 {
            /**
             * Always fails.
             *
             * @param array $s
             * @param array $p
             * @return array
             */
            public function evaluate(array $s, array $p): array {
                throw new \coding_exception('boom');
            }
        };
        $err = engine::run_rules($build(1), $policy, ['CL-13' => $broken]);
        $this->assertSame('ERRO', $err[0]['status']);
        $this->assertSame('CL-13|course|' . engine::RULE_CHECK, $err[0]['occurrence']);
    }

    /**
     * Several findings of one rule at the same location (three required HTML blocks missing) get distinct keys that do
     * not depend on the evidence text; adding one block changes only its own occurrence.
     */
    public function test_several_findings_at_one_location(): void {
        $this->resetAfterTest();
        $policy = policy::load(['reoffer_year' => self::YEAR]);
        $rules = ['CL-21' => new local\rule\cl21()];
        $b = new snapshot_builder(10, 100);
        $snap = $b->get();
        $snap['blocks'] = [];
        $snap['blocks_source'] = 'test';
        $none = engine::run_rules($snap, $policy, $rules);
        $keys = self::keys($none);
        $this->assertSame([
            'CL-21|course|progress_block',
            'CL-21|course|html_block|atencao ao prazo',
            'CL-21|course|html_block|duvidas tecnicas',
            'CL-21|course|html_block|acessibilidade',
        ], $keys);
        $snap['blocks'] = [['instanceid' => 1, 'name' => 'html', 'title' => 'Acessibilidade', 'region' => 'side-pre',
            'visible' => 1, 'parentcontextlevel' => 50, 'inherited' => false, 'pagetypepattern' => '*', 'config' => null]];
        $one = engine::run_rules($snap, $policy, $rules);
        $this->assertSame($keys, self::keys($one));
        $changed = array_keys(array_diff_assoc(array_column($none, 'status'), array_column($one, 'status')));
        $this->assertSame([3], $changed);
    }

    /**
     * X-DEP-04: the same cycle found from another starting module has the same key.
     */
    public function test_cycle_subject_is_rotation_independent(): void {
        $this->assertSame('12>15>19', local\rule\xdep04::canonical([15, 19, 12, 15]));
        $this->assertSame('12>15>19', local\rule\xdep04::canonical([12, 15, 19, 12]));
        $this->assertSame('12>15>19', local\rule\xdep04::canonical([19, 12, 15, 19]));
    }
}
