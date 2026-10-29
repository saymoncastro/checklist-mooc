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

use local_mooc_audit\local\locator;
use local_mooc_audit\local\rule\base;

/**
 * Mapping of findings (locus + identifiers) to correction URLs.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\locator
 */
final class locator_test extends \advanced_testcase {

    /** @var array Snapshot-like modules keyed by cmid. */
    private const MODULES = [40 => ['cmid' => 40, 'modname' => 'quiz'], 46 => ['cmid' => 46, 'modname' => 'simplecertificate']];
    /** @var array Snapshot-like sections. */
    private const SECTIONS = [[ 'id' => 701, 'number' => 0], ['id' => 703, 'number' => 2], ['id' => 705, 'number' => 3]];

    /**
     * Expected URL per locus.
     *
     * @return array
     */
    public static function loci_provider(): array {
        return [
            'module_edit' => [['locus' => base::LOCUS_MODULE_EDIT, 'cmid' => 40], '/course/modedit.php?update=40'],
            'module_view' => [['locus' => base::LOCUS_MODULE_VIEW, 'cmid' => 46], '/mod/simplecertificate/view.php?id=46'],
            'course_settings' => [['locus' => base::LOCUS_COURSE_SETTINGS], '/course/edit.php?id=12'],
            'enrol' => [['locus' => base::LOCUS_ENROL], '/enrol/instances.php?id=12'],
            'gradebook' => [['locus' => base::LOCUS_GRADEBOOK], '/grade/edit/tree/index.php?id=12'],
            'blocks' => [['locus' => base::LOCUS_BLOCKS], '/course/view.php?id=12'],
            'section' => [['locus' => base::LOCUS_SECTION, 'section' => 2], '/course/section.php?id=703'],
            'import' => [['locus' => base::LOCUS_IMPORT], '/backup/import.php?id=12'],
            'none' => [['locus' => base::LOCUS_NONE], null],
            'missing locus' => [[], null],
            'module_edit, unknown cmid' => [['locus' => base::LOCUS_MODULE_EDIT, 'cmid' => 99], null],
            'module_view, no cmid' => [['locus' => base::LOCUS_MODULE_VIEW], null],
            'section without number' => [['locus' => base::LOCUS_SECTION], null],
            'section, unknown number' => [['locus' => base::LOCUS_SECTION, 'section' => 9], null],
        ];
    }

    /**
     * Each locus maps to its page; missing identifiers map to null.
     *
     * @dataProvider loci_provider
     * @param array $result
     * @param string|null $expected path and query, relative to wwwroot
     */
    public function test_url_for(array $result, ?string $expected): void {
        global $CFG;
        $url = locator::url_for($result, 12, self::MODULES, self::SECTIONS);
        if ($expected === null) {
            $this->assertNull($url);
        } else {
            $this->assertSame($CFG->wwwroot . $expected, $url->out(false));
        }
    }

    /**
     * Every locus has a localised title and the list is the one rules may use.
     */
    public function test_loci_and_titles(): void {
        $loci = locator::loci();
        $this->assertCount(9, $loci);
        $this->assertSame($loci, array_unique($loci));
        foreach ($loci as $locus) {
            $this->assertStringNotContainsString('[[', locator::title($locus), $locus);
        }
        $this->assertSame(get_string('locus_import', 'local_mooc_audit'), locator::title(base::LOCUS_IMPORT));
    }
}
