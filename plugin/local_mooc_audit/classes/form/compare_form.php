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

namespace local_mooc_audit\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Selection of the two offers to compare (posted with sesskey, then redirected to the GET URL of the comparison).
 *
 * The pickers list only courses where the user holds local/mooc_audit:compare (the page checks the capability again in
 * both course contexts). Each course is shown as "short name — full name (start year, id)", whatever the site setting
 * "courselistshortnames" says: the previous and the new offer usually share the full name, and only the short name,
 * the year and the id tell them apart. Typing any of these parts filters the list.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compare_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $courses = [0 => ''] + self::course_options();
        $attributes = ['multiple' => false, 'noselectionstring' => get_string('cmp_choose_course', 'local_mooc_audit'),
            'placeholder' => get_string('cmp_search_course', 'local_mooc_audit')];
        foreach (['source', 'target'] as $field) {
            $mform->addElement('autocomplete', $field, get_string("cmp_$field", 'local_mooc_audit'), $courses, $attributes);
            $mform->setType($field, PARAM_INT);
            $mform->addRule($field, null, 'required', null, 'client');
        }
        $mform->addElement('advcheckbox', 'confirm', '', get_string('cmp_confirm', 'local_mooc_audit'));
        $this->add_action_buttons(false, get_string('cmp_submit', 'local_mooc_audit'));
    }

    /**
     * Courses the current user may compare, as "short name — full name (start year, id)", most recent start first.
     *
     * @param int|null $userid default: current user
     * @return string[] course id => label
     */
    public static function course_options(?int $userid = null): array {
        $courses = get_user_capability_course(
            'local/mooc_audit:compare',
            $userid,
            true,
            'shortname, fullname, startdate',
            'startdate DESC, fullname ASC'
        ) ?: [];
        $tz = \core_date::get_server_timezone();
        $out = [];
        foreach ($courses as $c) {
            if ((int) $c->id === (int) SITEID) {
                continue;
            }
            $context = \context_course::instance($c->id);
            $out[(int) $c->id] = get_string('cmp_course_option', 'local_mooc_audit', (object) [
                'shortname' => format_string($c->shortname, true, ['context' => $context, 'escape' => false]),
                'fullname' => format_string($c->fullname, true, ['context' => $context, 'escape' => false]),
                'start' => $c->startdate ? userdate($c->startdate, '%Y', $tz, false) : '—',
                'id' => (int) $c->id,
            ]);
        }
        return $out;
    }

    /**
     * Server-side validation: both courses chosen, different, order confirmed.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['source'])) {
            $errors['source'] = get_string('required');
        }
        if (empty($data['target'])) {
            $errors['target'] = get_string('required');
        }
        if (!empty($data['source']) && (int) $data['source'] === (int) ($data['target'] ?? 0)) {
            $errors['target'] = get_string('cmp_same_course', 'local_mooc_audit');
        }
        if (empty($data['confirm'])) {
            $errors['confirm'] = get_string('cmp_confirm_required', 'local_mooc_audit');
        }
        return $errors;
    }
}
