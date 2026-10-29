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

/**
 * Writes the value, then fails inside the transaction (the write must be rolled back).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
