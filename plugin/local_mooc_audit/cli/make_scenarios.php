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
 * CLI (TEST ENVIRONMENTS ONLY): builds the experimental scenarios A–G of the comparison between offers (specs/003)
 * with the component data generator (the same definitions used by tests/comparator_test.php) and Moodle's native
 * import (backup/restore in import mode, no user data).
 *
 *   A  reference course of the previous year → import adjusted to the new offer
 *   B  inconsistent course → import adjusted (inherited problems, duplicated news forum)
 *   C  glossary with entries used as game source → import (entries are not imported)
 *   D  reference course → import adjusted, then attempts/grade/completion of a quiz changed in the target
 *   E  three resources with files → import, then record removed / content lost / resource hidden in the target
 *   F  repeated names (Libras version, identical copies) → import
 *   G  reference course → import WITHOUT the new offer adjustments
 *
 * php local/mooc_audit/cli/make_scenarios.php --year=2027
 *
 * Refuses to run unless the site is local (wwwroot with localhost) AND developer debugging is on. Courses with the
 * same short names (CEN_*) are deleted first. Not part of a production package. Prints the
 * pairs source → target as JSON.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
if (!class_exists('PHPUnit\\Framework\\TestCase') && is_readable($CFG->dirroot . '/vendor/autoload.php')) {
    require_once($CFG->dirroot . '/vendor/autoload.php');
}
if (!class_exists('PHPUnit\\Framework\\TestCase')) {
    cli_error('PHPUnit is required to build the scenario courses '
        . '(run "composer install" at the Moodle root of the development site).');
}
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once(__DIR__ . '/../tests/generator/lib.php');

[$options] = cli_get_params(['year' => 0, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Cria os cenários A–G da comparação entre ofertas. Opção --year=AAAA da nova oferta (padrão: ano seguinte);'
        . ' as salas de origem são do ano anterior.');
    exit(0);
}
// Stricter than make_fixtures.php because it deletes courses: local wwwroot AND developer debugging, both required.
if (strpos($CFG->wwwroot, 'localhost') === false || empty($CFG->debugdeveloper)) {
    cli_error('Recusado: este script só roda em ambiente local de desenvolvimento (wwwroot localhost e debug de desenvolvedor).');
}
\core\session\manager::set_user(get_admin());
$year = (int) ($options['year'] ?: (date('Y') + 1));
$prev = $year - 1;

$names = ['CEN_A', 'CEN_A_NOVA', 'CEN_B', 'CEN_B_NOVA', 'CEN_C', 'CEN_C_NOVA', 'CEN_D_NOVA', 'CEN_E', 'CEN_E_NOVA', 'CEN_F',
    'CEN_F_NOVA', 'CEN_G_NOVA'];
foreach ($names as $shortname) {
    if ($existing = $DB->get_record('course', ['shortname' => $shortname])) {
        delete_course($existing, false);
    }
}
$gen = new local_mooc_audit_generator(new testing_data_generator());
$out = [];

[$a] = $gen->create_course_a($prev, 'CEN_A');
$out['A'] = [$a->id, $gen->import_into_new_course($a, 'CEN_A_NOVA', $year)->id];
[$b] = $gen->create_course_b($prev, 'CEN_B');
$out['B'] = [$b->id, $gen->import_into_new_course($b, 'CEN_B_NOVA', $year)->id];
[$c] = $gen->create_glossary_game_course($prev, 'CEN_C');
$out['C'] = [$c->id, $gen->import_into_new_course($c, 'CEN_C_NOVA', $year)->id];
$d = $gen->import_into_new_course($a, 'CEN_D_NOVA', $year);
foreach (get_fast_modinfo($d)->get_instances_of('quiz') as $cm) {
    if ($cm->name === 'Questionário 1') {
        $gen->change_quiz_assessment($cm->id, 1, 40);
    }
}
$out['D'] = [$a->id, $d->id];
[$e] = $gen->create_files_course($prev, 'CEN_E');
$etarget = $gen->import_into_new_course($e, 'CEN_E_NOVA', $year);
$gen->break_files($etarget);
$out['E'] = [$e->id, $etarget->id];
[$f] = $gen->create_repeated_names_course($prev, 'CEN_F');
$out['F'] = [$f->id, $gen->import_into_new_course($f, 'CEN_F_NOVA', $year)->id];
$out['G'] = [$a->id, $gen->import_into_new_course($a, 'CEN_G_NOVA', $year, false)->id];

cli_writeln(json_encode(array_map(fn($p) => ['source' => (int) $p[0], 'target' => (int) $p[1]], $out), JSON_PRETTY_PRINT));
