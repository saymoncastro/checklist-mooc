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
use local_mooc_audit\local\api\run_view;
use local_mooc_audit\local\api\service;

/**
 * One stored run by id (specs/004 US3), with its report and evolution. Read-only; a run out of reach is reported as
 * missing.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_run extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'includereport' => new external_value(PARAM_BOOL, 'Include the stored report', VALUE_DEFAULT, true),
            'includeevolutionitems' => new external_value(PARAM_BOOL, 'Include the evolution items', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Read the run.
     *
     * @param int $runid
     * @param bool $includereport
     * @param bool $includeevolutionitems
     * @return array
     */
    public static function execute(int $runid, bool $includereport = true, bool $includeevolutionitems = false): array {
        $params = self::validate_parameters(self::execute_parameters(), ['runid' => $runid,
            'includereport' => $includereport, 'includeevolutionitems' => $includeevolutionitems]);
        service::require_api();
        $run = service::find_run($params['runid']);
        return ['api_version' => service::API_VERSION,
            'run' => run_view::export($run, $params['includereport'], $params['includeevolutionitems'])];
    }

    /**
     * Response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'api_version' => new external_value(PARAM_TEXT, 'API version'),
            'run' => run_view::structure(),
        ]);
    }
}
