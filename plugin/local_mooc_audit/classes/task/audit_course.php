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

namespace local_mooc_audit\task;

use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

/**
 * Background audit of one course, requested by a batch (specs/005 research R6, R7) or the external API (specs/004).
 *
 * Runs as the requester and checks again, at execution time, that the requester may still audit the course in batch
 * (batchaudit) or through the API (api), according to the origin of the run.
 * A per-course lock keeps two background audits of the same course apart; a run already final is skipped (duplicated
 * delivery); a run found "running" with its lock free belonged to a dead process and is resumed, up to MAX_ATTEMPTS.
 * Failures inside the audit end the run as failed without rescheduling the task.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_course extends \core\task\adhoc_task {
    /** @var int Attempts before a repeatedly interrupted run is given up. */
    const MAX_ATTEMPTS = 3;

    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_audit_course', 'local_mooc_audit');
    }

    /**
     * Concurrency limit from the plugin settings ($CFG->task_concurrency_limit still overrides it).
     *
     * @return int
     */
    protected function get_default_concurrency_limit(): int {
        $limit = (int) get_config('local_mooc_audit', 'batch_concurrency');
        return $limit > 0 ? min(10, $limit) : 2;
    }

    /**
     * What a queued run needs at execution time, by origin.
     *
     * @param string $origin
     * @return string[] [system capability of the requester, string of the permission failure]
     */
    protected static function requirement(string $origin): array {
        return $origin === run_store::ORIGIN_API ? ['local/mooc_audit:api', 'error_permission_api']
            : ['local/mooc_audit:batchaudit', 'error_permission'];
    }

    /**
     * Whether the requester may still run this audit.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $origin run origin: batch runs need batchaudit, API runs need api (system)
     * @return bool
     */
    public static function authorised(int $userid, int $courseid, string $origin = run_store::ORIGIN_BATCH): bool {
        if ($userid <= 0) {
            return false;
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        [$capability] = self::requirement($origin);
        return has_capability($capability, \context_system::instance(), $user)
            && has_capability('local/mooc_audit:audit', \context_course::instance($courseid), $user);
    }

    /**
     * Policy override of a batch: the explicit cycle (reoffer year) it asked for, if any (decision 12.6).
     *
     * @param int $batchid
     * @return array|null
     */
    public static function policy_override(int $batchid): ?array {
        $batch = $batchid ? \local_mooc_audit\local\history\batch_manager::get($batchid) : null;
        $year = $batch ? (int) (json_decode($batch->criteria, true)['reofferyear'] ?? 0) : 0;
        return $year ? ['reoffer_year' => $year] : null;
    }

    /**
     * Run the audit.
     */
    public function execute() {
        global $DB;
        $runid = (int) ($this->get_custom_data()->runid ?? 0);
        $run = run_store::get($runid);
        if (!$run || in_array($run->status, run_store::final_states(), true)) {
            mtrace("local_mooc_audit: run $runid already finished or missing; nothing to do.");
            return;
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_mooc_audit')->get_lock('course_' . $run->courseid, 0);
        if (!$lock) {
            // Another background audit of this course is running: let the task API retry later.
            throw new \moodle_exception('error_lockbusy', 'local_mooc_audit');
        }
        try {
            $run = run_store::get($runid);
            if (in_array($run->status, run_store::final_states(), true)) {
                return;
            }
            if ($run->attempts >= self::MAX_ATTEMPTS) {
                recorder::fail($runid, 'interrupted', get_string('error_interrupted', 'local_mooc_audit', $run->attempts));
                return;
            }
            if ($run->courseid == SITEID || !$DB->record_exists('course', ['id' => $run->courseid])) {
                recorder::fail($runid, 'course_missing', get_string('invalidcourseid', 'error'));
                return;
            }
            if (!self::authorised((int) $run->userid, (int) $run->courseid, (string) $run->origin)) {
                [, $string] = self::requirement((string) $run->origin);
                recorder::fail($runid, 'permission', get_string($string, 'local_mooc_audit'));
                return;
            }
            if (!run_store::start($runid)) {
                return;
            }
            [$done] = recorder::execute($runid, self::policy_override((int) $run->batchid));
            mtrace("local_mooc_audit: run $runid course {$run->courseid} → {$done->status}");
        } finally {
            $lock->release();
            gc_collect_cycles();
        }
    }
}
