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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/api_testcase.php');

/**
 * Access to the external API under the administrator's control (specs/004 US4, SC-003).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\api\service
 * @covers     \local_mooc_audit\external\request_audit
 * @covers     \local_mooc_audit\external\get_run
 * @covers     \local_mooc_audit\external\get_latest_audit
 */
final class access_test extends \local_mooc_audit\tests\api_testcase {
    /**
     * After installation no role has the API capability; the service is disabled, restricted and limited to the three
     * functions, which are the only functions of the plugin.
     */
    public function test_installation_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame([], get_roles_with_capability('local/mooc_audit:api', CAP_ALLOW, \context_system::instance()));
        $service = $DB->get_record('external_services', ['shortname' => 'local_mooc_audit'], '*', MUST_EXIST);
        $this->assertSame(['0', '1', 'local/mooc_audit:api', 'local_mooc_audit'], [(string) $service->enabled,
            (string) $service->restrictedusers, $service->requiredcapability, $service->component]);
        $functions = ['local_mooc_audit_get_latest_audit', 'local_mooc_audit_get_run', 'local_mooc_audit_request_audit'];
        $this->assertEqualsCanonicalizing($functions, $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$service->id]
        ));
        $this->assertEqualsCanonicalizing($functions, $DB->get_fieldset_select(
            'external_functions',
            'name',
            'component = ?',
            ['local_mooc_audit']
        ));
    }

    /**
     * Who may not use the API: an editing teacher without the API capability; an account limited to another category;
     * a hidden course without viewhiddencourses; the site course and a missing id.
     */
    public function test_denied_matrix(): void {
        $this->setup_site();
        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $a = $this->getDataGenerator()->create_course(['category' => $cata->id]);
        $b = $this->getDataGenerator()->create_course(['category' => $catb->id]);
        $hidden = $this->getDataGenerator()->create_course(['category' => $cata->id, 'visible' => 0]);
        $runa = $this->audit_now((int) $a->id);
        $runb = $this->audit_now((int) $b->id);
        $calls = fn(int $courseid, int $runid) => [
            'request' => fn() => request_audit::execute($courseid),
            'latest' => fn() => get_latest_audit::execute($courseid),
            'run' => fn() => get_run::execute($runid),
        ];
        $before = $this->writes();

        $teacher = $this->getDataGenerator()->create_and_enrol($a, 'editingteacher');
        $this->setUser($teacher);
        foreach ($calls((int) $a->id, (int) $runa->id) as $name => $fn) {
            $this->assertSame('nopermissions', $this->error_of($fn), "teacher without api: $name");
        }

        $this->setUser($this->integration_user(\context_coursecat::instance($cata->id)));
        $this->assertSame('', $this->error_of(fn() => get_latest_audit::execute((int) $a->id)), 'own category');
        foreach ($calls((int) $b->id, (int) $runb->id) as $name => $fn) {
            $expected = $name === 'run' ? ['runnotfound'] : ['nopermissions', 'requireloginerror'];
            $this->assertContains($this->error_of($fn), $expected, "other category: $name");
        }

        $this->setUser($this->integration_user(null, true, ['moodle/course:viewhiddencourses']));
        $this->assertSame('requireloginerror', $this->error_of(fn() => request_audit::execute((int) $hidden->id)));
        $this->assertSame('requireloginerror', $this->error_of(fn() => get_latest_audit::execute((int) $hidden->id)));
        foreach ([SITEID, 999999] as $id) {
            $this->assertSame('invalidcourseid', $this->error_of(fn() => request_audit::execute($id)), "course $id");
            $this->assertSame('invalidcourseid', $this->error_of(fn() => get_latest_audit::execute($id)), "course $id");
        }
        $this->assertSame($before, $this->writes(), 'refused calls write nothing');
    }
}
