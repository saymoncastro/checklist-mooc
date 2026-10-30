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

namespace local_mooc_audit\local\presentation;

use local_mooc_audit\local\rule\base;
use local_mooc_audit\local\status;

/**
 * What the report says and offers for each verified condition (specs/006, contracts/presentation.md).
 *
 * Maps rule + check + variant to the person responsible, the actions that lead to the Moodle screen where the problem
 * is fixed and the third-party plugin the guidance depends on. The texts live in lang/ (msg_<rule>_<check>_<variant>_*).
 * Presentation only: nothing here changes a rule result.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {
    /** @var string The course editing teacher. */
    const WHO_TEACHER = 'teacher';
    /** @var string The reoffer team (standard room, categories). */
    const WHO_TEAM = 'team';
    /** @var string The site administrator (plugins). */
    const WHO_ADMIN = 'admin';

    /** @var string Plugin of the certificate required by the policy (required_certificate_module). */
    const PLUGIN_CERTIFICATE = 'certificate';
    /** @var string Game module (source of the glossary rule). */
    const PLUGIN_GAME = 'game';
    /** @var string Completion progress block (CL-21). */
    const PLUGIN_PROGRESS = 'progress';

    /** @var array rule => check => variant => [who, actions, plugin?]; see contracts/presentation.md for the actions. */
    const ENTRIES = [
        'CL-04' => ['summary_link' => ['nc' => [self::WHO_TEACHER, ['course_settings']]]],
        'CL-05' => ['category' => ['nc' => [self::WHO_TEAM, ['course_settings']]]],
        'CL-06' => ['startdate' => ['nc' => [self::WHO_TEACHER, ['course_settings']]]],
        'CL-07' => ['enddate' => ['nc' => [self::WHO_TEACHER, ['course_settings']]]],
        'CL-08' => [
            'self_enrol' => ['missing' => [self::WHO_TEACHER, ['enrol_list']], 'nc' => [self::WHO_TEACHER, ['enrol_self']]],
            'guest' => ['at' => [self::WHO_TEACHER, ['enrol_list']]],
        ],
        'CL-09' => [
            'forum_presence' => ['nc' => [self::WHO_TEAM, ['import']]],
            'forum_completion' => ['nc' => [self::WHO_TEACHER, ['cm_completion']]],
        ],
        'CL-11' => [
            'profile_presence' => ['nc' => [self::WHO_TEAM, ['import']]],
            'profile_completion' => ['nc' => [self::WHO_TEACHER, ['cm_completion']]],
            'certificate_presence' => ['nc' => [self::WHO_TEAM, ['import'], self::PLUGIN_CERTIFICATE]],
            'requires_profile' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
        ],
        'CL-12' => [
            'total_sum' => ['nc' => [self::WHO_TEACHER, ['gradebook']]],
            'aggregation' => ['nc' => [self::WHO_TEACHER, ['gradebook']]],
            'onlygraded' => ['nc' => [self::WHO_TEACHER, ['gradebook']]],
            'total_grademax' => ['at' => [self::WHO_TEACHER, ['gradebook']]],
            'subcategories' => ['at' => [self::WHO_TEACHER, ['gradebook']]],
            'course_item' => ['nv' => [self::WHO_TEAM, []]],
            'root_category' => ['nv' => [self::WHO_TEAM, []]],
        ],
        'CL-13' => [
            'attempts' => ['nc' => [self::WHO_TEACHER, ['cm_grade']]],
            'intro_attempts' => ['nc' => [self::WHO_TEACHER, ['cm_settings', 'cm_grade']],
                'at' => [self::WHO_TEACHER, ['cm_settings']]],
        ],
        'CL-14' => ['close_date' => ['nc' => [self::WHO_TEACHER, ['cm_dates']], 'at' => [self::WHO_TEACHER, ['cm_dates']],
            'nv' => [self::WHO_TEACHER, ['cm_settings']]]],
        'CL-16' => [
            'evaluation_presence' => ['nc' => [self::WHO_TEAM, ['import']]],
            'requires_graded' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
            'root_operator' => ['at' => [self::WHO_TEACHER, ['cm_restrict']]],
        ],
        'CL-17' => [
            'completion' => ['nc' => [self::WHO_TEACHER, ['cm_completion']], 'ncoff' => [self::WHO_TEACHER, ['cm_completion']],
                'at' => [self::WHO_TEACHER, ['cm_completion']], 'atlow' => [self::WHO_TEACHER, ['cm_completion']]],
            'summary' => ['at' => [self::WHO_TEACHER, []]],
        ],
        'CL-18' => ['forbidden' => [
            'sourceempty' => [self::WHO_TEACHER, ['mod_view']],
            'source' => [self::WHO_TEACHER, ['mod_view']],
            'nc' => [self::WHO_TEACHER, ['course_page']],
            'nogame' => [self::WHO_ADMIN, []],
        ]],
        'CL-19' => ['completion' => ['nc' => [self::WHO_TEACHER, ['cm_completion']]]],
        'CL-20' => [
            'hiddensections' => ['nc' => [self::WHO_TEACHER, ['course_settings']]],
            'content' => ['empty' => [self::WHO_TEACHER, ['section_edit']], 'labels' => [self::WHO_TEACHER, ['section_edit']]],
            'generic_name' => ['at' => [self::WHO_TEACHER, ['section_edit']]],
            'hidden_completion' => ['at' => [self::WHO_TEACHER, ['section_edit']]],
        ],
        'CL-21' => [
            'progress_block' => ['nc' => [self::WHO_TEACHER, ['course_page'], self::PLUGIN_PROGRESS]],
            'html_block' => ['nc' => [self::WHO_TEACHER, ['course_page']]],
        ],
        'CL-22' => [
            'licence_label' => ['nc' => [self::WHO_TEAM, ['import']]],
            'licence_feedback' => ['nc' => [self::WHO_TEAM, ['import']]],
            'licence_certificate' => ['nc' => [self::WHO_TEAM, ['import'], self::PLUGIN_CERTIFICATE]],
        ],
        'CL-23' => [
            'certificate_presence' => ['nc' => [self::WHO_TEAM, ['import'], self::PLUGIN_CERTIFICATE]],
            'requires_evaluation' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
            'min_grade' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
            'root_operator' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
        ],
        'X-AV-01' => ['intro_grade' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]]],
        'X-CERT-01' => [
            'chain' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]],
            'hidden' => ['nc' => [self::WHO_TEACHER, ['cm_visibility']]],
            'certificate_presence' => ['nc' => [self::WHO_TEAM, ['import'], self::PLUGIN_CERTIFICATE]],
        ],
        'X-CONT-01' => [
            'empty_content' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]],
            'book_chapters' => ['nc' => [self::WHO_TEACHER, ['mod_view']]],
            'quiz_questions' => ['nc' => [self::WHO_TEACHER, ['quiz_questions']]],
            'feedback_items' => ['nc' => [self::WHO_TEACHER, ['mod_view']]],
            'files' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]],
        ],
        'X-CONT-04' => ['pluginfile' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]]],
        'X-DEP-01' => ['missing_target' => ['cm' => [self::WHO_TEACHER, ['cm_restrict']],
            'section' => [self::WHO_TEACHER, ['section_restrict']]]],
        'X-DEP-02' => [
            'target_completion' => ['nc' => [self::WHO_TEACHER, ['subject_completion', 'cm_restrict']],
                'at' => [self::WHO_TEACHER, ['subject_visibility', 'cm_restrict']]],
            'target_grade' => ['nc' => [self::WHO_TEACHER, ['cm_restrict', 'subject_settings']]],
        ],
        'X-DEP-03' => ['grade_item' => ['nc' => [self::WHO_TEACHER, ['cm_restrict']]]],
        'X-DEP-04' => ['cycle' => ['nc' => [self::WHO_TEACHER, ['cycle_restrict']]]],
        'X-INT-02' => ['storage' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]]],
        'X-INT-03' => ['plugin' => ['mod' => [self::WHO_ADMIN, []], 'availability' => [self::WHO_ADMIN, []]]],
        'X-STR-01' => [
            'visibility' => ['at' => [self::WHO_TEACHER, ['cm_visibility']], 'atinfo' => [self::WHO_TEACHER, []]],
            'name' => ['nc' => [self::WHO_TEACHER, ['cm_settings']]],
        ],
    ];

    /** @var array Practical impact of a problem of each rule (FR-041); warnings are always "check". Severity is unchanged. */
    const IMPACT = [
        'CL-04' => 'policy', 'CL-05' => 'offer', 'CL-06' => 'offer', 'CL-07' => 'offer', 'CL-08' => 'offer',
        'CL-09' => 'policy', 'CL-11' => 'certification', 'CL-12' => 'certification', 'CL-13' => 'policy',
        'CL-14' => 'policy', 'CL-16' => 'certification', 'CL-17' => 'policy', 'CL-18' => 'policy', 'CL-19' => 'policy',
        'CL-20' => 'policy', 'CL-21' => 'policy', 'CL-22' => 'policy', 'CL-23' => 'certification',
        'X-AV-01' => 'policy', 'X-CERT-01' => 'certification', 'X-CONT-01' => 'access', 'X-CONT-04' => 'access',
        'X-DEP-01' => 'access', 'X-DEP-02' => 'access', 'X-DEP-03' => 'access', 'X-DEP-04' => 'access',
        'X-INT-02' => 'access', 'X-INT-03' => 'access', 'X-STR-01' => 'access',
    ];

    /** @var array Checks that are not proven facts (FR-040): a reading of the description text or a search by name. */
    const EVIDENCE = [
        'CL-13|intro_attempts' => 'text', 'X-AV-01|intro_grade' => 'text', 'CL-20|generic_name' => 'text',
        'CL-22|licence_label' => 'text', 'CL-09|forum_presence' => 'name', 'CL-11|profile_presence' => 'name',
        'CL-16|evaluation_presence' => 'name', 'CL-22|licence_feedback' => 'name', 'CL-22|licence_certificate' => 'name',
    ];

    /**
     * Impact code of a result (FR-041), or null for states that are not problems nor warnings.
     *
     * @param array $result stored result
     * @return string|null certification, access, offer, policy or check
     */
    public static function impact(array $result): ?string {
        if ($result['status'] === status::ATENCAO) {
            return 'check';
        }
        return $result['status'] === status::NAO_CONFORME ? (self::IMPACT[$result['rule']] ?? 'policy') : null;
    }

    /**
     * Kind of evidence of a check when it is not a proven fact.
     *
     * @param string $rule
     * @param string $check
     * @return string|null text or name
     */
    public static function evidence(string $rule, string $check): ?string {
        return self::EVIDENCE[$rule . '|' . $check] ?? null;
    }

    /**
     * Split an occurrence key rule|entity|check[|subject][#n] (engine::occurrence) into its parts.
     *
     * @param string|null $occurrence
     * @return array rule, entity, check, subject (null when absent)
     */
    public static function parse_occurrence(?string $occurrence): array {
        $key = preg_replace('/#\d+$/', '', (string) $occurrence);
        $parts = explode('|', $key, 4);
        return ['rule' => $parts[0] ?? '', 'entity' => $parts[1] ?? '', 'check' => $parts[2] ?? '',
            'subject' => isset($parts[3]) && $parts[3] !== '' ? $parts[3] : null];
    }

    /**
     * Rule, entity, check and subject of a stored result: the explicit fields of contract 1.1 when present, otherwise
     * the occurrence key (runs recorded with contract 1.0).
     *
     * @param array $result stored result
     * @return array rule, entity, check, subject
     */
    public static function occurrence_of(array $result): array {
        $occ = self::parse_occurrence($result['occurrence'] ?? null);
        if (isset($result['check']) && $result['check'] !== '') {
            $occ['check'] = (string) $result['check'];
            $occ['subject'] = isset($result['subject']) && $result['subject'] !== '' ? (string) $result['subject'] : null;
        }
        return $occ;
    }

    /**
     * Message variant of a stored result, or null when the catalogue has no message for it (the stored text is shown).
     *
     * The discriminators are stored and stable fields (state, severity, locus, subject), never the evidence text.
     *
     * @param array $result stored result (contract 1.0 or 1.1)
     * @param string $check
     * @param string|null $subject
     * @return string|null
     */
    public static function variant(array $result, string $check, ?string $subject): ?string {
        $states = [status::NAO_CONFORME => 'nc', status::ATENCAO => 'at', status::NAO_VERIFICAVEL => 'nv'];
        $variant = $states[$result['status']] ?? null;
        if ($variant === null) {
            return null;
        }
        switch ($result['rule'] . '|' . $check) {
            case 'CL-08|self_enrol':
                $variant = $subject === null ? 'missing' : $variant;
                break;
            case 'CL-17|completion':
                if ($variant === 'at' && ($result['gravidade'] ?? '') === status::BAIXA) {
                    $variant = 'atlow';
                }
                break;
            case 'CL-18|forbidden':
                if (($result['locus'] ?? '') === base::LOCUS_MODULE_VIEW) {
                    $variant = $variant === 'nc' ? 'sourceempty' : 'source';
                }
                break;
            case 'CL-20|content':
                $variant = ($result['orientacao'] ?? '') !== '' && $result['orientacao'] !== null ? 'empty' : 'labels';
                break;
            case 'X-DEP-01|missing_target':
                $variant = ($result['locus'] ?? '') === base::LOCUS_SECTION ? 'section' : 'cm';
                break;
            case 'X-STR-01|visibility':
                $variant = ($result['gravidade'] ?? '') === status::INFORMATIVA ? 'atinfo' : 'at';
                break;
            case 'X-INT-03|plugin':
                $variant = str_starts_with((string) $subject, 'availability:') ? 'availability' : 'mod';
                break;
        }
        return isset(self::ENTRIES[$result['rule']][$check][$variant]) ? $variant : null;
    }

    /**
     * Catalogue entry of a variant.
     *
     * @param string $rule
     * @param string $check
     * @param string $variant
     * @return array|null who, actions, plugin
     */
    public static function entry(string $rule, string $check, string $variant): ?array {
        $e = self::ENTRIES[$rule][$check][$variant] ?? null;
        return $e ? ['who' => $e[0], 'actions' => $e[1], 'plugin' => $e[2] ?? null] : null;
    }

    /**
     * Language-string prefix of a variant: msg_<rule>_<check>_<variant>.
     *
     * @param string $rule
     * @param string $check
     * @param string $variant
     * @return string
     */
    public static function key(string $rule, string $check, string $variant): string {
        return 'msg_' . base::key($rule) . '_' . $check . '_' . $variant;
    }

    /**
     * Frankenstyle component of each plugin the guidance depends on.
     *
     * @param array $policy institutional policy
     * @return array plugin constant => component
     */
    public static function required_plugins(array $policy): array {
        return [
            self::PLUGIN_CERTIFICATE => 'mod_' . ($policy['required_certificate_module'] ?? 'simplecertificate'),
            self::PLUGIN_GAME => 'mod_game',
            self::PLUGIN_PROGRESS => 'block_completion_progress',
        ];
    }

    /**
     * Every (rule, check, variant) of the catalogue, for the coverage test.
     *
     * @return array list of [rule, check, variant]
     */
    public static function all(): array {
        $out = [];
        foreach (self::ENTRIES as $rule => $checks) {
            foreach ($checks as $check => $variants) {
                foreach (array_keys($variants) as $variant) {
                    $out[] = [$rule, $check, $variant];
                }
            }
        }
        return $out;
    }
}
