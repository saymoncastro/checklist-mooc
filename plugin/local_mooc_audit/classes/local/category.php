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
 * The eight fixed categories of the rule catalogue (every rule belongs to exactly one).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category {
    /** @var string Dates, category, enrolment. */
    const OFFER_ENROL = 'offer_enrol';
    /** @var string Forum, surveys, licence, summary buttons. */
    const INSTITUTIONAL_RESOURCES = 'institutional_resources';
    /** @var string Grade book, attempts, closing dates, statements. */
    const ASSESSMENT_GRADES = 'assessment_grades';
    /** @var string Completion tracking and access restrictions. */
    const COMPLETION_RESTRICTIONS = 'completion_restrictions';
    /** @var string Certificate conditions and reachability. */
    const CERTIFICATION = 'certification';
    /** @var string Empty resources, missing files, broken references. */
    const CONTENT_FILES = 'content_files';
    /** @var string Sections, hidden modules, blocks, glossary. */
    const STRUCTURE_BLOCKS = 'structure_blocks';
    /** @var string Installed plugins and site settings. */
    const PLUGINS_ENVIRONMENT = 'plugins_environment';

    /**
     * All categories in display order.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::OFFER_ENROL, self::INSTITUTIONAL_RESOURCES, self::ASSESSMENT_GRADES, self::COMPLETION_RESTRICTIONS,
            self::CERTIFICATION, self::CONTENT_FILES, self::STRUCTURE_BLOCKS, self::PLUGINS_ENVIRONMENT];
    }

    /**
     * Whether a value is a known category.
     *
     * @param string|null $category
     * @return bool
     */
    public static function is_valid(?string $category): bool {
        return in_array($category, self::all(), true);
    }

    /**
     * Display rank (0 first).
     *
     * @param string|null $category
     * @return int
     */
    public static function rank(?string $category): int {
        $i = array_search($category, self::all(), true);
        return $i === false ? 9 : $i;
    }

    /**
     * Localised title.
     *
     * @param string $category
     * @return string
     */
    public static function title(string $category): string {
        return get_string('category_' . $category, 'local_mooc_audit');
    }
}
