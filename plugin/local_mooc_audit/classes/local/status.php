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

/**
 * Result states and severities of the audit, with their display order.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {
    /** @var string Requirement met. */
    const CONFORME = 'CONFORME';
    /** @var string Requirement not met (evidence available). */
    const NAO_CONFORME = 'NAO_CONFORME';
    /** @var string Deserves a human look; not a proven failure. */
    const ATENCAO = 'ATENCAO';
    /** @var string The rule does not apply to this course. */
    const NAO_APLICAVEL = 'NAO_APLICAVEL';
    /** @var string Data unavailable or verification requires human action. */
    const NAO_VERIFICAVEL = 'NAO_VERIFICAVEL';
    /** @var string Internal failure of the rule itself. */
    const ERRO = 'ERRO';

    /** @var string Severities. */
    const BLOQUEANTE = 'Bloqueante';
    /** @var string */
    const ALTA = 'Alta';
    /** @var string */
    const MEDIA = 'Média';
    /** @var string */
    const BAIXA = 'Baixa';
    /** @var string */
    const INFORMATIVA = 'Informativa';

    /** @var string Overall status of a course. */
    const OVERALL_BLOCKED = 'BLOQUEADA';
    /** @var string */
    const OVERALL_NEEDS_FIX = 'REQUER_CORRECOES';
    /** @var string */
    const OVERALL_CLEAN = 'SEM_INCONFORMIDADES';

    /**
     * All states, in display order (worst first).
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::NAO_CONFORME, self::ATENCAO, self::ERRO, self::NAO_VERIFICAVEL, self::NAO_APLICAVEL, self::CONFORME];
    }

    /**
     * All severities, in display order (worst first).
     *
     * @return string[]
     */
    public static function severities(): array {
        return [self::BLOQUEANTE, self::ALTA, self::MEDIA, self::BAIXA, self::INFORMATIVA];
    }

    /**
     * Display rank of a state (0 = worst).
     *
     * @param string|null $status
     * @return int
     */
    public static function status_rank(?string $status): int {
        $i = array_search($status, self::all(), true);
        return $i === false ? 9 : $i;
    }

    /**
     * Display rank of a severity (0 = worst).
     *
     * @param string|null $severity
     * @return int
     */
    public static function severity_rank(?string $severity): int {
        $i = array_search($severity, self::severities(), true);
        return $i === false ? 9 : $i;
    }

    /**
     * Language string key of a state.
     *
     * @param string $status
     * @return string
     */
    public static function string_key(string $status): string {
        return 'status_' . strtolower($status);
    }

    /**
     * Language string key of a severity.
     *
     * @param string $severity
     * @return string
     */
    public static function severity_string_key(string $severity): string {
        $map = [self::BLOQUEANTE => 'blocking', self::ALTA => 'high', self::MEDIA => 'medium', self::BAIXA => 'low',
            self::INFORMATIVA => 'info'];
        return 'severity_' . ($map[$severity] ?? 'unknown');
    }
}
