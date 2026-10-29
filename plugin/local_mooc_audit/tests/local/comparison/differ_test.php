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

namespace local_mooc_audit\local\comparison;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/snapshot_builder.php');

use local_mooc_audit\comparator;
use local_mooc_audit\tests\snapshot_builder;

/**
 * Classified differences (specs/003 research R3): expected, relevant and informative, and the fix of the "problematic"
 * flag (it was always true in the experimental comparator).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\comparison\differ
 * @covers     \local_mooc_audit\comparator::compare
 */
final class differ_test extends \advanced_testcase {
    /**
     * Source and target builders.
     *
     * @return snapshot_builder[]
     */
    private function builders(): array {
        return [new snapshot_builder(10, 100), new snapshot_builder(20, 300)];
    }

    /**
     * The comparison entry of the pair whose source cmid is given.
     *
     * @param array $r comparator::compare() output
     * @param int $scmid
     * @return array
     */
    private function entry(array $r, int $scmid): array {
        foreach ($r['matches'] as $m) {
            if ($m['source_cmid'] === $scmid) {
                return $m;
            }
        }
        $this->fail("no match for $scmid");
    }

    /**
     * Codes of the differences of an entry (or list), optionally of one kind.
     *
     * @param array $diffs
     * @param string|null $kind
     * @return string[]
     */
    private function codes(array $diffs, ?string $kind = null): array {
        return array_values(array_map(fn($d) => $d['code'], array_filter($diffs, fn($d) => !$kind || $d['kind'] === $kind)));
    }

    /**
     * Quiz with settings, a grade item and a close date.
     *
     * @param snapshot_builder $x
     * @param int $gid grade item id
     * @param array $extra
     * @param array $over
     * @return int cmid
     */
    private function quiz(snapshot_builder $x, int $gid, array $extra = [], array $over = []): int {
        return $x->add('quiz', 'Questionário 1', 3, array_merge([
            'extra' => array_merge(['attempts' => 2, 'grade' => 50, 'timeclose' => 1830308340, 'slots' => 5], $extra),
            'texts' => ['intro' => '<p>Tentativas permitidas: 2.</p>'],
            'grade_items' => [snapshot_builder::grade_item($gid, 50)],
            'completion' => 2, 'completiongradeitemnumber' => 0,
        ], $over));
    }

    /**
     * The defect: "problematic" must be false for informative or expected changes and true only for relevant ones.
     */
    public function test_problematic_only_for_relevant_differences(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $p1 = $a->add('page', 'Texto', 1, ['texts' => ['content' => '<p>Antigo</p>']]);
        $b->add('page', 'Texto', 1, ['texts' => ['content' => '<p>Novo</p>']]);
        $q1 = $this->quiz($a, 900);
        $this->quiz($b, 950, ['attempts' => 1]);
        $a->add('quiz', 'Questionário 2', 3, ['extra' => ['attempts' => 2, 'timeclose' => 1798772399]]);
        $b->add('quiz', 'Questionário 2', 3, ['extra' => ['attempts' => 2, 'timeclose' => 1830308340]]);
        $r = comparator::compare($a->get(), $b->get());

        $text = $this->entry($r, $p1);
        $this->assertSame(['modificada', 'informativa', false], [$text['state'], $text['relevance'], $text['problematic']]);
        $this->assertSame(['text_changed'], $this->codes($text['diffs']));

        $quiz = $this->entry($r, $q1);
        $this->assertSame(['modificada', 'relevante', true], [$quiz['state'], $quiz['relevance'], $quiz['problematic']]);
        $this->assertSame(['setting_changed'], $this->codes($quiz['diffs']));
        $this->assertSame(['extra.attempts', 2, 1], [$quiz['diffs'][0]['field'], $quiz['diffs'][0]['source'],
            $quiz['diffs'][0]['target']]);

        $dates = $this->entry($r, $q1 + 1);
        $this->assertSame(['preservada', 'esperada', false], [$dates['state'], $dates['relevance'], $dates['problematic']]);
        $this->assertSame(['date_shifted'], $this->codes($dates['diffs'], differ::EXPECTED));
    }

    /**
     * Completion and grade item changes are relevant; ids of grade items are only counted as expected.
     */
    public function test_completion_and_grade_items(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $q = $this->quiz($a, 900);
        $this->quiz($b, 950, [], ['completion' => 1, 'completiongradeitemnumber' => null,
            'grade_items' => [array_merge(snapshot_builder::grade_item(950, 40), ['gradepass' => 20.0])]]);
        $r = comparator::compare($a->get(), $b->get());
        $e = $this->entry($r, $q);
        $fields = array_column($e['diffs'], 'field');
        $this->assertContains('completion', $fields);
        $this->assertContains('completiongradeitemnumber', $fields);
        $this->assertContains('grade_items[0].grademax', $fields);
        $this->assertContains('grade_items[0].gradepass', $fields);
        $this->assertSame(['completion_changed', 'grade_item_changed'], array_values(array_unique($this->codes($e['diffs']))));
        $this->assertGreaterThanOrEqual(3, $e['expected_count']);
    }

    /**
     * Availability: remapped ids are preserved; a lost condition or a reference without counterpart is relevant; a
     * shifted date is expected.
     */
    public function test_availability_remapping(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $pa = $a->add('page', 'Leitura', 1, ['texts' => ['content' => '<p>L</p>'], 'completion' => 2, 'completionview' => 1]);
        $pb = $b->add('page', 'Leitura', 1, ['texts' => ['content' => '<p>L</p>'], 'completion' => 2, 'completionview' => 1]);
        $ga = $a->add('page', 'Só na origem', 2, ['texts' => ['content' => '<p>S</p>']]);
        $tree = fn($cm, $gid, $t) => ['op' => '&', 'showc' => [true, true, true], 'c' => [
            ['type' => 'completion', 'cm' => $cm, 'e' => 1], ['type' => 'grade', 'id' => $gid, 'min' => 60],
            ['type' => 'date', 'd' => '>=', 't' => $t]]];
        // Same conditions with remapped ids.
        $a1 = $a->add('quiz', 'Q-igual', 3, ['availability' => $tree($pa, $a->course_item_id(), 1000)]);
        $b->add('quiz', 'Q-igual', 3, ['availability' => $tree($pb, $b->course_item_id(), 1000)]);
        // Only the date changed.
        $a2 = $a->add('quiz', 'Q-data', 3, ['availability' => $tree($pa, $a->course_item_id(), 1000)]);
        $b->add('quiz', 'Q-data', 3, ['availability' => $tree($pb, $b->course_item_id(), 2000)]);
        // Grade condition lost.
        $a3 = $a->add('quiz', 'Q-perdida', 3, ['availability' => $tree($pa, $a->course_item_id(), 1000)]);
        $b->add('quiz', 'Q-perdida', 3, ['availability' => ['op' => '&', 'showc' => [true, true], 'c' => [
            ['type' => 'completion', 'cm' => $pb, 'e' => 1], ['type' => 'date', 'd' => '>=', 't' => 1000]]]]);
        // Depends on an activity that does not exist in the target.
        $a4 = $a->add('quiz', 'Q-sem-par', 3, ['availability' => ['op' => '&', 'showc' => [true],
            'c' => [['type' => 'completion', 'cm' => $ga, 'e' => 1]]]]);
        $b->add('quiz', 'Q-sem-par', 3, ['availability' => null]);
        $r = comparator::compare($a->get(), $b->get());

        $this->assertSame('preservada', $this->entry($r, $a1)['state']);
        $this->assertSame([], $this->entry($r, $a1)['diffs']);
        $this->assertSame(['date_shifted'], $this->codes($this->entry($r, $a2)['diffs']));
        $this->assertSame('preservada', $this->entry($r, $a2)['state']);
        $lost = $this->entry($r, $a3);
        $this->assertSame(['availability_changed'], $this->codes($lost['diffs']));
        $this->assertTrue($lost['problematic']);
        $this->assertStringContainsString(get_string('cmp_course_total', 'local_mooc_audit'), $lost['diffs'][0]['source']);
        $unmapped = $this->entry($r, $a4);
        $this->assertSame(['availability_unmapped'], $this->codes($unmapped['diffs']));
        $this->assertStringContainsString('Só na origem', $unmapped['diffs'][0]['explanation']);
    }

    /**
     * Glossary used as game source: lost entries are relevant and name the dependent game; the game reference to the
     * glossary is remapped (expected), or relevant when it does not point to the counterpart.
     */
    public function test_glossary_entries_and_dependent_game(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $ga = $a->add('glossary', 'Glossário de termos', 3, ['extra' => ['entries' => 3, 'categories' => 0]]);
        $gb = $b->add('glossary', 'Glossário de termos', 3, ['extra' => ['entries' => 0, 'categories' => 0]]);
        $sa = $a->get();
        $sb = $b->get();
        $game = fn($glossaryinstance, $available) => ['extra' => ['sourcemodule' => 'glossary', 'gamekind' => 'cross',
            'glossaryid' => $glossaryinstance, 'glossary_entries_available' => $available]];
        $j1 = $a->add('game', 'Palavras cruzadas', 3, $game($sa['modules'][$ga]['instanceid'], 3));
        $b->add('game', 'Palavras cruzadas', 3, $game($sb['modules'][$gb]['instanceid'], 0));
        $j2 = $a->add('game', 'Forca', 3, $game($sa['modules'][$ga]['instanceid'], 3));
        $b->add('game', 'Forca', 3, $game(77777, 3));
        $r = comparator::compare($a->get(), $b->get());

        $gl = $this->entry($r, $ga);
        $this->assertSame(['content_lost'], $this->codes($gl['diffs']));
        $this->assertSame([3, 0], [$gl['diffs'][0]['source'], $gl['diffs'][0]['target']]);
        $this->assertSame(['Palavras cruzadas'], array_column($gl['diffs'][0]['dependents'], 'name'));
        $this->assertStringContainsString('Palavras cruzadas', $gl['diffs'][0]['explanation']);

        $cross = $this->entry($r, $j1);
        $this->assertSame(['content_lost'], $this->codes($cross['diffs']));
        $this->assertSame('extra.glossary_entries_available', $cross['diffs'][0]['field']);
        $hang = $this->entry($r, $j2);
        $this->assertSame(['reference_unmapped'], $this->codes($hang['diffs']));
        $this->assertStringContainsString('Glossário de termos', $hang['diffs'][0]['explanation']);
    }

    /**
     * Files: record missing, content missing from the store, unavailable to students (hidden), added.
     */
    public function test_files(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $f = fn(array $list, array $over = []) => array_merge(['files' => ['count' => 0, 'missing_in_storage' => [],
            'list' => $list]], $over);
        $r1 = $a->add('resource', 'R1', 2, $f([snapshot_builder::file('a.pdf')]));
        $b->add('resource', 'R1', 2, $f([]));
        $r2 = $a->add('resource', 'R2', 2, $f([snapshot_builder::file('b.pdf')]));
        $b->add('resource', 'R2', 2, $f([snapshot_builder::file('b.pdf', 10, false)]));
        $r3 = $a->add('resource', 'R3', 2, $f([snapshot_builder::file('c.pdf')]));
        $b->add('resource', 'R3', 2, $f([snapshot_builder::file('c.pdf')], ['visible' => 0]));
        $r4 = $a->add('resource', 'R4', 2, $f([snapshot_builder::file('d.pdf')]));
        $b->add('resource', 'R4', 2, $f([snapshot_builder::file('d.pdf'), snapshot_builder::file('e.pdf')]));
        $r = comparator::compare($a->get(), $b->get());
        $this->assertSame(['file_record_missing'], $this->codes($this->entry($r, $r1)['diffs']));
        $this->assertSame(['file_content_missing'], $this->codes($this->entry($r, $r2)['diffs']));
        $this->assertEqualsCanonicalizing(['visibility_changed', 'file_unavailable'], $this->codes($this->entry($r, $r3)['diffs']));
        $this->assertSame(['file_added'], $this->codes($this->entry($r, $r4)['diffs']));
        $this->assertFalse($this->entry($r, $r4)['problematic']);
    }

    /**
     * Texts emptied are relevant; renaming and moving are informative.
     */
    public function test_texts_rename_move(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $p = $a->add('page', 'Introdução', 1, ['texts' => ['content' => '<p>Bem-vindo</p>']]);
        $b->add('page', 'Introdução', 1, ['texts' => ['content' => '<p></p>']]);
        $m = $a->add('quiz', 'Final', 2);
        $b->add('quiz', 'Final', 3);
        $n = $a->add('url', 'Site', 1, ['extra' => ['externalurl' => 'https://a.example']]);
        $b->add('url', 'Site oficial', 1, ['extra' => ['externalurl' => 'https://a.example']]);
        $r = comparator::compare($a->get(), $b->get());
        $this->assertSame(['text_emptied'], $this->codes($this->entry($r, $p)['diffs']));
        $this->assertTrue($this->entry($r, $p)['problematic']);
        $this->assertSame(['moved'], $this->codes($this->entry($r, $m)['diffs'], differ::INFO));
        $this->assertFalse($this->entry($r, $m)['problematic']);
        $this->assertSame(['renamed'], $this->codes($this->entry($r, $n)['diffs']));
    }

    /**
     * Course level: offer settings expected; format option, enrolment method, block, aggregation and section
     * visibility relevant; section names and counts informative; additional activities classified.
     */
    public function test_course_differences(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->patch('course', ['startdate' => 1767236400, 'formatoptions' => ['hiddensections' => 1, 'coursedisplay' => 0]]);
        $b->patch('course', ['formatoptions' => ['hiddensections' => 0, 'coursedisplay' => 0]]);
        $a->patch('enrols', [1 => ['id' => 1, 'enrol' => 'guest', 'status' => 1, 'name' => '', 'enrolstartdate' => 0,
            'enrolenddate' => 0, 'customint6' => null, 'roleid' => 0, 'has_password' => true]]);
        $b->patch('blocks', [0 => array_merge($b->get()['blocks'][0], ['title' => 'Dúvidas técnicas'])]);
        $grade = $b->get()['grade'];
        $grade['categories'][300 + 800]['aggregateonlygraded'] = 1;
        $b->patch('grade', $grade);
        $sections = $b->get()['sections'];
        $sections[$b->section_id(2)]['name'] = 'Conteúdos';
        $sections[$b->section_id(3)]['visible'] = 0;
        $b->patch('sections', $sections);
        $b->add('quiz', 'Avaliação extra', 3, ['grade_items' => [snapshot_builder::grade_item(990, 10)]]);
        $b->add('url', 'Leitura extra', 2);
        $r = comparator::compare($a->get(), $b->get());
        $bycode = [];
        foreach ($r['course_diffs'] as $d) {
            $bycode[$d['code']][] = $d['kind'] . ':' . $d['field'];
        }
        $this->assertContains('esperada:shortname', $bycode['offer_setting']);
        $this->assertContains('esperada:startdate', $bycode['offer_setting']);
        $this->assertSame(['relevante:formatoptions.hiddensections'], $bycode['course_setting_changed']);
        $this->assertSame(['relevante:guest'], $bycode['enrol_missing']);
        $this->assertSame(['relevante:html'], $bycode['block_missing']);
        $this->assertSame(['informativa:html'], $bycode['block_added']);
        $this->assertSame(['relevante:aggregateonlygraded'], $bycode['grade_aggregation_changed']);
        $this->assertSame(['informativa:section[2].name'], $bycode['section_renamed']);
        $this->assertSame(['relevante:section[3].visible'], $bycode['section_visibility_changed']);
        $this->assertSame(['extra_graded', 'extra_resource'], array_column($r['extra_in_target'], 'code'));
        $this->assertSame(['relevante', 'informativa'], array_column($r['extra_in_target'], 'kind'));
        $this->assertGreaterThan(0, $r['expected_ids']);
    }

    /**
     * Identical courses (ids aside): no relevant or informative difference at all.
     */
    public function test_identical_courses_have_only_expected_differences(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        foreach ([$a, $b] as $x) {
            $x->patch('course', ['shortname' => 'MESMO']);
            $this->quiz($x, $x === $a ? 900 : 950);
            $x->add('page', 'Leitura', 1, ['texts' => ['content' => '<p>L</p>'], 'files' => ['count' => 1,
                'missing_in_storage' => [], 'list' => [snapshot_builder::file('img.png')]]]);
        }
        $r = comparator::compare($a->get(), $b->get());
        foreach ($r['matches'] as $m) {
            $this->assertSame('preservada', $m['state']);
            $this->assertSame([], $m['diffs']);
        }
        $this->assertSame([], $r['course_diffs']);
    }

    /**
     * A source that starts after the target is flagged as a possible inversion (never inverted automatically).
     */
    public function test_inverted_order_warning(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->patch('course', ['startdate' => 1830308400]);
        $r = comparator::compare($a->get(), $b->get());
        $this->assertContains(get_string('cmp_inverted', 'local_mooc_audit'), $r['warnings']);
        $this->assertSame(10, $r['source']['id']);
        $r = comparator::compare($b->get(), $a->get());
        $this->assertNotContains(get_string('cmp_inverted', 'local_mooc_audit'), $r['warnings']);
    }

    /**
     * Enrolment: dates and self enrolment status are offer settings; the role, the status of other methods and fewer
     * instances are relevant.
     */
    public function test_enrolment_classification(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $guest = ['id' => 1, 'enrol' => 'guest', 'status' => 1, 'name' => '', 'enrolstartdate' => 0, 'enrolenddate' => 0,
            'customint6' => null, 'roleid' => 0, 'has_password' => false];
        $self = $a->get()['enrols'][0];
        $a->patch('enrols', [0 => array_merge($self, ['status' => 1, 'enrolstartdate' => 0]), 1 => $guest,
            2 => array_merge($self, ['id' => 2])]);
        $b->patch('enrols', [0 => array_merge($self, ['roleid' => 3]), 1 => array_merge($guest, ['status' => 0])]);
        $r = comparator::compare($a->get(), $b->get());
        $kinds = [];
        foreach ($r['course_diffs'] as $d) {
            if ($d['scope'] === 'enrol') {
                $kinds[$d['field']] = $d['kind'];
            }
        }
        $this->assertSame([
            'self.instances' => 'relevante',
            'self.status' => 'esperada',
            'self.roleid' => 'relevante',
            'self.enrolstartdate' => 'esperada',
            'guest.status' => 'relevante',
        ], $kinds);
    }
}
