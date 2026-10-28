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
 * CLI (TEST ENVIRONMENTS ONLY): creates the two reference courses of the project using the component data generator
 * (tests/generator/lib.php), so that PHPUnit and the lab share one definition of the fixtures.
 *
 *   Course A — reference configuration according to the institutional policy.
 *   Course B — deliberately inconsistent configuration (known, documented problems, including a file whose content
 *              is removed from the file store).
 *
 * php local/mooc_audit/cli/make_fixtures.php --year=2027
 *
 * Refuses to run unless the site is a local/dev site ($CFG->wwwroot contains localhost or debugdeveloper is on).
 * Existing courses with the same shortnames are deleted first.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once(__DIR__ . '/../tests/generator/lib.php');

list($options) = cli_get_params(['year' => 0, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Cria os cursos de teste A (referência) e B (inconsistente). Opção --year=AAAA (padrão: ano seguinte).');
    exit(0);
}
if (strpos($CFG->wwwroot, 'localhost') === false && empty($CFG->debugdeveloper)) {
    cli_error('Recusado: este script só roda em ambiente local de desenvolvimento.');
}
\core\session\manager::set_user(get_admin());
$year = (int) ($options['year'] ?: (date('Y') + 1));

foreach (['MOOC_TESTE_A', 'MOOC_TESTE_B'] as $shortname) {
    if ($existing = $DB->get_record('course', ['shortname' => $shortname])) {
        delete_course($existing, false);
    }
}
$generator = new local_mooc_audit_generator(new testing_data_generator());
[$a, $idsa] = $generator->create_course_a($year);
[$b, $idsb] = $generator->create_course_b($year);

cli_writeln(json_encode(['A' => ['courseid' => $a->id, 'cmids' => $idsa], 'B' => ['courseid' => $b->id, 'cmids' => $idsb]],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
