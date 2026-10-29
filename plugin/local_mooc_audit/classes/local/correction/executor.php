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

namespace local_mooc_audit\local\correction;

use core\lock\lock_config;
use local_mooc_audit\engine;

/**
 * Applies a confirmed correction proposal. The only class of the plugin that writes to course data.
 *
 * Order (any failed check ends without writing): capabilities (plugin capability in the course, core
 * manageactivities in the activity) → activity exists → per-activity lock → proposal rebuilt from the database →
 * fingerprint equal to the confirmed one → executable → in one transaction: the strategy re-reads the stored value and
 * the student data, writes, and the value is read back through the module API (any mismatch rolls back) → commit →
 * cache refresh and course_module_updated → full re-audit compared with the state before → trail row and log event.
 * Success is reported only for an applied correction whose value was read back.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class executor {
    /** @var string Written, read back and re-audited (see reaudit for the result of the rule). */
    const APPLIED = 'applied';
    /** @var string The write failed and was rolled back, or the value could not be read back. */
    const FAILED = 'failed';
    /** @var string The state changed since the preview: a new analysis is required. */
    const CONFLICT = 'conflict';
    /** @var string The proposal is not executable (impediments). */
    const REFUSED = 'refused';
    /** @var string There is no longer a finding to correct. */
    const NOTHING = 'nothing';
    /** @var string Another correction of the same activity is running. */
    const BUSY = 'busy';
    /** @var string The activity no longer exists. */
    const GONE = 'gone';
    /** @var int Seconds to wait for the per-activity lock. */
    const LOCK_TIMEOUT = 5;

    /** @var planner */
    protected planner $planner;

    /**
     * Constructor.
     *
     * @param planner|null $planner planner (tests may inject one with other strategies)
     */
    public function __construct(?planner $planner = null) {
        $this->planner = $planner ?? new planner();
    }

    /**
     * Apply the correction of a rule on an activity, if the current state still matches the confirmed proposal.
     *
     * @param int $courseid
     * @param string $ruleid
     * @param int $cmid
     * @param string $fingerprint fingerprint of the proposal the user confirmed
     * @param array|null $policyoverride
     * @return array status (class constants), proposal, reaudit, comparison, message, fixid
     */
    public function execute(int $courseid, string $ruleid, int $cmid, string $fingerprint, ?array $policyoverride = null): array {
        require_capability('local/mooc_audit:applycorrection', \context_course::instance($courseid));
        $cm = get_fast_modinfo($courseid)->cms[$cmid] ?? null;
        if (!$cm || $cm->deletioninprogress) {
            return $this->result(self::GONE);
        }
        require_capability('moodle/course:manageactivities', \context_module::instance($cmid));

        $lock = lock_config::get_lock_factory('local_mooc_audit')->get_lock('fix_' . $cmid, static::LOCK_TIMEOUT);
        if (!$lock) {
            return $this->result(self::BUSY);
        }
        try {
            return $this->run($courseid, $ruleid, $cmid, $fingerprint, $policyoverride);
        } finally {
            $lock->release();
        }
    }

    /**
     * The checks and the write, under the lock.
     *
     * @param int $courseid
     * @param string $ruleid
     * @param int $cmid
     * @param string $fingerprint
     * @param array|null $policyoverride
     * @return array
     */
    protected function run(int $courseid, string $ruleid, int $cmid, string $fingerprint, ?array $policyoverride): array {
        global $DB;
        $p = $this->planner->plan($courseid, $ruleid, $cmid, $policyoverride);
        if (!$p) {
            return $this->result(self::NOTHING);
        }
        if (!hash_equals($p->fingerprint(), $fingerprint)) {
            return $this->result(self::CONFLICT, $p);
        }
        if (!$p->is_executable()) {
            return $this->result(self::REFUSED, $p);
        }
        $strategy = $this->planner->strategy_for($p);

        $cm = get_fast_modinfo($courseid)->get_cm($cmid);
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $strategy->apply($p, $cm);
                // Moodle must read the new value through the module API before the write is kept: a mismatch rolls
                // everything back, so a failure never leaves a change behind.
                if (!$strategy->persisted($p, $cm)) {
                    throw new \moodle_exception('fix_notpersisted', 'local_mooc_audit');
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } catch (conflict_exception $e) {
            return $this->result(self::CONFLICT, $p, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $notpersisted = $e instanceof \moodle_exception && $e->errorcode === 'fix_notpersisted';
            $message = $notpersisted ? $e->getMessage() : self::short($e);
            $id = trail::record($p, trail::FAILED, verifier::SKIPPED, ['error' => $message]);
            return $this->result(self::FAILED, $p, ['message' => $message, 'fixid' => $id]);
        }

        $comparison = null;
        $message = '';
        // Cache refresh and standard event only after the commit (a rollback must not leave a cache at a new revision).
        try {
            $strategy->after_commit($cm);
        } catch (\Throwable $e) {
            $message = self::short($e);
        }
        try {
            $after = $this->reaudit($courseid, $policyoverride);
            $comparison = verifier::compare($p->baseline, $after['results'], $ruleid, $cmid);
            $reaudit = $comparison['outcome'];
            $details = ['resolved' => $comparison['resolved'], 'introduced' => $comparison['introduced']];
        } catch (\Throwable $e) {
            $reaudit = verifier::ERROR;
            $message = trim($message . ' ' . self::short($e));
            $details = ['error' => $message];
        }
        $id = trail::record($p, trail::APPLIED, $reaudit, $details);
        trail::log_applied($id, $p, $reaudit);
        return $this->result(self::APPLIED, $p, ['reaudit' => $reaudit, 'comparison' => $comparison, 'message' => $message,
            'fixid' => $id]);
    }

    /**
     * Full audit after the write (same engine and view as the audit page).
     *
     * @param int $courseid
     * @param array|null $policyoverride
     * @return array report
     */
    protected function reaudit(int $courseid, ?array $policyoverride): array {
        return engine::audit($courseid, $policyoverride, 0);
    }

    /**
     * Result array.
     *
     * @param string $status
     * @param proposal|null $p
     * @param array $extra
     * @return array
     */
    protected function result(string $status, ?proposal $p = null, array $extra = []): array {
        return array_merge(['status' => $status, 'proposal' => $p, 'reaudit' => verifier::SKIPPED, 'comparison' => null,
            'message' => '', 'fixid' => null], $extra);
    }

    /**
     * Short error text for the trail (no stack trace, bounded length).
     *
     * @param \Throwable $e
     * @return string
     */
    protected static function short(\Throwable $e): string {
        return \core_text::substr(get_class($e) . ': ' . $e->getMessage(), 0, 500);
    }
}
