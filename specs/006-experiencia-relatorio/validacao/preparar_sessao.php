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
 * LABORATÓRIO APENAS — prepara uma sessão do teste de linha de base da spec 006 (FR-029) para um participante.
 *
 * Cria duas salas novas só desse participante, na categoria em que o gerador as coloca (a mesma do cenário de
 * referência; mover para outra categoria acrescentaria uma não conformidade do CL-05) — uma cópia do Curso B (bloqueantes, correção
 * CL-13 disponível) e uma "sala com alertas" (Curso A + lição avaliada, página oculta e seção com nome genérico, que
 * produzem alertas e uma verificação "não verificável") —, um usuário de teste com o papel do perfil e uma auditoria
 * registrada em cada sala, para que a página já mostre o resultado ao abrir. Cada sessão recebe salas novas: a tarefa
 * de correção não interfere na sessão seguinte.
 *
 * Fora do plugin; não faz parte do pacote. Recusa rodar fora de host local com depuração de desenvolvedor.
 * Uso (raiz do Moodle, contêiner de laboratório; exige o autoloader do Composer, como cli/make_fixtures.php):
 *   php /tmp/preparar_sessao.php --participante=P01 --perfil=professor --senha='<senha escolhida pelo moderador>'
 *   perfis: professor (professor editor nas duas salas) | tecnica (gerente nas duas salas)
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/vendor/autoload.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/mooc_audit/tests/generator/lib.php');

$host = strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
$local = $host === 'localhost' || str_ends_with($host, '.localhost') || $host === '127.0.0.1' || $host === '[::1]';
if (!$local || empty($CFG->debugdeveloper)) {
    cli_error('Recusado: somente laboratório (host local e depuração de desenvolvedor).');
}

[$o] = cli_get_params(['participante' => '', 'perfil' => 'professor', 'senha' => '', 'ano' => 0]);
$p = strtoupper(trim((string) $o['participante']));
if (!preg_match('/^P\d{2}$/', $p) || !in_array($o['perfil'], ['professor', 'tecnica'], true) || $o['senha'] === '') {
    cli_error("Uso: --participante=P01 --perfil=professor|tecnica --senha='...' [--ano=AAAA]");
}
// O gerador omite em silêncio certificado, jogo e bloco de progresso quando o plugin falta, o que muda o gabarito do
// roteiro (T1, T7): o ambiente de referência exige os três.
$missing = [];
foreach (['mod_simplecertificate', 'mod_game', 'block_completion_progress'] as $component) {
    if (!core_plugin_manager::instance()->get_plugin_info($component)) {
        $missing[] = $component;
    }
}
if ($missing) {
    cli_error('Recusado: fora do ambiente de referência do roteiro; faltam os plugins ' . implode(', ', $missing) . '.');
}
$year = (int) $o['ano'] ?: (int) date('Y') + 1;
\core\session\manager::set_user(get_admin());

$gen = new local_mooc_audit_generator(new testing_data_generator());
$suffix = date('His');

// Sala 1: cópia do Curso B.
[$b] = $gen->create_course_b($year, "LB_{$p}_B_$suffix");

// Sala 2: Curso A com alertas e uma verificação não verificável.
[$a] = $gen->create_course_a($year, "LB_{$p}_ALERTAS_$suffix");
$DB->set_field('course', 'fullname', 'Curso A — com alertas', ['id' => $a->id]);
$lesson = (new testing_data_generator())->create_module('lesson', ['course' => $a->id, 'section' => 2,
    'name' => 'Lição complementar', 'grade' => 10]);
$page = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
    WHERE cm.course = ? ORDER BY cm.id DESC", [$a->id], IGNORE_MULTIPLE);
if ($page) {
    set_coursemodule_visible($page->id, 0);
}
$DB->set_field('course_sections', 'name', 'Topic 3', ['course' => $a->id, 'section' => 3]);
rebuild_course_cache($a->id, true);

// Participante.
$username = strtolower("lb_$p");
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    $user = $DB->get_record('user', ['id' => user_create_user(['username' => $username, 'auth' => 'manual',
        'password' => $o['senha'], 'firstname' => 'Participante', 'lastname' => $p,
        'email' => "$username@example.com", 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id], true, false)]);
} else {
    update_internal_user_password($user, $o['senha']);
}
foreach ([$b, $a] as $course) {
    $role = $o['perfil'] === 'professor' ? 'editingteacher' : 'manager';
    (new testing_data_generator())->enrol_user($user->id, $course->id, $role);
}

// Auditoria registrada em cada sala (a página mostra o último resultado guardado; decisão 12.1).
foreach ([$b, $a] as $course) {
    $origin = \local_mooc_audit\local\history\run_store::ORIGIN_CLI;
    [$run] = \local_mooc_audit\local\history\recorder::record_now((int) $course->id, $origin, (int) get_admin()->id);
    $summary = json_decode((string) $run->summary, true) ?: [];
    $counts = $summary['por_status'] ?? [];
    $url = (new moodle_url('/local/mooc_audit/index.php', ['courseid' => $course->id]))->out(false);
    $name = $DB->get_field('course', 'fullname', ['id' => $course->id]);
    cli_writeln("$url → $name ($run->overall, não conformes " . ($counts['NAO_CONFORME'] ?? 0) . ', atenção '
        . ($counts['ATENCAO'] ?? 0) . ', não verificáveis ' . ($counts['NAO_VERIFICAVEL'] ?? 0) . ')');
}
$quiz = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
    JOIN {quiz} q ON q.id = cm.instance WHERE cm.course = ? AND q.attempts = 1", [$b->id], IGNORE_MULTIPLE);
cli_writeln("Participante $p ($o[perfil]): usuário $username; Curso B id $b->id (Questionário 1: cmid " . ($quiz->id ?? '?')
    . "); sala com alertas id $a->id.");
