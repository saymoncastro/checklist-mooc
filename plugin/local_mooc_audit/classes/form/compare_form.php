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
 * Selection of the two offers to compare (GET form: the comparison writes nothing and its URL can be shared).
 *
 * The course pickers search by name or short name and list only courses where the user holds
 * local/mooc_audit:compare; the page checks the capability again in both course contexts.
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
        $options = ['requiredcapabilities' => ['local/mooc_audit:compare'], 'multiple' => false, 'includefrontpage' => false];
        $mform->addElement('course', 'source', get_string('cmp_source', 'local_mooc_audit'), $options);
        $mform->addRule('source', null, 'required', null, 'client');
        $mform->addElement('course', 'target', get_string('cmp_target', 'local_mooc_audit'), $options);
        $mform->addRule('target', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'confirm', '', get_string('cmp_confirm', 'local_mooc_audit'));
        $this->add_action_buttons(false, get_string('cmp_submit', 'local_mooc_audit'));
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
