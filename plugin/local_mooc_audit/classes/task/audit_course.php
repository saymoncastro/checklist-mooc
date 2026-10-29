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
 * Background audit of one course of a batch (specs/005 research R6, R7).
 *
 * Runs as the requester and checks again, at execution time, that the requester may still audit the course in batch.
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
     * Whether the requester may still run this audit.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function authorised(int $userid, int $courseid): bool {
        if ($userid <= 0) {
            return false;
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }
        return has_capability('local/mooc_audit:batchaudit', \context_system::instance(), $user)
            && has_capability('local/mooc_audit:audit', \context_course::instance($courseid), $user);
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
            if (!self::authorised((int) $run->userid, (int) $run->courseid)) {
                recorder::fail($runid, 'permission', get_string('error_permission', 'local_mooc_audit'));
                return;
            }
            if (!run_store::start($runid)) {
                return;
            }
            [$done] = recorder::execute($runid);
            mtrace("local_mooc_audit: run $runid course {$run->courseid} → {$done->status}");
        } finally {
            $lock->release();
            gc_collect_cycles();
        }
    }
}
