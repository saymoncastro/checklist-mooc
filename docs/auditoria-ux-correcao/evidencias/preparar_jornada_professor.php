<?php
// Prepara a jornada de UX no site descartável dist53: usuário professor editor na cópia do Curso B.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
$courseid = (int) $argv[1];
$u = $DB->get_record('user', ['username' => 'prof.ux']);
if (!$u) {
    $id = user_create_user((object) ['username' => 'prof.ux', 'firstname' => 'Professora', 'lastname' => 'Conteudista',
        'email' => 'prof.ux@example.com', 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'password' => random_string(20), 'lang' => 'pt_br'], true, false);
    $u = $DB->get_record('user', ['id' => $id]);
}
$course = get_course($courseid);
$DB->set_field('course', 'fullname', 'Sala de teste UX (cópia do Curso B)', ['id' => $courseid]);
$DB->set_field('course', 'shortname', 'UX_TESTE', ['id' => $courseid]);
rebuild_course_cache($courseid, true);
$manual = enrol_get_plugin('manual');
$inst = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
$role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$manual->enrol_user($inst, $u->id, $role->id);
$ctx = context_course::instance($courseid);
foreach (['local/mooc_audit:audit', 'local/mooc_audit:preparecorrection', 'local/mooc_audit:applycorrection',
    'moodle/course:changecategory', 'moodle/course:update', 'moodle/course:manageactivities', 'enrol/self:config',
    'moodle/grade:manage', 'moodle/backup:backuptargetimport', 'moodle/site:manageblocks'] as $cap) {
    echo str_pad($cap, 42), has_capability($cap, $ctx, $u) ? 'sim' : 'não', "\n";
}
// Categorias em que pode criar cursos (o seletor de categoria de course/edit.php só lista estas).
echo 'categorias com course:create: ', count(core_course_category::make_categories_list('moodle/course:create', $u->id)), "\n";
echo "user id {$u->id}\n";
