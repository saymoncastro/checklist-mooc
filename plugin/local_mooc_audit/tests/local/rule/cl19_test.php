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

namespace local_mooc_audit\local\rule;

use local_mooc_audit\local\status;
use local_mooc_audit\rule_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mooc_audit/tests/helper/rule_testcase.php');

/**
 * CL-19: labels have no activity completion.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\cl19
 */
final class cl19_test extends rule_testcase {
    /**
     * Label without completion → C; with completion → NC.
     */
    public function test_labels(): void {
        $course = $this->course();
        $this->gen()->add_module($course, 'label', 1, ['introeditor' => ['text' => '<p>Rótulo</p>', 'format' => FORMAT_HTML,
            'itemid' => 0]]);
        $this->assert_all_status(status::CONFORME, $this->rule('CL-19', $course));

        $l = $this->gen()->add_module($course, 'label', 1, ['introeditor' => ['text' => '<p>Outro</p>', 'format' => FORMAT_HTML,
            'itemid' => 0],
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $r = $this->rule('CL-19', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame($l->coursemodule, $r[0]['cmid']);
        $this->assertSame('0', $r[0]['esperado']);
        $this->assert_nc_complete($r);
    }
}
