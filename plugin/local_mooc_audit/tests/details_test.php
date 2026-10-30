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

namespace local_mooc_audit;

use local_mooc_audit\local\details;
use local_mooc_audit\local\presentation\catalog;
use local_mooc_audit\local\status;

/**
 * Structured facts of report contract 1.1 (specs/006, FR-043): check, subject, params, subjects, evidence level.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\details
 * @covers     \local_mooc_audit\engine
 */
final class details_test extends \advanced_testcase {
    /**
     * First result of a rule, check and state.
     *
     * @param array $report
     * @param string $rule
     * @param string $check
     * @param string $status
     * @return array
     */
    protected function result(array $report, string $rule, string $check, string $status = status::NAO_CONFORME): array {
        foreach ($report['results'] as $r) {
            if ($r['rule'] === $rule && $r['check'] === $check && $r['status'] === $status) {
                return $r;
            }
        }
        $this->fail("$rule $check $status not found");
    }

    /**
     * Course B: every result keeps check and subject consistent with its occurrence key and has an evidence level; the
     * closed list of checks carries the values found and required and the related activities.
     */
    public function test_course_b_facts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$b, $ids] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_b(2027);
        $report = engine::audit($b->id, ['reoffer_year' => 2027]);
        $this->assertSame('1.1', $report['contract_version']);
        $levels = [details::PROVEN, details::HEURISTIC, details::NAME_MATCH, details::STATIC];
        foreach ($report['results'] as $r) {
            $occ = catalog::parse_occurrence($r['occurrence']);
            $this->assertSame($occ['check'], $r['check'], $r['occurrence']);
            $this->assertSame($occ['subject'], $r['subject'] === '' ? null : $r['subject'], $r['occurrence']);
            $this->assertContains($r['evidence_level'], $levels, $r['occurrence']);
        }

        $attempts = $this->result($report, 'CL-13', 'attempts');
        $this->assertSame(['found' => 1, 'required' => 2, 'rule' => 'min'], $attempts['params']);
        $this->assertSame(details::HEURISTIC, $this->result($report, 'CL-13', 'intro_attempts')['evidence_level']);

        $dependency = $this->result($report, 'X-DEP-02', 'target_completion');
        $this->assertSame([(int) substr($dependency['subject'], 3)], $dependency['subjects']);
        $chain = $this->result($report, 'X-CERT-01', 'chain');
        $this->assertContains(
            (int) substr($dependency['subject'], 3),
            $chain['subjects'],
            'the page without completion is in the certificate chain'
        );
        $this->assertCount(2, $this->result($report, 'X-DEP-04', 'cycle')['subjects']);

        $enrol = $this->result($report, 'CL-08', 'self_enrol');
        $this->assertFalse($enrol['params']['enabled']);
        $this->assertArrayHasKey('coursestart', $enrol['params']);
    }

    /**
     * The certificate chain without static impediment is a static check, not a proven fact.
     */
    public function test_static_level(): void {
        $this->assertSame(details::STATIC, details::level('X-CERT-01', 'chain', status::CONFORME));
        $this->assertSame(details::PROVEN, details::level('X-CERT-01', 'chain', status::NAO_CONFORME));
        $this->assertSame(details::NAME_MATCH, details::level('CL-09', 'forum_presence', status::NAO_CONFORME));
    }
}
