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

use local_mooc_audit\local\catalog;
use local_mooc_audit\local\rule\rule_interface;

/**
 * Fingerprints that tell a change of policy or of rule code from a change in the course (specs/005 research R4).
 *
 * The policy fingerprint covers the effective policy without the execution clock (reference_timestamp) and the load
 * warnings. Each rule gets two short hashes: "p" of the policy values it declares (plus the reoffer year for the rules
 * of the offer cycle) and "r" of its source file together with the shared code every rule depends on (collector,
 * helpers, base class, states): a change in any of them may change results without any change in the course.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fingerprint {
    /** @var string[] Keys of a loaded policy that are not policy content. */
    const NOT_CONTENT = ['warnings', 'reference_timestamp'];

    /** @var string[] Shared code read by every rule, relative to the plugin directory. */
    const SHARED = ['classes/snapshot.php', 'classes/local/helpers.php', 'classes/local/rule/base.php',
        'classes/local/status.php'];

    /** @var array Source hash per rule class (files do not change during a request). */
    protected static array $sources = [];
    /** @var string|null Hash of the shared code. */
    protected static ?string $shared = null;

    /**
     * Canonical JSON: object keys sorted recursively, lists kept in order.
     *
     * @param mixed $value
     * @return string
     */
    public static function canonical($value): string {
        return json_encode(self::sorted($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Sort object keys recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function sorted($value) {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map([self::class, 'sorted'], $value);
        if (!array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }

    /**
     * Policy content stored with a run: the loaded policy without the clock and the warnings.
     *
     * @param array $policy loaded policy (policy::load())
     * @return array
     */
    public static function policy_content(array $policy): array {
        return array_diff_key($policy, array_flip(self::NOT_CONTENT));
    }

    /**
     * Fingerprint of a loaded policy.
     *
     * @param array $policy
     * @return string sha1
     */
    public static function policy(array $policy): string {
        return sha1(self::canonical(self::policy_content($policy)));
    }

    /**
     * Per-rule fingerprints.
     *
     * @param array $policy loaded policy
     * @param rule_interface[]|null $rules default: the catalogue
     * @return array rule id => ['p' => hash, 'r' => hash]
     */
    public static function rules(array $policy, ?array $rules = null): array {
        $out = [];
        foreach ($rules ?? catalog::instances() as $id => $rule) {
            $meta = $rule->meta();
            $keys = $meta['policy_keys'];
            if ($meta['offer_cycle']) {
                $keys[] = 'reoffer_year';
            }
            $slice = [];
            foreach (array_unique($keys) as $k) {
                $slice[$k] = $policy[$k] ?? null;
            }
            $out[$id] = ['p' => substr(sha1(self::canonical($slice)), 0, 12), 'r' => self::source($rule)];
        }
        return $out;
    }

    /**
     * Short hash of the source file of a rule class and of the shared code.
     *
     * @param rule_interface $rule
     * @return string
     */
    public static function source(rule_interface $rule): string {
        if (self::$shared === null) {
            $dir = dirname(__DIR__, 3);
            self::$shared = sha1(implode("\n", array_map(fn($f) => sha1_file("$dir/$f") ?: $f, self::SHARED)));
        }
        $class = get_class($rule);
        if (!isset(self::$sources[$class])) {
            $file = (new \ReflectionClass($rule))->getFileName();
            self::$sources[$class] = substr(sha1(self::$shared . ($file && is_readable($file) ? file_get_contents($file)
                : $class)), 0, 12);
        }
        return self::$sources[$class];
    }
}
