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

namespace local_mooc_audit\local\comparison;

use local_mooc_audit\local\helpers;

/**
 * Differences between two matched modules and between two courses, each classified as expected, relevant or
 * informative (specs/003 research R3). A difference is relevant when it can change what students get or how they are
 * assessed; expected when it is the normal consequence of a new offer (ids, dates); informative otherwise.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class differ {
    /** @var string Normal consequence of a new offer (ids, dates, offer settings). */
    const EXPECTED = 'esperada';
    /** @var string Needs review. */
    const RELEVANT = 'relevante';
    /** @var string No proven operational impact. */
    const INFO = 'informativa';

    /** @var string[] Date settings of modules (shifted in every reoffer). */
    const DATE_FIELDS = ['timeopen', 'timeclose', 'duedate', 'cutoffdate', 'allowsubmissionsfromdate', 'timeavailableto'];
    /** @var string[] Settings that only change how content is displayed. */
    const DISPLAY_FIELDS = ['display', 'numbering', 'printintro', 'displayformat'];
    /** @var string[] Counts of content held by the module (questions, items, entries, chapters). */
    const CONTENT_COUNTS = ['slots', 'items', 'entries', 'categories', 'chapters', 'glossary_entries_available',
        'glossary_category_entries'];
    /** @var array Settings that reference another module instance of the same course: field => module type. */
    const REFERENCES = ['glossaryid' => 'glossary', 'quizid' => 'quiz', 'bookid' => 'book'];
    /** @var string[] Ids not compared at all. */
    const IGNORED = ['main_library_id', 'missing_instance', 'glossarycategoryid'];
    /** @var string[] Course settings that belong to the offer cycle (checked by the policy in the target). */
    const OFFER_COURSE_FIELDS = ['fullname', 'shortname', 'startdate', 'enddate', 'visible'];
    /** @var string[] Course settings whose change needs review. */
    const COURSE_SETTINGS = ['format', 'enablecompletion', 'showcompletionconditions', 'lang'];
    /** @var string[] Course ids (expected to change, only counted). */
    const COURSE_IDS = ['id', 'contextid', 'timecreated', 'timemodified'];

    /** @var array Kind order, most severe first. */
    const KIND_ORDER = [self::RELEVANT => 0, self::INFO => 1, self::EXPECTED => 2];

    /**
     * Build a difference.
     *
     * @param string $scope module|course|section|enrol|block|grade
     * @param string $field
     * @param mixed $source
     * @param mixed $target
     * @param string $kind
     * @param string $code
     * @param array $a extra placeholders of the explanation
     * @return array
     */
    public static function d(string $scope, string $field, $source, $target, string $kind, string $code, array $a = []): array {
        $a = (object) array_merge(['field' => $field, 'source' => self::show($source), 'target' => self::show($target)], $a);
        return ['scope' => $scope, 'field' => $field, 'source' => self::value($source), 'target' => self::value($target),
            'kind' => $kind, 'code' => $code, 'explanation' => get_string('cmp_code_' . $code, 'local_mooc_audit', $a)];
    }

    /**
     * JSON-safe value, long strings truncated.
     *
     * @param mixed $v
     * @return mixed
     */
    protected static function value($v) {
        if (is_string($v) && \core_text::strlen($v) > 160) {
            return \core_text::substr($v, 0, 160) . '…';
        }
        return $v;
    }

    /**
     * Human rendering of a value inside an explanation.
     *
     * @param mixed $v
     * @return string
     */
    public static function show($v): string {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_array($v)) {
            return \core_text::substr(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 160);
        }
        return \core_text::substr((string) $v, 0, 160);
    }

    /**
     * Most severe kind among differences (relevante > informativa > esperada), 'nenhuma' when empty.
     *
     * @param array $diffs
     * @return string
     */
    public static function relevance(array $diffs): string {
        $best = 'nenhuma';
        $rank = 9;
        foreach ($diffs as $d) {
            if (self::KIND_ORDER[$d['kind']] < $rank) {
                $rank = self::KIND_ORDER[$d['kind']];
                $best = $d['kind'];
            }
        }
        return $best;
    }

    /**
     * Context shared by every pair: match map, instance map per module type and grade item map.
     *
     * @param array $src
     * @param array $dst
     * @param array $map source cmid => target cmid
     * @return array
     */
    public static function context(array $src, array $dst, array $map): array {
        $instances = [];
        $grades = [];
        if (!empty($src['grade']['course_item']) && !empty($dst['grade']['course_item'])) {
            $grades[$src['grade']['course_item']['id']] = $dst['grade']['course_item']['id'];
        }
        foreach ($map as $scm => $tcm) {
            $a = $src['modules'][$scm];
            $b = $dst['modules'][$tcm];
            $instances[$a['modname']][$a['instanceid']] = $b['instanceid'];
            foreach ($a['grade_items'] as $gi) {
                foreach ($b['grade_items'] as $tgi) {
                    if ($tgi['itemnumber'] === $gi['itemnumber']) {
                        $grades[$gi['id']] = $tgi['id'];
                    }
                }
            }
        }
        $srcbyinstance = [];
        foreach ($src['modules'] as $m) {
            $srcbyinstance[$m['modname']][$m['instanceid']] = $m;
        }
        return ['src' => $src, 'dst' => $dst, 'map' => $map, 'instances' => $instances, 'grades' => $grades,
            'srcbyinstance' => $srcbyinstance];
    }

    /**
     * Differences of a matched pair.
     *
     * @param array $a source module
     * @param array $b target module
     * @param array $ctx from context()
     * @return array [diffs, expected_count]
     */
    public static function module_pair(array $a, array $b, array $ctx): array {
        $diffs = [];
        $expected = 1; // The cmid itself.
        $m = 'module';

        if (helpers::norm($a['name']) !== helpers::norm($b['name'])) {
            $diffs[] = self::d($m, 'name', $a['name'], $b['name'], self::INFO, 'renamed');
        }
        if ($a['sectionnumber'] !== $b['sectionnumber']) {
            $diffs[] = self::d($m, 'section', $a['sectionnumber'], $b['sectionnumber'], self::INFO, 'moved');
        }
        foreach (['visible', 'visibleoncoursepage'] as $f) {
            if ((int) $a[$f] !== (int) $b[$f]) {
                $diffs[] = self::d($m, $f, (int) $a[$f], (int) $b[$f], self::RELEVANT, 'visibility_changed');
            }
        }
        foreach (['completion', 'completionview', 'completiongradeitemnumber', 'completionpassgrade'] as $f) {
            if ($a[$f] !== $b[$f]) {
                $diffs[] = self::d($m, $f, $a[$f], $b[$f], self::RELEVANT, 'completion_changed');
            }
        }
        if ((int) $a['completionexpected'] !== (int) $b['completionexpected']) {
            $diffs[] = self::d(
                $m,
                'completionexpected',
                helpers::d($a['completionexpected'] ?: null),
                helpers::d($b['completionexpected'] ?: null),
                self::EXPECTED,
                'date_shifted'
            );
        }

        // Instance present on one side only.
        $ma = !empty($a['extra']['missing_instance']);
        $mb = !empty($b['extra']['missing_instance']);
        if ($ma !== $mb) {
            $diffs[] = self::d($m, 'instance', $ma ? 0 : 1, $mb ? 0 : 1, self::RELEVANT, 'instance_missing');
        }
        $expected++; // The instance id.

        foreach ($a['extra'] as $k => $v) {
            if (in_array($k, self::IGNORED, true) || !array_key_exists($k, $b['extra'])) {
                continue;
            }
            $w = $b['extra'][$k];
            if (isset(self::REFERENCES[$k])) {
                [$d, $isexpected] = self::reference($k, $v, $w, $a, $b, $ctx);
                if ($d) {
                    $diffs[] = $d;
                }
                $expected += $isexpected ? 1 : 0;
                continue;
            }
            if ($v == $w) {
                continue;
            }
            if (in_array($k, self::DATE_FIELDS, true)) {
                $diffs[] = self::d(
                    $m,
                    "extra.$k",
                    helpers::d((int) $v ?: null),
                    helpers::d((int) $w ?: null),
                    self::EXPECTED,
                    'date_shifted'
                );
            } else if (in_array($k, self::CONTENT_COUNTS, true)) {
                $diffs[] = self::content_count($k, $v, $w, $b, $ctx);
            } else if (in_array($k, self::DISPLAY_FIELDS, true)) {
                $diffs[] = self::d($m, "extra.$k", $v, $w, self::INFO, 'display_changed');
            } else if (strpos($k, 'completion') === 0) {
                $diffs[] = self::d($m, "extra.$k", $v, $w, self::RELEVANT, 'completion_changed');
            } else {
                $diffs[] = self::d($m, "extra.$k", $v, $w, self::RELEVANT, 'setting_changed');
            }
        }

        array_push($diffs, ...self::availability($m, 'availability', $a['availability'], $b['availability'], $ctx));
        $expected += $a['availability'] && $a['availability_raw'] !== $b['availability_raw'] ? 1 : 0;
        array_push($diffs, ...self::grade_items($a, $b));
        $expected += count($a['grade_items']);
        array_push($diffs, ...self::files($a, $b, $ctx));
        array_push($diffs, ...self::texts($a, $b));
        return [$diffs, $expected];
    }

    /**
     * A setting that references another module of the course (game → glossary/quiz/book): expected when it points to
     * the counterpart of the original reference, relevant otherwise.
     *
     * @param string $k
     * @param mixed $v source value
     * @param mixed $w target value
     * @param array $a
     * @param array $b
     * @param array $ctx
     * @return array [difference or null, counted as expected]
     */
    protected static function reference(string $k, $v, $w, array $a, array $b, array $ctx): array {
        $type = self::REFERENCES[$k];
        $v = (int) $v;
        $w = (int) $w;
        if ($v === 0 && $w === 0) {
            return [null, false];
        }
        $mapped = $ctx['instances'][$type][$v] ?? null;
        if ($v !== 0 && $mapped !== null && $mapped === $w) {
            return [null, $v !== $w];
        }
        $srcref = $ctx['srcbyinstance'][$type][$v] ?? null;
        $name = $srcref ? $srcref['name'] : (string) $v;
        return [self::d(
            'module',
            "extra.$k",
            $v ?: null,
            $w ?: null,
            self::RELEVANT,
            'reference_unmapped',
            ['ref' => $name]
        ), false];
    }

    /**
     * A content count that changed: lost content is relevant (with the modules that depend on a glossary), added
     * content informative.
     *
     * @param string $k
     * @param mixed $v
     * @param mixed $w
     * @param array $b target module
     * @param array $ctx
     * @return array
     */
    protected static function content_count(string $k, $v, $w, array $b, array $ctx): array {
        if ((float) $w > (float) $v) {
            return self::d('module', "extra.$k", $v, $w, self::INFO, 'content_added');
        }
        $d = self::d('module', "extra.$k", $v, $w, self::RELEVANT, 'content_lost');
        if ($b['modname'] === 'glossary' && $k === 'entries') {
            $deps = [];
            foreach ($ctx['dst']['modules'] as $o) {
                if ((int) ($o['extra']['glossaryid'] ?? 0) === (int) $b['instanceid'] && $o['cmid'] !== $b['cmid']) {
                    $deps[] = ['cmid' => (int) $o['cmid'], 'modname' => $o['modname'], 'name' => (string) $o['name']];
                }
            }
            if ($deps) {
                $d['dependents'] = $deps;
                $d['explanation'] .= ' ' . get_string(
                    'cmp_dependents',
                    'local_mooc_audit',
                    implode(', ', array_map(fn($x) => "{$x['modname']} \"{$x['name']}\"", $deps))
                );
            }
        }
        return $d;
    }

    /**
     * Availability trees: source remapped through the match map (cm and grade item ids), dates masked; a difference
     * only in dates is expected, any other difference relevant; references without counterpart are reported by name.
     *
     * @param string $scope module|section
     * @param string $field
     * @param array|null $ta source tree
     * @param array|null $tb target tree
     * @param array $ctx
     * @return array differences
     */
    public static function availability(string $scope, string $field, ?array $ta, ?array $tb, array $ctx): array {
        if (isset($ta['_error']) || isset($tb['_error'])) {
            return isset($ta['_error']) && isset($tb['_error']) ? [] : [self::d($scope, $field, isset($ta['_error']) ? 'JSON'
                : null, isset($tb['_error']) ? 'JSON' : null, self::RELEVANT, 'availability_invalid')];
        }
        $unmapped = [];
        $remapped = self::remap($ta, $ctx, $unmapped);
        $out = [];
        if ($unmapped) {
            $out[] = self::d($scope, $field, implode(', ', $unmapped), null, self::RELEVANT, 'availability_unmapped');
        }
        $na = self::normalise($remapped, true);
        $nb = self::normalise(self::empty_tree($tb), true);
        if (json_encode($na) !== json_encode($nb)) {
            if (!$unmapped) {
                $out[] = self::d(
                    $scope,
                    $field,
                    self::describe($ta, $ctx['src']),
                    self::describe($tb, $ctx['dst']),
                    self::RELEVANT,
                    'availability_changed'
                );
            }
        } else if (json_encode(self::normalise($remapped, false)) !== json_encode(self::normalise(self::empty_tree($tb), false))) {
            $out[] = self::d(
                $scope,
                $field,
                self::describe($ta, $ctx['src']),
                self::describe($tb, $ctx['dst']),
                self::EXPECTED,
                'date_shifted'
            );
        }
        return $out;
    }

    /**
     * Null for a tree without conditions (Moodle stores both null and an empty AND tree).
     *
     * @param array|null $tree
     * @return array|null
     */
    protected static function empty_tree(?array $tree): ?array {
        return $tree && !empty($tree['c']) ? $tree : null;
    }

    /**
     * Remap cm ids and grade item ids of a source availability tree to the target ids.
     *
     * @param array|null $tree
     * @param array $ctx
     * @param array $unmapped by reference: names of referenced modules without counterpart
     * @return array|null
     */
    protected static function remap(?array $tree, array $ctx, array &$unmapped): ?array {
        $tree = self::empty_tree($tree);
        if (!$tree) {
            return null;
        }
        $walk = function (array $node) use (&$walk, $ctx, &$unmapped) {
            if (isset($node['c']) && is_array($node['c'])) {
                $node['c'] = array_map($walk, $node['c']);
                return $node;
            }
            $type = $node['type'] ?? '';
            if ($type === 'completion' && isset($node['cm'])) {
                $cm = (int) $node['cm'];
                if (isset($ctx['map'][$cm])) {
                    $node['cm'] = $ctx['map'][$cm];
                } else if ($cm > 0) {
                    $name = $ctx['src']['modules'][$cm]['name'] ?? "cmid $cm";
                    $unmapped[] = $name;
                }
            }
            if ($type === 'grade' && isset($node['id'])) {
                $id = (int) $node['id'];
                if (isset($ctx['grades'][$id])) {
                    $node['id'] = $ctx['grades'][$id];
                } else {
                    $unmapped[] = $ctx['src']['grade']['all_items'][$id]['itemname'] ?? "grade item $id";
                }
            }
            return $node;
        };
        return $walk($tree);
    }

    /**
     * Normalise a tree: numeric strings to numbers, object keys sorted, optionally date values masked.
     *
     * @param mixed $tree
     * @param bool $maskdates
     * @return mixed
     */
    protected static function normalise($tree, bool $maskdates) {
        if (is_array($tree)) {
            $isdate = ($tree['type'] ?? null) === 'date';
            $out = [];
            foreach ($tree as $k => $v) {
                $out[$k] = ($maskdates && $isdate && $k === 't') ? 'T' : self::normalise($v, $maskdates);
            }
            if (array_keys($out) !== range(0, count($out) - 1)) {
                ksort($out);
            }
            return $out;
        }
        return is_string($tree) && is_numeric($tree) ? $tree + 0 : $tree;
    }

    /**
     * Readable summary of a tree's leaf conditions (for the evidence).
     *
     * @param array|null $tree
     * @param array $s snapshot owning the ids
     * @return string|null
     */
    protected static function describe(?array $tree, array $s): ?string {
        $tree = self::empty_tree($tree);
        if (!$tree) {
            return null;
        }
        $parts = [];
        foreach (\local_mooc_audit\snapshot::walk_conditions($tree) as $c) {
            $type = (string) ($c['type'] ?? '?');
            if ($type === 'completion') {
                $name = $s['modules'][(int) ($c['cm'] ?? 0)]['name'] ?? '?';
                $parts[] = get_string('cmp_cond_completion', 'local_mooc_audit', $name);
            } else if ($type === 'grade') {
                $id = (int) ($c['id'] ?? 0);
                $name = $id === (int) ($s['grade']['course_item']['id'] ?? -1) ? get_string('cmp_course_total', 'local_mooc_audit')
                    : ($s['grade']['all_items'][$id]['itemname'] ?? '?');
                $parts[] = get_string('cmp_cond_grade', 'local_mooc_audit', (object) ['name' => $name,
                    'min' => isset($c['min']) ? ' ≥ ' . $c['min'] : '']);
            } else if ($type === 'date') {
                $parts[] = get_string('cmp_cond_date', 'local_mooc_audit', (object) ['d' => $c['d'] ?? '',
                    't' => helpers::d((int) ($c['t'] ?? 0))]);
            } else {
                $parts[] = $type;
            }
        }
        return ($tree['op'] ?? '&') . ': ' . implode('; ', $parts);
    }

    /**
     * Grade items by item number.
     *
     * @param array $a
     * @param array $b
     * @return array
     */
    protected static function grade_items(array $a, array $b): array {
        $ia = [];
        $ib = [];
        foreach ($a['grade_items'] as $g) {
            $ia[(int) $g['itemnumber']] = $g;
        }
        foreach ($b['grade_items'] as $g) {
            $ib[(int) $g['itemnumber']] = $g;
        }
        $out = [];
        foreach ($ia as $n => $g) {
            if (!isset($ib[$n])) {
                $out[] = self::d('module', "grade_items[$n]", $g['itemname'], null, self::RELEVANT, 'grade_item_changed');
                continue;
            }
            foreach (['gradetype', 'grademax', 'gradepass', 'hidden'] as $f) {
                if ((float) $g[$f] !== (float) $ib[$n][$f]) {
                    $out[] = self::d('module', "grade_items[$n].$f", $g[$f], $ib[$n][$f], self::RELEVANT, 'grade_item_changed');
                }
            }
        }
        foreach ($ib as $n => $g) {
            if (!isset($ia[$n])) {
                $out[] = self::d('module', "grade_items[$n]", null, $g['itemname'], self::RELEVANT, 'grade_item_changed');
            }
        }
        return $out;
    }

    /**
     * Files: record missing in the target, content missing from the file store, module with files not available to
     * students in the target (hidden module or hidden section), files added.
     *
     * @param array $a
     * @param array $b
     * @param array $ctx
     * @return array
     */
    protected static function files(array $a, array $b, array $ctx): array {
        $key = fn($f) => "{$f['filearea']}:{$f['filepath']}{$f['filename']}:{$f['filesize']}";
        $tb = [];
        foreach ($b['files']['list'] as $f) {
            $tb[$key($f)][] = $f;
        }
        $out = [];
        foreach ($a['files']['list'] as $f) {
            $k = $key($f);
            $label = $f['filepath'] . $f['filename'];
            if (empty($tb[$k])) {
                $out[] = self::d('module', 'files', $label, null, self::RELEVANT, 'file_record_missing', ['file' => $label]);
                continue;
            }
            $t = array_shift($tb[$k]);
            if ($t['readable'] === false && $f['readable'] !== false) {
                $out[] = self::d('module', 'files', $label, $label, self::RELEVANT, 'file_content_missing', ['file' => $label]);
            }
        }
        foreach ($tb as $rest) {
            foreach ($rest as $t) {
                $label = $t['filepath'] . $t['filename'];
                $out[] = self::d('module', 'files', null, $label, self::INFO, 'file_added', ['file' => $label]);
            }
        }
        $svis = (int) ($ctx['src']['sections'][$a['sectionid']]['visible'] ?? 1);
        $tvis = (int) ($ctx['dst']['sections'][$b['sectionid']]['visible'] ?? 1);
        if ($b['files']['list'] && ($a['visible'] && $svis) && (!$b['visible'] || !$tvis)) {
            $out[] = self::d(
                'module',
                'files',
                get_string('cmp_available', 'local_mooc_audit'),
                get_string('cmp_unavailable', 'local_mooc_audit'),
                self::RELEVANT,
                'file_unavailable'
            );
        }
        return $out;
    }

    /**
     * Texts compared by signature: emptied text is relevant, changed text informative.
     *
     * @param array $a
     * @param array $b
     * @return array
     */
    protected static function texts(array $a, array $b): array {
        $out = [];
        $keys = array_unique(array_merge(array_keys($a['texts']), array_keys($b['texts'])));
        sort($keys);
        foreach ($keys as $k) {
            $sa = matcher::text_signature($a['texts'][$k] ?? '');
            $sb = matcher::text_signature($b['texts'][$k] ?? '');
            if ($sa === $sb) {
                continue;
            }
            $ta = \core_text::substr(helpers::text($a['texts'][$k] ?? ''), 0, 80);
            $tb = \core_text::substr(helpers::text($b['texts'][$k] ?? ''), 0, 80);
            if ($sb === '' && $sa !== '') {
                $out[] = self::d('module', "texts.$k", $ta, '', self::RELEVANT, 'text_emptied');
            } else {
                $out[] = self::d('module', "texts.$k", $ta, $tb, self::INFO, 'text_changed');
            }
        }
        return $out;
    }

    /**
     * Classification of a target module without counterpart: graded or tracked by completion is relevant (it may
     * change the course total or the completion chain), anything else informative.
     *
     * @param array $m
     * @return array [kind, code]
     */
    public static function extra_kind(array $m): array {
        $graded = (bool) array_filter($m['grade_items'], fn($g) => (int) $g['gradetype'] > 0);
        return $graded || $m['completion'] ? [self::RELEVANT, 'extra_graded'] : [self::INFO, 'extra_resource'];
    }

    /**
     * Course level differences: settings, sections, enrolment methods, blocks and the grade book root.
     *
     * @param array $src
     * @param array $dst
     * @param array $ctx
     * @return array [differences, expected id count]
     */
    public static function course(array $src, array $dst, array $ctx): array {
        $a = $src['course'];
        $b = $dst['course'];
        $out = [];
        $ids = 0;
        foreach (self::COURSE_IDS as $f) {
            $ids += ($a[$f] ?? null) != ($b[$f] ?? null) ? 1 : 0;
        }
        foreach (self::OFFER_COURSE_FIELDS as $f) {
            if ($a[$f] != $b[$f]) {
                $show = in_array($f, ['startdate', 'enddate'], true) ? fn($t) => helpers::d((int) $t ?: null) : fn($v) => $v;
                $out[] = self::d('course', $f, $show($a[$f]), $show($b[$f]), self::EXPECTED, 'offer_setting');
            }
        }
        if (($a['category']['name'] ?? null) !== ($b['category']['name'] ?? null)) {
            $out[] = self::d(
                'course',
                'category',
                $a['category']['name'] ?? null,
                $b['category']['name'] ?? null,
                self::EXPECTED,
                'offer_setting'
            );
        }
        foreach (self::COURSE_SETTINGS as $f) {
            if ($a[$f] != $b[$f]) {
                $out[] = self::d('course', $f, $a[$f], $b[$f], self::RELEVANT, 'course_setting_changed');
            }
        }
        $fa = $a['formatoptions'] ?? [];
        $fb = $b['formatoptions'] ?? [];
        foreach (array_unique(array_merge(array_keys($fa), array_keys($fb))) as $f) {
            if (($fa[$f] ?? null) != ($fb[$f] ?? null)) {
                $out[] = self::d(
                    'course',
                    "formatoptions.$f",
                    $fa[$f] ?? null,
                    $fb[$f] ?? null,
                    self::RELEVANT,
                    'course_setting_changed'
                );
            }
        }
        if (matcher::text_signature($a['summary']) !== matcher::text_signature($b['summary'])) {
            $out[] = self::d(
                'course',
                'summary',
                \core_text::substr(helpers::text($a['summary']), 0, 80),
                \core_text::substr(helpers::text($b['summary']), 0, 80),
                self::INFO,
                'text_changed'
            );
        }
        array_push($out, ...self::sections($src, $dst, $ctx));
        array_push($out, ...self::enrols($src['enrols'], $dst['enrols']));
        array_push($out, ...self::blocks($src['blocks'], $dst['blocks']));
        array_push($out, ...self::grade_root($src, $dst));
        return [$out, $ids];
    }

    /**
     * Sections matched by number.
     *
     * @param array $src
     * @param array $dst
     * @param array $ctx
     * @return array
     */
    protected static function sections(array $src, array $dst, array $ctx): array {
        $bynum = fn($s) => array_column($s['sections'], null, 'number');
        $sa = $bynum($src);
        $sb = $bynum($dst);
        $out = [];
        if (count($sa) !== count($sb)) {
            $out[] = self::d('section', 'sections', count($sa), count($sb), self::INFO, 'sections_reorganized');
        }
        foreach ($sa as $n => $x) {
            if (!isset($sb[$n])) {
                continue;
            }
            $y = $sb[$n];
            if (helpers::norm($x['name']) !== helpers::norm($y['name'])) {
                $out[] = self::d('section', "section[$n].name", $x['name'], $y['name'], self::INFO, 'section_renamed');
            }
            if ((int) $x['visible'] !== (int) $y['visible']) {
                $out[] = self::d(
                    'section',
                    "section[$n].visible",
                    (int) $x['visible'],
                    (int) $y['visible'],
                    self::RELEVANT,
                    'section_visibility_changed'
                );
            }
            array_push($out, ...self::availability(
                'section',
                "section[$n].availability",
                $x['availability'],
                $y['availability'],
                $ctx
            ));
        }
        return $out;
    }

    /**
     * Enrolment methods by plugin name (first instance of each, plus the number of instances). Enrolment dates, and the
     * status and "new enrolments" of self enrolment, are offer settings (checked by CL-08 in the target); the role, the
     * status of any other method, a missing method or fewer instances are relevant. Passwords only as presence.
     *
     * @param array $ea
     * @param array $eb
     * @return array
     */
    protected static function enrols(array $ea, array $eb): array {
        $first = function (array $list) {
            $out = [];
            foreach ($list as $e) {
                $out[$e['enrol']] = $out[$e['enrol']] ?? $e;
            }
            return $out;
        };
        $a = $first($ea);
        $b = $first($eb);
        $counts = fn(array $list) => array_count_values(array_column($list, 'enrol'));
        $ca = $counts($ea);
        $cb = $counts($eb);
        $out = [];
        foreach ($a as $name => $e) {
            if (!isset($b[$name])) {
                $out[] = self::d('enrol', $name, $name, null, self::RELEVANT, 'enrol_missing');
                continue;
            }
            if ($cb[$name] < $ca[$name]) {
                $out[] = self::d('enrol', "$name.instances", $ca[$name], $cb[$name], self::RELEVANT, 'enrol_count_changed');
            }
            foreach (['status', 'customint6', 'roleid'] as $f) {
                if ($e[$f] != $b[$name][$f]) {
                    $offer = $name === 'self' && $f !== 'roleid';
                    $out[] = self::d(
                        'enrol',
                        "$name.$f",
                        $e[$f],
                        $b[$name][$f],
                        $offer ? self::EXPECTED : self::RELEVANT,
                        $offer ? 'offer_setting' : 'course_setting_changed'
                    );
                }
            }
            foreach (['enrolstartdate', 'enrolenddate'] as $f) {
                if ($e[$f] != $b[$name][$f]) {
                    $out[] = self::d(
                        'enrol',
                        "$name.$f",
                        helpers::d($e[$f] ?: null),
                        helpers::d($b[$name][$f] ?: null),
                        self::EXPECTED,
                        'offer_setting'
                    );
                }
            }
            if ($e['has_password'] !== $b[$name]['has_password']) {
                $out[] = self::d(
                    'enrol',
                    "$name.has_password",
                    $e['has_password'],
                    $b[$name]['has_password'],
                    self::RELEVANT,
                    'course_setting_changed'
                );
            }
        }
        foreach ($b as $name => $e) {
            if (!isset($a[$name])) {
                $out[] = self::d('enrol', $name, null, $name, self::INFO, 'enrol_added');
            }
        }
        return $out;
    }

    /**
     * Blocks on the course page by name and normalised title.
     *
     * @param array $ba
     * @param array $bb
     * @return array
     */
    protected static function blocks(array $ba, array $bb): array {
        $key = fn($b) => $b['name'] . ':' . helpers::norm((string) ($b['title'] ?? ''));
        $label = fn($b) => $b['name'] . ($b['title'] ? ' "' . $b['title'] . '"' : '');
        $tb = [];
        foreach ($bb as $b) {
            $tb[$key($b)][] = $b;
        }
        $out = [];
        foreach ($ba as $b) {
            if (!empty($tb[$key($b)])) {
                array_shift($tb[$key($b)]);
                continue;
            }
            $out[] = self::d('block', $b['name'], $label($b), null, self::RELEVANT, 'block_missing');
        }
        foreach ($tb as $rest) {
            foreach ($rest as $b) {
                $out[] = self::d('block', $b['name'], null, $label($b), self::INFO, 'block_added');
            }
        }
        return $out;
    }

    /**
     * Aggregation of the grade book root category and course total.
     *
     * @param array $src
     * @param array $dst
     * @return array
     */
    protected static function grade_root(array $src, array $dst): array {
        $out = [];
        $ra = $src['grade']['categories'][$src['grade']['course_item']['iteminstance'] ?? 0] ?? null;
        $rb = $dst['grade']['categories'][$dst['grade']['course_item']['iteminstance'] ?? 0] ?? null;
        if ($ra && $rb) {
            foreach (['aggregation', 'aggregateonlygraded'] as $f) {
                if ((int) $ra[$f] !== (int) $rb[$f]) {
                    $out[] = self::d('grade', $f, (int) $ra[$f], (int) $rb[$f], self::RELEVANT, 'grade_aggregation_changed');
                }
            }
        }
        $ca = $src['grade']['course_item'];
        $cb = $dst['grade']['course_item'];
        if ($ca && $cb && (float) $ca['grademax'] !== (float) $cb['grademax']) {
            $out[] = self::d(
                'grade',
                'course_total.grademax',
                $ca['grademax'],
                $cb['grademax'],
                self::RELEVANT,
                'grade_item_changed'
            );
        }
        return $out;
    }
}
