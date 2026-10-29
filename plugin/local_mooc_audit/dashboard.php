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
 * Audit follow-up dashboard (specs/005 US4): which courses need attention, which problems remain open, which were
 * resolved since the previous audit, which courses were never audited or have outdated results.
 *
 * Parameters (GET filters): categoryid, subcats, q, situation, severity, from, to, page. Requires
 * local/mooc_audit:viewdashboard; lists only courses where the viewer holds local/mooc_audit:audit. Read only.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\dashboard;

$filters = [
    'categoryid' => optional_param('categoryid', 0, PARAM_INT),
    'subcats' => optional_param('subcats', 1, PARAM_BOOL),
    'q' => optional_param('q', '', PARAM_TEXT),
    'situation' => optional_param('situation', '', PARAM_ALPHAEXT),
    'severity' => optional_param('severity', '', PARAM_TEXT),
    'from' => optional_param('from', '', PARAM_RAW_TRIMMED),
    'to' => optional_param('to', '', PARAM_RAW_TRIMMED),
];
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$clean = dashboard::clean_filters($filters);
$params = array_filter([
    'categoryid' => $clean['categoryid'], 'subcats' => $clean['subcats'] ? 1 : 0, 'q' => $clean['q'],
    'situation' => $clean['situation'], 'severity' => $clean['severity'], 'from' => $clean['from'], 'to' => $clean['to'],
], fn($v) => $v !== '' && $v !== 0 && $v !== null);
$params['subcats'] = $clean['subcats'] ? 1 : 0;
$url = new moodle_url('/local/mooc_audit/dashboard.php', $params);

admin_externalpage_setup('local_mooc_audit_dashboard', '', null, $url);
$system = context_system::instance();
$PAGE->set_title(get_string('dashboardmenu', 'local_mooc_audit'));
$PAGE->set_heading(get_string('dashboardmenu', 'local_mooc_audit'));

$data = dashboard::build((int) $USER->id, $filters);
$renderer = $PAGE->get_renderer('local_mooc_audit');
echo $OUTPUT->header();
echo $renderer->render(new \local_mooc_audit\output\dashboard_page(
    $data,
    core_course_category::make_categories_list(),
    has_capability('local/mooc_audit:batchaudit', $system),
    batch_manager::recent(10),
    dashboard::removed_courses(),
    $page,
    $perpage,
    $url
));
echo $OUTPUT->footer();
