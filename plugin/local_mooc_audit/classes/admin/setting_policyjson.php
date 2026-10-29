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

namespace local_mooc_audit\admin;

use admin_setting_configtextarea;

/**
 * Admin setting for the institutional policy: a JSON object, validated before it is saved.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_policyjson extends admin_setting_configtextarea {
    /**
     * Accept an empty value (defaults) or a JSON object; reject anything else with the decoder's message.
     *
     * @param string $data
     * @return bool|string true when valid, otherwise the error message
     */
    public function validate($data) {
        $data = trim((string) $data);
        if ($data === '') {
            return true;
        }
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return get_string('policyjson_invalid', 'local_mooc_audit', json_last_error() === JSON_ERROR_NONE
                ? get_string('policyjson_notobject', 'local_mooc_audit') : json_last_error_msg());
        }
        return true;
    }
}
