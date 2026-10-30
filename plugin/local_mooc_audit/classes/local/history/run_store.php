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

namespace local_mooc_audit\local\history;

use local_mooc_audit\engine;

/**
 * Audit runs (specs/005 data-model): creation, state transitions and queries.
 *
 * A run in a final state (complete, partial, failed) is never updated: every transition is a conditional UPDATE on
 * the non-final states, so a late or duplicated task cannot rewrite history. Only retention and the privacy provider
 * remove or anonymise rows.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_store {
    /** @var string Table. */
    const TABLE = 'local_mooc_audit_run';

    /** @var string Waiting for its background task. */
    const QUEUED = 'queued';
    /** @var string Being audited. */
    const RUNNING = 'running';
    /** @var string Report produced, every rule ran. */
    const COMPLETE = 'complete';
    /** @var string Report produced, at least one rule ended in ERRO. */
    const PARTIAL = 'partial';
    /** @var string No report. */
    const FAILED = 'failed';

    /** @var int A running run older than this (seconds) is not reused by a new batch (its process probably died). */
    const STALE_RUNNING = 900;

    /** @var string Audit page of the course. */
    const ORIGIN_PAGE = 'page';
    /** @var string Background batch. */
    const ORIGIN_BATCH = 'batch';
    /** @var string Command line. */
    const ORIGIN_CLI = 'cli';
    /** @var string Re-audit recorded right after an applied assisted correction (specs/002). */
    const ORIGIN_CORRECTION = 'correction';

    /**
     * Final states.
     *
     * @return string[]
     */
    public static function final_states(): array {
        return [self::COMPLETE, self::PARTIAL, self::FAILED];
    }

    /**
     * States whose report can serve as the base of an evolution.
     *
     * @return string[]
     */
    public static function valid_states(): array {
        return [self::COMPLETE, self::PARTIAL];
    }

    /**
     * Plugin version label stored in every run: "release (version)".
     *
     * @return string
     */
    public static function plugin_version(): string {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_mooc_audit');
        return engine::VERSION . ' (' . ($plugin->versiondisk ?? $plugin->versiondb ?? '?') . ')';
    }

    /**
     * Create a run.
     *
     * @param int $courseid
     * @param string $origin ORIGIN_*
     * @param int $userid requester (0 = process)
     * @param int $batchid
     * @param string $status QUEUED or RUNNING (synchronous audits)
     * @return int run id
     */
    public static function create(
        int $courseid,
        string $origin,
        int $userid,
        int $batchid = 0,
        string $status = self::QUEUED
    ): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'batchid' => $batchid,
            'userid' => $userid,
            'origin' => $origin,
            'status' => $status,
            'pluginversion' => self::plugin_version(),
            'contractversion' => engine::CONTRACT_VERSION,
            'attempts' => $status === self::RUNNING ? 1 : 0,
            'timecreated' => $now,
            'timestarted' => $status === self::RUNNING ? $now : null,
        ]);
    }

    /**
     * Mark a queued (or interrupted running) run as running and count the attempt.
     *
     * @param int $id
     * @return bool false when the run is already final or does not exist
     */
    public static function start(int $id): bool {
        global $DB;
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET status = :running, timestarted = :now, attempts = attempts + 1
              WHERE id = :id AND status IN (:queued, :running2)',
            ['running' => self::RUNNING, 'now' => time(), 'id' => $id, 'queued' => self::QUEUED, 'running2' => self::RUNNING]
        );
        return $DB->get_field(self::TABLE, 'status', ['id' => $id]) === self::RUNNING;
    }

    /**
     * Move a non-final run to a final state with its data. Final runs are left untouched.
     *
     * @param int $id
     * @param string $status COMPLETE, PARTIAL or FAILED
     * @param array $fields other columns (summary, report, details, evolution, errors as arrays are JSON encoded)
     * @return bool whether this call finished the run
     */
    public static function finish(int $id, string $status, array $fields = []): bool {
        global $DB;
        if (!in_array($status, self::final_states(), true)) {
            throw new \coding_exception('Not a final state: ' . $status);
        }
        $allowed = ['overall', 'policyid', 'reofferyear', 'coursecacherev', 'summary', 'report', 'details', 'previousrunid',
            'evolution',
            'errors', 'pluginversion'];
        $set = ['status = :status', 'timefinished = :timefinished'];
        $params = ['status' => $status, 'timefinished' => time(), 'id' => $id, 'queued' => self::QUEUED,
            'running' => self::RUNNING];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                throw new \coding_exception('Unknown run field: ' . $k);
            }
            $set[] = "$k = :f_$k";
            // Course texts may carry invalid UTF-8 (byte truncation): substitute rather than lose the whole report.
            $params["f_$k"] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) : $v;
        }
        $before = $DB->get_field(self::TABLE, 'status', ['id' => $id]);
        if (!in_array($before, [self::QUEUED, self::RUNNING], true)) {
            return false;
        }
        $DB->execute('UPDATE {' . self::TABLE . '} SET ' . implode(', ', $set)
            . ' WHERE id = :id AND status IN (:queued, :running)', $params);
        // Another finisher may have won in between: report success only if the row now holds this outcome.
        $after = $DB->get_record(self::TABLE, ['id' => $id], 'status, timefinished');
        return $after && $after->status === $status && (int) $after->timefinished === $params['timefinished'];
    }

    /**
     * Queued or running runs that are still alive: a queued run whose background task still exists, or a run that
     * started less than STALE_RUNNING seconds ago. Dead runs (lost task, process killed) are left for the daily cleanup,
     * which fails them as "stale"; they neither block a new batch nor show as pending on the dashboard.
     *
     * @param int[] $courseids
     * @param int|null $now
     * @return \stdClass[] courseid => oldest alive run of the course
     */
    public static function alive_pending(array $courseids, ?int $now = null): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        [$cin, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params += ['queued' => self::QUEUED, 'running' => self::RUNNING];
        $runs = $DB->get_records_select(
            self::TABLE,
            "courseid $cin AND status IN (:queued, :running)",
            $params,
            'id ASC',
            'id, courseid, batchid, status, timecreated, timestarted'
        );
        if (!$runs) {
            return [];
        }
        $tasked = [];
        $tasks = $DB->get_records(
            'task_adhoc',
            ['classname' => '\\' . \local_mooc_audit\task\audit_course::class],
            '',
            'id, customdata'
        );
        foreach ($tasks as $t) {
            $data = json_decode((string) $t->customdata);
            if (!empty($data->runid)) {
                $tasked[(int) $data->runid] = true;
            }
        }
        $limit = ($now ?? time()) - self::STALE_RUNNING;
        $out = [];
        foreach ($runs as $run) {
            $alive = $run->status === self::QUEUED ? isset($tasked[(int) $run->id]) : (int) $run->timestarted > $limit;
            if ($alive && !isset($out[(int) $run->courseid])) {
                $out[(int) $run->courseid] = $run;
            }
        }
        return $out;
    }

    /**
     * The alive queued or running run of a course, if any (see alive_pending()).
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function reusable_pending(int $courseid, ?int $now = null): ?\stdClass {
        return self::alive_pending([$courseid], $now)[$courseid] ?? null;
    }

    /**
     * A run.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Decode a JSON column of a run.
     *
     * @param \stdClass $run
     * @param string $field
     * @return array|null
     */
    public static function json(\stdClass $run, string $field): ?array {
        if (!isset($run->$field) || $run->$field === null || $run->$field === '') {
            return null;
        }
        $v = json_decode($run->$field, true);
        return is_array($v) ? $v : null;
    }

    /**
     * Latest run with a report (complete or partial) of a course by end time, optionally finished before a moment.
     *
     * Runs are ordered by the time they finished, not by id: a batch run queued before a page audit may finish after it.
     *
     * @param int $courseid
     * @param int $finishedby only runs finished at or before this time (0 = any)
     * @param int $excludeid run to leave out (the run being compared)
     * @return \stdClass|null
     */
    public static function latest_valid(int $courseid, int $finishedby = 0, int $excludeid = 0): ?\stdClass {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal(self::valid_states(), SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $where = "courseid = :courseid AND status $in";
        if ($finishedby) {
            $where .= ' AND timefinished <= :finishedby';
            $params['finishedby'] = $finishedby;
        }
        if ($excludeid) {
            $where .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludeid;
        }
        $runs = $DB->get_records_select(self::TABLE, $where, $params, 'timefinished DESC, id DESC', '*', 0, 1);
        return $runs ? reset($runs) : null;
    }

    /**
     * Latest run per course (by end time, then id) among some states.
     *
     * @param string[] $states final states to consider
     * @param int[]|null $courseids restrict to these courses (null = all)
     * @param string $fields run columns, prefixed with r.
     * @return \stdClass[] courseid => run
     */
    public static function latest_per_course(array $states, ?array $courseids = null, string $fields = 'r.*'): array {
        global $DB;
        if ($courseids === []) {
            return [];
        }
        [$sin, $params] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 's');
        [$sin2, $params2] = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 't');
        $params += $params2;
        $cwhere = '';
        if ($courseids !== null) {
            [$cin, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
            $cwhere = "AND courseid $cin";
            $params += $cparams;
        }
        $rows = $DB->get_records_sql(
            "SELECT $fields
               FROM {" . self::TABLE . "} r
               JOIN (SELECT courseid, MAX(timefinished) AS tf FROM {" . self::TABLE . "}
                      WHERE status $sin $cwhere GROUP BY courseid) m
                 ON m.courseid = r.courseid AND m.tf = r.timefinished
              WHERE r.status $sin2
           ORDER BY r.id",
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->courseid] = $r;
        }
        return $out;
    }

    /**
     * Runs of a course, newest first, without the large JSON columns.
     *
     * @param int $courseid
     * @param int $from
     * @param int $num
     * @return \stdClass[]
     */
    public static function for_course(int $courseid, int $from = 0, int $num = 20): array {
        global $DB;
        return array_values($DB->get_records(
            self::TABLE,
            ['courseid' => $courseid],
            'id DESC',
            'id, courseid, batchid, userid, origin, status, overall, policyid, pluginversion, reofferyear, summary,
             previousrunid, evolution, errors, attempts, timecreated, timestarted, timefinished',
            $from,
            $num
        ));
    }

    /**
     * Number of runs of a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function count_for_course(int $courseid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['courseid' => $courseid]);
    }
}
