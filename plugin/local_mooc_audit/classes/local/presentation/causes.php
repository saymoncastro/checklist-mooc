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

namespace local_mooc_audit\local\presentation;

use local_mooc_audit\local\status;

/**
 * Results that share a cause (specs/006, FR-038, relations R12 of tasks.md): a closed, tested list, never text
 * similarity. A result outside every relation is its own cause.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class causes {
    /**
     * Cause key of a stored result.
     *
     * @param array $r stored result
     * @param int $index position of the result in the report (unique key for results outside every relation)
     * @return string
     */
    public static function key(array $r, int $index): string {
        $occ = catalog::parse_occurrence($r['occurrence'] ?? null);
        $cmid = (int) ($r['cmid'] ?? 0);
        $rc = $r['rule'] . '|' . $occ['check'];
        if ($cmid && in_array($rc, ['CL-13|attempts', 'CL-13|intro_attempts'], true)) {
            // The same assisted correction (quiz_attempts) solves both; the simulation of the planner shows it.
            return 'attempts:' . $cmid;
        }
        if ($cmid && in_array($rc, ['CL-09|forum_completion', 'CL-17|completion', 'CL-19|completion'], true)) {
            return 'completion:' . $cmid;
        }
        if ($occ['check'] === 'certificate_presence' && in_array($r['rule'], ['CL-11', 'CL-23', 'X-CERT-01'], true)) {
            return 'certificate_presence';
        }
        if (
            $r['status'] === status::ATENCAO && $rc === 'X-DEP-02|target_completion'
                && preg_match('/^cm:(\d+)$/', (string) $occ['subject'], $m)
        ) {
            return 'hidden:' . $m[1];
        }
        if (
            $r['status'] === status::ATENCAO && $rc === 'X-STR-01|visibility' && $cmid
                && ($r['gravidade'] ?? '') !== status::INFORMATIVA
        ) {
            return 'hidden:' . $cmid;
        }
        return 'own:' . $index;
    }

    /**
     * Groups of results by cause, among the given states.
     *
     * @param array $results stored results
     * @param string[] $states states taken into account
     * @return array key => list of indexes, the main result first (state, then severity, then report order)
     */
    public static function groups(array $results, array $states = [status::NAO_CONFORME, status::ATENCAO]): array {
        $groups = [];
        foreach ($results as $i => $r) {
            if (in_array($r['status'], $states, true)) {
                $groups[self::key($r, $i)][] = $i;
            }
        }
        foreach ($groups as &$members) {
            usort($members, fn($a, $b) => [status::status_rank($results[$a]['status']),
                status::severity_rank($results[$a]['gravidade']), $a] <=> [status::status_rank($results[$b]['status']),
                status::severity_rank($results[$b]['gravidade']), $b]);
        }
        return $groups;
    }
}
