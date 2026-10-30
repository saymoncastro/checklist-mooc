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

namespace local_mooc_audit\local;

/**
 * Shared helpers of the rules (text normalisation, module selection, formatting).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helpers {
    /** @var array Close-date columns per graded type (first non empty wins; specs/001 CL-14 and specs/002). */
    const CLOSE_FIELDS = [
        'quiz' => ['timeclose'],
        'game' => ['timeclose'],
        'choice' => ['timeclose'],
        'feedback' => ['timeclose'],
        'assign' => ['cutoffdate', 'duedate'],
        'forum' => ['cutoffdate', 'duedate'],
        'data' => ['timeavailableto'],
    ];

    /** @var array Module completion columns and the criterion each one expresses (CL-17 and its correction proposal). */
    const COMPLETION_FIELDS = [
        'completionsubmit' => 'submit', 'completionentries' => 'entries', 'completionposts' => 'posts',
        'completiondiscussions' => 'posts', 'completionreplies' => 'posts', 'completionminattempts' => 'attempts',
        'completionattemptsexhausted' => 'attempts', 'completionpass' => 'grade',
    ];

    /**
     * Close date of a graded activity: 0 when not set, null when the type has no known close-date column.
     *
     * @param array $m snapshot module
     * @return int|null
     */
    public static function close_date(array $m): ?int {
        if (!isset(self::CLOSE_FIELDS[$m['modname']])) {
            return null;
        }
        $close = 0;
        foreach (self::CLOSE_FIELDS[$m['modname']] as $field) {
            $close = $close ?: (int) ($m['extra'][$field] ?? 0);
        }
        return $close;
    }

    /**
     * Automatic completion criteria of a module (view, grade, submit, entries, posts, attempts), without duplicates.
     *
     * @param array $m snapshot module
     * @return string[]
     */
    public static function completion_criteria(array $m): array {
        $crit = [];
        if ($m['completionview']) {
            $crit[] = 'view';
        }
        if ($m['completiongradeitemnumber'] !== null) {
            $crit[] = 'grade';
        }
        foreach (self::COMPLETION_FIELDS as $field => $criterion) {
            if (!empty($m['extra'][$field])) {
                $crit[] = $criterion;
            }
        }
        return array_values(array_unique($crit));
    }

    /**
     * Normalise text for comparisons (lowercase, no accents, collapsed spaces).
     *
     * @param string|null $s
     * @return string
     */
    public static function norm(?string $s): string {
        $s = \core_text::strtolower(trim((string) $s));
        $s = \core_text::specialtoascii($s);
        return preg_replace('/\s+/u', ' ', $s);
    }

    /**
     * Strip HTML to plain text. Line breaks and the end of block elements become spaces, so that "<p>A</p><p>B</p>" reads
     * "A B" and not "AB" (A-11 of docs/revisao-geral); inline elements keep their words together.
     *
     * @param string|null $s
     * @return string
     */
    public static function text(?string $s): string {
        $breaks = '~<br\b[^>]*>|</(?:p|div|li|h[1-6]|tr|td|th|blockquote|pre|section|article|header|footer)\s*>~i';
        $s = preg_replace($breaks, '$0 ', (string) $s);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Human label of a module.
     *
     * @param array $m
     * @return string
     */
    public static function label(array $m): string {
        return "{$m['modname']} \"" . ($m['name'] ?? '(sem nome)') . "\" (cmid={$m['cmid']})";
    }

    /**
     * Find modules by type / name fragment.
     *
     * @param array $s snapshot
     * @param string|null $modname
     * @param string|null $namelike
     * @return array
     */
    public static function mods(array $s, ?string $modname = null, ?string $namelike = null): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            if ($modname && $m['modname'] !== $modname) {
                continue;
            }
            if ($namelike && strpos(self::norm($m['name']), self::norm($namelike)) === false) {
                continue;
            }
            $out[] = $m;
        }
        return $out;
    }

    /**
     * Modules with a value grade item (grademax > 0): "atividades que valem nota".
     *
     * @param array $s snapshot
     * @return array list of [module, gradeitem]
     */
    public static function graded(array $s): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach ($m['grade_items'] as $gi) {
                if ($gi['gradetype'] == 1 && $gi['grademax'] > 0) {
                    $out[] = [$m, $gi];
                }
            }
        }
        return $out;
    }

    /**
     * Certificate modules (excluding the licence declaration).
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array
     */
    public static function certs(array $s, array $p): array {
        return array_values(array_filter(self::mods($s, $p['required_certificate_module']), function ($m) use ($p) {
            return strpos(self::norm($m['name']), self::norm($p['licence_certificate_name'])) === false;
        }));
    }

    /**
     * Format a timestamp in the site timezone.
     *
     * @param int|null $ts
     * @return string
     */
    public static function d(?int $ts): string {
        return $ts ? userdate($ts, '%d/%m/%Y %H:%M', 99, false) : '(não definida)';
    }
}
