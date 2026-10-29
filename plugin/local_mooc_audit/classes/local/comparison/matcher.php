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
 * Matches the modules of a source course with the modules of a target course without using any internal id.
 *
 * Deterministic passes over the modules still unmatched, each accepting a pair only when its key is unique on BOTH
 * sides (specs/003 research R2):
 *   1. type + section + normalised name                      (confidence alta)
 *   2. inside a group of 1: + content fingerprint             (alta)  — versions with the same name (Libras)
 *   3. type + normalised name                                 (alta)  — activity moved to another section
 *   -  remaining type + name groups with modules on both sides are frozen as ambiguities
 *   4. type + content fingerprint (non trivial)               (media) — renamed activity
 *   5. type + section + position in the section               (media) — renamed and edited
 * Manual pairs (validated) are applied before pass 1. Decisions depend only on key counts, so the order of the modules
 * never changes the result.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matcher {
    /** @var string Confidence of passes 1-3. */
    const HIGH = 'alta';
    /** @var string Confidence of passes 4-5. */
    const MEDIUM = 'media';
    /** @var string Pair chosen by the user. */
    const MANUAL = 'manual';

    /** @var string[] Module settings that describe content (part of the content fingerprint). */
    const CONTENT_FIELDS = ['externalurl', 'slots', 'items', 'chapters', 'machine_name', 'gamekind', 'sourcemodule'];

    /** @var string[] Settings left out of the settings fingerprint (ids, dates, derived counts). */
    const NOT_SETTINGS = ['glossaryid', 'quizid', 'bookid', 'glossarycategoryid', 'main_library_id', 'missing_instance',
        'timeopen', 'timeclose', 'duedate', 'cutoffdate', 'allowsubmissionsfromdate', 'timeavailableto',
        'glossary_entries_available', 'glossary_category_entries'];

    /**
     * Match two snapshots.
     *
     * @param array $src source snapshot
     * @param array $dst target snapshot
     * @param array $manual list of [source cmid, target cmid] chosen by the user
     * @return array pairs (source cmid => [target, strategy, confidence]), ambiguities, missing, extra, manual (validation)
     */
    public static function match(array $src, array $dst, array $manual = []): array {
        $us = array_fill_keys(array_keys($src['modules']), true);
        $ut = array_fill_keys(array_keys($dst['modules']), true);
        $pairs = [];
        $manualout = [];

        foreach ($manual as [$scm, $tcm]) {
            $reason = null;
            if (!isset($src['modules'][$scm])) {
                $reason = 'source_not_found';
            } else if (!isset($dst['modules'][$tcm])) {
                $reason = 'target_not_found';
            } else if ($src['modules'][$scm]['modname'] !== $dst['modules'][$tcm]['modname']) {
                $reason = 'different_type';
            } else if (!isset($us[$scm]) || !isset($ut[$tcm])) {
                $reason = 'duplicate';
            }
            $manualout[] = ['source_cmid' => (int) $scm, 'target_cmid' => (int) $tcm, 'accepted' => $reason === null,
                'reason' => $reason];
            if ($reason === null) {
                $pairs[$scm] = ['target' => $tcm, 'strategy' => 'manual', 'confidence' => self::MANUAL];
                unset($us[$scm], $ut[$tcm]);
            }
        }

        $full = fn(array $s, array $m) => self::key_full($m);
        $fullcontent = fn(array $s, array $m) => self::key_full($m) . '|' . (self::fingerprint($m) ?? '-');
        $name = fn(array $s, array $m) => self::key_name($m);
        $content = fn(array $s, array $m) => ($fp = self::fingerprint($m)) === null ? null : $m['modname'] . '|' . $fp;
        $position = fn(array $s, array $m) => $m['modname'] . '|' . $m['sectionnumber'] . '|' . self::position($s, $m);

        self::unique_pass($src, $dst, $us, $ut, $full, 'type_section_name', self::HIGH, $pairs);
        self::unique_pass($src, $dst, $us, $ut, $fullcontent, 'type_section_name_content', self::HIGH, $pairs);
        self::unique_pass($src, $dst, $us, $ut, $name, 'type_name', self::HIGH, $pairs);
        $ambiguities = self::freeze($src, $dst, $us, $ut);
        self::unique_pass($src, $dst, $us, $ut, $content, 'type_content', self::MEDIUM, $pairs);
        self::unique_pass($src, $dst, $us, $ut, $position, 'type_section_position', self::MEDIUM, $pairs);

        ksort($pairs);
        $missing = array_keys($us);
        $extra = array_keys($ut);
        sort($missing);
        sort($extra);
        return ['pairs' => $pairs, 'ambiguities' => $ambiguities, 'missing' => $missing, 'extra' => $extra,
            'manual' => $manualout];
    }

    /**
     * Accept every key that is unique among the unmatched modules of both sides.
     *
     * @param array $src
     * @param array $dst
     * @param array $us unmatched source cmids (by reference)
     * @param array $ut unmatched target cmids (by reference)
     * @param callable $key fn(snapshot, module): ?string (null = module not eligible for this pass)
     * @param string $strategy
     * @param string $confidence
     * @param array $pairs by reference
     */
    protected static function unique_pass(
        array $src,
        array $dst,
        array &$us,
        array &$ut,
        callable $key,
        string $strategy,
        string $confidence,
        array &$pairs
    ): void {
        $is = self::index($src, $us, $key);
        $it = self::index($dst, $ut, $key);
        foreach ($is as $k => $scms) {
            if (count($scms) === 1 && count($it[$k] ?? []) === 1) {
                $pairs[$scms[0]] = ['target' => $it[$k][0], 'strategy' => $strategy, 'confidence' => $confidence];
                unset($us[$scms[0]], $ut[$it[$k][0]]);
            }
        }
    }

    /**
     * Group unmatched cmids by key.
     *
     * @param array $s snapshot
     * @param array $unmatched cmid => true
     * @param callable $key
     * @return array key => cmid[]
     */
    protected static function index(array $s, array $unmatched, callable $key): array {
        $out = [];
        foreach (array_keys($unmatched) as $cmid) {
            $k = $key($s, $s['modules'][$cmid]);
            if ($k !== null) {
                $out[$k][] = $cmid;
            }
        }
        ksort($out);
        foreach ($out as &$cmids) {
            sort($cmids);
        }
        return $out;
    }

    /**
     * Freeze the remaining type + name groups that have modules on both sides: several plausible candidates, so no
     * weaker pass (content, position) may pick one of them.
     *
     * @param array $src
     * @param array $dst
     * @param array $us by reference
     * @param array $ut by reference
     * @return array ambiguities
     */
    protected static function freeze(array $src, array $dst, array &$us, array &$ut): array {
        $name = fn(array $s, array $m) => self::key_name($m);
        $is = self::index($src, $us, $name);
        $it = self::index($dst, $ut, $name);
        $out = [];
        foreach ($is as $k => $scms) {
            if (empty($it[$k])) {
                continue;
            }
            $tcms = $it[$k];
            $fps = [];
            $sets = [];
            foreach ($scms as $c) {
                $fps[self::fingerprint($src['modules'][$c]) ?? '-'] = true;
                $sets[self::settings_fingerprint($src['modules'][$c])] = true;
            }
            foreach ($tcms as $c) {
                $fps[self::fingerprint($dst['modules'][$c]) ?? '-'] = true;
                $sets[self::settings_fingerprint($dst['modules'][$c])] = true;
            }
            if (count($scms) === 1 && count($tcms) > 1) {
                $reason = 'duplicado_no_destino';
            } else if (count($scms) > 1 && count($tcms) === 1) {
                $reason = 'duplicado_na_origem';
            } else if (count($fps) === 1) {
                $reason = 'conteudo_equivalente';
            } else {
                $reason = 'mesmo_nome';
            }
            $m = $src['modules'][$scms[0]];
            $out[] = [
                'reason' => $reason,
                'modname' => $m['modname'],
                'name' => (string) $m['name'],
                'source' => array_map(fn($c) => self::ref($src, $c), $scms),
                'target' => array_map(fn($c) => self::ref($dst, $c), $tcms),
                'settings_identical' => count($sets) === 1,
            ];
            foreach ($scms as $c) {
                unset($us[$c]);
            }
            foreach ($tcms as $c) {
                unset($ut[$c]);
            }
        }
        return $out;
    }

    /**
     * Location of a module for the report.
     *
     * @param array $s
     * @param int $cmid
     * @return array
     */
    protected static function ref(array $s, int $cmid): array {
        $m = $s['modules'][$cmid];
        return ['cmid' => (int) $cmid, 'section' => (int) $m['sectionnumber'], 'position' => self::position($s, $m)];
    }

    /**
     * Type + section + normalised name.
     *
     * @param array $m
     * @return string
     */
    public static function key_full(array $m): string {
        return $m['modname'] . '|' . $m['sectionnumber'] . '|' . helpers::norm($m['name']);
    }

    /**
     * Type + normalised name.
     *
     * @param array $m
     * @return string
     */
    public static function key_name(array $m): string {
        return $m['modname'] . '|' . helpers::norm($m['name']);
    }

    /**
     * Position (0-based) of a module in its section sequence; -1 when not in the sequence.
     *
     * @param array $s
     * @param array $m
     * @return int
     */
    public static function position(array $s, array $m): int {
        $seq = $s['sections'][$m['sectionid']]['sequence'] ?? [];
        $pos = array_search($m['cmid'], $seq);
        return $pos === false ? -1 : (int) $pos;
    }

    /**
     * Comparable signature of an HTML text: visible text plus the file names it embeds or links (ids and query
     * strings removed, so that remapped links do not count as a change).
     *
     * @param string|null $html
     * @return string
     */
    public static function text_signature(?string $html): string {
        $html = (string) $html;
        $refs = [];
        if (preg_match_all('/(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', $html, $mm)) {
            foreach ($mm[1] as $url) {
                $refs[] = basename((string) parse_url(html_entity_decode($url), PHP_URL_PATH));
            }
        }
        sort($refs);
        return trim(helpers::norm(helpers::text($html)) . ($refs ? ' [' . implode(' ', $refs) . ']' : ''));
    }

    /**
     * Content fingerprint: type, texts, files and content settings, without configuration or ids. Null when the module
     * has no content at all (trivial fingerprint, never used to match).
     *
     * @param array $m
     * @return string|null
     */
    public static function fingerprint(array $m): ?string {
        $parts = [];
        $texts = $m['texts'] ?? [];
        ksort($texts);
        foreach ($texts as $k => $t) {
            $sig = self::text_signature($t);
            if ($sig !== '') {
                $parts[] = "t:$k=$sig";
            }
        }
        $files = array_map(fn($f) => "{$f['filearea']}:{$f['filepath']}{$f['filename']}:{$f['filesize']}",
            $m['files']['list'] ?? []);
        sort($files);
        foreach ($files as $f) {
            $parts[] = "f:$f";
        }
        foreach (self::CONTENT_FIELDS as $k) {
            if (isset($m['extra'][$k]) && $m['extra'][$k] !== '' && $m['extra'][$k] !== 0) {
                $parts[] = "c:$k=" . $m['extra'][$k];
            }
        }
        return $parts ? sha1($m['modname'] . "\n" . implode("\n", $parts)) : null;
    }

    /**
     * Settings fingerprint (visibility, completion, settings, grade items) used to tell whether a group of equivalent
     * modules has identical settings on both sides.
     *
     * @param array $m
     * @return string
     */
    public static function settings_fingerprint(array $m): string {
        $extra = array_diff_key($m['extra'] ?? [], array_flip(self::NOT_SETTINGS));
        ksort($extra);
        $grades = array_map(fn($g) => [$g['itemnumber'], $g['gradetype'], (float) $g['grademax'], (float) $g['gradepass'],
            $g['hidden']], $m['grade_items'] ?? []);
        sort($grades);
        return sha1(json_encode([$m['visible'], $m['completion'], $m['completionview'], $m['completiongradeitemnumber'],
            $m['completionpassgrade'], $extra, $grades]));
    }
}
