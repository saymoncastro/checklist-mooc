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

namespace local_mooc_audit\local;

use local_mooc_audit\local\rule\rule_interface;

/**
 * Explicit, ordered catalogue of the rule classes (no autodiscovery: deterministic order, no I/O).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string[] Rule classes in evaluation order. */
    const RULES = [
        rule\cl04::class,
        rule\cl05::class,
        rule\cl06::class,
        rule\cl07::class,
        rule\cl08::class,
        rule\cl09::class,
        rule\cl11::class,
        rule\cl12::class,
        rule\cl13::class,
        rule\cl14::class,
        rule\cl16::class,
        rule\cl17::class,
        rule\cl18::class,
        rule\cl19::class,
        rule\cl20::class,
        rule\cl21::class,
        rule\cl22::class,
        rule\cl23::class,
        rule\xint02::class,
        rule\xint03::class,
        rule\xcont01::class,
        rule\xcont04::class,
        rule\xav01::class,
        rule\xdep01::class,
        rule\xdep02::class,
        rule\xdep03::class,
        rule\xdep04::class,
        rule\xcert01::class,
        rule\xstr01::class,
    ];

    /**
     * Instantiate every rule.
     *
     * @return rule_interface[] keyed by rule id
     */
    public static function instances(): array {
        $out = [];
        foreach (self::RULES as $class) {
            $rule = new $class();
            $out[$rule->id()] = $rule;
        }
        return $out;
    }

    /**
     * Number of rules.
     *
     * @return int
     */
    public static function count(): int {
        return count(self::RULES);
    }
}
