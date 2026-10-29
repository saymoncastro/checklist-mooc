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

namespace local_mooc_audit\tests;

/**
 * Builds synthetic course snapshots (same shape as \local_mooc_audit\snapshot::build()) for unit tests of the
 * comparison, without a database. Ids of each builder start at a different base, as after an import.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_builder {
    /** @var array The snapshot being built. */
    private array $s;
    /** @var int Next cmid. */
    private int $next;

    /**
     * Constructor: course with sections 0..4.
     *
     * @param int $courseid
     * @param int $cmbase first cmid (and base of every other id)
     */
    public function __construct(int $courseid, int $cmbase) {
        $this->next = $cmbase;
        $this->s = [
            'course' => ['id' => $courseid, 'contextid' => $cmbase + 900, 'shortname' => "C$courseid", 'fullname' => 'Curso',
                'summary' => '<p>Resumo</p>', 'format' => 'topics', 'startdate' => 1798772400, 'enddate' => 1830308340,
                'visible' => 1, 'lang' => 'pt_br', 'enablecompletion' => 1, 'showcompletionconditions' => 1,
                'timecreated' => $cmbase, 'timemodified' => $cmbase, 'category' => ['id' => 1, 'name' => 'Inscrições Abertas'],
                'formatoptions' => ['hiddensections' => 1, 'coursedisplay' => 0]],
            'sections' => [],
            'modules' => [],
            'grade' => ['categories' => [$cmbase + 800 => ['id' => $cmbase + 800, 'fullname' => '?', 'parent' => null,
                'depth' => 1, 'aggregation' => 13, 'aggregateonlygraded' => 0, 'hidden' => 0]],
                'course_item' => ['id' => $cmbase + 700, 'itemtype' => 'course', 'itemmodule' => null,
                    'iteminstance' => $cmbase + 800, 'itemnumber' => null, 'itemname' => null, 'gradetype' => 1,
                    'grademax' => 100.0, 'grademin' => 0.0, 'gradepass' => 0.0, 'categoryid' => null, 'hidden' => 0,
                    'aggregationcoef2' => 0.0, 'weightoverride' => 0],
                'items_total' => 1, 'all_items' => []],
            'enrols' => [['id' => $cmbase + 600, 'enrol' => 'self', 'status' => 0, 'name' => '',
                'enrolstartdate' => 1798772400, 'enrolenddate' => 1830308340, 'customint6' => 1, 'roleid' => 5,
                'has_password' => false]],
            'blocks' => [['instanceid' => $cmbase + 500, 'name' => 'html', 'title' => 'Acessibilidade', 'region' => 'side-pre',
                'visible' => 1, 'parentcontextlevel' => 50, 'inherited' => false, 'pagetypepattern' => 'course-view-*',
                'config' => null]],
            'site' => ['enableavailability' => 1, 'enablecompletion' => 1],
            'plugins' => ['installed' => [], 'used' => ['mod' => [], 'block' => [], 'availability' => []]],
            'completion_criteria' => 0,
            'warnings' => [],
        ];
        $this->s['grade']['all_items'][$cmbase + 700] = $this->s['grade']['course_item'];
        for ($n = 0; $n <= 4; $n++) {
            $id = $cmbase + 400 + $n;
            $this->s['sections'][$id] = ['id' => $id, 'number' => $n, 'name' => "Seção $n", 'rawname' => "Seção $n",
                'visible' => 1, 'uservisible' => true, 'availableinfo' => '', 'summary' => '', 'sequence' => [],
                'availability' => null, 'availability_raw' => null];
        }
    }

    /**
     * Add a module at the end of a section.
     *
     * @param string $modname
     * @param string $name
     * @param int $section section number
     * @param array $over module fields to override (extra, texts, files, grade_items, ...)
     * @return int cmid
     */
    public function add(string $modname, string $name, int $section, array $over = []): int {
        $cmid = $this->next++;
        $sectionid = $this->section_id($section);
        $m = array_merge([
            'cmid' => $cmid, 'modname' => $modname, 'instanceid' => $cmid + 10000, 'name' => $name,
            'sectionid' => $sectionid, 'sectionnumber' => $section, 'visible' => 1, 'visibleoncoursepage' => 1,
            'uservisible' => true, 'availableinfo' => '', 'completion' => 0, 'completionview' => 0,
            'completiongradeitemnumber' => null, 'completionpassgrade' => 0, 'completionexpected' => 0,
            'availability' => null, 'availability_raw' => null, 'contextid' => $cmid + 20000,
            'texts' => ['intro' => ''], 'extra' => [], 'grade_items' => [],
            'files' => ['count' => 0, 'missing_in_storage' => [], 'list' => []], 'plugin_version' => '1',
        ], $over);
        $m['files']['count'] = count($m['files']['list']);
        $this->s['modules'][$cmid] = $m;
        $this->s['sections'][$sectionid]['sequence'][] = $cmid;
        foreach ($m['grade_items'] as $gi) {
            $this->s['grade']['all_items'][$gi['id']] = $gi;
        }
        return $cmid;
    }

    /**
     * A graded module grade item.
     *
     * @param int $id
     * @param float $grademax
     * @param string $name
     * @return array
     */
    public static function grade_item(int $id, float $grademax, string $name = 'Q'): array {
        return ['id' => $id, 'itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => 1, 'itemnumber' => 0,
            'itemname' => $name, 'gradetype' => 1, 'grademax' => $grademax, 'grademin' => 0.0, 'gradepass' => 0.0,
            'categoryid' => null, 'hidden' => 0, 'aggregationcoef2' => 0.0, 'weightoverride' => 0];
    }

    /**
     * A file entry.
     *
     * @param string $name
     * @param int $size
     * @param bool|null $readable
     * @return array
     */
    public static function file(string $name, int $size = 10, ?bool $readable = true): array {
        return ['id' => crc32($name), 'component' => 'mod_resource', 'filearea' => 'content', 'filepath' => '/',
            'filename' => $name, 'filesize' => $size, 'mimetype' => 'text/plain', 'is_reference' => false,
            'readable' => $readable];
    }

    /**
     * Change fields of a module.
     *
     * @param int $cmid
     * @param array $over
     * @return self
     */
    public function set(int $cmid, array $over): self {
        $this->s['modules'][$cmid] = array_replace($this->s['modules'][$cmid], $over);
        $this->s['modules'][$cmid]['files']['count'] = count($this->s['modules'][$cmid]['files']['list']);
        return $this;
    }

    /**
     * Change course fields (merged one level deep).
     *
     * @param string $part course|grade|enrols|blocks|sections|site
     * @param array $over
     * @return self
     */
    public function patch(string $part, array $over): self {
        $this->s[$part] = array_replace($this->s[$part], $over);
        return $this;
    }

    /**
     * Section id of a section number.
     *
     * @param int $number
     * @return int
     */
    public function section_id(int $number): int {
        foreach ($this->s['sections'] as $id => $sec) {
            if ($sec['number'] === $number) {
                return $id;
            }
        }
        throw new \coding_exception("no section $number");
    }

    /**
     * Course total grade item id.
     *
     * @return int
     */
    public function course_item_id(): int {
        return $this->s['grade']['course_item']['id'];
    }

    /**
     * The snapshot.
     *
     * @return array
     */
    public function get(): array {
        return $this->s;
    }
}
