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
 * Rule CL-11: Pesquisa de Perfil presente e exigida pelo certificado.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl11 extends base {
    /** @var string */
    const ID = 'CL-11';
    /** @var string */
    const CATEGORY = category::INSTITUTIONAL_RESOURCES;
    /** @var string */
    const ORIGIN = 'checklist:11';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = ['required_feedback_profile', 'required_certificate_module', 'licence_certificate_name'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 11; decisão institucional 10';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $prof = helpers::mods($s, 'feedback', $p['required_feedback_profile']);
        if (!$prof) {
            return [$this->f(
                status::NAO_CONFORME,
                'Atividades do curso',
                'nenhuma Pesquisa (feedback) ~ "' . $p['required_feedback_profile'] . '"',
                ['orientacao' => $this->g('import'), 'locus' => self::LOCUS_IMPORT]
            )];
        }
        $pm = $prof[0];
        $out = [$this->f(status::CONFORME, helpers::label($pm), 'presente', ['cmid' => $pm['cmid']])];
        if ($pm['completion'] != 2 || ($pm['extra']['completionsubmit'] ?? 0) != 1) {
            $out[] = $this->f(
                status::NAO_CONFORME,
                helpers::label($pm),
                "completion={$pm['completion']} completionsubmit=" . ($pm['extra']['completionsubmit'] ?? 'n/a'),
                ['cmid' => $pm['cmid'], 'esperado' => 'conclusão automática ao enviar', 'orientacao' => $this->g('completion')]
            );
        }
        $certs = helpers::certs($s, $p);
        if (!$certs) {
            $out[] = $this->f(
                status::NAO_CONFORME,
                'Atividades do curso',
                'nenhum certificado de conclusão',
                ['locus' => self::LOCUS_IMPORT]
            );
        }
        foreach ($certs as $c) {
            $cms = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                if (($cond['type'] ?? '') === 'completion') {
                    $cms[] = (int) $cond['cm'];
                }
            }
            $ok = in_array($pm['cmid'], $cms);
            $out[] = $this->f(
                $ok ? status::CONFORME : status::NAO_CONFORME,
                helpers::label($c),
                'restrições de conclusão: cmids ' . json_encode($cms),
                ['cmid' => $c['cmid'], 'esperado' => "exigir conclusão de cm={$pm['cmid']} (Pesquisa de Perfil)",
                'orientacao' => $this->guidance()]
            );
        }
        return $out;
    }
}
