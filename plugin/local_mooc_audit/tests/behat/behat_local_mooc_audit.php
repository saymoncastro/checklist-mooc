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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps of local_mooc_audit: course set-ups the core generators cannot express, and the audit page.
 *
 * @package    local_mooc_audit
 * @category   test
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_mooc_audit extends behat_base {
    /**
     * Creates, in a course, a quiz that requires completing a page without completion tracking (X-DEP-02, case A of
     * docs/auditoria-ux-correcao): the condition needs the page id, which the core generators cannot reference.
     *
     * @Given /^the course "(?P<shortname_string>(?:[^"]|\\")*)" has a quiz that requires a page without completion$/
     * @param string $shortname
     */
    public function the_course_has_a_quiz_that_requires_a_page_without_completion(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $generator = \testing_util::get_data_generator();
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Aprofundamento', 'completion' => 0]);
        $availability = json_encode(['op' => '&', 'showc' => [true],
            'c' => [['type' => 'completion', 'cm' => (int) $page->cmid, 'e' => 1]]]);
        $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Questionário 1', 'attempts' => 2,
            'completion' => 1, 'availability' => $availability]);
    }

    /**
     * Opens the technical audit page of a course (the course page of the reference course is slow to render in Behat).
     *
     * @Given /^I am on the technical audit page of "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $shortname
     */
    public function i_am_on_the_technical_audit_page_of(string $shortname): void {
        global $DB;
        $id = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $this->execute('behat_general::i_visit', [new moodle_url('/local/mooc_audit/index.php', ['courseid' => $id])]);
    }
}
