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
 * CLI: compare two offers of a course, source (previous offer) and target (new offer). Read-only.
 *
 * php local/mooc_audit/cli/compare.php --source=2 --target=6 [--pairs=SRC-TGT,SRC-TGT] [--policy=FILE] [--json] [--out=FILE]
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(
    ['source' => 0, 'target' => 0, 'pairs' => '', 'policy' => '', 'json' => false, 'out' => '', 'help' => false],
    ['h' => 'help']
);
if ($options['help'] || !$options['source'] || !$options['target']) {
    cli_writeln(implode("\n", [
        'Comparação somente leitura entre duas ofertas de um curso.',
        '',
        'Opções:',
        '  --source=N     curso da oferta anterior (obrigatório)',
        '  --target=N     curso da nova oferta (obrigatório)',
        '  --pairs=L      correspondências manuais "cmidOrigem-cmidDestino", separadas por vírgula',
        '  --policy=FILE  JSON com parâmetros de política aplicados às duas salas',
        '                 (o ano da origem continua sendo o da sua data de início)',
        '  --json         imprime o contrato comparison 1.0 (schema/comparison-v1.0.schema.json)',
        '  --out=FILE     grava o JSON no arquivo',
    ]));
    exit(0);
}
\core\session\manager::set_user(get_admin());
foreach (['source', 'target'] as $side) {
    $id = (int) $options[$side];
    if ($id == SITEID || !$DB->record_exists('course', ['id' => $id])) {
        cli_error(get_string('invalidcourseid', 'error') . " ($side=$id)");
    }
}
if ((int) $options['source'] === (int) $options['target']) {
    cli_error(get_string('cmp_same_course', 'local_mooc_audit'));
}
$override = null;
if ($options['policy'] !== '') {
    $text = is_readable($options['policy']) ? file_get_contents($options['policy']) : false;
    $error = get_string('filenotfound', 'error');
    $override = $text === false ? null : \local_mooc_audit\policy::decode_object($text, $error);
    if ($override === null) {
        cli_error(get_string('policyjson_invalid', 'local_mooc_audit', $error));
    }
}
$pairs = \local_mooc_audit\comparator::parse_pairs(array_filter(explode(',', (string) $options['pairs'])));
$report = \local_mooc_audit\comparator::run((int) $options['source'], (int) $options['target'], $pairs, $override);
if ($options['out'] !== '') {
    file_put_contents($options['out'], \local_mooc_audit\comparator::to_json($report));
}
echo $options['json'] ? \local_mooc_audit\comparator::to_json($report) . "\n" : \local_mooc_audit\comparator::render_text($report);
