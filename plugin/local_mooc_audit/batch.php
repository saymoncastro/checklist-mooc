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
 * Batch audits (specs/005 US3): new batch (preview, confirm), creation from the dashboard selection, progress of a
 * batch and re-run of its failures.
 *
 * GET id=B: progress (local/mooc_audit:viewdashboard). GET without id: new batch form (local/mooc_audit:batchaudit).
 * POST action=create (mode=selection, courseids[]) or action=retry (id): sesskey + local/mooc_audit:batchaudit.
 * Every course is checked against local/mooc_audit:audit of the requester; the background tasks check again.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_mooc_audit\local\history\batch_manager;

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$url = new moodle_url('/local/mooc_audit/batch.php', array_filter(['id' => $id]));
admin_externalpage_setup('local_mooc_audit_dashboard', '', null, $url);
$system = context_system::instance();
$PAGE->set_title(get_string('batchmenu', 'local_mooc_audit'));
$PAGE->set_heading(get_string('batchmenu', 'local_mooc_audit'));
$dashboardurl = new moodle_url('/local/mooc_audit/dashboard.php');

/**
 * Message of a batch creation outcome.
 *
 * @param array $out batch_manager::create() output
 * @return array [message, type]
 */
function local_mooc_audit_batch_message(array $out): array {
    if ($out['error'] === 'toomany') {
        return [get_string('batch_error_toomany', 'local_mooc_audit', batch_manager::max_courses()),
            \core\output\notification::NOTIFY_ERROR];
    }
    if ($out['error']) {
        return [get_string('batch_error_nothing_to_audit', 'local_mooc_audit'), \core\output\notification::NOTIFY_ERROR];
    }
    return [get_string('batch_created', 'local_mooc_audit', (object) ['queued' => $out['queued'], 'reused' => $out['reused']]),
        \core\output\notification::NOTIFY_SUCCESS];
}

if ($action === 'create' || $action === 'retry') {
    require_sesskey();
    require_capability('local/mooc_audit:batchaudit', $system);
    if ($action === 'retry') {
        $batch = batch_manager::get($id) ?: throw new moodle_exception('invalidrecord', 'error', '', batch_manager::TABLE);
        $n = batch_manager::retry_failed($batch, (int) $USER->id);
        redirect(
            new moodle_url('/local/mooc_audit/batch.php', ['id' => $batch->id]),
            get_string('batch_retried', 'local_mooc_audit', $n),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $out = batch_manager::create(['mode' => batch_manager::MODE_SELECTION,
        'courseids' => optional_param_array('courseids', [], PARAM_INT)], (int) $USER->id);
    [$message, $type] = local_mooc_audit_batch_message($out);
    redirect(
        $out['batchid'] ? new moodle_url('/local/mooc_audit/batch.php', ['id' => $out['batchid']]) : $dashboardurl,
        $message,
        null,
        $type
    );
}

$renderer = $PAGE->get_renderer('local_mooc_audit');
if ($id) {
    $batch = batch_manager::get($id) ?: throw new moodle_exception('invalidrecord', 'error', '', batch_manager::TABLE);
    $runs = batch_manager::runs($batch);
    $courses = [];
    if ($runs) {
        $records = $DB->get_records_list('course', 'id', array_keys($runs), '', 'id, shortname, fullname');
        foreach ($records as $c) {
            if (has_capability('local/mooc_audit:audit', context_course::instance($c->id))) {
                $courses[(int) $c->id] = $c;
            }
        }
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('batch_title', 'local_mooc_audit', format_string($batch->name)));
    echo $renderer->render(new \local_mooc_audit\output\batch_page(
        $batch,
        $runs,
        $courses,
        batch_manager::progress($batch),
        has_capability('local/mooc_audit:batchaudit', $system)
    ));
    echo $OUTPUT->footer();
    exit;
}

require_capability('local/mooc_audit:batchaudit', $system);
$form = new \local_mooc_audit\form\batch_form($url);
if ($form->is_cancelled()) {
    redirect($dashboardurl);
}
$preview = null;
if ($data = $form->get_data()) {
    $criteria = (array) $data;
    if (empty($data->previewbutton)) {
        $out = batch_manager::create($criteria, (int) $USER->id, (string) $data->name);
        [$message, $type] = local_mooc_audit_batch_message($out);
        if ($out['batchid']) {
            redirect(new moodle_url('/local/mooc_audit/batch.php', ['id' => $out['batchid']]), $message, null, $type);
        }
        $preview = ['message' => [$message, $type]] + batch_manager::resolve($criteria, (int) $USER->id);
    } else {
        $preview = batch_manager::resolve($criteria, (int) $USER->id);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('batch_new', 'local_mooc_audit'));
echo html_writer::tag('p', get_string('batch_mode_help', 'local_mooc_audit'));
if ($preview) {
    if (!empty($preview['message'])) {
        echo $OUTPUT->notification($preview['message'][0], $preview['message'][1]);
    } else if ($preview['toomany']) {
        echo $OUTPUT->notification(
            get_string('batch_error_toomany', 'local_mooc_audit', batch_manager::max_courses()),
            \core\output\notification::NOTIFY_ERROR
        );
    }
    echo $OUTPUT->heading(get_string('batch_accepted', 'local_mooc_audit', count($preview['accepted'])), 4);
    $items = array_map(
        fn($c) => s($c->shortname) . ' — ' . format_string($c->fullname) . ' (id ' . $c->id . ')',
        $preview['accepted']
    );
    echo html_writer::alist($items, ['data-region' => 'batch-preview']);
    if ($preview['skipped']) {
        echo $OUTPUT->heading(get_string('batch_skipped', 'local_mooc_audit', count($preview['skipped'])), 4);
        echo html_writer::alist(array_map(fn($s) => 'id ' . $s['courseid'] . ': '
            . get_string('batch_skip_' . $s['reason'], 'local_mooc_audit', $s['runid'] ?? null), $preview['skipped']));
    }
}
$form->display();
echo $OUTPUT->footer();
