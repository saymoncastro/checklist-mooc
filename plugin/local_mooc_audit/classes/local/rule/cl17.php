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
 * Rule CL-17: Conclusão automática com critério adequado ao tipo.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl17 extends base {
    /** @var string */
    const ID = 'CL-17';
    /** @var string */
    const CATEGORY = category::COMPLETION_RESTRICTIONS;
    /** @var string */
    const ORIGIN = 'checklist:17';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['completion_expected'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 17; decisão institucional 5 (critério por tipo)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $counts = [0 => 0, 1 => 0, 2 => 0];
        foreach ($s['modules'] as $m) {
            $counts[$m['completion']] = ($counts[$m['completion']] ?? 0) + 1;
            $exp = $p['completion_expected'][$m['modname']] ?? null;
            if ($m['completion'] == 1) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($m), 'conclusão MANUAL', ['cmid' => $m['cmid'],
                    'esperado' => 'automática (ou nenhuma, para rótulos/fóruns institucionais)',
                    'orientacao' => $this->guidance()]);
                continue;
            }
            if ($m['completion'] == 2) {
                $x = $m['extra'];
                $crit = [];
                if ($m['completionview']) {
                    $crit[] = 'view';
                }
                if ($m['completiongradeitemnumber'] !== null) {
                    $crit[] = 'grade';
                }
                foreach (
                    ['completionsubmit' => 'submit', 'completionentries' => 'entries', 'completionposts' => 'posts',
                        'completiondiscussions' => 'posts',
                    'completionreplies' => 'posts', 'completionminattempts' => 'attempts',
                        'completionattemptsexhausted' => 'attempts', 'completionpass' => 'grade'] as $k => $v
                ) {
                    if (!empty($x[$k])) {
                        $crit[] = $v;
                    }
                }
                $crit = array_values(array_unique($crit));
                if (!$crit) {
                    $out[] = $this->f(
                        status::ATENCAO,
                        helpers::label($m),
                        'conclusão automática sem nenhum critério',
                        ['cmid' => $m['cmid']]
                    );
                } else if (
                    $exp && $exp !== 'none' && !in_array(explode('_', $exp)[0], $crit) && !($exp === 'posts_or_none'
                        && in_array('posts', $crit))
                        && !($exp === 'submit_or_grade' && (in_array('submit', $crit) || in_array('grade', $crit)))
                ) {
                    $out[] = $this->f(
                        status::ATENCAO,
                        helpers::label($m),
                        'critérios=' . implode('+', $crit),
                        ['cmid' => $m['cmid'], 'esperado' => "critério '$exp' para {$m['modname']}",
                        'gravidade' => status::BAIXA,
                        'orientacao' => $this->g('criteria')]
                    );
                }
                if ($exp === 'none' && $m['modname'] === 'label') {
                    // Reported by CL-19.
                    continue;
                }
            }
        }
        array_unshift($out, $this->f(
            $counts[1] ? status::ATENCAO : status::CONFORME,
            'Conclusão de atividades',
            "nenhuma={$counts[0]} manual={$counts[1]} automática={$counts[2]}",
            ['gravidade' => status::INFORMATIVA]
        ));
        return $out;
    }
}
