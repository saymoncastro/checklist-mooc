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

namespace local_mooc_audit\tests;

use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

/**
 * Shared setup of the external API tests (specs/004): fixtures, an integration account and the write counters of
 * FR-020.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class api_testcase extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;

    /**
     * Reset, admin user and the reoffer year of the fixtures.
     */
    protected function setup_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('policyjson', json_encode(['reoffer_year' => self::YEAR]), 'local_mooc_audit');
    }

    /**
     * Plugin generator.
     *
     * @return \local_mooc_audit_generator
     */
    protected function gen(): \local_mooc_audit_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
    }

    /**
     * An integration account as documented in API.md: one role with the API capabilities in the system context, or
     * split between the system (api) and a category (course access).
     *
     * @param \context|null $coursescope context of the course capabilities (default: system)
     * @param bool $api grant local/mooc_audit:api
     * @param string[] $without course capabilities left out
     * @return \stdClass user
     */
    protected function integration_user(?\context $coursescope = null, bool $api = true, array $without = []): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();
        $coursescope = $coursescope ?? $system;
        $sysrole = create_role('API ' . $user->id, 'api' . $user->id, '');
        if ($api) {
            assign_capability('local/mooc_audit:api', CAP_ALLOW, $sysrole, $system->id);
        }
        assign_capability('webservice/rest:use', CAP_ALLOW, $sysrole, $system->id);
        $courserole = $coursescope->id === $system->id ? $sysrole : create_role('Cat ' . $user->id, 'cat' . $user->id, '');
        $caps = array_diff(['local/mooc_audit:audit', 'moodle/course:view', 'moodle/course:viewhiddencourses'], $without);
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $courserole, $coursescope->id);
        }
        role_assign($sysrole, $user->id, $system->id);
        if ($courserole !== $sysrole) {
            role_assign($courserole, $user->id, $coursescope->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * Record an audit of a course as the audit page does.
     *
     * @param int $courseid
     * @return \stdClass run
     */
    protected function audit_now(int $courseid): \stdClass {
        [$run] = recorder::record_now($courseid, run_store::ORIGIN_PAGE, get_admin()->id);
        return $run;
    }

    /**
     * Run the queued audits of the plugin, as cron would.
     *
     * @return string task output
     */
    protected function run_queue(): string {
        ob_start();
        $this->runAdhocTasks(\local_mooc_audit\task\audit_course::class);
        return (string) ob_get_clean();
    }

    /**
     * Rows of the plugin tables and of the adhoc task queue, and the content of the runs (FR-020: a read writes
     * nothing).
     *
     * @return array
     */
    protected function writes(): array {
        global $DB;
        $out = [];
        foreach (['local_mooc_audit_run', 'local_mooc_audit_policy', 'local_mooc_audit_batch', 'task_adhoc'] as $t) {
            $out[$t] = $DB->count_records($t);
        }
        $out['runs'] = sha1(json_encode($DB->get_records('local_mooc_audit_run', null, 'id')));
        return $out;
    }

    /**
     * Error code of a call that must fail.
     *
     * @param callable $fn
     * @return string errorcode, or '' when the call succeeded
     */
    protected function error_of(callable $fn): string {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
        return '';
    }
}
