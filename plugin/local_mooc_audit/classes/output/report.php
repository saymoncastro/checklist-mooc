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
use local_mooc_audit\local\correction\planner;
use local_mooc_audit\local\locator;
use local_mooc_audit\local\presentation\catalog as messages;
use local_mooc_audit\local\presentation\causes;
use local_mooc_audit\local\presentation\finding;
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
    /** @var string[] Badge classes of each state (bg-* + text-*: valid in Bootstrap 4 of Moodle 4.5 and 5 of Moodle 5.x). */
    const STATUS_CLASS = [
        status::NAO_CONFORME => 'bg-danger text-white',
        status::ATENCAO => 'bg-warning text-dark',
        status::ERRO => 'bg-dark text-white',
        status::NAO_VERIFICAVEL => 'bg-secondary text-dark',
        status::NAO_APLICAVEL => 'bg-light text-dark',
        status::CONFORME => 'bg-success text-white',
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
    /** @var bool Offer "See correction proposal" on eligible results (viewer holds local/mooc_audit:preparecorrection). */
    protected bool $withcorrections;
    /** @var moodle_url|null Link to compare this course with the previous offer (viewer holds local/mooc_audit:compare). */
    protected ?moodle_url $compareurl;
    /** @var moodle_url|null JSON of a stored run (specs/005): the report is a record, so it offers neither re-run nor a
     * live JSON link, which would execute and record a new audit. */
    protected ?moodle_url $storedjsonurl;
    /** @var array|null policy applied by the run (null: the policy in force) */
    protected ?array $policy;
    /** @var finding|null presentation of the results (specs/006); null when the course structure is unavailable */
    protected ?finding $presenter = null;
    /** @var string View of the problems: priority (severity › category) or activity (specs/006, FR-012). */
    protected string $view = self::VIEW_PRIORITY;
    /** @var stdClass[] Template item of each result, by position in the report. */
    protected array $items = [];

    /** @var string */
    const VIEW_PRIORITY = 'priority';
    /** @var string */
    const VIEW_ACTIVITY = 'activity';
    /** @var string Browser cookie that remembers the chosen view. */
    const VIEW_COOKIE = 'local_mooc_audit_view';

    /**
     * Constructor.
     *
     * @param array $report report produced by the engine
     * @param moodle_url $pageurl URL of the current page
     * @param bool $withcorrections offer the correction proposal action on eligible results (specs/002)
     * @param moodle_url|null $compareurl link to the comparison with the previous offer (specs/003)
     * @param moodle_url|null $storedjsonurl JSON of the stored run when the report comes from the history (specs/005)
     * @param array|null $policy policy applied by the run, for the texts built at display time (specs/006); null: the
     *     policy in force
     */
    public function __construct(
        array $report,
        moodle_url $pageurl,
        bool $withcorrections = false,
        ?moodle_url $compareurl = null,
        ?moodle_url $storedjsonurl = null,
        ?array $policy = null
    ) {
        $this->report = $report;
        $this->pageurl = $pageurl;
        $this->withcorrections = $withcorrections;
        $this->compareurl = $compareurl;
        $this->storedjsonurl = $storedjsonurl;
        $this->policy = $policy;
    }

    /**
     * View asked in the URL (view=priority|activity), remembered in a cookie of the browser (specs/006, FR-014: no
     * other persistence); without either, the default view. Must be called before any output (it may set the cookie).
     *
     * @return string
     */
    public static function remembered_view(): string {
        global $CFG;
        $views = [self::VIEW_PRIORITY, self::VIEW_ACTIVITY];
        $asked = optional_param('view', '', PARAM_ALPHA);
        if (in_array($asked, $views, true)) {
            if (!headers_sent()) {
                setcookie(self::VIEW_COOKIE, $asked, ['expires' => time() + YEARSECS, 'path' => $CFG->sessioncookiepath ?: '/',
                    'secure' => is_moodle_cookie_secure(), 'httponly' => true, 'samesite' => 'Lax']);
            }
            return $asked;
        }
        $cookie = clean_param($_COOKIE[self::VIEW_COOKIE] ?? '', PARAM_ALPHA);
        return in_array($cookie, $views, true) ? $cookie : self::VIEW_PRIORITY;
    }

    /**
     * Choose the view of the problems.
     *
     * @param string $view self::VIEW_PRIORITY or self::VIEW_ACTIVITY (anything else: priority)
     * @return self
     */
    public function set_view(string $view): self {
        $this->view = $view === self::VIEW_ACTIVITY ? self::VIEW_ACTIVITY : self::VIEW_PRIORITY;
        return $this;
    }

    /**
     * Data for templates/report.mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $r = $this->report;

        try {
            $this->presenter = new finding(
                (int) $r['course']['id'],
                $r['results'],
                $this->policy ?? \local_mooc_audit\policy::load(),
                $this->pageurl
            );
        } catch (\moodle_exception $e) {
            // Course no longer available (deleted after the run): the stored texts are shown.
            $this->presenter = null;
        }
        $data = new stdClass();
        $data->course = (object) $r['course'];
        // Mustache escapes {{ }} values: format without escaping here to avoid "&amp;amp;".
        $data->course->fullname = format_string($r['course']['fullname'], true, ['escape' => false]);
        $data->engine = $r['engine'];
        $data->contract_version = $r['contract_version'];
        $data->policy_version = $r['policy_version'];
        $data->reoffer_year = $r['reoffer_year'];
        $data->executed_at = userdate(strtotime($r['executed_at']));
        $data->total_s = $r['timing']['total_s'];
        // Running an audit records it (specs/005, decision 12.1): an explicit POST with sesskey, never a link.
        $rerun = new moodle_url($this->pageurl, ['action' => 'run']);
        $rerun->remove_params('format');
        $data->rerunurl = $rerun->out(false);
        $data->rerunbutton = $this->storedjsonurl ? ''
            : $output->render(new single_button($rerun, get_string('rerun', 'local_mooc_audit'), 'post'));
        $json = $this->storedjsonurl ?? new moodle_url($this->pageurl, ['format' => 'json']);
        $data->jsonurl = $json->out(false);
        $data->compareurl = $this->compareurl ? $this->compareurl->out(false) : null;

        $data->inventory = $this->inventory($r['inventory']);
        $data->overall = $this->overall($r['summary']);
        $data->counts_status = $this->counts_status($r['summary']['por_status']);
        $data->counts_severity = $this->counts_severity($r['summary']['nao_conformes_por_gravidade']);
        $data->rules_total = $r['summary']['regras'];

        $this->build_items();
        $idx = [];
        foreach ($r['results'] as $i => $res) {
            $idx[$res['status']][] = $i;
        }
        $pick = fn(string $state) => array_map(fn($i) => $this->items[$i], $idx[$state] ?? []);
        $data->nonconformities = $this->grouped($idx[status::NAO_CONFORME] ?? []);
        $data->warnings = $this->grouped($idx[status::ATENCAO] ?? []);
        $data->summary = $this->summary($idx);
        $data->startwith = $this->startwith($idx[status::NAO_CONFORME] ?? []);
        $data->hasstartwith = (bool) $data->startwith;
        $data->activityview = $this->view === self::VIEW_ACTIVITY;
        $data->views = $this->views();
        $data->activities = $data->activityview ? $this->by_activity(array_merge(
            $idx[status::NAO_CONFORME] ?? [],
            $idx[status::ATENCAO] ?? []
        )) : [];
        $data->manual = $this->manual();
        $data->hasmanual = (bool) $data->manual;
        $nv = $pick(status::NAO_VERIFICAVEL);
        $na = $pick(status::NAO_APLICAVEL);
        $data->unverifiable = (object) ['has' => (bool) ($nv || $na), 'nv' => array_values($nv), 'hasnv' => (bool) $nv,
            'na' => array_values($na), 'hasna' => (bool) $na];
        $data->errors = array_values($pick(status::ERRO));
        $data->haserrors = (bool) $data->errors;
        $data->conformant = $this->conformant($r['results']);
        $data->hasconformant = (bool) $data->conformant;

        $notices = array_map(fn($w) => is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE), $r['warnings'] ?? []);
        $data->notices = (object) ['policy' => array_values($r['policy_warnings'] ?? []), 'collection' => array_values($notices)];
        $data->notices->has = (bool) ($data->notices->policy || $data->notices->collection);
        $missing = $this->presenter ? $this->presenter->missing_plugins() : [];
        $data->missingplugins = $missing ? get_string('missingplugins', 'local_mooc_audit', implode(', ', $missing)) : null;
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
            'category' => format_string($inv['category']['name'] ?? '', true, ['escape' => false]),
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
     * @param int[] $indexes positions of the results in the report
     * @return stdClass {count, has, groups: [{severity, label, count, categories: [{category, title, items}]}]}
     */
    protected function grouped(array $indexes): stdClass {
        $tree = [];
        foreach ($indexes as $i) {
            $res = $this->report['results'][$i];
            $tree[$res['gravidade'] ?? ''][$res['category']][] = $this->items[$i];
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
        return (object) ['count' => count($indexes), 'has' => (bool) $indexes, 'groups' => $groups];
    }

    /**
     * One result as a template item.
     *
     * @param array $res
     * @return stdClass
     */
    protected function item(array $res): stdClass {
        $item = (object) [
            'rule' => $res['rule'],
            'title' => $res['titulo'],
            'category' => category::title($res['category']),
            'status' => $res['status'],
            'statuslabel' => get_string(status::string_key($res['status']), 'local_mooc_audit'),
            'statusclass' => self::STATUS_CLASS[$res['status']] ?? 'bg-secondary text-dark',
            'severity' => $res['gravidade'],
            'severitylabel' => $res['gravidade'] ? get_string(
                status::severity_string_key($res['gravidade']),
                'local_mooc_audit'
            ) : '',
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
            'hasdetails' => ($res['encontrado'] !== null && $res['encontrado'] !== '') || ($res['esperado'] !== null
                && $res['esperado'] !== ''),
            'correctionurl' => $this->withcorrections && planner::eligible($res) ? (new moodle_url(
                '/local/mooc_audit/correction.php',
                ['courseid' => $this->report['course']['id'], 'rule' => $res['rule'], 'cmid' => $res['cmid']]
            ))->out(false) : null,
        ];
        $p = $this->presenter ? $this->presenter->present($res) : (object) ['fallback' => true, 'check' => ''];
        $item->presented = !$p->fallback;
        if ($item->presented) {
            foreach (['problem', 'why', 'fix', 'who', 'verify', 'before', 'actions', 'place', 'whoyou', 'verifyspecific'] as $field) {
                $item->$field = $p->$field;
            }
            $item->alternatives = array_map(fn($text) => (object) ['text' => $text], $p->alternatives);
            $item->hasalternatives = (bool) $p->alternatives;
            $item->hasactions = (bool) $p->actions;
        }
        $item->check = $p->check;
        $impact = messages::impact($res);
        $item->impact = $impact ? get_string('impact_' . $impact, 'local_mooc_audit') : null;
        $kind = messages::evidence($res['rule'], $p->check);
        $item->evidencekind = $kind ? get_string('evidencekind_' . $kind, 'local_mooc_audit') : null;
        $item->occurrence = $res['occurrence'] ?? '';
        $item->hasorientacao = $res['orientacao'] !== null && $res['orientacao'] !== '';
        return $item;
    }

    /**
     * Template item of every result, with its anchor and the cause relations (R12) between items.
     */
    protected function build_items(): void {
        $results = $this->report['results'];
        $this->items = [];
        foreach ($results as $i => $res) {
            $this->items[$i] = $this->item($res);
            $this->items[$i]->anchor = 'local_mooc_audit-occ-' . $i;
        }
        foreach (causes::groups($results) as $members) {
            if (count($members) < 2) {
                continue;
            }
            $main = $this->items[$members[0]];
            $main->alsosolves = get_string('cause_alsosolves', 'local_mooc_audit', count($members) - 1);
            foreach (array_slice($members, 1) as $i) {
                $this->items[$i]->samecause = get_string('cause_same', 'local_mooc_audit', $main->problem ?? $main->title);
                $this->items[$i]->samecauseanchor = $main->anchor;
            }
        }
    }

    /**
     * Counts of the top of the report (FR-002): problems, blocking, places, causes, warnings, assisted corrections.
     *
     * @param array $idx state => positions
     * @return stdClass
     */
    protected function summary(array $idx): stdClass {
        $results = $this->report['results'];
        $nc = $idx[status::NAO_CONFORME] ?? [];
        $places = [];
        $activities = [];
        $assisted = [];
        $blocking = 0;
        foreach ($nc as $i) {
            $res = $results[$i];
            $entity = messages::parse_occurrence($res['occurrence'] ?? null)['entity'];
            if (!empty($res['cmid'])) {
                $activities[(int) $res['cmid']] = true;
            } else {
                $places[$entity === 'course' || $entity === '' ? 'course:' . ($res['locus'] ?? '') : $entity] = true;
            }
            $blocking += ($res['gravidade'] ?? '') === status::BLOQUEANTE ? 1 : 0;
            if (!empty($this->items[$i]->correctionurl)) {
                $assisted[$this->items[$i]->correctionurl] = true;
            }
        }
        $facts = [
            ['label' => get_string('summary_problems', 'local_mooc_audit'), 'value' => count($nc)],
            ['label' => get_string('summary_blocking', 'local_mooc_audit'), 'value' => $blocking],
            ['label' => get_string('summary_causes', 'local_mooc_audit'),
                'value' => count(causes::groups($results, [status::NAO_CONFORME]))],
            ['label' => get_string('summary_activities', 'local_mooc_audit'), 'value' => count($activities)],
            ['label' => get_string('summary_configs', 'local_mooc_audit'), 'value' => count($places)],
            ['label' => get_string('summary_checks', 'local_mooc_audit'),
                'value' => count($idx[status::ATENCAO] ?? []) + count($idx[status::NAO_VERIFICAVEL] ?? [])],
        ];
        if ($this->withcorrections) {
            $facts[] = ['label' => get_string('summary_assisted', 'local_mooc_audit'), 'value' => count($assisted)];
        }
        return (object) ['clean' => !$nc, 'facts' => array_map(fn($f) => (object) $f, $facts),
            'cleantext' => get_string('summary_clean', 'local_mooc_audit', $this->report['summary']['regras'])];
    }

    /**
     * "Start here": up to three problems, worst first; among equals, those no other fix must precede.
     *
     * @param int[] $nc positions of the non conformities
     * @return array
     */
    protected function startwith(array $nc): array {
        $results = $this->report['results'];
        $nc = array_values(array_filter($nc, fn($i) => empty($this->items[$i]->samecause)));
        // Worst first; then what other fixes depend on (research R7); then access and certificate before the rest.
        $first = ['X-DEP-02|target_completion', 'CL-06|startdate', 'CL-07|enddate', 'CL-12|total_sum'];
        $impacts = ['access' => 0, 'certification' => 1, 'offer' => 2, 'policy' => 3];
        $rank = fn($i) => [status::severity_rank($results[$i]['gravidade']),
            in_array($results[$i]['rule'] . '|' . $this->items[$i]->check, $first, true) ? 0 : 1,
            $impacts[messages::impact($results[$i]) ?? 'policy'] ?? 3, empty($this->items[$i]->before) ? 0 : 1, $i];
        usort($nc, fn($a, $b) => $rank($a) <=> $rank($b));
        $out = [];
        foreach (array_slice($nc, 0, 3) as $i) {
            $item = $this->items[$i];
            $out[] = (object) ['problem' => $item->problem ?? ($item->rule . ' · ' . $item->title), 'anchor' => $item->anchor,
                'severitylabel' => $item->severitylabel, 'impact' => $item->impact,
                'action' => !empty($item->actions) ? $item->actions[0] : null];
        }
        return $out;
    }

    /**
     * Links to switch the view (FR-012).
     *
     * @return array
     */
    protected function views(): array {
        $out = [];
        foreach ([self::VIEW_PRIORITY, self::VIEW_ACTIVITY] as $view) {
            $url = new moodle_url($this->pageurl, ['view' => $view]);
            $url->remove_params('action', 'format');
            $out[] = (object) ['label' => get_string('view_' . $view, 'local_mooc_audit'), 'url' => $url->out(false),
                'current' => $this->view === $view];
        }
        return $out;
    }

    /**
     * Problems and warnings grouped by activity (course order), then section, then the course settings (FR-013).
     *
     * @param int[] $indexes positions of the non conformities and warnings
     * @return array
     */
    protected function by_activity(array $indexes): array {
        $results = $this->report['results'];
        $courseid = (int) $this->report['course']['id'];
        try {
            $modinfo = get_fast_modinfo($courseid);
            $cms = $modinfo->get_cms();
        } catch (\moodle_exception $e) {
            $modinfo = null;
            $cms = [];
        }
        $buckets = [];
        foreach ($indexes as $i) {
            $res = $results[$i];
            $entity = messages::parse_occurrence($res['occurrence'] ?? null)['entity'];
            if (!empty($res['cmid'])) {
                $key = 'cm:' . (int) $res['cmid'];
            } else if (str_starts_with($entity, 'sec:') && isset($res['section'])) {
                $key = 'sec:' . (int) $res['section'];
            } else {
                $key = 'course';
            }
            $buckets[$key][] = $i;
        }
        $order = array_map(fn($cmid) => 'cm:' . $cmid, array_keys($cms));
        $rest = array_values(array_diff(array_keys($buckets), $order, ['course']));
        sort($rest);
        $groups = [];
        foreach (array_merge($order, $rest, ['course']) as $key) {
            if (empty($buckets[$key])) {
                continue;
            }
            $members = $buckets[$key];
            $rank = fn($i) => [status::status_rank($results[$i]['status']), status::severity_rank($results[$i]['gravidade']), $i];
            usort($members, fn($a, $b) => $rank($a) <=> $rank($b));
            $worst = $results[$members[0]];
            $group = (object) ['items' => array_map(fn($i) => $this->items[$i], $members), 'count' => count($members),
                'worst' => get_string(status::string_key($worst['status']), 'local_mooc_audit') . ' · '
                    . get_string(status::severity_string_key($worst['gravidade']), 'local_mooc_audit'),
                'worstclass' => self::STATUS_CLASS[$worst['status']] ?? 'bg-secondary text-dark', 'url' => null];
            if (str_starts_with($key, 'cm:')) {
                $cm = $cms[(int) substr($key, 3)] ?? null;
                $group->name = $cm ? format_string($cm->name, true, ['context' => $cm->context, 'escape' => false])
                    : get_string('msg_removed', 'local_mooc_audit', (int) substr($key, 3));
                if ($cm && has_capability('moodle/course:manageactivities', $cm->context)) {
                    $group->url = (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false);
                }
            } else if (str_starts_with($key, 'sec:')) {
                $sec = $modinfo ? $modinfo->get_section_info((int) substr($key, 4)) : null;
                $group->name = get_string('place_section', 'local_mooc_audit', $sec ? get_section_name($courseid, $sec)
                    : substr($key, 4));
            } else {
                $group->name = get_string('view_coursesettings', 'local_mooc_audit');
            }
            $groups[] = $group;
        }
        return $groups;
    }

    /**
     * Manual checks (FR-040): what the audit does not prove, such as the actual issuing of the certificate.
     *
     * @return array
     */
    protected function manual(): array {
        $out = [];
        $policy = $this->policy ?? \local_mooc_audit\policy::load();
        foreach ($this->report['results'] as $i => $res) {
            if ($res['rule'] !== 'X-CERT-01' || messages::parse_occurrence($res['occurrence'] ?? null)['check'] !== 'chain') {
                continue;
            }
            $name = (string) $res['local'];
            try {
                $cm = !empty($res['cmid']) ? (get_fast_modinfo((int) $this->report['course']['id'])->get_cms()[(int) $res['cmid']]
                    ?? null) : null;
                $name = $cm ? format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]) : $name;
            } catch (\moodle_exception $e) {
                // Course no longer available: the stored location is kept.
                $cm = null;
            }
            $out[] = (object) [
                'title' => get_string('manual_certificate_title', 'local_mooc_audit', $name),
                'text' => get_string('manual_certificate_text', 'local_mooc_audit', $policy['certificate_min_grade'] ?? 60),
            ];
        }
        return $out;
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
                $rules[$res['rule']] = (object) ['rule' => $res['rule'], 'title' => $res['titulo'],
                    'category' => category::title($res['category']),
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
