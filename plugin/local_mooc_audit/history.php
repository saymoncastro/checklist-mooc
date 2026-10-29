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
 * Audit history of a course (specs/005): list of the recorded audits, one stored audit with its evolution.
 *
 * Parameters: courseid (required); runid (one run); baseid (run to compare with; default: the previous run);
 * page; format=html|json (with runid). Requires local/mooc_audit:audit in the course. The stored history of a removed
 * course stays readable for holders of local/mooc_audit:viewdashboard. Nothing is recalculated nor written.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_mooc_audit\local\history\evolution;
use local_mooc_audit\local\history\policy_store;
use local_mooc_audit\local\history\run_store;

$courseid = required_param('courseid', PARAM_INT);
$runid = optional_param('runid', 0, PARAM_INT);
$baseid = optional_param('baseid', 0, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$course = $courseid != SITEID ? $DB->get_record('course', ['id' => $courseid]) : false;
$url = new moodle_url('/local/mooc_audit/history.php', array_filter(['courseid' => $courseid, 'runid' => $runid]));
if ($course) {
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('local/mooc_audit:audit', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $heading = format_string($course->fullname);
    $auditurl = new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
} else {
    // Removed course: its stored history is only for the dashboard viewers.
    require_login(null, false);
    $context = context_system::instance();
    require_capability('local/mooc_audit:viewdashboard', $context);
    if ($courseid == SITEID || !run_store::count_for_course($courseid)) {
        throw new moodle_exception('invalidcourseid');
    }
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('admin');
    $heading = get_string('history', 'local_mooc_audit');
    $auditurl = null;
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('history', 'local_mooc_audit') . ': ' . $heading);
$PAGE->set_heading($heading);
$renderer = $PAGE->get_renderer('local_mooc_audit');

if (!$runid) {
    $runs = run_store::for_course($courseid, $page * $perpage, $perpage);
    $policies = [];
    $ids = array_unique(array_filter(array_map(fn($r) => (int) $r->policyid, $runs)));
    if ($ids) {
        $policies = $DB->get_records_list(policy_store::TABLE, 'id', $ids, '', 'id, fingerprint, policyversion');
    }
    $paging = $OUTPUT->paging_bar(run_store::count_for_course($courseid), $page, $perpage,
        new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid]));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('history', 'local_mooc_audit'));
    if (!$course) {
        echo $OUTPUT->notification(get_string('history_removed_course', 'local_mooc_audit', $courseid),
            \core\output\notification::NOTIFY_WARNING);
    }
    echo $renderer->render(new \local_mooc_audit\output\history_page($courseid, $runs, $policies, $paging, $auditurl));
    echo $OUTPUT->footer();
    exit;
}

$run = run_store::get($runid);
if (!$run || (int) $run->courseid !== $courseid) {
    throw new moodle_exception('invalidrecord', 'error', '', run_store::TABLE);
}
$valid = in_array($run->status, run_store::valid_states(), true);
$base = null;
if ($valid) {
    $candidate = $baseid ? run_store::get($baseid) : ($run->previousrunid ? run_store::get((int) $run->previousrunid) : null);
    if ($candidate && (int) $candidate->courseid === $courseid && (int) $candidate->id < (int) $run->id
            && in_array($candidate->status, run_store::valid_states(), true)) {
        $base = $candidate;
    }
}
$report = run_store::json($run, 'report');
$cmp = null;
$corrections = [];
if ($base) {
    $cmp = evolution::compare(
        ['report' => run_store::json($base, 'report') ?? [], 'details' => run_store::json($base, 'details') ?? [],
            'pluginversion' => $base->pluginversion],
        ['report' => $report ?? [], 'details' => run_store::json($run, 'details') ?? [], 'pluginversion' => $run->pluginversion]
    );
    $corrections = evolution::corrections_between($courseid, (int) $base->timefinished,
        (int) ($run->timestarted ?: $run->timecreated));
}
$policy = $run->policyid ? policy_store::get((int) $run->policyid) : null;

if ($format === 'json') {
    $meta = (array) $run;
    unset($meta['report'], $meta['details']);
    foreach (['summary', 'evolution', 'errors'] as $k) {
        $meta[$k] = run_store::json($run, $k);
    }
    $meta['policy'] = $policy ? ['version' => $policy->policyversion, 'fingerprint' => $policy->fingerprint,
        'content' => json_decode($policy->policy, true)] : null;
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['run' => $meta, 'base' => $base ? (int) $base->id : null, 'evolution' => $cmp,
        'corrections' => array_values($corrections), 'report' => $report],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$baseoptions = [];
if ($valid) {
    [$in, $params] = $DB->get_in_or_equal(run_store::valid_states(), SQL_PARAMS_NAMED);
    $params += ['courseid' => $courseid, 'runid' => $run->id];
    $earlier = $DB->get_records_select(run_store::TABLE, "courseid = :courseid AND id < :runid AND status $in", $params,
        'id DESC', 'id, timefinished', 0, 50);
    foreach ($earlier as $e) {
        $baseoptions[(int) $e->id] = '#' . $e->id . ' — ' . \local_mooc_audit\output\history_helper::date((int) $e->timefinished);
    }
}
$reporthtml = $report ? $renderer->render(new \local_mooc_audit\output\report(
    $report,
    $auditurl ?? new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid])
)) : '';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('history', 'local_mooc_audit') . ': ' . get_string('label_runid', 'local_mooc_audit')
    . ' ' . $run->id);
echo $renderer->render(new \local_mooc_audit\output\run_page($run, $base, $cmp, $corrections, $policy, $reporthtml,
    $baseoptions));
echo $OUTPUT->footer();
