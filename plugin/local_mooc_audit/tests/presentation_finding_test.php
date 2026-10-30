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

use local_mooc_audit\local\presentation\finding;
use local_mooc_audit\local\status;

/**
 * What the report shows for each result (specs/006, US2, US7, US8): names, actions, precedences, responsibility.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\presentation\finding
 */
final class presentation_finding_test extends \advanced_testcase {
    /** @var \stdClass Course B. */
    protected \stdClass $course;
    /** @var array Audit report of course B. */
    protected array $report;

    /**
     * Course B audited as administrator.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        [$this->course] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $this->report = engine::audit($this->course->id, ['reoffer_year' => 2027]);
    }

    /**
     * Presenter for the current user.
     *
     * @param callable|null $installed
     * @param array|null $results
     * @return finding
     */
    protected function presenter(?callable $installed = null, ?array $results = null): finding {
        return new finding(
            (int) $this->course->id,
            $results ?? $this->report['results'],
            policy::load(['reoffer_year' => 2027]),
            new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $this->course->id]),
            $installed
        );
    }

    /**
     * First stored result of a rule and check.
     *
     * @param string $rule
     * @param string $check
     * @param string|null $status
     * @return array
     */
    protected function result(string $rule, string $check, ?string $status = status::NAO_CONFORME): array {
        foreach ($this->report['results'] as $r) {
            if ($r['rule'] === $rule && explode('|', $r['occurrence'])[2] === $check && (!$status || $r['status'] === $status)) {
                return $r;
            }
        }
        $this->fail("$rule $check not found");
    }

    /**
     * X-DEP-02: both activities by name, one action per alternative, each opening one section of the form.
     */
    public function test_dependency_names_both_activities_and_links_to_both_sections(): void {
        $p = $this->presenter()->present($this->result('X-DEP-02', 'target_completion'));
        $this->assertFalse($p->fallback);
        $this->assertStringContainsString('Questionário 1', $p->problem);
        $this->assertStringContainsString('Aprofundamento', $p->problem);
        $this->assertStringNotContainsString('cmid', $p->problem);
        $this->assertCount(2, $p->alternatives);
        $urls = array_map(fn($a) => $a->url, $p->actions);
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('showonly=activitycompletionheader', $urls[0]);
        $this->assertStringContainsString('showonly=availabilityconditionsheader', $urls[1]);
        $this->assertSame(get_string('who_you', 'local_mooc_audit'), $p->who);
    }

    /**
     * Another fix on the same activity says that the invalid condition must be fixed first (the form is not saved).
     */
    public function test_invalid_condition_comes_first_on_the_same_activity(): void {
        $p = $this->presenter()->present($this->result('CL-13', 'attempts'));
        $this->assertSame(
            get_string('msg_before_restriction', 'local_mooc_audit', (object) ['name' => 'Questionário 1']),
            $p->before
        );
        $this->assertNull($this->presenter()->present($this->result('X-DEP-02', 'target_completion'))->before);
    }

    /**
     * X-DEP-04: the cycle by activity names, one "restrict access" action per activity.
     */
    public function test_cycle_by_names(): void {
        $p = $this->presenter()->present($this->result('X-DEP-04', 'cycle'));
        $this->assertStringContainsString('"Ciclo A" → "Ciclo B" → "Ciclo A"', $p->problem);
        $this->assertCount(2, $p->actions);
    }

    /**
     * An activity removed after the audit is named as such and gets no action.
     */
    public function test_removed_activity_has_no_action(): void {
        $r = $this->result('X-DEP-02', 'target_completion');
        $target = (int) substr(explode('|', $r['occurrence'])[3], 3);
        course_delete_module($target);
        $p = $this->presenter()->present($r);
        $this->assertStringContainsString(get_string('msg_removed', 'local_mooc_audit', $target), $p->problem);
        $this->assertCount(1, $p->actions);
        $this->assertStringContainsString('showonly=availabilityconditionsheader', $p->actions[0]->url);
    }

    /**
     * A viewer without editing capabilities sees the texts and who can fix, never an action.
     */
    public function test_viewer_without_editing_rights_gets_no_action(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'teacher');
        $this->setUser($user);
        foreach ([['X-DEP-02', 'target_completion'], ['CL-06', 'startdate'], ['CL-08', 'self_enrol']] as [$rule, $check]) {
            $p = $this->presenter()->present($this->result($rule, $check));
            $this->assertSame([], $p->actions, $rule);
            $this->assertSame(get_string('who_teacher', 'local_mooc_audit'), $p->who, $rule);
        }
    }

    /**
     * Manual completion on a label: "disable completion", consistent with CL-19 (not "switch to automatic").
     */
    public function test_manual_completion_on_label_says_disable(): void {
        $cms = get_fast_modinfo($this->course->id)->get_cms();
        foreach ($this->report['results'] as $r) {
            if (
                $r['rule'] === 'CL-17' && $r['status'] === status::NAO_CONFORME && !empty($r['cmid'])
                    && ($cms[$r['cmid']]->modname ?? '') === 'label'
            ) {
                $p = $this->presenter()->present($r);
                $this->assertSame(get_string('msg_cl17_completion_ncoff_fix', 'local_mooc_audit', (object) [
                    'completion' => get_string('activitycompletion', 'completion')]), $p->fix);
                return;
            }
        }
        $this->fail('CL-17 on the label not found');
    }

    /**
     * Precedences: dates before self enrolment; gradebook total before the certificate minimum grade.
     */
    public function test_dates_and_total_come_first(): void {
        $results = $this->report['results'];
        $results[] = ['rule' => 'CL-06', 'status' => status::NAO_CONFORME, 'occurrence' => 'CL-06|course|startdate',
            'cmid' => null, 'gravidade' => status::ALTA, 'locus' => 'course_settings', 'local' => '', 'orientacao' => null];
        $results[] = ['rule' => 'CL-12', 'status' => status::NAO_CONFORME, 'occurrence' => 'CL-12|course|total_sum',
            'cmid' => null, 'gravidade' => status::ALTA, 'locus' => 'gradebook', 'local' => '', 'orientacao' => null];
        $presenter = $this->presenter(null, $results);
        $this->assertSame(
            get_string('msg_before_dates', 'local_mooc_audit'),
            $presenter->present($this->result('CL-08', 'self_enrol'))->before
        );
        $this->assertSame(
            get_string('msg_before_total', 'local_mooc_audit'),
            $presenter->present($this->result('CL-23', 'min_grade'))->before
        );
    }

    /**
     * Missing certificate plugin: administrator, no import action, and a notice for the top of the report.
     */
    public function test_missing_certificate_plugin(): void {
        $r = ['rule' => 'CL-23', 'status' => status::NAO_CONFORME, 'occurrence' => 'CL-23|course|certificate_presence',
            'cmid' => null, 'gravidade' => status::BLOQUEANTE, 'locus' => 'import', 'local' => '', 'orientacao' => null];
        $presenter = $this->presenter(fn($component) => $component !== 'mod_simplecertificate');
        $p = $presenter->present($r);
        $this->assertSame(get_string('who_admin', 'local_mooc_audit'), $p->who);
        $this->assertSame([], $p->actions);
        $this->assertStringContainsString('mod_simplecertificate', $p->problem);
        $this->assertCount(1, $presenter->missing_plugins());
        $this->assertSame(get_string('place_course', 'local_mooc_audit'), $p->place);
        // Installed: the team imports it from the standard room.
        $p = $this->presenter(fn($component) => true)->present($r);
        $this->assertSame(get_string('who_team', 'local_mooc_audit'), $p->who);
        $this->assertStringContainsString('/backup/import.php', $p->actions[0]->url);
    }

    /**
     * Course category: the reoffer team when the viewer can only keep the current category.
     */
    public function test_category_is_for_the_team_when_the_selector_has_no_other_category(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'editingteacher');
        $this->setUser($user);
        $r = ['rule' => 'CL-05', 'status' => status::NAO_CONFORME, 'occurrence' => 'CL-05|course|category',
            'cmid' => null, 'gravidade' => status::BAIXA, 'locus' => 'course_settings', 'local' => '', 'orientacao' => null];
        $this->assertSame(get_string('who_team', 'local_mooc_audit'), $this->presenter()->present($r)->who);
    }

    /**
     * Course settings and self enrolment come back to the report; results without an occurrence keep the stored text.
     */
    public function test_return_url_and_fallback(): void {
        $p = $this->presenter()->present($this->result('CL-08', 'self_enrol'));
        $this->assertStringContainsString('/enrol/editinstance.php', $p->actions[0]->url);
        $this->assertStringContainsString('returnurl=', $p->actions[0]->url);
        $old = $this->result('CL-08', 'self_enrol');
        unset($old['occurrence']);
        $this->assertTrue($this->presenter()->present($old)->fallback);
    }
}
