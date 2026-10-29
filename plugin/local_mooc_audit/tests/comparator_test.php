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
 * Comparison between offers with real courses and Moodle's import (backup/restore in import mode): the experimental
 * scenarios A–G of specs/003. Every comparison also validates against schema/comparison-v1.0.schema.json.
 *
 * Source courses are offers of 2026; targets are the 2027 offer, audited with the 2027 policy.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\comparator
 */
final class comparator_test extends \advanced_testcase {
    /** @var array Policy of the new offer. */
    private const POLICY = ['reoffer_year' => 2027];

    /**
     * Generator of the plugin.
     *
     * @return \local_mooc_audit_generator
     */
    private function gen(): \local_mooc_audit_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
    }

    /**
     * Run a comparison and validate it against the contract.
     *
     * @param \stdClass $source
     * @param \stdClass $target
     * @param array $manual
     * @return array
     */
    private function compare(\stdClass $source, \stdClass $target, array $manual = []): array {
        global $CFG;
        $r = comparator::run($source->id, $target->id, $manual, self::POLICY);
        $validator = new tests\schema_validator($CFG->dirroot . '/local/mooc_audit/schema/comparison-v1.0.schema.json');
        $this->assertSame([], $validator->errors(json_decode(comparator::to_json($r), true)));
        return $r;
    }

    /**
     * The match entry of a target module name.
     *
     * @param array $r
     * @param string $name
     * @return array
     */
    private function match_named(array $r, string $name): array {
        foreach ($r['matches'] as $m) {
            if ($m['target_name'] === $name) {
                return $m;
            }
        }
        $this->fail("no match named $name");
    }

    /**
     * Audit items of a class: list of "rule|target local".
     *
     * @param array $r
     * @param string $class
     * @return string[]
     */
    private function items(array $r, string $class): array {
        return array_values(array_map(fn($i) => $i['rule'], array_filter(
            $r['audit']['items'],
            fn($i) => $i['classification'] === $class
        )));
    }

    /**
     * Scenario A — correct import of the reference course: everything preserved or ambiguous (news forum duplicated by
     * the import), ids changed only as expected differences; only the closing dates remain to be adjusted.
     */
    public function test_scenario_a_correct_import(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->gen()->create_course_a(2026);
        $t = $this->gen()->import_into_new_course($a, 'A_2027', 2027);
        $r = $this->compare($a, $t);

        $this->assertSame([], $r['missing_in_target']);
        $ambiguous = array_sum(array_map(fn($x) => count($x['source']), $r['ambiguities']));
        $this->assertSame($r['source']['modules'], count($r['matches']) + $ambiguous);
        foreach ($r['matches'] as $m) {
            $this->assertSame('alta', $m['confidence'], $m['source_name']);
            $this->assertFalse($m['problematic'], $m['source_name'] . ' ' . json_encode($m['diffs']));
            $this->assertNotSame($m['source_cmid'], $m['target_cmid']);
        }
        foreach ($r['ambiguities'] as $x) {
            $this->assertSame(['forum', 'duplicado_no_destino'], [$x['modname'], $x['reason']]);
        }
        // The import keeps the closing dates of the previous offer: CL-14 is a pending item of the new offer for both
        // quizzes; the adjusted course dates and self enrolment are not.
        $this->assertSame(['CL-14', 'CL-14'], $this->items($r, 'pendencia_oferta'));
        $this->assertSame(0, $r['summary']['course_diffs_relevant'], json_encode($r['course_diffs']));
        $fields = array_column(array_filter($r['course_diffs'], fn($d) => $d['kind'] === 'esperada'), 'field');
        $this->assertContains('shortname', $fields);
        $this->assertContains('startdate', $fields);
        $this->assertGreaterThan(count($r['matches']), $r['summary']['expected_identifier_changes']);
        $this->assertSame(2026, $r['policy']['source_reoffer_year']);
        $this->assertSame(2027, $r['policy']['target_reoffer_year']);
    }

    /**
     * Scenario B — inconsistent course imported: structural problems persistent, offer-cycle rules never persistent,
     * news forum duplicate registered without choosing a candidate.
     */
    public function test_scenario_b_inherited_problems_and_duplicate_forum(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->gen()->create_course_b(2026);
        $t = $this->gen()->import_into_new_course($b, 'B_2027', 2027);
        $r = $this->compare($b, $t);

        $persistent = $this->items($r, 'persistente');
        foreach (['X-DEP-04', 'CL-12', 'X-CONT-01', 'CL-13', 'X-INT-02'] as $rule) {
            $this->assertContains($rule, $persistent);
        }
        $this->assertSame([], array_intersect($persistent, comparator::CYCLE_RULES));
        $this->assertSame([], $r['missing_in_target']);
        $news = array_filter($r['ambiguities'], fn($x) => $x['reason'] === 'duplicado_no_destino');
        if ($news) {
            $this->assertGreaterThanOrEqual(1, $r['summary']['duplicates_in_target']);
            foreach ($news as $x) {
                $this->assertCount(2, $x['target']);
            }
        }
    }

    /**
     * Scenario C — glossary used as game source: entries are not imported; the difference is relevant, names the
     * dependent game, and CL-18 appears as new in the target.
     */
    public function test_scenario_c_glossary_entries_lost(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$c] = $this->gen()->create_glossary_game_course(2026);
        $t = $this->gen()->import_into_new_course($c, 'C_2027', 2027);
        $r = $this->compare($c, $t);

        $gl = $this->match_named($r, 'Glossário do jogo');
        $lost = array_values(array_filter($gl['diffs'], fn($d) => $d['code'] === 'content_lost'));
        $this->assertSame(['extra.entries', 3, 0], [$lost[0]['field'], $lost[0]['source'], $lost[0]['target']]);
        $this->assertTrue($gl['problematic']);
        if (\local_mooc_audit_generator::has_module('game')) {
            $this->assertSame(['Palavras cruzadas'], array_column($lost[0]['dependents'], 'name'));
            $game = $this->match_named($r, 'Palavras cruzadas');
            $this->assertContains('extra.glossary_entries_available', array_column($game['diffs'], 'field'));
            $this->assertNotContains('reference_unmapped', array_column($game['diffs'], 'code'), 'glossaryid is remapped');
            $this->assertContains('CL-18', $this->items($r, 'novo_no_destino'));
        }
    }

    /**
     * Scenario D — assessment changed in the target after the import: attempts, maximum grade and completion appear as
     * relevant differences with both values; CL-13 is new in the target.
     */
    public function test_scenario_d_assessment_changed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->gen()->create_course_a(2026);
        $t = $this->gen()->import_into_new_course($a, 'A_2027', 2027);
        $quiz = null;
        foreach (get_fast_modinfo($t)->get_instances_of('quiz') as $cm) {
            $quiz = $cm->name === 'Questionário 1' ? $cm : $quiz;
        }
        $this->gen()->change_quiz_assessment($quiz->id, 1, 40);
        $r = $this->compare($a, $t);

        $m = $this->match_named($r, 'Questionário 1');
        $this->assertTrue($m['problematic']);
        $byfield = array_column($m['diffs'], null, 'field');
        $this->assertSame([2, 1, 'relevante'], [$byfield['extra.attempts']['source'], $byfield['extra.attempts']['target'],
            $byfield['extra.attempts']['kind']]);
        $this->assertEquals([50, 40], [$byfield['extra.grade']['source'], $byfield['extra.grade']['target']]);
        $this->assertSame('grade_item_changed', $byfield['grade_items[0].grademax']['code']);
        $this->assertSame('completion_changed', $byfield['completion']['code']);
        $this->assertContains('CL-13', $this->items($r, 'novo_no_destino'));
        $this->assertSame('REVISAR', $r['summary']['verdict']);
    }

    /**
     * Scenario E — files: record removed, content missing from the file store, resource hidden from students; three
     * distinct codes; the source keeps its files.
     */
    public function test_scenario_e_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$e] = $this->gen()->create_files_course(2026);
        $t = $this->gen()->import_into_new_course($e, 'E_2027', 2027);
        $this->gen()->break_files($t);
        $r = $this->compare($e, $t);

        $codes = fn($name) => array_column($this->match_named($r, $name)['diffs'], 'code');
        $this->assertSame(['file_record_missing'], $codes('Apostila 1'));
        $this->assertSame(['file_content_missing'], $codes('Apostila 2'));
        $this->assertEqualsCanonicalizing(['visibility_changed', 'file_unavailable'], $codes('Apostila 3'));
        $new = $this->items($r, 'novo_no_destino');
        $this->assertContains('X-INT-02', $new);
    }

    /**
     * Scenario F — repeated names: the Libras version is matched by content, identical copies stay ambiguous with
     * identical settings; nothing is reported missing.
     */
    public function test_scenario_f_repeated_names(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$f, $ids] = $this->gen()->create_repeated_names_course(2026);
        $t = $this->gen()->import_into_new_course($f, 'F_2027', 2027);
        $r = $this->compare($f, $t);

        $aula = array_values(array_filter($r['matches'], fn($m) => $m['source_name'] === 'Aula 1'));
        $this->assertCount(2, $aula);
        foreach ($aula as $m) {
            $this->assertSame(['type_section_name_content', 'alta', 'preservada'], [$m['strategy'], $m['confidence'],
                $m['state']]);
        }
        $libras = array_values(array_filter($aula, fn($m) => $m['source_cmid'] === (int) $ids['aula1_libras']))[0];
        $content = get_fast_modinfo($t)->get_cm($libras['target_cmid']);
        $this->assertStringContainsString('libras', (string) $DB->get_field('page', 'content', ['id' => $content->instance]));
        $equiv = array_values(array_filter($r['ambiguities'], fn($x) => $x['name'] === 'Leitura complementar'));
        $this->assertSame('conteudo_equivalente', $equiv[0]['reason']);
        $this->assertTrue($equiv[0]['settings_identical']);
        $this->assertSame([], $r['missing_in_target']);

        // The user tells which copy is which: manual pairs, not persisted.
        $pairs = [[$equiv[0]['source'][0]['cmid'], $equiv[0]['target'][0]['cmid']],
            [$equiv[0]['source'][1]['cmid'], $equiv[0]['target'][1]['cmid']]];
        $r2 = $this->compare($f, $t, $pairs);
        $manual = array_filter($r2['matches'], fn($m) => $m['confidence'] === 'manual');
        $this->assertCount(2, $manual);
        $this->assertSame([], array_filter($r2['ambiguities'], fn($x) => $x['name'] === 'Leitura complementar'));
        $this->assertSame([true, true], array_column($r2['manual_pairs'], 'accepted'));
    }

    /**
     * Scenario G — offers of different years: without the new offer adjustments, dates and self enrolment are pending
     * items of the new offer, never inherited problems; with the adjustments there is none.
     */
    public function test_scenario_g_offer_years(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->gen()->create_course_a(2026);
        $raw = $this->gen()->import_into_new_course($a, 'A_2027_RAW', 2027, false);
        $r = $this->compare($a, $raw);
        $pending = $this->items($r, 'pendencia_oferta');
        foreach (['CL-06', 'CL-07'] as $rule) {
            $this->assertContains($rule, $pending);
        }
        $this->assertSame([], array_intersect($this->items($r, 'persistente'), comparator::CYCLE_RULES));
        $this->assertSame('REVISAR', $r['summary']['verdict']);
        $this->assertStringContainsString('2026', $r['policy']['note']);

        // The source audited in its own year is conformant on the cycle rules: no item comes from it.
        $this->assertSame([], array_filter($r['audit']['items'], fn($i) => in_array($i['rule'], comparator::CYCLE_RULES, true)
            && $i['classification'] !== 'pendencia_oferta'));
    }

    /**
     * A source without start date: offer-cycle rules of the source have no context and are not compared.
     */
    public function test_source_without_start_date(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $src = $this->getDataGenerator()->create_course(['startdate' => 0, 'enddate' => 0]);
        $dst = $this->getDataGenerator()->create_course();
        $r = $this->compare($src, $dst);
        $this->assertSame(['sem_contexto', null], [$r['policy']['source_basis'], $r['policy']['source_reoffer_year']]);
        $this->assertSame(0, (int) $DB->get_field('course', 'startdate', ['id' => $src->id]));
    }
}
