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
 * CLI: compare a source course with an imported target course (read-only).
 *
 * php local/mooc_audit/cli/compare.php --source=4 --target=6 [--json] [--out=FILE]
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['source' => 0, 'target' => 0, 'json' => false, 'out' => '', 'help' => false], ['h' => 'help']);
if ($options['help'] || !$options['source'] || !$options['target']) {
    cli_writeln('Uso: --source=COURSEID --target=COURSEID [--json] [--out=FILE]');
    exit(0);
}
\core\session\manager::set_user(get_admin());
$src = \local_mooc_audit\snapshot::build((int) $options['source']);
$dst = \local_mooc_audit\snapshot::build((int) $options['target']);
$report = \local_mooc_audit\comparator::compare($src, $dst);
if ($options['out'] !== '') {
    file_put_contents($options['out'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo $options['json'] ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    : \local_mooc_audit\comparator::render_text($report);
