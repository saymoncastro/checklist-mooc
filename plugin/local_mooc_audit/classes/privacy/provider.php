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

namespace local_mooc_audit\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * The audit reads course configuration only. The assisted correction trail (local_mooc_audit_fix, specs/002) stores the
 * id of the user who confirmed each correction, in the course context. Deletion anonymises the rows (userid = 0) instead
 * of deleting them: the institutional trail of what was changed in the course is kept without personal data.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mooc_audit_fix', [
            'userid' => 'privacy:metadata:fix:userid',
            'courseid' => 'privacy:metadata:fix:courseid',
            'cmid' => 'privacy:metadata:fix:cmid',
            'ruleid' => 'privacy:metadata:fix:ruleid',
            'field' => 'privacy:metadata:fix:field',
            'oldvalue' => 'privacy:metadata:fix:oldvalue',
            'newvalue' => 'privacy:metadata:fix:newvalue',
            'timecreated' => 'privacy:metadata:fix:timecreated',
        ], 'privacy:metadata:fix');
        return $collection;
    }

    /**
     * Course contexts where the user confirmed a correction.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id FROM {context} ctx JOIN {local_mooc_audit_fix} f ON f.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND f.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users who confirmed corrections in a course context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mooc_audit_fix} WHERE courseid = :courseid AND userid > 0',
            ['courseid' => $context->instanceid]);
    }

    /**
     * Export the corrections the user confirmed.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $rows = $DB->get_records('local_mooc_audit_fix', ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecreated', 'id, cmid, ruleid, field, oldvalue, newvalue, outcome, reaudit, timecreated');
            if (!$rows) {
                continue;
            }
            $data = array_map(fn($r) => (object) [
                'cmid' => $r->cmid, 'ruleid' => $r->ruleid, 'field' => $r->field, 'oldvalue' => $r->oldvalue,
                'newvalue' => $r->newvalue, 'outcome' => $r->outcome, 'reaudit' => $r->reaudit,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ], array_values($rows));
            writer::with_context($context)->export_data([get_string('privacy:path', 'local_mooc_audit')],
                (object) ['corrections' => $data]);
        }
    }

    /**
     * Anonymise every row of a course.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Anonymise the rows of a user in the approved courses.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Anonymise the rows of several users in a course.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->set_field_select('local_mooc_audit_fix', 'userid', 0, "courseid = :courseid AND userid $insql", $params);
    }
}
