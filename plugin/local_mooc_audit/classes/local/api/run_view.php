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

namespace local_mooc_audit\local\api;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_mooc_audit\local\history\evolution;
use local_mooc_audit\local\history\policy_store;
use local_mooc_audit\local\history\run_store;

/**
 * Projection of a stored run for the external API (RunView of specs/004 contracts/web-services.md).
 *
 * The stored report is returned as the JSON text recorded with the run, never rebuilt. The requester of the run is
 * never part of the projection.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_view {
    /**
     * Project a run.
     *
     * @param \stdClass $run full run record
     * @param bool $includereport add the stored report (complete and partial runs only)
     * @param bool $includeitems add the evolution items against the base run
     * @return array
     */
    public static function export(\stdClass $run, bool $includereport, bool $includeitems): array {
        $out = [
            'id' => (int) $run->id,
            'courseid' => (int) $run->courseid,
            'origin' => (string) $run->origin,
            'status' => (string) $run->status,
            'overall' => $run->overall === null || $run->overall === '' ? null : (string) $run->overall,
            'timecreated' => (int) $run->timecreated,
            'timestarted' => (int) ($run->timestarted ?? 0),
            'timefinished' => (int) ($run->timefinished ?? 0),
            'attempts' => (int) $run->attempts,
            'pluginversion' => (string) $run->pluginversion,
            'contract_version' => (string) $run->contractversion,
            'reofferyear' => (int) ($run->reofferyear ?? 0),
            'errors' => array_map(fn($e) => array_filter([
                'code' => (string) ($e['code'] ?? ''),
                'rule' => isset($e['rule']) ? (string) $e['rule'] : null,
                'message' => (string) ($e['message'] ?? ''),
            ], fn($v) => $v !== null), run_store::json($run, 'errors') ?? []),
        ];
        $policy = $run->policyid ? policy_store::get((int) $run->policyid) : null;
        if ($policy) {
            $out['policy'] = ['version' => (string) $policy->policyversion, 'fingerprint' => (string) $policy->fingerprint];
        }
        $summary = run_store::json($run, 'summary');
        if ($summary) {
            $out['summary'] = [
                'by_status' => self::pairs($summary['por_status'] ?? [], 'status'),
                'by_severity' => self::pairs($summary['nao_conformes_por_gravidade'] ?? [], 'severity'),
                'rules' => (int) ($summary['regras'] ?? 0),
                'overall_status' => (string) ($summary['overall_status'] ?? ''),
            ];
        }
        $evo = run_store::json($run, 'evolution');
        if ($evo) {
            $out['evolution'] = ['baserunid' => (int) ($evo['base'] ?? 0), 'notes' => array_values(array_map(
                'strval',
                $evo['notes'] ?? []
            ))];
            if (isset($evo['counts']) && is_array($evo['counts'])) {
                $out['evolution']['counts'] = [];
                foreach (evolution::classes() as $class) {
                    $out['evolution']['counts'][$class] = (int) ($evo['counts'][$class] ?? 0);
                }
            }
            if ($includeitems) {
                $cmp = evolution::for_run($run);
                if ($cmp) {
                    $out['evolution']['items'] = array_map([self::class, 'item'], $cmp['items']);
                } else {
                    // The base was removed by the retention: only the stored counts remain.
                    $out['evolution']['items_unavailable'] = true;
                }
            }
        }
        if ($includereport && in_array($run->status, run_store::valid_states(), true) && (string) $run->report !== '') {
            $out['report'] = (string) $run->report;
        }
        return $out;
    }

    /**
     * Map of counts as a list (external structures have no free keys).
     *
     * @param array $map
     * @param string $name key name in each pair
     * @return array
     */
    protected static function pairs(array $map, string $name): array {
        $out = [];
        foreach ($map as $k => $n) {
            $out[] = [$name => (string) $k, 'count' => (int) $n];
        }
        return $out;
    }

    /**
     * One evolution item without its null fields.
     *
     * @param array $item evolution::compare() item
     * @return array
     */
    protected static function item(array $item): array {
        $out = [];
        foreach ($item as $k => $v) {
            if ($v !== null) {
                $out[$k] = in_array($k, ['cmid', 'successor_cmid'], true) ? (int) $v : (string) $v;
            }
        }
        return $out;
    }

    /**
     * Structure of a projected run.
     *
     * @return external_single_structure
     */
    public static function structure(): external_single_structure {
        $counts = [];
        foreach (evolution::classes() as $class) {
            $counts[$class] = new external_value(PARAM_INT, 'Problems of class ' . $class);
        }
        $opt = fn(string $type, string $desc) => new external_value($type, $desc, VALUE_OPTIONAL);
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Run id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'origin' => new external_value(PARAM_ALPHA, 'Where the audit was requested: page, batch, cli, correction, api'),
            'status' => new external_value(PARAM_ALPHA, 'queued, running, complete, partial or failed'),
            'overall' => new external_value(
                PARAM_ALPHAEXT,
                'Overall result; null without a report',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'timecreated' => new external_value(PARAM_INT, 'Request time'),
            'timestarted' => new external_value(PARAM_INT, 'Start time (0 = not started)'),
            'timefinished' => new external_value(PARAM_INT, 'End time (0 = not finished)'),
            'attempts' => new external_value(PARAM_INT, 'Times a task started the run'),
            'pluginversion' => new external_value(PARAM_TEXT, 'Plugin version that recorded the run'),
            'contract_version' => new external_value(PARAM_TEXT, 'Version of the report contract'),
            'policy' => new external_single_structure([
                'version' => new external_value(PARAM_TEXT, 'Policy version'),
                'fingerprint' => new external_value(PARAM_ALPHANUMEXT, 'Policy fingerprint'),
            ], 'Applied policy', VALUE_OPTIONAL),
            'reofferyear' => new external_value(PARAM_INT, 'Reoffer year audited (0 = unknown)'),
            'summary' => new external_single_structure([
                'by_status' => new external_multiple_structure(new external_single_structure([
                    'status' => new external_value(PARAM_ALPHAEXT, 'Result state'),
                    'count' => new external_value(PARAM_INT, 'Number of results'),
                ])),
                'by_severity' => new external_multiple_structure(new external_single_structure([
                    'severity' => new external_value(PARAM_TEXT, 'Severity'),
                    'count' => new external_value(PARAM_INT, 'Number of non conformant results'),
                ])),
                'rules' => new external_value(PARAM_INT, 'Rules in the catalogue'),
                'overall_status' => new external_value(PARAM_ALPHAEXT, 'Overall result'),
            ], 'Summary of the report', VALUE_OPTIONAL),
            'errors' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHAEXT, 'Failure or limitation code'),
                'rule' => $opt(PARAM_ALPHANUMEXT, 'Rule'),
                'message' => new external_value(PARAM_RAW, 'Message'),
            ]), 'Failures and limitations'),
            'evolution' => new external_single_structure([
                'baserunid' => new external_value(PARAM_INT, 'Run compared with'),
                'counts' => new external_single_structure(
                    $counts,
                    'Problems per class (absent when not comparable)',
                    VALUE_OPTIONAL
                ),
                'notes' => new external_multiple_structure(new external_value(PARAM_ALPHAEXT, 'Note code')),
                'items' => new external_multiple_structure(new external_single_structure([
                    'class' => new external_value(PARAM_ALPHAEXT, 'Evolution class'),
                    'reason' => $opt(PARAM_ALPHAEXT, 'Why not comparable'),
                    'key' => new external_value(PARAM_RAW, 'Occurrence key'),
                    'rule' => new external_value(PARAM_ALPHANUMEXT, 'Rule'),
                    'title' => new external_value(PARAM_RAW, 'Rule title'),
                    'severity' => new external_value(PARAM_TEXT, 'Severity'),
                    'status_before' => $opt(PARAM_ALPHAEXT, 'State in the base run'),
                    'status_after' => $opt(PARAM_ALPHAEXT, 'State in this run'),
                    'severity_before' => $opt(PARAM_TEXT, 'Severity in the base run'),
                    'severity_after' => $opt(PARAM_TEXT, 'Severity in this run'),
                    'local' => $opt(PARAM_RAW, 'Where'),
                    'cmid' => $opt(PARAM_INT, 'Activity'),
                    'link' => $opt(PARAM_RAW, 'Link to the item'),
                    'evidence_before' => $opt(PARAM_RAW, 'Evidence in the base run'),
                    'evidence_after' => $opt(PARAM_RAW, 'Evidence in this run'),
                    'successor_cmid' => $opt(PARAM_INT, 'Replacing activity'),
                ]), 'Evolution items (includeevolutionitems=1)', VALUE_OPTIONAL),
                'items_unavailable' => $opt(PARAM_BOOL, 'The base run was removed: items cannot be listed'),
            ], 'Evolution against the previous valid run', VALUE_OPTIONAL),
            'report' => $opt(PARAM_RAW, 'Stored report as JSON text (contract 1.0), complete and partial runs only'),
        ]);
    }
}
