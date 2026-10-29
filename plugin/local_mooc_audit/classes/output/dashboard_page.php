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

use local_mooc_audit\local\catalog;
use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\dashboard;
use local_mooc_audit\local\status;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Follow-up dashboard (specs/005 US4): counters, summed evolution, filters, one row per course and the recent batches.
 * No approval wording, no percentage: the scope notice says what "no non-conformity detected" means.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_page implements renderable, templatable {
    /** @var array dashboard::build() output. */
    protected array $data;
    /** @var array Category id => name. */
    protected array $categories;
    /** @var bool Viewer may request batches. */
    protected bool $canbatch;
    /** @var stdClass[] Recent batches. */
    protected array $batches;
    /** @var stdClass[] Removed courses with history. */
    protected array $removed;
    /** @var int Page (0-based). */
    protected int $page;
    /** @var int Rows per page. */
    protected int $perpage;
    /** @var moodle_url Page URL with the filters. */
    protected moodle_url $url;

    /**
     * Constructor.
     *
     * @param array $data
     * @param array $categories
     * @param bool $canbatch
     * @param stdClass[] $batches
     * @param stdClass[] $removed
     * @param int $page
     * @param int $perpage
     * @param moodle_url $url
     */
    public function __construct(array $data, array $categories, bool $canbatch, array $batches, array $removed, int $page,
            int $perpage, moodle_url $url) {
        $this->data = $data;
        $this->categories = $categories;
        $this->canbatch = $canbatch;
        $this->batches = $batches;
        $this->removed = $removed;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->url = $url;
    }

    /**
     * Data for templates/dashboard.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $f = $this->data['filters'];
        $c = $this->data['counts'];
        $d = new stdClass();
        $d->scopenotice = get_string('dashboard_scope_notice', 'local_mooc_audit', catalog::count());
        $d->tiles = [
            (object) ['label' => get_string('dashboard_selected', 'local_mooc_audit'), 'count' => $c['selected'],
                'filter' => ''],
            (object) ['label' => get_string('dashboard_audited', 'local_mooc_audit'), 'count' => $c['audited'],
                'filter' => ''],
        ];
        foreach (dashboard::situations() as $s) {
            $d->tiles[] = (object) ['label' => get_string('situation_' . $s, 'local_mooc_audit'), 'count' => $c[$s],
                'cssclass' => history_helper::SITUATION_CLASS[$s], 'filter' => $s];
        }
        foreach ([dashboard::STALE, dashboard::PENDING] as $s) {
            $d->tiles[] = (object) ['label' => get_string('situation_' . $s, 'local_mooc_audit'), 'count' => $c[$s],
                'filter' => $s];
        }
        foreach ($d->tiles as $t) {
            $t->url = $t->filter !== '' ? (new moodle_url($this->url, ['situation' => $t->filter, 'page' => 0]))->out(false)
                : null;
        }
        $d->evolution = history_helper::evolution_counts($this->data['evolution']);
        $d->hasevolution = (bool) $d->evolution;

        // Filters.
        $d->formurl = (new moodle_url('/local/mooc_audit/dashboard.php'))->out(false);
        $d->categories = [(object) ['id' => 0, 'name' => get_string('dashboard_filter_any', 'local_mooc_audit'),
            'selected' => !$f['categoryid']]];
        foreach ($this->categories as $id => $name) {
            $d->categories[] = (object) ['id' => $id, 'name' => $name, 'selected' => $f['categoryid'] == $id];
        }
        $d->subcats = $f['subcats'];
        $d->q = $f['q'];
        $d->situations = [(object) ['code' => '', 'label' => get_string('dashboard_filter_any', 'local_mooc_audit'),
            'selected' => $f['situation'] === '']];
        foreach (array_merge(dashboard::situations(), [dashboard::STALE, dashboard::PENDING]) as $s) {
            $d->situations[] = (object) ['code' => $s, 'label' => get_string('situation_' . $s, 'local_mooc_audit'),
                'selected' => $f['situation'] === $s];
        }
        $d->severities = [(object) ['code' => '', 'label' => get_string('dashboard_filter_any', 'local_mooc_audit'),
            'selected' => $f['severity'] === '']];
        foreach (status::severities() as $s) {
            $d->severities[] = (object) ['code' => $s, 'label' => get_string(status::severity_string_key($s), 'local_mooc_audit'),
                'selected' => $f['severity'] === $s];
        }
        $d->from = $f['from'];
        $d->to = $f['to'];

        // Rows of this page.
        $rows = array_slice($this->data['rows'], $this->page * $this->perpage, $this->perpage);
        $d->rows = array_map(fn($row) => $this->row($row), $rows);
        $d->hasrows = (bool) $d->rows;
        $d->paging = $output->render(new \paging_bar(count($this->data['rows']), $this->page, $this->perpage, $this->url));
        $d->canbatch = $this->canbatch;
        $d->batchurl = (new moodle_url('/local/mooc_audit/batch.php'))->out(false);
        $d->sesskey = sesskey();

        $d->batches = array_map(function ($b) {
            $p = batch_manager::progress($b);
            return (object) [
                'name' => $b->name,
                'date' => history_helper::date((int) $b->timecreated),
                'requester' => history_helper::requester((int) $b->userid),
                'total' => $p['total'],
                'done' => $p['complete'] + $p['partial'],
                'failed' => $p['failed'],
                'pending' => $p['queued'] + $p['running'],
                'url' => (new moodle_url('/local/mooc_audit/batch.php', ['id' => $b->id]))->out(false),
            ];
        }, $this->batches);
        $d->hasbatches = (bool) $d->batches;
        $d->removed = array_map(fn($r) => (object) [
            'courseid' => (int) $r->courseid,
            'runs' => (int) $r->runs,
            'url' => (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $r->courseid]))->out(false),
        ], $this->removed);
        $d->hasremoved = (bool) $d->removed;
        return $d;
    }

    /**
     * One course row.
     *
     * @param stdClass $row dashboard::build() row
     * @return stdClass
     */
    protected function row(stdClass $row): stdClass {
        $course = $row->course;
        $context = \context_course::instance($course->id);
        $stale = array_map(fn($r) => get_string('stale_' . $r, 'local_mooc_audit', dashboard::stale_days()), $row->stale);
        return (object) [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
            'shortname' => format_string($course->shortname, true, ['context' => $context, 'escape' => false]),
            'category' => format_string($course->categoryname, true, ['escape' => false]),
            'startdate' => $course->startdate ? userdate($course->startdate, get_string('strftimedatefullshort', 'langconfig'))
                : '',
            'situation' => history_helper::situation($row->situation),
            'pending' => $row->pending,
            'stale' => implode('; ', $stale),
            'isstale' => (bool) $stale,
            'severities' => history_helper::severities($row->severity),
            'attention' => $row->attention,
            'lastaudit' => history_helper::date($row->lastaudit),
            'evolution' => history_helper::evolution_counts($row->evolution),
            'reporturl' => (new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]))->out(false),
            'historyurl' => (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $course->id]))->out(false),
            'runurl' => $row->valid ? (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $course->id,
                'runid' => $row->valid->id]))->out(false) : null,
        ];
    }
}
