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
 * Rule CL-13: Questionários com ao menos duas tentativas (e enunciado coerente).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl13 extends base {
    /** @var string */
    const ID = 'CL-13';
    /** @var string */
    const CATEGORY = category::ASSESSMENT_GRADES;
    /** @var string */
    const ORIGIN = 'checklist:13';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = ['min_quiz_attempts', 'attempts_rule'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 13; decisão institucional 1 (mínimo de 2 tentativas)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach (helpers::mods($s, 'quiz') as $m) {
            $a = (int) ($m['extra']['attempts'] ?? 0);
            $ok = $p['attempts_rule'] === 'exact' ? $a === (int) $p['min_quiz_attempts'] : ($a === 0
                || $a >= (int) $p['min_quiz_attempts']);
            $out[] = $this->f(
                $ok ? status::CONFORME : status::NAO_CONFORME,
                helpers::label($m),
                "tentativas permitidas=$a",
                ['cmid' => $m['cmid'], 'esperado' => ($p['attempts_rule'] === 'exact' ? '= ' : '≥ ') . $p['min_quiz_attempts']
                    . ' (0 = ilimitadas)',
                'orientacao' => $this->guidance()]
            );
            $intro = helpers::norm(helpers::text($m['texts']['intro'] ?? ''));
            if (preg_match('/tentativas?\s*(?:permitidas?)?\s*[:\-]?\s*(\d+)/u', $intro, $mm)) {
                if ((int) $mm[1] !== $a) {
                    $out[] = $this->f(
                        status::NAO_CONFORME,
                        helpers::label($m),
                        "enunciado declara {$mm[1]} tentativas; configurado=$a",
                        ['cmid' => $m['cmid'],
                        'orientacao' => $this->g('intro')]
                    );
                }
            } else if ($intro !== '') {
                $out[] = $this->f(
                    status::ATENCAO,
                    helpers::label($m),
                    'enunciado não menciona o número de tentativas',
                    ['cmid' => $m['cmid'], 'gravidade' => status::BAIXA]
                );
            }
        }
        return $out ?: [$this->f(status::NAO_APLICAVEL, 'Questionários', 'curso sem questionários')];
    }
}
