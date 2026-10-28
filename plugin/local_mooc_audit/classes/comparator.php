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

defined('MOODLE_INTERNAL') || die();

/**
 * Experimental comparator between a source course and an imported target course.
 *
 * Modules are matched by stable attributes, never by cmid: (modname, section number, normalised name), then
 * (modname, normalised name), then (modname, section number, position within the section). Ambiguous matches
 * (several candidates) are reported explicitly.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparator {

    /** @var array Course settings expected to change in a reoffer (reported as "expected differences"). */
    const EXPECTED_COURSE_DIFFS = ['id', 'contextid', 'shortname', 'fullname', 'startdate', 'enddate', 'timecreated', 'timemodified', 'category'];

    /** @var array Module attributes compared once two modules are matched. */
    const MODULE_FIELDS = ['visible', 'visibleoncoursepage', 'completion', 'completionview', 'completiongradeitemnumber', 'completionpassgrade', 'sectionnumber'];

    /**
     * Compare two snapshots.
     *
     * @param array $src
     * @param array $dst
     * @return array
     */
    public static function compare(array $src, array $dst): array {
        $report = ['source' => $src['course']['id'], 'target' => $dst['course']['id'], 'course_diffs' => [], 'expected_course_diffs' => [],
            'matched' => [], 'missing_in_target' => [], 'extra_in_target' => [], 'ambiguous' => [], 'modified' => [], 'preserved' => 0, 'notes' => []];

        foreach ($src['course'] as $k => $v) {
            if (in_array($k, ['summary', 'formatoptions'])) {
                $v = is_array($v) ? json_encode($v) : trim((string) $v);
                $w = is_array($dst['course'][$k] ?? null) ? json_encode($dst['course'][$k]) : trim((string) ($dst['course'][$k] ?? ''));
                if ($v !== $w) {
                    $report['course_diffs'][] = ['field' => $k, 'source' => mb_substr($v, 0, 120), 'target' => mb_substr($w, 0, 120)];
                }
                continue;
            }
            if (($dst['course'][$k] ?? null) != $v) {
                $entry = ['field' => $k, 'source' => $v, 'target' => $dst['course'][$k] ?? null];
                if (in_array($k, self::EXPECTED_COURSE_DIFFS)) {
                    $report['expected_course_diffs'][] = $entry;
                } else {
                    $report['course_diffs'][] = $entry;
                }
            }
        }

        // Index target modules by matching keys.
        $byfull = [];
        $byname = [];
        $bypos = [];
        foreach ($dst['modules'] as $m) {
            $byfull[self::key_full($m)][] = $m['cmid'];
            $byname[self::key_name($m)][] = $m['cmid'];
            $bypos[self::key_pos($dst, $m)][] = $m['cmid'];
        }
        $used = [];
        $map = []; // Source cmid => target cmid.
        foreach ($src['modules'] as $m) {
            $cands = array_values(array_diff($byfull[self::key_full($m)] ?? [], $used));
            $how = 'tipo+seção+nome';
            if (count($cands) !== 1) {
                $cands2 = array_values(array_diff($byname[self::key_name($m)] ?? [], $used));
                if (count($cands2) === 1) {
                    $cands = $cands2;
                    $how = 'tipo+nome';
                } else if (count($cands) === 0 && count($cands2) === 0) {
                    $cands3 = array_values(array_diff($bypos[self::key_pos($src, $m)] ?? [], $used));
                    if (count($cands3) === 1 && $dst['modules'][$cands3[0]]['modname'] === $m['modname']) {
                        $cands = $cands3;
                        $how = 'tipo+seção+posição';
                    }
                } else if (count($cands2) > 1) {
                    $cands = $cands2;
                }
            }
            if (count($cands) === 1) {
                $used[] = $cands[0];
                $map[$m['cmid']] = $cands[0];
                $report['matched'][] = ['source_cmid' => $m['cmid'], 'target_cmid' => $cands[0], 'modname' => $m['modname'], 'name' => $m['name'], 'how' => $how];
            } else if (count($cands) > 1) {
                $report['ambiguous'][] = ['source_cmid' => $m['cmid'], 'modname' => $m['modname'], 'name' => $m['name'], 'candidates' => $cands];
            } else {
                $report['missing_in_target'][] = ['source_cmid' => $m['cmid'], 'modname' => $m['modname'], 'name' => $m['name'], 'section' => $m['sectionnumber']];
            }
        }
        foreach ($dst['modules'] as $m) {
            if (!in_array($m['cmid'], $used)) {
                $report['extra_in_target'][] = ['target_cmid' => $m['cmid'], 'modname' => $m['modname'], 'name' => $m['name'], 'section' => $m['sectionnumber']];
            }
        }

        // Compare matched pairs.
        foreach ($map as $scm => $tcm) {
            $a = $src['modules'][$scm];
            $b = $dst['modules'][$tcm];
            $diffs = [];
            foreach (self::MODULE_FIELDS as $f) {
                if ($a[$f] != $b[$f]) {
                    $diffs[] = ['field' => $f, 'source' => $a[$f], 'target' => $b[$f]];
                }
            }
            foreach ($a['extra'] as $k => $v) {
                if (in_array($k, ['missing_instance', 'glossary_entries_available', 'glossary_category_entries', 'main_library_id'])) {
                    continue;
                }
                if (array_key_exists($k, $b['extra']) && $b['extra'][$k] != $v) {
                    $diffs[] = ['field' => "extra.$k", 'source' => $v, 'target' => $b['extra'][$k]];
                }
            }
            // Availability: remap source cmids/grade item ids through the match map before comparing.
            $remapped = self::remap_availability($a['availability'], $map, $src, $dst);
            if (json_encode(self::normalise_tree($remapped)) !== json_encode(self::normalise_tree($b['availability']))) {
                $diffs[] = ['field' => 'availability', 'source' => $a['availability_raw'], 'target' => $b['availability_raw'], 'source_remapped' => json_encode($remapped)];
            }
            $ga = array_map(fn($g) => [$g['itemnumber'], $g['gradetype'], $g['grademax']], $a['grade_items']);
            $gb = array_map(fn($g) => [$g['itemnumber'], $g['gradetype'], $g['grademax']], $b['grade_items']);
            if ($ga != $gb) {
                $diffs[] = ['field' => 'grade_items', 'source' => $ga, 'target' => $gb];
            }
            $fa = array_map(fn($f) => $f['filearea'] . ':' . $f['filepath'] . $f['filename'] . ':' . $f['filesize'], $a['files']['list']);
            $fb = array_map(fn($f) => $f['filearea'] . ':' . $f['filepath'] . $f['filename'] . ':' . $f['filesize'], $b['files']['list']);
            sort($fa);
            sort($fb);
            if ($fa != $fb) {
                $diffs[] = ['field' => 'files', 'source' => $fa, 'target' => $fb];
            }
            $ta = array_map(fn($t) => md5(trim((string) $t)), $a['texts']);
            $tb = array_map(fn($t) => md5(trim((string) $t)), $b['texts']);
            foreach ($ta as $k => $h) {
                if (($tb[$k] ?? null) !== $h) {
                    $diffs[] = ['field' => "texts.$k", 'source' => mb_substr(\local_mooc_audit\local\helpers::text($a['texts'][$k]), 0, 80), 'target' => mb_substr(\local_mooc_audit\local\helpers::text($b['texts'][$k] ?? ''), 0, 80)];
                }
            }
            if ($diffs) {
                $report['modified'][] = ['source_cmid' => $scm, 'target_cmid' => $tcm, 'modname' => $a['modname'], 'name' => $a['name'], 'diffs' => $diffs,
                    'problematic' => (bool) array_filter($diffs, fn($d) => !in_array($d['field'], ['texts.intro', 'texts.content']) || true)];
            } else {
                $report['preserved']++;
            }
        }

        // Enrolment, blocks, grade settings.
        $ea = array_map(fn($e) => "{$e['enrol']}:status={$e['status']}:start={$e['enrolstartdate']}:end={$e['enrolenddate']}:new={$e['customint6']}", $src['enrols']);
        $eb = array_map(fn($e) => "{$e['enrol']}:status={$e['status']}:start={$e['enrolstartdate']}:end={$e['enrolenddate']}:new={$e['customint6']}", $dst['enrols']);
        sort($ea);
        sort($eb);
        if ($ea != $eb) {
            $report['course_diffs'][] = ['field' => 'enrols', 'source' => $ea, 'target' => $eb];
        }
        $ba = array_map(fn($b) => $b['name'] . ':' . ($b['title'] ?? ''), $src['blocks']);
        $bb = array_map(fn($b) => $b['name'] . ':' . ($b['title'] ?? ''), $dst['blocks']);
        sort($ba);
        sort($bb);
        if ($ba != $bb) {
            $report['course_diffs'][] = ['field' => 'blocks', 'source' => $ba, 'target' => $bb];
        }
        $ra = $src['grade']['categories'][$src['grade']['course_item']['iteminstance'] ?? 0] ?? null;
        $rb = $dst['grade']['categories'][$dst['grade']['course_item']['iteminstance'] ?? 0] ?? null;
        if ($ra && $rb && ($ra['aggregation'] != $rb['aggregation'] || $ra['aggregateonlygraded'] != $rb['aggregateonlygraded'])) {
            $report['course_diffs'][] = ['field' => 'grade_root_category', 'source' => [$ra['aggregation'], $ra['aggregateonlygraded']], 'target' => [$rb['aggregation'], $rb['aggregateonlygraded']]];
        }
        $report['summary'] = ['source_modules' => count($src['modules']), 'target_modules' => count($dst['modules']), 'matched' => count($map),
            'preserved' => $report['preserved'], 'modified' => count($report['modified']), 'missing_in_target' => count($report['missing_in_target']),
            'extra_in_target' => count($report['extra_in_target']), 'ambiguous' => count($report['ambiguous'])];
        return $report;
    }

    /**
     * Matching keys.
     *
     * @param array $m
     * @return string
     */
    protected static function key_full(array $m): string {
        return $m['modname'] . '|' . $m['sectionnumber'] . '|' . \local_mooc_audit\local\helpers::norm($m['name']);
    }

    /**
     * Type + name key.
     *
     * @param array $m
     * @return string
     */
    protected static function key_name(array $m): string {
        return $m['modname'] . '|' . \local_mooc_audit\local\helpers::norm($m['name']);
    }

    /**
     * Type + section + position key.
     *
     * @param array $s
     * @param array $m
     * @return string
     */
    protected static function key_pos(array $s, array $m): string {
        $seq = $s['sections'][$m['sectionid']]['sequence'] ?? [];
        $pos = array_search($m['cmid'], $seq);
        return $m['modname'] . '|' . $m['sectionnumber'] . '|' . ($pos === false ? '?' : $pos);
    }

    /**
     * Remap cm ids (through the match map) and grade item ids (course total) in an availability tree.
     *
     * @param array|null $tree
     * @param array $map
     * @param array $src
     * @param array $dst
     * @return array|null
     */
    protected static function remap_availability(?array $tree, array $map, array $src, array $dst): ?array {
        if (!$tree) {
            return $tree;
        }
        $walk = function(array $node) use (&$walk, $map, $src, $dst) {
            if (isset($node['c'])) {
                $node['c'] = array_map($walk, $node['c']);
                return $node;
            }
            if (($node['type'] ?? '') === 'completion' && isset($map[(int) $node['cm']])) {
                $node['cm'] = $map[(int) $node['cm']];
            }
            if (($node['type'] ?? '') === 'grade') {
                $sid = (int) ($node['id'] ?? 0);
                if (($src['grade']['course_item']['id'] ?? null) === $sid && $dst['grade']['course_item']) {
                    $node['id'] = $dst['grade']['course_item']['id'];
                } else {
                    // Module grade item: find the matched module owning it.
                    foreach ($src['modules'] as $scm => $m) {
                        foreach ($m['grade_items'] as $gi) {
                            if ($gi['id'] === $sid && isset($map[$scm])) {
                                foreach ($dst['modules'][$map[$scm]]['grade_items'] as $tgi) {
                                    if ($tgi['itemnumber'] === $gi['itemnumber']) {
                                        $node['id'] = $tgi['id'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $node;
        };
        return $walk($tree);
    }

    /**
     * Normalise numeric strings so "9" and 9 compare equal.
     *
     * @param mixed $tree
     * @return mixed
     */
    protected static function normalise_tree($tree) {
        if (is_array($tree)) {
            $out = [];
            foreach ($tree as $k => $v) {
                $out[$k] = self::normalise_tree($v);
            }
            if (array_keys($out) !== range(0, count($out) - 1)) {
                ksort($out); // Key order is irrelevant in availability JSON ("showc" may precede "c").
            }
            return $out;
        }
        return is_string($tree) && is_numeric($tree) ? $tree + 0 : $tree;
    }

    /**
     * Plain-text rendering.
     *
     * @param array $r
     * @return string
     */
    public static function render_text(array $r): string {
        $l = ["=== Comparação curso {$r['source']} (origem) → {$r['target']} (destino)", 'Resumo: ' . json_encode($r['summary'])];
        foreach ($r['expected_course_diffs'] as $d) {
            $l[] = '  [esperado]   ' . $d['field'] . ': ' . json_encode($d['source'], JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($d['target'], JSON_UNESCAPED_UNICODE);
        }
        foreach ($r['course_diffs'] as $d) {
            $l[] = '  [curso]      ' . $d['field'] . ': ' . json_encode($d['source'], JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($d['target'], JSON_UNESCAPED_UNICODE);
        }
        foreach ($r['missing_in_target'] as $m) {
            $l[] = "  [AUSENTE]    {$m['modname']} \"{$m['name']}\" (origem cmid {$m['source_cmid']}, seção {$m['section']})";
        }
        foreach ($r['extra_in_target'] as $m) {
            $l[] = "  [ADICIONAL]  {$m['modname']} \"{$m['name']}\" (destino cmid {$m['target_cmid']}, seção {$m['section']})";
        }
        foreach ($r['ambiguous'] as $m) {
            $l[] = "  [AMBÍGUO]    {$m['modname']} \"{$m['name']}\" (origem cmid {$m['source_cmid']}) candidatos " . json_encode($m['candidates']);
        }
        foreach ($r['modified'] as $m) {
            $l[] = "  [MODIFICADO] {$m['modname']} \"{$m['name']}\" ({$m['source_cmid']} → {$m['target_cmid']})";
            foreach ($m['diffs'] as $d) {
                $l[] = '       ' . $d['field'] . ': ' . mb_substr(json_encode($d['source'], JSON_UNESCAPED_UNICODE), 0, 160) . ' → ' . mb_substr(json_encode($d['target'], JSON_UNESCAPED_UNICODE), 0, 160);
            }
        }
        $hows = [];
        foreach ($r['matched'] as $m) {
            $hows[$m['how']] = ($hows[$m['how']] ?? 0) + 1;
        }
        $l[] = '  correspondências por estratégia: ' . json_encode($hows, JSON_UNESCAPED_UNICODE) . "; preservados sem diferença: {$r['preserved']}";
        return implode("\n", $l) . "\n";
    }
}
