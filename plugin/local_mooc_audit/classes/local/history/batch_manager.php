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

/**
 * Batches of background audits (specs/005 FR-016 to FR-023).
 *
 * Selection by category, list of ids, start year or a selection made on the dashboard. Every course is checked against
 * the requester's local/mooc_audit:audit capability; refused courses are kept with the reason. A course that already
 * has a live queued or running run gets no second one: the batch records the run it reuses. Progress is derived from the runs
 * of the batch, never stored twice.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batch_manager {
    /** @var string Table. */
    const TABLE = 'local_mooc_audit_batch';

    /** @var string Selection by category. */
    const MODE_CATEGORY = 'category';
    /** @var string Selection by list of course ids. */
    const MODE_IDS = 'ids';
    /** @var string Selection by year of the course start date. */
    const MODE_STARTYEAR = 'startyear';
    /** @var string Selection made on the dashboard. */
    const MODE_SELECTION = 'selection';

    /** @var string Refusal reasons. */
    const SKIP_NOT_FOUND = 'not_found';
    /** @var string */
    const SKIP_SITE = 'site';
    /** @var string */
    const SKIP_PERMISSION = 'permission';
    /** @var string The course already has a queued or running run (reused). */
    const SKIP_DUPLICATE = 'duplicate';

    /**
     * Largest batch accepted (setting batch_max_courses).
     *
     * @return int
     */
    public static function max_courses(): int {
        $max = (int) get_config('local_mooc_audit', 'batch_max_courses');
        return $max > 0 ? min(2000, $max) : 100;
    }

    /**
     * Normalise the selection criteria.
     *
     * @param array $criteria mode + categoryid/subcategories, courseids, startyear
     * @return array
     */
    public static function clean_criteria(array $criteria): array {
        $mode = $criteria['mode'] ?? '';
        if (!in_array($mode, [self::MODE_CATEGORY, self::MODE_IDS, self::MODE_STARTYEAR, self::MODE_SELECTION], true)) {
            throw new \invalid_parameter_exception('mode');
        }
        $ids = $criteria['courseids'] ?? [];
        if (is_string($ids)) {
            $ids = preg_split('/[\s,;]+/', $ids, -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn($i) => $i > 0)));
        return [
            'mode' => $mode,
            'categoryid' => (int) ($criteria['categoryid'] ?? 0),
            'subcategories' => !empty($criteria['subcategories']),
            'courseids' => in_array($mode, [self::MODE_IDS, self::MODE_SELECTION], true) ? $ids : [],
            'startyear' => (int) ($criteria['startyear'] ?? 0),
            // Explicit cycle (reoffer year) applied to the audits of the batch; 0 = the policy default (decision 12.6).
            'reofferyear' => max(0, (int) ($criteria['reofferyear'] ?? 0)),
        ];
    }

    /**
     * Course ids matching the criteria, before permission checks.
     *
     * @param array $c clean criteria
     * @return int[]
     */
    protected static function candidates(array $c): array {
        global $DB;
        switch ($c['mode']) {
            case self::MODE_CATEGORY:
                $cat = $DB->get_record('course_categories', ['id' => $c['categoryid']], 'id, path');
                if (!$cat) {
                    return [];
                }
                $where = 'c.category = :catid';
                $params = ['catid' => $cat->id];
                if ($c['subcategories']) {
                    $where = '(c.category = :catid OR cc.path LIKE :path)';
                    $params['path'] = $cat->path . '/%';
                }
                return array_map('intval', $DB->get_fieldset_sql(
                    "SELECT c.id FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
                      WHERE $where ORDER BY c.id",
                    $params
                ));
            case self::MODE_STARTYEAR:
                $tz = \core_date::get_server_timezone_object();
                $from = (new \DateTime($c['startyear'] . '-01-01 00:00:00', $tz))->getTimestamp();
                $to = (new \DateTime(($c['startyear'] + 1) . '-01-01 00:00:00', $tz))->getTimestamp();
                return array_map('intval', $DB->get_fieldset_select(
                    'course',
                    'id',
                    'id <> :site AND startdate >= :from AND startdate < :to ORDER BY id',
                    ['site' => SITEID, 'from' => $from, 'to' => $to]
                ));
            default:
                return $c['courseids'];
        }
    }

    /**
     * Resolve a selection for a requester: accepted courses and refused ones with the reason.
     *
     * @param array $criteria
     * @param int $userid requester
     * @return array ['criteria' => clean, 'accepted' => courseid => course record, 'skipped' => list, 'toomany' => bool]
     */
    public static function resolve(array $criteria, int $userid): array {
        global $DB;
        $c = self::clean_criteria($criteria);
        $ids = self::candidates($c);
        $existing = [];
        if ($ids) {
            [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $existing = $DB->get_records_select('course', "id $in", $params, 'id', 'id, shortname, fullname, category, startdate');
        }
        $accepted = [];
        $skipped = [];
        $auditable = access::auditable_course_ids($userid);
        foreach ($ids as $id) {
            if ($id == SITEID) {
                $skipped[] = ['courseid' => $id, 'reason' => self::SKIP_SITE];
            } else if (!isset($existing[$id])) {
                $skipped[] = ['courseid' => $id, 'reason' => self::SKIP_NOT_FOUND];
            } else if (!isset($auditable[$id])) {
                $skipped[] = ['courseid' => $id, 'reason' => self::SKIP_PERMISSION];
            } else {
                $accepted[$id] = $existing[$id];
            }
        }
        return ['criteria' => $c, 'accepted' => $accepted, 'skipped' => $skipped,
            'toomany' => count($accepted) > self::max_courses()];
    }

    /**
     * Create a batch and queue one run per accepted course.
     *
     * @param array $criteria
     * @param int $userid requester (must hold local/mooc_audit:batchaudit)
     * @param string $name label (default: mode and date)
     * @return array ['batchid' => int (0 when nothing was created), 'queued' => int, 'reused' => int, 'skipped' => list,
     *     'error' => string|null (nothing_to_audit or toomany)]
     */
    public static function create(array $criteria, int $userid, string $name = ''): array {
        global $DB;
        require_capability('local/mooc_audit:batchaudit', \context_system::instance(), $userid);
        $r = self::resolve($criteria, $userid);
        if (!$r['accepted']) {
            return ['batchid' => 0, 'queued' => 0, 'reused' => 0, 'skipped' => $r['skipped'], 'error' => 'nothing_to_audit'];
        }
        if ($r['toomany']) {
            return ['batchid' => 0, 'queued' => 0, 'reused' => 0, 'skipped' => $r['skipped'], 'error' => 'toomany'];
        }
        $name = trim($name) !== '' ? \core_text::substr(trim($name), 0, 255)
            : get_string('batch_default_name', 'local_mooc_audit', (object) [
                'mode' => get_string('batch_mode_' . $r['criteria']['mode'], 'local_mooc_audit'),
                'date' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig')),
            ]);
        $transaction = $DB->start_delegated_transaction();
        $batchid = (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'name' => $name,
            'criteria' => json_encode($r['criteria']),
            'skipped' => null,
            'timecreated' => time(),
        ]);
        $skipped = $r['skipped'];
        $queued = 0;
        foreach (array_keys($r['accepted']) as $courseid) {
            $pending = run_store::reusable_pending($courseid);
            if ($pending) {
                $skipped[] = ['courseid' => $courseid, 'reason' => self::SKIP_DUPLICATE, 'runid' => (int) $pending->id];
                continue;
            }
            recorder::queue($courseid, $userid, $batchid);
            $queued++;
        }
        $DB->set_field(self::TABLE, 'skipped', $skipped ? json_encode($skipped) : null, ['id' => $batchid]);
        $transaction->allow_commit();
        return ['batchid' => $batchid, 'queued' => $queued, 'reused' => count($skipped) - count($r['skipped']),
            'skipped' => $skipped, 'error' => null];
    }

    /**
     * A batch.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Latest run of each course of a batch (its own runs plus the reused pending runs of other requests).
     *
     * @param \stdClass $batch
     * @param bool $light only id, courseid, batchid and status (progress counts)
     * @return \stdClass[] courseid => run (without the large columns)
     */
    public static function runs(\stdClass $batch, bool $light = false): array {
        global $DB;
        $fields = $light ? 'id, courseid, batchid, status'
            : 'id, courseid, batchid, userid, origin, status, overall, summary, evolution, errors, timecreated, timestarted,
                timefinished';
        $out = [];
        foreach ($DB->get_records(run_store::TABLE, ['batchid' => $batch->id], 'id ASC', $fields) as $run) {
            $out[(int) $run->courseid] = $run;
        }
        $reused = [];
        foreach (json_decode((string) $batch->skipped, true) ?: [] as $s) {
            if (!empty($s['runid']) && !isset($out[(int) $s['courseid']])) {
                $reused[(int) $s['runid']] = (int) $s['courseid'];
            }
        }
        if ($reused) {
            foreach ($DB->get_records_list(run_store::TABLE, 'id', array_keys($reused), '', $fields) as $run) {
                $out[$reused[(int) $run->id]] = $run;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Progress of a batch: number of courses per state of their latest run.
     *
     * @param \stdClass $batch
     * @return array state => n, plus 'total'
     */
    public static function progress(\stdClass $batch): array {
        $counts = array_fill_keys([run_store::QUEUED, run_store::RUNNING, run_store::COMPLETE, run_store::PARTIAL,
            run_store::FAILED], 0);
        foreach (self::runs($batch, true) as $run) {
            $counts[$run->status]++;
        }
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    /**
     * Queue new runs, in the same batch, for the courses whose latest run of the batch failed.
     *
     * @param \stdClass $batch
     * @param int $userid requester (must hold local/mooc_audit:batchaudit)
     * @return int number of runs queued
     */
    public static function retry_failed(\stdClass $batch, int $userid): int {
        require_capability('local/mooc_audit:batchaudit', \context_system::instance(), $userid);
        $n = 0;
        foreach (self::runs($batch) as $courseid => $run) {
            // Failed runs of this batch, and failed runs of other requests this batch had reused.
            if ($run->status !== run_store::FAILED) {
                continue;
            }
            if (
                !\core\context\course::instance($courseid, IGNORE_MISSING)
                    || !has_capability('local/mooc_audit:audit', \context_course::instance($courseid), $userid)
                    || run_store::reusable_pending($courseid)
            ) {
                continue;
            }
            recorder::queue($courseid, $userid, (int) $batch->id);
            $n++;
        }
        return $n;
    }

    /**
     * Most recent batches.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public static function recent(int $limit = 10): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, null, 'id DESC', '*', 0, $limit));
    }
}
