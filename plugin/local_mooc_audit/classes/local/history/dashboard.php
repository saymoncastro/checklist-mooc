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

use local_mooc_audit\local\status;

/**
 * Data of the follow-up dashboard (specs/005 FR-024 to FR-028, research R10).
 *
 * Every course of the scope (courses the viewer may audit) gets exactly one situation derived from its latest final
 * run: not audited, incomplete (failed or partial), with blocking issues, needs fixes, no non-conformity detected.
 * "Outdated" and "pending" are extra flags. Only the small columns of the runs are read, never the stored reports.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {
    /** @var string Situations, in priority order. */
    const NOT_AUDITED = 'not_audited';
    /** @var string */
    const INCOMPLETE = 'incomplete';
    /** @var string */
    const BLOCKING = 'blocking';
    /** @var string */
    const NEEDS_FIX = 'needs_fix';
    /** @var string */
    const NONE_DETECTED = 'none_detected';

    /** @var string Extra filters. */
    const STALE = 'stale';
    /** @var string */
    const PENDING = 'pending';

    /**
     * Situations in display order.
     *
     * @return string[]
     */
    public static function situations(): array {
        return [self::BLOCKING, self::NEEDS_FIX, self::INCOMPLETE, self::NOT_AUDITED, self::NONE_DETECTED];
    }

    /**
     * Days after which a result is outdated (setting history_stale_days).
     *
     * @return int
     */
    public static function stale_days(): int {
        $days = (int) get_config('local_mooc_audit', 'history_stale_days');
        return $days > 0 ? min(365, $days) : 7;
    }

    /**
     * Normalise the filters.
     *
     * @param array $f
     * @return array
     */
    public static function clean_filters(array $f): array {
        $situation = (string) ($f['situation'] ?? '');
        $severity = (string) ($f['severity'] ?? '');
        $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        return [
            'categoryid' => max(0, (int) ($f['categoryid'] ?? 0)),
            'subcats' => !isset($f['subcats']) || !empty($f['subcats']),
            'q' => trim((string) ($f['q'] ?? '')),
            'situation' => in_array($situation, array_merge(self::situations(), [self::STALE, self::PENDING]), true)
                ? $situation : '',
            'severity' => in_array($severity, status::severities(), true) ? $severity : '',
            'from' => $date($f['from'] ?? ''),
            'to' => $date($f['to'] ?? ''),
        ];
    }

    /**
     * Courses of the scope: category (and subcategories), text, and the viewer's audit capability.
     *
     * @param int $userid
     * @param array $f clean filters
     * @return \stdClass[] id => course
     */
    public static function scope(int $userid, array $f): array {
        global $DB;
        $where = ['c.id <> :site'];
        $params = ['site' => SITEID];
        if ($f['categoryid']) {
            $path = $DB->get_field('course_categories', 'path', ['id' => $f['categoryid']]);
            if ($path === false) {
                return [];
            }
            if ($f['subcats']) {
                $where[] = '(c.category = :catid OR cc.path LIKE :path)';
                $params['path'] = $path . '/%';
            } else {
                $where[] = 'c.category = :catid';
            }
            $params['catid'] = $f['categoryid'];
        }
        if ($f['q'] !== '') {
            $where[] = '(' . $DB->sql_like('c.fullname', ':q1', false, false) . ' OR '
                . $DB->sql_like('c.shortname', ':q2', false, false) . ')';
            $params['q1'] = $params['q2'] = '%' . $DB->sql_like_escape($f['q']) . '%';
        }
        $courses = $DB->get_records_sql(
            'SELECT c.id, c.shortname, c.fullname, c.category, c.startdate, c.visible, c.cacherev, c.timemodified,
                    cc.name AS categoryname
               FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY c.fullname, c.id',
            $params
        );
        return array_intersect_key($courses, access::auditable_course_ids($userid));
    }

    /**
     * Latest run per course (by end time) among some states, with the fingerprint of its policy.
     *
     * @param int[] $courseids
     * @param string[] $states
     * @return \stdClass[] courseid => run (small columns)
     */
    protected static function latest(array $courseids, array $states): array {
        global $DB;
        $runs = run_store::latest_per_course($states, $courseids, 'r.id, r.courseid, r.status, r.overall, r.policyid,
            r.pluginversion, r.summary, r.evolution, r.coursecacherev, r.timestarted, r.timefinished, r.details');
        $policyids = array_unique(array_filter(array_map(fn($r) => (int) $r->policyid, $runs)));
        $fps = $policyids ? $DB->get_records_list(policy_store::TABLE, 'id', $policyids, '', 'id, fingerprint') : [];
        foreach ($runs as $r) {
            $r->fingerprint = $fps[$r->policyid]->fingerprint ?? null;
            // Keep only the code fingerprint of the details (a few kB per room otherwise).
            $r->codefp = self::run_code($r);
            unset($r->details);
        }
        return $runs;
    }

    /**
     * Situation of a course from its latest final run.
     *
     * @param \stdClass|null $final
     * @return string
     */
    public static function situation(?\stdClass $final): string {
        if (!$final) {
            return self::NOT_AUDITED;
        }
        if ($final->status !== run_store::COMPLETE) {
            return self::INCOMPLETE;
        }
        return match ($final->overall) {
            status::OVERALL_BLOCKED => self::BLOCKING,
            status::OVERALL_NEEDS_FIX => self::NEEDS_FIX,
            default => self::NONE_DETECTED,
        };
    }

    /**
     * Why the latest valid result is outdated.
     *
     * @param \stdClass|null $valid
     * @param int $now
     * @param string $policyfp fingerprint of the policy in force
     * @param string $version current plugin version label (compared only for runs recorded without rule fingerprints)
     * @param \stdClass|null $course course with cacherev and timemodified (known change of the room since the run)
     * @return string[] age, course_changed, policy_changed, plugin_changed
     */
    public static function stale_reasons(
        ?\stdClass $valid,
        int $now,
        string $policyfp,
        string $version,
        ?\stdClass $course = null
    ): array {
        if (!$valid) {
            return [];
        }
        $out = [];
        // Activities and sections bump the course cache revision, course settings timemodified; the audit itself never
        // changes either (readonly_test). Runs recorded before the revision was stored compare timemodified only.
        if ($course) {
            $started = (int) ($valid->timestarted ?? $valid->timefinished);
            $seen = $valid->coursecacherev ?? null;
            if (($seen !== null && (int) $course->cacherev !== (int) $seen) || (int) $course->timemodified > $started) {
                $out[] = 'course_changed';
            }
        }
        if ($valid->timefinished < $now - self::stale_days() * DAYSECS) {
            $out[] = 'age';
        }
        if ((string) $valid->fingerprint !== $policyfp) {
            $out[] = 'policy_changed';
        }
        // A new plugin version only outdates the result when the code of the rules changed (A-03 of docs/revisao-geral).
        $code = self::run_code($valid);
        if ($code !== null ? $code !== fingerprint::current_code() : $valid->pluginversion !== $version) {
            $out[] = 'plugin_changed';
        }
        return $out;
    }

    /**
     * Code fingerprint of the rules that produced a run (from its details), or null when the run did not record it.
     *
     * @param \stdClass $run run with codefp (dashboard rows) or details
     * @return string|null
     */
    public static function run_code(\stdClass $run): ?string {
        if (property_exists($run, 'codefp')) {
            return $run->codefp;
        }
        $rules = run_store::json($run, 'details')['rules'] ?? null;
        return is_array($rules) && $rules ? fingerprint::code($rules) : null;
    }

    /**
     * Rows, counters and summed evolution for a viewer and filters.
     *
     * @param int $userid
     * @param array $filters raw filters
     * @param int|null $now
     * @return array ['filters', 'rows' => list, 'counts' => key => n, 'evolution' => class => n]
     */
    public static function build(int $userid, array $filters, ?int $now = null): array {
        $now = $now ?? time();
        $f = self::clean_filters($filters);
        $courses = self::scope($userid, $f);
        $ids = array_keys($courses);
        $finals = self::latest($ids, run_store::final_states());
        $valids = self::latest($ids, run_store::valid_states());
        $pending = run_store::alive_pending($ids, $now);
        $policyfp = policy_store::current_fingerprint();
        $version = run_store::plugin_version();
        $tz = \core_date::get_server_timezone_object();
        $from = $f['from'] ? (new \DateTime($f['from'] . ' 00:00:00', $tz))->getTimestamp() : null;
        $to = $f['to'] ? (new \DateTime($f['to'] . ' 00:00:00', $tz))->getTimestamp() + DAYSECS : null;

        $rows = [];
        foreach ($courses as $id => $c) {
            $final = $finals[$id] ?? null;
            $valid = $valids[$id] ?? null;
            $summary = $valid ? (run_store::json($valid, 'summary') ?? []) : [];
            $bysev = $summary['nao_conformes_por_gravidade'] ?? [];
            $row = (object) [
                'course' => $c,
                'situation' => self::situation($final),
                'final' => $final,
                'valid' => $valid,
                'pending' => isset($pending[$id]),
                'stale' => self::stale_reasons($valid, $now, $policyfp, $version, $c),
                'severity' => $bysev,
                'attention' => (int) ($summary['por_status'][status::ATENCAO] ?? 0),
                'evolution' => $valid ? (run_store::json($valid, 'evolution')['counts'] ?? null) : null,
                'lastaudit' => $final ? (int) $final->timefinished : null,
            ];
            if ($f['situation'] === self::STALE && !$row->stale) {
                continue;
            }
            if ($f['situation'] === self::PENDING && !$row->pending) {
                continue;
            }
            if (in_array($f['situation'], self::situations(), true) && $row->situation !== $f['situation']) {
                continue;
            }
            if ($f['severity'] !== '' && empty($bysev[$f['severity']])) {
                continue;
            }
            if (($from || $to) && (!$row->lastaudit || ($from && $row->lastaudit < $from) || ($to && $row->lastaudit >= $to))) {
                continue;
            }
            $rows[] = $row;
        }
        $order = array_flip(self::situations());
        usort($rows, fn($a, $b) => [$order[$a->situation], \core_text::strtolower($a->course->fullname), $a->course->id]
            <=> [$order[$b->situation], \core_text::strtolower($b->course->fullname), $b->course->id]);

        $counts = ['selected' => count($rows), 'audited' => 0, self::STALE => 0, self::PENDING => 0]
            + array_fill_keys(self::situations(), 0);
        $evolution = array_fill_keys(evolution::classes(), 0);
        foreach ($rows as $row) {
            $counts[$row->situation]++;
            // Audited = has at least one report; a course whose runs all failed was never audited.
            $counts['audited'] += $row->valid ? 1 : 0;
            $counts[self::STALE] += $row->stale ? 1 : 0;
            $counts[self::PENDING] += $row->pending ? 1 : 0;
            foreach ($row->evolution ?? [] as $class => $n) {
                $evolution[$class] = ($evolution[$class] ?? 0) + (int) $n;
            }
        }
        return ['filters' => $f, 'rows' => $rows, 'counts' => $counts, 'evolution' => $evolution];
    }

    /**
     * Courses that no longer exist but still have runs (their stored history stays readable from the dashboard).
     *
     * @return \stdClass[] courseid, runs, lastrun
     */
    public static function removed_courses(): array {
        global $DB;
        return array_values($DB->get_records_sql(
            'SELECT r.courseid, COUNT(1) AS runs, MAX(r.id) AS lastrun
               FROM {' . run_store::TABLE . '} r
          LEFT JOIN {course} c ON c.id = r.courseid
              WHERE c.id IS NULL
           GROUP BY r.courseid
           ORDER BY r.courseid'
        ));
    }
}
