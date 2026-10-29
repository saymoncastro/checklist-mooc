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
 * List of the recorded audits of a course (specs/005 FR-006).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_page implements renderable, templatable {
    /** @var int Course id. */
    protected int $courseid;
    /** @var stdClass[] Runs (newest first). */
    protected array $runs;
    /** @var stdClass[] Stored policies by id. */
    protected array $policies;
    /** @var string Paging bar HTML. */
    protected string $paging;
    /** @var moodle_url|null Link to audit the course now (null for a removed course). */
    protected ?moodle_url $auditurl;

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param stdClass[] $runs
     * @param stdClass[] $policies id => policy record
     * @param string $paging paging bar HTML
     * @param moodle_url|null $auditurl
     */
    public function __construct(int $courseid, array $runs, array $policies, string $paging, ?moodle_url $auditurl) {
        $this->courseid = $courseid;
        $this->runs = $runs;
        $this->policies = $policies;
        $this->paging = $paging;
        $this->auditurl = $auditurl;
    }

    /**
     * Data for templates/history.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $rows = [];
        foreach ($this->runs as $run) {
            $summary = run_store::json($run, 'summary') ?? [];
            $evo = run_store::json($run, 'evolution');
            $policy = $this->policies[$run->policyid] ?? null;
            $rows[] = (object) [
                'id' => (int) $run->id,
                'date' => history_helper::date((int) ($run->timefinished ?: $run->timecreated)),
                'origin' => get_string('history_origin_' . $run->origin, 'local_mooc_audit'),
                'requester' => history_helper::requester((int) $run->userid),
                'status' => history_helper::run_status($run->status),
                'overall' => history_helper::overall($run->overall),
                'severities' => history_helper::severities($summary['nao_conformes_por_gravidade'] ?? []),
                'hasreport' => in_array($run->status, run_store::valid_states(), true),
                'evolution' => history_helper::evolution_counts($evo['counts'] ?? null),
                'first' => $run->status !== run_store::FAILED && !$evo,
                'policy' => $policy ? $policy->policyversion . ' · ' . substr($policy->fingerprint, 0, 8) : '',
                'reofferyear' => $run->reofferyear,
                'version' => $run->pluginversion,
                'url' => (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $this->courseid,
                    'runid' => $run->id]))->out(false),
            ];
        }
        return (object) [
            'rows' => $rows,
            'hasrows' => (bool) $rows,
            'paging' => $this->paging,
            'auditurl' => $this->auditurl ? $this->auditurl->out(false) : null,
        ];
    }
}
