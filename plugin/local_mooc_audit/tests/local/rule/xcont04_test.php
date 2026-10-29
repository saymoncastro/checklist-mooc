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
 * X-CONT-04: @@PLUGINFILE@@ references without a matching file.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xcont04
 */
final class xcont04_test extends rule_testcase {
    /**
     * Reference with the file in the module area → C; without the file → NC; with "?time=" and the file → C.
     */
    public function test_pluginfile_references(): void {
        $course = $this->course();
        $page = $this->page($course, 'Com imagem', '<p><img src="@@PLUGINFILE@@/banner.png" alt="b"></p>');
        $fs = get_file_storage();
        $fs->create_file_from_string(['contextid' => \context_module::instance($page->coursemodule)->id, 'component' => 'mod_page',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'banner.png'], 'png');
        $this->assert_all_status(status::CONFORME, $this->rule('X-CONT-04', $course));

        $broken = $this->page($course, 'Imagem perdida', '<p><img src="@@PLUGINFILE@@/perdida.png" alt="p"></p>');
        $r = $this->rule('X-CONT-04', $course);
        $this->assert_all_status(status::NAO_CONFORME, $r);
        $this->assertSame($broken->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('@@PLUGINFILE@@/perdida.png', $r[0]['evidencia']);
        $this->assertStringContainsString('campo content', $r[0]['local']);
        $this->assert_nc_complete($r);

        $course2 = $this->course(['shortname' => 'xcont04b']);
        $p = $this->page($course2, 'Com time', '<p><img src="@@PLUGINFILE@@/foto%20um.jpg?time=1700000000" alt="f"></p>');
        $fs->create_file_from_string(['contextid' => \context_module::instance($p->coursemodule)->id, 'component' => 'mod_page',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'foto um.jpg'], 'jpg');
        $this->assert_all_status(status::CONFORME, $this->rule('X-CONT-04', $course2));
    }
}
