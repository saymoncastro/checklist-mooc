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
