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

/**
 * Test doubles of the assisted correction (failures that cannot be produced through the Moodle API).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mooc_audit\local\correction;

defined('MOODLE_INTERNAL') || die();

/**
 * Writes the value, then fails inside the transaction (the write must be rolled back).
 */
class failing_write extends strategy\quiz_attempts {
    /**
     * Apply and fail.
     *
     * @param proposal $p
     * @param \cm_info $cm
     */
    public function apply(proposal $p, \cm_info $cm): void {
        parent::apply($p, $cm);
        throw new \moodle_exception('error', 'error', '', null, 'simulated failure after the write');
    }
}

/**
 * Writes the value but Moodle "does not read it back".
 */
class unreadable_write extends strategy\quiz_attempts {
    /**
     * Never persisted.
     *
     * @param proposal $p
     * @param \cm_info $cm
     * @return bool
     */
    public function persisted(proposal $p, \cm_info $cm): bool {
        return false;
    }
}

/**
 * Executor whose re-audit fails.
 */
class reaudit_fails_executor extends executor {
    /**
     * Fail.
     *
     * @param int $courseid
     * @param array|null $policyoverride
     * @return array
     */
    protected function reaudit(int $courseid, ?array $policyoverride): array {
        throw new \moodle_exception('error', 'error', '', null, 'simulated re-audit failure');
    }
}

/**
 * Executor that does not wait for the lock.
 */
class nowait_executor extends executor {
    /** @var int */
    const LOCK_TIMEOUT = 0;
}
