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

/**
 * A correction proposal for one finding (rule + activity): what would change, why, with which impact and risks.
 *
 * Ephemeral: built by the planner from the current state of the course, never persisted. The fingerprint summarises
 * the state the proposal was built on; the executor rebuilds the proposal at confirmation time and refuses to write when
 * the fingerprints differ.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proposal {
    /** @var string Category A: executable after explicit confirmation. */
    const CATEGORY_EXECUTABLE = 'A';
    /** @var string Category B: guided (needs pedagogical interpretation or depends on other settings). */
    const CATEGORY_GUIDED = 'B';
    /** @var string Category C: depends on an external resource or an institutional decision. */
    const CATEGORY_EXTERNAL = 'C';

    /** @var string Rule id (CL-13...). */
    public string $ruleid;
    /** @var int Course id. */
    public int $courseid;
    /** @var int Course module id. */
    public int $cmid;
    /** @var string Strategy name. */
    public string $strategy;
    /** @var string Category (A, B or C) of this case. */
    public string $category = self::CATEGORY_GUIDED;
    /** @var string|null Setting written by an executable proposal (null for guided proposals). */
    public ?string $field = null;
    /** @var int|string|null Current raw value. */
    public $current = null;
    /** @var int|string|null Proposed raw value (recomputed on the server; never taken from the browser). */
    public $proposed = null;
    /** @var string Current value for people. */
    public string $currentdisplay = '';
    /** @var string Proposed value for people. */
    public string $proposeddisplay = '';
    /** @var string The problem: evidence of the finding(s). */
    public string $problem = '';
    /** @var string Activity label. */
    public string $location = '';
    /** @var string|null URL of the official settings page of the activity. */
    public ?string $editurl = null;
    /** @var string Rule title. */
    public string $ruletitle = '';
    /** @var string Normative or technical source of the rule. */
    public string $rulesource = '';
    /** @var string Why this value is proposed (policy / decision). */
    public string $justification = '';
    /** @var string Expected effect for the students and the course. */
    public string $effect = '';
    /** @var string[] Risks and warnings (do not prevent execution). */
    public array $risks = [];
    /** @var string[] Impediments keyed by a stable code (any of them makes the proposal not executable). */
    public array $blockers = [];
    /** @var string[] What depends on the activity or what the correction depends on. */
    public array $dependencies = [];
    /** @var array|null Simulated impact (verifier::compare() output) or null when not simulated. */
    public ?array $impact = null;
    /** @var string[] Capabilities required to apply. */
    public array $capabilities = [];
    /** @var string[] Roles that hold the capabilities in the course. */
    public array $roles = [];
    /** @var bool Whether the current user holds every required capability (display only; re-checked on confirmation). */
    public bool $canexecute = false;
    /** @var string Policy version used. */
    public string $policyversion = '';
    /** @var array State the proposal was built on (cacherev, instance timemodified...), part of the fingerprint. */
    public array $state = [];
    /** @var array Audit results the proposal was built on (baseline of the re-audit comparison; not fingerprinted). */
    public array $baseline = [];

    /**
     * Constructor.
     *
     * @param string $ruleid
     * @param int $courseid
     * @param int $cmid
     * @param string $strategy
     */
    public function __construct(string $ruleid, int $courseid, int $cmid, string $strategy) {
        $this->ruleid = $ruleid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->strategy = $strategy;
    }

    /**
     * Whether the proposal can be applied: category A, a field to write, no impediment, and a simulation showing that the
     * target finding is resolved without introducing any non conformity.
     *
     * @return bool
     */
    public function is_executable(): bool {
        return $this->category === self::CATEGORY_EXECUTABLE
            && $this->field !== null
            && !$this->blockers
            && $this->impact !== null
            && !empty($this->impact['targetresolved'])
            && empty($this->impact['introduced']);
    }

    /**
     * Fingerprint of the state the proposal was built on. Any change of course revision, instance, values, policy (label
     * and values) or impediments changes it. Impediments count by their codes, never by their localised texts, so that
     * the language or the timezone of the user does not matter.
     *
     * @return string sha256 hex
     */
    public function fingerprint(): string {
        $state = $this->state;
        ksort($state);
        return hash('sha256', json_encode([
            $this->ruleid, $this->courseid, $this->cmid, $this->strategy, $this->field, (string) $this->current,
            (string) $this->proposed, $this->policyversion, $state, array_keys($this->blockers), $this->is_executable(),
        ]));
    }
}
