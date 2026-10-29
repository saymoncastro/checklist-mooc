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

namespace local_mooc_audit\output;

use local_mooc_audit\local\history\run_store;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Progress and results of a batch (specs/005 FR-023). Only courses the viewer may audit are listed.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batch_page implements renderable, templatable {
    /** @var stdClass The batch. */
    protected stdClass $batch;
    /** @var stdClass[] courseid => latest run. */
    protected array $runs;
    /** @var stdClass[] courseid => course (visible to the viewer). */
    protected array $courses;
    /** @var array Progress counts. */
    protected array $progress;
    /** @var bool Viewer may re-run failures. */
    protected bool $canbatch;

    /**
     * Constructor.
     *
     * @param stdClass $batch
     * @param stdClass[] $runs
     * @param stdClass[] $courses
     * @param array $progress
     * @param bool $canbatch
     */
    public function __construct(stdClass $batch, array $runs, array $courses, array $progress, bool $canbatch) {
        $this->batch = $batch;
        $this->runs = $runs;
        $this->courses = $courses;
        $this->progress = $progress;
        $this->canbatch = $canbatch;
    }

    /**
     * Data for templates/batch.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $b = $this->batch;
        $criteria = json_decode($b->criteria, true) ?: [];
        $d = new stdClass();
        $d->id = (int) $b->id;
        $d->name = $b->name;
        $d->date = history_helper::date((int) $b->timecreated);
        $d->requester = history_helper::requester((int) $b->userid);
        $d->mode = get_string('batch_mode_' . ($criteria['mode'] ?? 'ids'), 'local_mooc_audit');
        $parts = [];
        if (!empty($criteria['categoryid'])) {
            $cat = \core_course_category::get($criteria['categoryid'], IGNORE_MISSING, true);
            $parts[] = get_string('batch_category', 'local_mooc_audit') . ': '
                . ($cat ? $cat->get_formatted_name() : $criteria['categoryid'])
                . (!empty($criteria['subcategories']) ? ' (+ ' . get_string('batch_subcategories', 'local_mooc_audit') . ')' : '');
        }
        if (!empty($criteria['startyear'])) {
            $parts[] = get_string('batch_startyear', 'local_mooc_audit') . ': ' . $criteria['startyear'];
        }
        if (!empty($criteria['courseids'])) {
            $parts[] = 'id: ' . implode(', ', $criteria['courseids']);
        }
        $d->criteria = implode(' · ', $parts);
        $d->progress = [];
        foreach ([run_store::QUEUED, run_store::RUNNING, run_store::COMPLETE, run_store::PARTIAL, run_store::FAILED] as $s) {
            $d->progress[] = (object) ['status' => history_helper::run_status($s), 'count' => $this->progress[$s]];
        }
        $d->total = $this->progress['total'];
        $d->pending = $this->progress[run_store::QUEUED] + $this->progress[run_store::RUNNING] > 0;

        $d->skipped = array_map(fn($s) => (object) [
            'courseid' => (int) $s['courseid'],
            'reason' => get_string('batch_skip_' . $s['reason'], 'local_mooc_audit', $s['runid'] ?? null),
        ], json_decode((string) $b->skipped, true) ?: []);
        $d->hasskipped = (bool) $d->skipped;
        $d->skippedtitle = get_string('batch_skipped', 'local_mooc_audit', count($d->skipped));

        $d->rows = [];
        foreach ($this->runs as $courseid => $run) {
            if (!isset($this->courses[$courseid])) {
                continue;
            }
            $course = $this->courses[$courseid];
            $summary = run_store::json($run, 'summary') ?? [];
            $evo = run_store::json($run, 'evolution');
            $d->rows[] = (object) [
                'courseid' => $courseid,
                'fullname' => format_string($course->fullname, true, ['escape' => false]),
                'shortname' => format_string($course->shortname, true, ['escape' => false]),
                'status' => history_helper::run_status($run->status),
                'overall' => history_helper::overall($run->overall),
                'severities' => history_helper::severities($summary['nao_conformes_por_gravidade'] ?? []),
                'evolution' => history_helper::evolution_counts($evo['counts'] ?? null),
                'errors' => history_helper::errors($run),
                'date' => history_helper::date((int) ($run->timefinished ?: $run->timecreated)),
                'reused' => (int) $run->batchid !== (int) $b->id,
                'url' => (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid,
                    'runid' => $run->id]))->out(false),
            ];
        }
        $d->hasrows = (bool) $d->rows;
        $outside = count($this->runs) - count($d->rows);
        $d->outside = $outside ? get_string('batch_outside_permission', 'local_mooc_audit', $outside) : null;
        $d->canretry = $this->canbatch && $this->progress[run_store::FAILED] > 0;
        $d->formurl = (new moodle_url('/local/mooc_audit/batch.php'))->out(false);
        $d->sesskey = sesskey();
        $d->dashboardurl = (new moodle_url('/local/mooc_audit/dashboard.php'))->out(false);
        return $d;
    }
}
