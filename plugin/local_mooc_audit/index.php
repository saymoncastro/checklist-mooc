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
 * Technical audit of a course: read-only report page, in the course context.
 *
 * Every audit opened here is recorded in the history (specs/005): the report shows the evolution since the previous
 * recorded audit and links to the history of the course. The course itself is never modified.
 *
 * Parameters: courseid (or id), required; format=html|json.
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

// Configuration view (no test viewer): rules ignore per-user availability, so the result does not depend on who
// opens the page. The audit is recorded (specs/005); a failure outside the rules is recorded too, then shown.
[$run, $report, $error] = \local_mooc_audit\local\history\recorder::record_now(
    (int) $course->id,
    \local_mooc_audit\local\history\run_store::ORIGIN_PAGE,
    (int) $USER->id
);
if ($error) {
    throw $error;
}
if (!$report) {
    throw new moodle_exception('invalidcourseid');
}

if ($format === 'json') {
    $evolution = \local_mooc_audit\local\history\run_store::json($run, 'evolution');
    $report['run'] = ['id' => (int) $run->id, 'status' => $run->status, 'previousrunid' => (int) $run->previousrunid,
        'evolution' => $evolution];
    @header('Content-Type: application/json; charset=utf-8');
    echo \local_mooc_audit\engine::to_json($report);
    exit;
}

$renderer = $PAGE->get_renderer('local_mooc_audit');
echo $OUTPUT->header();
echo $renderer->render(new \local_mooc_audit\output\run_summary(
    $run,
    \local_mooc_audit\local\history\run_store::count_for_course((int) $course->id)
));
$withcorrections = has_capability('local/mooc_audit:preparecorrection', $context);
$compareurl = has_capability('local/mooc_audit:compare', $context)
    ? new moodle_url('/local/mooc_audit/compare.php', ['target' => $course->id]) : null;
echo $renderer->render(new \local_mooc_audit\output\report($report, $url, $withcorrections, $compareurl));
echo $OUTPUT->footer();
