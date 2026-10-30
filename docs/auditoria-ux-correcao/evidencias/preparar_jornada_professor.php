<?php
// LABORATÓRIO APENAS — prepara a jornada de professor da auditoria de capacidade de correção
// (docs/auditoria-ux-correcao/README.md, seção 1): DUPLICA a sala de origem (o Curso B), sem usuários, numa sala nova
// "Sala de teste UX", e inscreve nela o usuário de teste prof.ux como professor editor (senha aleatória; acesso pelo
// "Acessar como" do administrador). Nenhuma sala existente é alterada: só a cópia recém‑criada.
//
// Proteções: recusa rodar fora de site local com depuração de desenvolvedor (mesma regra de local\devsite) e exige
// --origem e --confirmar.
//
// Uso (raiz do Moodle do contêiner de laboratório; no 5.x a raiz contém public/):
//   php /tmp/preparar_jornada_professor.php --origem=3 --confirmar
// Fora do plugin; não faz parte do pacote.

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/externallib.php');

if (!\local_mooc_audit\local\devsite::allowed($CFG->wwwroot, !empty($CFG->debugdeveloper))) {
    cli_error('Recusado: somente laboratório (host local e depuração de desenvolvedor).');
}
[$o] = cli_get_params(['origem' => 0, 'confirmar' => false]);
$source = (int) $o['origem'];
if (!$source || empty($o['confirmar'])) {
    cli_error('Uso: --origem=N --confirmar (N = sala de origem, por exemplo o Curso B; ela não é alterada)');
}
$src = get_course($source);
\core\session\manager::set_user(get_admin());
// Duplicação nativa (backup e restauração no mesmo site), sem usuários: a cópia nasce sem inscrições.
$copy = core_course_external::duplicate_course($source, 'Sala de teste UX (cópia de ' . $src->fullname . ')',
    'UX_TESTE_' . date('His'), (int) $src->category, 1, [['name' => 'users', 'value' => 0]]);
$courseid = (int) $copy['id'];

$u = $DB->get_record('user', ['username' => 'prof.ux', 'deleted' => 0]);
if (!$u) {
    $id = user_create_user((object) ['username' => 'prof.ux', 'firstname' => 'Professora', 'lastname' => 'Conteudista',
        'email' => 'prof.ux@example.com', 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'password' => random_string(20)], true, false);
    $u = $DB->get_record('user', ['id' => $id]);
}
$inst = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
$role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
enrol_get_plugin('manual')->enrol_user($inst, $u->id, $role->id);

$ctx = context_course::instance($courseid);
foreach (['local/mooc_audit:audit', 'local/mooc_audit:preparecorrection', 'local/mooc_audit:applycorrection',
    'moodle/course:changecategory', 'moodle/course:update', 'moodle/course:manageactivities', 'enrol/self:config',
    'moodle/grade:manage', 'moodle/backup:backuptargetimport', 'moodle/site:manageblocks'] as $cap) {
    cli_writeln(str_pad($cap, 42) . (has_capability($cap, $ctx, $u) ? 'sim' : 'não'));
}
// Num curso existente, o seletor de categoria de course/edit.php lista só as categorias em que o usuário tem
// moodle/course:changecategory no contexto da categoria (course/edit_form.php), além da atual.
\core\session\manager::set_user($u);
cli_writeln('categorias no seletor (além da atual): ' . count(core_course_category::make_categories_list(
    'moodle/course:changecategory')));
cli_writeln("usuário prof.ux id {$u->id}; sala de teste $courseid (cópia de $source, que não foi alterada)");
