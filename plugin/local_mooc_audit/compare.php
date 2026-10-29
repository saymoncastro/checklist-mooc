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
 * Compare two offers of a course (specs/003): selection form and read-only comparison report.
 *
 * Parameters (GET): source, target (course ids), confirm=1 (order confirmed), pair[]=SRC-TGT (manual matches, not
 * persisted), format=html|json. The selection form is posted (with sesskey) and redirects to the GET URL, so a
 * comparison is reproducible from its URL. local/mooc_audit:compare is required in BOTH course contexts.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$sourceid = optional_param('source', 0, PARAM_INT);
$targetid = optional_param('target', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$format = optional_param('format', 'html', PARAM_ALPHA);
$rawpairs = optional_param_array('pair', [], PARAM_ALPHANUMEXT);

require_login(null, false);
$system = context_system::instance();
$params = array_filter(['source' => $sourceid, 'target' => $targetid]);
$url = new moodle_url('/local/mooc_audit/compare.php', $params);
if (has_capability('local/mooc_audit:compare', $system)) {
    admin_externalpage_setup('local_mooc_audit_compare', '', null, $url);
} else {
    // A category manager may compare the courses of their categories: the page needs the capability in some course.
    if (!get_user_capability_course('local/mooc_audit:compare', null, true, '', '', 1)) {
        throw new required_capability_exception($system, 'local/mooc_audit:compare', 'nopermissions', '');
    }
    $PAGE->set_context($system);
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('admin');
}
$PAGE->set_title(get_string('comparemenu', 'local_mooc_audit'));
$PAGE->set_heading(get_string('comparemenu', 'local_mooc_audit'));

$form = new \local_mooc_audit\form\compare_form(new moodle_url('/local/mooc_audit/compare.php'));
if ($data = $form->get_data()) {
    redirect(new moodle_url('/local/mooc_audit/compare.php', ['source' => (int) $data->source, 'target' => (int) $data->target,
        'confirm' => 1]));
}

$error = null;
if ($sourceid && $targetid && $confirm) {
    foreach ([$sourceid, $targetid] as $id) {
        if ($id == SITEID || !$DB->record_exists('course', ['id' => $id])) {
            throw new moodle_exception('invalidcourseid');
        }
    }
    if ($sourceid === $targetid) {
        $error = get_string('cmp_same_course', 'local_mooc_audit');
    } else {
        \local_mooc_audit\comparator::require_access($sourceid, $targetid);
        $pairs = \local_mooc_audit\comparator::parse_pairs($rawpairs);
        $comparison = \local_mooc_audit\comparator::run($sourceid, $targetid, $pairs);
        if ($format === 'json') {
            @header('Content-Type: application/json; charset=utf-8');
            echo \local_mooc_audit\comparator::to_json($comparison);
            exit;
        }
        $pageurl = new moodle_url($url, ['confirm' => 1]);
        foreach ($pairs as $i => [$s, $t]) {
            $pageurl->param("pair[$i]", "$s-$t");
        }
        echo $OUTPUT->header();
        $renderer = $PAGE->get_renderer('local_mooc_audit');
        echo $renderer->render(new \local_mooc_audit\output\comparison_report($comparison, $pageurl));
        echo $OUTPUT->footer();
        exit;
    }
} else if ($sourceid && $targetid && !$confirm) {
    $error = get_string('cmp_confirm_required', 'local_mooc_audit');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('comparemenu', 'local_mooc_audit'));
echo html_writer::tag('p', get_string('cmp_intro', 'local_mooc_audit'));
if ($error) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->set_data(['source' => $sourceid ?: null, 'target' => $targetid ?: null]);
$form->display();
echo $OUTPUT->footer();
