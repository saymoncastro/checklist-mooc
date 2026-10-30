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

use local_mooc_audit\local\status;

/**
 * Compares two lists of audit results (before × after a correction, real or simulated). Pure: no database access.
 *
 * Findings are counted per (rule, activity, location) and state, because a rule may report several findings for the
 * same activity (CL-13: attempts and statement) and the evidence text changes with the value. A group whose count of
 * NAO_CONFORME (or ATENCAO) grows was introduced; one whose count shrinks was resolved.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verifier {
    /** @var string Re-audit outcome: target resolved, nothing introduced. */
    const RESOLVED = 'resolved';
    /** @var string Re-audit outcome: the target finding is still there. */
    const NOT_RESOLVED = 'not_resolved';
    /** @var string Re-audit outcome: target resolved but another non conformity appeared. */
    const INTRODUCED = 'introduced';
    /** @var string Re-audit could not run. */
    const ERROR = 'error';
    /** @var string Re-audit not run (nothing was written). */
    const SKIPPED = 'skipped';

    /**
     * Compare results.
     *
     * @param array $before results before the change
     * @param array $after results after the change
     * @param string $ruleid target rule
     * @param int $cmid target activity
     * @param callable|null $describe fn(array $result): string for the lists (default "RULE — location: evidence")
     * @return array targetbefore, targetafter (statuses of the target findings), targetresolved, resolved, introduced,
     *               introducedwarnings (lists of descriptions), outcome (one of the constants)
     */
    public static function compare(array $before, array $after, string $ruleid, int $cmid, ?callable $describe = null): array {
        $cb = self::counts($before, $describe);
        $ca = self::counts($after, $describe);
        $resolved = [];
        $introduced = [];
        $introducedwarnings = [];
        foreach ([status::NAO_CONFORME, status::ATENCAO] as $st) {
            foreach (array_unique(array_merge(array_keys($cb[$st]), array_keys($ca[$st]))) as $key) {
                $nb = count($cb[$st][$key] ?? []);
                $na = count($ca[$st][$key] ?? []);
                if ($na > $nb) {
                    $list = array_slice($ca[$st][$key], $nb);
                    if ($st === status::NAO_CONFORME) {
                        $introduced = array_merge($introduced, $list);
                    } else {
                        $introducedwarnings = array_merge($introducedwarnings, $list);
                    }
                } else if ($na < $nb) {
                    $resolved = array_merge($resolved, array_slice($cb[$st][$key], $na));
                }
            }
        }
        $targetbefore = self::target($before, $ruleid, $cmid);
        $targetafter = self::target($after, $ruleid, $cmid);
        $ncafter = count(array_keys($targetafter, status::NAO_CONFORME, true));
        $ncbefore = count(array_keys($targetbefore, status::NAO_CONFORME, true));
        $wbefore = count(array_keys($targetbefore, status::ATENCAO, true));
        $wafter = count(array_keys($targetafter, status::ATENCAO, true));
        // Resolved when no non conformity of the rule remains on the activity and something improved (a non conformity or,
        // when the target was a warning, a warning disappeared).
        $targetresolved = $ncafter === 0 && ($ncbefore > 0 || $wafter < $wbefore);
        if (!$targetresolved) {
            $outcome = self::NOT_RESOLVED;
        } else if ($introduced) {
            $outcome = self::INTRODUCED;
        } else {
            $outcome = self::RESOLVED;
        }
        return [
            'targetbefore' => $targetbefore,
            'targetafter' => $targetafter,
            'targetresolved' => $targetresolved,
            'resolved' => $resolved,
            'introduced' => $introduced,
            'introducedwarnings' => $introducedwarnings,
            'outcome' => $outcome,
        ];
    }

    /**
     * Statuses of the findings of a rule on an activity.
     *
     * @param array $results
     * @param string $ruleid
     * @param int $cmid
     * @return string[]
     */
    public static function target(array $results, string $ruleid, int $cmid): array {
        $out = [];
        foreach ($results as $r) {
            if ($r['rule'] === $ruleid && (int) ($r['cmid'] ?? 0) === $cmid) {
                $out[] = $r['status'];
            }
        }
        return $out;
    }

    /**
     * Findings grouped by state then by (rule, activity, location); values are human readable descriptions.
     *
     * @param array $results
     * @param callable|null $describe fn(array $result): string; default rule, location and evidence
     * @return array
     */
    protected static function counts(array $results, ?callable $describe = null): array {
        $out = [status::NAO_CONFORME => [], status::ATENCAO => []];
        foreach ($results as $r) {
            if (!isset($out[$r['status']])) {
                continue;
            }
            $key = $r['rule'] . '|' . (int) ($r['cmid'] ?? 0) . '|' . ($r['local'] ?? '');
            $out[$r['status']][$key][] = $describe ? $describe($r)
                : $r['rule'] . ' — ' . ($r['local'] ?? '-') . ': ' . ($r['evidencia'] ?? '');
        }
        return $out;
    }
}
