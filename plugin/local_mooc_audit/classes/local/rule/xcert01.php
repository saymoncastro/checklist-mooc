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
 * Rule X-CERT-01: Alcançabilidade estática do certificado.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xcert01 extends base {
    /** @var string */
    const ID = 'X-CERT-01';
    /** @var string */
    const CATEGORY = category::CERTIFICATION;
    /** @var string */
    const ORIGIN = 'extra:6.5';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::BLOQUEANTE;
    /** @var string[] */
    const POLICY_KEYS = ['required_certificate_module', 'licence_certificate_name'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.5';
    /** @var bool Reads completion tracking settings. */
    const REQUIRES_COMPLETION = true;
    /** @var bool Reads access restrictions. */
    const REQUIRES_AVAILABILITY = true;

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        foreach (helpers::certs($s, $p) as $c) {
            $stack = [$c['cmid']];
            $seen = [];
            $chain = [];
            while ($stack) {
                $u = array_pop($stack);
                if (isset($seen[$u])) {
                    continue;
                }
                $seen[$u] = true;
                foreach (snapshot::walk_conditions($s['modules'][$u]['availability'] ?? null) as $cond) {
                    if (($cond['type'] ?? '') === 'completion') {
                        $chain[(int) $cond['cm']] = true;
                        $stack[] = (int) $cond['cm'];
                    }
                }
            }
            $probs = [];
            foreach (array_keys($chain) as $v) {
                $t = $s['modules'][$v] ?? null;
                if (!$t) {
                    $probs[] = "cm $v inexistente";
                } else if ($t['completion'] == 0) {
                    $probs[] = helpers::label($t) . ' sem conclusão';
                } else if ($t['visible'] == 0) {
                    $probs[] = helpers::label($t) . ' oculto';
                } else if ($s['viewer_userid'] && $t['uservisible'] === false) {
                    // Only meaningful for an explicit test viewer: with viewer 0 the snapshot reflects whoever runs
                    // the audit, and an auditor without "ignore availability" would see every restricted link.
                    $probs[] = helpers::label($t) . ' indisponível para o usuário avaliado';
                }
            }
            $types = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                $types[$cond['type']] = ($types[$cond['type']] ?? 0) + 1;
            }
            $out[] = $this->f(
                $probs ? status::NAO_CONFORME : status::CONFORME,
                helpers::label($c),
                'cadeia transitiva de conclusão: ' . count($chain) . ' módulos; tipos de condição: ' . json_encode($types),
                ['cmid' => $c['cmid'], 'encontrado' => $probs ? implode('; ', $probs) : 'cadeia estaticamente satisfazível',
                'orientacao' => $this->guidance(), 'check' => 'chain']
            );
            if ($c['visible'] == 0) {
                $out[] = $this->f(status::NAO_CONFORME, helpers::label($c), 'certificado oculto', ['cmid' => $c['cmid'],
                    'check' => 'hidden']);
            }
        }
        return $out ?: [$this->f(status::NAO_CONFORME, 'Atividades do curso', 'sem certificado de conclusão', [
            'check' => 'certificate_presence'])];
    }
}
