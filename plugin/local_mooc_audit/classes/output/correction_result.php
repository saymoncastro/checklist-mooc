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

use local_mooc_audit\local\correction\executor;
use local_mooc_audit\local\correction\verifier;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The outcome of a confirmed correction (templates/correction_result.mustache).
 *
 * Success is shown only for an applied correction whose target finding the re-audit no longer reports; every other
 * outcome says what happened and that nothing (or not everything) was achieved.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correction_result implements renderable, templatable {
    /** @var array Result of executor::execute(). */
    protected array $result;
    /** @var moodle_url Audit report URL. */
    protected moodle_url $reporturl;
    /** @var moodle_url Proposal URL (new analysis). */
    protected moodle_url $proposalurl;

    /**
     * Constructor.
     *
     * @param array $result
     * @param moodle_url $reporturl
     * @param moodle_url $proposalurl
     */
    public function __construct(array $result, moodle_url $reporturl, moodle_url $proposalurl) {
        $this->result = $result;
        $this->reporturl = $reporturl;
        $this->proposalurl = $proposalurl;
    }

    /**
     * Template data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $r = $this->result;
        $p = $r['proposal'];
        $status = $r['status'];
        $cacheerror = ($r['cacheerror'] ?? '') !== '';
        if ($status === executor::APPLIED) {
            // A failed cache refresh after the commit is reported before the re-audit outcome.
            $key = $cacheerror ? 'applied_cache' : 'applied_' . $r['reaudit'];
        } else {
            $key = $status;
        }
        $success = $status === executor::APPLIED && $r['reaudit'] === verifier::RESOLVED && !$cacheerror;
        $data = (object) [
            'status' => $status,
            'success' => $success,
            'alertclass' => $success ? 'alert-success' : ($status === executor::APPLIED ? 'alert-warning' : 'alert-danger'),
            'message' => get_string('fix_result_' . $key, 'local_mooc_audit'),
            'detail' => $r['message'],
            'hasdetail' => $r['message'] !== '',
            'reporturl' => $this->reporturl->out(false),
            'proposalurl' => $this->proposalurl->out(false),
            'newanalysis' => in_array($status, [executor::CONFLICT, executor::BUSY, executor::FAILED], true),
            'hasproposal' => (bool) $p,
        ];
        if ($p) {
            $data->rule = $p->ruleid;
            $data->ruletitle = $p->ruletitle;
            $data->location = $p->location;
            $data->field = $p->field;
            $data->before = $p->currentdisplay;
            $data->after = $p->proposeddisplay;
            $data->written = $status === executor::APPLIED;
            $data->blockers = array_values($p->blockers);
            $data->hasblockers = $status === executor::REFUSED && $p->blockers;
        }
        $c = $r['comparison'];
        if ($c) {
            $data->targetbefore = implode(', ', $c['targetbefore']);
            $data->targetafter = implode(', ', $c['targetafter']) ?: '-';
            $data->resolved = array_values($c['resolved']);
            $data->introduced = array_values($c['introduced']);
            $data->hasresolved = (bool) $c['resolved'];
            $data->hasintroduced = (bool) $c['introduced'];
            $data->hascomparison = true;
        }
        return $data;
    }
}
