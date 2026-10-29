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
use stdClass;

/**
 * Base class of the per-rule tests: builds courses through the API and runs a single rule through the engine.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class rule_testcase extends \advanced_testcase {
    /** @var int Reoffer year used by every scenario. */
    const YEAR = 2027;

    /**
     * Timestamp of a server-timezone date.
     *
     * @param string $date e.g. "2027-01-01 00:00"
     * @return int
     */
    protected function ts(string $date): int {
        return (new \DateTime($date, new \DateTimeZone(\core_date::get_server_timezone())))->getTimestamp();
    }

    /**
     * Plugin generator.
     *
     * @return \local_mooc_audit_generator
     */
    protected function gen(): \local_mooc_audit_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
    }

    /**
     * A course in the offer category, with completion enabled, dated 01/01–31/12 of the reoffer year.
     *
     * @param array $extra overrides for create_course
     * @return stdClass
     */
    protected function course(array $extra = []): stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        return $this->getDataGenerator()->create_course(array_merge([
            'enablecompletion' => 1, 'startdate' => $this->ts(self::YEAR . '-01-01 00:00'), 'enddate' => $this->ts(self::YEAR
                . '-12-31 23:59'),
            'category' => $this->gen()->target_category()->id, 'numsections' => 1, 'hiddensections' => 1,
        ], $extra));
    }

    /**
     * Results of one rule for a course (fresh audit).
     *
     * @param string $rule rule id
     * @param stdClass|int $course
     * @param int $userid viewer
     * @param array $policy override
     * @return array
     */
    protected function rule(string $rule, $course, int $userid = 0, array $policy = []): array {
        $courseid = is_object($course) ? $course->id : $course;
        rebuild_course_cache($courseid, true);
        $report = engine::audit($courseid, array_merge(['reoffer_year' => self::YEAR], $policy), $userid);
        return array_values(array_filter($report['results'], fn($r) => $r['rule'] === $rule));
    }

    /**
     * Statuses of a list of results.
     *
     * @param array $results
     * @return string[]
     */
    protected function statuses(array $results): array {
        return array_values(array_column($results, 'status'));
    }

    /**
     * Assert that every result has the given status (and that there is at least one).
     *
     * @param string $status
     * @param array $results
     * @param string $message
     */
    protected function assert_all_status(string $status, array $results, string $message = ''): void {
        $this->assertNotEmpty($results, $message);
        $this->assertSame(array_fill(0, count($results), $status), $this->statuses($results), $message . ' '
            . json_encode(array_map(fn($r) => [$r['status'], $r['local'], $r['evidencia']], $results), JSON_UNESCAPED_UNICODE));
    }

    /**
     * The results of a given status.
     *
     * @param array $results
     * @param string $status
     * @return array
     */
    protected function with(array $results, string $status): array {
        return array_values(array_filter($results, fn($r) => $r['status'] === $status));
    }

    /**
     * Every non conformant result carries location, evidence and guidance.
     *
     * @param array $results
     */
    protected function assert_nc_complete(array $results): void {
        foreach ($this->with($results, status::NAO_CONFORME) as $r) {
            $this->assertNotEmpty($r['local'], $r['rule']);
            $this->assertNotEmpty($r['evidencia'], $r['rule']);
            $this->assertNotEmpty($r['orientacao'], $r['rule']);
        }
    }

    /**
     * A page module.
     *
     * @param stdClass $course
     * @param string $name
     * @param string $html
     * @param array $extra
     * @return stdClass create_module info (coursemodule, instance)
     */
    protected function page(stdClass $course, string $name, string $html = '<p>Texto.</p>', array $extra = []): stdClass {
        return $this->gen()->add_module($course, 'page', 1, array_merge(['name' => $name, 'page' => ['text' => $html,
            'format' => FORMAT_HTML, 'itemid' => 0],
            'content' => $html, 'contentformat' => FORMAT_HTML, 'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0,
                'printlastmodified' => 0], $extra));
    }

    /**
     * A quiz with questions.
     *
     * @param stdClass $course
     * @param string $name
     * @param int $attempts
     * @param float $grade
     * @param int $timeclose
     * @param string $intro
     * @param int $questions
     * @param array $extra
     * @return stdClass create_module info
     */
    protected function quiz(
        stdClass $course,
        string $name,
        int $attempts = 2,
        float $grade = 50,
        int $timeclose = 0,
        string $intro = '',
        int $questions = 2,
        array $extra = []
    ): stdClass {
        $data = array_merge(['name' => $name, 'introeditor' => ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0],
            'attempts' => $attempts, 'grade' => $grade, 'grademethod' => QUIZ_GRADEHIGHEST, 'timeclose' => $timeclose,
                'timeopen' => 0, 'timelimit' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completiongradeitemnumber' => 0, 'completionusegrade' => 1,
                'quizpassword' => '',
            'preferredbehaviour' => 'deferredfeedback', 'questionsperpage' => 1, 'shuffleanswers' => 1, 'decimalpoints' => 2,
                'questiondecimalpoints' => -1,
            'overduehandling' => 'autosubmit', 'graceperiod' => 0, 'reviewattempt' => 69904, 'reviewcorrectness' => 4352,
                'reviewmarks' => 4352,
            'reviewspecificfeedback' => 4352, 'reviewgeneralfeedback' => 4352, 'reviewrightanswer' => 4352,
                'reviewoverallfeedback' => 4352,
            'reviewmaxmarks' => 69904], $extra);
        $q = $this->gen()->add_module($course, 'quiz', 1, $data);
        if ($questions) {
            $this->gen()->add_questions($q, $questions);
        }
        return $q;
    }

    /**
     * A feedback with one item.
     *
     * @param stdClass $course
     * @param string $name
     * @param int $completion
     * @param int $completionsubmit
     * @param bool $withitem
     * @return stdClass create_module info
     */
    protected function feedback(
        stdClass $course,
        string $name,
        int $completion = COMPLETION_TRACKING_AUTOMATIC,
        int $completionsubmit = 1,
        bool $withitem = true
    ): stdClass {
        $fb = $this->gen()->add_module($course, 'feedback', 1, ['name' => $name, 'anonymous' => 1,
            'completionsubmit' => $completionsubmit, 'completion' => $completion,
            'page_after_submit_editor' => ['text' => 'Obrigado', 'format' => FORMAT_HTML, 'itemid' => 0],
                'page_after_submit' => 'Obrigado',
            'page_after_submitformat' => FORMAT_HTML, 'site_after_submit' => '', 'multiple_submit' => 0, 'publish_stats' => 0,
                'email_notification' => 0]);
        if ($withitem) {
            $this->gen()->add_feedback_item($fb->instance);
        }
        return $fb;
    }

    /**
     * A simplecertificate module (the test is skipped when the plugin is absent).
     *
     * @param stdClass $course
     * @param string $name
     * @param array $extra
     * @return stdClass create_module info
     */
    protected function certificate(
        stdClass $course,
        string $name = 'Certificado digital de conclusão',
        array $extra = []
    ): stdClass {
        if (!\local_mooc_audit_generator::has_module('simplecertificate')) {
            $this->markTestSkipped('mod_simplecertificate not installed');
        }
        return $this->gen()->add_module($course, 'simplecertificate', 1, array_merge(['name' => $name,
            'certificatetext' => ['text' => 'Certificamos {USERNAME}.', 'format' => FORMAT_HTML],
                'secondpagetext' => ['text' => '', 'format' => FORMAT_HTML],
            'width' => 297, 'height' => 210, 'certificatetextx' => 40, 'certificatetexty' => 80, 'coursename' => '',
                'certdate' => -1, 'certgrade' => 0,
            'gradefmt' => 1, 'emailothers' => '', 'emailteachers' => 0, 'reportcert' => 0, 'delivery' => 0, 'requiredtime' => 0,
                'printqrcode' => 0,
            'qrcodefirstpage' => 0, 'codex' => 240, 'codey' => 150, 'enablesecondpage' => 0, 'secondpagex' => 40,
                'secondpagey' => 3, 'certdatefmt' => '',
            'timestartdatefmt' => '', 'outcome' => 0, 'certificateimage' => '', 'secondimage' => ''], $extra));
    }

    /**
     * Completion conditions tree.
     *
     * @param int[] $cmids
     * @param string $op
     * @param int $e expected completion state
     * @return array
     */
    protected function completion_tree(array $cmids, string $op = '&', int $e = 1): array {
        $tree = ['op' => $op, 'c' => array_map(fn($cm) => ['type' => 'completion', 'cm' => $cm, 'e' => $e], $cmids)];
        // Moodle stores "showc" (one flag per child) for AND trees and a single "show" for OR trees.
        if ($op === '&' || $op === '!|') {
            $tree['showc'] = array_fill(0, count($cmids), true);
        } else {
            $tree['show'] = true;
        }
        return $tree;
    }
}
