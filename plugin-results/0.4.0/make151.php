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
 * Measurement script (LAB ONLY, outside the plugin): a synthetic 151-module course (pages with chained completion
 * restrictions, labels, quizzes with questions, URLs, forums, resources with files) and its import into a new course,
 * to measure the comparison of two rooms of the size used in the proof of concept.
 *
 * Copy into the container and run from the Moodle root: php /tmp/make151.php
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
\core\session\manager::set_user(get_admin());
foreach (['MEDE151', 'MEDE151_NOVA'] as $shortname) {
    if ($existing = $DB->get_record('course', ['shortname' => $shortname])) {
        delete_course($existing, false);
    }
}
$gen = new local_mooc_audit_generator(new testing_data_generator());
$tz = new DateTimeZone(core_date::get_server_timezone());
$course = create_course((object) ['fullname' => 'Sala de medição (151 módulos)', 'shortname' => 'MEDE151',
    'category' => $gen->target_category()->id, 'format' => 'topics', 'numsections' => 6,
    'startdate' => (new DateTime('2026-01-01 00:00', $tz))->getTimestamp(),
    'enddate' => (new DateTime('2026-12-31 23:59', $tz))->getTimestamp(), 'enablecompletion' => 1, 'lang' => 'pt_br']);
$fs = get_file_storage();
$n = count(get_fast_modinfo($course)->get_cms());
$prev = null;
$quizzes = [];
for ($s = 1; $n < 151; $s = $s % 6 + 1) {
    $page = $gen->add_module($course, 'page', $s, ['name' => "Página $n", 'content' => "<p>Conteúdo $n</p>",
        'contentformat' => FORMAT_HTML, 'display' => 5, 'printintro' => 0, 'printlastmodified' => 0,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
    if ($prev) {
        $gen->set_availability($page->coursemodule, ['op' => '&', 'showc' => [true],
            'c' => [['type' => 'completion', 'cm' => $prev, 'e' => 1]]]);
    }
    $prev = $page->coursemodule;
    $n++;
    $gen->add_module($course, 'label', $s, ['introeditor' => ['text' => "<p>Rótulo $n</p>", 'format' => FORMAT_HTML,
        'itemid' => 0]]);
    $n++;
    if ($n % 7 === 0) {
        $q = $gen->add_module($course, 'quiz', $s, $gen->quiz_data("Questionário $n", 2, 10, 0));
        $gen->add_questions($q, 3);
        $quizzes[] = $q->coursemodule;
        $n++;
    }
    if ($n % 9 === 0) {
        $gen->add_module($course, 'url', $s, ['name' => "Link $n", 'externalurl' => "https://example.org/$n", 'display' => 0]);
        $n++;
    }
    if ($n % 13 === 0) {
        $gen->add_module($course, 'forum', $s, ['name' => "Fórum $n", 'type' => 'general']);
        $n++;
    }
    if ($n % 17 === 0) {
        $res = $gen->add_module($course, 'resource', $s, ['name' => "Arquivo $n", 'display' => 0, 'printintro' => 0,
            'files' => 0]);
        $fs->create_file_from_string(['contextid' => context_module::instance($res->coursemodule)->id,
            'component' => 'mod_resource', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/',
            'filename' => "arquivo$n.txt"], "arquivo $n");
        $n++;
    }
}
rebuild_course_cache($course->id, true);
$t0 = microtime(true);
$target = $gen->import_into_new_course(get_course($course->id), 'MEDE151_NOVA', 2027);
$timport = microtime(true) - $t0;
cli_writeln(json_encode(['source' => (int) $course->id, 'target' => (int) $target->id,
    'source_modules' => count(get_fast_modinfo($course->id)->get_cms()),
    'target_modules' => count(get_fast_modinfo($target->id)->get_cms()), 'import_s' => round($timport, 2)]));
