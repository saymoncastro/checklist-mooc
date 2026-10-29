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
 * Measurement script (LAB ONLY, specs/005 R13): dashboard build and rendering time for the admin, and storage used by the
 * history. Run from the Moodle root: php /tmp/measure.php
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');

use local_mooc_audit\local\history\dashboard;

\core\session\manager::set_user(get_admin());
$PAGE->set_url('/local/mooc_audit/dashboard.php');
$PAGE->set_context(context_system::instance());
for ($i = 0; $i < 3; $i++) {
    $t0 = microtime(true);
    $reads = $DB->perf_get_reads();
    $data = dashboard::build((int) $USER->id, []);
    $tbuild = microtime(true) - $t0;
    $html = $PAGE->get_renderer('local_mooc_audit')->render(new \local_mooc_audit\output\dashboard_page(
        $data,
        core_course_category::make_categories_list(),
        true,
        \local_mooc_audit\local\history\batch_manager::recent(10),
        dashboard::removed_courses(),
        0,
        50,
        new moodle_url('/local/mooc_audit/dashboard.php')
    ));
    printf("painel: %d salas, build %.3fs, render %.3fs, leituras %d, HTML %d bytes\n", count($data['rows']), $tbuild,
        microtime(true) - $t0 - $tbuild, $DB->perf_get_reads() - $reads, strlen($html));
}
$size = $DB->get_records_sql("SELECT relname, pg_total_relation_size(c.oid) AS bytes FROM pg_class c
    WHERE relname IN ('m_local_mooc_audit_run', 'm_local_mooc_audit_policy', 'm_local_mooc_audit_batch')");
foreach ($size as $s) {
    printf("%s: %.1f kB\n", $s->relname, $s->bytes / 1024);
}
$r = $DB->get_record_sql("SELECT COUNT(1) AS n, AVG(octet_length(report)) AS avgreport, MAX(octet_length(report)) AS maxreport,
    AVG(octet_length(details)) AS avgdetails FROM {local_mooc_audit_run} WHERE report IS NOT NULL");
printf("execuções com relatório: %d; relatório médio %.1f kB (máx. %.1f kB); detalhes médios %.1f kB\n", $r->n,
    $r->avgreport / 1024, $r->maxreport / 1024, $r->avgdetails / 1024);
