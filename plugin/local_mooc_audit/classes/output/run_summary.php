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
 * "Since the previous audit" box shown above the report of the audit page (specs/005 FR-005).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_summary implements renderable, templatable {
    /** @var stdClass The run just recorded. */
    protected stdClass $run;
    /** @var int Number of runs of the course. */
    protected int $count;

    /**
     * Constructor.
     *
     * @param stdClass $run
     * @param int $count number of recorded runs of the course
     */
    public function __construct(stdClass $run, int $count) {
        $this->run = $run;
        $this->count = $count;
    }

    /**
     * Data for templates/run_summary.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $evo = run_store::json($this->run, 'evolution');
        $courseid = (int) $this->run->courseid;
        $data = new stdClass();
        $data->runid = (int) $this->run->id;
        $data->status = history_helper::run_status($this->run->status);
        $data->historyurl = (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid]))->out(false);
        $data->historylabel = get_string('history_link', 'local_mooc_audit', $this->count);
        $data->evolutionurl = (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid,
            'runid' => $data->runid]))->out(false);
        $data->first = !$evo;
        $data->comparable = $evo && $evo['counts'] !== null;
        $data->counts = history_helper::evolution_counts($evo['counts'] ?? null);
        $data->nochange = $data->comparable && !$data->counts;
        $data->notes = array_map(fn($n) => get_string('evolution_note_' . $n, 'local_mooc_audit'), $evo['notes'] ?? []);
        $data->errors = $this->run->status === run_store::COMPLETE ? [] : history_helper::errors($this->run);
        $data->haserrors = (bool) $data->errors;
        return $data;
    }
}
