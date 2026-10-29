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

namespace local_mooc_audit\local\correction;

use local_mooc_audit\engine;
use local_mooc_audit\local\catalog;
use local_mooc_audit\local\correction\strategy\base as strategy_base;
use local_mooc_audit\local\status;
use local_mooc_audit\policy;
use local_mooc_audit\snapshot;

/**
 * Builds the correction proposal of a finding from the current state of the course. Read only.
 *
 * Steps: fresh snapshot and rule results (same engine as the audit page), target findings of the rule on the activity,
 * strategy, description, simulated impact (rules run on the snapshot with the proposed value), state for the
 * fingerprint, capabilities and roles.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner {
    /** @var strategy_base[] Strategies, in order of preference per rule. */
    protected array $strategies;

    /**
     * Constructor.
     *
     * @param strategy_base[]|null $strategies strategies to use (default: the shipped ones; tests may inject others)
     */
    public function __construct(?array $strategies = null) {
        $this->strategies = $strategies ?? self::default_strategies();
    }

    /**
     * Strategies shipped with the plugin.
     *
     * @return strategy_base[]
     */
    public static function default_strategies(): array {
        return [
            new strategy\quiz_attempts(),
            new strategy\quiz_timeclose(),
            new strategy\close_date_guidance(),
            new strategy\completion_guidance(),
        ];
    }

    /**
     * Rules that have at least one correction strategy.
     *
     * @return string[]
     */
    public static function rules(): array {
        return array_values(array_unique(array_map(fn($s) => $s::RULE, self::default_strategies())));
    }

    /**
     * Whether an audit result may open a correction proposal: a rule with a strategy, an identified activity and a non
     * conformity or warning.
     *
     * @param array $result audit result
     * @return bool
     */
    public static function eligible(array $result): bool {
        return !empty($result['cmid'])
            && in_array($result['status'], [status::NAO_CONFORME, status::ATENCAO], true)
            && in_array($result['rule'], self::rules(), true);
    }

    /**
     * The strategy of a proposal.
     *
     * @param proposal $p
     * @return strategy_base
     */
    public function strategy_for(proposal $p): strategy_base {
        foreach ($this->strategies as $s) {
            if ($s->name() === $p->strategy) {
                return $s;
            }
        }
        throw new \coding_exception('Unknown correction strategy ' . $p->strategy);
    }

    /**
     * Build the proposal for a rule on an activity, or null when the rule reports no non conformity or warning there.
     *
     * @param int $courseid
     * @param string $ruleid
     * @param int $cmid
     * @param array|null $policyoverride
     * @return proposal|null
     * @throws \moodle_exception when the rule has no strategy or the activity is not in the course
     */
    public function plan(int $courseid, string $ruleid, int $cmid, ?array $policyoverride = null): ?proposal {
        $rules = catalog::instances();
        if (!isset($rules[$ruleid]) || !in_array($ruleid, array_map(fn($s) => $s::RULE, $this->strategies), true)) {
            throw new \moodle_exception('fix_norule', 'local_mooc_audit', '', s($ruleid));
        }
        $policy = policy::load($policyoverride);
        $snapshot = snapshot::build($courseid, 0);
        $module = $snapshot['modules'][$cmid] ?? null;
        if (!$module) {
            throw new \moodle_exception('fix_nomodule', 'local_mooc_audit');
        }
        $results = engine::run_rules($snapshot, $policy);
        $findings = array_values(array_filter($results, fn($r) => self::eligible($r) && $r['rule'] === $ruleid
            && (int) $r['cmid'] === $cmid));
        if (!$findings) {
            return null;
        }
        $strategy = null;
        foreach ($this->strategies as $s) {
            if ($s::RULE === $ruleid && $s->supports($module)) {
                $strategy = $s;
                break;
            }
        }
        if (!$strategy) {
            return null;
        }

        $meta = $rules[$ruleid]->meta();
        $p = new proposal($ruleid, $courseid, $cmid, $strategy->name());
        $p->ruletitle = $meta['title'];
        $p->rulesource = $meta['source'];
        $p->location = \local_mooc_audit\local\helpers::label($module);
        $p->problem = implode(' · ', array_map(fn($f) => $f['evidencia'], $findings));
        $p->policyversion = $policy['policy_version'];
        $p->baseline = $results;
        $p->capabilities = $strategy->capabilities();

        $strategy->describe($p, ['snapshot' => $snapshot, 'policy' => $policy, 'module' => $module, 'findings' => $findings,
            'results' => $results]);

        if ($p->category === proposal::CATEGORY_EXECUTABLE && $p->field !== null && $p->proposed !== null) {
            $after = engine::run_rules($strategy->simulate($snapshot, $p), $policy);
            $p->impact = verifier::compare($results, $after, $ruleid, $cmid);
            if (!$p->impact['targetresolved']) {
                $p->blockers[] = get_string(
                    'fix_blocker_notresolved',
                    'local_mooc_audit',
                    implode('; ', array_map(fn($r) => $r['evidencia'], array_filter($after, fn($r) => $r['rule'] === $ruleid
                    && (int) $r['cmid'] === $cmid && $r['status'] === status::NAO_CONFORME)))
                );
            }
            if ($p->impact['introduced']) {
                $p->blockers[] = get_string('fix_blocker_introduced', 'local_mooc_audit', implode('; ', $p->impact['introduced']));
            }
        }

        $p->state['cacherev'] = facts::cacherev($courseid);
        $coursecontext = \context_course::instance($courseid);
        $modcontext = \context_module::instance($cmid);
        $p->roles = facts::roles_with($coursecontext, ['local/mooc_audit:applycorrection', 'moodle/course:manageactivities']);
        $p->canexecute = has_capability('local/mooc_audit:applycorrection', $coursecontext)
            && has_capability('moodle/course:manageactivities', $modcontext);
        return $p;
    }
}
