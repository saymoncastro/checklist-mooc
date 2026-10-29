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

namespace local_mooc_audit\local\correction\strategy;

use local_mooc_audit\local\correction\facts;
use local_mooc_audit\local\correction\proposal;
use local_mooc_audit\local\helpers;
use local_mooc_audit\snapshot;

/**
 * CL-17: activity completion criterion — guided only in this delivery (category B).
 *
 * Completion feeds access restrictions (the course evaluation requires the completion of every graded activity, the
 * certificate requires the evaluation), course completion criteria and the students' completion records, which Moodle
 * locks or recalculates when the criterion changes. The proposal shows the current setting, the criterion of the policy
 * for the type, who depends on the activity (directly and through the evaluation up to the certificate) and the records
 * at stake, then sends the user to the completion section of the official settings page.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_guidance extends base {
    /** @var string */
    const RULE = 'CL-17';

    /**
     * Any activity.
     *
     * @param array $module
     * @return bool
     */
    public function supports(array $module): bool {
        return true;
    }

    /**
     * Describe.
     *
     * @param proposal $p
     * @param array $ctx
     */
    public function describe(proposal $p, array $ctx): void {
        $s = $ctx['snapshot'];
        $m = $ctx['module'];
        $policy = $ctx['policy'];
        $expected = $policy['completion_expected'][$m['modname']] ?? null;
        $known = ['view', 'grade', 'submit', 'submit_or_grade', 'entries', 'posts_or_none', 'none'];

        $p->category = proposal::CATEGORY_GUIDED;
        $p->currentdisplay = $this->current($m);
        $p->proposeddisplay = $this->str('cl17_expected_' . (in_array($expected, $known, true) ? $expected : 'unknown'));
        $p->editurl = $this->edit_url($p->cmid, 'id_activitycompletionheader');
        $p->justification = $this->str('cl17_justification', $m['modname']);
        $p->effect = $this->str('cl17_effect');
        $p->blockers[] = $this->str('cl17_guided_only');

        // Who depends on the completion of this activity, directly or transitively.
        $reach = $this->dependents($s, $p->cmid);
        foreach ($reach['direct'] as $label) {
            $p->dependencies[] = $this->str('cl17_dep_requiredby', $label);
        }
        $evaluation = array_column(helpers::mods($s, 'feedback', $policy['required_feedback_evaluation']), 'cmid');
        $certificates = array_column(helpers::certs($s, $policy), 'cmid');
        if (array_intersect($evaluation, $reach['cmids'])) {
            $p->dependencies[] = $this->str('cl17_dep_evaluation');
        }
        if (array_intersect($certificates, $reach['cmids'])) {
            $p->dependencies[] = $this->str('cl17_dep_certificate');
        }
        if ($n = facts::course_completion_criteria($p->courseid, $p->cmid)) {
            $p->dependencies[] = $this->str('cl17_dep_coursecompletion', $n);
        }
        if (!$p->dependencies) {
            $p->dependencies[] = $this->str('cl17_dep_none');
        }
        if ($reach['cmids'] && $expected === 'none') {
            $p->risks[] = $this->str('cl17_risk_none_required');
        }
        if ($n = facts::completion_records($p->cmid)) {
            $p->risks[] = $this->str('cl17_risk_records', $n);
        }
        if ($n = facts::learners($p->cmid, 'moodle/course:isincompletionreports')) {
            $p->risks[] = $this->str('risk_learners_generic', $n);
        }
    }

    /**
     * Current completion setting for people.
     *
     * @param array $m snapshot module
     * @return string
     */
    protected function current(array $m): string {
        if ((int) $m['completion'] === COMPLETION_TRACKING_NONE) {
            return $this->str('cl17_mode_none');
        }
        if ((int) $m['completion'] === COMPLETION_TRACKING_MANUAL) {
            return $this->str('cl17_mode_manual');
        }
        $crit = [];
        if ($m['completionview']) {
            $crit[] = 'view';
        }
        if ($m['completiongradeitemnumber'] !== null) {
            $crit[] = 'grade';
        }
        foreach (
            ['completionsubmit' => 'submit', 'completionentries' => 'entries', 'completionposts' => 'posts',
            'completiondiscussions' => 'posts', 'completionreplies' => 'posts', 'completionminattempts' => 'attempts',
            'completionattemptsexhausted' => 'attempts', 'completionpass' => 'grade'] as $k => $v
        ) {
            if (!empty($m['extra'][$k])) {
                $crit[] = $v;
            }
        }
        return $this->str('cl17_mode_automatic', $crit ? implode(' + ', array_unique($crit)) : '—');
    }

    /**
     * Activities and sections whose access restrictions require the completion of the activity, and every activity
     * reachable through such requirements (evaluation → certificate).
     *
     * @param array $s snapshot
     * @param int $cmid
     * @return array direct (labels of direct dependents), cmids (every reachable activity)
     */
    protected function dependents(array $s, int $cmid): array {
        $requires = function (?array $tree, int $target): bool {
            foreach (snapshot::walk_conditions($tree) as $c) {
                if (($c['type'] ?? '') === 'completion' && (int) ($c['cm'] ?? 0) === $target) {
                    return true;
                }
            }
            return false;
        };
        $direct = [];
        foreach ($s['modules'] as $m) {
            if ($requires($m['availability'], $cmid)) {
                $direct[] = helpers::label($m);
            }
        }
        foreach ($s['sections'] as $sec) {
            if ($requires($sec['availability'], $cmid)) {
                $direct[] = $this->str('cl17_section', $sec['name']);
            }
        }
        $seen = [];
        $queue = [$cmid];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($s['modules'] as $m) {
                if (!isset($seen[$m['cmid']]) && $m['cmid'] !== $cmid && $requires($m['availability'], $current)) {
                    $seen[$m['cmid']] = true;
                    $queue[] = $m['cmid'];
                }
            }
        }
        return ['direct' => $direct, 'cmids' => array_keys($seen)];
    }
}
