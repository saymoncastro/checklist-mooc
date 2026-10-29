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
 * Proposal invariants and fingerprint.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\correction\proposal
 */
final class proposal_test extends \basic_testcase {
    /**
     * An executable proposal.
     *
     * @return proposal
     */
    private function executable(): proposal {
        $p = new proposal('CL-13', 2, 5, 'quiz_attempts');
        $p->category = proposal::CATEGORY_EXECUTABLE;
        $p->field = 'attempts';
        $p->current = 1;
        $p->proposed = 2;
        $p->policyversion = 'v1';
        $p->state = ['cacherev' => 10, 'timemodified' => 100];
        $p->impact = ['targetresolved' => true, 'introduced' => [], 'resolved' => ['x'], 'introducedwarnings' => []];
        return $p;
    }

    /**
     * Executable only with category A, a field, no impediment and a favourable simulation.
     */
    public function test_is_executable(): void {
        $this->assertTrue($this->executable()->is_executable());
        $p = $this->executable();
        $p->category = proposal::CATEGORY_GUIDED;
        $this->assertFalse($p->is_executable());
        $p = $this->executable();
        $p->field = null;
        $this->assertFalse($p->is_executable());
        $p = $this->executable();
        $p->blockers[] = 'students';
        $this->assertFalse($p->is_executable());
        $p = $this->executable();
        $p->impact = null;
        $this->assertFalse($p->is_executable(), 'not simulated');
        $p = $this->executable();
        $p->impact['targetresolved'] = false;
        $this->assertFalse($p->is_executable());
        $p = $this->executable();
        $p->impact['introduced'] = ['X-DEP-02'];
        $this->assertFalse($p->is_executable());
    }

    /**
     * The fingerprint is stable and changes with the course revision, the instance, the values, the policy and the
     * impediments; display texts and risks do not change it.
     */
    public function test_fingerprint(): void {
        $fp = $this->executable()->fingerprint();
        $this->assertSame($fp, $this->executable()->fingerprint());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fp);
        $p = $this->executable();
        $p->risks[] = 'enrolled';
        $p->currentdisplay = 'one';
        $this->assertSame($fp, $p->fingerprint());
        foreach ([
            fn($p) => $p->state['cacherev'] = 11,
            fn($p) => $p->state['timemodified'] = 101,
            fn($p) => $p->current = 3,
            fn($p) => $p->proposed = 4,
            fn($p) => $p->policyversion = 'v2',
            fn($p) => $p->blockers[] = 'grades',
        ] as $i => $change) {
            $p = $this->executable();
            $change($p);
            $this->assertNotSame($fp, $p->fingerprint(), "change $i");
        }
    }
}
