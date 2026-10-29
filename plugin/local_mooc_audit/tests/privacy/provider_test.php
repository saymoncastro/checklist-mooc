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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: who confirmed corrections, requested audits and batches; export and anonymisation.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * A trail row.
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    private function row(int $courseid, int $userid): int {
        global $DB;
        return $DB->insert_record('local_mooc_audit_fix', (object) ['courseid' => $courseid, 'cmid' => 7, 'ruleid' => 'CL-13',
            'strategy' => 'quiz_attempts', 'field' => 'attempts', 'oldvalue' => '1', 'newvalue' => '2', 'outcome' => 'applied',
            'reaudit' => 'resolved', 'policyversion' => 'v', 'engineversion' => '0.3.0', 'userid' => $userid,
            'timecreated' => time()]);
    }

    /**
     * Metadata, contexts, users, export and anonymisation.
     */
    public function test_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->row($c1->id, $u1->id);
        $this->row($c2->id, $u1->id);
        $this->row($c1->id, $u2->id);
        $ctx1 = \context_course::instance($c1->id);
        $ctx2 = \context_course::instance($c2->id);

        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('local_mooc_audit'))->get_collection();
        $this->assertSame(
            ['local_mooc_audit_fix', 'local_mooc_audit_run', 'local_mooc_audit_batch'],
            array_map(fn($i) => $i->get_name(), $items)
        );

        $contexts = provider::get_contexts_for_userid($u1->id)->get_contextids();
        sort($contexts);
        $this->assertEquals([$ctx1->id, $ctx2->id], $contexts);

        $userlist = new userlist($ctx1, 'local_mooc_audit');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($u1, 'local_mooc_audit', [$ctx1->id]));
        $data = writer::with_context($ctx1)->get_data([get_string('privacy:path', 'local_mooc_audit')]);
        $this->assertCount(1, $data->corrections);
        $this->assertSame('attempts', $data->corrections[0]->field);

        provider::delete_data_for_user(new approved_contextlist($u1, 'local_mooc_audit', [$ctx1->id]));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_fix', ['userid' => $u1->id, 'courseid' => $c1->id]));
        $this->assertSame(1, $DB->count_records('local_mooc_audit_fix', ['userid' => $u1->id, 'courseid' => $c2->id]));
        $this->assertSame(3, $DB->count_records('local_mooc_audit_fix'), 'rows kept, anonymised');

        provider::delete_data_for_users(new approved_userlist($ctx1, 'local_mooc_audit', [$u2->id]));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_fix', ['userid' => $u2->id]));

        provider::delete_data_for_all_users_in_context($ctx2);
        $this->assertSame(3, $DB->count_records('local_mooc_audit_fix', ['userid' => 0]));
    }

    /**
     * Recorded audits (course context) and batches (system context): contexts, users, export and anonymisation that
     * keeps the history.
     */
    public function test_runs_and_batches(): void {
        global $DB;
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $run = fn(int $userid) => \local_mooc_audit\local\history\run_store::create((int) $c1->id, 'page', $userid);
        $run($u1->id);
        $run($u2->id);
        $DB->insert_record('local_mooc_audit_batch', (object) ['userid' => $u1->id, 'name' => 'Lote', 'criteria' => '{}',
            'timecreated' => time()]);
        $ctx = \context_course::instance($c1->id);
        $system = \context_system::instance();

        $contexts = provider::get_contexts_for_userid($u1->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$ctx->id, $system->id], $contexts);
        $this->assertEquals([$ctx->id], provider::get_contexts_for_userid($u2->id)->get_contextids());
        $users = new userlist($ctx, 'local_mooc_audit');
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $users->get_userids());
        $sysusers = new userlist($system, 'local_mooc_audit');
        provider::get_users_in_context($sysusers);
        $this->assertEquals([$u1->id], $sysusers->get_userids());

        provider::export_user_data(new approved_contextlist($u1, 'local_mooc_audit', [$ctx->id, $system->id]));
        $runs = writer::with_context($ctx)->get_data([get_string('privacy:path_runs', 'local_mooc_audit')]);
        $this->assertCount(1, $runs->runs);
        $this->assertSame('page', $runs->runs[0]->origin);
        $batches = writer::with_context($system)->get_data([get_string('privacy:path_batches', 'local_mooc_audit')]);
        $this->assertSame('Lote', $batches->batches[0]->name);

        provider::delete_data_for_user(new approved_contextlist($u1, 'local_mooc_audit', [$ctx->id, $system->id]));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_run', ['userid' => $u1->id]));
        $this->assertSame(0, $DB->count_records('local_mooc_audit_batch', ['userid' => $u1->id]));
        $this->assertSame(2, $DB->count_records('local_mooc_audit_run'), 'history kept, anonymised');
        $this->assertSame(1, $DB->count_records('local_mooc_audit_batch'));

        provider::delete_data_for_users(new approved_userlist($ctx, 'local_mooc_audit', [$u2->id]));
        $this->assertSame(2, $DB->count_records('local_mooc_audit_run', ['userid' => 0]));
    }
}
