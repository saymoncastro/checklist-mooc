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
    // The accepted keys and the reference JSON are generated from policy::defaults() so that they never diverge.
    $defaults = \local_mooc_audit\policy::defaults();
    $rows = [];
    foreach ($defaults as $key => $value) {
        $type = match (true) {
            $value === null => get_string('policyjson_type_auto', 'local_mooc_audit'),
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            default => 'object',
        };
        $rows[] = html_writer::tag('li', html_writer::tag('code', s($key)) . ' (' . s($type) . '): '
            . html_writer::tag('code', s(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))));
    }
    $desc = html_writer::tag('p', get_string('policyjson_desc', 'local_mooc_audit'))
        . html_writer::tag('p', get_string('policyjson_keys', 'local_mooc_audit'))
        . html_writer::tag('ul', implode('', $rows), ['class' => 'small']);
    $settings->add(new \local_mooc_audit\admin\setting_policyjson(
        'local_mooc_audit/policyjson',
        get_string('policyjson', 'local_mooc_audit'),
        $desc,
        '',
        PARAM_RAW
    ));
    $settings->add(new admin_setting_heading('local_mooc_audit/policydefaults',
        get_string('policyjson_defaults', 'local_mooc_audit'),
        html_writer::tag('pre', s(json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ['class' => 'small'])));
    $settings->add(new admin_setting_heading('local_mooc_audit/tools', '', html_writer::link(
        new moodle_url('/local/mooc_audit/index.php'), get_string('runaudit', 'local_mooc_audit'))));
}
