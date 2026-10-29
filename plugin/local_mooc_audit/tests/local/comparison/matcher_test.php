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

use local_mooc_audit\tests\snapshot_builder;

/**
 * Module matching without internal ids (specs/003 research R2): passes, uniqueness on both sides, ambiguities, manual
 * pairs and determinism.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\comparison\matcher
 */
final class matcher_test extends \advanced_testcase {
    /**
     * Source (ids from 100) and target (ids from 300) builders.
     *
     * @return snapshot_builder[]
     */
    private function builders(): array {
        return [new snapshot_builder(10, 100), new snapshot_builder(20, 300)];
    }

    /**
     * Page data with a text.
     *
     * @param string $html
     * @return array
     */
    private function page(string $html): array {
        return ['texts' => ['intro' => '', 'content' => $html]];
    }

    /**
     * Names of matched pairs: source name => [target name, strategy, confidence].
     *
     * @param array $r matcher output
     * @param array $src
     * @param array $dst
     * @return array
     */
    private function pairs(array $r, array $src, array $dst): array {
        $out = [];
        foreach ($r['pairs'] as $s => $p) {
            $out[$src['modules'][$s]['name'] . "#$s"] = [$dst['modules'][$p['target']]['name'], $p['strategy'],
                $p['confidence']];
        }
        return $out;
    }

    /**
     * Same structure with every id changed (as after an import): everything matched by type + section + name.
     */
    public function test_identical_courses_with_new_ids(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        foreach ([$a, $b] as $x) {
            $x->add('page', 'Conceitos', 1, $this->page('<p>Um</p>'));
            $x->add('quiz', 'Questionário 1', 3);
            $x->add('forum', 'Espaço de interação', 0);
        }
        $r = matcher::match($a->get(), $b->get());
        $this->assertCount(3, $r['pairs']);
        $this->assertSame([], $r['missing']);
        $this->assertSame([], $r['extra']);
        $this->assertSame([], $r['ambiguities']);
        $this->assertSame([100 => 300, 101 => 301, 102 => 302], array_map(fn($p) => $p['target'], $r['pairs']));
        foreach ($r['pairs'] as $p) {
            $this->assertSame('type_section_name', $p['strategy']);
            $this->assertSame(matcher::HIGH, $p['confidence']);
        }
    }

    /**
     * The order of the modules never changes the result.
     */
    public function test_result_does_not_depend_on_module_order(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        foreach ([$a, $b] as $x) {
            $x->add('page', 'Aula', 1, $this->page('<p>A</p>'));
            $x->add('page', 'Aula', 1, $this->page('<p>B</p>'));
            $x->add('forum', 'Avisos', 0);
            $x->add('page', 'Movida', 2, $this->page('<p>M</p>'));
        }
        $b->add('forum', 'Avisos', 0);
        $src = $a->get();
        $dst = $b->get();
        $r1 = matcher::match($src, $dst);
        $src['modules'] = array_reverse($src['modules'], true);
        $dst['modules'] = array_reverse($dst['modules'], true);
        $r2 = matcher::match($src, $dst);
        $this->assertSame($r1, $r2);
    }

    /**
     * The import duplicates "Avisos": one in the source, two identical in the target. No pair is chosen, not even by
     * position (the native forum is at the same position as the source one), and nothing is reported missing.
     */
    public function test_duplicate_in_target_is_ambiguous(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $news = ['extra' => ['type' => 'news'], 'texts' => ['intro' => 'Notícias e avisos gerais']];
        $a->add('forum', 'Avisos', 0, $news);
        $a->add('page', 'Conceitos', 1, $this->page('<p>Um</p>'));
        $b->add('forum', 'Avisos', 0, $news);
        $b->add('page', 'Conceitos', 1, $this->page('<p>Um</p>'));
        $b->add('forum', 'Avisos', 0, $news);
        $r = matcher::match($a->get(), $b->get());
        $this->assertArrayNotHasKey(100, $r['pairs']);
        $this->assertSame([], $r['missing']);
        $this->assertSame([], $r['extra']);
        $this->assertCount(1, $r['ambiguities']);
        $amb = $r['ambiguities'][0];
        $this->assertSame('duplicado_no_destino', $amb['reason']);
        $this->assertSame([100], array_column($amb['source'], 'cmid'));
        $this->assertSame([300, 302], array_column($amb['target'], 'cmid'));
        $this->assertTrue($amb['settings_identical']);
    }

    /**
     * Two modules with the same type and name in the source and one in the target: ambiguous (which one was kept?).
     */
    public function test_duplicate_in_source_is_ambiguous(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('label', 'Atenção', 1, ['texts' => ['intro' => 'Atenção']]);
        $a->add('label', 'Atenção', 2, ['texts' => ['intro' => 'Atenção']]);
        $b->add('label', 'Atenção', 3, ['texts' => ['intro' => 'Atenção']]);
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([], $r['pairs']);
        $this->assertSame('duplicado_na_origem', $r['ambiguities'][0]['reason']);
    }

    /**
     * Same name, different content (version in Libras): each one matched with its own version by content.
     */
    public function test_same_name_different_content_matched_by_content(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('page', 'Aula 1', 1, $this->page('<p>Texto da aula</p>'));
        $a->add('page', 'Aula 1', 1, $this->page('<p>Texto da aula</p><iframe src="https://v.example/libras-aula1"></iframe>'));
        // Target in the other order.
        $b->add('page', 'Aula 1', 1, $this->page('<p>Texto da aula</p><iframe src="https://v.example/libras-aula1"></iframe>'));
        $b->add('page', 'Aula 1', 1, $this->page('<p>Texto da aula</p>'));
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([100 => 301, 101 => 300], array_map(fn($p) => $p['target'], $r['pairs']));
        $this->assertSame('type_section_name_content', $r['pairs'][100]['strategy']);
        $this->assertSame(matcher::HIGH, $r['pairs'][100]['confidence']);
        $this->assertSame([], $r['ambiguities']);
    }

    /**
     * Identical modules with the same name on both sides: equivalent content, settings identical or not.
     */
    public function test_identical_clones_are_equivalent_content(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        foreach ([$a, $b] as $x) {
            $x->add('page', 'Leitura complementar', 2, $this->page('<p>Mesmo texto</p>'));
            $x->add('page', 'Leitura complementar', 2, $this->page('<p>Mesmo texto</p>'));
        }
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([], $r['pairs']);
        $this->assertSame([], $r['missing']);
        $this->assertSame('conteudo_equivalente', $r['ambiguities'][0]['reason']);
        $this->assertTrue($r['ambiguities'][0]['settings_identical']);

        $b->set(301, ['completion' => 2, 'completionview' => 1]);
        $r = matcher::match($a->get(), $b->get());
        $this->assertFalse($r['ambiguities'][0]['settings_identical']);
    }

    /**
     * Activity moved to another section: matched by type + name, not reported as missing + additional.
     */
    public function test_moved_activity(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('quiz', 'Avaliação final', 2);
        $b->add('quiz', 'Avaliação final', 3);
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame(['type_name', matcher::HIGH], [$r['pairs'][100]['strategy'], $r['pairs'][100]['confidence']]);
        $this->assertSame([], $r['missing']);
    }

    /**
     * Renamed activity with identical content: medium confidence by content.
     */
    public function test_renamed_activity_matched_by_content(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('page', 'Introdução', 1, $this->page('<p>Bem-vindo ao curso</p>'));
        $b->add('page', 'Apresentação do curso', 1, $this->page('<p>Bem-vindo ao curso</p>'));
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame(['type_content', matcher::MEDIUM], [$r['pairs'][100]['strategy'], $r['pairs'][100]['confidence']]);
    }

    /**
     * Renamed and edited activity at the same position of the same section: medium confidence by position.
     */
    public function test_renamed_and_edited_matched_by_position(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('page', 'Texto 1', 1, $this->page('<p>Versão antiga</p>'));
        $b->add('page', 'Texto um', 1, $this->page('<p>Versão nova</p>'));
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame(['type_section_position', matcher::MEDIUM], [$r['pairs'][100]['strategy'],
            $r['pairs'][100]['confidence']]);
    }

    /**
     * Content or position never decide inside a frozen ambiguity, and a module of another type never matches.
     */
    public function test_weak_passes_never_pick_inside_ambiguity_or_across_types(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('forum', 'Avisos', 0);
        $b->add('forum', 'Avisos', 0);
        $b->add('forum', 'Avisos', 0);
        $a->add('page', 'Leitura', 2, $this->page('<p>x</p>'));
        $b->add('url', 'Leitura', 2);
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([], $r['pairs']);
        $this->assertSame([101], $r['missing']);
        $this->assertSame([302], $r['extra']);
    }

    /**
     * Missing and additional activities.
     */
    public function test_missing_and_extra(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('quiz', 'Questionário 1', 3);
        $a->add('glossary', 'Glossário', 3, ['texts' => ['intro' => 'Termos']]);
        $b->add('quiz', 'Questionário 1', 3);
        $b->add('url', 'Material extra', 2, ['extra' => ['externalurl' => 'https://x.example']]);
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([101], $r['missing']);
        $this->assertSame([301], $r['extra']);
    }

    /**
     * Manual pairs are validated and applied before the automatic passes; invalid ones are reported.
     */
    public function test_manual_pairs(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('forum', 'Avisos', 0);
        $a->add('page', 'P', 1, $this->page('<p>p</p>'));
        $b->add('forum', 'Avisos', 0);
        $b->add('forum', 'Avisos', 0);
        $b->add('page', 'P', 1, $this->page('<p>p</p>'));
        $r = matcher::match($a->get(), $b->get(), [[100, 301], [101, 300], [999, 302], [100, 300], [101, 999]]);
        $this->assertSame(['target' => 301, 'strategy' => 'manual', 'confidence' => matcher::MANUAL], $r['pairs'][100]);
        $this->assertSame([300], $r['extra']);
        $this->assertSame([], $r['ambiguities']);
        $this->assertSame(302, $r['pairs'][101]['target']);
        $reasons = array_column($r['manual'], 'reason');
        $this->assertSame([null, 'different_type', 'source_not_found', 'duplicate', 'target_not_found'], $reasons);
    }

    /**
     * Fingerprints ignore settings and ids; text signatures ignore ids and query strings of links.
     */
    public function test_fingerprints(): void {
        $this->resetAfterTest();
        [$a] = $this->builders();
        $c1 = $a->add('page', 'P', 1, ['texts' => ['content' => '<p>Veja <a href="/mod/page/view.php?id=5">aqui</a></p>'],
            'extra' => ['display' => 5]]);
        $c2 = $a->add('page', 'P', 1, ['texts' => ['content' => '<p>Veja  <a href="/mod/page/view.php?id=77">aqui</a></p>'],
            'extra' => ['display' => 6], 'completion' => 2]);
        $c3 = $a->add('label', 'vazio', 1, ['texts' => ['intro' => '<p> </p>']]);
        $s = $a->get();
        $this->assertSame(matcher::fingerprint($s['modules'][$c1]), matcher::fingerprint($s['modules'][$c2]));
        $this->assertNull(matcher::fingerprint($s['modules'][$c3]));
        $this->assertNotSame(matcher::settings_fingerprint($s['modules'][$c1]), matcher::settings_fingerprint($s['modules'][$c2]));
        $this->assertSame('veja aqui [view.php]', matcher::text_signature('<p>Veja <a href="x/view.php?id=1">aqui</a></p>'));
    }

    /**
     * Modules without content never match by the empty fingerprint: one empty forum in the source and, in the target,
     * an empty one and one with an introduction stay ambiguous.
     */
    public function test_empty_fingerprint_never_disambiguates(): void {
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $a->add('forum', 'Dúvidas', 0);
        $b->add('forum', 'Dúvidas', 0);
        $b->add('forum', 'Dúvidas', 0, ['texts' => ['intro' => 'Tire suas dúvidas aqui']]);
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([], $r['pairs']);
        $this->assertSame('duplicado_no_destino', $r['ambiguities'][0]['reason']);
    }

    /**
     * External links keep their query (it identifies the video); links into this site keep only the file name.
     */
    public function test_external_links_keep_their_query(): void {
        global $CFG;
        $this->resetAfterTest();
        [$a, $b] = $this->builders();
        $video = fn($id) => ['texts' => ['content' => "<p>Aula</p><a href=\"https://www.youtube.com/watch?v=$id\">vídeo</a>"]];
        $a->add('page', 'Aula 1', 1, $video('AAA'));
        $a->add('page', 'Aula 1', 1, $video('LIBRAS'));
        $b->add('page', 'Aula 1', 1, $video('LIBRAS'));
        $b->add('page', 'Aula 1', 1, $video('AAA'));
        $r = matcher::match($a->get(), $b->get());
        $this->assertSame([100 => 301, 101 => 300], array_map(fn($p) => $p['target'], $r['pairs']));
        $this->assertNotSame(
            matcher::text_signature('<a href="https://youtu.be.example/watch?v=1">x</a>'),
            matcher::text_signature('<a href="https://youtu.be.example/watch?v=2">x</a>')
        );
        $this->assertSame(
            matcher::text_signature('<a href="' . $CFG->wwwroot . '/mod/page/view.php?id=1">x</a>'),
            matcher::text_signature('<a href="' . $CFG->wwwroot . '/mod/page/view.php?id=2">x</a>')
        );
    }
}
