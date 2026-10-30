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

use local_mooc_audit\local\presentation\causes;
use local_mooc_audit\local\status;
use local_mooc_audit\output\report;

/**
 * Structure of the report (specs/006, phase 2): top summary, start here, causes, views, manual checks.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\output\report
 * @covers     \local_mooc_audit\local\presentation\causes
 */
final class presentation_summary_test extends \advanced_testcase {
    /**
     * Template data of the report of a course.
     *
     * @param \stdClass $course
     * @param string $view
     * @return array [audit, data]
     */
    protected function export(\stdClass $course, string $view = report::VIEW_PRIORITY): array {
        global $PAGE;
        $audit = engine::audit($course->id, ['reoffer_year' => 2027]);
        $url = new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
        $data = (new report($audit, $url, true))->set_view($view)->export_for_template($PAGE->get_renderer('local_mooc_audit'));
        return [$audit, $data];
    }

    /**
     * Value of a summary fact by its label.
     *
     * @param \stdClass $data
     * @param string $key language string of the label
     * @return int|null
     */
    protected function fact(\stdClass $data, string $key): ?int {
        foreach ($data->summary->facts as $fact) {
            if ($fact->label === get_string($key, 'local_mooc_audit')) {
                return $fact->value;
            }
        }
        return null;
    }

    /**
     * Course B: counts by nature, causes by the closed list R12, start here with the dependency first, manual check of
     * the certificate.
     */
    public function test_course_b_summary_causes_and_start_here(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        [$audit, $data] = $this->export($b);

        $nc = array_values(array_filter($audit['results'], fn($r) => $r['status'] === status::NAO_CONFORME));
        $this->assertFalse($data->summary->clean);
        $this->assertSame(count($nc), $this->fact($data, 'summary_problems'));
        $this->assertSame(
            count(array_filter($nc, fn($r) => $r['gravidade'] === status::BLOQUEANTE)),
            $this->fact($data, 'summary_blocking')
        );
        $causes = causes::groups($audit['results'], [status::NAO_CONFORME]);
        $this->assertSame(count($causes), $this->fact($data, 'summary_causes'));
        $this->assertLessThan(count($nc), count($causes), 'some problems share a cause');
        // Quiz attempts and the statement are one cause; so are the two completion items of the label.
        $this->assertNotEmpty(array_filter(array_keys($causes), fn($k) => str_starts_with($k, 'attempts:')));
        $this->assertNotEmpty(array_filter($causes, fn($m) => count($m) > 1));

        $this->assertNotEmpty($data->startwith);
        $this->assertLessThanOrEqual(3, count($data->startwith));
        $this->assertStringContainsString('Aprofundamento', $data->startwith[0]->problem, 'the blocking dependency comes first');

        $this->assertTrue($data->hasmanual);
        $this->assertStringContainsString('Certificado digital', $data->manual[0]->title);
    }

    /**
     * View by activity: each activity once, the same occurrences and states as the view by priority (FR-015).
     */
    public function test_views_show_the_same_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        [, $priority] = $this->export($b);
        [, $activity] = $this->export($b, report::VIEW_ACTIVITY);

        $this->assertTrue($activity->activityview);
        $this->assertFalse($priority->activityview);
        $fromgroups = function (\stdClass $section): array {
            $out = [];
            foreach ($section->groups as $g) {
                foreach ($g->categories as $c) {
                    foreach ($c->items as $item) {
                        $out[] = $item->anchor . '|' . $item->status;
                    }
                }
            }
            return $out;
        };
        $bypriority = array_merge($fromgroups($priority->nonconformities), $fromgroups($priority->warnings));
        $byactivity = [];
        $names = [];
        foreach ($activity->activities as $group) {
            $names[] = $group->name;
            foreach ($group->items as $item) {
                $byactivity[] = $item->anchor . '|' . $item->status;
            }
        }
        sort($bypriority);
        sort($byactivity);
        $this->assertSame($bypriority, $byactivity);
        $this->assertSame(1, count(array_keys($names, 'Questionário 1')));
        $this->assertSame(get_string('view_coursesettings', 'local_mooc_audit'), end($names));
    }

    /**
     * A course without problems: a plain sentence, no approval wording, no start here.
     */
    public function test_clean_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(2027);
        [$audit, $data] = $this->export($a);
        $this->assertSame(0, $audit['summary']['por_status'][status::NAO_CONFORME] ?? 0);
        $this->assertTrue($data->summary->clean);
        $this->assertStringNotContainsStringIgnoringCase('aprovad', $data->summary->cleantext);
        $this->assertFalse($data->hasstartwith);
    }
}
