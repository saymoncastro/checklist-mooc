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
 * Assisted correction of one finding (specs/002): GET shows the proposal (never writes); POST with sesskey and the
 * fingerprint of the previewed state confirms it.
 *
 * Parameters: courseid, rule, cmid; on confirmation also confirm=1 and fingerprint.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_mooc_audit\local\correction\executor;
use local_mooc_audit\local\correction\planner;

$courseid = required_param('courseid', PARAM_INT);
$ruleid = required_param('rule', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course || $course->id == SITEID) {
    throw new moodle_exception('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/mooc_audit:preparecorrection', $context);
if (!in_array($ruleid, planner::rules(), true)) {
    throw new moodle_exception('fix_norule', 'local_mooc_audit', '', s($ruleid));
}

$url = new moodle_url('/local/mooc_audit/correction.php', ['courseid' => $course->id, 'rule' => $ruleid, 'cmid' => $cmid]);
$reporturl = new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('fix_pagetitle', 'local_mooc_audit', format_string($course->fullname)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('auditmenu', 'local_mooc_audit'), $reporturl);
$PAGE->navbar->add(get_string('fix_heading', 'local_mooc_audit'));
$renderer = $PAGE->get_renderer('local_mooc_audit');

$cm = get_fast_modinfo($course)->cms[$cmid] ?? null;
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if ($confirm && data_submitted()) {
    // Confirmation: POST only, session key, capabilities re-checked by the executor; values come from the database.
    require_sesskey();
    require_capability('local/mooc_audit:applycorrection', $context);
    $fingerprint = required_param('fingerprint', PARAM_ALPHANUM);
    $result = (new executor())->execute($course->id, $ruleid, $cmid, $fingerprint);
    echo $OUTPUT->header();
    echo $renderer->render(new \local_mooc_audit\output\correction_result($result, $reporturl, $url));
    echo $OUTPUT->footer();
    exit;
}

// Preview (GET, or a POST without confirmation): read only.
echo $OUTPUT->header();
if (!$cm || $cm->deletioninprogress) {
    echo $OUTPUT->notification(get_string('fix_nomodule', 'local_mooc_audit'), 'error');
    echo $OUTPUT->continue_button($reporturl);
} else {
    $proposal = (new planner())->plan($course->id, $ruleid, $cmid);
    echo $renderer->render(new \local_mooc_audit\output\correction_proposal($proposal, $url, $reporturl));
}
echo $OUTPUT->footer();
