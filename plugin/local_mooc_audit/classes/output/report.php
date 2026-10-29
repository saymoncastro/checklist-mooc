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

use local_mooc_audit\local\category;
use local_mooc_audit\local\locator;
use local_mooc_audit\local\status;
use moodle_url;
use renderable;
use renderer_base;
use single_button;
use stdClass;
use templatable;

/**
 * The audit report of a course, prepared for the report template.
 *
 * Converts the array produced by {@see \local_mooc_audit\engine::audit()} into the data of templates/report.mustache:
 * header, inventory, overall status and counts, non conformities grouped by severity then category, warnings
 * (ATENCAO), not verifiable / not applicable results with their reason, rule errors, the compact list of conformant
 * rules and the notices (data collection and policy). Every label comes from the language pack.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report implements renderable, templatable {

    /** @var string[] Bootstrap badge class of each state. */
    const STATUS_CLASS = [
        status::NAO_CONFORME => 'badge-danger',
        status::ATENCAO => 'badge-warning',
        status::ERRO => 'badge-dark',
        status::NAO_VERIFICAVEL => 'badge-secondary',
        status::NAO_APLICAVEL => 'badge-light',
        status::CONFORME => 'badge-success',
    ];

    /** @var string[] Bootstrap alert class of each overall status. */
    const OVERALL_CLASS = [
        status::OVERALL_BLOCKED => 'alert-danger',
        status::OVERALL_NEEDS_FIX => 'alert-warning',
        status::OVERALL_CLEAN => 'alert-success',
    ];

    /** @var array The report produced by the engine (contract 1.0). */
    protected array $report;
    /** @var moodle_url URL of the page that shows the report (used for re-run and JSON links). */
    protected moodle_url $pageurl;

    /**
     * Constructor.
     *
     * @param array $report report produced by the engine
     * @param moodle_url $pageurl URL of the current page
     */
    public function __construct(array $report, moodle_url $pageurl) {
        $this->report = $report;
        $this->pageurl = $pageurl;
    }

    /**
     * Data for templates/report.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $r = $this->report;
        $bystatus = [];
        foreach ($r['results'] as $res) {
            $bystatus[$res['status']][] = $res;
        }

        $data = new stdClass();
        $data->course = (object) $r['course'];
        $data->course->fullname = format_string($r['course']['fullname']);
        $data->engine = $r['engine'];
        $data->contract_version = $r['contract_version'];
        $data->policy_version = $r['policy_version'];
        $data->reoffer_year = $r['reoffer_year'];
        $data->executed_at = userdate(strtotime($r['executed_at']));
        $data->total_s = $r['timing']['total_s'];
        $rerun = new moodle_url($this->pageurl);
        $rerun->remove_params('format');
        $data->rerunurl = $rerun->out(false);
        $data->rerunbutton = $output->render(new single_button($rerun, get_string('rerun', 'local_mooc_audit'), 'get'));
        $json = new moodle_url($this->pageurl, ['format' => 'json']);
        $data->jsonurl = $json->out(false);

        $data->inventory = $this->inventory($r['inventory']);
        $data->overall = $this->overall($r['summary']);
        $data->counts_status = $this->counts_status($r['summary']['por_status']);
        $data->counts_severity = $this->counts_severity($r['summary']['nao_conformes_por_gravidade']);
        $data->rules_total = $r['summary']['regras'];

        $data->nonconformities = $this->grouped($bystatus[status::NAO_CONFORME] ?? []);
        $data->warnings = $this->grouped($bystatus[status::ATENCAO] ?? []);
        $nv = array_map([$this, 'item'], $bystatus[status::NAO_VERIFICAVEL] ?? []);
        $na = array_map([$this, 'item'], $bystatus[status::NAO_APLICAVEL] ?? []);
        $data->unverifiable = (object) ['has' => (bool) ($nv || $na), 'nv' => array_values($nv), 'hasnv' => (bool) $nv,
            'na' => array_values($na), 'hasna' => (bool) $na];
        $data->errors = array_values(array_map([$this, 'item'], $bystatus[status::ERRO] ?? []));
        $data->haserrors = (bool) $data->errors;
        $data->conformant = $this->conformant($r['results']);
        $data->hasconformant = (bool) $data->conformant;

        $notices = array_map(fn($w) => is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE), $r['warnings'] ?? []);
        $data->notices = (object) ['policy' => array_values($r['policy_warnings'] ?? []), 'collection' => array_values($notices)];
        $data->notices->has = (bool) ($data->notices->policy || $data->notices->collection);
        return $data;
    }

    /**
     * Inventory block.
     *
     * @param array $inv
     * @return stdClass
     */
    protected function inventory(array $inv): stdClass {
        $types = [];
        foreach ($inv['modules_by_type'] as $type => $count) {
            $types[] = (object) ['type' => $type, 'count' => $count];
        }
        return (object) [
            'startdate' => $inv['startdate'] ?? '-',
            'enddate' => $inv['enddate'] ?? '-',
            'category' => format_string($inv['category']['name'] ?? ''),
            'visible' => get_string($inv['visible'] ? 'yes' : 'no'),
            'sections' => $inv['sections'],
            'modules' => $inv['modules'],
            'modules_by_type' => $types,
            'grade_items' => $inv['grade_items'],
            'files' => $inv['files'],
            'files_missing' => $inv['files_missing_in_storage'],
            'enrols' => implode(', ', $inv['enrols']) ?: '-',
            'blocks' => implode(', ', $inv['blocks']) ?: '-',
        ];
    }

    /**
     * Overall status block.
     *
     * @param array $summary
     * @return stdClass
     */
    protected function overall(array $summary): stdClass {
        $code = $summary['overall_status'];
        return (object) ['code' => $code, 'label' => get_string('overall_' . strtolower($code), 'local_mooc_audit'),
            'cssclass' => self::OVERALL_CLASS[$code] ?? 'alert-secondary'];
    }

    /**
     * Counts per state, in display order.
     *
     * @param array $bystatus
     * @return array
     */
    protected function counts_status(array $bystatus): array {
        $out = [];
        foreach (status::all() as $s) {
            $out[] = (object) ['code' => $s, 'label' => get_string(status::string_key($s), 'local_mooc_audit'),
                'count' => $bystatus[$s] ?? 0, 'cssclass' => self::STATUS_CLASS[$s]];
        }
        return $out;
    }

    /**
     * Counts of non conformities per severity, in display order.
     *
     * @param array $bysev
     * @return array
     */
    protected function counts_severity(array $bysev): array {
        $out = [];
        foreach (status::severities() as $sev) {
            $out[] = (object) ['code' => $sev, 'label' => get_string(status::severity_string_key($sev), 'local_mooc_audit'),
                'count' => $bysev[$sev] ?? 0];
        }
        return $out;
    }

    /**
     * Group results by severity (worst first) then by category (catalogue order).
     *
     * @param array $results
     * @return stdClass {count, has, groups: [{severity, label, count, categories: [{category, title, items}]}]}
     */
    protected function grouped(array $results): stdClass {
        $tree = [];
        foreach ($results as $res) {
            $tree[$res['gravidade'] ?? ''][$res['category']][] = $this->item($res);
        }
        $groups = [];
        foreach (status::severities() as $sev) {
            if (empty($tree[$sev])) {
                continue;
            }
            $cats = [];
            $n = 0;
            foreach (category::all() as $cat) {
                if (empty($tree[$sev][$cat])) {
                    continue;
                }
                $cats[] = (object) ['category' => $cat, 'title' => category::title($cat), 'items' => $tree[$sev][$cat]];
                $n += count($tree[$sev][$cat]);
            }
            $groups[] = (object) ['severity' => $sev, 'label' => get_string(status::severity_string_key($sev), 'local_mooc_audit'),
                'count' => $n, 'categories' => $cats];
        }
        return (object) ['count' => count($results), 'has' => (bool) $results, 'groups' => $groups];
    }

    /**
     * One result as a template item.
     *
     * @param array $res
     * @return stdClass
     */
    protected function item(array $res): stdClass {
        return (object) [
            'rule' => $res['rule'],
            'title' => $res['titulo'],
            'category' => category::title($res['category']),
            'status' => $res['status'],
            'statuslabel' => get_string(status::string_key($res['status']), 'local_mooc_audit'),
            'statusclass' => self::STATUS_CLASS[$res['status']] ?? 'badge-secondary',
            'severity' => $res['gravidade'],
            'severitylabel' => $res['gravidade'] ? get_string(status::severity_string_key($res['gravidade']), 'local_mooc_audit') : '',
            'local' => $res['local'],
            'link' => $res['link'],
            'haslink' => !empty($res['link']),
            'linktitle' => !empty($res['link']) ? get_string('link_title', 'local_mooc_audit', locator::title($res['locus'])) : '',
            'evidencia' => $res['evidencia'],
            'encontrado' => $res['encontrado'],
            'esperado' => $res['esperado'],
            'orientacao' => $res['orientacao'],
            'hasfound' => $res['encontrado'] !== null && $res['encontrado'] !== '',
            'hasexpected' => $res['esperado'] !== null && $res['esperado'] !== '',
            'hasdetails' => ($res['encontrado'] !== null && $res['encontrado'] !== '') || ($res['esperado'] !== null && $res['esperado'] !== ''),
        ];
    }

    /**
     * Compact list of the rules whose every result is CONFORME (one entry per rule, catalogue order).
     *
     * A rule with one conformant and one non conformant result is not listed here: it already appears in the
     * non-conformities.
     *
     * @param array $results all results of the report
     * @return array
     */
    protected function conformant(array $results): array {
        $rules = [];
        foreach ($results as $res) {
            if (!isset($rules[$res['rule']])) {
                $rules[$res['rule']] = (object) ['rule' => $res['rule'], 'title' => $res['titulo'], 'category' => category::title($res['category']),
                    'only' => true];
            }
            if ($res['status'] !== status::CONFORME) {
                $rules[$res['rule']]->only = false;
            }
        }
        $out = [];
        foreach ($rules as $rule) {
            if ($rule->only) {
                unset($rule->only);
                $out[] = $rule;
            }
        }
        return $out;
    }
}
