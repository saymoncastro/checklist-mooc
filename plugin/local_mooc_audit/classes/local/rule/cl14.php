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
 * Rule CL-14: Atividades avaliativas com data de encerramento.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl14 extends base {
    /** @var string */
    const ID = 'CL-14';
    /** @var string */
    const CATEGORY = category::ASSESSMENT_GRADES;
    /** @var string */
    const ORIGIN = 'checklist:14';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 14';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $ed = $s['course']['enddate'];
        foreach (helpers::graded($s) as [$m, $gi]) {
            $x = $m['extra'];
            $close = null;
            $known = true;
            switch ($m['modname']) {
                case 'quiz':
                case 'game':
                case 'choice':
                case 'feedback':
                    $close = $x['timeclose'] ?? 0;
                    break;
                case 'assign':
                case 'forum':
                    $close = ($x['cutoffdate'] ?? 0) ?: ($x['duedate'] ?? 0);
                    break;
                case 'data':
                    $close = $x['timeavailableto'] ?? 0;
                    break;
                default:
                    $known = false;
            }
            if (!$known) {
                $out[] = $this->f(
                    status::NAO_VERIFICAVEL,
                    helpers::label($m),
                    'tipo sem campo de encerramento conhecido',
                    ['cmid' => $m['cmid'], 'gravidade' => status::INFORMATIVA]
                );
            } else if (!$close) {
                $out[] = $this->f(
                    status::NAO_CONFORME,
                    helpers::label($m),
                    'data de encerramento não definida',
                    ['cmid' => $m['cmid'], 'orientacao' => $this->guidance()]
                );
            } else {
                $note = ($ed && $close != $ed) ? ' (≠ fim do curso ' . helpers::d($ed) . ')' : '';
                $out[] = $this->f($note ? status::ATENCAO : status::CONFORME, helpers::label($m), 'encerramento='
                    . helpers::d($close) . $note, ['cmid' => $m['cmid'], 'gravidade' => $note ? status::BAIXA : null]);
            }
        }
        return $out ?: [$this->f(status::NAO_APLICAVEL, 'Atividades avaliativas', 'nenhuma')];
    }
}
