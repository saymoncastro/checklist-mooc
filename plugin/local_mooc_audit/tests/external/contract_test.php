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
use local_mooc_audit\local\api\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/schema_validator.php');

/**
 * Stable contract of the external API 1.0 (specs/004 US5, contracts/web-services.md).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\external\request_audit
 * @covers     \local_mooc_audit\external\get_run
 * @covers     \local_mooc_audit\external\get_latest_audit
 */
final class contract_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * Every successful response carries api_version 1.0 and, with a report, contract_version 1.0 and a valid report;
     * functions and parameters are the documented ones.
     */
    public function test_contract(): void {
        global $CFG;
        $this->setup_site();
        [$a] = $this->gen()->create_course_a(self::YEAR);
        $run = $this->audit_now((int) $a->id);
        $this->setUser($this->integration_user());
        $responses = [
            external_api::clean_returnvalue(request_audit::execute_returns(), request_audit::execute((int) $a->id)),
            external_api::clean_returnvalue(get_run::execute_returns(), get_run::execute((int) $run->id)),
            external_api::clean_returnvalue(get_latest_audit::execute_returns(), get_latest_audit::execute((int) $a->id)),
        ];
        $validator = new \local_mooc_audit\tests\schema_validator($CFG->dirroot
            . '/local/mooc_audit/schema/report-v1.1.schema.json');
        foreach ($responses as $i => $out) {
            $this->assertSame(service::API_VERSION, $out['api_version'], "response $i");
            if (isset($out['run'])) {
                $this->assertSame(\local_mooc_audit\engine::CONTRACT_VERSION, $out['run']['contract_version']);
                $report = json_decode($out['run']['report'], true);
                $this->assertSame(\local_mooc_audit\engine::CONTRACT_VERSION, $report['contract_version']);
                $this->assertSame([], $validator->errors($report));
            }
        }

        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/mooc_audit/db/services.php');
        $expected = [
            'local_mooc_audit_request_audit' => [request_audit::class, ['courseid']],
            'local_mooc_audit_get_run' => [get_run::class, ['runid', 'includereport', 'includeevolutionitems']],
            'local_mooc_audit_get_latest_audit' => [get_latest_audit::class,
                ['courseid', 'includereport', 'includeevolutionitems']],
        ];
        $this->assertSame(array_keys($expected), array_keys($functions));
        foreach ($expected as $name => [$class, $params]) {
            $this->assertSame($class, $functions[$name]['classname']);
            $this->assertSame($params, array_keys($class::execute_parameters()->keys), $name);
        }
        $this->assertSame(array_keys($expected), $services['Auditor MOOC']['functions']);
    }
}
