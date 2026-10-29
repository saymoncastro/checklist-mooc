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
 * CLI: audit a course (read-only).
 *
 * php local/mooc_audit/cli/audit.php --courseid=3 [--userid=5] [--policy=/path/policy.json] [--json] [--out=/path/report.json]
 * [--snapshot]
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'courseid' => 0, 'userid' => 0, 'policy' => '', 'json' => false, 'out' => '', 'snapshot' => false, 'help' => false,
], ['h' => 'help', 'c' => 'courseid']);

if ($options['help'] || empty($options['courseid'])) {
    cli_writeln(implode("\n", [
        'Auditoria MOOC somente leitura de um curso.',
        '',
        'Opções:',
        '  --courseid=N   curso a auditar (obrigatório)',
        "  --userid=N     usuário cuja visão define 'uservisible' (0 = admin)",
        '  --policy=FILE  JSON com parâmetros de política que sobrescrevem os padrões',
        '  --json         imprime o relatório em JSON (contrato 1.0) em vez de texto;',
        '                 schema em local/mooc_audit/schema/report-v1.0.schema.json',
        '  --out=FILE     grava o JSON do relatório (mesmo contrato e codificação da página) no arquivo',
        '  --snapshot     inclui o retrato completo no JSON',
    ]));
    exit(0);
}

\core\session\manager::set_user(get_admin());
$override = null;
if ($options['policy'] !== '') {
    $text = is_readable($options['policy']) ? file_get_contents($options['policy']) : false;
    $error = get_string('filenotfound', 'error');
    $override = $text === false ? null : \local_mooc_audit\policy::decode_object($text, $error);
    if ($override === null) {
        cli_error(get_string('policyjson_invalid', 'local_mooc_audit', $error));
    }
}

$report = \local_mooc_audit\engine::audit(
    (int) $options['courseid'],
    $override,
    (int) $options['userid'],
    (bool) $options['snapshot']
);

if ($options['out'] !== '') {
    file_put_contents($options['out'], \local_mooc_audit\engine::to_json($report));
}
if ($options['json']) {
    echo \local_mooc_audit\engine::to_json($report), "\n";
} else {
    echo \local_mooc_audit\engine::render_text($report);
}
