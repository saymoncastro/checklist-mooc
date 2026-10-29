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

namespace local_mooc_audit\event;

/**
 * An assisted correction was applied to an activity (specs/002).
 *
 * Other data: ruleid, field, oldvalue, newvalue, reaudit (resolved, not_resolved, introduced or error).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correction_applied extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_mooc_audit_fix';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_correction_applied', 'local_mooc_audit');
    }

    /**
     * Description (not localised, as in core events).
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' applied the correction of rule '{$this->other['ruleid']}' to the course "
            . "module with id '{$this->contextinstanceid}': {$this->other['field']} '{$this->other['oldvalue']}' -> "
            . "'{$this->other['newvalue']}' (re-audit: {$this->other['reaudit']}).";
    }

    /**
     * The audit page of the course.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/mooc_audit/index.php', ['courseid' => $this->courseid]);
    }

    /**
     * Validate.
     */
    protected function validate_data() {
        parent::validate_data();
        foreach (['ruleid', 'field', 'oldvalue', 'newvalue', 'reaudit'] as $key) {
            if (!array_key_exists($key, $this->other)) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
        if ($this->contextlevel !== CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * The trail table is not restored with courses.
     *
     * @return array|int
     */
    public static function get_objectid_mapping() {
        return \core\event\base::NOT_MAPPED;
    }

    /**
     * Other data has no ids to map.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
