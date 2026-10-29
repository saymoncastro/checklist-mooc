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
 * Institutional policy: everything that is a Cefor/Ifes decision rather than a universal technical check.
 *
 * Defaults mirror DECISOES-INSTITUCIONAIS-CHECKLIST.md (2026-10-28). Any key can be overridden through the
 * admin setting local_mooc_audit/policyjson (JSON object) or by passing a JSON file to the CLI.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy {
    /** @var string Version label of the default policy shipped with the plugin. */
    const DEFAULT_VERSION = '2027-decisoes-2026-10-28';

    /**
     * Default policy values.
     *
     * @return array
     */
    public static function defaults(): array {
        return [
            'policy_version' => self::DEFAULT_VERSION,
            // Null = year following the reference date (the reoffer process runs in Oct/Nov for the next year).
            'reoffer_year' => null,
            'reference_timestamp' => null,
            // Decisions 3, 6 and 7: new rooms start 01/01 and end 31/12 of the reoffer year.
            'required_start_date' => '01/01',
            'allowed_end_dates' => ['31/12'],
            'min_days_between_start_and_end' => 30,
            // Offer settings are read from the live room, never from a backup.
            'target_category_name' => 'Inscrições Abertas',
            // Decision 1: at least two attempts (0 = unlimited accepted).
            'min_quiz_attempts' => 2,
            'attempts_rule' => 'min',
            // Decision 2: natural aggregation, empty grades NOT excluded, total 100.
            'course_total_max' => 100.0,
            'required_aggregation' => 13,
            'aggregateonlygraded_allowed' => false,
            'certificate_min_grade' => 60,
            // Decision 4: collaborative glossary forbidden; glossary used only as game source tolerated.
            'forbidden_modules' => ['glossary'],
            // Decision 10 (pending confirmation): canonical names observed in the 2026 courses.
            'required_forum_name' => 'Espaço de interação',
            'required_feedback_profile' => 'Pesquisa de Perfil',
            'required_feedback_evaluation' => 'Avaliação do curso',
            'required_certificate_module' => 'simplecertificate',
            'licence_label_text' => 'Licença para capacitação',
            'licence_feedback_name' => 'Licença para capacitação',
            'licence_certificate_name' => 'Declaração licença para capacitação',
            // Decision 8: the block must be present in the room (site-level blocks are visible to the plugin).
            'required_blocks' => [
                'completion_progress' => null,
                'html' => ['Atenção ao prazo', 'Dúvidas técnicas', 'Acessibilidade'],
            ],
            'summary_required_links' => [
                'https://mooc.cefor.ifes.edu.br/moodle/login/signup.php',
                'https://mooc.cefor.ifes.edu.br/moodle/course/index.php?categoryid=7',
            ],
            // Decision 5: expected automatic completion criterion per module type.
            'completion_expected' => [
                'page' => 'view', 'book' => 'view', 'url' => 'view', 'resource' => 'view', 'folder' => 'view',
                'quiz' => 'grade', 'game' => 'grade', 'hvp' => 'grade', 'assign' => 'submit_or_grade',
                'feedback' => 'submit', 'choice' => 'submit', 'data' => 'entries', 'forum' => 'posts_or_none',
                'label' => 'none', 'simplecertificate' => 'none',
            ],
            'generic_section_names' => '/^(topic|tópico|topico|seção|secao|section)\s*\d+$/u',
        ];
    }

    /**
     * Load defaults merged with the admin setting and an optional extra override.
     *
     * @param array|null $override extra values (e.g. decoded from a CLI JSON file)
     * @return array
     */
    public static function load(?array $override = null): array {
        $policy = self::defaults();
        $warnings = [];
        $json = trim((string) get_config('local_mooc_audit', 'policyjson'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $policy = array_merge($policy, self::typed($decoded, $warnings));
                $policy['policy_version'] = ($decoded['policy_version'] ?? $policy['policy_version']) . '+admin';
            } else {
                $warnings[] = get_string('policy_invalid', 'local_mooc_audit', json_last_error_msg());
            }
        }
        if ($override) {
            $policy = array_merge($policy, self::typed($override, $warnings));
        }
        $policy['warnings'] = $warnings;
        $ref = $policy['reference_timestamp'] ?: time();
        if (empty($policy['reoffer_year'])) {
            $policy['reoffer_year'] = (int) date('Y', $ref) + 1;
        }
        $policy['reference_timestamp'] = $ref;
        return $policy;
    }

    /**
     * Drop override values whose type differs from the default's type (array vs scalar), recording a warning.
     * Unknown keys are kept (a newer policy may carry keys this version ignores); null is accepted for keys that
     * default to null.
     *
     * @param array $values
     * @param array $warnings by reference
     * @return array
     */
    protected static function typed(array $values, array &$warnings): array {
        $defaults = self::defaults();
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, $defaults) || $defaults[$k] === null || $v === null) {
                continue;
            }
            if (is_array($defaults[$k]) !== is_array($v)) {
                $warnings[] = get_string('policy_badtype', 'local_mooc_audit', $k);
                unset($values[$k]);
            }
        }
        return $values;
    }
}
