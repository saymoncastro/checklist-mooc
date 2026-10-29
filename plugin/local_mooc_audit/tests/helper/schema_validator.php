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

namespace local_mooc_audit\tests;

/**
 * Minimal JSON Schema validator for the contracts of the plugin (report 1.0, comparison 1.0), without external library.
 *
 * Covers only the subset the contracts use: type, const, enum, pattern, required, properties, additionalProperties,
 * items, $ref to $defs, allOf (plain or if/then), minLength.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema_validator {
    /** @var array Decoded root schema (target of $ref). */
    private array $schema;

    /**
     * Constructor.
     *
     * @param string $file path of the schema
     */
    public function __construct(string $file) {
        $this->schema = json_decode(file_get_contents($file), true);
    }

    /**
     * The decoded schema.
     *
     * @return array
     */
    public function schema(): array {
        return $this->schema;
    }

    /**
     * Errors of a value against the whole schema.
     *
     * @param mixed $value decoded JSON
     * @return string[]
     */
    public function errors($value): array {
        $errors = [];
        $this->validate($value, $this->schema, '$', $errors);
        return $errors;
    }

    /**
     * Validate a value against a (sub)schema, collecting errors with their JSON path.
     *
     * @param mixed $value decoded JSON (arrays for objects and lists)
     * @param array $schema
     * @param string $path
     * @param array $errors by reference
     */
    public function validate($value, array $schema, string $path, array &$errors): void {
        if (isset($schema['$ref'])) {
            $ref = explode('/', ltrim($schema['$ref'], '#/'));
            $target = $this->schema;
            foreach ($ref as $k) {
                $target = $target[$k];
            }
            $this->validate($value, $target, $path, $errors);
            return;
        }
        if (isset($schema['const']) && $value !== $schema['const']) {
            $errors[] = "$path: expected const " . json_encode($schema['const']) . ', got ' . json_encode($value);
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "$path: " . json_encode($value) . ' not in enum';
        }
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $ok = false;
            foreach ($types as $t) {
                $ok = $ok || match ($t) {
                    'string' => is_string($value),
                    'integer' => is_int($value),
                    'number' => is_int($value) || is_float($value),
                    'boolean' => is_bool($value),
                    'null' => $value === null,
                    'array' => is_array($value) && array_is_list($value),
                    'object' => is_array($value) && (!array_is_list($value) || $value === []),
                    default => false,
                };
            }
            if (!$ok) {
                $errors[] = "$path: type " . json_encode($value) . ' is not ' . implode('|', $types);
                return;
            }
        }
        if (
            isset($schema['pattern']) && is_string($value) && !preg_match('/' . str_replace('/', '\/', $schema['pattern'])
            . '/u', $value)
        ) {
            $errors[] = "$path: \"$value\" does not match {$schema['pattern']}";
        }
        if (isset($schema['minLength']) && is_string($value) && mb_strlen($value) < $schema['minLength']) {
            $errors[] = "$path: shorter than {$schema['minLength']}";
        }
        if (is_array($value)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) {
                    $errors[] = "$path: missing required \"$key\"";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $sub) {
                if (array_key_exists($key, $value)) {
                    $this->validate($value[$key], $sub, "$path.$key", $errors);
                }
            }
            if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                foreach ($value as $key => $v) {
                    if (!isset($schema['properties'][$key])) {
                        $this->validate($v, $schema['additionalProperties'], "$path.$key", $errors);
                    }
                }
            }
            if (isset($schema['items']) && array_is_list($value)) {
                foreach ($value as $i => $v) {
                    $this->validate($v, $schema['items'], "{$path}[$i]", $errors);
                }
            }
        }
        foreach ($schema['allOf'] ?? [] as $sub) {
            if (isset($sub['if'])) {
                $cond = [];
                $this->validate($value, $sub['if'], $path, $cond);
                if (!$cond && isset($sub['then'])) {
                    $this->validate($value, $sub['then'], $path, $errors);
                }
            } else {
                $this->validate($value, $sub, $path, $errors);
            }
        }
    }
}
