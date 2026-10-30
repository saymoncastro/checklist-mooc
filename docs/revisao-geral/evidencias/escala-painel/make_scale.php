<?php
// Lab-only load script for A-05 (docs/revisao-geral): N light courses in sub-categories of "Escala", each with a history
// cloned from real runs of the fixture courses A (id 2) and B (id 3). Never part of the plugin.
// Usage (Moodle root): php /tmp/make_scale.php --n=3000 [--seed=7]

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!\local_mooc_audit\local\devsite::allowed($CFG->wwwroot, !empty($CFG->debugdeveloper))) {
    cli_error('Somente laboratório.');
}
[$o] = cli_get_params(['n' => 3000, 'seed' => 7, 'subcats' => 10]);
mt_srand((int) $o['seed']);
\core\session\manager::set_user(get_admin());

$sources = [];
foreach ([2, 3] as $cid) {
    $r = $DB->get_records('local_mooc_audit_run', ['courseid' => $cid, 'status' => 'complete'], 'id DESC', '*', 0, 1);
    if (!$r) {
        cli_error("curso $cid sem execução completa: rode cli/audit.php --courseid=$cid --record antes");
    }
    $sources[] = reset($r);
}

$root = $DB->get_record('course_categories', ['idnumber' => 'ESCALA_A05'])
    ?: core_course_category::create(['name' => 'Escala (A-05)', 'idnumber' => 'ESCALA_A05'])->get_db_record();
$subs = [];
for ($s = 1; $s <= (int) $o['subcats']; $s++) {
    $idn = sprintf('ESCALA_A05_%02d', $s);
    $subs[] = $DB->get_record('course_categories', ['idnumber' => $idn])
        ?: core_course_category::create(['name' => sprintf('Escala %02d', $s), 'idnumber' => $idn, 'parent' => $root->id])
            ->get_db_record();
}

$t0 = microtime(true);
$now = time();
$created = $runs = 0;
$buffer = [];
for ($i = 1; $i <= (int) $o['n']; $i++) {
    $short = sprintf('ESC_%05d', $i);
    if ($DB->record_exists('course', ['shortname' => $short])) {
        continue;
    }
    $cat = $subs[$i % count($subs)];
    $course = create_course((object) ['fullname' => "Sala de escala $i", 'shortname' => $short, 'category' => $cat->id,
        'startdate' => mktime(0, 0, 0, 1, 1, 2027), 'enddate' => mktime(23, 59, 0, 12, 31, 2027)]);
    $created++;
    $roll = mt_rand(1, 100);
    if ($roll <= 10) {
        continue; // Never audited.
    }
    $k = mt_rand(1, 6);
    $prev = 0;
    for ($j = 0; $j < $k; $j++) {
        $src = $sources[mt_rand(0, 1)];
        $finished = $now - ($k - $j) * mt_rand(2, 10) * DAYSECS;
        $failed = ($j === $k - 1 && $roll > 97);
        $row = clone $src;
        unset($row->id);
        $row->courseid = $course->id;
        $row->batchid = 0;
        $row->origin = 'batch';
        $row->timecreated = $finished - 5;
        $row->timestarted = $finished - 2;
        $row->timefinished = $finished;
        $row->previousrunid = 0;
        $row->coursecacherev = (int) $course->cacherev;
        if ($failed) {
            $row->status = 'failed';
            $row->overall = null;
            $row->report = $row->details = $row->summary = $row->evolution = null;
            $row->errors = json_encode([['code' => 'exception', 'message' => 'simulada']]);
        }
        $buffer[] = $row;
        $runs++;
    }
    if (count($buffer) >= 500) {
        $DB->insert_records('local_mooc_audit_run', $buffer);
        $buffer = [];
    }
    if ($i % 500 === 0) {
        cli_writeln(sprintf('%d salas, %d execuções, %.0fs', $i, $runs, microtime(true) - $t0));
    }
}
if ($buffer) {
    $DB->insert_records('local_mooc_audit_run', $buffer);
}

// A manager who is not a site admin (system role), to measure the capability path of get_user_capability_course.
$user = $DB->get_record('user', ['username' => 'gestor_escala']);
if (!$user) {
    $user = $DB->get_record('user', ['id' => user_create_user(['username' => 'gestor_escala', 'auth' => 'nologin',
        'firstname' => 'Gestor', 'lastname' => 'Escala', 'email' => 'gestor_escala@example.com', 'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id], false, false)]);
    role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $user->id, context_system::instance()->id);
}
cli_writeln(sprintf('pronto: %d salas novas, %d execuções novas, %.0fs; total de cursos %d, execuções %d', $created, $runs,
    microtime(true) - $t0, $DB->count_records('course') - 1, $DB->count_records('local_mooc_audit_run')));
