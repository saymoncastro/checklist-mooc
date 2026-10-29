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
 * Rule CL-04: Sumário contém botões institucionais.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cl04 extends base {
    /** @var string */
    const ID = 'CL-04';
    /** @var string */
    const CATEGORY = category::INSTITUTIONAL_RESOURCES;
    /** @var string */
    const ORIGIN = 'checklist:4';
    /** @var string */
    const TYPE = self::TYPE_INSTITUTIONAL;
    /** @var string */
    const SEVERITY = status::MEDIA;
    /** @var string[] */
    const POLICY_KEYS = ['summary_required_links'];
    /** @var string */
    const LOCUS = self::LOCUS_COURSE_SETTINGS;
    /** @var string */
    const SOURCE = 'Checklist item 4 (botões Criar Conta / Turmas anteriores no sumário)';

    /**
     * Evaluate.
     *
     * @param array $s snapshot
     * @param array $p policy
     * @return array findings
     */
    public function evaluate(array $s, array $p): array {
        $out = [];
        $summary = html_entity_decode((string) $s['course']['summary']);
        foreach ($p['summary_required_links'] as $link) {
            $ok = strpos($summary, $link) !== false;
            $out[] = $this->f(
                $ok ? status::CONFORME : status::NAO_CONFORME,
                'Sumário do curso',
                "link $link " . ($ok ? 'presente' : 'ausente'),
                ['orientacao' => $this->guidance()]
            );
        }
        return $out;
    }
}
