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

/**
 * Minimal web page: audit a course by id (read-only). Requires local/mooc_audit:audit in the course context.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

require_login();
$PAGE->set_url(new moodle_url('/local/mooc_audit/index.php', ['courseid' => $courseid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_mooc_audit'));
$PAGE->set_heading(get_string('pluginname', 'local_mooc_audit'));

if ($courseid) {
    $context = context_course::instance($courseid);
    require_capability('local/mooc_audit:audit', $context);
    $report = \local_mooc_audit\engine::audit($courseid);
    if ($format === 'json') {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

echo $OUTPUT->header();
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);
echo html_writer::label(get_string('courseid', 'local_mooc_audit'), 'courseid') . ' ';
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'courseid', 'id' => 'courseid', 'value' => $courseid, 'min' => 1]) . ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('runaudit', 'local_mooc_audit')]);
echo html_writer::end_tag('form');

if ($courseid) {
    $s = $report['summary'];
    echo html_writer::tag('h3', s($report['course']['fullname']) . ' (id ' . $courseid . ')');
    echo html_writer::tag('p', 'Resumo: ' . s(json_encode($s['por_status'])) . ' · não conformes por gravidade: ' . s(json_encode($s['nao_conformes_por_gravidade'], JSON_UNESCAPED_UNICODE))
        . ' · ' . html_writer::link(new moodle_url('/local/mooc_audit/index.php', ['courseid' => $courseid, 'format' => 'json']), 'JSON'));
    $table = new html_table();
    $table->head = ['Estado', 'Regra', 'Gravidade', 'Local', 'Evidência', 'Encontrado', 'Esperado', 'Orientação'];
    $order = ['NAO_CONFORME' => 0, 'ATENCAO' => 1, 'ERRO' => 2, 'NAO_VERIFICAVEL' => 3, 'NAO_APLICAVEL' => 4, 'CONFORME' => 5];
    $results = $report['results'];
    usort($results, fn($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));
    foreach ($results as $r) {
        $table->data[] = [s($r['status']), s($r['rule']) . '<br><small>' . s($r['titulo']) . '</small>', s($r['gravidade']), s($r['local']),
            s(mb_substr((string) $r['evidencia'], 0, 300)), s((string) $r['encontrado']), s((string) $r['esperado']), s((string) $r['orientacao'])];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
