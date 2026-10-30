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
 * Last recorded audit of a course, read-only (decision 12.1 of specs/005): the audit page and the external API
 * (specs/004) show the same result, with the same reasons why it may be outdated.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class latest {
    /**
     * The last recorded audit of a course and what surrounds it. Nothing is run nor written.
     *
     * @param \stdClass $course course record (cacherev and timemodified are compared with the run)
     * @param int|null $now
     * @param bool $withpending look for the alive pending run (reads the adhoc task queue)
     * @return array ['valid' => last complete or partial run (with its policy fingerprint) or null,
     *     'stale' => string[] reasons (dashboard::stale_reasons()), 'lastfailure' => newer failed run or null,
     *     'pending' => alive queued or running run or null (always null without $withpending)]
     */
    public static function for_course(\stdClass $course, ?int $now = null, bool $withpending = true): array {
        $now = $now ?? time();
        $courseid = (int) $course->id;
        $valid = run_store::latest_valid($courseid);
        $stale = [];
        if ($valid) {
            $valid->fingerprint = $valid->policyid ? (policy_store::get((int) $valid->policyid)->fingerprint ?? null) : null;
            $stale = dashboard::stale_reasons(
                $valid,
                $now,
                policy_store::current_fingerprint(),
                run_store::plugin_version(),
                $course
            );
        }
        $lastfinal = run_store::latest_per_course(run_store::final_states(), [$courseid])[$courseid] ?? null;
        $lastfailure = $lastfinal && $lastfinal->status === run_store::FAILED
            && (!$valid || (int) $lastfinal->id !== (int) $valid->id) ? $lastfinal : null;
        return [
            'valid' => $valid,
            'stale' => $stale,
            'lastfailure' => $lastfailure,
            'pending' => $withpending ? run_store::reusable_pending($courseid, $now) : null,
        ];
    }
}
