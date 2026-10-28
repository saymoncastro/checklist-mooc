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

namespace local_mooc_audit\local\rule;

use local_mooc_audit\local\status;

/**
 * Base class of the rules: metadata through class constants and the finding builder.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements rule_interface {

    /** @var string Rule type: depends on an institutional decision (parameters in the policy). */
    const TYPE_INSTITUTIONAL = 'institucional';
    /** @var string Rule type: universal technical check. */
    const TYPE_TECHNICAL = 'tecnica';

    /** @var string Locus kinds used by findings (see local\locator). */
    const LOCUS_MODULE_EDIT = 'module_edit';
    /** @var string */
    const LOCUS_MODULE_VIEW = 'module_view';
    /** @var string */
    const LOCUS_COURSE_SETTINGS = 'course_settings';
    /** @var string */
    const LOCUS_ENROL = 'enrol';
    /** @var string */
    const LOCUS_GRADEBOOK = 'gradebook';
    /** @var string */
    const LOCUS_BLOCKS = 'blocks';
    /** @var string */
    const LOCUS_SECTION = 'section';
    /** @var string */
    const LOCUS_NONE = 'none';

    /** @var string Stable id. */
    const ID = '';
    /** @var string Category (local\category constant). */
    const CATEGORY = '';
    /** @var string Origin: checklist:n or extra:section. */
    const ORIGIN = '';
    /** @var string Type. */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string Default severity. */
    const SEVERITY = status::MEDIA;
    /** @var string[] Policy keys read by the rule. */
    const POLICY_KEYS = [];
    /** @var string Default locus of the findings. */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string Normative or technical source. */
    const SOURCE = '';

    /**
     * Stable id.
     *
     * @return string
     */
    public function id(): string {
        return static::ID;
    }

    /**
     * Language-string key fragment of a rule id (CL-04 → cl04, X-INT-02 → xint02).
     *
     * @param string $id
     * @return string
     */
    public static function key(string $id): string {
        return strtolower(str_replace('-', '', $id));
    }

    /**
     * Metadata.
     *
     * @return array
     */
    public function meta(): array {
        $key = self::key(static::ID);
        return [
            'id' => static::ID,
            'title' => get_string("rule_{$key}_title", 'local_mooc_audit'),
            'category' => static::CATEGORY,
            'origin' => static::ORIGIN,
            'type' => static::TYPE,
            'severity' => static::SEVERITY,
            'policy_keys' => static::POLICY_KEYS,
            'locus' => static::LOCUS,
            'guidance' => get_string("rule_{$key}_guidance", 'local_mooc_audit'),
            'source' => static::SOURCE,
        ];
    }

    /**
     * Build a finding.
     *
     * @param string $status one of the status constants
     * @param string|null $local human readable location
     * @param string|null $evidencia evidence
     * @param array $extra encontrado, esperado, orientacao, gravidade, cmid, locus, section
     * @return array
     */
    protected function f(string $status, ?string $local = null, ?string $evidencia = null, array $extra = []): array {
        return array_merge(['status' => $status, 'local' => $local, 'evidencia' => $evidencia, 'encontrado' => null,
            'esperado' => null, 'orientacao' => null, 'gravidade' => null, 'cmid' => null, 'locus' => null, 'section' => null], $extra);
    }

    /**
     * Default guidance of this rule (lang key rule_<key>_guidance).
     *
     * @return string
     */
    protected function guidance(): string {
        return get_string('rule_' . self::key(static::ID) . '_guidance', 'local_mooc_audit');
    }

    /**
     * A specific guidance string of this rule (lang key rule_<key>_g_<name>).
     *
     * @param string $name
     * @param mixed $a placeholder data
     * @return string
     */
    protected function g(string $name, $a = null): string {
        return get_string('rule_' . self::key(static::ID) . "_g_$name", 'local_mooc_audit', $a);
    }
}
