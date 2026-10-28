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

use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;
use local_mooc_audit\local\category;

/**
 * Rule X-INT-02: Arquivos registrados sem conteúdo no armazenamento do Moodle.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xint02 extends base {
    /** @var string */
    const ID = 'X-INT-02';
    /** @var string */
    const CATEGORY = category::CONTENT_FILES;
    /** @var string */
    const ORIGIN = 'extra:6.1';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.1; file_system::is_file_readable_*';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $total = 0;
        foreach ($s['modules'] as $m) {
            $total += $m['files']['count'];
            foreach ($m['files']['missing_in_storage'] as $fe) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), "arquivo \"{$fe['filename']}\" ({$fe['component']}/{$fe['filearea']}, {$fe['filesize']} bytes" . ($fe['is_reference'] ? ', alias' : '') . ') registrado mas sem conteúdo legível no armazenamento',
                    ['cmid' => $m['cmid'], 'orientacao' => $this->guidance()]);
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Arquivos dos módulos', "$total arquivos com conteúdo legível no armazenamento")];
        }
}
