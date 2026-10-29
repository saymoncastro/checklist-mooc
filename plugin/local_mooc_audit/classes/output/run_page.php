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

use local_mooc_audit\local\history\evolution;
use local_mooc_audit\local\history\run_store;
use local_mooc_audit\local\status;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * One recorded audit: metadata, errors and limitations, evolution against a base run, assisted corrections of the
 * period (specs/002 trail, not attributed) and the stored report (specs/005 FR-006, FR-010, FR-015).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_page implements renderable, templatable {
    /** @var stdClass The run. */
    protected stdClass $run;
    /** @var stdClass|null The base run. */
    protected ?stdClass $base;
    /** @var array|null evolution::compare() output. */
    protected ?array $cmp;
    /** @var stdClass[] Assisted corrections of the period. */
    protected array $corrections;
    /** @var stdClass|null Stored policy. */
    protected ?stdClass $policy;
    /** @var string Rendered stored report. */
    protected string $reporthtml;
    /** @var array Earlier valid runs: id => label. */
    protected array $baseoptions;

    /**
     * Constructor.
     *
     * @param stdClass $run
     * @param stdClass|null $base
     * @param array|null $cmp
     * @param stdClass[] $corrections
     * @param stdClass|null $policy
     * @param string $reporthtml
     * @param array $baseoptions
     */
    public function __construct(stdClass $run, ?stdClass $base, ?array $cmp, array $corrections, ?stdClass $policy,
            string $reporthtml, array $baseoptions) {
        $this->run = $run;
        $this->base = $base;
        $this->cmp = $cmp;
        $this->corrections = $corrections;
        $this->policy = $policy;
        $this->reporthtml = $reporthtml;
        $this->baseoptions = $baseoptions;
    }

    /**
     * Data for templates/run.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $run = $this->run;
        $courseid = (int) $run->courseid;
        $data = new stdClass();
        $data->runid = (int) $run->id;
        $data->recordof = get_string('history_record_of', 'local_mooc_audit',
            history_helper::date((int) ($run->timefinished ?: $run->timecreated)));
        $data->status = history_helper::run_status($run->status);
        $data->overall = history_helper::overall($run->overall);
        $data->origin = get_string('history_origin_' . $run->origin, 'local_mooc_audit');
        $data->requester = history_helper::requester((int) $run->userid);
        $data->policy = $this->policy ? $this->policy->policyversion : '';
        $data->fingerprint = $this->policy ? substr($this->policy->fingerprint, 0, 12) : '';
        $data->reofferyear = $run->reofferyear;
        $data->version = $run->pluginversion;
        $data->errors = history_helper::errors($run);
        $data->haserrors = (bool) $data->errors;
        $data->historyurl = (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid]))->out(false);
        $data->jsonurl = (new moodle_url('/local/mooc_audit/history.php', ['courseid' => $courseid, 'runid' => $run->id,
            'baseid' => $this->base->id ?? 0, 'format' => 'json']))->out(false);

        $valid = in_array($run->status, run_store::valid_states(), true);
        $data->hasbase = (bool) $this->base;
        $data->first = $valid && !$this->base && !$run->previousrunid;
        $data->basemissing = $valid && !$this->base && $run->previousrunid;
        if ($this->base) {
            $data->evolutiontitle = get_string('evolution_title', 'local_mooc_audit',
                history_helper::date((int) $this->base->timefinished));
            $data->notes = array_map(fn($n) => get_string('evolution_note_' . $n, 'local_mooc_audit'), $this->cmp['notes']);
            $data->counts = history_helper::evolution_counts($this->cmp['counts'], true);
            $data->comparable = $this->cmp['counts'] !== null;
            $data->groups = $this->groups($this->cmp['items']);
            $data->nochange = $data->comparable && !$this->cmp['items'];
            $data->corrections = array_map(fn($c) => (object) [
                'date' => history_helper::date((int) $c->timecreated),
                'rule' => $c->ruleid,
                'cmid' => (int) $c->cmid,
                'field' => $c->field,
                'oldvalue' => $c->oldvalue,
                'newvalue' => $c->newvalue,
                'outcome' => $c->outcome,
                'requester' => history_helper::requester((int) $c->userid),
            ], $this->corrections);
            $data->hascorrections = (bool) $data->corrections;
        }
        $data->baseoptions = [];
        foreach ($this->baseoptions as $id => $label) {
            $data->baseoptions[] = (object) ['id' => $id, 'label' => $label, 'selected' => $this->base && $id == $this->base->id];
        }
        $data->hasbaseoptions = count($data->baseoptions) > 0;
        $data->formurl = (new moodle_url('/local/mooc_audit/history.php'))->out(false);
        $data->courseid = $courseid;
        $data->reporthtml = $this->reporthtml;
        $data->hasreport = $this->reporthtml !== '';
        return $data;
    }

    /**
     * Evolution items grouped by class, in display order.
     *
     * @param array $items
     * @return array
     */
    protected function groups(array $items): array {
        $sev = fn(?string $s) => $s ? get_string(status::severity_string_key($s), 'local_mooc_audit') : '';
        $st = fn(?string $s) => $s ? get_string(status::string_key($s), 'local_mooc_audit') : '—';
        $groups = [];
        foreach (evolution::classes() as $class) {
            $list = array_values(array_filter($items, fn($i) => $i['class'] === $class));
            if (!$list) {
                continue;
            }
            $groups[] = (object) [
                'code' => $class,
                'label' => get_string('evolution_class_' . $class, 'local_mooc_audit'),
                'cssclass' => history_helper::EVOLUTION_CLASS[$class],
                'count' => count($list),
                'items' => array_map(fn($i) => (object) [
                    'rule' => $i['rule'],
                    'title' => $i['title'],
                    'local' => $i['local'],
                    'link' => $i['link'],
                    'before' => $st($i['status_before']) . ($i['severity_before'] ? ' · ' . $sev($i['severity_before']) : ''),
                    'after' => $st($i['status_after']) . ($i['severity_after'] ? ' · ' . $sev($i['severity_after']) : ''),
                    'evidence_before' => $i['evidence_before'],
                    'evidence_after' => $i['evidence_after'],
                    'reason' => $i['reason'] ? get_string('evolution_reason_' . $i['reason'], 'local_mooc_audit') : null,
                    'successor' => $i['successor_cmid'] ? get_string('evolution_successor', 'local_mooc_audit',
                        $i['successor_cmid']) : null,
                    'key' => $i['key'],
                ], $list),
            ];
        }
        return $groups;
    }
}
