<?php
// Lab-only measurement for A-05 (docs/revisao-geral): dashboard build and rendering for a site admin and for a
// non-admin system manager, in several filter scenarios, with a breakdown of the main steps. Never part of the plugin.
// Usage (Moodle root): php /tmp/measure_scale.php [--repeat=3]

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

use local_mooc_audit\local\history\access;
use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\dashboard;
use local_mooc_audit\local\history\run_store;

[$o] = cli_get_params(['repeat' => 3]);
$sub = (int) $DB->get_field('course_categories', 'id', ['idnumber' => 'ESCALA_A05_01']);
$scenarios = [
    'todas as salas' => [],
    'uma subcategoria (~300)' => ['categoryid' => $sub, 'subcats' => 0],
    'situação: com bloqueantes' => ['situation' => dashboard::BLOCKING],
    'busca por nome' => ['q' => 'Sala de escala 12'],
];
$users = ['admin' => get_admin(), 'gerente (não admin)' => $DB->get_record('user', ['username' => 'gestor_escala'])];

$PAGE->set_url('/local/mooc_audit/dashboard.php');
$PAGE->set_context(context_system::instance());
$renderer = $PAGE->get_renderer('local_mooc_audit');

printf("Moodle %s · PHP %s · cursos %d · execuções %d (válidas %d)\n\n", $CFG->release, PHP_VERSION,
    $DB->count_records('course') - 1, $DB->count_records(run_store::TABLE),
    $DB->count_records_select(run_store::TABLE, "status IN ('complete', 'partial')"));
printf("%-22s %-28s %6s %9s %9s %8s %8s %9s %8s\n", 'usuário', 'cenário', 'linhas', 'build(s)', 'render(s)',
    'leituras', 'pico MB', 'perm.(s)', 'runs(s)');
foreach ($users as $ulabel => $user) {
    \core\session\manager::set_user($user);
    foreach ($scenarios as $slabel => $filters) {
        for ($i = 0; $i < (int) $o['repeat']; $i++) {
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $base = memory_get_usage();
            $reads = $DB->perf_get_reads();
            $t0 = microtime(true);
            $data = dashboard::build((int) $user->id, $filters);
            $tbuild = microtime(true) - $t0;
            $html = $renderer->render(new \local_mooc_audit\output\dashboard_page($data,
                core_course_category::make_categories_list(), true, batch_manager::recent(10), dashboard::removed_courses(), 0,
                50, new moodle_url('/local/mooc_audit/dashboard.php')));
            $trender = microtime(true) - $t0 - $tbuild;
            $peak = (memory_get_peak_usage() - $base) / 1048576;
            $r = $DB->perf_get_reads() - $reads;
            // Breakdown (separate calls, warm caches after the first repetition).
            $t1 = microtime(true);
            $ids = array_keys(access::auditable_course_ids((int) $user->id));
            $tperm = microtime(true) - $t1;
            $t2 = microtime(true);
            run_store::latest_per_course(run_store::valid_states(), $ids, 'r.id, r.courseid, r.summary, r.details');
            $truns = microtime(true) - $t2;
            printf("%-22s %-28s %6d %9.3f %9.3f %8d %8.1f %9.3f %8.3f%s\n", $ulabel, $slabel, count($data['rows']), $tbuild,
                $trender, $r, $peak, $tperm, $truns, $i === 0 ? '  (1ª)' : '');
            unset($data, $html);
        }
    }
}
$size = $DB->get_record_sql("SELECT pg_total_relation_size('m_local_mooc_audit_run') AS bytes");
printf("\nm_local_mooc_audit_run: %.1f MB\n", $size->bytes / 1048576);
