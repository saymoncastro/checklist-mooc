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

use local_mooc_audit\local\status;

/**
 * Comparison of results before × after a correction (pure).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\correction\verifier
 */
final class verifier_test extends \basic_testcase {
    /**
     * A result.
     *
     * @param string $rule
     * @param int $cmid
     * @param string $status
     * @param string $evidence
     * @param string $local
     * @return array
     */
    private function r(string $rule, int $cmid, string $status, string $evidence = 'e', string $local = 'quiz Q'): array {
        return ['rule' => $rule, 'cmid' => $cmid ?: null, 'status' => $status, 'evidencia' => $evidence, 'local' => $local];
    }

    /**
     * Two non conformities of the rule on the activity disappear: resolved, nothing introduced.
     */
    public function test_resolved(): void {
        $before = [$this->r('CL-13', 5, status::NAO_CONFORME, 'tentativas=1'), $this->r(
            'CL-13',
            5,
            status::NAO_CONFORME,
            'enunciado 2'
        ), $this->r('CL-07', 0, status::NAO_CONFORME, 'fim', 'curso')];
        $after = [
            $this->r('CL-13', 5, status::CONFORME, 'tentativas=2'),
            $this->r('CL-07', 0, status::NAO_CONFORME, 'fim', 'curso'),
        ];
        $c = verifier::compare($before, $after, 'CL-13', 5);
        $this->assertTrue($c['targetresolved']);
        $this->assertSame(verifier::RESOLVED, $c['outcome']);
        $this->assertCount(2, $c['resolved']);
        $this->assertSame([], $c['introduced']);
        $this->assertSame([status::NAO_CONFORME, status::NAO_CONFORME], $c['targetbefore']);
        $this->assertSame([status::CONFORME], $c['targetafter']);
    }

    /**
     * One non conformity replaced by another of the same rule and activity (statement now disagrees): not resolved.
     */
    public function test_not_resolved_when_another_finding_of_the_rule_remains(): void {
        $before = [$this->r('CL-13', 5, status::NAO_CONFORME, 'tentativas=1')];
        $after = [$this->r('CL-13', 5, status::CONFORME), $this->r('CL-13', 5, status::NAO_CONFORME, 'enunciado declara 1')];
        $c = verifier::compare($before, $after, 'CL-13', 5);
        $this->assertFalse($c['targetresolved']);
        $this->assertSame(verifier::NOT_RESOLVED, $c['outcome']);
    }

    /**
     * Target resolved but another rule now fails: introduced.
     */
    public function test_introduced(): void {
        $before = [$this->r('CL-14', 5, status::NAO_CONFORME)];
        $after = [$this->r('CL-14', 5, status::CONFORME), $this->r('X-DEP-02', 9, status::NAO_CONFORME, 'impossível', 'page P')];
        $c = verifier::compare($before, $after, 'CL-14', 5);
        $this->assertTrue($c['targetresolved']);
        $this->assertSame(verifier::INTRODUCED, $c['outcome']);
        $this->assertSame(['X-DEP-02 — page P: impossível'], $c['introduced']);
    }

    /**
     * A warning as target (close date ≠ course end) is resolved when it disappears; a new warning elsewhere is reported
     * apart and does not count as introduced non conformity.
     */
    public function test_warning_target_and_new_warning(): void {
        $before = [$this->r('CL-14', 5, status::ATENCAO)];
        $after = [$this->r('CL-14', 5, status::CONFORME), $this->r('CL-13', 5, status::ATENCAO, 'silencioso')];
        $c = verifier::compare($before, $after, 'CL-14', 5);
        $this->assertSame(verifier::RESOLVED, $c['outcome']);
        $this->assertCount(1, $c['introducedwarnings']);
        $this->assertSame([], $c['introduced']);
    }

    /**
     * Nothing changed: not resolved, no list.
     */
    public function test_nothing_changed(): void {
        $before = [$this->r('CL-14', 5, status::ATENCAO)];
        $c = verifier::compare($before, $before, 'CL-14', 5);
        $this->assertSame(verifier::NOT_RESOLVED, $c['outcome']);
        $this->assertSame([], $c['resolved']);
    }
}
