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
 * Rule CL-08: Autoinscrição habilitada com período correto.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl08 extends base {
    /** @var string */
    const ID = 'CL-08';
    /** @var string */
    const CATEGORY = category::OFFER_ENROL;
    /** @var string */
    const ORIGIN = 'checklist:8';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_ENROL;
    /** @var string */
    const SOURCE = 'Checklist item 8; decisão institucional 3';
    /** @var bool Depends on the dates/category/enrolment of the offer (specs/003). */
    const OFFER_CYCLE = true;

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $selfs = array_filter($s['enrols'], fn($e) => $e['enrol'] === 'self');
        if (!$selfs) {
            return [$this->f(
                status::NAO_CONFORME,
                'Métodos de inscrição',
                'nenhuma instância de autoinscrição',
                ['orientacao' => $this->g('add'), 'check' => 'self_enrol']
            )];
        }
        $sd = $s['course']['startdate'];
        $ed = $s['course']['enddate'];
        foreach ($selfs as $e) {
            $probs = [];
            if ($e['status'] != 0) {
                $probs[] = 'método desabilitado';
            }
            if ($e['customint6'] !== 1) {
                $probs[] = '"Permitir novas inscrições" ≠ Sim';
            }
            if (!$e['enrolstartdate'] || !$e['enrolenddate']) {
                $probs[] = 'datas de inscrição não definidas';
            } else {
                if ($sd && $e['enrolstartdate'] != $sd) {
                    $probs[] = 'início da inscrição ≠ início do curso';
                }
                if ($ed && $e['enrolenddate'] != $ed) {
                    $probs[] = 'fim da inscrição ≠ fim do curso';
                }
            }
            $out[] = $this->f(
                $probs ? status::NAO_CONFORME : status::CONFORME,
                "Métodos de inscrição > Autoinscrição (id {$e['id']})",
                "status={$e['status']} novas_inscricoes={$e['customint6']} início=" . helpers::d($e['enrolstartdate']) . ' fim='
                    . helpers::d($e['enrolenddate']),
                ['encontrado' => implode('; ', $probs) ?: 'ok', 'orientacao' => $this->guidance(), 'check' => 'self_enrol',
                'subject' => 'enrol:' . $e['id']]
            );
        }
        foreach ($s['enrols'] as $e) {
            if ($e['enrol'] === 'guest' && $e['status'] == 0) {
                $out[] = $this->f(
                    status::ATENCAO,
                    'Métodos de inscrição > Acesso de visitante',
                    'habilitado' . ($e['has_password'] ? ' com senha' : ''),
                    ['gravidade' => status::INFORMATIVA, 'orientacao' => $this->g('guest'), 'check' => 'guest',
                    'subject' => 'enrol:' . $e['id']]
                );
            }
        }
        return $out;
    }
}
