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


/**
 * Builds a read-only, JSON-serialisable snapshot of a live course ("CourseSnapshot").
 *
 * The structure intentionally mirrors the model produced by the MBZ prototype (poc/mbz_inspect.py) so that the
 * same rule logic can run against either source. Data access, in order of preference:
 *   1. Moodle APIs: get_course(), get_fast_modinfo(), course_get_format(), grade_item/grade_category,
 *      enrol_get_instances(), block_manager, file_storage/file_system, core_plugin_manager.
 *   2. Module tables through the DML abstraction ($DB->get_record('quiz', ...)) for module-specific settings,
 *      because Moodle has no cross-module API exposing them. Only whitelisted columns are read.
 *
 * Nothing is written. No user content is executed.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot {
    /** @var array Columns read per module table (whitelist). Text fields listed separately. */
    const MODULE_FIELDS = [
        'quiz' => ['attempts', 'timeopen', 'timeclose', 'grade', 'sumgrades', 'grademethod', 'completionminattempts',
            'completionattemptsexhausted', 'completionpass', 'timelimit'],
        'feedback' => ['completionsubmit', 'anonymous', 'timeopen', 'timeclose', 'multiple_submit'],
        'forum' => ['type', 'completiondiscussions', 'completionreplies', 'completionposts', 'duedate', 'cutoffdate',
            'grade_forum'],
        'url' => ['externalurl', 'display'],
        'page' => ['display'],
        'label' => [],
        'book' => ['numbering'],
        'resource' => ['display', 'tobemigrated'],
        'folder' => ['display'],
        'glossary' => ['mainglossary', 'defaultapproval', 'allowcomments', 'completionentries'],
        'choice' => ['completionsubmit', 'timeclose', 'allowupdate'],
        'data' => ['completionentries', 'timeavailableto', 'approval'],
        'assign' => ['duedate', 'cutoffdate', 'grade', 'allowsubmissionsfromdate', 'completionsubmit', 'nosubmissions'],
        'game' => ['sourcemodule', 'glossaryid', 'glossarycategoryid', 'quizid', 'bookid', 'gamekind', 'grade', 'timeclose',
            'timeopen'],
        'hvp' => ['main_library_id', 'completionpass'],
        'simplecertificate' => ['certificateimage', 'secondimage', 'requiredtime', 'coursename', 'certdate', 'certgrade',
            'enablesecondpage', 'printqrcode'],
    ];

    /** @var array Text (HTML) columns read per module table. */
    const MODULE_TEXTS = [
        'page' => ['intro', 'content'], 'label' => ['intro'], 'url' => ['intro'], 'book' => ['intro'], 'forum' => ['intro'],
        'quiz' => ['intro'], 'feedback' => ['intro', 'page_after_submit'], 'resource' => ['intro'], 'folder' => ['intro'],
        'choice' => ['intro'], 'glossary' => ['intro'], 'data' => ['intro'], 'game' => ['intro'], 'hvp' => ['intro'],
        'assign' => ['intro'], 'simplecertificate' => ['intro', 'certificatetext', 'secondpagetext'],
    ];

    /**
     * Build the snapshot.
     *
     * @param int $courseid
     * @param int $userid user whose view is used for uservisible/availableinfo (0 = current user, normally admin)
     * @return array
     */
    public static function build(int $courseid, int $userid = 0): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/blocklib.php');
        require_once($CFG->libdir . '/pagelib.php');

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course, $userid);
        $fs = get_file_storage();
        $filesystem = $fs->get_file_system();

        $s = [
            'source' => 'moodle-plugin',
            'engine' => 'local_mooc_audit ' . get_config('local_mooc_audit', 'version'),
            'collected_at' => date('c'),
            'viewer_userid' => $userid,
            'site' => [
                'wwwroot' => $CFG->wwwroot,
                'release' => $CFG->release,
                'version' => $CFG->version,
                'timezone' => \core_date::get_server_timezone(),
                'enableavailability' => (int) $CFG->enableavailability,
                'enablecompletion' => (int) $CFG->enablecompletion,
            ],
            'course' => [],
            'sections' => [],
            'modules' => [],
            'grade' => ['categories' => [], 'course_item' => null, 'items_total' => 0],
            'enrols' => [],
            'blocks' => [],
            'blocks_source' => null,
            'plugins' => ['installed' => [], 'used' => ['mod' => [], 'block' => [], 'availability' => []]],
            'completion_criteria' => 0,
            'warnings' => [],
        ];

        // Course.
        $category = \core_course_category::get($course->category, IGNORE_MISSING, true);
        $s['course'] = [
            'id' => (int) $course->id,
            'contextid' => (int) $context->id,
            'shortname' => $course->shortname,
            'fullname' => $course->fullname,
            'summary' => $course->summary,
            'format' => $course->format,
            'startdate' => (int) $course->startdate,
            'enddate' => (int) $course->enddate,
            'visible' => (int) $course->visible,
            'lang' => $course->lang,
            'enablecompletion' => (int) $course->enablecompletion,
            'showcompletionconditions' => (int) $course->showcompletionconditions,
            'timecreated' => (int) $course->timecreated,
            'timemodified' => (int) $course->timemodified,
            'category' => ['id' => (int) $course->category, 'name' => $category ? $category->name : null],
            'formatoptions' => $format->get_format_options(),
        ];

        // Sections.
        foreach ($modinfo->get_section_info_all() as $section) {
            $s['sections'][(int) $section->id] = [
                'id' => (int) $section->id,
                'number' => (int) $section->section,
                'name' => $format->get_section_name($section),
                'rawname' => $section->name,
                'visible' => (int) $section->visible,
                'uservisible' => (bool) $section->uservisible,
                'availableinfo' => is_string($section->availableinfo) ? $section->availableinfo : '',
                'summary' => $section->summary,
                'sequence' => array_map('intval', array_filter(explode(',', (string) $section->sequence))),
                'availability' => self::decode_availability($section->availability),
                'availability_raw' => $section->availability,
            ];
        }

        // Grade book (course level).
        foreach (\grade_category::fetch_all(['courseid' => $courseid]) ?: [] as $cat) {
            $s['grade']['categories'][(int) $cat->id] = [
                'id' => (int) $cat->id, 'fullname' => $cat->fullname, 'parent' => $cat->parent ? (int) $cat->parent : null,
                'depth' => (int) $cat->depth, 'aggregation' => (int) $cat->aggregation,
                'aggregateonlygraded' => (int) $cat->aggregateonlygraded, 'hidden' => (int) $cat->hidden,
            ];
        }
        $gradeitemsbycm = [];
        $allitems = [];
        foreach (\grade_item::fetch_all(['courseid' => $courseid]) ?: [] as $gi) {
            $row = [
                'id' => (int) $gi->id, 'itemtype' => $gi->itemtype, 'itemmodule' => $gi->itemmodule,
                'iteminstance' => $gi->iteminstance === null ? null : (int) $gi->iteminstance,
                'itemnumber' => $gi->itemnumber === null ? null : (int) $gi->itemnumber,
                'itemname' => $gi->itemname, 'gradetype' => (int) $gi->gradetype, 'grademax' => (float) $gi->grademax,
                'grademin' => (float) $gi->grademin, 'gradepass' => (float) $gi->gradepass,
                'categoryid' => $gi->categoryid === null ? null : (int) $gi->categoryid, 'hidden' => (int) $gi->hidden,
                'aggregationcoef2' => (float) $gi->aggregationcoef2, 'weightoverride' => (int) $gi->weightoverride,
            ];
            $allitems[$row['id']] = $row;
            if ($gi->itemtype === 'course') {
                $s['grade']['course_item'] = $row;
            }
            if ($gi->itemtype === 'mod') {
                $gradeitemsbycm[$gi->itemmodule . ':' . $gi->iteminstance][] = $row;
            }
        }
        $s['grade']['items_total'] = count($allitems);
        $s['grade']['all_items'] = $allitems;

        // Modules.
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $m = [
                'cmid' => (int) $cm->id,
                'modname' => $cm->modname,
                'instanceid' => (int) $cm->instance,
                'name' => $cm->name,
                'sectionid' => (int) $cm->section,
                'sectionnumber' => (int) $cm->sectionnum,
                'visible' => (int) $cm->visible,
                'visibleoncoursepage' => (int) $cm->visibleoncoursepage,
                'uservisible' => (bool) $cm->uservisible,
                'availableinfo' => is_string($cm->availableinfo) ? $cm->availableinfo : '',
                'completion' => (int) $cm->completion,
                'completionview' => (int) $cm->completionview,
                'completiongradeitemnumber' => $cm->completiongradeitemnumber === null ? null
                    : (int) $cm->completiongradeitemnumber,
                'completionpassgrade' => (int) $cm->completionpassgrade,
                'completionexpected' => (int) $cm->completionexpected,
                'availability' => self::decode_availability($cm->availability),
                'availability_raw' => $cm->availability,
                'contextid' => (int) $cm->context->id,
                'texts' => [],
                'extra' => [],
                'grade_items' => $gradeitemsbycm[$cm->modname . ':' . $cm->instance] ?? [],
                'files' => ['count' => 0, 'missing_in_storage' => [], 'list' => []],
                'plugin_version' => get_config('mod_' . $cm->modname, 'version'),
            ];
            $s['plugins']['used']['mod'][$cm->modname] = true;
            foreach (self::walk_conditions($m['availability']) as $cond) {
                if (!empty($cond['type'])) {
                    $s['plugins']['used']['availability'][$cond['type']] = true;
                }
            }
            // Module-specific settings (DML whitelist).
            try {
                self::load_module_record($m, $cm->modname, (int) $cm->instance);
            } catch (\Throwable $e) {
                $s['warnings'][] = "{$cm->modname} {$cm->id}: " . $e->getMessage();
            }
            // Files of the module context and whether their content really exists in the file store.
            $filerecs = $DB->get_records_select(
                'files',
                'contextid = :ctx AND filename <> :dot',
                ['ctx' => $cm->context->id, 'dot' => '.'],
                'id',
                'id, component, filearea, itemid, filepath, filename, filesize, mimetype, referencefileid'
            );
            foreach ($filerecs as $fr) {
                $m['files']['count']++;
                $entry = ['id' => (int) $fr->id, 'component' => $fr->component, 'filearea' => $fr->filearea,
                    'filepath' => $fr->filepath,
                    'filename' => $fr->filename, 'filesize' => (int) $fr->filesize, 'mimetype' => $fr->mimetype,
                    'is_reference' => $fr->referencefileid !== null, 'readable' => null];
                $stored = $fs->get_file_by_id($fr->id);
                if ($stored) {
                    try {
                        $entry['readable'] = $filesystem->is_file_readable_locally_by_storedfile($stored)
                            || $filesystem->is_file_readable_remotely_by_storedfile($stored);
                    } catch (\Throwable $e) {
                        $entry['readable'] = false;
                    }
                }
                if ($entry['readable'] === false) {
                    $m['files']['missing_in_storage'][] = $entry;
                }
                $m['files']['list'][] = $entry;
            }
            $s['modules'][(int) $cm->id] = $m;
        }

        // Enrolment instances (all, including disabled).
        foreach (enrol_get_instances($courseid, false) as $inst) {
            $s['enrols'][] = [
                'id' => (int) $inst->id, 'enrol' => $inst->enrol, 'status' => (int) $inst->status, 'name' => $inst->name,
                'enrolstartdate' => (int) $inst->enrolstartdate, 'enrolenddate' => (int) $inst->enrolenddate,
                'customint6' => $inst->customint6 === null ? null : (int) $inst->customint6, 'roleid' => (int) $inst->roleid,
                'has_password' => !empty($inst->password),
            ];
        }

        // Blocks shown on the course page (course-level AND inherited from parent contexts).
        self::load_blocks($s, $course, $context);
        foreach ($s['blocks'] as $b) {
            $s['plugins']['used']['block'][$b['name']] = true;
        }

        // Installed plugins of the relevant types.
        $pm = \core_plugin_manager::instance();
        foreach (['mod', 'block', 'availability', 'qtype'] as $type) {
            $s['plugins']['installed'][$type] = $pm->get_installed_plugins($type);
        }
        foreach ($s['plugins']['used'] as $type => &$list) {
            $list = array_keys($list);
        }
        unset($list);

        // Course completion criteria.
        $completion = new \completion_info($course);
        $s['completion_criteria'] = $completion->is_enabled() ? count($completion->get_criteria()) : 0;

        return $s;
    }

    /**
     * Read whitelisted settings and text fields of the module instance.
     *
     * @param array $m module snapshot (by reference)
     * @param string $modname
     * @param int $instanceid
     */
    protected static function load_module_record(array &$m, string $modname, int $instanceid): void {
        global $DB;
        $fields = self::MODULE_FIELDS[$modname] ?? [];
        $texts = self::MODULE_TEXTS[$modname] ?? ['intro'];
        $columns = $DB->get_columns($modname);
        $select = array_values(array_filter(array_unique(array_merge(['id'], $fields, $texts)), function ($c) use ($columns) {
            return isset($columns[$c]);
        }));
        $rec = $DB->get_record($modname, ['id' => $instanceid], implode(',', $select), IGNORE_MISSING);
        if (!$rec) {
            $m['extra']['missing_instance'] = true;
            return;
        }
        foreach ($fields as $f) {
            if (property_exists($rec, $f)) {
                $m['extra'][$f] = is_numeric($rec->$f) ? $rec->$f + 0 : $rec->$f;
            }
        }
        foreach ($texts as $t) {
            if (property_exists($rec, $t) && $rec->$t !== null) {
                $m['texts'][$t] = $rec->$t;
            }
        }
        // Derived counts (one cheap query each).
        switch ($modname) {
            case 'quiz':
                $m['extra']['slots'] = $DB->count_records('quiz_slots', ['quizid' => $instanceid]);
                break;
            case 'feedback':
                $m['extra']['items'] = $DB->count_records('feedback_item', ['feedback' => $instanceid]);
                break;
            case 'glossary':
                $m['extra']['entries'] = $DB->count_records('glossary_entries', ['glossaryid' => $instanceid]);
                $m['extra']['categories'] = $DB->count_records('glossary_categories', ['glossaryid' => $instanceid]);
                break;
            case 'book':
                $chapters = $DB->get_records('book_chapters', ['bookid' => $instanceid], 'pagenum', 'id, title, hidden, content');
                $m['extra']['chapters'] = count($chapters);
                $i = 0;
                foreach ($chapters as $ch) {
                    $m['texts']["chapter[$i]"] = $ch->content;
                    $i++;
                }
                break;
            case 'hvp':
                if (!empty($rec->main_library_id)) {
                    $lib = $DB->get_record(
                        'hvp_libraries',
                        ['id' => $rec->main_library_id],
                        'machine_name, major_version, minor_version',
                        IGNORE_MISSING
                    );
                    $m['extra']['machine_name'] = $lib ? "{$lib->machine_name} {$lib->major_version}.{$lib->minor_version}" : null;
                }
                break;
            case 'game':
                if (!empty($rec->glossaryid)) {
                    $m['extra']['glossary_entries_available'] = $DB->count_records(
                        'glossary_entries',
                        ['glossaryid' => $rec->glossaryid]
                    );
                    if (!empty($rec->glossarycategoryid)) {
                        $m['extra']['glossary_category_entries'] = $DB->count_records_sql(
                            'SELECT COUNT(1) FROM {glossary_entries_categories} ec WHERE ec.categoryid = :cat',
                            ['cat' => $rec->glossarycategoryid]
                        );
                    }
                }
                break;
        }
    }

    /**
     * Blocks displayed on the course page, including inherited ones. Uses block_manager; falls back to DML.
     *
     * @param array $s
     * @param \stdClass $course
     * @param \context_course $context
     */
    protected static function load_blocks(array &$s, \stdClass $course, \context_course $context): void {
        global $DB;
        try {
            $page = new \moodle_page();
            $page->set_course($course);
            $page->set_context($context);
            $page->set_pagelayout('course');
            $page->set_pagetype('course-view-' . $course->format);
            $page->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
            $page->blocks->add_regions(['side-pre', 'side-post', 'content'], false);
            $page->blocks->set_default_region('side-pre');
            $page->blocks->load_blocks(true);
            $page->blocks->create_all_block_instances();
            foreach ($page->blocks->get_regions() as $region) {
                foreach ($page->blocks->get_blocks_for_region($region) as $block) {
                    $inst = $block->instance;
                    $parent = \context::instance_by_id($inst->parentcontextid, IGNORE_MISSING);
                    $s['blocks'][] = [
                        'instanceid' => (int) $inst->id,
                        'name' => $inst->blockname,
                        'title' => isset($block->config->title) ? $block->config->title : ($block->title ?? null),
                        'region' => $region,
                        'visible' => isset($inst->visible) ? (int) $inst->visible : 1,
                        'parentcontextlevel' => $parent ? $parent->contextlevel : null,
                        'inherited' => $parent ? ($parent->id !== $context->id) : null,
                        'pagetypepattern' => $inst->pagetypepattern,
                        'config' => self::safe_config($block->config),
                    ];
                }
            }
            $s['blocks_source'] = 'block_manager';
            return;
        } catch (\Throwable $e) {
            $s['warnings'][] = 'block_manager falhou, usando consulta direta: ' . $e->getMessage();
        }
        // Fallback: block instances whose parent context is the course or an ancestor showing in subcontexts.
        $ctxids = $context->get_parent_context_ids(true);
        [$insql, $params] = $DB->get_in_or_equal($ctxids, SQL_PARAMS_NAMED);
        $sql = "SELECT bi.* FROM {block_instances} bi WHERE bi.parentcontextid $insql
                  AND (bi.parentcontextid = :ctx OR bi.showinsubcontexts = 1)
                  AND (bi.pagetypepattern = '*' OR bi.pagetypepattern LIKE 'course-view%')";
        $params['ctx'] = $context->id;
        foreach ($DB->get_records_sql($sql, $params) as $inst) {
            $config = $inst->configdata ? @unserialize(base64_decode($inst->configdata), ['allowed_classes' => ['stdClass']])
                : null;
            $parent = \context::instance_by_id($inst->parentcontextid, IGNORE_MISSING);
            $s['blocks'][] = [
                'instanceid' => (int) $inst->id, 'name' => $inst->blockname,
                'title' => isset($config->title) ? $config->title : null, 'region' => $inst->defaultregion, 'visible' => 1,
                'parentcontextlevel' => $parent ? $parent->contextlevel : null,
                'inherited' => $parent ? ($parent->id !== $context->id) : null,
                'pagetypepattern' => $inst->pagetypepattern, 'config' => self::safe_config($config),
            ];
        }
        $s['blocks_source'] = 'dml-fallback';
    }

    /**
     * Keep only scalar/array config values (no objects, no secrets).
     *
     * @param mixed $config
     * @return array|null
     */
    protected static function safe_config($config): ?array {
        if (!$config) {
            return null;
        }
        $out = [];
        foreach ((array) $config as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[$k] = is_string($v) && strlen($v) > 2000 ? substr($v, 0, 2000) . '…' : $v;
            } else if (is_array($v) || is_object($v)) {
                $out[$k] = json_decode(json_encode($v), true);
            }
        }
        return $out;
    }

    /**
     * Decode an availability JSON string.
     *
     * @param string|null $json
     * @return array|null
     */
    public static function decode_availability(?string $json): ?array {
        if ($json === null || $json === '') {
            return null;
        }
        $d = json_decode($json, true);
        return is_array($d) ? $d : ['_error' => 'JSON inválido'];
    }

    /**
     * Iterate leaf conditions of an availability tree.
     *
     * @param array|null $tree
     * @return \Generator
     */
    public static function walk_conditions(?array $tree): \Generator {
        if (!$tree || !isset($tree['c']) || !is_array($tree['c'])) {
            return;
        }
        foreach ($tree['c'] as $c) {
            if (isset($c['type'])) {
                yield $c;
            } else if (isset($c['c'])) {
                yield from self::walk_conditions($c);
            }
        }
    }
}
