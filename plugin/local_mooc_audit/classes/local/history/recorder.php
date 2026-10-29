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
use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;
use local_mooc_audit\policy;
use local_mooc_audit\task\audit_course;

/**
 * Recorded audits: the single path that runs the engine and stores the result (audit page, CLI and background tasks).
 *
 * The audit itself stays read-only towards the course; the only writes are the run row and, once per distinct content,
 * the applied policy. Failures outside the rules end the run as "failed" with the reason instead of losing it.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recorder {
    /** @var int Longest error message kept in a run. */
    const MAX_MESSAGE = 1000;

    /**
     * Audit a course now and record the run (audit page, CLI).
     *
     * @param int $courseid
     * @param string $origin run_store::ORIGIN_PAGE or ORIGIN_CLI
     * @param int $userid requester (0 = process)
     * @param array|null $override policy override (tests, CLI)
     * @param array|null $rules rules to run (default: the catalogue; tests may inject others)
     * @return array [run record, report or null, Throwable or null]
     */
    public static function record_now(
        int $courseid,
        string $origin,
        int $userid,
        ?array $override = null,
        ?array $rules = null
    ): array {
        $runid = run_store::create($courseid, $origin, $userid, 0, run_store::RUNNING);
        return self::execute($runid, $override, $rules);
    }

    /**
     * Create a queued run and its background task.
     *
     * @param int $courseid
     * @param int $userid requester; the task runs as this user and checks the permissions again
     * @param int $batchid
     * @return int run id
     */
    public static function queue(int $courseid, int $userid, int $batchid): int {
        $runid = run_store::create($courseid, run_store::ORIGIN_BATCH, $userid, $batchid);
        $task = new audit_course();
        $task->set_custom_data(['runid' => $runid]);
        $task->set_userid($userid);
        $task->set_component('local_mooc_audit');
        \core\task\manager::queue_adhoc_task($task);
        return $runid;
    }

    /**
     * End a run as failed.
     *
     * @param int $runid
     * @param string $code course_missing, permission, exception, interrupted or stale
     * @param string $message
     * @return bool whether the run was still open
     */
    public static function fail(int $runid, string $code, string $message = ''): bool {
        return run_store::finish($runid, run_store::FAILED, ['errors' => [['code' => $code,
            'message' => \core_text::substr($message, 0, self::MAX_MESSAGE)]]]);
    }

    /**
     * Audit the course of a started run and finish it (complete, partial or failed).
     *
     * @param int $runid
     * @param array|null $override policy override
     * @param array|null $rules rules to run (default: the catalogue; tests may inject others)
     * @return array [run record, report or null, Throwable or null]
     */
    public static function execute(int $runid, ?array $override = null, ?array $rules = null): array {
        global $DB;
        $run = run_store::get($runid);
        if (!$run) {
            throw new \coding_exception('Unknown run ' . $runid);
        }
        if ($run->courseid == SITEID || !$DB->record_exists('course', ['id' => $run->courseid])) {
            self::fail($runid, 'course_missing', get_string('invalidcourseid', 'error'));
            return [run_store::get($runid), null, null];
        }
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $t0 = microtime(true);
        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        try {
            $policy = policy::load($override);
            $policyid = policy_store::ensure($policy);
            $report = engine::audit_with_policy((int) $run->courseid, $policy, 0, true, $rules);
            $details = ['rules' => fingerprint::rules($policy, $rules)] + self::entities($report['snapshot']);
            unset($report['snapshot']);

            $errors = [];
            foreach ($report['results'] as $r) {
                if ($r['status'] === status::ERRO) {
                    $errors[] = ['code' => 'rule_error', 'rule' => $r['rule'],
                        'message' => \core_text::substr((string) $r['evidencia'], 0, self::MAX_MESSAGE)];
                }
            }
            $state = $errors ? run_store::PARTIAL : run_store::COMPLETE;
            foreach ($report['policy_warnings'] as $w) {
                $errors[] = ['code' => 'policy_warning', 'message' => (string) $w];
            }
            foreach ($report['warnings'] as $w) {
                $errors[] = ['code' => 'collection_warning',
                    'message' => is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE)];
            }

            $version = run_store::plugin_version();
            $evolution = null;
            $previous = run_store::latest_valid((int) $run->courseid, (int) ($run->timestarted ?: $run->timecreated), $runid);
            if ($previous) {
                $cmp = evolution::compare(
                    ['report' => run_store::json($previous, 'report') ?? [],
                        'details' => run_store::json($previous, 'details') ?? [], 'pluginversion' => $previous->pluginversion],
                    ['report' => $report, 'details' => $details, 'pluginversion' => $version]
                );
                $evolution = ['base' => (int) $previous->id, 'counts' => $cmp['counts'], 'notes' => $cmp['notes']];
            }
            $details['metrics'] = [
                'seconds' => round(microtime(true) - $t0, 3),
                'peak_mb' => round(memory_get_peak_usage() / 1048576, 1),
                'db_reads' => $DB->perf_get_reads() - $reads,
                'db_writes' => $DB->perf_get_writes() - $writes,
            ];
            run_store::finish($runid, $state, [
                'overall' => $report['summary']['overall_status'],
                'policyid' => $policyid,
                'reofferyear' => (int) $report['reoffer_year'],
                'summary' => $report['summary'],
                'report' => $report,
                'details' => $details,
                'previousrunid' => $previous ? (int) $previous->id : 0,
                'evolution' => $evolution,
                'errors' => $errors ?: null,
                'pluginversion' => $version,
            ]);
            return [run_store::get($runid), $report, null];
        } catch (\Throwable $e) {
            self::fail($runid, 'exception', get_class($e) . ': ' . $e->getMessage());
            return [run_store::get($runid), null, $e];
        }
    }

    /**
     * Entities of the audited course needed to tell a removed activity or section from a resolved problem.
     *
     * @param array $snapshot
     * @return array ['modules' => cmid => "modname|normalised name", 'sections' => section ids]
     */
    public static function entities(array $snapshot): array {
        $modules = [];
        foreach ($snapshot['modules'] as $cmid => $m) {
            $modules[(int) $cmid] = $m['modname'] . '|' . helpers::norm($m['name']);
        }
        $sections = array_values(array_map(fn($s) => (int) $s['id'], $snapshot['sections']));
        sort($sections);
        return ['modules' => $modules, 'sections' => $sections];
    }
}
