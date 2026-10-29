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
     * Strip HTML to plain text.
     *
     * @param string|null $s
     * @return string
     */
    public static function text(?string $s): string {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
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
