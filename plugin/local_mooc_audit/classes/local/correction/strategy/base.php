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

use local_mooc_audit\local\correction\conflict_exception;
use local_mooc_audit\local\correction\facts;
use local_mooc_audit\local\correction\proposal;
use moodle_url;

/**
 * Knowledge of how to correct the findings of one rule on one kind of activity.
 *
 * A strategy describes a proposal from the current state (describe) and, when it is executable, applies it (apply) and
 * checks through the module API that the new value is what Moodle now reads (persisted). Guided strategies only
 * describe. Strategies never decide on their own that a proposal is executable: the proposal does (no impediment and a
 * favourable simulation).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string Rule handled. */
    const RULE = '';

    /**
     * Strategy name (class short name).
     *
     * @return string
     */
    public function name(): string {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * Whether this strategy handles the given activity (snapshot module).
     *
     * @param array $module
     * @return bool
     */
    abstract public function supports(array $module): bool;

    /**
     * Fill the proposal from the current state.
     *
     * @param proposal $p proposal with rule, course, activity, location, problem and rule metadata already set
     * @param array $ctx snapshot, policy, module, findings (target findings), results (all results)
     */
    abstract public function describe(proposal $p, array $ctx): void;

    /**
     * The snapshot as it would be after the change (used to simulate the impact by running the rules).
     *
     * @param array $snapshot
     * @param proposal $p
     * @return array
     */
    public function simulate(array $snapshot, proposal $p): array {
        $snapshot['modules'][$p->cmid]['extra'][$p->field] = $p->proposed;
        return $snapshot;
    }

    /**
     * Write the proposed value. Called inside a transaction, after every check; the executor then calls persisted() in
     * the same transaction and after_commit() once committed. Guided strategies cannot apply.
     *
     * @param proposal $p
     * @param \cm_info $cm
     * @throws conflict_exception when the stored value is not the one of the proposal
     */
    public function apply(proposal $p, \cm_info $cm): void {
        throw new \coding_exception('Guided correction strategies cannot apply changes: ' . $this->name());
    }

    /**
     * Whether Moodle now reads the proposed value (through the module API, not only the table).
     *
     * @param proposal $p
     * @param \cm_info $cm
     * @return bool
     */
    public function persisted(proposal $p, \cm_info $cm): bool {
        return false;
    }

    /**
     * Capabilities required to apply (course context for the plugin capability, activity context for the core one).
     *
     * @return string[]
     */
    public function capabilities(): array {
        return ['local/mooc_audit:applycorrection', 'moodle/course:manageactivities'];
    }

    /**
     * Language string of the corrections (key fix_<key>).
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    protected function str(string $key, $a = null): string {
        return get_string('fix_' . $key, 'local_mooc_audit', $a);
    }

    /**
     * A date for people: user timezone and, when different, server timezone (the one the rules use).
     *
     * @param int $ts
     * @return string
     */
    protected function date(int $ts): string {
        if (!$ts) {
            return $this->str('date_none');
        }
        $user = userdate($ts, get_string('strftimedatetime', 'langconfig'));
        $server = userdate($ts, get_string('strftimedatetime', 'langconfig'), \core_date::get_server_timezone());
        return $user === $server ? $user : $this->str('date_twozones', (object) ['user' => $user, 'server' => $server,
            'tz' => \core_date::get_server_timezone()]);
    }

    /**
     * Official settings page of the activity, optionally at a form section.
     *
     * @param int $cmid
     * @param string $anchor
     * @return string
     */
    protected function edit_url(int $cmid, string $anchor = ''): string {
        $url = new moodle_url('/course/modedit.php', ['update' => $cmid]);
        if ($anchor !== '') {
            $url->set_anchor($anchor);
        }
        return $url->out(false);
    }

    /**
     * Read a column of the instance and refuse to go on when it is not the value of the proposal.
     *
     * @param string $table
     * @param int $id
     * @param string $field
     * @param mixed $expected
     * @throws conflict_exception
     */
    protected function assert_current(string $table, int $id, string $field, $expected): void {
        global $DB;
        $value = $DB->get_field($table, $field, ['id' => $id]);
        if ($value === false || (string) $value !== (string) $expected) {
            throw new conflict_exception("$table.$field = " . var_export($value, true) . ', expected ' . $expected);
        }
    }

    /**
     * Refuse to go on when students attempted the quiz or have a grade since the proposal was built (the plan counts
     * them, but a student may start an attempt between the plan and the write).
     *
     * @param \cm_info $cm
     * @throws conflict_exception
     */
    protected function assert_no_student_data(\cm_info $cm): void {
        if (facts::quiz_attempts((int) $cm->instance) || facts::instance_grades($cm->modname, (int) $cm->instance)) {
            throw new conflict_exception('student data appeared after the preview');
        }
    }

    /**
     * What Moodle does after a module setting changes outside the form: refresh the course cache (new cacherev) and log
     * the standard course_module_updated event. Called by the executor after the commit, so that a rollback never
     * leaves a cache entry at a new course revision.
     *
     * @param \cm_info $cm
     */
    public function after_commit(\cm_info $cm): void {
        rebuild_course_cache($cm->course, true);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
    }
}
