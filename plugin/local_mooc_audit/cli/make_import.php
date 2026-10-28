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
 * CLI (TEST ENVIRONMENTS ONLY): simulates a reoffer without MOOC Admin.
 *
 * 1. Creates an empty target course in the same category.
 * 2. Imports the source course content with Moodle's native import (core_course_external::import_course, the same
 *    mechanism as Course administration > Import), without user data.
 * 3. Applies the adjustments expected for the new offer (dates, self-enrolment dates) unless --no-adjust is given.
 *
 * php local/mooc_audit/cli/make_import.php --source=8 --year=2027 [--no-adjust]
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->libdir . '/enrollib.php');

list($options) = cli_get_params(['source' => 0, 'year' => 0, 'no-adjust' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || !$options['source']) {
    cli_writeln('Uso: --source=COURSEID [--year=AAAA] [--no-adjust]');
    exit(0);
}
if (strpos($CFG->wwwroot, 'localhost') === false && empty($CFG->debugdeveloper)) {
    cli_error('Recusado: este script só roda em ambiente local de desenvolvimento.');
}
\core\session\manager::set_user(get_admin());
$source = get_course((int) $options['source']);
$year = (int) ($options['year'] ?: (date('Y') + 1));
$tz = new DateTimeZone(core_date::get_server_timezone());
$start = (new DateTime("$year-01-01 00:00", $tz))->getTimestamp();
$end = (new DateTime("$year-12-31 23:59", $tz))->getTimestamp();

$shortname = $source->shortname . '_' . $year;
if ($existing = $DB->get_record('course', ['shortname' => $shortname])) {
    delete_course($existing, false);
}
// 1. Empty target course (what a reoffer would start from).
$target = create_course((object) ['fullname' => $source->fullname, 'shortname' => $shortname, 'category' => $source->category, 'format' => $source->format,
    'numsections' => 1, 'startdate' => $start, 'enddate' => $end, 'visible' => 1, 'enablecompletion' => 1, 'showcompletionconditions' => 1,
    'summary' => $source->summary, 'summaryformat' => FORMAT_HTML, 'lang' => $source->lang]);
cli_writeln("Curso de destino criado: id {$target->id} ($shortname)");

// 2. Native import (course content only, no users), with default import settings.
$t0 = microtime(true);
$result = core_course_external::import_course($source->id, $target->id, 0, []);
cli_writeln('Importação nativa concluída em ' . round(microtime(true) - $t0, 1) . 's' . ($result === null ? '' : ' ' . json_encode($result)));

// 3. Expected adjustments of a new offer.
if (!$options['no-adjust']) {
    $target = get_course($target->id);
    $target->startdate = $start;
    $target->enddate = $end;
    update_course($target);
    $plugin = enrol_get_plugin('self');
    foreach (enrol_get_instances($target->id, false) as $inst) {
        if ($inst->enrol === 'self') {
            $plugin->update_instance($inst, (object) ['status' => ENROL_INSTANCE_ENABLED, 'enrolstartdate' => $start, 'enrolenddate' => $end, 'customint6' => 1, 'roleid' => 5, 'name' => '']);
        }
    }
    cli_writeln('Ajustes da nova oferta aplicados (datas do curso e da autoinscrição).');
}
rebuild_course_cache($target->id, true);
cli_writeln(json_encode(['source' => $source->id, 'target' => $target->id]));
