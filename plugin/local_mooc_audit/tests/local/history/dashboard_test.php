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

namespace local_mooc_audit\local\history;

use local_mooc_audit\local\status;

/**
 * Dashboard data (specs/005 US4): one situation per course, outdated flags, filters, counters, summed evolution, and
 * no misleading wording in the rendered page.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\dashboard
 * @covers     \local_mooc_audit\output\dashboard_page
 */
final class dashboard_test extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;

    /**
     * A finished run written directly (situations not reachable with the fixtures: failed, partial).
     *
     * @param int $courseid
     * @param string $status
     * @param string|null $overall
     * @return int
     */
    protected function fake_run(int $courseid, string $status, ?string $overall = null): int {
        $id = run_store::create($courseid, run_store::ORIGIN_PAGE, 0, 0, run_store::RUNNING);
        run_store::finish($id, $status, ['overall' => $overall,
            'policyid' => policy_store::ensure(\local_mooc_audit\policy::load()), 'summary' => $overall ? ['por_status' => [],
            'nao_conformes_por_gravidade' => [], 'overall_status' => $overall] : null]);
        return $id;
    }

    /**
     * Every situation, filters and counters.
     */
    public function test_situations_filters_and_counters(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        [$a] = $gen->create_course_a(self::YEAR);
        [$b] = $gen->create_course_b(self::YEAR);
        $never = $this->getDataGenerator()->create_course(['fullname' => 'Sala nunca auditada']);
        $failed = $this->getDataGenerator()->create_course(['fullname' => 'Sala com falha']);
        $partial = $this->getDataGenerator()->create_course(['fullname' => 'Sala parcial']);
        recorder::record_now((int) $a->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        recorder::record_now((int) $b->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        [$second] = recorder::record_now((int) $b->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        $this->fake_run((int) $failed->id, run_store::FAILED);
        $this->fake_run((int) $partial->id, run_store::PARTIAL, status::OVERALL_NEEDS_FIX);
        recorder::queue((int) $never->id, (int) $USER->id, 0);
        // A dead run (queued, its task lost) is not "pending" (code review finding 5).
        run_store::create((int) $failed->id, run_store::ORIGIN_BATCH, (int) $USER->id);

        $data = dashboard::build((int) $USER->id, []);
        $situations = [];
        foreach ($data['rows'] as $row) {
            $situations[(int) $row->course->id] = $row->situation;
        }
        $this->assertSame(dashboard::NONE_DETECTED, $situations[$a->id]);
        $this->assertSame(dashboard::BLOCKING, $situations[$b->id]);
        $this->assertSame(dashboard::NOT_AUDITED, $situations[$never->id]);
        $this->assertSame(dashboard::INCOMPLETE, $situations[$failed->id]);
        $this->assertSame(dashboard::INCOMPLETE, $situations[$partial->id]);
        $c = $data['counts'];
        $this->assertSame(5, $c['selected']);
        $this->assertSame(3, $c['audited'], 'a course whose runs all failed was never audited (code review finding 4)');
        $this->assertSame(1, $c[dashboard::NOT_AUDITED]);
        $this->assertSame(1, $c[dashboard::BLOCKING]);
        $this->assertSame(1, $c[dashboard::NONE_DETECTED]);
        $this->assertSame(0, $c[dashboard::NEEDS_FIX]);
        $this->assertSame(2, $c[dashboard::INCOMPLETE]);
        $this->assertSame(1, $c[dashboard::PENDING]);
        $this->assertSame(
            5,
            array_sum(array_intersect_key($c, array_flip(dashboard::situations()))),
            'exactly one situation per course'
        );
        $this->assertSame(run_store::json($second, 'evolution')['counts']['persistent'], $data['evolution']['persistent']);
        // Blocking first.
        $this->assertSame(dashboard::BLOCKING, $data['rows'][0]->situation);

        // Filters.
        $only = fn(array $f) => array_map(fn($r) => (int) $r->course->id, dashboard::build((int) $USER->id, $f)['rows']);
        $this->assertSame([(int) $b->id], $only(['situation' => dashboard::BLOCKING]));
        $this->assertSame([(int) $b->id], $only(['severity' => status::BLOQUEANTE]));
        $this->assertSame([(int) $never->id], $only(['situation' => dashboard::PENDING]));
        $this->assertSame([(int) $failed->id], $only(['q' => 'com falha']));
        $this->assertEqualsCanonicalizing([(int) $a->id, (int) $b->id], $only(['categoryid' => $a->category]));
        $today = date('Y-m-d');
        $this->assertCount(4, $only(['from' => $today, 'to' => $today]));
        $this->assertSame([], $only(['to' => '2000-01-01']));
        $this->assertSame('', dashboard::clean_filters(['situation' => 'approved'])['situation']);

        // Outdated: age, policy change.
        $this->assertSame([], $only(['situation' => dashboard::STALE]));
        // Every run of B aged (the latest run is the last one to finish, not the highest id).
        $DB->set_field(run_store::TABLE, 'timefinished', time() - 30 * DAYSECS, ['courseid' => $b->id]);
        $DB->set_field(run_store::TABLE, 'timefinished', time() - 29 * DAYSECS, ['id' => $second->id]);
        $this->assertSame([(int) $b->id], $only(['situation' => dashboard::STALE]));
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR, 'min_quiz_attempts' => 3]), 'local_mooc_audit');
        $rows = dashboard::build((int) $USER->id, ['situation' => dashboard::STALE])['rows'];
        $this->assertCount(3, $rows);
        $this->assertContains('policy_changed', $rows[0]->stale);
    }

    /**
     * The rendered dashboard never says "approved" nor shows a percentage, and shows the scope notice.
     */
    public function test_no_misleading_wording(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$a] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(self::YEAR);
        recorder::record_now((int) $a->id, run_store::ORIGIN_PAGE, (int) $USER->id, ['reoffer_year' => self::YEAR]);
        $PAGE->set_url('/local/mooc_audit/dashboard.php');
        $PAGE->set_context(\context_system::instance());
        foreach (['en', 'pt_br'] as $lang) {
            force_current_language($lang);
            $html = $PAGE->get_renderer('local_mooc_audit')->render(new \local_mooc_audit\output\dashboard_page(
                dashboard::build((int) $USER->id, []),
                [],
                true,
                [],
                [],
                0,
                50,
                new \moodle_url('/local/mooc_audit/dashboard.php')
            ));
            $this->assertDoesNotMatchRegularExpression('/aprovad|approved|\d+\s*%/iu', strip_tags($html));
            $this->assertStringContainsString(
                get_string('dashboard_scope_notice', 'local_mooc_audit', 29),
                html_entity_decode($html, ENT_QUOTES)
            );
            $this->assertStringContainsString(get_string('situation_none_detected', 'local_mooc_audit'), $html);
        }
        force_current_language('');
    }

    /**
     * A change of the room after its audit flags the result as outdated (decision 12.3), and a new audit clears it.
     */
    public function test_course_changed_after_audit(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
        [$a] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(self::YEAR);
        [$run] = recorder::record_now((int) $a->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        $this->assertNotEmpty($run->coursecacherev);
        $stale = fn() => dashboard::build((int) $USER->id, ['q' => $a->shortname])['rows'][0]->stale;
        $this->assertNotContains('course_changed', $stale());

        $this->getDataGenerator()->create_module('page', ['course' => $a->id, 'section' => 1]);
        $this->assertContains('course_changed', $stale());

        $this->waitForSecond();
        recorder::record_now((int) $a->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        $this->assertNotContains('course_changed', $stale());
    }
    /**
     * A new plugin version outdates a result only when the code of the rules changed (A-03 of docs/revisao-geral).
     */
    public function test_plugin_update_without_rule_change_is_not_stale(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
        [$a] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(self::YEAR);
        [$run] = recorder::record_now((int) $a->id, run_store::ORIGIN_PAGE, (int) $USER->id);
        $stale = fn() => dashboard::build((int) $USER->id, ['q' => $a->shortname])['rows'][0]->stale;
        $this->assertNotContains('plugin_changed', $stale());

        // Other version.php (schema, interface…), same rules: still current.
        $DB->set_field(run_store::TABLE, 'pluginversion', '0.4.9 (2026010100)', ['id' => $run->id]);
        $this->assertNotContains('plugin_changed', $stale());

        // A rule whose code differs from the installed one: outdated, on the dashboard and on the report page.
        $details = run_store::json($run, 'details');
        $details['rules']['CL-13']['r'] = '000000000000';
        $DB->set_field(run_store::TABLE, 'details', json_encode($details), ['id' => $run->id]);
        $this->assertContains('plugin_changed', $stale());
        // As index.php does: the stored run plus the fingerprint of its policy.
        $record = run_store::get((int) $run->id);
        $record->fingerprint = policy_store::get((int) $record->policyid)->fingerprint;
        $reasons = dashboard::stale_reasons(
            $record,
            time(),
            policy_store::current_fingerprint(),
            run_store::plugin_version()
        );
        $this->assertContains('plugin_changed', $reasons);

        // Run recorded without rule fingerprints: falls back to the plugin version.
        $DB->set_field(run_store::TABLE, 'details', null, ['id' => $run->id]);
        $this->assertContains('plugin_changed', $stale());
        $DB->set_field(run_store::TABLE, 'pluginversion', run_store::plugin_version(), ['id' => $run->id]);
        $this->assertNotContains('plugin_changed', $stale());
    }
}
