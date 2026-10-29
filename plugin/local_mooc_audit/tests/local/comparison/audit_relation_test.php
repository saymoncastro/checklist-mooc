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

namespace local_mooc_audit\local\comparison;

use local_mooc_audit\comparator;
use local_mooc_audit\local\catalog;
use local_mooc_audit\local\status;

/**
 * Relation between the audits of the source and the target (specs/003 data-model, AuditItem): each class, keys without
 * ids, and offer-cycle rules never reported as persistent.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mooc_audit\local\comparison\audit_relation
 */
final class audit_relation_test extends \advanced_testcase {
    /** @var array Matcher output: 100→300, 101→301 paired; 102 missing; 303 extra; 104 vs 304/305 ambiguous. */
    private const MATCH = [
        'pairs' => [100 => ['target' => 300], 101 => ['target' => 301]],
        'missing' => [102],
        'extra' => [303],
        'ambiguities' => [['source' => [['cmid' => 104]], 'target' => [['cmid' => 304], ['cmid' => 305]]]],
    ];

    /**
     * A result in the engine format.
     *
     * @param string $rule
     * @param string $status
     * @param int|null $cmid
     * @param string $local
     * @param int|null $section
     * @return array
     */
    private function r(string $rule, string $status, ?int $cmid = null, string $local = 'Curso', ?int $section = null): array {
        return ['rule' => $rule, 'status' => $status, 'cmid' => $cmid, 'section' => $section, 'local' => $local,
            'evidencia' => "$rule $status", 'titulo' => "Título $rule", 'category' => 'x', 'gravidade' => status::ALTA,
            'link' => $cmid ? "http://x/$cmid" : null, 'orientacao' => 'Faça algo', 'encontrado' => null, 'esperado' => null,
            'locus' => 'none', 'origem' => '', 'classe' => 'A', 'tipo' => 'tecnica'];
    }

    /**
     * Classification by rule key.
     *
     * @param array $src
     * @param array $dst
     * @return array "rule|cmid_source|cmid_target" => class
     */
    private function classify(array $src, array $dst): array {
        [$items] = audit_relation::relate($src, $dst, self::MATCH, comparator::CYCLE_RULES);
        $out = [];
        foreach ($items as $i) {
            $out[$i['rule'] . '|' . ($i['cmid_source'] ?? '-') . '|' . ($i['cmid_target'] ?? '-')] = $i['classification'];
        }
        return $out;
    }

    /**
     * Every target class.
     */
    public function test_target_classes(): void {
        $nc = status::NAO_CONFORME;
        $src = [
            $this->r('CL-13', $nc, 100), // Persistent.
            $this->r('CL-17', status::CONFORME, 101), // New in the target.
            $this->r('CL-06', status::CONFORME), // Cycle: pending, whatever the source.
            $this->r('CL-07', $nc), // Cycle problem in the source only: not listed.
        ];
        $dst = [
            $this->r('CL-13', $nc, 300),
            $this->r('CL-17', status::ATENCAO, 301),
            $this->r('X-CONT-01', $nc, 303), // Additional activity.
            $this->r('X-CONT-01', $nc, 305), // Ambiguous activity.
            $this->r('CL-06', $nc),
            $this->r('CL-07', status::CONFORME),
            $this->r('X-INT-03', status::NAO_VERIFICAVEL),
        ];
        $this->assertSame([
            'CL-06|-|-' => 'pendencia_oferta',
            'CL-17|101|301' => 'novo_no_destino',
            'X-CONT-01|-|303' => 'novo_em_atividade_adicional',
            'CL-13|100|300' => 'persistente',
            'X-CONT-01|-|305' => 'indeterminado',
            'X-INT-03|-|-' => 'nao_verificavel_no_destino',
        ], $this->classify($src, $dst));
    }

    /**
     * A warning that became a non-conformity is new in the target; a non-conformity that became a warning persists.
     */
    public function test_worsening_is_new_and_improvement_persists(): void {
        $src = [$this->r('CL-18', status::ATENCAO, 100), $this->r('CL-13', status::NAO_CONFORME, 101)];
        $dst = [$this->r('CL-18', status::NAO_CONFORME, 300), $this->r('CL-13', status::ATENCAO, 301)];
        $this->assertSame(
            ['CL-18|100|300' => 'novo_no_destino', 'CL-13|101|301' => 'persistente'],
            $this->classify($src, $dst)
        );
    }

    /**
     * Source problems that do not appear in the target.
     */
    public function test_source_classes(): void {
        $nc = status::NAO_CONFORME;
        $src = [
            $this->r('CL-13', $nc, 100), // Resolved (target conformant).
            $this->r('CL-19', $nc, 101), // Resolved (no result at that location in the target).
            $this->r('X-CONT-01', $nc, 102), // Missing activity.
            $this->r('CL-18', $nc, 104), // Ambiguous activity.
            $this->r('CL-12', $nc, null, 'Livro de notas > categoria raiz'), // Not applicable in the target.
        ];
        $dst = [
            $this->r('CL-13', status::CONFORME, 300),
            $this->r('CL-12', status::NAO_APLICAVEL, null, 'Livro de notas > categoria raiz'),
        ];
        $this->assertSame([
            'CL-18|104|-' => 'indeterminado',
            'X-CONT-01|102|-' => 'ausente_no_destino',
            'CL-13|100|300' => 'resolvido_no_destino',
            'CL-19|101|301' => 'resolvido_no_destino',
            'CL-12|-|-' => 'nao_aplicavel_no_destino',
        ], array_intersect_key($this->classify($src, $dst), array_flip(['X-CONT-01|102|-', 'CL-13|100|300', 'CL-19|101|301',
            'CL-12|-|-', 'CL-18|104|-'])));
        [$items, $counts] = audit_relation::relate($src, $dst, self::MATCH, comparator::CYCLE_RULES);
        $this->assertSame(2, $counts['resolvido_no_destino']);
        $this->assertSame(5, count($items));
    }

    /**
     * Location keys never contain internal ids: the same enrolment method or module pair gives the same key on both
     * sides; section results use the section number.
     */
    public function test_keys_without_ids(): void {
        $pairs = [100 => 300];
        $reverse = [300 => 100];
        $s = audit_relation::key(
            $this->r('CL-08', 'x', null, 'Métodos de inscrição > Autoinscrição (id 24)'),
            'source',
            $pairs,
            $reverse
        );
        $t = audit_relation::key(
            $this->r('CL-08', 'x', null, 'Métodos de inscrição > Autoinscrição (id 97)'),
            'target',
            $pairs,
            $reverse
        );
        $this->assertSame($s, $t);
        $this->assertStringNotContainsString('24', $s);
        $this->assertSame(
            audit_relation::key($this->r('CL-13', 'x', 100), 'source', $pairs, $reverse),
            audit_relation::key($this->r('CL-13', 'x', 300), 'target', $pairs, $reverse)
        );
        $this->assertSame('CL-20|s:3', audit_relation::key(
            $this->r('CL-20', 'x', null, 'Seção 3 "Topic 3"', 3),
            'target',
            $pairs,
            $reverse
        ));
    }

    /**
     * Several results with the same key are grouped: worst state and every evidence.
     */
    public function test_grouping_keeps_worst_state_and_evidence(): void {
        $src = [$this->r('CL-13', status::CONFORME, 100)];
        $dst = [$this->r('CL-13', status::ATENCAO, 300), $this->r('CL-13', status::NAO_CONFORME, 300)];
        [$items] = audit_relation::relate($src, $dst, self::MATCH, comparator::CYCLE_RULES);
        $this->assertCount(1, $items);
        $this->assertSame(status::NAO_CONFORME, $items[0]['status_target']);
        $this->assertSame('CL-13 ATENCAO | CL-13 NAO_CONFORME', $items[0]['evidence_target']);
        $this->assertSame(status::CONFORME, $items[0]['status_source']);
    }

    /**
     * The offer-cycle rules exist in the catalogue and are exactly the date/enrolment/category/closing-date rules.
     */
    public function test_cycle_rules_are_catalogue_rules(): void {
        $this->resetAfterTest();
        $ids = array_keys(catalog::instances());
        foreach (comparator::CYCLE_RULES as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertSame(['CL-05', 'CL-06', 'CL-07', 'CL-08', 'CL-14'], comparator::CYCLE_RULES);
        $offer = array_keys(array_filter(catalog::instances(), fn($r) => $r->meta()['category'] === 'offer_enrol'));
        $this->assertSame([], array_diff($offer, comparator::CYCLE_RULES), 'every offer/enrolment rule is a cycle rule');
    }
}
