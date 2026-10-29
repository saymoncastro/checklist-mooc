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

use local_mooc_audit\local\rule\base;
use moodle_url;

/**
 * Maps a finding (locus + identifiers) to the Moodle page where the problem can be fixed.
 *
 * Knowledge of routes lives only here; rules declare a locus and identifiers, never URLs.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locator {

    /**
     * Every locus a finding may declare.
     *
     * @return string[]
     */
    public static function loci(): array {
        return [base::LOCUS_MODULE_EDIT, base::LOCUS_MODULE_VIEW, base::LOCUS_COURSE_SETTINGS, base::LOCUS_ENROL, base::LOCUS_GRADEBOOK,
            base::LOCUS_BLOCKS, base::LOCUS_SECTION, base::LOCUS_IMPORT, base::LOCUS_NONE];
    }

    /**
     * Localised description of the destination of a locus (used as link title).
     *
     * @param string $locus
     * @return string
     */
    public static function title(string $locus): string {
        return get_string('locus_' . $locus, 'local_mooc_audit');
    }

    /**
     * URL for a result, or null when there is no navigable destination.
     *
     * @param array $result finding with keys locus, cmid, section
     * @param int $courseid
     * @param array $modules snapshot modules keyed by cmid
     * @param array $sections snapshot sections keyed by section id (with 'number')
     * @return moodle_url|null
     */
    public static function url_for(array $result, int $courseid, array $modules, array $sections): ?moodle_url {
        $locus = $result['locus'] ?? base::LOCUS_NONE;
        $cmid = (int) ($result['cmid'] ?? 0);
        switch ($locus) {
            case base::LOCUS_MODULE_EDIT:
                return $cmid && isset($modules[$cmid]) ? new moodle_url('/course/modedit.php', ['update' => $cmid]) : null;
            case base::LOCUS_MODULE_VIEW:
                if (!$cmid || !isset($modules[$cmid])) {
                    return null;
                }
                return new moodle_url('/mod/' . $modules[$cmid]['modname'] . '/view.php', ['id' => $cmid]);
            case base::LOCUS_COURSE_SETTINGS:
                return new moodle_url('/course/edit.php', ['id' => $courseid]);
            case base::LOCUS_ENROL:
                return new moodle_url('/enrol/instances.php', ['id' => $courseid]);
            case base::LOCUS_GRADEBOOK:
                return new moodle_url('/grade/edit/tree/index.php', ['id' => $courseid]);
            case base::LOCUS_BLOCKS:
                return new moodle_url('/course/view.php', ['id' => $courseid]);
            case base::LOCUS_IMPORT:
                return new moodle_url('/backup/import.php', ['id' => $courseid]);
            case base::LOCUS_SECTION:
                if (!isset($result['section'])) {
                    return null;
                }
                foreach ($sections as $sec) {
                    if ((int) $sec['number'] === (int) $result['section']) {
                        return new moodle_url('/course/section.php', ['id' => $sec['id']]);
                    }
                }
                return null;
            default:
                return null;
        }
    }
}
