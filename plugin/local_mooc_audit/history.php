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

$url = new moodle_url('/local/mooc_audit/history.php', array_filter(['courseid' => $courseid, 'runid' => $runid]));
if ($courseid != SITEID && $DB->record_exists('course', ['id' => $courseid])) {
    require_login($courseid);
} else {
    require_login(null, false);
}
[$course, $context] = \local_mooc_audit\local\history\access::require_history($courseid);
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_pagelayout('incourse');
    $heading = format_string($course->fullname);
    $auditurl = new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
} else {
    // Removed course: its stored history is only for the dashboard viewers.
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
    $paging = $OUTPUT->paging_bar(
        run_store::count_for_course($courseid),
        $page,
        $perpage,
        new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid])
    );
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('history', 'local_mooc_audit'));
    if (!$course) {
        echo $OUTPUT->notification(
            get_string('history_removed_course', 'local_mooc_audit', $courseid),
            \core\output\notification::NOTIFY_WARNING
        );
    }
    echo $renderer->render(new \local_mooc_audit\output\history_page($courseid, $runs, $policies, $paging, $auditurl));
    echo $OUTPUT->footer();
    exit;
}

$run = \local_mooc_audit\local\history\access::run_of_course($runid, $courseid);
$valid = in_array($run->status, run_store::valid_states(), true);
$base = evolution::base_for($run, $baseid);
$report = run_store::json($run, 'report');
$cmp = null;
$corrections = [];
if ($base) {
    $cmp = evolution::between($base, $run);
    // The specs/002 trail (values and who corrected) is shown to whoever may see correction proposals.
    if (has_capability('local/mooc_audit:preparecorrection', $context)) {
        $corrections = evolution::corrections_between(
            $courseid,
            (int) $base->timefinished,
            (int) ($run->timestarted ?: $run->timecreated)
        );
    }
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
    echo json_encode(
        ['run' => $meta, 'base' => $base ? (int) $base->id : null, 'evolution' => $cmp,
        'corrections' => array_values($corrections), 'report' => $report],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$baseoptions = [];
if ($valid) {
    [$in, $params] = $DB->get_in_or_equal(run_store::valid_states(), SQL_PARAMS_NAMED);
    $params += ['courseid' => $courseid, 'runid' => $run->id, 'started' => (int) ($run->timestarted ?: $run->timecreated)];
    $earlier = $DB->get_records_select(
        run_store::TABLE,
        "courseid = :courseid AND id <> :runid AND timefinished <= :started AND status $in",
        $params,
        'timefinished DESC, id DESC',
        'id, timefinished',
        0,
        50
    );
    foreach ($earlier as $e) {
        $baseoptions[(int) $e->id] = '#' . $e->id . ' — ' . \local_mooc_audit\output\history_helper::date((int) $e->timefinished);
    }
}
// The stored report is a record: no re-run button, and its JSON link returns the stored run (never a new audit).
$runurl = new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid, 'runid' => $run->id]);
$reporthtml = $report ? $renderer->render(new \local_mooc_audit\output\report(
    $report,
    $runurl,
    false,
    null,
    new moodle_url($runurl, ['format' => 'json']),
    $policy && is_array($runpolicy = json_decode((string) $policy->policy, true)) ? $runpolicy : null
)) : '';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('history', 'local_mooc_audit') . ': ' . get_string('label_runid', 'local_mooc_audit')
    . ' ' . $run->id);
echo $renderer->render(new \local_mooc_audit\output\run_page(
    $run,
    $base,
    $cmp,
    $corrections,
    $policy,
    $reporthtml,
    $baseoptions,
    has_capability('local/mooc_audit:preparecorrection', $context)
));
echo $OUTPUT->footer();
