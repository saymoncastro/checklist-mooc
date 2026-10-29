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
 * Rule CL-21: Blocos padrão presentes na página do curso.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl21 extends base {
    /** @var string */
    const ID = 'CL-21';
    /** @var string */
    const CATEGORY = category::STRUCTURE_BLOCKS;
    /** @var string */
    const ORIGIN = 'checklist:21';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['required_blocks'];
    /** @var string */
    const LOCUS = self::LOCUS_BLOCKS;
    /** @var string */
    const SOURCE = 'Checklist item 21; decisão institucional 8 (bloco Acessibilidade)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $names = array_map(fn($b) => $b['name'] . ($b['inherited'] ? '(site/categoria)' : ''), $s['blocks']);
        $titles = array_map(fn($b) => helpers::norm($b['title']), $s['blocks']);
        $hasprogress = (bool) array_filter($s['blocks'], fn($b) => $b['name'] === 'completion_progress');
        $out[] = $this->f(
            $hasprogress ? status::CONFORME : status::NAO_CONFORME,
            'Blocos da página do curso',
            'blocos=' . json_encode($names, JSON_UNESCAPED_UNICODE) . ' (fonte: ' . $s['blocks_source'] . ')',
            ['esperado' => 'bloco Progresso de Conclusão presente', 'check' => 'progress_block']
        );
        foreach ($p['required_blocks']['html'] as $t) {
            $ok = (bool) array_filter($titles, fn($x) => strpos($x, helpers::norm($t)) !== false);
            $out[] = $this->f(
                $ok ? status::CONFORME : status::NAO_CONFORME,
                'Blocos da página do curso',
                "bloco \"$t\": " . ($ok ? 'presente' : 'ausente'),
                ['orientacao' => $ok ? null : $this->guidance(), 'check' => 'html_block', 'subject' => helpers::norm($t)]
            );
        }
        return $out;
    }
}
