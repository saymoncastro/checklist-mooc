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

namespace local_mooc_audit\local\history;

/**
 * Classification of the evolution between two runs (specs/005 data-model), on synthetic results.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\history\evolution
 */
final class evolution_test extends \basic_testcase {
    /** @var array Default rule fingerprints. */
    const FP = ['CL-13' => ['p' => 'p13', 'r' => 'r13'], 'CL-14' => ['p' => 'p14', 'r' => 'r14'],
        'CL-16' => ['p' => 'p16', 'r' => 'r16'], 'CL-21' => ['p' => 'p21', 'r' => 'r21']];

    /**
     * A result.
     *
     * @param string $key occurrence
     * @param string $status
     * @param string|null $severity
     * @return array
     */
    protected static function r(string $key, string $status, ?string $severity = 'Alta'): array {
        [$rule, $entity] = explode('|', $key);
        return ['rule' => $rule, 'titulo' => "Título $rule", 'status' => $status, 'gravidade' => $severity,
            'local' => $entity, 'cmid' => str_starts_with($entity, 'cm:') ? (int) substr($entity, 3) : null,
            'evidencia' => "evidência $key $status", 'link' => null, 'occurrence' => $key];
    }

    /**
     * A run as evolution::compare() expects it.
     *
     * @param array $results
     * @param array $modules cmid => "modname|name"
     * @param array $fp rule fingerprints
     * @param string $version
     * @return array
     */
    protected static function audit(
        array $results,
        array $modules = [42 => 'quiz|q1', 43 => 'quiz|q2', 50 => 'feedback|av'],
        array $fp = self::FP,
        string $version = '0.5.0 (2026103000)'
    ): array {
        return ['report' => ['results' => $results],
            'details' => ['rules' => $fp, 'modules' => $modules, 'sections' => [0, 1, 2]], 'pluginversion' => $version];
    }

    /**
     * Classes by key.
     *
     * @param array $cmp
     * @return array
     */
    protected static function classes(array $cmp): array {
        return array_column($cmp['items'], 'class', 'key');
    }

    /**
     * The example of the request: quiz with one attempt, wrong closing date, missing survey; the quiz is fixed.
     */
    public function test_request_example_one_resolved_two_persistent(): void {
        $before = self::audit([
            self::r('CL-13|cm:42|attempts', 'NAO_CONFORME'),
            self::r('CL-14|cm:42|close_date', 'NAO_CONFORME', 'Média'),
            self::r('CL-16|course|evaluation_presence', 'NAO_CONFORME'),
        ]);
        $after = self::audit([
            self::r('CL-13|cm:42|attempts', 'CONFORME', null),
            self::r('CL-14|cm:42|close_date', 'NAO_CONFORME', 'Média'),
            self::r('CL-16|course|evaluation_presence', 'NAO_CONFORME'),
        ]);
        $cmp = evolution::compare($before, $after);
        $this->assertSame(1, $cmp['counts']['resolved']);
        $this->assertSame(2, $cmp['counts']['persistent']);
        $this->assertSame(0, $cmp['counts']['new']);
        $this->assertSame('resolved', self::classes($cmp)['CL-13|cm:42|attempts']);
        $this->assertSame([], $cmp['notes']);
    }

    /**
     * No change: everything persistent, nothing new or resolved; a problem that disappears from the list of a rule
     * that ran is resolved too (absence of the key).
     */
    public function test_unchanged_and_absent_key(): void {
        $results = [self::r('CL-13|cm:42|attempts', 'NAO_CONFORME'), self::r('CL-13|cm:43|attempts', 'ATENCAO', 'Baixa')];
        $cmp = evolution::compare(self::audit($results), self::audit($results));
        $this->assertSame(2, $cmp['counts']['persistent']);
        $this->assertSame(2, array_sum($cmp['counts']));

        $cmp = evolution::compare(self::audit($results), self::audit([self::r('CL-13|cm:42|attempts', 'NAO_CONFORME')]));
        $this->assertSame('resolved', self::classes($cmp)['CL-13|cm:43|attempts']);
    }

    /**
     * New problem; state or severity change.
     */
    public function test_new_and_changed(): void {
        $before = self::audit([self::r('CL-13|cm:42|intro_attempts', 'ATENCAO', 'Baixa'),
            self::r('CL-14|cm:43|close_date', 'NAO_CONFORME', 'Média')]);
        $after = self::audit([self::r('CL-13|cm:42|intro_attempts', 'NAO_CONFORME', 'Alta'),
            self::r('CL-14|cm:43|close_date', 'NAO_CONFORME', 'Média'),
            self::r('CL-13|cm:43|attempts', 'NAO_CONFORME')]);
        $c = self::classes(evolution::compare($before, $after));
        $this->assertSame('changed', $c['CL-13|cm:42|intro_attempts']);
        $this->assertSame('persistent', $c['CL-14|cm:43|close_date']);
        $this->assertSame('new', $c['CL-13|cm:43|attempts']);
        $item = array_values(array_filter(
            evolution::compare($before, $after)['items'],
            fn($i) => $i['key'] === 'CL-13|cm:42|intro_attempts'
        ))[0];
        $this->assertSame(['ATENCAO', 'NAO_CONFORME', 'Baixa', 'Alta'], [$item['status_before'], $item['status_after'],
            $item['severity_before'], $item['severity_after']]);
    }

    /**
     * A rule that ended in ERRO, or an occurrence that became not verifiable, is never "resolved".
     */
    public function test_error_and_unverifiable_are_not_resolved(): void {
        $before = self::audit([self::r('CL-13|cm:42|attempts', 'NAO_CONFORME'),
            self::r('CL-14|cm:42|close_date', 'NAO_CONFORME', 'Média')]);
        $after = self::audit([self::r('CL-13|course|_rule', 'ERRO', null),
            self::r('CL-14|cm:42|close_date', 'NAO_VERIFICAVEL', 'Informativa')]);
        $cmp = evolution::compare($before, $after);
        $this->assertSame(2, $cmp['counts']['unverifiable']);
        $this->assertSame(0, $cmp['counts']['resolved']);
        // Back from ERRO: the problems found now were not verified before.
        $cmp = evolution::compare($after, $before);
        $this->assertSame('not_comparable', self::classes($cmp)['CL-13|cm:42|attempts']);
        $this->assertSame('previously_unverified', $cmp['items'][0]['reason']);
        $this->assertSame(0, $cmp['counts']['new']);
    }

    /**
     * A policy change limited to one rule: its appearing/disappearing problems are not comparable, the problems present
     * in both runs stay persistent and the other rules are classified normally.
     */
    public function test_policy_change_only_affects_its_rule(): void {
        $before = self::audit([self::r('CL-13|cm:42|attempts', 'NAO_CONFORME'), self::r('CL-13|cm:43|attempts', 'NAO_CONFORME'),
            self::r('CL-14|cm:42|close_date', 'NAO_CONFORME', 'Média')]);
        $fp = self::FP;
        $fp['CL-13']['p'] = 'other';
        $after = self::audit([self::r('CL-13|cm:42|attempts', 'CONFORME', null), self::r('CL-13|cm:43|attempts', 'NAO_CONFORME'),
            self::r('CL-13|cm:50|attempts', 'NAO_CONFORME')], fp: $fp);
        $cmp = evolution::compare($before, $after);
        $c = self::classes($cmp);
        $this->assertSame('not_comparable', $c['CL-13|cm:42|attempts']);
        $this->assertSame('persistent', $c['CL-13|cm:43|attempts']);
        $this->assertSame('not_comparable', $c['CL-13|cm:50|attempts']);
        $this->assertSame('resolved', $c['CL-14|cm:42|close_date']);
        $this->assertSame(0, $cmp['counts']['new']);
        $reasons = array_column($cmp['items'], 'reason', 'key');
        $this->assertSame('policy_changed', $reasons['CL-13|cm:42|attempts']);

        $fp = self::FP;
        $fp['CL-13']['r'] = 'newcode';
        $cmp = evolution::compare($before, self::audit([], fp: $fp, version: '0.6.0 (2027010100)'));
        $this->assertSame('rule_changed', array_column($cmp['items'], 'reason', 'key')['CL-13|cm:42|attempts']);
        $this->assertSame(['plugin_version_changed'], $cmp['notes']);
    }

    /**
     * Activity removed; activity replaced by one of the same type and name with the same problem (not "resolved + new").
     */
    public function test_removed_and_replaced(): void {
        $before = self::audit([self::r('CL-13|cm:42|attempts', 'NAO_CONFORME'), self::r('CL-13|cm:43|attempts', 'NAO_CONFORME')]);
        $after = self::audit([self::r('CL-13|cm:44|attempts', 'NAO_CONFORME')], [44 => 'quiz|q1', 50 => 'feedback|av']);
        $cmp = evolution::compare($before, $after);
        $c = self::classes($cmp);
        $this->assertSame('replaced', $c['CL-13|cm:42|attempts']);
        $this->assertSame('removed', $c['CL-13|cm:43|attempts']);
        $this->assertArrayNotHasKey('CL-13|cm:44|attempts', $c);
        $this->assertSame(44, array_column($cmp['items'], 'successor_cmid', 'key')['CL-13|cm:42|attempts']);
        $this->assertSame(0, $cmp['counts']['new'] + $cmp['counts']['resolved']);

        // A removed section.
        $before = self::audit([self::r('CL-20|sec:2|content', 'ATENCAO', 'Média')]);
        $after = self::audit([]);
        $after['details']['sections'] = [0, 1];
        $this->assertSame('removed', self::classes(evolution::compare($before, $after))['CL-20|sec:2|content']);
    }

    /**
     * Several occurrences of one rule at one location: only the fixed block is resolved.
     */
    public function test_several_occurrences_same_location(): void {
        $keys = ['CL-21|course|html_block|atencao ao prazo', 'CL-21|course|html_block|duvidas tecnicas',
            'CL-21|course|html_block|acessibilidade'];
        $before = self::audit(array_map(fn($k) => self::r($k, 'NAO_CONFORME', 'Média'), $keys));
        $after = self::audit([self::r($keys[0], 'NAO_CONFORME', 'Média'), self::r($keys[1], 'CONFORME', null),
            self::r($keys[2], 'NAO_CONFORME', 'Média')]);
        $c = self::classes(evolution::compare($before, $after));
        $this->assertSame(['persistent', 'resolved', 'persistent'], [$c[$keys[0]], $c[$keys[1]], $c[$keys[2]]]);
    }

    /**
     * A base recorded without occurrence keys cannot be compared.
     */
    public function test_base_without_occurrences(): void {
        $old = self::r('CL-13|cm:42|attempts', 'NAO_CONFORME');
        unset($old['occurrence']);
        $cmp = evolution::compare(self::audit([$old]), self::audit([self::r('CL-13|cm:42|attempts', 'NAO_CONFORME')]));
        $this->assertNull($cmp['counts']);
        $this->assertSame(['base_without_occurrences'], $cmp['notes']);
    }
}
