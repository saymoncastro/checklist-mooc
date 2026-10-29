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

namespace local_mooc_audit\local\correction;

use local_mooc_audit\correction_testcase;
use local_mooc_audit\engine;
use local_mooc_audit\local\status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/correction_testcase.php');
foreach (['failing_write', 'unreadable_write', 'reaudit_fails_executor', 'nowait_executor'] as $double) {
    require_once($CFG->dirroot . "/local/mooc_audit/tests/fixtures/correction/$double.php");
}

/**
 * Confirmed corrections: only the planned field changes, Moodle reads the new value, the re-audit no longer reports the
 * finding, the trail records it; conflicts, missing capabilities, student data, failures and repetitions never write.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\correction\executor
 * @covers     \local_mooc_audit\local\correction\trail
 * @covers     \local_mooc_audit\event\correction_applied
 */
final class executor_test extends correction_testcase {
    /**
     * Execute with the fingerprint of a fresh preview.
     *
     * @param \stdClass $course
     * @param string $rule
     * @param int $cmid
     * @param executor|null $executor
     * @return array
     */
    private function confirm(\stdClass $course, string $rule, int $cmid, ?executor $executor = null): array {
        $p = $this->plan($course, $rule, $cmid);
        $this->assertNotNull($p);
        return ($executor ?? new executor())->execute($course->id, $rule, $cmid, $p->fingerprint(), $this->policy());
    }

    /**
     * Statuses of a rule on an activity in a fresh audit.
     *
     * @param \stdClass $course
     * @param string $rule
     * @param int $cmid
     * @return string[]
     */
    private function statuses_of(\stdClass $course, string $rule, int $cmid): array {
        $report = engine::audit($course->id, $this->policy());
        return verifier::target($report['results'], $rule, $cmid);
    }

    /**
     * Keys whose values differ between two rows.
     *
     * @param \stdClass $a
     * @param \stdClass $b
     * @return string[]
     */
    private function changed(\stdClass $a, \stdClass $b): array {
        return array_keys(array_diff_assoc((array) $b, (array) $a));
    }

    /**
     * CL-13: attempts 1 → 2. Only attempts and timemodified change; the quiz API reads 2; both CL-13 findings are gone;
     * one trail row and the standard events.
     */
    public function test_cl13_applies_only_the_planned_field(): void {
        global $DB;
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Avaliação do Tópico 1', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $before = $this->quizrow($q);
        $cacherev = (int) $DB->get_field('course', 'cacherev', ['id' => $course->id]);
        $this->assertSame([status::NAO_CONFORME, status::NAO_CONFORME], $this->statuses_of($course, 'CL-13', $q->coursemodule));
        $this->waitForSecond();

        $sink = $this->redirectEvents();
        $r = $this->confirm($course, 'CL-13', $q->coursemodule);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(executor::APPLIED, $r['status'], $r['message']);
        $this->assertSame(verifier::RESOLVED, $r['reaudit']);
        $after = $this->quizrow($q);
        $this->assertSame(['attempts', 'timemodified'], $this->changed($before, $after));
        $this->assertEquals(2, $after->attempts);
        $this->assertSame(2, (int) \mod_quiz\quiz_settings::create($q->instance)->get_quiz()->attempts);
        $this->assertSame([status::CONFORME], $this->statuses_of($course, 'CL-13', $q->coursemodule));
        $this->assertGreaterThan($cacherev, (int) $DB->get_field('course', 'cacherev', ['id' => $course->id]));
        $this->assertCount(2, $r['comparison']['resolved']);
        $this->assertSame([], $r['comparison']['introduced']);

        $rows = $this->trail();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertEquals($course->id, $row->courseid);
        $this->assertEquals($q->coursemodule, $row->cmid);
        $this->assertSame('CL-13', $row->ruleid);
        $this->assertSame('quiz_attempts', $row->strategy);
        $this->assertSame('attempts', $row->field);
        $this->assertSame('1', $row->oldvalue);
        $this->assertSame('2', $row->newvalue);
        $this->assertSame(trail::APPLIED, $row->outcome);
        $this->assertSame(verifier::RESOLVED, $row->reaudit);
        $this->assertEquals(get_admin()->id, $row->userid);
        $this->assertNotEmpty($row->policyversion);
        $this->assertSame(engine::VERSION, $row->engineversion);
        $this->assertGreaterThan(0, $row->timecreated);
        $this->assertEquals($r['fixid'], $row->id);

        $classes = array_map(fn($e) => get_class($e), $events);
        $this->assertContains(\core\event\course_module_updated::class, $classes);
        $applied = array_values(array_filter($events, fn($e) => $e instanceof \local_mooc_audit\event\correction_applied));
        $this->assertCount(1, $applied);
        $this->assertEquals($row->id, $applied[0]->objectid);
        $this->assertEquals($q->coursemodule, $applied[0]->contextinstanceid);
        $this->assertSame(['ruleid' => 'CL-13', 'field' => 'attempts', 'oldvalue' => '1', 'newvalue' => '2',
            'reaudit' => 'resolved'], $applied[0]->other);
    }

    /**
     * CL-14: close date = course end date; the calendar gets the close event; CL-14 conformant.
     */
    public function test_cl14_sets_close_date_and_calendar(): void {
        global $DB;
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 2, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $before = $this->quizrow($q);
        $this->assertFalse($DB->record_exists('event', ['modulename' => 'quiz', 'instance' => $q->instance,
            'eventtype' => 'close']));
        $this->waitForSecond();

        $r = $this->confirm($course, 'CL-14', $q->coursemodule);

        $this->assertSame(executor::APPLIED, $r['status'], $r['message']);
        $this->assertSame(verifier::RESOLVED, $r['reaudit']);
        $after = $this->quizrow($q);
        $this->assertSame(['timeclose', 'timemodified'], $this->changed($before, $after));
        $this->assertEquals($course->enddate, $after->timeclose);
        $this->assertSame((int) $course->enddate, (int) \mod_quiz\quiz_settings::create($q->instance)->get_quiz()->timeclose);
        $this->assertTrue($DB->record_exists('event', ['modulename' => 'quiz', 'instance' => $q->instance,
            'eventtype' => 'close', 'timestart' => $course->enddate]));
        $this->assertSame([status::CONFORME], $this->statuses_of($course, 'CL-14', $q->coursemodule));
        $this->assertSame('timeclose', $this->trail()[0]->field);
        $this->assertSame('0', $this->trail()[0]->oldvalue);
        $this->assertEquals($course->enddate, $this->trail()[0]->newvalue);
    }

    /**
     * Confirming again (reload, back button) writes nothing more.
     */
    public function test_repetition_is_harmless(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $first = (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
        $this->assertSame(executor::APPLIED, $first['status']);
        $row = $this->quizrow($q);
        $second = (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
        $this->assertSame(executor::NOTHING, $second['status']);
        $this->assertEquals($row, $this->quizrow($q));
        $this->assertCount(1, $this->trail());
    }

    /**
     * Any change between preview and confirmation (activity edited, course end date, policy, forged fingerprint)
     * stops the execution without writing.
     */
    public function test_conflicts_never_write(): void {
        global $DB;
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $changes = [
            'activity edited' => function () use ($DB, $q, $course) {
                $DB->set_field('quiz', 'name', 'Q renomeado', ['id' => $q->instance]);
                $DB->set_field('course_modules', 'visible', 0, ['id' => $q->coursemodule]);
                rebuild_course_cache($course->id, true);
            },
            'policy changed' => fn() => set_config('policyjson', '{"policy_version": "outra"}', 'local_mooc_audit'),
        ];
        foreach ($changes as $label => $change) {
            $p = $this->plan($course, 'CL-13', $q->coursemodule);
            $row = $this->quizrow($q);
            $change();
            $r = (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
            $this->assertSame(executor::CONFLICT, $r['status'], $label);
            $this->assertSame($row->attempts, $this->quizrow($q)->attempts, $label);
        }
        $r = (new executor())->execute($course->id, 'CL-13', $q->coursemodule, str_repeat('0', 64), $this->policy());
        $this->assertSame(executor::CONFLICT, $r['status'], 'forged fingerprint');

        // CL-14: the course end date changes after the preview.
        $p = $this->plan($course, 'CL-14', $q->coursemodule);
        $y = $this->year();
        update_course((object) ['id' => $course->id, 'startdate' => $course->startdate, 'enddate' => $this->ts("$y-12-31 22:00")]);
        $r = (new executor())->execute($course->id, 'CL-14', $q->coursemodule, $p->fingerprint(), $this->policy());
        $this->assertSame(executor::CONFLICT, $r['status']);
        $this->assertEquals(0, $this->quizrow($q)->timeclose);
        $this->assertSame([], $this->trail());
    }

    /**
     * A planner that returns the proposal of the preview, as if nothing had changed since (to reach the checks made
     * inside the write transaction).
     *
     * @param proposal $p
     * @return planner
     */
    private function stale_planner(proposal $p): planner {
        $planner = new class extends planner {
            /** @var proposal|null */
            public ?proposal $fixed = null;

            /**
             * The fixed proposal.
             *
             * @param int $courseid
             * @param string $ruleid
             * @param int $cmid
             * @param array|null $policyoverride
             * @return proposal|null
             */
            public function plan(int $courseid, string $ruleid, int $cmid, ?array $policyoverride = null): ?proposal {
                return $this->fixed;
            }
        };
        $planner->fixed = $p;
        return $planner;
    }

    /**
     * The value stored at write time differs from the confirmed one (edited in the same instant): conflict, rollback.
     */
    public function test_value_changed_inside_the_transaction(): void {
        global $DB;
        $this->preventResetByRollback();
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        // Simulate a concurrent edit that keeps every fingerprinted value (no cache rebuild, same timemodified).
        $DB->set_field('quiz', 'attempts', 3, ['id' => $q->instance]);
        $r = (new executor($this->stale_planner($p)))->execute(
            $course->id,
            'CL-13',
            $q->coursemodule,
            $p->fingerprint(),
            $this->policy()
        );
        $this->assertSame(executor::CONFLICT, $r['status']);
        $this->assertEquals(3, $this->quizrow($q)->attempts);
        $this->assertSame([], $this->trail());
    }

    /**
     * A student starts an attempt between the (re)planning and the write: the write transaction re-checks student data
     * and stops (CL-13 and CL-14).
     */
    public function test_student_attempt_appearing_before_the_write(): void {
        $this->preventResetByRollback();
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p13 = $this->plan($course, 'CL-13', $q->coursemodule);
        $p14 = $this->plan($course, 'CL-14', $q->coursemodule);
        $this->assertTrue($p13->is_executable() && $p14->is_executable());
        $this->student_attempt($course, $q);
        foreach ([$p13, $p14] as $p) {
            $r = (new executor($this->stale_planner($p)))->execute(
                $course->id,
                $p->ruleid,
                $q->coursemodule,
                $p->fingerprint(),
                $this->policy()
            );
            $this->assertSame(executor::CONFLICT, $r['status'], $p->ruleid);
        }
        $row = $this->quizrow($q);
        $this->assertEquals(1, $row->attempts);
        $this->assertEquals(0, $row->timeclose);
        $this->assertSame([], $this->trail());
    }

    /**
     * The activity was deleted after the preview.
     */
    public function test_activity_gone(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        course_delete_module($q->coursemodule);
        $r = (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
        $this->assertSame(executor::GONE, $r['status']);
        $this->assertSame([], $this->trail());
    }

    /**
     * Seeing a proposal does not allow applying it; the plugin capability does not replace the core one.
     */
    public function test_capabilities_are_checked_on_the_server(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $coursecontext = \context_course::instance($course->id);

        // Editing teacher allowed to prepare only.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $teacherrole = $this->getDataGenerator()->create_role();
        assign_capability('local/mooc_audit:preparecorrection', CAP_ALLOW, $teacherrole, $coursecontext);
        role_assign($teacherrole, $teacher->id, $coursecontext);
        $this->setUser($teacher);
        $this->assertFalse($this->plan($course, 'CL-13', $q->coursemodule)->canexecute);
        try {
            (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
            $this->fail('teacher without applycorrection');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/mooc_audit:applycorrection'), $e->getMessage());
        }

        // Plugin capability granted, core manageactivities prohibited on the activity.
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/mooc_audit:applycorrection', CAP_ALLOW, $role, $coursecontext);
        role_assign($role, $user->id, $coursecontext);
        $this->setUser($user);
        try {
            (new executor())->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
            $this->fail('no manageactivities');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('moodle/course:manageactivities'), $e->getMessage());
        }
        $this->assertEquals(1, $this->quizrow($q)->attempts);
        $this->assertSame([], $this->trail());
    }

    /**
     * A student attempt makes the proposal not executable: refused, nothing written. Guided proposals (CL-17) are
     * refused as well.
     */
    public function test_refused_with_student_data_and_for_guided_proposals(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $this->student_attempt($course, $q);
        $r = $this->confirm($course, 'CL-13', $q->coursemodule);
        $this->assertSame(executor::REFUSED, $r['status']);
        $this->assertNotEmpty($r['proposal']->blockers);
        $this->assertEquals(1, $this->quizrow($q)->attempts);

        $page = $this->page($course, 'Leitura', '<p>Texto.</p>', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $r = $this->confirm($course, 'CL-17', $page->coursemodule);
        $this->assertSame(executor::REFUSED, $r['status']);
        $this->assertSame([], $this->trail());
    }

    /**
     * A failure after the write inside the transaction: everything rolled back, failure recorded, no success.
     */
    public function test_failure_rolls_back_and_is_recorded(): void {
        $this->preventResetByRollback();
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $planner = new planner([new failing_write()]);
        $p = $planner->plan($course->id, 'CL-13', $q->coursemodule, $this->policy());
        $sink = $this->redirectEvents();
        $r = (new executor($planner))->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(executor::FAILED, $r['status']);
        $this->assertStringContainsString('simulated failure', $r['message']);
        $this->assertEquals(1, $this->quizrow($q)->attempts, 'rolled back');
        $rows = $this->trail();
        $this->assertCount(1, $rows);
        $this->assertSame(trail::FAILED, $rows[0]->outcome);
        $this->assertSame(verifier::SKIPPED, $rows[0]->reaudit);
        $this->assertStringContainsString('simulated failure', $rows[0]->details);
        $this->assertSame([], array_filter($events, fn($e) => $e instanceof \local_mooc_audit\event\correction_applied));
    }

    /**
     * The value cannot be read back through the module API: failure, rolled back, not success.
     */
    public function test_value_not_read_back_is_a_failure(): void {
        $this->preventResetByRollback();
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $planner = new planner([new unreadable_write()]);
        $p = $planner->plan($course->id, 'CL-13', $q->coursemodule, $this->policy());
        $r = (new executor($planner))->execute($course->id, 'CL-13', $q->coursemodule, $p->fingerprint(), $this->policy());
        $this->assertSame(executor::FAILED, $r['status']);
        $this->assertSame(get_string('fix_notpersisted', 'local_mooc_audit'), $r['message']);
        $this->assertSame(trail::FAILED, $this->trail()[0]->outcome);
        $this->assertEquals(1, $this->quizrow($q)->attempts, 'rolled back: nothing kept');
    }

    /**
     * The re-audit fails after a successful write: applied, re-audit "error", no claim that the problem is resolved.
     */
    public function test_reaudit_failure_is_reported(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $r = $this->confirm($course, 'CL-13', $q->coursemodule, new reaudit_fails_executor());
        $this->assertSame(executor::APPLIED, $r['status']);
        $this->assertSame(verifier::ERROR, $r['reaudit']);
        $this->assertNull($r['comparison']);
        $this->assertStringContainsString('simulated re-audit failure', $r['message']);
        $this->assertSame(verifier::ERROR, $this->trail()[0]->reaudit);
        $this->assertEquals(2, $this->quizrow($q)->attempts);
    }

    /**
     * Another correction of the same activity holds the lock: nothing is done.
     */
    public function test_busy_activity(): void {
        global $CFG;
        $CFG->lock_factory = '\core\lock\db_record_lock_factory';
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $lock = \core\lock\lock_config::get_lock_factory('local_mooc_audit')->get_lock('fix_' . $q->coursemodule, 0);
        $this->assertNotFalse($lock);
        try {
            $r = $this->confirm($course, 'CL-13', $q->coursemodule, new nowait_executor());
            $this->assertSame(executor::BUSY, $r['status']);
            $this->assertEquals(1, $this->quizrow($q)->attempts);
        } finally {
            $lock->release();
        }
        $this->assertSame(executor::APPLIED, $this->confirm($course, 'CL-13', $q->coursemodule, new nowait_executor())['status']);
    }
}
