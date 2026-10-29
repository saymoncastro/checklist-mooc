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

namespace local_mooc_audit\local\rule;

use local_mooc_audit\local\helpers;
use local_mooc_audit\local\status;
use local_mooc_audit\local\category;

/**
 * Rule X-INT-03: Plugins usados pelo curso instalados no site.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xint03 extends base {
    /** @var string */
    const ID = 'X-INT-03';
    /** @var string */
    const CATEGORY = category::PLUGINS_ENVIRONMENT;
    /** @var string */
    const ORIGIN = 'extra:6.1';
    /** @var string */
    const TYPE = self::TYPE_TECHNICAL;
    /** @var string */
    const SEVERITY = status::ALTA;
    /** @var string[] */
    const POLICY_KEYS = [];
    /** @var string */
    const LOCUS = self::LOCUS_NONE;
    /** @var string */
    const SOURCE = 'Relatório de investigação §6.1; availability/classes/tree.php (condição de plugin ausente é ignorada)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $std = \core_plugin_manager::standard_plugins_list('mod') ?: [];
        foreach ($s['plugins']['used']['mod'] as $mod) {
            $installed = isset($s['plugins']['installed']['mod'][$mod]);
            $out[] = $this->f($installed ? status::CONFORME : status::NAO_CONFORME, "módulo $mod", ($installed
                ? 'instalado (versão ' . $s['plugins']['installed']['mod'][$mod] . ')' : 'NÃO instalado')
                . (in_array($mod, $std) ? '' : ' — plugin de terceiros'), ['gravidade' => in_array($mod, $std)
                    ? status::INFORMATIVA : status::ALTA, 'check' => 'plugin', 'subject' => "mod:$mod"]);
        }
        $stdav = \core_plugin_manager::standard_plugins_list('availability') ?: [];
        foreach ($s['plugins']['used']['availability'] as $type) {
            $installed = isset($s['plugins']['installed']['availability'][$type]);
            $out[] = $this->f(
                $installed ? status::CONFORME : status::NAO_CONFORME,
                "condição de acesso \"$type\"",
                $installed ? 'plugin availability_' . $type . ' instalado' : 'plugin availability_' . $type
                    . ' AUSENTE: condições deste tipo são ignoradas silenciosamente',
                ['gravidade' => in_array($type, $stdav) ? status::INFORMATIVA : status::ALTA, 'check' => 'plugin',
                    'subject' => "availability:$type"]
            );
        }
        return $out ?: [$this->f(status::NAO_APLICAVEL, 'Plugins', 'curso sem módulos nem condições de acesso', [
            'check' => 'applicability'])];
    }
}
