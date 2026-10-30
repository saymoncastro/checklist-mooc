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

use local_mooc_audit\local\correction\proposal;
use local_mooc_audit\local\correction\trail;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The preview of a correction proposal (templates/correction.mustache): never writes.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correction_proposal implements renderable, templatable {
    /** @var proposal|null The proposal (null: nothing to correct). */
    protected ?proposal $proposal;
    /** @var moodle_url Page URL (form action). */
    protected moodle_url $pageurl;
    /** @var moodle_url Audit report URL (cancel). */
    protected moodle_url $reporturl;

    /**
     * Constructor.
     *
     * @param proposal|null $proposal
     * @param moodle_url $pageurl
     * @param moodle_url $reporturl
     */
    public function __construct(?proposal $proposal, moodle_url $pageurl, moodle_url $reporturl) {
        $this->proposal = $proposal;
        $this->pageurl = $pageurl;
        $this->reporturl = $reporturl;
    }

    /**
     * Template data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $p = $this->proposal;
        $data = (object) ['reporturl' => $this->reporturl->out(false), 'hasproposal' => (bool) $p];
        if (!$p) {
            return $data;
        }
        $executable = $p->is_executable();
        $data->rule = $p->ruleid;
        $data->ruletitle = $p->ruletitle;
        $data->rulesource = $p->rulesource;
        $data->location = $p->location;
        $data->editurl = $p->editurl;
        $data->problem = $p->problem;
        $data->current = $p->currentdisplay;
        $data->proposed = $p->proposeddisplay;
        $data->justification = $p->justification;
        $data->effect = $p->effect;
        $data->category = $p->category;
        $data->categorylabel = get_string('fix_category_' . strtolower($p->category), 'local_mooc_audit');
        $data->executable = $executable;
        $data->modelabel = get_string($executable ? 'fix_mode_executable'
            : ($p->category === proposal::CATEGORY_EXECUTABLE ? 'fix_mode_blocked' : 'fix_mode_guided'), 'local_mooc_audit');
        $data->modeclass = $executable ? 'alert-info' : 'alert-warning';
        $data->risks = array_values($p->risks);
        $data->hasrisks = (bool) $p->risks;
        $data->blockers = array_values($p->blockers);
        $data->hasblockers = (bool) $p->blockers;
        $data->dependencies = array_values($p->dependencies);
        $data->hasdependencies = (bool) $p->dependencies;
        $data->simulated = $p->impact !== null;
        if ($p->impact !== null) {
            $data->impactresolved = array_values($p->impact['resolved']);
            $data->impactintroduced = array_values($p->impact['introduced']);
            $data->impactwarnings = array_values($p->impact['introducedwarnings']);
            $data->hasimpactresolved = (bool) $p->impact['resolved'];
            $data->hasimpactintroduced = (bool) $p->impact['introduced'];
            $data->hasimpactwarnings = (bool) $p->impact['introducedwarnings'];
        }
        $data->capabilities = implode(', ', $p->capabilities);
        $data->roles = $p->roles ? implode(', ', $p->roles) : get_string('fix_roles_none', 'local_mooc_audit');
        $data->canexecute = $p->canexecute;
        $data->showform = $executable && $p->canexecute;
        $data->cannotexecute = $executable && !$p->canexecute;
        $data->actionurl = $this->pageurl->out(false);
        $data->sesskey = sesskey();
        $data->fingerprint = $p->fingerprint();
        $data->courseid = $p->courseid;
        $data->cmid = $p->cmid;
        $data->policyversion = $p->policyversion;
        $data->history = array_map(fn($row) => (object) [
            'date' => userdate($row->timecreated),
            'field' => correction_result::field_label((string) $row->field),
            'oldvalue' => $row->oldvalue,
            'newvalue' => $row->newvalue,
            'outcome' => get_string('fix_outcome_' . $row->outcome, 'local_mooc_audit'),
            'reaudit' => get_string('fix_reaudit_' . $row->reaudit, 'local_mooc_audit'),
        ], trail::for_activity($p->cmid));
        $data->hashistory = (bool) $data->history;
        return $data;
    }
}
