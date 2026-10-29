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
 * Rule X-CONT-01: Recursos vazios (página/rótulo sem conteúdo, questionário sem questões, arquivo sem arquivo).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xcont01 extends base {
    /** @var string */
    const ID = 'X-CONT-01';
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
    const SOURCE = 'Relatório de investigação §6.2';

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
            if (in_array($m['modname'], ['page', 'label', 'book'])) {
                $all = html_entity_decode(implode(' ', $m['texts']));
                if (helpers::text($all) === '' && !preg_match('/<(img|iframe|video|audio|a)\b/i', $all)) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        'sem texto e sem mídia/links',
                        ['cmid' => $m['cmid'], 'orientacao' => $this->guidance(), 'check' => 'empty_content']
                    );
                }
                if ($m['modname'] === 'book' && empty($m['extra']['chapters'])) {
                    $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'livro sem capítulos', ['cmid' => $m['cmid'],
                        'check' => 'book_chapters']);
                }
            }
            if ($m['modname'] === 'quiz' && ($m['extra']['slots'] ?? 0) == 0) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'questionário sem questões', ['cmid' => $m['cmid'],
                    'gravidade' => status::ALTA,
                    'locus' => self::LOCUS_MODULE_VIEW, 'check' => 'quiz_questions']);
            }
            if ($m['modname'] === 'feedback' && ($m['extra']['items'] ?? 0) == 0) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'pesquisa sem itens', ['cmid' => $m['cmid'],
                    'locus' => self::LOCUS_MODULE_VIEW, 'check' => 'feedback_items']);
            }
            if (in_array($m['modname'], ['resource', 'folder'])) {
                $content = array_filter($m['files']['list'], fn($fe) => $fe['filearea'] === 'content');
                if (!$content) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        'recurso de arquivo sem nenhum arquivo associado',
                        ['cmid' => $m['cmid'], 'gravidade' => status::ALTA,
                        'orientacao' => $this->g('reupload'), 'check' => 'files']
                    );
                }
            }
        }
        return $out ?: [$this->f(status::CONFORME, 'Recursos', 'nenhum recurso vazio detectado', ['check' => 'summary'])];
    }
}
