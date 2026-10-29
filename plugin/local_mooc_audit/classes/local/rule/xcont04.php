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
 * Rule X-CONT-04: Referências @@PLUGINFILE@@ sem arquivo correspondente.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xcont04 extends base {
    /** @var string */
    const ID = 'X-CONT-04';
    /** @var string */
    const CATEGORY = category::CONTENT_FILES;
    /** @var string */
    const ORIGIN = 'extra:6.2';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.2 (falso positivo com ?time= corrigido)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            $paths = [];
            foreach ($m['files']['list'] as $fe) {
                $paths[($fe['filepath'] ?: '/') . $fe['filename']] = true;
            }
            foreach ($m['texts'] as $field => $t) {
                if (!preg_match_all('~@@PLUGINFILE@@(/[^"\'\s)<>]+)~', html_entity_decode((string) $t), $mm)) {
                    continue;
                }
                foreach (array_unique($mm[1]) as $ref) {
                    $path = rawurldecode(preg_replace('/[?#].*$/', '', $ref));
                    if (!isset($paths[$path])) {
                        $out[] = $this->f(
                            status::NAO_CONFORME,
                            helpers::label($m) . " campo $field",
                            "@@PLUGINFILE@@$ref não encontrado entre os arquivos do módulo",
                            ['cmid' => $m['cmid'], 'orientacao' => $this->guidance(), 'check' => 'pluginfile',
                                'subject' => "$field:$path"]
                        );
                    }
                }
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Conteúdos', 'todas as referências @@PLUGINFILE@@ resolvidas', [
            'check' => 'summary'])];
    }
}
