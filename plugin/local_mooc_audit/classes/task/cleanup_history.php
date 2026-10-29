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

use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\policy_store;
use local_mooc_audit\local\history\recorder;
use local_mooc_audit\local\history\run_store;

/**
 * Daily retention of the audit history (specs/005 FR-022, FR-034).
 *
 * 1. Runs queued or running for more than a day whose background task no longer exists are failed as "stale".
 * 2. Final runs older than the retention period are deleted, except the latest valid run of each existing course (the
 *    current state shown on the dashboard).
 * 3. Batches older than the retention period without runs, and policies no run refers to, are deleted.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_history extends \core\task\scheduled_task {
    /** @var int Default retention (days). */
    const DEFAULT_DAYS = 365;
    /** @var int Shortest retention accepted. */
    const MIN_DAYS = 30;
    /** @var int Longest retention accepted. */
    const MAX_DAYS = 1825;

    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_history', 'local_mooc_audit');
    }

    /**
     * Effective retention in days (setting clamped to the allowed range).
     *
     * @return int
     */
    public static function retention_days(): int {
        $days = (int) get_config('local_mooc_audit', 'history_retention_days');
        return $days > 0 ? max(self::MIN_DAYS, min(self::MAX_DAYS, $days)) : self::DEFAULT_DAYS;
    }

    /**
     * Run the cleanup.
     */
    public function execute() {
        $done = self::purge(time());
        mtrace('local_mooc_audit: ' . json_encode($done));
    }

    /**
     * Apply the retention.
     *
     * @param int $now
     * @return array counts: stale, runs, batches, policies
     */
    public static function purge(int $now): array {
        global $DB;
        $out = ['stale' => 0, 'runs' => 0, 'batches' => 0, 'policies' => 0];

        // 1. Stale runs.
        $tasked = [];
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . audit_course::class], '', 'id, customdata');
        foreach ($tasks as $t) {
            $data = json_decode((string) $t->customdata);
            if (!empty($data->runid)) {
                $tasked[(int) $data->runid] = true;
            }
        }
        $open = $DB->get_records_select(
            run_store::TABLE,
            'status IN (:queued, :running) AND timecreated < :limit',
            ['queued' => run_store::QUEUED, 'running' => run_store::RUNNING, 'limit' => $now - DAYSECS],
            '',
            'id'
        );
        foreach ($open as $run) {
            if (!isset($tasked[(int) $run->id]) && recorder::fail((int) $run->id, 'stale',
                    get_string('error_stale', 'local_mooc_audit'))) {
                $out['stale']++;
            }
        }

        // 2. Retention, keeping the latest valid run of each existing course.
        [$in, $params] = $DB->get_in_or_equal(run_store::valid_states(), SQL_PARAMS_NAMED);
        $keep = array_flip(array_map('intval', $DB->get_fieldset_sql(
            "SELECT MAX(r.id) FROM {" . run_store::TABLE . "} r JOIN {course} c ON c.id = r.courseid
              WHERE r.status $in GROUP BY r.courseid",
            $params
        )));
        [$fin, $fparams] = $DB->get_in_or_equal(run_store::final_states(), SQL_PARAMS_NAMED);
        $fparams['cutoff'] = $now - self::retention_days() * DAYSECS;
        $old = $DB->get_fieldset_select(run_store::TABLE, 'id', "status $fin AND timefinished < :cutoff", $fparams);
        $delete = array_values(array_filter(array_map('intval', $old), fn($id) => !isset($keep[$id])));
        foreach (array_chunk($delete, 500) as $chunk) {
            $DB->delete_records_list(run_store::TABLE, 'id', $chunk);
        }
        $out['runs'] = count($delete);

        // 3. Orphans.
        $batches = $DB->get_fieldset_sql(
            'SELECT b.id FROM {' . batch_manager::TABLE . '} b
              WHERE b.timecreated < :limit
                AND NOT EXISTS (SELECT 1 FROM {' . run_store::TABLE . '} r WHERE r.batchid = b.id)',
            ['limit' => $fparams['cutoff']]
        );
        $DB->delete_records_list(batch_manager::TABLE, 'id', $batches);
        $out['batches'] = count($batches);
        $policies = $DB->get_fieldset_sql(
            'SELECT p.id FROM {' . policy_store::TABLE . '} p
              WHERE p.timecreated < :limit
                AND NOT EXISTS (SELECT 1 FROM {' . run_store::TABLE . '} r WHERE r.policyid = p.id)',
            ['limit' => $now - DAYSECS]
        );
        $DB->delete_records_list(policy_store::TABLE, 'id', $policies);
        $out['policies'] = count($policies);
        return $out;
    }
}
