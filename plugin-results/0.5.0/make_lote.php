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
 * Measurement script (LAB ONLY, outside the plugin, specs/005 R13): N synthetic rooms alternating the reference course
 * A (no non-conformity) and the inconsistent course B, in a category of their own, to measure batch audits and the
 * dashboard. Copy into the container and run from the Moodle root: php /tmp/make_lote.php --n=100
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
require_once($CFG->dirroot . '/local/mooc_audit/tests/generator/lib.php');

if (strpos($CFG->wwwroot, 'localhost') === false) {
    cli_error('Somente laboratório.');
}
[$options] = cli_get_params(['n' => 40, 'year' => 2027]);
\core\session\manager::set_user(get_admin());
$cat = $DB->get_record('course_categories', ['idnumber' => 'LOTE_MEDICAO'])
    ?: core_course_category::create(['name' => 'Lote de medição', 'idnumber' => 'LOTE_MEDICAO'])->get_db_record();
$gen = new local_mooc_audit_generator(new testing_data_generator());
$t0 = microtime(true);
for ($i = 1; $i <= (int) $options['n']; $i++) {
    $which = $i % 2 ? 'A' : 'B';
    $short = sprintf('LOTE_%s_%03d', $which, $i);
    if ($DB->record_exists('course', ['shortname' => $short])) {
        continue;
    }
    [$course] = $which === 'A' ? $gen->create_course_a((int) $options['year'], $short)
        : $gen->create_course_b((int) $options['year'], $short);
    move_courses([$course->id], $cat->id);
}
cli_writeln(sprintf('categoria %d: %d salas (%.0fs)', $cat->id, $DB->count_records('course', ['category' => $cat->id]),
    microtime(true) - $t0));
