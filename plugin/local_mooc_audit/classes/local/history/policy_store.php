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
 * Applied policies: the effective policy content is stored once per fingerprint and referenced by the runs.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy_store {
    /** @var string Table. */
    const TABLE = 'local_mooc_audit_policy';

    /**
     * Id of the stored policy with this content, storing it when new.
     *
     * @param array $policy loaded policy (policy::load())
     * @return int
     */
    public static function ensure(array $policy): int {
        global $DB;
        $fp = fingerprint::policy($policy);
        $id = $DB->get_field(self::TABLE, 'id', ['fingerprint' => $fp]);
        if ($id) {
            return (int) $id;
        }
        try {
            return (int) $DB->insert_record(self::TABLE, (object) [
                'fingerprint' => $fp,
                'policyversion' => \core_text::substr((string) $policy['policy_version'], 0, 100),
                'policy' => fingerprint::canonical(fingerprint::policy_content($policy)),
                'timecreated' => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // Another request stored the same content in the meantime (unique index).
            return (int) $DB->get_field(self::TABLE, 'id', ['fingerprint' => $fp], MUST_EXIST);
        }
    }

    /**
     * A stored policy.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Fingerprint of the policy currently in force (admin setting + defaults), used to flag outdated results.
     *
     * @return string
     */
    public static function current_fingerprint(): string {
        return fingerprint::policy(\local_mooc_audit\policy::load());
    }
}
