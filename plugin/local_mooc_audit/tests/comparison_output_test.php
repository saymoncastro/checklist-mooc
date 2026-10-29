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
 * Comparison page body and CLI text (specs/003 FR-022, FR-027): sections in order, escaped course content, manual match
 * form for ambiguities, link from the audit report only for users who may compare.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\output\comparison_report
 * @covers     \local_mooc_audit\comparator::render_text
 */
final class comparison_output_test extends \advanced_testcase {
    /**
     * Sections in the order of FR-022, escaping, ambiguity form, no correction action.
     */
    public function test_sections_order_and_escaping(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $src = $gen->create_course(['fullname' => 'Origem <b>negrito</b>']);
        $dst = $gen->create_course(['fullname' => 'Destino']);
        $gen->create_module('page', ['course' => $src->id, 'name' => 'Aviso <script>x</script>', 'content' => '<p>a</p>']);
        $gen->create_module('label', ['course' => $src->id, 'intro' => 'Repetido']);
        $gen->create_module('label', ['course' => $dst->id, 'intro' => 'Repetido']);
        $gen->create_module('label', ['course' => $dst->id, 'intro' => 'Repetido']);
        $r = comparator::run($src->id, $dst->id);
        $url = new \moodle_url('/local/mooc_audit/compare.php', ['source' => $src->id, 'target' => $dst->id, 'confirm' => 1]);
        $html = $PAGE->get_renderer('local_mooc_audit')->render(new output\comparison_report($r, $url));

        $ids = ['summary', 'pending', 'missing', 'ambiguous', 'modified', 'extra', 'course', 'expected', 'preserved', 'warnings'];
        $last = -1;
        foreach ($ids as $id) {
            $pos = strpos($html, "id=\"local_mooc_audit-$id\"");
            $this->assertNotFalse($pos, $id);
            $this->assertGreaterThan($last, $pos, "$id out of order");
            $last = $pos;
        }
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>negrito</b>', $html);
        $this->assertStringContainsString('name="pair[]"', $html);
        $this->assertStringContainsString('value="' . $r['ambiguities'][0]['source'][0]['cmid'] . '-', $html);
        $this->assertStringContainsString('format=json', $html);
        $this->assertStringNotContainsString('correction.php', $html);

        $text = comparator::render_text($r);
        $this->assertStringContainsString(get_string('cmp_section_missing', 'local_mooc_audit'), $text);
        $this->assertStringContainsString('Aviso <script>x</script>', $text, 'plain text for the terminal');
    }

    /**
     * The audit report shows "Compare with the previous offer" only when a compare URL is given (page decides by
     * capability).
     */
    public function test_report_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $report = engine::audit($course->id);
        $url = new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        $without = $renderer->render(new output\report($report, $url));
        $with = $renderer->render(new output\report(
            $report,
            $url,
            false,
            new \moodle_url('/local/mooc_audit/compare.php', ['target' => $course->id])
        ));
        $this->assertStringNotContainsString('compare.php', $without);
        $this->assertStringContainsString('compare.php?target=' . $course->id, $with);
        $this->assertStringContainsString(get_string('comparelink', 'local_mooc_audit'), $with);
    }
}
