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

namespace local_mooc_audit\local\presentation;

use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;
use moodle_url;
use stdClass;

/**
 * Builds, at display time, what the report shows for a stored result (specs/006, data-model.md).
 *
 * Texts come from lang/ in the language of whoever views the page, names from the current course structure and
 * actions only for destinations that exist and that the viewer may use. The stored result is never changed: its
 * evidence stays available as technical details, and results without a catalogue message keep the stored text.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finding {
    /** @var array Edit form section of each module action (moodleform_mod headers, the same the core course page uses). */
    const SHOWONLY = [
        'restrict' => 'availabilityconditionsheader',
        'completion' => 'activitycompletionheader',
        'grade' => 'modstandardgrade',
        'timing' => 'timing',
        'visibility' => 'modstandardelshdr',
    ];

    /** @var int */
    protected int $courseid;
    /** @var \course_modinfo */
    protected \course_modinfo $modinfo;
    /** @var \context_course */
    protected \context_course $context;
    /** @var array policy applied by the run */
    protected array $policy;
    /** @var moodle_url */
    protected moodle_url $reporturl;
    /** @var callable component => installed? */
    protected $installed;
    /** @var array cmid => true when the activity has an invalid completion condition (X-DEP-02, not conformant) */
    protected array $blockedcms = [];
    /** @var bool CL-06 or CL-07 not conformant */
    protected bool $datesfirst = false;
    /** @var bool CL-12 total not conformant */
    protected bool $totalfirst = false;
    /** @var array "rule|cmid" of the not conformant results, for related checks */
    protected array $present = [];
    /** @var array plugin constant => true when a shown result depends on it and it is missing */
    protected array $missingused = [];

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param array $results every stored result of the report (for precedences and relations)
     * @param array $policy policy applied by the run (policy::load() when unknown)
     * @param moodle_url $reporturl page to come back to after a fix
     * @param callable|null $installed fn(string $component): bool (tests); default core_plugin_manager
     */
    public function __construct(int $courseid, array $results, array $policy, moodle_url $reporturl, ?callable $installed = null) {
        $this->courseid = $courseid;
        $this->modinfo = get_fast_modinfo($courseid);
        $this->context = \context_course::instance($courseid);
        $this->policy = $policy;
        $this->reporturl = $reporturl;
        $this->installed = $installed ?? fn(string $c) => (bool) \core_plugin_manager::instance()->get_plugin_info($c);
        foreach ($results as $r) {
            if ($r['status'] !== status::NAO_CONFORME) {
                continue;
            }
            $occ = catalog::parse_occurrence($r['occurrence'] ?? null);
            if ($r['rule'] === 'X-DEP-02' && $occ['check'] === 'target_completion' && !empty($r['cmid'])) {
                $this->blockedcms[(int) $r['cmid']] = true;
            }
            $this->datesfirst = $this->datesfirst || in_array($r['rule'], ['CL-06', 'CL-07'], true);
            $this->totalfirst = $this->totalfirst || ($r['rule'] === 'CL-12' && $occ['check'] === 'total_sum');
            $this->present[$r['rule'] . '|' . (int) ($r['cmid'] ?? 0)] = true;
        }
    }

    /**
     * Presentable result.
     *
     * @param array $r stored result (contract 1.0)
     * @return stdClass problem, why, fix, alternatives[], who, verify, before, actions[], fallback, check
     */
    public function present(array $r): stdClass {
        $occ = catalog::parse_occurrence($r['occurrence'] ?? null);
        $out = (object) ['fallback' => true, 'check' => $occ['check'], 'actions' => [], 'alternatives' => []];
        $variant = $occ['check'] !== '' ? catalog::variant($r, $occ['check'], $occ['subject']) : null;
        if ($variant === null) {
            return $out;
        }
        $cm = $this->cm($r['cmid'] ?? null);
        $variant = $this->adjust($r, $occ['check'], $variant, $cm);
        $entry = catalog::entry($r['rule'], $occ['check'], $variant);
        $who = $entry['who'];
        $actions = $entry['actions'];
        if ($entry['plugin'] && !$this->is_installed($entry['plugin'])) {
            $this->missingused[$entry['plugin']] = true;
            $variant = 'noplugin';
            $who = catalog::WHO_ADMIN;
            $actions = [];
        }
        if ($variant === 'nogame') {
            $this->missingused[catalog::PLUGIN_GAME] = true;
        }
        $key = catalog::key($r['rule'], $occ['check'], $variant);
        $a = $this->params($r, $occ, $cm);
        $sm = get_string_manager();
        $out->fallback = false;
        $out->problem = get_string($key . '_problem', 'local_mooc_audit', $a);
        $out->why = get_string($key . '_why', 'local_mooc_audit', $a);
        $out->fix = get_string($key . '_fix', 'local_mooc_audit', $a);
        for ($i = 1; $sm->string_exists($key . '_alt' . $i, 'local_mooc_audit'); $i++) {
            $out->alternatives[] = get_string($key . '_alt' . $i, 'local_mooc_audit', $a);
        }
        $out->verifyspecific = $sm->string_exists($key . '_verify', 'local_mooc_audit');
        $out->verify = $out->verifyspecific
            ? get_string($key . '_verify', 'local_mooc_audit', $a) : get_string('msg_verify_default', 'local_mooc_audit');
        foreach ($actions as $type) {
            foreach ($this->actions($type, $r, $occ, $cm) as $action) {
                if ($action) {
                    $out->actions[] = $action;
                }
            }
        }
        if (
            $r['rule'] === 'CL-05' && $who === catalog::WHO_TEAM
                && count(\core_course_category::make_categories_list('moodle/course:changecategory')) > 1
        ) {
            $who = catalog::WHO_TEACHER;
        }
        $out->whoyou = $who === catalog::WHO_TEACHER && $out->actions;
        $out->who = $out->whoyou ? get_string('who_you', 'local_mooc_audit') : get_string('who_' . $who, 'local_mooc_audit');
        $out->before = $this->before($r, $occ['check'], $a);
        // Without the plugin there is no screen to go to: the problem belongs to the course as a whole.
        $out->place = $variant === 'noplugin' ? get_string('place_course', 'local_mooc_audit') : $this->place($r, $cm, $a);
        return $out;
    }

    /**
     * Missing plugins that shown results depend on (call after present()).
     *
     * @return array list of plugin display names
     */
    public function missing_plugins(): array {
        $out = [];
        foreach (array_keys($this->missingused) as $plugin) {
            $out[] = get_string('plugin_' . $plugin, 'local_mooc_audit', catalog::required_plugins($this->policy)[$plugin]);
        }
        return $out;
    }

    /**
     * Variant adjustments that depend on the other results or on the site (research R6, R8).
     *
     * @param array $r
     * @param string $check
     * @param string $variant
     * @param \cm_info|null $cm
     * @return string
     */
    protected function adjust(array $r, string $check, string $variant, ?\cm_info $cm): string {
        if ($r['rule'] === 'CL-17' && $check === 'completion' && $variant === 'nc') {
            $cmid = (int) ($r['cmid'] ?? 0);
            if (
                ($cm && $cm->modname === 'label') || isset($this->present['CL-09|' . $cmid])
                    || isset($this->present['CL-19|' . $cmid])
            ) {
                return 'ncoff';
            }
        }
        if ($r['rule'] === 'CL-18' && $variant === 'nc' && !$this->is_installed(catalog::PLUGIN_GAME)) {
            return 'nogame';
        }
        return $variant;
    }

    /**
     * Whether a plugin the guidance depends on is installed.
     *
     * @param string $plugin catalog::PLUGIN_*
     * @return bool
     */
    protected function is_installed(string $plugin): bool {
        return ($this->installed)(catalog::required_plugins($this->policy)[$plugin]);
    }

    /**
     * Current activity, or null when the id is empty or the activity no longer exists.
     *
     * @param mixed $cmid
     * @return \cm_info|null
     */
    protected function cm($cmid): ?\cm_info {
        $cmid = (int) $cmid;
        return $cmid ? ($this->modinfo->get_cms()[$cmid] ?? null) : null;
    }

    /**
     * Display name of an activity, or a removed-activity label.
     *
     * @param int $cmid
     * @return string
     */
    protected function name(int $cmid): string {
        $cm = $this->cm($cmid);
        return $cm ? format_string($cm->name, true, ['context' => $cm->context, 'escape' => false])
            : get_string('msg_removed', 'local_mooc_audit', $cmid);
    }

    /**
     * Message parameters: names, labels of the Moodle screens and policy values.
     *
     * @param array $r
     * @param array $occ parsed occurrence
     * @param \cm_info|null $cm
     * @return stdClass
     */
    protected function params(array $r, array $occ, ?\cm_info $cm): stdClass {
        $p = $this->policy;
        $a = new stdClass();
        $a->name = !empty($r['cmid']) ? $this->name((int) $r['cmid']) : (string) $r['local'];
        $subject = (string) $occ['subject'];
        $a->target = preg_match('/^cm:(\d+)$/', $subject, $m) ? $this->name((int) $m[1]) : '';
        if ($r['rule'] === 'X-DEP-04' && $subject !== '') {
            $names = array_map(fn($id) => '"' . $this->name((int) $id) . '"', explode('>', $subject));
            $names[] = $names[0];
            $a->nodes = implode(' → ', $names);
        }
        if (isset($r['section'])) {
            $sec = $this->modinfo->get_section_info((int) $r['section']);
            $a->section = $sec ? get_section_name($this->courseid, $sec) : (string) $r['section'];
        }
        $a->file = basename(preg_replace('~^[^:]*:~', '', $subject)) ?: $subject;
        $a->plugin = preg_replace('/^(mod|availability):/', '', $subject);
        $a->block = $subject;
        foreach ($p['required_blocks']['html'] ?? [] as $title) {
            if (helpers::norm($title) === $subject) {
                $a->block = $title;
            }
        }
        $a->min = (int) ($p['min_quiz_attempts'] ?? 2);
        $a->grade = $p['certificate_min_grade'] ?? 60;
        $a->total = $p['course_total_max'] ?? 100;
        $a->category = $p['target_category_name'] ?? '';
        $a->year = $p['reoffer_year'] ?? '';
        $a->start = ($p['required_start_date'] ?? '01/01') . '/' . $a->year;
        $a->end = implode(' ' . get_string('msg_or', 'local_mooc_audit') . ' ', array_map(
            fn($d) => $d . '/' . $a->year,
            (array) ($p['allowed_end_dates'] ?? ['31/12'])
        ));
        $a->forum = $p['required_forum_name'] ?? '';
        $a->profile = $p['required_feedback_profile'] ?? '';
        $a->evaluation = $p['required_feedback_evaluation'] ?? '';
        $a->licencelabel = $p['licence_label_text'] ?? '';
        $a->licencefeedback = $p['licence_feedback_name'] ?? '';
        $a->licencecertificate = $p['licence_certificate_name'] ?? '';
        $components = catalog::required_plugins($p);
        foreach ([catalog::PLUGIN_CERTIFICATE, catalog::PLUGIN_GAME, catalog::PLUGIN_PROGRESS] as $plugin) {
            $a->{'plugin' . $plugin} = get_string('plugin_' . $plugin, 'local_mooc_audit', $components[$plugin]);
        }
        foreach (
            [
            'restrictaccess' => ['restrictaccess', 'availability'], 'completion' => ['activitycompletion', 'completion'],
            'gradeheader' => ['gradenoun', 'moodle'], 'attempts' => ['attemptsallowed', 'quiz'],
            'quizclose' => ['quizclose', 'quiz'], 'timing' => ['timing', 'quiz'], 'aggregation' => ['aggregation', 'grades'],
            'onlygraded' => ['aggregateonlygraded', 'grades'], 'gradebook' => ['gradebooksetup', 'grades'],
            'coursetotal' => ['coursetotal', 'grades'], 'selfenrol' => ['pluginname', 'enrol_self'],
            'newenrols' => ['newenrols', 'enrol_self'], 'startdate' => ['startdate', 'moodle'],
            'enddate' => ['enddate', 'moodle'], 'coursecategory' => ['coursecategory', 'moodle'],
            'hiddensections' => ['hiddensections', 'moodle'], 'invisible' => ['hiddensectionsinvisible', 'moodle'],
            'editsettings' => ['editsettings', 'moodle'], 'commonsettings' => ['modstandardels', 'form'],
            'editquestions' => ['editquestions', 'quiz'], 'summary' => ['summary', 'moodle'],
            ] as $field => [$id, $component]
        ) {
            $a->$field = get_string($id, $component);
        }
        return $a;
    }

    /**
     * Actions of a type for a result: label and URL, only when the destination exists and the viewer may use it.
     *
     * @param string $type contracts/presentation.md
     * @param array $r
     * @param array $occ
     * @param \cm_info|null $cm
     * @return array list of objects with label and url
     */
    protected function actions(string $type, array $r, array $occ, ?\cm_info $cm): array {
        $subject = (string) $occ['subject'];
        $subjectcm = preg_match('/^cm:(\d+)$/', $subject, $m) ? $this->cm((int) $m[1]) : null;
        $local = $this->reporturl->out(false);
        switch ($type) {
            case 'cm_restrict':
            case 'cm_completion':
            case 'cm_grade':
            case 'cm_timing':
            case 'cm_visibility':
            case 'cm_settings':
            case 'cm_dates':
                return $cm ? [$this->module_action($cm, substr($type, 3))] : [];
            case 'subject_restrict':
            case 'subject_completion':
            case 'subject_visibility':
            case 'subject_settings':
                return $subjectcm ? [$this->module_action($subjectcm, substr($type, 8))] : [];
            case 'cycle_restrict':
                $out = [];
                foreach (explode('>', $subject) as $id) {
                    if ($node = $this->cm((int) $id)) {
                        $out[] = $this->module_action($node, 'restrict');
                    }
                }
                return array_values(array_filter($out));
            case 'quiz_questions':
                if (!$cm || $cm->modname !== 'quiz' || !has_capability('mod/quiz:manage', $cm->context)) {
                    return [];
                }
                return [$this->action(get_string('editquestions', 'quiz'), $cm, new moodle_url(
                    '/mod/quiz/edit.php',
                    ['cmid' => $cm->id]
                ))];
            case 'mod_view':
                if (!$cm || !has_capability('moodle/course:manageactivities', $cm->context)) {
                    return [];
                }
                return [$this->action(get_string('act_open', 'local_mooc_audit'), $cm, $cm->url ?? new moodle_url(
                    '/mod/' . $cm->modname . '/view.php',
                    ['id' => $cm->id]
                ))];
            case 'section_edit':
            case 'section_restrict':
                $sec = isset($r['section']) ? $this->modinfo->get_section_info((int) $r['section']) : null;
                if (!$sec || !has_capability('moodle/course:update', $this->context)) {
                    return [];
                }
                $params = ['id' => $sec->id];
                if ($type === 'section_restrict') {
                    $params['showonly'] = 'availabilityconditions';
                }
                $label = $type === 'section_restrict' ? get_string('restrictaccess', 'availability')
                    : get_string('act_section', 'local_mooc_audit');
                return [(object) ['label' => $label . ': ' . get_section_name($this->courseid, $sec),
                    'url' => (new moodle_url('/course/editsection.php', $params))->out(false)]];
            case 'enrol_self':
                $id = preg_match('/^enrol:(\d+)$/', $subject, $m) ? (int) $m[1] : 0;
                global $DB;
                if (
                    !$id || !has_capability('enrol/self:config', $this->context)
                        || !$DB->record_exists('enrol', ['id' => $id, 'courseid' => $this->courseid, 'enrol' => 'self'])
                ) {
                    return [];
                }
                return [(object) ['label' => get_string('act_edit', 'local_mooc_audit', get_string('pluginname', 'enrol_self')),
                    'url' => (new moodle_url('/enrol/editinstance.php', ['courseid' => $this->courseid, 'id' => $id,
                        'type' => 'self', 'returnurl' => $local]))->out(false)]];
            case 'enrol_list':
                return $this->course_action(
                    'moodle/course:enrolreview',
                    get_string('enrolmentinstances', 'enrol'),
                    new moodle_url('/enrol/instances.php', ['id' => $this->courseid])
                );
            case 'course_settings':
                return $this->course_action(
                    'moodle/course:update',
                    get_string('act_coursesettings', 'local_mooc_audit'),
                    new moodle_url('/course/edit.php', ['id' => $this->courseid, 'returnto' => 'url', 'returnurl' => $local])
                );
            case 'gradebook':
                return $this->course_action(
                    'moodle/grade:manage',
                    get_string('gradebooksetup', 'grades'),
                    new moodle_url('/grade/edit/tree/index.php', ['id' => $this->courseid])
                );
            case 'import':
                return $this->course_action(
                    'moodle/backup:backuptargetimport',
                    get_string('act_import', 'local_mooc_audit'),
                    new moodle_url('/backup/import.php', ['id' => $this->courseid])
                );
            case 'course_page':
                return $this->course_action(
                    'moodle/course:update',
                    get_string('act_coursepage', 'local_mooc_audit'),
                    new moodle_url('/course/view.php', ['id' => $this->courseid])
                );
        }
        return [];
    }

    /**
     * Action on the edit form of an activity, opened on one section when the form has it.
     *
     * @param \cm_info $cm
     * @param string $what restrict, completion, grade, timing, visibility, settings or dates
     * @return stdClass|null
     */
    protected function module_action(\cm_info $cm, string $what): ?stdClass {
        if (!has_capability('moodle/course:manageactivities', $cm->context)) {
            return null;
        }
        if ($what === 'dates') {
            $what = $cm->modname === 'quiz' ? 'timing' : 'settings';
        }
        $labels = ['restrict' => ['restrictaccess', 'availability'], 'completion' => ['activitycompletion', 'completion'],
            'grade' => ['gradenoun', 'moodle'], 'timing' => ['timing', 'quiz'], 'visibility' => ['modstandardels', 'form'],
            'settings' => ['editsettings', 'moodle']];
        $params = ['update' => $cm->id];
        if (isset(self::SHOWONLY[$what])) {
            $params['showonly'] = self::SHOWONLY[$what];
        }
        return $this->action(get_string(...$labels[$what]), $cm, new moodle_url('/course/modedit.php', $params));
    }

    /**
     * Action object.
     *
     * @param string $label screen label
     * @param \cm_info $cm
     * @param moodle_url $url
     * @return stdClass
     */
    protected function action(string $label, \cm_info $cm, moodle_url $url): stdClass {
        return (object) ['label' => $label . ': ' . format_string($cm->name, true, ['context' => $cm->context,
            'escape' => false]), 'url' => $url->out(false)];
    }

    /**
     * Course-level action, when the viewer has the capability.
     *
     * @param string $capability
     * @param string $label
     * @param moodle_url $url
     * @return array
     */
    protected function course_action(string $capability, string $label, moodle_url $url): array {
        return has_capability($capability, $this->context) ? [(object) ['label' => $label, 'url' => $url->out(false)]] : [];
    }

    /**
     * Where the problem is, in the viewer's language: the activity, the section or the course screen.
     *
     * @param array $r
     * @param \cm_info|null $cm
     * @param stdClass $a
     * @return string
     */
    protected function place(array $r, ?\cm_info $cm, stdClass $a): string {
        if (!empty($r['cmid'])) {
            return $a->name;
        }
        if (isset($a->section)) {
            return get_string('place_section', 'local_mooc_audit', $a->section);
        }
        $locus = (string) ($r['locus'] ?? '');
        return $locus !== '' && $locus !== \local_mooc_audit\local\rule\base::LOCUS_NONE
            ? \local_mooc_audit\local\locator::title($locus) : get_string('place_course', 'local_mooc_audit');
    }

    /**
     * Precedence note (research R7), or null.
     *
     * @param array $r
     * @param string $check
     * @param stdClass $a
     * @return string|null
     */
    protected function before(array $r, string $check, stdClass $a): ?string {
        $cmid = (int) ($r['cmid'] ?? 0);
        if ($cmid && isset($this->blockedcms[$cmid]) && !($r['rule'] === 'X-DEP-02' && $check === 'target_completion')) {
            return get_string('msg_before_restriction', 'local_mooc_audit', $a);
        }
        if ($this->datesfirst && (($r['rule'] === 'CL-08' && $check === 'self_enrol') || $r['rule'] === 'CL-14')) {
            return get_string('msg_before_dates', 'local_mooc_audit', $a);
        }
        if ($this->totalfirst && $r['rule'] === 'CL-23' && $check === 'min_grade') {
            return get_string('msg_before_total', 'local_mooc_audit', $a);
        }
        return null;
    }
}
