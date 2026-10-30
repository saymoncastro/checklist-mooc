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
 * Guard of the scripts that create or delete courses for tests (cli/make_*.php): they run only on a local development
 * site, with developer debugging on (A-04 of docs/revisao-geral). Turning the debugger on in production for a diagnosis
 * is not enough, and neither is a host name that merely contains "localhost".
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class devsite {
    /**
     * Whether the site address is a local host: localhost, a name ending in .localhost, 127.0.0.1 or [::1].
     *
     * @param string $wwwroot
     * @return bool
     */
    public static function is_local(string $wwwroot): bool {
        $host = strtolower((string) parse_url($wwwroot, PHP_URL_HOST));
        return $host === 'localhost' || str_ends_with($host, '.localhost') || $host === '127.0.0.1' || $host === '[::1]';
    }

    /**
     * Whether a test-data script may run: local site AND developer debugging.
     *
     * @param string $wwwroot
     * @param bool $debugdeveloper
     * @return bool
     */
    public static function allowed(string $wwwroot, bool $debugdeveloper): bool {
        return $debugdeveloper && self::is_local($wwwroot);
    }
}
