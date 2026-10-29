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

use local_mooc_audit\local\correction\trail;
use local_mooc_audit\local\status;

/**
 * Evolution of the problems of a course between two runs (specs/005 data-model, "Evolução").
 *
 * Occurrences are matched by their structured key only. A problem (NAO_CONFORME or ATENCAO) that disappears is
 * "resolved" only when the rule ran normally, the entity still exists and neither the rule's policy slice nor its code
 * changed; otherwise it is "unverifiable", "removed", "replaced" or "not comparable". Problems that appear in a rule
 * whose policy or code changed, or that the base run did not verify, are "not comparable" instead of "new".
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evolution {
    /** @var string Problem only in the current run. */
    const NEW = 'new';
    /** @var string Same problem, same state and severity. */
    const PERSISTENT = 'persistent';
    /** @var string Same problem, other state or severity. */
    const CHANGED = 'changed';
    /** @var string Problem gone after a normal verification. */
    const RESOLVED = 'resolved';
    /** @var string The current run could not verify it. */
    const UNVERIFIABLE = 'unverifiable';
    /** @var string Policy or rule changed, or the base did not verify it. */
    const NOT_COMPARABLE = 'not_comparable';
    /** @var string The activity or section no longer exists. */
    const REMOVED = 'removed';
    /** @var string The activity was replaced by one of the same type and name that has the same problem. */
    const REPLACED = 'replaced';

    /**
     * Classes in display order (what needs attention first).
     *
     * @return string[]
     */
    public static function classes(): array {
        return [self::NEW, self::CHANGED, self::PERSISTENT, self::REPLACED, self::UNVERIFIABLE, self::NOT_COMPARABLE,
            self::REMOVED, self::RESOLVED];
    }

    /**
     * Whether a state is a problem.
     *
     * @param string|null $state
     * @return bool
     */
    public static function is_problem(?string $state): bool {
        return $state === status::NAO_CONFORME || $state === status::ATENCAO;
    }

    /**
     * Whether a state means "not verified".
     *
     * @param string|null $state
     * @return bool
     */
    protected static function unverified(?string $state): bool {
        return $state === status::ERRO || $state === status::NAO_VERIFICAVEL;
    }

    /**
     * Entity part of an occurrence key.
     *
     * @param string $key
     * @return array [type, id] with type course, cm or sec
     */
    public static function entity(string $key): array {
        $entity = explode('|', $key)[1] ?? 'course';
        if (preg_match('/^(cm|sec):(\d+)$/', $entity, $m)) {
            return [$m[1], (int) $m[2]];
        }
        return ['course', 0];
    }

    /**
     * Rules not fully verified in a report: the engine's single ERRO / NAO_VERIFICAVEL replacing the rule, or any course
     * level result of the rule in one of those states (e.g. CL-12 without the course total). A problem of such a rule
     * that disappears is "not verifiable now", never "resolved".
     *
     * @param array $results
     * @return array rule => true
     */
    protected static function rules_not_run(array $results): array {
        $out = [];
        foreach ($results as $r) {
            if (self::unverified($r['status']) && self::entity($r['occurrence'])[0] === 'course') {
                $out[$r['rule']] = true;
            }
        }
        return $out;
    }

    /**
     * Activities removed in the current run that have exactly one successor of the same type and normalised name.
     *
     * @param array $before cmid => "modname|name"
     * @param array $after cmid => "modname|name"
     * @return array removed cmid => successor cmid
     */
    protected static function successors(array $before, array $after): array {
        $removed = [];
        $added = [];
        foreach (array_diff_key($before, $after) as $cmid => $k) {
            $removed[$k][] = (int) $cmid;
        }
        foreach (array_diff_key($after, $before) as $cmid => $k) {
            $added[$k][] = (int) $cmid;
        }
        $out = [];
        foreach ($removed as $k => $cmids) {
            if (count($cmids) === 1 && count($added[$k] ?? []) === 1) {
                $out[$cmids[0]] = $added[$k][0];
            }
        }
        return $out;
    }

    /** @var string A field of an occurrence key that references an activity (entity or subject): "cm:<cmid>". */
    const CM_REF = '/(?<=\|)cm:(\d+)(?=\||$)/';
    /** @var string A field of an occurrence key that references an enrolment instance: "enrol:<id>". */
    const ENROL_REF = '/(?<=\|)enrol:(\d+)(?=\||$)/';

    /**
     * The key with every reference to a replaced activity (entity or subject, e.g. the target of an X-DEP-02 condition)
     * rewritten to its successor.
     *
     * @param string $key
     * @param array $successors removed cmid => successor cmid
     * @return array [remapped key, first successor used or null]
     */
    protected static function remap(string $key, array $successors): array {
        $first = null;
        $remapped = preg_replace_callback(self::CM_REF, function ($m) use ($successors, &$first) {
            if (!isset($successors[(int) $m[1]])) {
                return $m[0];
            }
            $first = $first ?? $successors[(int) $m[1]];
            return 'cm:' . $successors[(int) $m[1]];
        }, $key);
        return [$remapped, $first];
    }

    /**
     * Whether an occurrence references something that existed in the base run and no longer exists: an activity
     * (entity or subject), a section, or an enrolment instance (when both runs recorded their enrolment ids).
     *
     * @param string $key
     * @param array $base details of the base run
     * @param array $current details of the current run
     * @return bool
     */
    protected static function references_removed(string $key, array $base, array $current): bool {
        [$type, $id] = self::entity($key);
        if ($type === 'sec' && !in_array($id, $current['sections'] ?? [], true)) {
            return true;
        }
        preg_match_all(self::CM_REF, $key, $m);
        foreach ($m[1] as $cmid) {
            if (isset($base['modules'][$cmid]) && !isset($current['modules'][$cmid])) {
                return true;
            }
        }
        if (isset($base['enrols'], $current['enrols'])) {
            preg_match_all(self::ENROL_REF, $key, $m);
            foreach ($m[1] as $enrolid) {
                if (in_array((int) $enrolid, $base['enrols'], true) && !in_array((int) $enrolid, $current['enrols'], true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Why the results of a rule may differ for reasons outside the course.
     *
     * @param string $rule
     * @param array $before rule fingerprints of the base run
     * @param array $after rule fingerprints of the current run
     * @return string|null policy_changed, rule_changed, rule_added, rule_removed or null
     */
    protected static function rule_change(string $rule, array $before, array $after): ?string {
        if (!isset($before[$rule])) {
            return 'rule_added';
        }
        if (!isset($after[$rule])) {
            return 'rule_removed';
        }
        if ($before[$rule]['p'] !== $after[$rule]['p']) {
            return 'policy_changed';
        }
        if ($before[$rule]['r'] !== $after[$rule]['r']) {
            return 'rule_changed';
        }
        return null;
    }

    /**
     * Compare two runs of the same course.
     *
     * @param array $base ['report' => array, 'details' => array, 'pluginversion' => string] of the older run
     * @param array $current same for the newer run
     * @return array ['items' => list, 'counts' => class => n (null when not comparable at all), 'notes' => string[]]
     */
    public static function compare(array $base, array $current): array {
        $notes = [];
        if (($base['pluginversion'] ?? '') !== ($current['pluginversion'] ?? '')) {
            $notes[] = 'plugin_version_changed';
        }
        $bresults = $base['report']['results'] ?? [];
        $cresults = $current['report']['results'] ?? [];
        foreach ([$bresults, $cresults] as $results) {
            foreach ($results as $r) {
                if (empty($r['occurrence'])) {
                    return ['items' => [], 'counts' => null, 'notes' => array_merge($notes, ['base_without_occurrences'])];
                }
            }
        }
        $b = array_column($bresults, null, 'occurrence');
        $c = array_column($cresults, null, 'occurrence');
        $bfp = $base['details']['rules'] ?? [];
        $cfp = $current['details']['rules'] ?? [];
        $bnotrun = self::rules_not_run($bresults);
        $cnotrun = self::rules_not_run($cresults);
        $bdetails = $base['details'] ?? [];
        $cdetails = $current['details'] ?? [];
        $successors = self::successors($bdetails['modules'] ?? [], $cdetails['modules'] ?? []);

        $items = [];
        $consumed = [];
        foreach ($b as $key => $old) {
            if (!self::is_problem($old['status'])) {
                continue;
            }
            $new = $c[$key] ?? null;
            $reason = null;
            $successor = null;
            [$skey, $successor] = self::remap($key, $successors);
            if ($new && self::is_problem($new['status'])) {
                $class = $old['status'] === $new['status'] && $old['gravidade'] === $new['gravidade']
                    ? self::PERSISTENT : self::CHANGED;
                $successor = null;
            } else if ($skey !== $key && isset($c[$skey]) && self::is_problem($c[$skey]['status'])) {
                // The activity, or an activity it refers to, was replaced and the problem reappears on the successor.
                $class = self::REPLACED;
                $new = $c[$skey];
                $consumed[$skey] = true;
            } else if (self::references_removed($key, $bdetails, $cdetails)) {
                $class = self::REMOVED;
            } else if (($new && self::unverified($new['status'])) || isset($cnotrun[$old['rule']])) {
                $class = self::UNVERIFIABLE;
            } else {
                $reason = self::rule_change($old['rule'], $bfp, $cfp);
                $class = $reason ? self::NOT_COMPARABLE : self::RESOLVED;
            }
            $items[] = self::item($class, $reason, $key, $old, $new, $successor);
        }
        foreach ($c as $key => $new) {
            if (!self::is_problem($new['status']) || isset($consumed[$key])) {
                continue;
            }
            $old = $b[$key] ?? null;
            if ($old && self::is_problem($old['status'])) {
                continue;
            }
            $reason = null;
            if (($old && self::unverified($old['status'])) || isset($bnotrun[$new['rule']])) {
                $reason = 'previously_unverified';
            } else {
                $reason = self::rule_change($new['rule'], $bfp, $cfp);
            }
            $items[] = self::item($reason ? self::NOT_COMPARABLE : self::NEW, $reason, $key, $old, $new, null);
        }

        $order = array_flip(self::classes());
        usort($items, fn($x, $y) => [$order[$x['class']], status::severity_rank($x['severity']), $x['rule'], $x['key']]
            <=> [$order[$y['class']], status::severity_rank($y['severity']), $y['rule'], $y['key']]);
        $counts = array_fill_keys(self::classes(), 0);
        foreach ($items as $i) {
            $counts[$i['class']]++;
        }
        return ['items' => $items, 'counts' => $counts, 'notes' => $notes];
    }

    /**
     * One evolution item.
     *
     * @param string $class
     * @param string|null $reason
     * @param string $key
     * @param array|null $old result in the base run
     * @param array|null $new result in the current run
     * @param int|null $successor cmid of the replacing activity
     * @return array
     */
    protected static function item(string $class, ?string $reason, string $key, ?array $old, ?array $new, ?int $successor): array {
        $r = $new ?? $old;
        $problem = $new && self::is_problem($new['status']) ? $new : $old;
        return [
            'class' => $class,
            'reason' => $reason,
            'key' => $key,
            'rule' => $r['rule'],
            'title' => $r['titulo'],
            'severity' => $problem['gravidade'],
            'status_before' => $old['status'] ?? null,
            'status_after' => $new['status'] ?? null,
            'severity_before' => $old['gravidade'] ?? null,
            'severity_after' => $new['gravidade'] ?? null,
            'local' => $r['local'],
            'cmid' => $r['cmid'],
            'link' => $new['link'] ?? null,
            'evidence_before' => $old['evidencia'] ?? null,
            'evidence_after' => $new['evidencia'] ?? null,
            'successor_cmid' => $successor,
        ];
    }

    /**
     * Assisted corrections recorded for the course between two moments (read from the specs/002 trail, never copied).
     *
     * @param int $courseid
     * @param int $from inclusive lower bound (end of the base run)
     * @param int $to inclusive upper bound (start of the current run)
     * @return \stdClass[]
     */
    public static function corrections_between(int $courseid, int $from, int $to): array {
        global $DB;
        return array_values($DB->get_records_select(
            trail::TABLE,
            'courseid = :courseid AND timecreated >= :from AND timecreated <= :to',
            ['courseid' => $courseid, 'from' => $from, 'to' => $to],
            'timecreated ASC, id ASC',
            'id, cmid, ruleid, field, oldvalue, newvalue, outcome, reaudit, userid, timecreated'
        ));
    }
}
