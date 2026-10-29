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
use local_mooc_audit\local\status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/correction_testcase.php');

/**
 * Correction proposals: eligibility, content, simulation, impediments and warnings per strategy.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\correction\planner
 * @covers     \local_mooc_audit\local\correction\strategy\quiz_attempts
 * @covers     \local_mooc_audit\local\correction\strategy\quiz_timeclose
 * @covers     \local_mooc_audit\local\correction\strategy\close_date_guidance
 * @covers     \local_mooc_audit\local\correction\strategy\completion_guidance
 */
final class planner_test extends correction_testcase {
    /**
     * Rules with strategies and eligibility of results.
     */
    public function test_rules_and_eligibility(): void {
        $this->assertSame(['CL-13', 'CL-14', 'CL-17'], planner::rules());
        $r = ['rule' => 'CL-13', 'cmid' => 5, 'status' => status::NAO_CONFORME];
        $this->assertTrue(planner::eligible($r));
        $this->assertTrue(planner::eligible(['status' => status::ATENCAO] + $r));
        $this->assertFalse(planner::eligible(['status' => status::CONFORME] + $r));
        $this->assertFalse(planner::eligible(['status' => status::NAO_VERIFICAVEL] + $r));
        $this->assertFalse(planner::eligible(['cmid' => null] + $r), 'no activity');
        $this->assertFalse(planner::eligible(['rule' => 'CL-12'] + $r), 'no strategy');
    }

    /**
     * CL-13, 1 attempt, statement "2": executable proposal with the nine elements; the simulation predicts that both
     * CL-13 findings of the quiz disappear and nothing new appears.
     */
    public function test_cl13_executable_proposal(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Avaliação do Tópico 1', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);

        $this->assertTrue($p->is_executable(), json_encode($p->blockers));
        $this->assertSame('quiz_attempts', $p->strategy);
        $this->assertSame(proposal::CATEGORY_EXECUTABLE, $p->category);
        $this->assertSame('attempts', $p->field);
        $this->assertSame(1, $p->current);
        $this->assertSame(2, $p->proposed);
        // The nine elements.
        $this->assertStringContainsString('tentativas permitidas=1', $p->problem);
        $this->assertStringContainsString('Avaliação do Tópico 1', $p->location);
        $this->assertStringContainsString('/course/modedit.php?update=' . $q->coursemodule, $p->editurl);
        $this->assertNotEmpty($p->ruletitle);
        $this->assertStringContainsString('decisão institucional 1', $p->rulesource);
        $this->assertSame('1', $p->currentdisplay);
        $this->assertSame('2', $p->proposeddisplay);
        $this->assertNotEmpty($p->justification);
        $this->assertNotEmpty($p->effect);
        $this->assertNotEmpty($p->dependencies, 'grading method unchanged');
        $this->assertSame(['local/mooc_audit:applycorrection', 'moodle/course:manageactivities'], $p->capabilities);
        $this->assertNotEmpty($p->roles);
        $this->assertTrue($p->canexecute, 'admin');
        // Simulation.
        $this->assertTrue($p->impact['targetresolved']);
        $this->assertCount(2, array_filter($p->impact['resolved'], fn($r) => str_starts_with($r, 'CL-13')));
        $this->assertSame([], $p->impact['introduced']);
        $this->assertSame([], $p->blockers);
        $this->assertSame([], $p->risks, 'no students, no override');
    }

    /**
     * CL-13 when changing only the attempts would leave the rule failing (statement declares 1): not executable.
     */
    public function test_cl13_statement_declares_current_value(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 1.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $this->assertFalse($p->is_executable());
        $this->assertFalse($p->impact['targetresolved']);
        $this->assertStringContainsString('enunciado declara 1', implode(' ', $p->blockers));
    }

    /**
     * Attempts already conformant, statement wrong: guided proposal (text), nothing to write.
     */
    public function test_cl13_statement_only_is_guided(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 2, 50, 0, '<p>Tentativas permitidas: 3.</p>');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $this->assertSame(proposal::CATEGORY_GUIDED, $p->category);
        $this->assertNull($p->field);
        $this->assertNull($p->impact);
        $this->assertFalse($p->is_executable());
    }

    /**
     * Policy "exact 2" with 3 attempts or unlimited: never lowered automatically.
     */
    public function test_cl13_never_reduces(): void {
        $course = $this->ccourse();
        $q3 = $this->quiz($course, 'Q3', 3, 50, 0, '<p>Tentativas permitidas: 3.</p>');
        $q0 = $this->quiz($course, 'Q0', 0, 50, 0, '<p>Tentativas: ilimitadas.</p>');
        foreach ([$q3, $q0] as $q) {
            $p = (new planner())->plan($course->id, 'CL-13', $q->coursemodule, $this->policy() + ['attempts_rule' => 'exact']);
            $this->assertSame(proposal::CATEGORY_GUIDED, $p->category);
            $this->assertNull($p->field);
            $this->assertFalse($p->is_executable());
            $this->assertNotEmpty($p->blockers);
        }
        // With the default policy ("at least 2") they are conformant: no proposal.
        $this->assertNull($this->plan($course, 'CL-13', $q3->coursemodule));
    }

    /**
     * Students: enrolled only → warning; an attempt → impediment; teacher previews do not count.
     */
    public function test_cl13_students(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $this->assertTrue($p->is_executable());
        $this->assertCount(1, $p->risks);
        $this->assertStringContainsString('1 enrolled student', $p->risks[0]);

        // A teacher preview is not student data.
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($q->instance, get_admin()->id);
        $this->assertTrue($this->plan($course, 'CL-13', $q->coursemodule)->is_executable(), 'preview ignored');

        $this->student_attempt($course, $q);
        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $this->assertFalse($p->is_executable());
        $this->assertStringContainsString('1 student attempt', implode(' ', $p->blockers));
        $this->assertSame(proposal::CATEGORY_EXECUTABLE, $p->category, 'still category A, blocked');
    }

    /**
     * CL-14 on a quiz without close date, course end conformant and in the future: executable, proposes the course end.
     */
    public function test_cl14_quiz_executable(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 2, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-14', $q->coursemodule);
        $this->assertTrue($p->is_executable(), json_encode($p->blockers, JSON_UNESCAPED_UNICODE));
        $this->assertSame('quiz_timeclose', $p->strategy);
        $this->assertSame('timeclose', $p->field);
        $this->assertSame(0, $p->current);
        $this->assertSame((int) $course->enddate, $p->proposed);
        $this->assertSame(['CL-14 — ' . \local_mooc_audit\local\helpers::label(['modname' => 'quiz', 'name' => 'Q',
            'cmid' => $q->coursemodule]) . ': data de encerramento não definida'], $p->impact['resolved']);
        $this->assertArrayHasKey('enddate', $p->state);
    }

    /**
     * CL-14 impediments: course end out of policy (CL-07), quiz opening after the course end.
     */
    public function test_cl14_impediments(): void {
        $y = $this->year();
        $course = $this->ccourse(['enddate' => $this->ts("$y-06-30 23:59")]);
        $q = $this->quiz($course, 'Q', 2, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $p = $this->plan($course, 'CL-14', $q->coursemodule);
        $this->assertFalse($p->is_executable());
        $this->assertStringContainsString('CL-07', implode(' ', $p->blockers));

        $course2 = $this->ccourse(['shortname' => 'c2']);
        $q2 = $this->quiz(
            $course2,
            'Q',
            2,
            50,
            0,
            '<p>Tentativas permitidas: 2.</p>',
            2,
            ['timeopen' => $this->ts(($y + 1) . '-01-10 00:00')]
        );
        $p = $this->plan($course2, 'CL-14', $q2->coursemodule);
        $this->assertFalse($p->is_executable());
        $this->assertStringContainsString('opening', implode(' ', $p->blockers));
    }

    /**
     * CL-14 on an assignment: guided, with the field of the type.
     */
    public function test_cl14_other_types_are_guided(): void {
        $course = $this->ccourse();
        $a = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'name' => 'Tarefa', 'grade' => 10,
            'duedate' => 0, 'cutoffdate' => 0]);
        $p = $this->plan($course, 'CL-14', $a->cmid);
        $this->assertSame('close_date_guidance', $p->strategy);
        $this->assertSame(proposal::CATEGORY_GUIDED, $p->category);
        $this->assertFalse($p->is_executable());
        $this->assertStringContainsString('Due date', $p->effect);
        $this->assertStringContainsString('assign', implode(' ', $p->blockers));
    }

    /**
     * CL-17: manual completion of a page required by the course evaluation, itself required by the certificate:
     * guided proposal listing the chain, the course completion criterion and the completion records.
     */
    public function test_cl17_guidance_lists_dependencies(): void {
        global $DB;
        $course = $this->ccourse();
        $page = $this->page($course, 'Leitura', '<p>Texto.</p>', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $eval = $this->feedback($course, 'Avaliação do curso');
        $this->gen()->set_availability($eval->coursemodule, ['op' => '&', 'showc' => [true],
            'c' => [['type' => 'completion', 'cm' => (int) $page->coursemodule, 'e' => 1]]]);
        if (\local_mooc_audit_generator::has_module('simplecertificate')) {
            $cert = $this->gen()->add_module($course, 'simplecertificate', 1, ['name' => 'Certificado digital de conclusão',
                'certificatetext' => ['text' => 'x', 'format' => FORMAT_HTML], 'certificatetextformat' => FORMAT_HTML,
                'secondpagetext' => ['text' => '', 'format' => FORMAT_HTML]]);
            $this->gen()->set_availability($cert->coursemodule, ['op' => '&', 'showc' => [true],
                'c' => [['type' => 'completion', 'cm' => (int) $eval->coursemodule, 'e' => 1]]]);
        }
        $DB->insert_record('course_completion_criteria', (object) ['course' => $course->id, 'criteriatype' => 4,
            'module' => 'page', 'moduleinstance' => $page->coursemodule]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $page->coursemodule,
            'userid' => $student->id, 'completionstate' => 1, 'timemodified' => time()]);

        $p = $this->plan($course, 'CL-17', $page->coursemodule);
        $this->assertSame('completion_guidance', $p->strategy);
        $this->assertSame(proposal::CATEGORY_GUIDED, $p->category);
        $this->assertFalse($p->is_executable());
        $this->assertStringContainsString('Manual', $p->currentdisplay);
        $this->assertStringContainsString('view the activity', $p->proposeddisplay);
        $this->assertStringContainsString('#id_activitycompletionheader', $p->editurl);
        $deps = implode(' | ', $p->dependencies);
        $this->assertStringContainsString('Avaliação do curso', $deps);
        $this->assertStringContainsString('course completion criterion', $deps);
        if (\local_mooc_audit_generator::has_module('simplecertificate')) {
            $this->assertStringContainsString('certificate', $deps);
        }
        $this->assertStringContainsString('1 student completion record', implode(' ', $p->risks));
    }

    /**
     * Nothing to correct → null; unknown rule or activity → exception.
     */
    public function test_nothing_and_errors(): void {
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 2, 50, (int) $course->enddate, '<p>Tentativas permitidas: 2.</p>');
        $this->assertNull($this->plan($course, 'CL-13', $q->coursemodule));
        $this->assertNull($this->plan($course, 'CL-14', $q->coursemodule));
        try {
            $this->plan($course, 'CL-12', $q->coursemodule);
            $this->fail('rule without strategy');
        } catch (\moodle_exception $e) {
            $this->assertSame('fix_norule', $e->errorcode);
        }
        try {
            $this->plan($course, 'CL-13', 999999);
            $this->fail('unknown activity');
        } catch (\moodle_exception $e) {
            $this->assertSame('fix_nomodule', $e->errorcode);
        }
    }
}
