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
 * The audit reads course configuration only. Personal data stored by the plugin is limited to user ids:
 * - local_mooc_audit_fix (specs/002): who confirmed each assisted correction, course context;
 * - local_mooc_audit_run (specs/005): who requested each recorded audit, course context (the stored reports are the
 *   configuration view of the course, without student data);
 * - local_mooc_audit_batch (specs/005): who requested each batch, system context; runs of removed courses (no course
 *   context any more) are handled in the system context too.
 * Deletion anonymises the rows (userid = 0) instead of deleting them: the institutional trail of what was changed or
 * audited in the course is kept without personal data.
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
        $collection->add_database_table('local_mooc_audit_run', [
            'userid' => 'privacy:metadata:run:userid',
            'courseid' => 'privacy:metadata:run:courseid',
            'origin' => 'privacy:metadata:run:origin',
            'timecreated' => 'privacy:metadata:run:timecreated',
        ], 'privacy:metadata:run');
        $collection->add_database_table('local_mooc_audit_batch', [
            'userid' => 'privacy:metadata:batch:userid',
            'name' => 'privacy:metadata:batch:name',
            'timecreated' => 'privacy:metadata:batch:timecreated',
        ], 'privacy:metadata:batch');
        return $collection;
    }

    /**
     * Course contexts where the user confirmed a correction or requested an audit; system context for batches.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id FROM {context} ctx JOIN {local_mooc_audit_fix} f ON f.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND f.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT ctx.id FROM {context} ctx JOIN {local_mooc_audit_run} r ON r.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND r.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        if (
            $DB->record_exists('local_mooc_audit_batch', ['userid' => $userid]) || self::orphan_runs($userid)
                || self::orphan_fixes($userid)
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users who confirmed corrections or requested audits in a course context; requesters of batches in the system.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mooc_audit_batch} WHERE userid > 0', []);
            $userlist->add_from_sql('userid', 'SELECT r.userid FROM {local_mooc_audit_run} r
                LEFT JOIN {course} c ON c.id = r.courseid WHERE c.id IS NULL AND r.userid > 0', []);
            $userlist->add_from_sql('userid', 'SELECT f.userid FROM {local_mooc_audit_fix} f
                LEFT JOIN {course} c ON c.id = f.courseid WHERE c.id IS NULL AND f.userid > 0', []);
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_mooc_audit_run} WHERE courseid = :courseid AND userid > 0',
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_mooc_audit_fix} WHERE courseid = :courseid AND userid > 0',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export the corrections the user confirmed, the audits and the batches the user requested.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $batches = $DB->get_records(
                    'local_mooc_audit_batch',
                    ['userid' => $userid],
                    'timecreated',
                    'id, name, timecreated'
                );
                if ($batches) {
                    writer::with_context($context)->export_data(
                        [get_string('privacy:path_batches', 'local_mooc_audit')],
                        (object) ['batches' => array_map(
                            fn($b) => (object) ['id' => $b->id, 'name' => $b->name,
                            'timecreated' => \core_privacy\local\request\transform::datetime($b->timecreated)],
                            array_values($batches)
                        )]
                    );
                }
                $orphans = self::orphan_runs($userid);
                if ($orphans) {
                    writer::with_context($context)->export_data(
                        [get_string('privacy:path_runs', 'local_mooc_audit')],
                        (object) ['runs' => array_map(
                            fn($r) => (object) ['id' => $r->id, 'courseid' => $r->courseid, 'origin' => $r->origin,
                            'status' => $r->status,
                            'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated)],
                            array_values($orphans)
                        )]
                    );
                }
                $fixes = self::orphan_fixes($userid);
                if ($fixes) {
                    writer::with_context($context)->export_data(
                        [get_string('privacy:path', 'local_mooc_audit')],
                        (object) ['corrections' => array_map(fn($r) => (object) [
                            'courseid' => $r->courseid, 'cmid' => $r->cmid, 'ruleid' => $r->ruleid, 'field' => $r->field,
                            'oldvalue' => $r->oldvalue, 'newvalue' => $r->newvalue, 'outcome' => $r->outcome,
                            'reaudit' => $r->reaudit,
                            'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
                        ], array_values($fixes))]
                    );
                }
                continue;
            }
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $runs = $DB->get_records(
                'local_mooc_audit_run',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'id',
                'id, origin, status, overall, timecreated'
            );
            if ($runs) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path_runs', 'local_mooc_audit')],
                    (object) ['runs' => array_map(
                        fn($r) => (object) ['id' => $r->id, 'origin' => $r->origin,
                        'status' => $r->status, 'overall' => $r->overall,
                        'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated)],
                        array_values($runs)
                    )]
                );
            }
            $rows = $DB->get_records(
                'local_mooc_audit_fix',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecreated',
                'id, cmid, ruleid, field, oldvalue, newvalue, outcome, reaudit, timecreated'
            );
            if (!$rows) {
                continue;
            }
            $data = array_map(fn($r) => (object) [
                'cmid' => $r->cmid, 'ruleid' => $r->ruleid, 'field' => $r->field, 'oldvalue' => $r->oldvalue,
                'newvalue' => $r->newvalue, 'outcome' => $r->outcome, 'reaudit' => $r->reaudit,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ], array_values($rows));
            writer::with_context($context)->export_data(
                [get_string('privacy:path', 'local_mooc_audit')],
                (object) ['corrections' => $data]
            );
        }
    }

    /**
     * Anonymise every row of a course (or every batch, in the system context).
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['courseid' => $context->instanceid]);
            $DB->set_field('local_mooc_audit_run', 'userid', 0, ['courseid' => $context->instanceid]);
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field_select('local_mooc_audit_batch', 'userid', 0, 'userid > 0');
            foreach (array_keys(self::orphan_runs()) as $id) {
                $DB->set_field('local_mooc_audit_run', 'userid', 0, ['id' => $id]);
            }
            foreach (array_keys(self::orphan_fixes()) as $id) {
                $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['id' => $id]);
            }
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
                $DB->set_field('local_mooc_audit_run', 'userid', 0, ['courseid' => $context->instanceid, 'userid' => $userid]);
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field('local_mooc_audit_batch', 'userid', 0, ['userid' => $userid]);
                foreach (array_keys(self::orphan_runs((int) $userid)) as $id) {
                    $DB->set_field('local_mooc_audit_run', 'userid', 0, ['id' => $id]);
                }
                foreach (array_keys(self::orphan_fixes((int) $userid)) as $id) {
                    $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['id' => $id]);
                }
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
        if (!$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field_select('local_mooc_audit_batch', 'userid', 0, "userid $insql", $params);
            foreach ($userlist->get_userids() as $userid) {
                foreach (array_keys(self::orphan_runs((int) $userid)) as $id) {
                    $DB->set_field('local_mooc_audit_run', 'userid', 0, ['id' => $id]);
                }
                foreach (array_keys(self::orphan_fixes((int) $userid)) as $id) {
                    $DB->set_field('local_mooc_audit_fix', 'userid', 0, ['id' => $id]);
                }
            }
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $params['courseid'] = $context->instanceid;
        $DB->set_field_select('local_mooc_audit_fix', 'userid', 0, "courseid = :courseid AND userid $insql", $params);
        $DB->set_field_select('local_mooc_audit_run', 'userid', 0, "courseid = :courseid AND userid $insql", $params);
    }

    /**
     * Runs of courses that no longer exist (they have no course context: they are handled in the system context).
     *
     * @param int|null $userid only the runs of this requester (null = every identified requester)
     * @return \stdClass[] id => run (id, courseid, origin, status, timecreated)
     */
    protected static function orphan_runs(?int $userid = null): array {
        return self::orphans('local_mooc_audit_run', 'r.id, r.courseid, r.origin, r.status, r.timecreated', $userid);
    }

    /**
     * Assisted corrections of courses that no longer exist (handled in the system context, A-02 of docs/revisao-geral).
     *
     * @param int|null $userid only the corrections confirmed by this user (null = every identified user)
     * @return \stdClass[] id => trail row
     */
    protected static function orphan_fixes(?int $userid = null): array {
        return self::orphans(
            'local_mooc_audit_fix',
            'r.id, r.courseid, r.cmid, r.ruleid, r.field, r.oldvalue, r.newvalue, r.outcome, r.reaudit, r.timecreated',
            $userid
        );
    }

    /**
     * Rows of a plugin table whose course no longer exists.
     *
     * @param string $table plugin table with courseid and userid
     * @param string $fields fields of alias r
     * @param int|null $userid only the rows of this user (null = every identified user)
     * @return \stdClass[]
     */
    protected static function orphans(string $table, string $fields, ?int $userid): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT $fields
               FROM {" . $table . "} r
          LEFT JOIN {course} c ON c.id = r.courseid
              WHERE c.id IS NULL AND " . ($userid ? 'r.userid = :userid' : 'r.userid > 0'),
            ['userid' => (int) $userid]
        );
    }
}
