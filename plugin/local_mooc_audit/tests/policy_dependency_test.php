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

use local_mooc_audit\local\catalog;

/**
 * Every rule declares the policy keys it reads (specs/005 FR-013): the history uses the declared keys to tell a change
 * of policy from a change in the course, so an undeclared key would turn a policy change into "new" or "resolved"
 * problems. Each key is perturbed on courses A and B; every rule whose results change must declare it (rules of the
 * offer cycle may depend on the reoffer year through OFFER_CYCLE).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\rule\base
 */
final class policy_dependency_test extends \advanced_testcase {
    /** @var int Reoffer year of the fixtures. */
    const YEAR = 2027;

    /**
     * A different value of the same type for each policy key.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected static function perturb(string $key, $value) {
        $special = match ($key) {
            'reoffer_year' => self::YEAR + 1,
            'required_start_date' => '02/01',
            'allowed_end_dates' => ['30/12'],
            'attempts_rule' => 'exact',
            'required_blocks' => ['completion_progress' => null, 'html' => ['Outro bloco']],
            'generic_section_names' => '/./u',
            'completion_expected' => array_map(fn() => 'grade', $value),
            default => null,
        };
        if ($special !== null) {
            return $special;
        }
        return match (true) {
            is_bool($value) => !$value,
            is_int($value) => $value + 7,
            is_float($value) => $value / 2,
            is_string($value) => 'Valor perturbado',
            is_array($value) => ['valor-perturbado'],
            default => 1,
        };
    }

    /**
     * Results of each rule, reduced to everything a person sees.
     *
     * @param array $results
     * @return array rule => signature
     */
    protected static function by_rule(array $results): array {
        $out = [];
        foreach ($results as $r) {
            $out[$r['rule']][] = implode('|', [$r['occurrence'], $r['status'], $r['gravidade'], $r['evidencia'],
                $r['encontrado'], $r['esperado']]);
        }
        return $out;
    }

    /**
     * Perturb every key on A and B and check the declarations.
     */
    public function test_rules_declare_every_policy_key_they_read(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_mooc_audit');
        $snapshots = [snapshot::build($gen->create_course_a(self::YEAR)[0]->id),
            snapshot::build($gen->create_course_b(self::YEAR)[0]->id)];
        $base = policy::load(['reoffer_year' => self::YEAR]);
        $meta = array_map(fn($r) => $r->meta(), catalog::instances());
        $tested = 0;
        $missing = [];
        foreach (array_keys(policy::defaults()) as $key) {
            if (in_array($key, ['policy_version', 'reference_timestamp'], true)) {
                continue;
            }
            $changed = $base;
            $changed[$key] = self::perturb($key, $base[$key]);
            foreach ($snapshots as $snapshot) {
                $before = self::by_rule(engine::run_rules($snapshot, $base));
                $after = self::by_rule(engine::run_rules($snapshot, $changed));
                foreach ($before as $rule => $sig) {
                    if ($sig === ($after[$rule] ?? null)) {
                        continue;
                    }
                    $declared = in_array($key, $meta[$rule]['policy_keys'], true)
                        || ($key === 'reoffer_year' && $meta[$rule]['offer_cycle']);
                    if (!$declared) {
                        $missing["$rule:$key"] = "$rule reads '$key'";
                    }
                    $tested++;
                }
            }
        }
        $this->assertSame([], array_values($missing), 'Policy keys read but not declared in POLICY_KEYS');
        // The perturbations reach most rules that read the policy (sanity check of the test itself).
        $this->assertGreaterThanOrEqual(15, $tested);
    }

    /**
     * Rule fingerprints (specs/005 R4) follow the declarations: a key outside a rule's slice leaves its fingerprint
     * intact.
     */
    public function test_policy_slice_follows_declarations(): void {
        $this->resetAfterTest();
        $base = policy::load(['reoffer_year' => self::YEAR]);
        $changed = array_merge($base, ['min_quiz_attempts' => 9]);
        $a = local\history\fingerprint::rules($base);
        $b = local\history\fingerprint::rules($changed);
        $this->assertNotSame($a['CL-13']['p'], $b['CL-13']['p']);
        $this->assertSame($a['CL-13']['r'], $b['CL-13']['r']);
        foreach (array_diff(array_keys($a), ['CL-13']) as $rule) {
            $this->assertSame($a[$rule], $b[$rule], $rule);
        }
        $year = local\history\fingerprint::rules(array_merge($base, ['reoffer_year' => self::YEAR + 1]));
        foreach ($a as $rule => $fp) {
            $cycle = in_array($rule, ['CL-05', 'CL-06', 'CL-07', 'CL-08', 'CL-14'], true);
            $this->assertSame(!$cycle, $fp['p'] === $year[$rule]['p'], $rule);
        }
    }
}
