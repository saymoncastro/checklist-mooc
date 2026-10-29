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
 * CLI: create a batch audit and, optionally, run its tasks in this process (operation and performance measurement).
 *
 * php local/mooc_audit/cli/batch.php (--courses=2,3,8 | --category=ID [--subcats] | --startyear=2027) [--name=...] [--run]
 *
 * The batch is requested by the main administrator (the background tasks check the same permissions as for any
 * requester). Without --run the tasks wait for the cron (admin/cli/cron.php or admin/cli/adhoc_task.php --execute).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_mooc_audit\local\history\batch_manager;
use local_mooc_audit\local\history\run_store;
use local_mooc_audit\task\audit_course;

[$options, $unrecognized] = cli_get_params([
    'courses' => '', 'category' => 0, 'subcats' => false, 'startyear' => 0, 'reofferyear' => 0, 'name' => '', 'run' => false,
    'help' => false,
], ['h' => 'help']);

if ($options['help'] || (!$options['courses'] && !$options['category'] && !$options['startyear'])) {
    cli_writeln(implode("\n", [
        'Auditoria em lote (Spec 005).',
        '',
        'Opções (uma forma de seleção):',
        '  --courses=1,2,3   lista de ids',
        '  --category=N      categoria; --subcats inclui subcategorias',
        '  --startyear=AAAA  salas cuja data de início está no ano',
        '  --reofferyear=AAAA  ciclo (ano da reoferta) aplicado às auditorias; padrão: o da política',
        '  --name=TEXTO      nome do lote',
        '  --run             executa as tarefas do lote neste processo, em série, e imprime tempo e memória por sala',
    ]));
    exit(0);
}

$admin = get_admin();
\core\session\manager::set_user($admin);
if ($options['courses']) {
    $criteria = ['mode' => batch_manager::MODE_IDS, 'courseids' => $options['courses']];
} else if ($options['category']) {
    $criteria = ['mode' => batch_manager::MODE_CATEGORY, 'categoryid' => (int) $options['category'],
        'subcategories' => (bool) $options['subcats']];
} else {
    $criteria = ['mode' => batch_manager::MODE_STARTYEAR, 'startyear' => (int) $options['startyear']];
}
$criteria['reofferyear'] = (int) $options['reofferyear'];
$t0 = microtime(true);
$out = batch_manager::create($criteria, (int) $admin->id, (string) $options['name']);
if ($out['error']) {
    cli_error($out['error']);
}
cli_writeln("lote {$out['batchid']}: {$out['queued']} enfileiradas, {$out['reused']} reaproveitadas, "
    . (count($out['skipped']) - $out['reused']) . ' recusadas');
if (!$options['run']) {
    exit(0);
}

$batch = batch_manager::get($out['batchid']);
$tasks = $DB->get_records('task_adhoc', ['classname' => '\\' . audit_course::class], 'id ASC');
// Only the runs of this batch: reused runs of other requests stay with their own tasks.
$own = array_filter(batch_manager::runs($batch), fn($r) => (int) $r->batchid === (int) $batch->id);
$mine = array_map(fn($r) => (int) $r->id, $own);
foreach ($tasks as $record) {
    $data = json_decode($record->customdata);
    if (!in_array((int) ($data->runid ?? 0), $mine, true)) {
        continue;
    }
    $task = \core\task\manager::adhoc_task_from_record($record);
    $task->execute();
    // Done in this process: remove the queued copy so that cron does not pick it up (it would find the run final).
    $DB->delete_records('task_adhoc', ['id' => $record->id]);
    $run = run_store::get((int) $data->runid);
    $metrics = run_store::json($run, 'details')['metrics'] ?? [];
    cli_writeln(sprintf(
        '  curso %6d  %-9s %-20s %6.2fs %7.1f MB  leituras %5d  relatório %7d bytes',
        $run->courseid,
        $run->status,
        (string) $run->overall,
        $metrics['seconds'] ?? 0,
        $metrics['peak_mb'] ?? 0,
        $metrics['db_reads'] ?? 0,
        strlen((string) $run->report)
    ));
}
$p = batch_manager::progress($batch);
cli_writeln(sprintf(
    'total %d: concluídas %d, parciais %d, falhas %d, pendentes %d; %.1fs; pico do processo %.1f MB',
    $p['total'],
    $p['complete'],
    $p['partial'],
    $p['failed'],
    $p['queued'] + $p['running'],
    microtime(true) - $t0,
    memory_get_peak_usage() / 1048576
));
