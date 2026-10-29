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

require_once(__DIR__ . "/../helper/rule_testcase.php");

/**
 * X-INT-02: file records whose content is missing from the file store.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\xint02
 */
final class xint02_test extends rule_testcase {

    /**
     * File with content → C; content removed from filedir → NC naming the file.
     */
    public function test_missing_content(): void {
        $course = $this->course();
        $res = $this->gen()->add_module($course, 'resource', 1, ['name' => 'Apostila', 'display' => RESOURCELIB_DISPLAY_AUTO, 'printintro' => 0, 'files' => 0]);
        $fs = get_file_storage();
        $file = $fs->create_file_from_string(['contextid' => \context_module::instance($res->coursemodule)->id, 'component' => 'mod_resource',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'apostila.txt'], 'conteúdo único ' . $course->id . microtime());
        $r = $this->rule('X-INT-02', $course);
        $this->assertAll(status::CONFORME, $r);
        $this->assertStringContainsString('1 arquivos', $r[0]['evidencia']);

        $this->gen()->remove_file_content($file);
        $r = $this->rule('X-INT-02', $course);
        $this->assertAll(status::NAO_CONFORME, $r);
        $this->assertSame($res->coursemodule, $r[0]['cmid']);
        $this->assertStringContainsString('"apostila.txt"', $r[0]['evidencia']);
        $this->assertStringContainsString('mod_resource/content', $r[0]['evidencia']);
        $this->assertNcComplete($r);
    }
}
