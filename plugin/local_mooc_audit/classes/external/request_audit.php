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

namespace local_mooc_audit\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_mooc_audit\local\api\service;

/**
 * Request the audit of a course (specs/004 US2). Never runs the audit in the call: the run is queued for a background
 * task, or the alive pending run of the course is returned.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_audit extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to audit'),
        ]);
    }

    /**
     * Request the audit.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $USER;
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        service::require_api();
        $course = service::course_for($courseid);
        return ['api_version' => service::API_VERSION] + service::request((int) $course->id, (int) $USER->id);
    }

    /**
     * Response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'api_version' => new external_value(PARAM_TEXT, 'API version'),
            'runid' => new external_value(PARAM_INT, 'Run to follow with local_mooc_audit_get_run'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'status' => new external_value(PARAM_ALPHA, 'queued or running'),
            'reused' => new external_value(PARAM_BOOL, 'The course already had an alive pending run, returned instead'),
            'timecreated' => new external_value(PARAM_INT, 'Request time of the run'),
        ]);
    }
}
