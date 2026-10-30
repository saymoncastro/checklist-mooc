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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_mooc_audit\local\api\run_view;
use local_mooc_audit\local\api\service;
use local_mooc_audit\local\history\latest;
use local_mooc_audit\local\history\run_store;

/**
 * Result of the last recorded audit of a course (specs/004 US1), the same as the audit page shows: nothing is run nor
 * written. The reasons why it may be outdated are computed at the time of the call.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_latest_audit extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'includereport' => new external_value(PARAM_BOOL, 'Include the stored report', VALUE_DEFAULT, true),
            'includeevolutionitems' => new external_value(PARAM_BOOL, 'Include the evolution items', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Read the last recorded audit.
     *
     * @param int $courseid
     * @param bool $includereport
     * @param bool $includeevolutionitems
     * @return array
     */
    public static function execute(int $courseid, bool $includereport = true, bool $includeevolutionitems = false): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid,
            'includereport' => $includereport, 'includeevolutionitems' => $includeevolutionitems]);
        service::require_api();
        $course = service::course_for($params['courseid']);
        $latest = latest::for_course($course);
        $out = ['api_version' => service::API_VERSION, 'found' => (bool) $latest['valid']];
        if ($latest['valid']) {
            $out['run'] = run_view::export($latest['valid'], $params['includereport'], $params['includeevolutionitems']);
            $out['stale_reasons'] = array_values($latest['stale']);
        }
        if ($latest['lastfailure']) {
            $failure = $latest['lastfailure'];
            $out['last_failure'] = ['id' => (int) $failure->id, 'timefinished' => (int) $failure->timefinished,
                'errors' => run_view::export($failure, false, false)['errors']];
        }
        if ($latest['pending']) {
            $pending = run_store::get((int) $latest['pending']->id);
            $out['pending'] = ['id' => (int) $pending->id, 'status' => $pending->status, 'origin' => $pending->origin,
                'timecreated' => (int) $pending->timecreated];
        }
        return $out;
    }

    /**
     * Response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $errors = run_view::structure()->keys['errors'];
        return new external_single_structure([
            'api_version' => new external_value(PARAM_TEXT, 'API version'),
            'found' => new external_value(PARAM_BOOL, 'The course has a recorded audit with a report'),
            'run' => new external_single_structure(run_view::structure()->keys, 'Last recorded audit', VALUE_OPTIONAL),
            'stale_reasons' => new external_multiple_structure(
                new external_value(PARAM_ALPHAEXT, 'course_changed, age, policy_changed or plugin_changed'),
                'Why the result may be outdated (never the current conformity)',
                VALUE_OPTIONAL
            ),
            'last_failure' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Failed run'),
                'timefinished' => new external_value(PARAM_INT, 'End time'),
                'errors' => $errors,
            ], 'Failed run newer than the last recorded audit', VALUE_OPTIONAL),
            'pending' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Run id'),
                'status' => new external_value(PARAM_ALPHA, 'queued or running'),
                'origin' => new external_value(PARAM_ALPHA, 'Origin of the run'),
                'timecreated' => new external_value(PARAM_INT, 'Request time'),
            ], 'Alive pending run of the course', VALUE_OPTIONAL),
        ]);
    }
}
