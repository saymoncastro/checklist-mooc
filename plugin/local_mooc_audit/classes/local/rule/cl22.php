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
 * Rule CL-22: Licença para capacitação configurada.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl22 extends base {
    /** @var string */
    const ID = 'CL-22';
    /** @var string */
    const CATEGORY = category::INSTITUTIONAL_RESOURCES;
    /** @var string */
    const ORIGIN = 'checklist:22';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['licence_label_text', 'licence_feedback_name', 'licence_certificate_name'];
    /** @var string */
    const LOCUS = self::LOCUS_MODULE_EDIT;
    /** @var string */
    const SOURCE = 'Checklist item 22';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $labels = array_filter(helpers::mods($s, 'label'), fn($m) => $m['sectionnumber'] == 0
            && strpos(helpers::norm(helpers::text($m['texts']['intro'] ?? '')), helpers::norm($p['licence_label_text'])) !== false);
        $out[] = $this->f(
            $labels ? status::CONFORME : status::NAO_CONFORME,
            'Área do curso (seção 0)',
            'rótulo "' . $p['licence_label_text'] . '": ' . ($labels ? 'presente' : 'ausente'),
            ['locus' => $labels ? self::LOCUS_NONE : self::LOCUS_IMPORT]
        );
        $fb = helpers::mods($s, 'feedback', $p['licence_feedback_name']);
        $out[] = $this->f(
            $fb ? status::CONFORME : status::NAO_CONFORME,
            'Atividades do curso',
            'pesquisa "' . $p['licence_feedback_name'] . '": ' . ($fb ? 'presente' : 'ausente'),
            ['locus' => $fb ? self::LOCUS_NONE : self::LOCUS_IMPORT]
        );
        $dc = helpers::mods($s, $p['required_certificate_module'], $p['licence_certificate_name']);
        $out[] = $this->f(
            $dc ? status::CONFORME : status::NAO_CONFORME,
            'Atividades do curso',
            'declaração "' . $p['licence_certificate_name'] . '": ' . ($dc ? 'presente' : 'ausente'),
            ['locus' => $dc ? self::LOCUS_NONE : self::LOCUS_IMPORT]
        );
        return $out;
    }
}
