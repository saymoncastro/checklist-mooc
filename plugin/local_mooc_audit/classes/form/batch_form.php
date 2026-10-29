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

use local_mooc_audit\local\history\batch_manager;

/**
 * New batch audit (specs/005 FR-016): selection by category, list of ids or start year; "Preview" shows the accepted
 * and refused courses before "Queue the audits" creates the batch.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batch_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'action', 'form');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('text', 'name', get_string('batch_name', 'local_mooc_audit'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $modes = [];
        foreach ([batch_manager::MODE_CATEGORY, batch_manager::MODE_IDS, batch_manager::MODE_STARTYEAR] as $m) {
            $modes[$m] = get_string('batch_mode_' . $m, 'local_mooc_audit');
        }
        $mform->addElement('select', 'mode', get_string('batch_mode', 'local_mooc_audit'), $modes);
        $mform->addHelpButton('mode', 'batch_mode', 'local_mooc_audit');
        $mform->addElement(
            'select',
            'categoryid',
            get_string('batch_category', 'local_mooc_audit'),
            \core_course_category::make_categories_list()
        );
        $mform->hideIf('categoryid', 'mode', 'neq', batch_manager::MODE_CATEGORY);
        $mform->addElement('advcheckbox', 'subcategories', '', get_string('batch_subcategories', 'local_mooc_audit'));
        $mform->setDefault('subcategories', 1);
        $mform->hideIf('subcategories', 'mode', 'neq', batch_manager::MODE_CATEGORY);
        $mform->addElement('textarea', 'courseids', get_string('batch_courseids', 'local_mooc_audit'), ['rows' => 3,
            'cols' => 50]);
        $mform->setType('courseids', PARAM_TEXT);
        $mform->hideIf('courseids', 'mode', 'neq', batch_manager::MODE_IDS);
        $mform->addElement('text', 'startyear', get_string('batch_startyear', 'local_mooc_audit'), ['size' => 6]);
        $mform->setType('startyear', PARAM_INT);
        $mform->setDefault('startyear', (int) date('Y') + 1);
        $mform->hideIf('startyear', 'mode', 'neq', batch_manager::MODE_STARTYEAR);
        $buttons = [
            $mform->createElement('submit', 'previewbutton', get_string('batch_preview', 'local_mooc_audit')),
            $mform->createElement('submit', 'submitbutton', get_string('batch_confirm', 'local_mooc_audit')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * The start year must be plausible; course ids are parsed by batch_manager::clean_criteria() (any separator).
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (
            ($data['mode'] ?? '') === batch_manager::MODE_STARTYEAR && ((int) $data['startyear'] < 1970
                || (int) $data['startyear'] > 2100)
        ) {
            $errors['startyear'] = get_string('invaliddata', 'error');
        }
        return $errors;
    }
}
