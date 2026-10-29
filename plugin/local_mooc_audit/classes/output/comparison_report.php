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

namespace local_mooc_audit\output;

use local_mooc_audit\local\comparison\audit_relation;
use local_mooc_audit\local\comparison\differ;
use local_mooc_audit\local\status;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * A comparison between two offers (contract comparison 1.0), prepared for templates/comparison.mustache.
 *
 * Sections in the order of specs/003 FR-022: identification, summary, pending items of the target, missing,
 * ambiguities (with the manual match form), modified, additional, course differences, expected differences,
 * preserved, notices. Read-only: no correction action is offered.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparison_report implements renderable, templatable {
    /** @var string[] Badge class of each kind of difference. */
    const KIND_CLASS = [differ::RELEVANT => 'badge-danger', differ::INFO => 'badge-info', differ::EXPECTED => 'badge-light',
        'nenhuma' => 'badge-success'];
    /** @var string[] Alert class of each verdict. */
    const VERDICT_CLASS = ['REVISAR' => 'alert-danger', 'CONFERIR' => 'alert-warning',
        'SEM_DIFERENCAS_RELEVANTES' => 'alert-success'];
    /** @var string[] Classes about the target course (the others concern the previous offer). */
    const TARGET_CLASSES = ['pendencia_oferta', 'novo_no_destino', 'novo_em_atividade_adicional', 'persistente',
        'indeterminado', 'nao_verificavel_no_destino'];

    /** @var array Comparison (contract comparison 1.0). */
    protected array $r;
    /** @var moodle_url URL of the page. */
    protected moodle_url $pageurl;

    /**
     * Constructor.
     *
     * @param array $comparison result of comparator::run()
     * @param moodle_url $pageurl current page (source, target, confirm, pair[])
     */
    public function __construct(array $comparison, moodle_url $pageurl) {
        $this->r = $comparison;
        $this->pageurl = $pageurl;
    }

    /**
     * Data for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $r = $this->r;
        $str = fn(string $k, $a = null) => get_string($k, 'local_mooc_audit', $a);
        $d = new stdClass();
        $d->source = $this->side($r['source'], 'source');
        $d->target = $this->side($r['target'], 'target');
        $d->executed_at = userdate(strtotime($r['executed_at']));
        $d->total_s = $r['timing']['total_s'];
        $d->engine = $r['engine'];
        $d->contract = $r['contract'] . ' ' . $r['contract_version'];
        $d->policy = (object) $r['policy'];
        $d->policy->source_reoffer_year = $r['policy']['source_reoffer_year'] ?? '—';
        $json = new moodle_url($this->pageurl, ['format' => 'json']);
        $d->jsonurl = $json->out(false);
        $rerun = new moodle_url($this->pageurl);
        $rerun->remove_params('format');
        $d->rerunurl = $rerun->out(false);
        $d->inverturl = (new moodle_url('/local/mooc_audit/compare.php', ['source' => $r['target']['id'],
            'target' => $r['source']['id'], 'confirm' => 1]))->out(false);
        $d->inverted = (bool) array_filter($r['warnings'], fn($w) => $w === $str('cmp_inverted'));

        $s = $r['summary'];
        $d->verdict = (object) ['code' => $s['verdict'], 'label' => $str('cmp_verdict_' . strtolower($s['verdict'])),
            'cssclass' => self::VERDICT_CLASS[$s['verdict']]];
        $d->counts = [
            (object) ['label' => $str('cmp_count_preserved'), 'count' => $s['preserved']],
            (object) ['label' => $str('cmp_count_modified'), 'count' => $s['modified'] . ' (' . $s['modified_relevant'] . ' '
                . $str('cmp_count_modified_relevant') . ')'],
            (object) ['label' => $str('cmp_count_missing'), 'count' => $s['missing_in_target']],
            (object) ['label' => $str('cmp_count_extra'), 'count' => $s['extra_in_target']],
            (object) ['label' => $str('cmp_count_ambiguous'), 'count' => $s['ambiguous_groups']],
            (object) ['label' => $str('cmp_count_duplicates'), 'count' => $s['duplicates_in_target']],
        ];
        $counts = (array) $s['audit_counts'];
        foreach (audit_relation::CLASSES as $c) {
            if (!empty($counts[$c])) {
                $d->counts[] = (object) ['label' => $str('cmp_class_' . $c), 'count' => $counts[$c]];
            }
        }

        [$d->pending, $d->previous] = $this->pending($r['audit']['items']);
        $d->haspending = (bool) $d->pending;
        $d->hasprevious = (bool) $d->previous;
        $d->missing = array_values($r['missing_in_target']);
        $d->hasmissing = (bool) $d->missing;
        [$d->ambiguities, $d->manualhidden] = $this->ambiguities();
        $d->hasambiguities = (bool) $d->ambiguities;
        $d->manualaction = (new moodle_url('/local/mooc_audit/compare.php'))->out(false);

        $modified = array_values(array_filter($r['matches'], fn($m) => $m['state'] === 'modificada'));
        usort($modified, fn($x, $y) => differ::KIND_ORDER[$x['relevance']] <=> differ::KIND_ORDER[$y['relevance']]);
        $d->modified = array_map(fn($m) => $this->match($m, false), $modified);
        $d->hasmodified = (bool) $d->modified;
        $d->extra = array_map(
            fn($e) => (object) array_merge($e, ['kindlabel' => $str('cmp_kind_' . $e['kind']),
            'kindclass' => self::KIND_CLASS[$e['kind']], 'explanation' => $str('cmp_code_' . $e['code']),
            'duplicate' => isset($e['duplicate_of']) ? $str('cmp_duplicate_of', $e['duplicate_of']) : null]),
            $r['extra_in_target']
        );
        $d->hasextra = (bool) $d->extra;

        $notexpected = array_values(array_filter($r['course_diffs'], fn($x) => $x['kind'] !== differ::EXPECTED));
        usort($notexpected, fn($x, $y) => differ::KIND_ORDER[$x['kind']] <=> differ::KIND_ORDER[$y['kind']]);
        $d->hascoursediffs = (bool) $notexpected;
        $d->coursetable = $notexpected ? (object) ['diffs' => array_map([$this, 'diff'], $notexpected)] : null;
        $expected = array_values(array_filter($r['course_diffs'], fn($x) => $x['kind'] === differ::EXPECTED));
        foreach ($r['matches'] as $m) {
            foreach ($m['diffs'] as $x) {
                if ($x['kind'] === differ::EXPECTED) {
                    $x['field'] = $m['modname'] . ' "' . $m['target_name'] . '" — ' . $x['field'];
                    $expected[] = $x;
                }
            }
        }
        $d->hasexpected = (bool) $expected;
        $d->expectedtable = $expected ? (object) ['diffs' => array_map([$this, 'diff'], $expected)] : null;
        $d->expectedids = $str('cmp_expected_ids', $s['expected_identifier_changes'] ?? 0);

        $preserved = array_values(array_filter($r['matches'], fn($m) => $m['state'] === 'preservada'));
        $d->preserved = array_map(fn($m) => $this->match($m, true), $preserved);
        $d->preservedcount = count($d->preserved);
        $d->haspreserved = (bool) $d->preserved;
        $d->warnings = array_values($r['warnings']);
        $d->haswarnings = (bool) $d->warnings;
        return $d;
    }

    /**
     * One course side.
     *
     * @param array $side
     * @param string $key source|target
     * @return stdClass
     */
    protected function side(array $side, string $key): stdClass {
        $o = (object) $side;
        $o->label = get_string("cmp_side_$key", 'local_mooc_audit');
        $o->fullname = format_string($side['fullname'], true, ['escape' => false]);
        $o->category = format_string((string) $side['category'], true, ['escape' => false]);
        $o->overalllabel = get_string('overall_' . strtolower($side['overall_status']), 'local_mooc_audit');
        $o->overallclass = report::OVERALL_CLASS[$side['overall_status']] ?? 'alert-secondary';
        $o->auditurl = (new moodle_url('/local/mooc_audit/index.php', ['courseid' => $side['id']]))->out(false);
        $o->startdate = $side['startdate'] ?? '—';
        $o->enddate = $side['enddate'] ?? '—';
        return $o;
    }

    /**
     * Pending items grouped by class: target classes, then the previous offer.
     *
     * @param array $items
     * @return array [target groups, previous offer groups]
     */
    protected function pending(array $items): array {
        $byclass = [];
        foreach ($items as $i) {
            $byclass[$i['classification']][] = $i;
        }
        $target = [];
        $previous = [];
        foreach (audit_relation::CLASSES as $c) {
            if (empty($byclass[$c])) {
                continue;
            }
            $group = (object) [
                'code' => $c,
                'label' => get_string('cmp_class_' . $c, 'local_mooc_audit'),
                'description' => get_string('cmp_classdesc_' . $c, 'local_mooc_audit'),
                'count' => count($byclass[$c]),
                'items' => array_map(fn($i) => (object) array_merge($i, [
                    'severitylabel' => $i['severity'] ? get_string(status::severity_string_key($i['severity']), 'local_mooc_audit')
                        : '',
                    'sourcelabel' => $i['status_source'] ? get_string(status::string_key($i['status_source']), 'local_mooc_audit')
                        : '—',
                    'targetlabel' => $i['status_target'] ? get_string(status::string_key($i['status_target']), 'local_mooc_audit')
                        : '—',
                    'targetclass' => report::STATUS_CLASS[$i['status_target']] ?? 'badge-light',
                    'haslink' => !empty($i['link']),
                ]), $byclass[$c]),
            ];
            if (in_array($c, self::TARGET_CLASSES, true)) {
                $target[] = $group;
            } else {
                $previous[] = $group;
            }
        }
        return [$target, $previous];
    }

    /**
     * Ambiguities, each with a picker per source module, and the hidden fields of the manual match form.
     *
     * @return array [ambiguities, hidden fields]
     */
    protected function ambiguities(): array {
        $r = $this->r;
        $accepted = array_values(array_filter($r['manual_pairs'], fn($p) => $p['accepted']));
        $hidden = [
            (object) ['name' => 'source', 'value' => $r['source']['id']],
            (object) ['name' => 'target', 'value' => $r['target']['id']],
            (object) ['name' => 'confirm', 'value' => 1],
        ];
        foreach ($accepted as $p) {
            $hidden[] = (object) ['name' => 'pair[]', 'value' => $p['source_cmid'] . '-' . $p['target_cmid']];
        }
        $out = [];
        foreach ($r['ambiguities'] as $i => $a) {
            $pickers = [];
            foreach ($a['source'] as $src) {
                $options = [(object) ['value' => '', 'label' => get_string('cmp_candidate_none', 'local_mooc_audit')]];
                foreach ($a['target'] as $tgt) {
                    $options[] = (object) ['value' => $src['cmid'] . '-' . $tgt['cmid'],
                        'label' => get_string('label_section', 'local_mooc_audit') . " {$tgt['section']}, "
                            . get_string('cmp_position', 'local_mooc_audit') . ' ' . ($tgt['position'] + 1)
                            . " (id {$tgt['cmid']})"];
                }
                $pickers[] = (object) ['id' => "local_mooc_audit-amb-$i-{$src['cmid']}", 'source' => (object) $src,
                    'sourceurl' => (new moodle_url("/mod/{$a['modname']}/view.php", ['id' => $src['cmid']]))->out(false),
                    'options' => $options];
            }
            $out[] = (object) array_merge($a, [
                'reasonlabel' => $a['explanation'],
                'settings' => get_string(
                    $a['settings_identical'] ? 'cmp_settings_identical' : 'cmp_settings_different',
                    'local_mooc_audit'
                ),
                'targets' => array_map(fn($t) => (object) array_merge($t, ['url' => (new moodle_url(
                    "/mod/{$a['modname']}/view.php",
                    ['id' => $t['cmid']]
                ))->out(false)]), $a['target']),
                'pickers' => $pickers,
            ]);
        }
        return [$out, $hidden];
    }

    /**
     * A match as a template item.
     *
     * @param array $m
     * @param bool $compact
     * @return stdClass
     */
    protected function match(array $m, bool $compact): stdClass {
        $o = (object) $m;
        $o->strategylabel = get_string('cmp_strategy_' . $m['strategy'], 'local_mooc_audit');
        $o->confidencelabel = get_string('cmp_conf_' . $m['confidence'], 'local_mooc_audit');
        $o->confidenceclass = $m['confidence'] === 'alta' ? 'badge-success' : 'badge-warning';
        $o->kindlabel = get_string('cmp_kind_' . $m['relevance'], 'local_mooc_audit');
        $o->kindclass = self::KIND_CLASS[$m['relevance']];
        $o->renamed = $m['source_name'] !== $m['target_name'];
        $o->targeturl = (new moodle_url('/course/modedit.php', ['update' => $m['target_cmid']]))->out(false);
        $o->diffs = $compact ? [] : array_map([$this, 'diff'], array_values(array_filter(
            $m['diffs'],
            fn($x) => $x['kind'] !== differ::EXPECTED
        )));
        return $o;
    }

    /**
     * A difference as a template item.
     *
     * @param array $x
     * @return stdClass
     */
    protected function diff(array $x): stdClass {
        return (object) [
            'scope' => $x['scope'],
            'field' => $x['field'],
            'source' => differ::show($x['source']),
            'target' => differ::show($x['target']),
            'kind' => $x['kind'],
            'kindlabel' => get_string('cmp_kind_' . $x['kind'], 'local_mooc_audit'),
            'kindclass' => self::KIND_CLASS[$x['kind']],
            'code' => $x['code'],
            'explanation' => $x['explanation'],
        ];
    }
}
