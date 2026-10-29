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

namespace local_mooc_audit\local\rule;

/**
 * A rule of the audit catalogue.
 *
 * A rule is a pure function of the course snapshot and the institutional policy; it never reads the database nor
 * writes anything. It returns a list of findings (see base::f()).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface rule_interface {
    /**
     * Stable identifier (CL-nn for checklist items, X-AREA-nn for additional checks).
     *
     * @return string
     */
    public function id(): string;

    /**
     * Metadata: id, title, category, origin, type, severity, policy_keys, locus, guidance, source.
     *
     * @return array
     */
    public function meta(): array;

    /**
     * Evaluate the rule.
     *
     * @param array $snapshot course snapshot (see snapshot::build())
     * @param array $policy institutional policy (see policy::load())
     * @return array list of findings
     */
    public function evaluate(array $snapshot, array $policy): array;
}
