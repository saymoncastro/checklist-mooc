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

use local_mooc_audit\engine;
use local_mooc_audit\event\correction_applied;

/**
 * Minimal trail of the assisted corrections: one row per confirmed execution that reached the write step (applied or
 * failed) and the standard log event of applied corrections. No course content, no student data; the only personal
 * datum is the id of the user who confirmed (see privacy\provider).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trail {
    /** @var string Table. */
    const TABLE = 'local_mooc_audit_fix';
    /** @var string The value was written and read back. */
    const APPLIED = 'applied';
    /** @var string The write failed (rolled back) or the value could not be read back. */
    const FAILED = 'failed';

    /**
     * Record an execution.
     *
     * @param proposal $p the confirmed proposal
     * @param string $outcome APPLIED or FAILED
     * @param string $reaudit verifier outcome (resolved, not_resolved, introduced, error, skipped)
     * @param array $details short data: rules resolved/introduced or the error message
     * @return int row id
     */
    public static function record(proposal $p, string $outcome, string $reaudit, array $details = []): int {
        global $DB, $USER;
        return $DB->insert_record(self::TABLE, (object) [
            'courseid' => $p->courseid,
            'cmid' => $p->cmid,
            'ruleid' => $p->ruleid,
            'strategy' => $p->strategy,
            'field' => (string) $p->field,
            'oldvalue' => \core_text::substr((string) $p->current, 0, 255),
            'newvalue' => \core_text::substr((string) $p->proposed, 0, 255),
            'outcome' => $outcome,
            'reaudit' => $reaudit,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'policyversion' => \core_text::substr($p->policyversion, 0, 100),
            'engineversion' => engine::VERSION,
            'userid' => (int) $USER->id,
            'timecreated' => time(),
        ]);
    }

    /**
     * Log the standard event of an applied correction.
     *
     * @param int $id row id
     * @param proposal $p
     * @param string $reaudit
     */
    public static function log_applied(int $id, proposal $p, string $reaudit): void {
        correction_applied::create([
            'context' => \context_module::instance($p->cmid),
            'objectid' => $id,
            'other' => ['ruleid' => $p->ruleid, 'field' => (string) $p->field, 'oldvalue' => (string) $p->current,
                'newvalue' => (string) $p->proposed, 'reaudit' => $reaudit],
        ])->trigger();
    }

    /**
     * Rows of an activity, newest first.
     *
     * @param int $cmid
     * @param int $limit
     * @return array
     */
    public static function for_activity(int $cmid, int $limit = 10): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['cmid' => $cmid], 'timecreated DESC, id DESC', '*', 0, $limit));
    }
}
