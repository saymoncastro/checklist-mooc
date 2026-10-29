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
 * Moodle callbacks of local_mooc_audit.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the "Technical audit" entry to the course navigation for users allowed to audit the course.
 *
 * @param navigation_node $navigation the course navigation node
 * @param stdClass $course the course
 * @param context_course $context the course context
 * @return void
 */
function local_mooc_audit_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!has_capability('local/mooc_audit:audit', $context)) {
        return;
    }
    $url = new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('auditmenu', 'local_mooc_audit'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_mooc_audit',
        new pix_icon('i/report', '')
    );
}
