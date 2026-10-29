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
 * Admin settings: the institutional policy lives outside the code.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mooc_audit', get_string('pluginname', 'local_mooc_audit'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new \local_mooc_audit\admin\setting_policyjson(
        'local_mooc_audit/policyjson',
        get_string('policyjson', 'local_mooc_audit'),
        get_string('policyjson_desc', 'local_mooc_audit'),
        '',
        PARAM_RAW
    ));
    $settings->add(new admin_setting_heading('local_mooc_audit/tools', '', html_writer::link(
        new moodle_url('/local/mooc_audit/index.php'), get_string('runaudit', 'local_mooc_audit'))));
}
