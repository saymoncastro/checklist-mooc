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
 * Upgrade helpers of local_mooc_audit.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Grant local/mooc_audit:audit to the editing-teacher roles (decision 11, 2026-10-29).
 *
 * Moodle applies the archetypes of db/access.php only when a capability is created, so sites that already had the
 * capability (installed before 0.3.0) never gave it to editing teachers. Roles that already have any setting for the
 * capability in the system context (allow, prevent or prohibit chosen by an administrator) are left untouched.
 *
 * @return int number of roles granted
 */
function local_mooc_audit_grant_audit_to_editingteachers(): int {
    global $DB;
    $syscontext = context_system::instance();
    $granted = 0;
    foreach (get_archetype_roles('editingteacher') as $role) {
        $exists = $DB->record_exists('role_capabilities', ['roleid' => $role->id, 'contextid' => $syscontext->id,
            'capability' => 'local/mooc_audit:audit']);
        if (!$exists) {
            assign_capability('local/mooc_audit:audit', CAP_ALLOW, $role->id, $syscontext->id);
            $granted++;
        }
    }
    return $granted;
}
