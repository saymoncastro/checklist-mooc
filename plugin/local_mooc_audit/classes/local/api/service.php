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

namespace local_mooc_audit\local\api;

use core_external\external_api;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

/**
 * Access rules and the request of the external API (specs/004 contracts/web-services.md).
 *
 * Every function needs local/mooc_audit:api in the system and, for a course, the same access as the audit page
 * (require_login in the course and local/mooc_audit:audit). A run out of reach is reported exactly like a missing one.
 * A request never runs the audit: it reuses the alive pending run of the course or queues a background task.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service {
    /** @var string Version of the API contract, returned in every response. */
    const API_VERSION = '1.0';

    /**
     * Check that the current user may use the API.
     *
     * @throws \required_capability_exception|\moodle_exception
     */
    public static function require_api(): void {
        $context = \context_system::instance();
        external_api::validate_context($context);
        require_capability('local/mooc_audit:api', $context);
    }

    /**
     * A course the current user may audit, with the course context validated (require_login).
     *
     * @param int $courseid
     * @return \stdClass course record
     * @throws \moodle_exception invalidcourseid, requireloginerror, nopermissions
     */
    public static function course_for(int $courseid): \stdClass {
        global $DB;
        $course = $courseid != SITEID ? $DB->get_record('course', ['id' => $courseid]) : false;
        if (!$course) {
            throw new \moodle_exception('invalidcourseid', 'error');
        }
        $context = \context_course::instance($course->id);
        external_api::validate_context($context);
        require_capability('local/mooc_audit:audit', $context);
        return $course;
    }

    /**
     * A run the current user may read: of a course where they may audit or, for a removed course, with
     * local/mooc_audit:viewdashboard (same rule as history.php).
     *
     * @param int $runid
     * @return \stdClass
     * @throws \moodle_exception runnotfound, for a missing run and for one out of reach alike
     */
    public static function find_run(int $runid): \stdClass {
        global $DB;
        $run = $runid > 0 ? run_store::get($runid) : null;
        if ($run && self::may_read($run, $DB->get_record('course', ['id' => $run->courseid]) ?: null)) {
            return $run;
        }
        throw new \moodle_exception('runnotfound', 'local_mooc_audit');
    }

    /**
     * Whether the current user may read a run.
     *
     * @param \stdClass $run
     * @param \stdClass|null $course
     * @return bool
     */
    protected static function may_read(\stdClass $run, ?\stdClass $course): bool {
        if ((int) $run->courseid === (int) SITEID) {
            return false;
        }
        if (!$course) {
            return has_capability('local/mooc_audit:viewdashboard', \context_system::instance());
        }
        $context = \context_course::instance($course->id);
        try {
            external_api::validate_context($context);
        } catch (\moodle_exception $e) {
            return false;
        }
        return has_capability('local/mooc_audit:audit', $context);
    }

    /**
     * Largest number of alive pending API runs per requester (setting api_max_pending).
     *
     * @return int
     */
    public static function max_pending(): int {
        return \local_mooc_audit\local\helpers::bounded_setting('api_max_pending', 100, 2000);
    }

    /**
     * Request the audit of a course: reuse its alive pending run (any origin) or queue a new API run.
     *
     * Requests are serialised with the batches by the queue lock (recorder::queue_lock()), so that simultaneous calls
     * never queue two runs of a course.
     *
     * @param int $courseid course already checked with course_for()
     * @param int $userid requester; the background task runs as this user and checks the permissions again
     * @param int $wait seconds to wait for the queue lock
     * @return array ['runid', 'courseid', 'status', 'reused', 'timecreated']
     * @throws \moodle_exception busy, queuefull
     */
    public static function request(int $courseid, int $userid, int $wait = recorder::QUEUE_LOCK_WAIT): array {
        $lock = recorder::queue_lock($wait);
        try {
            $tasked = run_store::tasked_runs();
            $pending = run_store::reusable_pending($courseid, null, $tasked);
            if ($pending) {
                $run = $pending;
                $reused = true;
            } else {
                $mine = run_store::count_alive_pending_by_origin($userid, run_store::ORIGIN_API, null, $tasked);
                if ($mine >= self::max_pending()) {
                    throw new \moodle_exception('queuefull', 'local_mooc_audit', '', self::max_pending());
                }
                $run = run_store::get(recorder::queue($courseid, $userid, 0, run_store::ORIGIN_API));
                $reused = false;
            }
        } finally {
            $lock->release();
        }
        return [
            'runid' => (int) $run->id,
            'courseid' => $courseid,
            'status' => $run->status,
            'reused' => $reused,
            'timecreated' => (int) $run->timecreated,
        ];
    }
}
