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
use local_mooc_audit\snapshot;
use local_mooc_audit\local\category;

/**
 * Rule CL-23: Certificado condicionado à Avaliação do curso e à nota mínima no total.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl23 extends base {
    /** @var string */
    const ID = 'CL-23';
    /** @var string */
    const CATEGORY = category::CERTIFICATION;
    /** @var string */
    const ORIGIN = 'checklist:23';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::BLOQUEANTE;
    /** @var string[] */
    const POLICY_KEYS = ['certificate_min_grade', 'required_feedback_evaluation', 'required_certificate_module'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 23; availability/condition/grade';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $certs = helpers::certs($s, $p);
        if (!$certs) {
            return [$this->f(status::NAO_CONFORME, 'Atividades do curso', 'nenhum certificado de conclusão (' . $p['required_certificate_module'] . ')', ['orientacao' => $this->g('import'), 'locus' => self::LOCUS_IMPORT])];
        }
        $ev = helpers::mods($s, 'feedback', $p['required_feedback_evaluation']);
        $ci = $s['grade']['course_item'];
        $out = [];
        foreach ($certs as $c) {
            $comp = [];
            $grades = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                if (($cond['type'] ?? '') === 'completion') {
                    $comp[] = (int) $cond['cm'];
                }
                if (($cond['type'] ?? '') === 'grade') {
                    $grades[] = $cond;
                }
            }
            $okev = $ev && in_array($ev[0]['cmid'], $comp);
            $out[] = $this->f($okev ? status::CONFORME : status::NAO_CONFORME, helpers::label($c), 'exige conclusão de cmids ' . json_encode($comp),
                ['cmid' => $c['cmid'], 'esperado' => 'exigir a Avaliação do curso' . ($ev ? " (cm={$ev[0]['cmid']})" : ' (inexistente no curso)')]);
            $g60 = array_values(array_filter($grades, fn($g) => isset($g['min']) && (float) $g['min'] >= (float) $p['certificate_min_grade']));
            if (!$g60) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($c), 'condições de nota=' . json_encode($grades), ['cmid' => $c['cmid'], 'esperado' => 'nota ≥ ' . $p['certificate_min_grade'] . ' no total do curso']);
            } else if ($ci && (int) $g60[0]['id'] !== (int) $ci['id']) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($c), 'condição de nota aponta para o item ' . $g60[0]['id'], ['cmid' => $c['cmid'], 'esperado' => "item Total do curso (id {$ci['id']})",
                    'orientacao' => $this->g('courseitem')]);
            } else {
                $out[] = $this->f(status::CONFORME, helpers::label($c), 'nota ≥ ' . $g60[0]['min'] . ' no Total do curso (item ' . $g60[0]['id'] . ')', ['cmid' => $c['cmid']]);
            }
            if (($c['availability']['op'] ?? '&') !== '&') {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($c), 'operador raiz=' . $c['availability']['op'], ['cmid' => $c['cmid'], 'esperado' => '&']);
            }
        }
        return $out;
        }
}
