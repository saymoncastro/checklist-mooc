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

use local_mooc_audit\local\status;
use local_mooc_audit\output\report;

/**
 * Report page data and rendering (templates/report.mustache).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\output\report
 * @covers     \local_mooc_audit\output\renderer
 */
final class output_test extends \advanced_testcase {
    /**
     * Course B: non conformities grouped by severity then category, warnings, conformant list, overall status; the
     * template renders without missing strings and shows the evidence untruncated.
     */
    public function test_export_and_render_course_b(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $url = new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $b->id]);
        $audit = engine::audit($b->id, ['reoffer_year' => 2027]);
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        $data = (new report($audit, $url))->export_for_template($renderer);

        $this->assertSame(status::OVERALL_BLOCKED, $data->overall->code);
        $this->assertSame('alert-danger', $data->overall->cssclass);
        $this->assertSame($audit['summary']['por_status'][status::NAO_CONFORME], $data->nonconformities->count);
        $this->assertSame($audit['summary']['por_status'][status::ATENCAO], $data->warnings->count);
        // Severity groups come in display order and each category keeps catalogue order.
        $sevs = array_map(fn($g) => $g->severity, $data->nonconformities->groups);
        $this->assertSame(array_values(array_intersect(status::severities(), $sevs)), $sevs);
        $this->assertSame(status::BLOQUEANTE, $sevs[0]);
        $sum = 0;
        foreach ($data->nonconformities->groups as $g) {
            foreach ($g->categories as $c) {
                $sum += count($c->items);
                foreach ($c->items as $item) {
                    $this->assertNotEmpty($item->local, $item->rule);
                    $this->assertNotEmpty($item->evidencia, $item->rule);
                    $this->assertNotEmpty($item->orientacao, $item->rule);
                }
            }
            $this->assertSame($g->count, array_sum(array_map(fn($c) => count($c->items), $g->categories)));
        }
        $this->assertSame($data->nonconformities->count, $sum);
        // Conformant list: one entry per rule, only rules without any other state (CL-13 has NAO_CONFORME in B).
        $rules = array_map(fn($c) => $c->rule, $data->conformant);
        $this->assertSame($rules, array_unique($rules));
        $this->assertNotContains('CL-13', $rules);
        $this->assertContains('CL-05', $rules);
        $this->assertStringContainsString('format=json', $data->jsonurl);
        $this->assertStringNotContainsString('format=', $data->rerunurl);

        $html = $renderer->render(new report($audit, $url));
        $this->assertStringNotContainsString('[[', $html, 'missing language string');
        $this->assertStringNotContainsString('Example context', $html, 'template documentation leaked into the page');
        $this->assertStringContainsString(get_string('section_nonconformities', 'local_mooc_audit'), $html);
        $this->assertStringContainsString(get_string('overall_bloqueada', 'local_mooc_audit'), $html);
        $this->assertStringContainsString('<th', $html);
        // Locations with a destination are links (CL-13 → module settings); without one, plain text (X-DEP-04).
        $cl13 = array_values(array_filter($audit['results'], fn($r) => $r['rule'] === 'CL-13'
            && $r['status'] === status::NAO_CONFORME));
        $this->assertStringContainsString('<a href="' . s($cl13[0]['link']) . '"', $html);
        $this->assertStringContainsString(get_string('link_title', 'local_mooc_audit', get_string(
            'locus_module_edit',
            'local_mooc_audit'
        )), $html);
        $this->assertStringContainsString(get_string('rerun', 'local_mooc_audit'), $html);
        $this->assertStringContainsString('<form method="get"', $html);
        // Evidence is rendered in full (the PoC truncated it at 300 characters).
        $longest = '';
        foreach ($audit['results'] as $r) {
            if ($r['status'] === status::NAO_CONFORME && strlen((string) $r['evidencia']) > strlen($longest)) {
                $longest = $r['evidencia'];
            }
        }
        $this->assertStringContainsString(s($longest), $html);
    }

    /**
     * Transparency blocks: NAO_VERIFICAVEL with reason under "could not be verified", ERRO with message, policy and
     * collection notices; none of them changes the overall status.
     */
    public function test_unverifiable_errors_and_notices(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        set_config('enablecompletion', 0);
        set_config('policyjson', '{bad', 'local_mooc_audit');
        $audit = engine::audit($course->id);
        $audit['results'][] = ['rule' => 'T-ERR-01', 'titulo' => 'Broken rule', 'status' => status::ERRO,
            'category' => 'plugins_environment',
            'gravidade' => status::MEDIA, 'local' => null, 'evidencia' => 'RuntimeException: boom', 'encontrado' => null,
                'esperado' => null,
            'orientacao' => null, 'cmid' => null, 'locus' => 'none', 'link' => null, 'tipo' => 'tecnica', 'origem' => 'test',
                'classe' => 'A'];
        $audit['warnings'][] = 'block_manager falhou';
        $audit['summary'] = engine::summarize($audit['results']);
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        $data = (new report($audit, new \moodle_url(
            '/local/mooc_audit/index.php',
            ['courseid' => $course->id]
        )))->export_for_template($renderer);

        $this->assertTrue($data->unverifiable->hasnv);
        $this->assertContains(get_string('site_completion_disabled', 'local_mooc_audit'), array_column(
            $data->unverifiable->nv,
            'evidencia'
        ));
        foreach ($data->unverifiable->nv as $item) {
            $this->assertNotEmpty($item->evidencia, $item->rule);
        }
        $this->assertTrue($data->haserrors);
        $this->assertSame('RuntimeException: boom', $data->errors[0]->evidencia);
        $this->assertTrue($data->notices->has);
        $this->assertCount(1, $data->notices->policy);
        $this->assertSame(['block_manager falhou'], $data->notices->collection);
        $counts = array_column($data->counts_status, 'count', 'code');
        $this->assertSame(1, $counts[status::ERRO]);
        $this->assertGreaterThan(0, $counts[status::NAO_VERIFICAVEL]);
        $this->assertSame($audit['summary']['overall_status'], $data->overall->code);

        $html = $renderer->render(new report($audit, new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id])));
        foreach (['section_unverifiable_nv', 'section_errors', 'section_notices'] as $key) {
            $this->assertStringContainsString(get_string($key, 'local_mooc_audit'), $html, $key);
        }
        $this->assertStringContainsString(get_string('policy_invalid', 'local_mooc_audit', 'Syntax error'), $html);
        $this->assertStringContainsString('block_manager falhou', $html);
        $this->assertStringContainsString('RuntimeException: boom', $html);
    }

    /**
     * Course and category names are escaped exactly once (format_string output goes through Mustache escaping).
     */
    public function test_names_escaped_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $cat = $this->getDataGenerator()->create_category(['name' => 'Ofertas & Reofertas']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Arte & Cultura <b>MOOC</b>', 'category' => $cat->id]);
        $audit = engine::audit($course->id);
        $url = new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
        $html = $PAGE->get_renderer('local_mooc_audit')->render(new report($audit, $url));
        $this->assertStringContainsString('Arte &amp; Cultura', $html);
        $this->assertStringContainsString('Ofertas &amp; Reofertas', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<b>MOOC</b>', $html, 'markup in names is not rendered as HTML');
    }

    /**
     * Course A: clean overall status, no non conformities, every rule in the conformant list.
     */
    public function test_export_course_a(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(2027);
        $audit = engine::audit($a->id, ['reoffer_year' => 2027]);
        $data = (new report($audit, new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $a->id])))
            ->export_for_template($PAGE->get_renderer('local_mooc_audit'));
        $this->assertSame(status::OVERALL_CLEAN, $data->overall->code);
        $this->assertFalse($data->nonconformities->has);
        $this->assertSame([], $data->nonconformities->groups);
        $this->assertCount(local\catalog::count(), $data->conformant);
        $this->assertFalse($data->haserrors);
        $this->assertFalse($data->notices->has);
    }
}
