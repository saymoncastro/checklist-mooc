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
use local_mooc_audit\output\correction_proposal;
use local_mooc_audit\output\correction_result;
use local_mooc_audit\output\report;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/correction_testcase.php');

/**
 * Correction pages: action in the report, preview with or without the confirmation form, outcome messages.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\output\correction_proposal
 * @covers     \local_mooc_audit\output\correction_result
 * @covers     \local_mooc_audit\output\report
 */
final class output_test extends correction_testcase {
    /**
     * The report offers "See correction proposal" only on eligible results and only when asked to.
     */
    public function test_report_action(): void {
        global $PAGE;
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $url = new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
        $audit = engine::audit($course->id, $this->policy());
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        $proposal = get_string('fix_seeproposal', 'local_mooc_audit');

        $this->assertStringNotContainsString($proposal, $renderer->render(new report($audit, $url)));
        $html = $renderer->render(new report($audit, $url, true));
        $this->assertStringContainsString($proposal, $html);
        $this->assertStringContainsString('correction.php?courseid=' . $course->id . '&amp;rule=CL-13&amp;cmid='
            . $q->coursemodule, $html);
        $this->assertStringNotContainsString('rule=CL-12', $html, 'no strategy, no action');
        $this->assertStringNotContainsString('correction', engine::to_json($audit), 'contract 1.0 unchanged');
    }

    /**
     * Executable proposal: form with sesskey and fingerprint for a user who may apply; no form for a user who may
     * only see; guided proposal: link to the settings page, no form.
     */
    public function test_proposal_page(): void {
        global $PAGE;
        $course = $this->ccourse();
        $q = $this->quiz($course, 'Q', 1, 50, 0, '<p>Tentativas permitidas: 2.</p>');
        $page = $this->page($course, 'Leitura', '<p>Texto.</p>', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $url = new \moodle_url('/local/mooc_audit/correction.php');
        $renderer = $PAGE->get_renderer('local_mooc_audit');

        $p = $this->plan($course, 'CL-13', $q->coursemodule);
        $html = $renderer->render(new correction_proposal($p, $url, $url));
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        $this->assertStringContainsString('name="fingerprint" value="' . $p->fingerprint() . '"', $html);
        $this->assertStringContainsString(get_string('fix_confirm', 'local_mooc_audit'), $html);
        foreach (
            ['fix_problem', 'fix_location', 'fix_rule', 'fix_current', 'fix_proposed', 'fix_justification', 'fix_impact',
                'fix_risks', 'fix_authorised'] as $label
        ) {
            $this->assertStringContainsString(get_string($label, 'local_mooc_audit'), $html, $label);
        }
        $noundo = s(get_string('fix_noundo', 'local_mooc_audit'));
        $this->assertStringContainsString($noundo, $html);

        $p->canexecute = false;
        $html = $renderer->render(new correction_proposal($p, $url, $url));
        $this->assertStringNotContainsString('name="fingerprint"', $html);
        $this->assertStringContainsString(get_string('fix_cannotexecute', 'local_mooc_audit'), $html);

        $g = $this->plan($course, 'CL-17', $page->coursemodule);
        $html = $renderer->render(new correction_proposal($g, $url, $url));
        $this->assertStringNotContainsString('method="post"', $html);
        $this->assertStringContainsString(get_string('fix_mode_guided', 'local_mooc_audit'), $html);
        $this->assertStringContainsString('modedit.php?update=' . $page->coursemodule, $html);
        // Nothing is written nor recorded by a guided proposal: no "previous value is recorded" notice (B-11).
        $this->assertStringNotContainsString($noundo, $html);

        $html = $renderer->render(new correction_proposal(null, $url, $url));
        $this->assertStringContainsString(get_string('fix_nothing', 'local_mooc_audit'), $html);
    }

    /**
     * Success message only for applied + resolved; every other outcome says nothing (or not everything) was achieved.
     */
    public function test_result_messages(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        $url = new \moodle_url('/local/mooc_audit/index.php');
        $base = ['proposal' => null, 'reaudit' => verifier::SKIPPED, 'comparison' => null, 'message' => '', 'fixid' => null];
        $cases = [
            [executor::APPLIED, verifier::RESOLVED, 'fix_result_applied_resolved', true],
            [executor::APPLIED, verifier::NOT_RESOLVED, 'fix_result_applied_not_resolved', false],
            [executor::APPLIED, verifier::INTRODUCED, 'fix_result_applied_introduced', false],
            [executor::APPLIED, verifier::ERROR, 'fix_result_applied_error', false],
            [executor::FAILED, verifier::SKIPPED, 'fix_result_failed', false],
            [executor::CONFLICT, verifier::SKIPPED, 'fix_result_conflict', false],
            [executor::REFUSED, verifier::SKIPPED, 'fix_result_refused', false],
            [executor::NOTHING, verifier::SKIPPED, 'fix_result_nothing', false],
            [executor::BUSY, verifier::SKIPPED, 'fix_result_busy', false],
            [executor::GONE, verifier::SKIPPED, 'fix_result_gone', false],
        ];
        foreach ($cases as [$status, $reaudit, $key, $success]) {
            $html = $renderer->render(new correction_result(['status' => $status, 'reaudit' => $reaudit] + $base, $url, $url));
            $this->assertStringContainsString(get_string($key, 'local_mooc_audit'), $html, $key);
            $this->assertSame($success, str_contains($html, 'alert-success'), $key);
        }
    }
}
