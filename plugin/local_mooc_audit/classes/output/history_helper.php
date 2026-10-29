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

namespace local_mooc_audit\output;

use local_mooc_audit\local\history\evolution;
use local_mooc_audit\local\history\run_store;
use local_mooc_audit\local\status;
use stdClass;

/**
 * Shared formatting of runs, situations and evolution counts for the history, dashboard and batch pages (specs/005).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_helper {
    /** @var string[] Badge class of each run status. */
    const RUN_CLASS = [
        run_store::QUEUED => 'badge-secondary',
        run_store::RUNNING => 'badge-info',
        run_store::COMPLETE => 'badge-success',
        run_store::PARTIAL => 'badge-warning',
        run_store::FAILED => 'badge-danger',
    ];

    /** @var string[] Badge class of each situation (none_detected is deliberately not "success": it is no approval). */
    const SITUATION_CLASS = [
        'blocking' => 'badge-danger',
        'needs_fix' => 'badge-warning',
        'incomplete' => 'badge-dark',
        'not_audited' => 'badge-secondary',
        'none_detected' => 'badge-info',
    ];

    /** @var string[] Badge class of each evolution class. */
    const EVOLUTION_CLASS = [
        evolution::NEW => 'badge-danger',
        evolution::CHANGED => 'badge-warning',
        evolution::PERSISTENT => 'badge-warning',
        evolution::REPLACED => 'badge-warning',
        evolution::UNVERIFIABLE => 'badge-secondary',
        evolution::NOT_COMPARABLE => 'badge-secondary',
        evolution::REMOVED => 'badge-light',
        evolution::RESOLVED => 'badge-success',
    ];

    /** @var array Cache of requester names. */
    protected static array $names = [];

    /**
     * Run status for templates.
     *
     * @param string $status
     * @return stdClass code, label, cssclass
     */
    public static function run_status(string $status): stdClass {
        return (object) ['code' => $status, 'label' => get_string('runstatus_' . $status, 'local_mooc_audit'),
            'cssclass' => self::RUN_CLASS[$status] ?? 'badge-secondary'];
    }

    /**
     * Situation for templates.
     *
     * @param string $situation
     * @return stdClass code, label, cssclass
     */
    public static function situation(string $situation): stdClass {
        return (object) ['code' => $situation, 'label' => get_string('situation_' . $situation, 'local_mooc_audit'),
            'cssclass' => self::SITUATION_CLASS[$situation] ?? 'badge-secondary'];
    }

    /**
     * Overall result label of a run (empty when the run has no report).
     *
     * @param string|null $overall
     * @return string
     */
    public static function overall(?string $overall): string {
        return $overall ? get_string('overall_' . strtolower($overall), 'local_mooc_audit') : '';
    }

    /**
     * Non-zero evolution counts in display order.
     *
     * @param array|null $counts class => n
     * @param bool $all include zero counts
     * @return array list of label, count, cssclass, code
     */
    public static function evolution_counts(?array $counts, bool $all = false): array {
        $out = [];
        foreach (evolution::classes() as $class) {
            $n = (int) ($counts[$class] ?? 0);
            if ($n || $all) {
                $out[] = (object) ['code' => $class, 'count' => $n, 'cssclass' => self::EVOLUTION_CLASS[$class],
                    'label' => get_string('evolution_class_' . $class, 'local_mooc_audit')];
            }
        }
        return $out;
    }

    /**
     * Non conformities per severity (always the five severities, in order).
     *
     * @param array $bysev severity => n
     * @return array list of label, count
     */
    public static function severities(array $bysev): array {
        return array_map(fn($s) => (object) ['label' => get_string(status::severity_string_key($s), 'local_mooc_audit'),
            'count' => (int) ($bysev[$s] ?? 0), 'code' => $s], status::severities());
    }

    /**
     * Name of a requester ("process" for 0 or a removed user).
     *
     * @param int $userid
     * @return string
     */
    public static function requester(int $userid): string {
        if (!$userid) {
            return get_string('history_requester_process', 'local_mooc_audit');
        }
        if (!isset(self::$names[$userid])) {
            $user = \core_user::get_user($userid);
            self::$names[$userid] = $user && !$user->deleted ? fullname($user)
                : get_string('history_requester_process', 'local_mooc_audit');
        }
        return self::$names[$userid];
    }

    /**
     * Errors and limitations of a run for templates.
     *
     * @param \stdClass $run
     * @return array list of code label, rule, message
     */
    public static function errors(\stdClass $run): array {
        return array_map(fn($e) => (object) [
            'label' => get_string('error_code_' . ($e['code'] ?? 'exception'), 'local_mooc_audit'),
            'rule' => $e['rule'] ?? null,
            'message' => $e['message'] ?? '',
        ], run_store::json($run, 'errors') ?? []);
    }

    /**
     * Date of a run event.
     *
     * @param int|null $time
     * @return string
     */
    public static function date(?int $time): string {
        return $time ? userdate($time, get_string('strftimedatetimeshort', 'langconfig')) : '';
    }
}
