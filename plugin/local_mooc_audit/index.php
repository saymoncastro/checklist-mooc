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

/**
 * Technical audit of a course, in the course context (specs/001; decision 12.1 of specs/005).
 *
 * GET shows the result of the last recorded audit of the course, read-only: opening or reloading the page never runs
 * nor records anything. "Run audit" / "Run again" (POST with sesskey, action=run) runs a new audit, records it in the
 * history and redirects back to the page. format=json returns the last recorded report (contract 1.1 + run), or 404
 * when the course has never been audited. The course itself is never modified.
 *
 * Parameters: courseid (or id), required; format=html|json; action=run (POST).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    $courseid = optional_param('id', 0, PARAM_INT);
}
$format = optional_param('format', 'html', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

if (!$courseid) {
    // Compatibility entry (admin settings link): ask for the course id.
    require_login();
    $PAGE->set_url(new moodle_url('/local/mooc_audit/index.php'));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('auditmenu', 'local_mooc_audit'));
    $PAGE->set_heading(get_string('auditmenu', 'local_mooc_audit'));
    echo $OUTPUT->header();
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-3']);
    echo html_writer::label(get_string('courseid', 'local_mooc_audit'), 'courseid', true, ['class' => 'mr-2']);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'courseid', 'id' => 'courseid', 'min' => 1,
        'required' => 'required',
        'class' => 'form-control mr-2']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string(
        'runaudit',
        'local_mooc_audit'
    )]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course || $course->id == SITEID) {
    throw new moodle_exception('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/mooc_audit:audit', $context);

$url = new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pagetitle', 'local_mooc_audit', format_string($course->fullname)));
$PAGE->set_heading(format_string($course->fullname));

use local_mooc_audit\local\history\latest;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

if ($action === 'run') {
    // Explicit request: run the audit (configuration view, no test viewer) and record it.
    require_sesskey();
    [$run, $report, $error] = recorder::record_now((int) $course->id, run_store::ORIGIN_PAGE, (int) $USER->id);
    if ($report) {
        redirect(
            $url,
            get_string('audit_recorded', 'local_mooc_audit', (int) $run->id),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $message = $error ? get_class($error) . ': ' . $error->getMessage() : get_string('invalidcourseid', 'error');
    redirect($url, get_string('audit_failed', 'local_mooc_audit', s($message)), null, \core\output\notification::NOTIFY_ERROR);
}

// Read-only from here: the last recorded audit (complete or partial) and, when newer, the last failed attempt.
if ($format === 'json') {
    $run = run_store::latest_valid((int) $course->id);
    $report = $run ? run_store::json($run, 'report') : null;
    @header('Content-Type: application/json; charset=utf-8');
    if (!$report) {
        http_response_code(404);
        echo json_encode(['run' => null, 'error' => get_string('audit_none', 'local_mooc_audit')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $report['run'] = ['id' => (int) $run->id, 'status' => $run->status, 'executed_at' => date('c', (int) $run->timefinished),
        'previousrunid' => (int) $run->previousrunid, 'evolution' => run_store::json($run, 'evolution')];
    echo \local_mooc_audit\engine::to_json($report);
    exit;
}

// The page does not show the pending run: no need to read the task queue.
$latest = latest::for_course($course, null, false);
$run = $latest['valid'];
$report = $run ? run_store::json($run, 'report') : null;
$renderer = $PAGE->get_renderer('local_mooc_audit');
$runurl = new moodle_url($url, ['action' => 'run']);
// The chosen view may set its cookie: before any output.
$view = \local_mooc_audit\output\report::remembered_view();
echo $OUTPUT->header();
if (!$run) {
    echo $OUTPUT->heading(get_string('auditmenu', 'local_mooc_audit'));
    echo $OUTPUT->notification(get_string('audit_none', 'local_mooc_audit'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->single_button(
        $runurl,
        get_string('runaudit', 'local_mooc_audit'),
        'post',
        ['type' => single_button::BUTTON_PRIMARY]
    );
} else {
    echo $renderer->render(new \local_mooc_audit\output\run_summary(
        $run,
        run_store::count_for_course((int) $course->id),
        $latest['stale'],
        $latest['lastfailure']
    ));
    $withcorrections = has_capability('local/mooc_audit:preparecorrection', $context);
    $compareurl = has_capability('local/mooc_audit:compare', $context)
        ? new moodle_url('/local/mooc_audit/compare.php', ['target' => $course->id]) : null;
    // Texts are built at display time with the policy the run applied (specs/006, research R2).
    $stored = $run->policyid ? \local_mooc_audit\local\history\policy_store::get((int) $run->policyid) : null;
    $runpolicy = $stored ? json_decode((string) $stored->policy, true) : null;
    echo $renderer->render((new \local_mooc_audit\output\report(
        $report,
        $url,
        $withcorrections,
        $compareurl,
        null,
        is_array($runpolicy) ? $runpolicy : null
    ))->set_view($view));
}
echo $OUTPUT->footer();
