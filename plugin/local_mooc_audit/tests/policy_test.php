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

namespace local_mooc_audit;

/**
 * Institutional policy loading: defaults, admin override, type checks, warnings and the automatic reoffer year.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\policy
 * @covers     \local_mooc_audit\admin\setting_policyjson
 */
final class policy_test extends \advanced_testcase {
    /**
     * No setting: defaults, no warnings, reoffer year = reference year + 1.
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        $policy = policy::load();
        $this->assertSame(policy::DEFAULT_VERSION, $policy['policy_version']);
        $this->assertSame([], $policy['warnings']);
        $this->assertSame((int) date('Y') + 1, $policy['reoffer_year']);
        $this->assertSame(2, $policy['min_quiz_attempts']);

        $policy = policy::load(['reference_timestamp' => mktime(12, 0, 0, 10, 15, 2030)]);
        $this->assertSame(2031, $policy['reoffer_year']);
        $policy = policy::load(['reoffer_year' => 2028]);
        $this->assertSame(2028, $policy['reoffer_year']);
    }

    /**
     * Invalid JSON in the admin setting: defaults applied, one warning, version unchanged.
     */
    public function test_invalid_json_setting(): void {
        $this->resetAfterTest();
        set_config('policyjson', '{"min_quiz_attempts": 3,', 'local_mooc_audit');
        $policy = policy::load();
        $this->assertSame(2, $policy['min_quiz_attempts']);
        $this->assertSame(policy::DEFAULT_VERSION, $policy['policy_version']);
        $this->assertCount(1, $policy['warnings']);
        $this->assertSame(get_string('policy_invalid', 'local_mooc_audit', 'Syntax error'), $policy['warnings'][0]);
    }

    /**
     * Valid admin override: applied, version suffixed with +admin, unknown keys kept, wrong types dropped with a
     * warning, null accepted for keys that default to null.
     */
    public function test_admin_override(): void {
        $this->resetAfterTest();
        set_config('policyjson', json_encode(['min_quiz_attempts' => 3, 'policy_version' => '2028-v2', 'unknown_key' => 'x',
            'required_blocks' => 'oops', 'forbidden_modules' => 7, 'reoffer_year' => null]), 'local_mooc_audit');
        $policy = policy::load();
        $this->assertSame(3, $policy['min_quiz_attempts']);
        $this->assertSame('2028-v2+admin', $policy['policy_version']);
        $this->assertSame('x', $policy['unknown_key']);
        $this->assertIsArray($policy['required_blocks']);
        $this->assertSame(['glossary'], $policy['forbidden_modules']);
        $this->assertCount(2, $policy['warnings']);
        $this->assertSame(get_string('policy_badtype', 'local_mooc_audit', 'required_blocks'), $policy['warnings'][0]);
        $this->assertSame(get_string('policy_badtype', 'local_mooc_audit', 'forbidden_modules'), $policy['warnings'][1]);
        $this->assertSame((int) date('Y') + 1, $policy['reoffer_year']);
    }

    /**
     * A CLI/file override wins over the admin setting and is type-checked the same way.
     */
    public function test_override_precedence(): void {
        $this->resetAfterTest();
        set_config('policyjson', json_encode(['min_quiz_attempts' => 3]), 'local_mooc_audit');
        $policy = policy::load(['min_quiz_attempts' => 5, 'course_total_max' => [100]]);
        $this->assertSame(5, $policy['min_quiz_attempts']);
        $this->assertSame(100.0, $policy['course_total_max']);
        $this->assertCount(1, $policy['warnings']);
    }

    /**
     * Changing a canonical name in the admin policy changes what CL-09 looks for, without code changes; the report
     * shows the customised policy version.
     */
    public function test_canonical_name_change_applies_to_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $ids] = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit')->create_course_a(2027);
        $cm = get_coursemodule_from_id('forum', $ids['forum'], $course->id, false, MUST_EXIST);
        $DB->set_field('forum', 'name', 'Fórum de dúvidas', ['id' => $cm->instance]);
        rebuild_course_cache($course->id, true);

        $cl09 = fn(array $report) => array_values(array_filter($report['results'], fn($r) => $r['rule'] === 'CL-09'));
        $report = engine::audit($course->id, ['reoffer_year' => 2027]);
        $this->assertSame(policy::DEFAULT_VERSION, $report['policy_version']);
        $this->assertSame([\local_mooc_audit\local\status::NAO_CONFORME], array_column($cl09($report), 'status'));
        $this->assertSame('import', $cl09($report)[0]['locus']);

        set_config('policyjson', json_encode(['required_forum_name' => 'Fórum de dúvidas',
            'policy_version' => '2027-decisao-10']), 'local_mooc_audit');
        $report = engine::audit($course->id, ['reoffer_year' => 2027]);
        $this->assertSame('2027-decisao-10+admin', $report['policy_version']);
        $this->assertSame([], $report['policy_warnings']);
        $this->assertSame([\local_mooc_audit\local\status::CONFORME], array_column($cl09($report), 'status'));
        $this->assertStringContainsString('Fórum de dúvidas', $cl09($report)[0]['local']);
    }

    /**
     * The admin setting rejects malformed JSON and non-objects before saving, and accepts empty or an object.
     */
    public function test_setting_validation(): void {
        $this->resetAfterTest();
        $setting = new admin\setting_policyjson('local_mooc_audit/policyjson', 'x', 'y', '', PARAM_RAW);
        $this->assertTrue($setting->validate(''));
        $this->assertTrue($setting->validate("  \n"));
        $this->assertTrue($setting->validate('{"min_quiz_attempts": 3}'));
        $this->assertTrue($setting->validate('{}'));
        $this->assertSame(get_string('policyjson_invalid', 'local_mooc_audit', 'Syntax error'), $setting->validate('{not json'));
        $this->assertSame(
            get_string('policyjson_invalid', 'local_mooc_audit', get_string('policyjson_notobject', 'local_mooc_audit')),
            $setting->validate('42')
        );
        $this->assertSame(
            get_string('policyjson_invalid', 'local_mooc_audit', get_string('policyjson_notobject', 'local_mooc_audit')),
            $setting->validate('"text"')
        );
    }
}
