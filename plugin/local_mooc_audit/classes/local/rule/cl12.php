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
 * Rule CL-12: Livro de notas: soma 100, agregação Natural, sem excluir vazias.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl12 extends base {
    /** @var string */
    const ID = 'CL-12';
    /** @var string */
    const CATEGORY = category::ASSESSMENT_GRADES;
    /** @var string */
    const ORIGIN = 'checklist:12';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = ['course_total_max', 'required_aggregation', 'aggregateonlygraded_allowed'];
    /** @var string */
    const LOCUS = self::LOCUS_GRADEBOOK;
    /** @var string */
    const SOURCE = 'Checklist item 12; decisão institucional 2; availability/condition/grade (percentual sobre rawgrademax)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $graded = helpers::graded($s);
        $total = 0.0;
        $det = [];
        foreach ($graded as [$m, $gi]) {
            $total += $gi['grademax'];
            $det[] = "{$m['modname']}:{$m['cmid']}=" . rtrim(rtrim(number_format($gi['grademax'], 2, '.', ''), '0'), '.');
        }
        $ok = abs($total - (float) $p['course_total_max']) < 0.001;
        $out[] = $this->f(
            $ok ? status::CONFORME : status::NAO_CONFORME,
            'Livro de notas > Configurações',
            'soma dos máximos=' . $total . ' itens=' . implode(', ', $det),
            ['esperado' => (string) $p['course_total_max'], 'orientacao' => $this->guidance()]
        );
        $ci = $s['grade']['course_item'];
        if (!$ci) {
            $out[] = $this->f(status::NAO_VERIFICAVEL, 'Livro de notas', 'item de total do curso não encontrado');
            return $out;
        }
        $root = $s['grade']['categories'][$ci['iteminstance']] ?? null;
        if ($root) {
            if ((int) $root['aggregation'] !== (int) $p['required_aggregation']) {
                $out[] = $this->f(
                    status::NAO_CONFORME,
                    'Livro de notas > categoria raiz',
                    "aggregation={$root['aggregation']}",
                    ['esperado' => $p['required_aggregation'] . ' (Natural)',
                    'orientacao' => $this->g('aggregation')]
                );
            } else {
                $out[] = $this->f(status::CONFORME, 'Livro de notas > categoria raiz', 'aggregation=13 (Natural)');
            }
            if ($root['aggregateonlygraded'] && !$p['aggregateonlygraded_allowed']) {
                $out[] = $this->f(
                    status::NAO_CONFORME,
                    'Livro de notas > categoria raiz',
                    'aggregateonlygraded=1 (excluir notas vazias)',
                    ['esperado' => '0',
                    'orientacao' => $this->g('onlygraded')]
                );
            } else {
                $out[] = $this->f(status::CONFORME, 'Livro de notas > categoria raiz', 'aggregateonlygraded=0');
            }
        }
        if (abs($ci['grademax'] - (float) $p['course_total_max']) > 0.001) {
            $out[] = $this->f(
                status::ATENCAO,
                'Livro de notas > Total do curso',
                "grademax do total={$ci['grademax']}",
                ['esperado' => (string) $p['course_total_max']]
            );
        }
        $sub = array_filter($graded, fn($x) => $x[1]['categoryid'] != $ci['iteminstance']);
        if ($sub) {
            $out[] = $this->f(
                status::ATENCAO,
                'Livro de notas',
                count($sub) . ' itens em subcategorias',
                ['orientacao' => $this->g('subcats')]
            );
        }
        return $out;
    }
}
