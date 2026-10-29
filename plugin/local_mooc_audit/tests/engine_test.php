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

use local_mooc_audit\local\catalog;
use local_mooc_audit\local\category;
use local_mooc_audit\local\status;

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
        $this->assertContains(status::NAO_CONFORME, $this->statuses($report, 'CL-13'));
        $this->assertContains(status::CONFORME, $this->statuses($report, 'CL-13'));
        // 60 + 40 = 100: the sum itself is conforme.
        $sum = array_values(array_filter($report['results'], fn($r) => $r['rule'] === 'CL-12' && strpos(
            $r['evidencia'],
            'soma dos máximos'
        ) === 0));
        $this->assertSame(status::CONFORME, $sum[0]['status']);
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
        $this->assertNotContains(status::ERRO, array_column($report['results'], 'status'));
        $this->assertCount(catalog::count(), array_unique(array_column($report['results'], 'rule')));
    }

    /**
     * Contract 1.0: every result carries category, locus, link and valid state/severity; NAO_CONFORME has guidance;
     * the overall status follows the non conformities only.
     */
    public function test_contract_fields_and_overall_status(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$a] = $generator->create_course_a(2027);
        [$b] = $generator->create_course_b(2027);
        $ra = engine::audit($a->id, ['reoffer_year' => 2027]);
        $rb = engine::audit($b->id, ['reoffer_year' => 2027]);
        $this->assertSame(engine::CONTRACT_VERSION, $ra['contract_version']);
        $this->assertSame([], $ra['policy_warnings']);
        $this->assertSame(status::OVERALL_CLEAN, $ra['summary']['overall_status']);
        $this->assertSame(status::OVERALL_BLOCKED, $rb['summary']['overall_status']);
        $loci = \local_mooc_audit\local\locator::loci();
        $links = 0;
        foreach (array_merge($ra['results'], $rb['results']) as $r) {
            $this->assertTrue(category::is_valid($r['category']), $r['rule']);
            $this->assertContains($r['status'], status::all(), $r['rule']);
            $this->assertContains($r['gravidade'], status::severities(), $r['rule']);
            $this->assertContains($r['tipo'], ['institucional', 'tecnica'], $r['rule']);
            $this->assertContains($r['locus'], $loci, $r['rule']);
            if (in_array($r['locus'], ['module_edit', 'module_view'], true)) {
                $this->assertNotEmpty($r['cmid'], $r['rule'] . ': module locus without cmid');
                $this->assertNotNull($r['link'], $r['rule']);
            }
            $this->assertTrue($r['link'] === null || is_string($r['link']), $r['rule']);
            $links += $r['link'] !== null ? 1 : 0;
            if ($r['status'] === status::NAO_CONFORME) {
                $this->assertNotEmpty($r['local'], $r['rule']);
                $this->assertNotEmpty($r['evidencia'], $r['rule']);
                $this->assertNotEmpty($r['orientacao'], $r['rule']);
            }
            if ($r['status'] === status::ATENCAO) {
                $this->assertNotEmpty($r['local'], $r['rule']);
                $this->assertNotEmpty($r['evidencia'], $r['rule']);
            }
        }
        $this->assertContains(status::ATENCAO, array_column($rb['results'], 'status'));
        $this->assertGreaterThan(0, $links);
        // A module finding links to the module editing page.
        $cl13 = array_values(array_filter($rb['results'], fn($r) => $r['rule'] === 'CL-13'
            && $r['status'] === status::NAO_CONFORME));
        $this->assertStringContainsString('/course/modedit.php?update=' . $cl13[0]['cmid'], $cl13[0]['link']);
        // Course setting findings link to the course settings page.
        $cl06 = array_values(array_filter($rb['results'], fn($r) => $r['rule'] === 'CL-06'));
        $this->assertStringContainsString('/course/edit.php?id=' . $b->id, $cl06[0]['link']);
        // Section findings link to the section page.
        $sec = array_values(array_filter($rb['results'], fn($r) => $r['rule'] === 'CL-20' && $r['locus'] === 'section'));
        $this->assertNotEmpty($sec);
        $this->assertStringContainsString('/course/section.php?id=', $sec[0]['link']);
    }

    /**
     * Completion tracking disabled at site level: completion and certification rules become NAO_VERIFICAVEL with a
     * reason and never count as non conformities.
     */
    public function test_site_completion_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        set_config('enablecompletion', 0);
        $report = engine::audit($b->id, ['reoffer_year' => 2027]);
        foreach ($report['results'] as $r) {
            if (in_array($r['category'], [category::COMPLETION_RESTRICTIONS, category::CERTIFICATION], true)) {
                $this->assertSame(status::NAO_VERIFICAVEL, $r['status'], $r['rule']);
                $this->assertNotEmpty($r['evidencia']);
            } else {
                $this->assertNotSame(status::NAO_VERIFICAVEL, $r['status'], $r['rule']);
            }
        }
        // Other categories still report (e.g. CL-13 attempts); X-DEP-02/04 and CL-23 no longer count as blocking,
        // so the only blocking non conformity left is CL-18 (empty glossary used by a game).
        $this->assertArrayHasKey('NAO_CONFORME', $report['summary']['por_status']);
        $blocking = array_filter($report['results'], fn($r) => $r['status'] === status::NAO_CONFORME
            && $r['gravidade'] === status::BLOQUEANTE);
        $this->assertSame(['CL-18'], array_values(array_unique(array_column($blocking, 'rule'))));
    }

    /**
     * The CLI text output carries the overall status and the policy notices, through language strings.
     */
    public function test_render_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        set_config('policyjson', '{not json', 'local_mooc_audit');
        $report = engine::audit($course->id);
        $text = engine::render_text($report);
        $this->assertStringContainsString(get_string('label_overall', 'local_mooc_audit') . ': '
            . get_string('overall_' . strtolower($report['summary']['overall_status']), 'local_mooc_audit'), $text);
        $this->assertStringContainsString(get_string('policy_invalid', 'local_mooc_audit', 'Syntax error'), $text);
        $this->assertStringContainsString(get_string('label_conformant_omitted', 'local_mooc_audit'), $text);
    }

    /**
     * A missing institutional component points to the course import page; a module locus always has a module.
     */
    public function test_import_locus_for_missing_components(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = engine::audit($course->id);
        foreach (['CL-09', 'CL-11', 'CL-23'] as $rule) {
            $r = array_values(array_filter($report['results'], fn($x) => $x['rule'] === $rule));
            $this->assertSame(status::NAO_CONFORME, $r[0]['status'], $rule);
            $this->assertSame('import', $r[0]['locus'], $rule);
            $this->assertStringContainsString('/backup/import.php?id=' . $course->id, $r[0]['link'], $rule);
        }
        $cl22 = array_filter($report['results'], fn($x) => $x['rule'] === 'CL-22');
        $this->assertCount(3, $cl22);
        foreach ($cl22 as $r) {
            $this->assertSame('import', $r['locus']);
        }
    }

    /**
     * A rule that throws yields exactly one ERRO result with the message; every other rule is still evaluated and
     * the error never counts as a non conformity.
     */
    public function test_rule_exception_is_isolated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $broken = new class extends local\rule\base {
            /** @var string Rule id. */
            const ID = 'T-ERR-01';
            /** @var string Category. */
            const CATEGORY = category::PLUGINS_ENVIRONMENT;
            /** @var string Origin. */
            const ORIGIN = 'test';
            /**
             * Always fails.
             *
             * @param array $s
             * @param array $p
             * @return array
             */
            public function evaluate(array $s, array $p): array {
                throw new \RuntimeException('boom');
            }
            /**
             * Metadata without language strings.
             *
             * @return array
             */
            public function meta(): array {
                return ['id' => self::ID, 'title' => 'Broken rule', 'category' => self::CATEGORY, 'origin' => self::ORIGIN,
                    'type' => self::TYPE,
                    'severity' => self::SEVERITY, 'policy_keys' => [], 'locus' => self::LOCUS, 'guidance' => '', 'source' => ''];
            }
        };
        $rules = catalog::instances();
        $rules = array_merge(array_slice($rules, 0, 3, true), ['T-ERR-01' => $broken], array_slice($rules, 3, null, true));
        $snapshot = snapshot::build($course->id, 0);
        $results = engine::run_rules($snapshot, policy::load(), $rules);

        $err = array_values(array_filter($results, fn($r) => $r['rule'] === 'T-ERR-01'));
        $this->assertCount(1, $err);
        $this->assertSame(status::ERRO, $err[0]['status']);
        $this->assertSame(get_string('rule_error', 'local_mooc_audit', 'RuntimeException: boom'), $err[0]['evidencia']);
        $this->assertSame('none', $err[0]['locus']);
        $this->assertCount(catalog::count() + 1, array_unique(array_column($results, 'rule')));
        $summary = engine::summarize($results);
        $this->assertSame(1, $summary['por_status'][status::ERRO]);
        $this->assertSame(
            engine::summarize(array_filter($results, fn($r) => $r['rule'] !== 'T-ERR-01'))['overall_status'],
            $summary['overall_status']
        );
    }

    /**
     * Invalid or ill-typed admin policy: defaults are used and a warning is reported; valid overrides are applied.
     */
    public function test_policy_warnings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        set_config('policyjson', '{not json', 'local_mooc_audit');
        $report = engine::audit($course->id);
        $this->assertCount(1, $report['policy_warnings']);
        $this->assertSame(get_string('policy_invalid', 'local_mooc_audit', 'Syntax error'), $report['policy_warnings'][0]);
        $this->assertSame(policy::DEFAULT_VERSION, $report['policy_version']);

        set_config(
            'policyjson',
            json_encode(['min_quiz_attempts' => 3, 'required_blocks' => 'oops', 'unknown_key' => 1]),
            'local_mooc_audit'
        );
        $report = engine::audit($course->id);
        $this->assertCount(1, $report['policy_warnings']);
        $this->assertStringContainsString('required_blocks', $report['policy_warnings'][0]);
        $this->assertSame(policy::DEFAULT_VERSION . '+admin', $report['policy_version']);
        $policy = policy::load();
        $this->assertSame(3, $policy['min_quiz_attempts']);
        $this->assertIsArray($policy['required_blocks']);
        $this->assertSame(1, $policy['unknown_key']);
    }
}
