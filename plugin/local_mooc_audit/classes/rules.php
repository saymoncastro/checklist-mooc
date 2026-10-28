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

namespace local_mooc_audit;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule catalogue for the proof of concept.
 *
 * PoC design choice: one registry class with one static method per rule (production code should use one class per
 * rule under classes/rule/). Rule ids and semantics follow poc/mbz_inspect.py so that MBZ and plugin results can be
 * compared one to one. Every rule receives the snapshot ($s) and the policy ($p) and returns a list of findings.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {
    /** Status constants (same vocabulary as the MBZ prototype). */
    const CONFORME = 'CONFORME';
    const NAO_CONFORME = 'NAO_CONFORME';
    const ATENCAO = 'ATENCAO';
    const NA = 'NAO_APLICAVEL';
    const NV = 'NAO_VERIFICAVEL';
    const ERRO = 'ERRO';

    /**
     * Catalogue: id => [titulo, origem, classe, gravidade padrão, método].
     *
     * @return array
     */
    public static function catalog(): array {
        return [
            'CL-04' => ['Sumário contém botões institucionais', 'checklist:4', 'A', 'Média', 'cl04'],
            'CL-05' => ['Categoria do curso = categoria de inscrições abertas', 'checklist:5', 'A', 'Baixa', 'cl05'],
            'CL-06' => ['Data de início correta (01/01 do ano da reoferta)', 'checklist:6', 'A', 'Alta', 'cl06'],
            'CL-07' => ['Data de término correta (31/12 do ano da reoferta)', 'checklist:7', 'A', 'Alta', 'cl07'],
            'CL-08' => ['Autoinscrição habilitada com período correto', 'checklist:8', 'A', 'Alta', 'cl08'],
            'CL-09' => ['Fórum institucional "Espaço de interação" presente e sem conclusão', 'checklist:9', 'A', 'Média', 'cl09'],
            'CL-11' => ['Pesquisa de Perfil presente e exigida pelo certificado', 'checklist:11', 'A', 'Alta', 'cl11'],
            'CL-12' => ['Livro de notas: soma 100, agregação Natural, sem excluir vazias', 'checklist:12', 'A', 'Alta', 'cl12'],
            'CL-13' => ['Questionários com ao menos duas tentativas (e enunciado coerente)', 'checklist:13', 'A', 'Alta', 'cl13'],
            'CL-14' => ['Atividades avaliativas com data de encerramento', 'checklist:14', 'A', 'Média', 'cl14'],
            'CL-16' => ['Avaliação do curso restrita à conclusão de todas as atividades com nota', 'checklist:16', 'A', 'Alta', 'cl16'],
            'CL-17' => ['Conclusão automática com critério adequado ao tipo', 'checklist:17', 'A', 'Média', 'cl17'],
            'CL-18' => ['Glossário: proibido se colaborativo; vazio se fonte de jogos', 'checklist:18', 'A', 'Média', 'cl18'],
            'CL-19' => ['Rótulos sem conclusão de atividade', 'checklist:19', 'A', 'Baixa', 'cl19'],
            'CL-20' => ['Seções: ocultas invisíveis, sem seções vazias ou com nome genérico', 'checklist:20', 'A', 'Baixa', 'cl20'],
            'CL-21' => ['Blocos padrão presentes na página do curso', 'checklist:21', 'A', 'Média', 'cl21'],
            'CL-22' => ['Licença para capacitação configurada', 'checklist:22', 'A', 'Média', 'cl22'],
            'CL-23' => ['Certificado condicionado à Avaliação do curso e à nota mínima no total', 'checklist:23', 'A', 'Bloqueante', 'cl23'],
            'X-INT-02' => ['Arquivos registrados sem conteúdo no armazenamento do Moodle', 'extra:6.1', 'A', 'Alta', 'xint02'],
            'X-INT-03' => ['Plugins usados pelo curso instalados no site', 'extra:6.1', 'A', 'Alta', 'xint03'],
            'X-CONT-01' => ['Recursos vazios (página/rótulo sem conteúdo, questionário sem questões, arquivo sem arquivo)', 'extra:6.2', 'A', 'Média', 'xcont01'],
            'X-CONT-04' => ['Referências @@PLUGINFILE@@ sem arquivo correspondente', 'extra:6.2', 'A', 'Média', 'xcont04'],
            'X-AV-01' => ['Enunciado × configuração (pontos)', 'extra:6.3', 'A', 'Média', 'xav01'],
            'X-DEP-01' => ['Condições de acesso apontando para módulos inexistentes', 'extra:6.4', 'A', 'Alta', 'xdep01'],
            'X-DEP-02' => ['Condição exige conclusão de módulo sem conclusão ou oculto', 'extra:6.4', 'A', 'Bloqueante', 'xdep02'],
            'X-DEP-03' => ['Condições de nota apontando para itens inexistentes', 'extra:6.4', 'A', 'Alta', 'xdep03'],
            'X-DEP-04' => ['Ciclos no grafo de dependências de conclusão', 'extra:6.4', 'A', 'Bloqueante', 'xdep04'],
            'X-CERT-01' => ['Alcançabilidade estática do certificado', 'extra:6.5', 'A', 'Bloqueante', 'xcert01'],
            'X-STR-01' => ['Módulos ocultos, invisíveis ao usuário ou com nome vazio', 'extra:6.7', 'A', 'Baixa', 'xstr01'],
        ];
    }

    // ----------------------------------------------------------------------------------------------- helpers.

    /**
     * Build a finding.
     *
     * @param string $status
     * @param string|null $local
     * @param string|null $evidencia
     * @param array $extra encontrado, esperado, orientacao, gravidade, cmid
     * @return array
     */
    protected static function f(string $status, ?string $local = null, ?string $evidencia = null, array $extra = []): array {
        return array_merge(['status' => $status, 'local' => $local, 'evidencia' => $evidencia, 'encontrado' => null,
            'esperado' => null, 'orientacao' => null, 'gravidade' => null, 'cmid' => null], $extra);
    }

    /**
     * Normalise text for comparisons (lowercase, no accents, collapsed spaces).
     *
     * @param string|null $s
     * @return string
     */
    public static function norm(?string $s): string {
        $s = \core_text::strtolower(trim((string) $s));
        $s = \core_text::specialtoascii($s);
        return preg_replace('/\s+/u', ' ', $s);
    }

    /**
     * Strip HTML to plain text.
     *
     * @param string|null $s
     * @return string
     */
    public static function text(?string $s): string {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Human label of a module.
     *
     * @param array $m
     * @return string
     */
    protected static function label(array $m): string {
        return "{$m['modname']} \"" . ($m['name'] ?? '(sem nome)') . "\" (cmid={$m['cmid']})";
    }

    /**
     * Find modules by type / name fragment.
     *
     * @param array $s
     * @param string|null $modname
     * @param string|null $namelike
     * @return array
     */
    protected static function mods(array $s, ?string $modname = null, ?string $namelike = null): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            if ($modname && $m['modname'] !== $modname) {
                continue;
            }
            if ($namelike && strpos(self::norm($m['name']), self::norm($namelike)) === false) {
                continue;
            }
            $out[] = $m;
        }
        return $out;
    }

    /**
     * Modules with a value grade item (grademax > 0): "atividades que valem nota".
     *
     * @param array $s
     * @return array list of [module, gradeitem]
     */
    protected static function graded(array $s): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach ($m['grade_items'] as $gi) {
                if ($gi['gradetype'] == 1 && $gi['grademax'] > 0) {
                    $out[] = [$m, $gi];
                }
            }
        }
        return $out;
    }

    /**
     * Certificate modules (excluding the licence declaration).
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    protected static function certs(array $s, array $p): array {
        return array_values(array_filter(self::mods($s, $p['required_certificate_module']), function($m) use ($p) {
            return strpos(self::norm($m['name']), self::norm($p['licence_certificate_name'])) === false;
        }));
    }

    /**
     * Format a timestamp in the site timezone.
     *
     * @param int|null $ts
     * @return string
     */
    protected static function d(?int $ts): string {
        return $ts ? userdate($ts, '%d/%m/%Y %H:%M', 99, false) : '(não definida)';
    }

    // ------------------------------------------------------------------------------------------------- rules.

    /**
     * CL-04.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl04(array $s, array $p): array {
        $out = [];
        $summary = html_entity_decode((string) $s['course']['summary']);
        foreach ($p['summary_required_links'] as $link) {
            $ok = strpos($summary, $link) !== false;
            $out[] = self::f($ok ? self::CONFORME : self::NAO_CONFORME, 'Sumário do curso', "link $link " . ($ok ? 'presente' : 'ausente'),
                ['orientacao' => 'Acrescentar o botão ao final do sumário (Editar configurações do curso > Descrição).']);
        }
        return $out;
    }

    /**
     * CL-05.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl05(array $s, array $p): array {
        $cat = $s['course']['category']['name'];
        $ok = self::norm($cat) === self::norm($p['target_category_name']);
        return [self::f($ok ? self::CONFORME : self::NAO_CONFORME, 'Configurações do curso > Categoria', "categoria=\"$cat\"",
            ['esperado' => $p['target_category_name'], 'orientacao' => 'Mover o curso para a categoria da oferta.'])];
    }

    /**
     * CL-06.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl06(array $s, array $p): array {
        $sd = $s['course']['startdate'];
        $ed = $s['course']['enddate'];
        if (!$sd) {
            return [self::f(self::NAO_CONFORME, 'Configurações do curso > Data de início', 'não definida')];
        }
        $probs = [];
        $year = (int) userdate($sd, '%Y', 99, false);
        $dm = userdate($sd, '%d/%m', 99, false);
        if ($year !== (int) $p['reoffer_year']) {
            $probs[] = "ano=$year ≠ {$p['reoffer_year']}";
        }
        if ($p['required_start_date'] && $dm !== $p['required_start_date']) {
            $probs[] = "dia/mês=$dm ≠ {$p['required_start_date']}";
        }
        if ($ed && ($ed - $sd) < $p['min_days_between_start_and_end'] * DAYSECS) {
            $probs[] = 'menos de ' . $p['min_days_between_start_and_end'] . ' dias antes do fim';
        }
        return [self::f($probs ? self::NAO_CONFORME : self::CONFORME, 'Configurações do curso > Data de início',
            'início=' . self::d($sd) . ' fim=' . self::d($ed), ['encontrado' => implode('; ', $probs) ?: 'ok',
            'esperado' => "{$p['required_start_date']}/{$p['reoffer_year']}", 'orientacao' => 'Ajustar a data de início da oferta.'])];
    }

    /**
     * CL-07.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl07(array $s, array $p): array {
        $ed = $s['course']['enddate'];
        if (!$ed) {
            return [self::f(self::NAO_CONFORME, 'Configurações do curso > Data de término', 'não definida (0)')];
        }
        $dm = userdate($ed, '%d/%m', 99, false);
        $year = (int) userdate($ed, '%Y', 99, false);
        $ok = in_array($dm, $p['allowed_end_dates']) && $year === (int) $p['reoffer_year'];
        return [self::f($ok ? self::CONFORME : self::NAO_CONFORME, 'Configurações do curso > Data de término', 'término=' . self::d($ed),
            ['esperado' => implode('|', $p['allowed_end_dates']) . "/{$p['reoffer_year']}", 'orientacao' => 'Ajustar a data de término conforme o calendário do Cefor.'])];
    }

    /**
     * CL-08.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl08(array $s, array $p): array {
        $out = [];
        $selfs = array_filter($s['enrols'], fn($e) => $e['enrol'] === 'self');
        if (!$selfs) {
            return [self::f(self::NAO_CONFORME, 'Métodos de inscrição', 'nenhuma instância de autoinscrição', ['orientacao' => 'Adicionar o método Autoinscrição.'])];
        }
        $sd = $s['course']['startdate'];
        $ed = $s['course']['enddate'];
        foreach ($selfs as $e) {
            $probs = [];
            if ($e['status'] != 0) {
                $probs[] = 'método desabilitado';
            }
            if ($e['customint6'] !== 1) {
                $probs[] = '"Permitir novas inscrições" ≠ Sim';
            }
            if (!$e['enrolstartdate'] || !$e['enrolenddate']) {
                $probs[] = 'datas de inscrição não definidas';
            } else {
                if ($sd && $e['enrolstartdate'] != $sd) {
                    $probs[] = 'início da inscrição ≠ início do curso';
                }
                if ($ed && $e['enrolenddate'] != $ed) {
                    $probs[] = 'fim da inscrição ≠ fim do curso';
                }
            }
            $out[] = self::f($probs ? self::NAO_CONFORME : self::CONFORME, "Métodos de inscrição > Autoinscrição (id {$e['id']})",
                "status={$e['status']} novas_inscricoes={$e['customint6']} início=" . self::d($e['enrolstartdate']) . ' fim=' . self::d($e['enrolenddate']),
                ['encontrado' => implode('; ', $probs) ?: 'ok', 'orientacao' => 'Editar a Autoinscrição: habilitar, permitir novas inscrições e alinhar as datas com as do curso.']);
        }
        foreach ($s['enrols'] as $e) {
            if ($e['enrol'] === 'guest' && $e['status'] == 0) {
                $out[] = self::f(self::ATENCAO, 'Métodos de inscrição > Acesso de visitante', 'habilitado' . ($e['has_password'] ? ' com senha' : ''),
                    ['gravidade' => 'Informativa', 'orientacao' => 'Confirmar se o acesso de visitante é desejado.']);
            }
        }
        return $out;
    }

    /**
     * CL-09.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl09(array $s, array $p): array {
        $f = self::mods($s, 'forum', $p['required_forum_name']);
        if (!$f) {
            $names = array_map(fn($m) => $m['name'], self::mods($s, 'forum'));
            return [self::f(self::NAO_CONFORME, 'Fóruns do curso', 'nenhum fórum com nome ~ "' . $p['required_forum_name'] . '"; existentes: ' . json_encode($names, JSON_UNESCAPED_UNICODE),
                ['orientacao' => 'Importar o fórum da Sala Padrão. Verificação por nome canônico.'])];
        }
        $m = $f[0];
        $out = [self::f(self::CONFORME, self::label($m), 'presente', ['cmid' => $m['cmid']])];
        if ($m['completion'] != 0) {
            $out[] = self::f(self::NAO_CONFORME, self::label($m), "completion={$m['completion']}", ['cmid' => $m['cmid'], 'esperado' => 'conclusão desabilitada',
                'orientacao' => 'Desabilitar a conclusão de atividade do fórum institucional.']);
        }
        return $out;
    }

    /**
     * CL-11.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl11(array $s, array $p): array {
        $prof = self::mods($s, 'feedback', $p['required_feedback_profile']);
        if (!$prof) {
            return [self::f(self::NAO_CONFORME, 'Atividades do curso', 'nenhuma Pesquisa (feedback) ~ "' . $p['required_feedback_profile'] . '"',
                ['orientacao' => 'Importar a Pesquisa de Perfil da Sala Padrão Oficial.'])];
        }
        $pm = $prof[0];
        $out = [self::f(self::CONFORME, self::label($pm), 'presente', ['cmid' => $pm['cmid']])];
        if ($pm['completion'] != 2 || ($pm['extra']['completionsubmit'] ?? 0) != 1) {
            $out[] = self::f(self::NAO_CONFORME, self::label($pm), "completion={$pm['completion']} completionsubmit=" . ($pm['extra']['completionsubmit'] ?? 'n/a'),
                ['cmid' => $pm['cmid'], 'esperado' => 'conclusão automática ao enviar', 'orientacao' => 'Sem conclusão automática a restrição do certificado nunca é satisfeita.']);
        }
        $certs = self::certs($s, $p);
        if (!$certs) {
            $out[] = self::f(self::NAO_CONFORME, 'Atividades do curso', 'nenhum certificado de conclusão');
        }
        foreach ($certs as $c) {
            $cms = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                if (($cond['type'] ?? '') === 'completion') {
                    $cms[] = (int) $cond['cm'];
                }
            }
            $ok = in_array($pm['cmid'], $cms);
            $out[] = self::f($ok ? self::CONFORME : self::NAO_CONFORME, self::label($c), 'restrições de conclusão: cmids ' . json_encode($cms),
                ['cmid' => $c['cmid'], 'esperado' => "exigir conclusão de cm={$pm['cmid']} (Pesquisa de Perfil)",
                'orientacao' => 'Adicionar ao certificado a restrição de conclusão da Pesquisa de Perfil.']);
        }
        return $out;
    }

    /**
     * CL-12 (includes X-AV-04 aggregateonlygraded).
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl12(array $s, array $p): array {
        $out = [];
        $graded = self::graded($s);
        $total = 0.0;
        $det = [];
        foreach ($graded as [$m, $gi]) {
            $total += $gi['grademax'];
            $det[] = "{$m['modname']}:{$m['cmid']}=" . rtrim(rtrim(number_format($gi['grademax'], 2, '.', ''), '0'), '.');
        }
        $ok = abs($total - (float) $p['course_total_max']) < 0.001;
        $out[] = self::f($ok ? self::CONFORME : self::NAO_CONFORME, 'Livro de notas > Configurações', 'soma dos máximos=' . $total . ' itens=' . implode(', ', $det),
            ['esperado' => (string) $p['course_total_max'], 'orientacao' => 'Ajustar as notas máximas das atividades.']);
        $ci = $s['grade']['course_item'];
        if (!$ci) {
            $out[] = self::f(self::NV, 'Livro de notas', 'item de total do curso não encontrado');
            return $out;
        }
        $root = $s['grade']['categories'][$ci['iteminstance']] ?? null;
        if ($root) {
            if ((int) $root['aggregation'] !== (int) $p['required_aggregation']) {
                $out[] = self::f(self::NAO_CONFORME, 'Livro de notas > categoria raiz', "aggregation={$root['aggregation']}", ['esperado' => $p['required_aggregation'] . ' (Natural)',
                    'orientacao' => 'Alterar a agregação da categoria do curso para Natural.']);
            } else {
                $out[] = self::f(self::CONFORME, 'Livro de notas > categoria raiz', 'aggregation=13 (Natural)');
            }
            if ($root['aggregateonlygraded'] && !$p['aggregateonlygraded_allowed']) {
                $out[] = self::f(self::NAO_CONFORME, 'Livro de notas > categoria raiz', 'aggregateonlygraded=1 (excluir notas vazias)', ['esperado' => '0',
                    'orientacao' => 'Desmarcar "Excluir notas vazias": com ela ligada, a condição de 60 pontos do certificado é relativa só às atividades já avaliadas.']);
            } else {
                $out[] = self::f(self::CONFORME, 'Livro de notas > categoria raiz', 'aggregateonlygraded=0');
            }
        }
        if (abs($ci['grademax'] - (float) $p['course_total_max']) > 0.001) {
            $out[] = self::f(self::ATENCAO, 'Livro de notas > Total do curso', "grademax do total={$ci['grademax']}", ['esperado' => (string) $p['course_total_max']]);
        }
        $sub = array_filter($graded, fn($x) => $x[1]['categoryid'] != $ci['iteminstance']);
        if ($sub) {
            $out[] = self::f(self::ATENCAO, 'Livro de notas', count($sub) . ' itens em subcategorias', ['orientacao' => 'Verificar agregação das subcategorias.']);
        }
        return $out;
    }

    /**
     * CL-13.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl13(array $s, array $p): array {
        $out = [];
        foreach (self::mods($s, 'quiz') as $m) {
            $a = (int) ($m['extra']['attempts'] ?? 0);
            $ok = $p['attempts_rule'] === 'exact' ? $a === (int) $p['min_quiz_attempts'] : ($a === 0 || $a >= (int) $p['min_quiz_attempts']);
            $out[] = self::f($ok ? self::CONFORME : self::NAO_CONFORME, self::label($m), "tentativas permitidas=$a",
                ['cmid' => $m['cmid'], 'esperado' => ($p['attempts_rule'] === 'exact' ? '= ' : '≥ ') . $p['min_quiz_attempts'] . ' (0 = ilimitadas)',
                'orientacao' => 'Editar configurações do questionário > Nota > Tentativas permitidas.']);
            $intro = self::norm(self::text($m['texts']['intro'] ?? ''));
            if (preg_match('/tentativas?\s*(?:permitidas?)?\s*[:\-]?\s*(\d+)/u', $intro, $mm)) {
                if ((int) $mm[1] !== $a) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), "enunciado declara {$mm[1]} tentativas; configurado=$a", ['cmid' => $m['cmid'],
                        'orientacao' => 'Corrigir o enunciado ou a configuração.']);
                }
            } else if ($intro !== '') {
                $out[] = self::f(self::ATENCAO, self::label($m), 'enunciado não menciona o número de tentativas', ['cmid' => $m['cmid'], 'gravidade' => 'Baixa']);
            }
        }
        return $out ?: [self::f(self::NA, 'Questionários', 'curso sem questionários')];
    }

    /**
     * CL-14.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl14(array $s, array $p): array {
        $out = [];
        $ed = $s['course']['enddate'];
        foreach (self::graded($s) as [$m, $gi]) {
            $x = $m['extra'];
            $close = null;
            $known = true;
            switch ($m['modname']) {
                case 'quiz':
                case 'game':
                case 'choice':
                case 'feedback':
                    $close = $x['timeclose'] ?? 0;
                    break;
                case 'assign':
                case 'forum':
                    $close = ($x['cutoffdate'] ?? 0) ?: ($x['duedate'] ?? 0);
                    break;
                case 'data':
                    $close = $x['timeavailableto'] ?? 0;
                    break;
                default:
                    $known = false;
            }
            if (!$known) {
                $out[] = self::f(self::NV, self::label($m), 'tipo sem campo de encerramento conhecido', ['cmid' => $m['cmid'], 'gravidade' => 'Informativa']);
            } else if (!$close) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), 'data de encerramento não definida', ['cmid' => $m['cmid'], 'orientacao' => 'Definir a data de encerramento.']);
            } else {
                $note = ($ed && $close != $ed) ? ' (≠ fim do curso ' . self::d($ed) . ')' : '';
                $out[] = self::f($note ? self::ATENCAO : self::CONFORME, self::label($m), 'encerramento=' . self::d($close) . $note, ['cmid' => $m['cmid'], 'gravidade' => $note ? 'Baixa' : null]);
            }
        }
        return $out ?: [self::f(self::NA, 'Atividades avaliativas', 'nenhuma')];
    }

    /**
     * CL-16.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl16(array $s, array $p): array {
        $ev = self::mods($s, 'feedback', $p['required_feedback_evaluation']);
        if (!$ev) {
            return [self::f(self::NAO_CONFORME, 'Atividades do curso', 'nenhuma "' . $p['required_feedback_evaluation'] . '"')];
        }
        $e = $ev[0];
        $req = [];
        foreach (snapshot::walk_conditions($e['availability']) as $c) {
            if (($c['type'] ?? '') === 'completion') {
                $req[] = (int) $c['cm'];
            }
        }
        $graded = [];
        foreach (self::graded($s) as [$m, $gi]) {
            $graded[$m['cmid']] = $m;
        }
        $missing = [];
        foreach ($graded as $cmid => $m) {
            if (!in_array($cmid, $req)) {
                $missing[] = self::label($m);
            }
        }
        $out = [self::f($missing ? self::NAO_CONFORME : self::CONFORME, self::label($e), 'exige conclusão de cmids ' . json_encode($req),
            ['cmid' => $e['cmid'], 'encontrado' => $missing ? 'faltam: ' . implode('; ', $missing) : 'todas as ' . count($graded) . ' atividades com nota exigidas',
            'esperado' => 'conclusão de todas as atividades com nota', 'orientacao' => 'Adicionar as restrições de conclusão faltantes.'])];
        $op = $e['availability']['op'] ?? '&';
        if ($op !== '&') {
            $out[] = self::f(self::ATENCAO, self::label($e), "operador raiz=$op", ['cmid' => $e['cmid'], 'esperado' => '& (todas)']);
        }
        return $out;
    }

    /**
     * CL-17.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl17(array $s, array $p): array {
        $out = [];
        $counts = [0 => 0, 1 => 0, 2 => 0];
        foreach ($s['modules'] as $m) {
            $counts[$m['completion']] = ($counts[$m['completion']] ?? 0) + 1;
            $exp = $p['completion_expected'][$m['modname']] ?? null;
            if ($m['completion'] == 1) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), 'conclusão MANUAL', ['cmid' => $m['cmid'], 'esperado' => 'automática (ou nenhuma, para rótulos/fóruns institucionais)',
                    'orientacao' => 'Trocar para conclusão automática com o critério adequado ao tipo.']);
                continue;
            }
            if ($m['completion'] == 2) {
                $x = $m['extra'];
                $crit = [];
                if ($m['completionview']) {
                    $crit[] = 'view';
                }
                if ($m['completiongradeitemnumber'] !== null) {
                    $crit[] = 'grade';
                }
                foreach (['completionsubmit' => 'submit', 'completionentries' => 'entries', 'completionposts' => 'posts', 'completiondiscussions' => 'posts',
                    'completionreplies' => 'posts', 'completionminattempts' => 'attempts', 'completionattemptsexhausted' => 'attempts', 'completionpass' => 'grade'] as $k => $v) {
                    if (!empty($x[$k])) {
                        $crit[] = $v;
                    }
                }
                $crit = array_values(array_unique($crit));
                if (!$crit) {
                    $out[] = self::f(self::ATENCAO, self::label($m), 'conclusão automática sem nenhum critério', ['cmid' => $m['cmid']]);
                } else if ($exp && $exp !== 'none' && !in_array(explode('_', $exp)[0], $crit) && !($exp === 'posts_or_none' && in_array('posts', $crit))
                        && !($exp === 'submit_or_grade' && (in_array('submit', $crit) || in_array('grade', $crit)))) {
                    $out[] = self::f(self::ATENCAO, self::label($m), 'critérios=' . implode('+', $crit), ['cmid' => $m['cmid'], 'esperado' => "critério '$exp' para {$m['modname']}",
                        'gravidade' => 'Baixa', 'orientacao' => 'Rever o critério de conclusão conforme a tabela da política.']);
                }
                if ($exp === 'none' && $m['modname'] === 'label') {
                    // Reported by CL-19.
                    continue;
                }
            }
        }
        array_unshift($out, self::f($counts[1] ? self::ATENCAO : self::CONFORME, 'Conclusão de atividades', "nenhuma={$counts[0]} manual={$counts[1]} automática={$counts[2]}",
            ['gravidade' => 'Informativa']));
        return $out;
    }

    /**
     * CL-18.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl18(array $s, array $p): array {
        $out = [];
        foreach ($p['forbidden_modules'] as $mod) {
            $gs = self::mods($s, $mod);
            if (!$gs) {
                $out[] = self::f(self::CONFORME, 'Atividades do curso', "nenhum módulo $mod");
            }
            foreach ($gs as $g) {
                $deps = array_filter(self::mods($s, 'game'), fn($m) => ($m['extra']['sourcemodule'] ?? '') === 'glossary' && (int) ($m['extra']['glossaryid'] ?? 0) === $g['instanceid']);
                $entries = (int) ($g['extra']['entries'] ?? 0);
                if ($deps) {
                    $names = array_map(fn($m) => self::label($m) . ' [entradas na categoria: ' . ($m['extra']['glossary_category_entries'] ?? 'n/a') . ']', $deps);
                    $st = $entries === 0 ? self::NAO_CONFORME : self::ATENCAO;
                    $out[] = self::f($st, self::label($g), "glossário com $entries entradas; fonte de " . count($deps) . ' jogos: ' . implode('; ', $names),
                        ['cmid' => $g['cmid'], 'gravidade' => $entries === 0 ? 'Bloqueante' : 'Média', 'esperado' => 'entradas disponíveis para os jogos',
                        'orientacao' => 'As entradas não vêm em backup/importação sem usuários: repovoar (importar XML do glossário) ou trocar a fonte dos jogos para o banco de questões.']);
                } else {
                    $out[] = self::f(self::NAO_CONFORME, self::label($g), "módulo $mod presente ($entries entradas) sem uso por jogos", ['cmid' => $g['cmid'],
                        'orientacao' => 'Remover o glossário (glossário colaborativo é proibido pela política).']);
                }
            }
        }
        return $out;
    }

    /**
     * CL-19.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl19(array $s, array $p): array {
        $out = [];
        foreach (self::mods($s, 'label') as $m) {
            if ($m['completion'] != 0) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), "completion={$m['completion']}", ['cmid' => $m['cmid'], 'esperado' => '0', 'orientacao' => 'Desabilitar a conclusão no rótulo.']);
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Rótulos', 'nenhum rótulo com conclusão habilitada')];
    }

    /**
     * CL-20 (includes generic section names from X-STR-01).
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl20(array $s, array $p): array {
        $out = [];
        $hs = $s['course']['formatoptions']['hiddensections'] ?? null;
        $out[] = self::f((string) $hs === '1' ? self::CONFORME : self::NAO_CONFORME, 'Configurações do curso > Formato > Seções escondidas', "hiddensections=" . var_export($hs, true),
            ['esperado' => '1 (completamente invisíveis)']);
        foreach ($s['sections'] as $sec) {
            if ($sec['number'] == 0) {
                continue;
            }
            $mods = array_filter($sec['sequence'], fn($c) => isset($s['modules'][$c]));
            $lbl = "Seção {$sec['number']} \"{$sec['name']}\"";
            if (!$mods) {
                $out[] = self::f(self::ATENCAO, $lbl, 'seção sem módulos', ['orientacao' => 'Excluir ou ocultar a seção.']);
            } else if (count(array_filter($mods, fn($c) => $s['modules'][$c]['modname'] !== 'label')) === 0) {
                $out[] = self::f(self::ATENCAO, $lbl, 'seção só com rótulos', ['gravidade' => 'Baixa']);
            }
            if ($sec['rawname'] !== null && preg_match($p['generic_section_names'], self::norm($sec['rawname']))) {
                $out[] = self::f(self::ATENCAO, $lbl, 'nome genérico "' . $sec['rawname'] . '"', ['orientacao' => 'Nomear a seção conforme o conteúdo.']);
            }
            if (!$sec['visible'] && array_filter($mods, fn($c) => $s['modules'][$c]['completion'])) {
                $out[] = self::f(self::ATENCAO, $lbl, 'seção oculta com atividades que têm conclusão', ['gravidade' => 'Média']);
            }
        }
        return $out;
    }

    /**
     * CL-21.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl21(array $s, array $p): array {
        $out = [];
        $names = array_map(fn($b) => $b['name'] . ($b['inherited'] ? '(site/categoria)' : ''), $s['blocks']);
        $titles = array_map(fn($b) => self::norm($b['title']), $s['blocks']);
        $hasprogress = (bool) array_filter($s['blocks'], fn($b) => $b['name'] === 'completion_progress');
        $out[] = self::f($hasprogress ? self::CONFORME : self::NAO_CONFORME, 'Blocos da página do curso', 'blocos=' . json_encode($names, JSON_UNESCAPED_UNICODE) . ' (fonte: ' . $s['blocks_source'] . ')',
            ['esperado' => 'bloco Progresso de Conclusão presente']);
        foreach ($p['required_blocks']['html'] as $t) {
            $ok = (bool) array_filter($titles, fn($x) => strpos($x, self::norm($t)) !== false);
            $out[] = self::f($ok ? self::CONFORME : self::NAO_CONFORME, 'Blocos da página do curso', "bloco \"$t\": " . ($ok ? 'presente' : 'ausente'),
                ['orientacao' => $ok ? null : 'Adicionar o bloco HTML padrão (ou verificar o bloco de site correspondente).']);
        }
        return $out;
    }

    /**
     * CL-22.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl22(array $s, array $p): array {
        $out = [];
        $labels = array_filter(self::mods($s, 'label'), fn($m) => $m['sectionnumber'] == 0 && strpos(self::norm(self::text($m['texts']['intro'] ?? '')), self::norm($p['licence_label_text'])) !== false);
        $out[] = self::f($labels ? self::CONFORME : self::NAO_CONFORME, 'Área do curso (seção 0)', 'rótulo "' . $p['licence_label_text'] . '": ' . ($labels ? 'presente' : 'ausente'));
        $fb = self::mods($s, 'feedback', $p['licence_feedback_name']);
        $out[] = self::f($fb ? self::CONFORME : self::NAO_CONFORME, 'Atividades do curso', 'pesquisa "' . $p['licence_feedback_name'] . '": ' . ($fb ? 'presente' : 'ausente'));
        $dc = self::mods($s, $p['required_certificate_module'], $p['licence_certificate_name']);
        $out[] = self::f($dc ? self::CONFORME : self::NAO_CONFORME, 'Atividades do curso', 'declaração "' . $p['licence_certificate_name'] . '": ' . ($dc ? 'presente' : 'ausente'));
        return $out;
    }

    /**
     * CL-23.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function cl23(array $s, array $p): array {
        $certs = self::certs($s, $p);
        if (!$certs) {
            return [self::f(self::NAO_CONFORME, 'Atividades do curso', 'nenhum certificado de conclusão (' . $p['required_certificate_module'] . ')', ['orientacao' => 'Importar o certificado da Sala Padrão.'])];
        }
        $ev = self::mods($s, 'feedback', $p['required_feedback_evaluation']);
        $ci = $s['grade']['course_item'];
        $out = [];
        foreach ($certs as $c) {
            $comp = [];
            $grades = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                if (($cond['type'] ?? '') === 'completion') {
                    $comp[] = (int) $cond['cm'];
                }
                if (($cond['type'] ?? '') === 'grade') {
                    $grades[] = $cond;
                }
            }
            $okev = $ev && in_array($ev[0]['cmid'], $comp);
            $out[] = self::f($okev ? self::CONFORME : self::NAO_CONFORME, self::label($c), 'exige conclusão de cmids ' . json_encode($comp),
                ['cmid' => $c['cmid'], 'esperado' => 'exigir a Avaliação do curso' . ($ev ? " (cm={$ev[0]['cmid']})" : ' (inexistente no curso)')]);
            $g60 = array_values(array_filter($grades, fn($g) => isset($g['min']) && (float) $g['min'] >= (float) $p['certificate_min_grade']));
            if (!$g60) {
                $out[] = self::f(self::NAO_CONFORME, self::label($c), 'condições de nota=' . json_encode($grades), ['cmid' => $c['cmid'], 'esperado' => 'nota ≥ ' . $p['certificate_min_grade'] . ' no total do curso']);
            } else if ($ci && (int) $g60[0]['id'] !== (int) $ci['id']) {
                $out[] = self::f(self::NAO_CONFORME, self::label($c), 'condição de nota aponta para o item ' . $g60[0]['id'], ['cmid' => $c['cmid'], 'esperado' => "item Total do curso (id {$ci['id']})",
                    'orientacao' => 'A condição de nota do certificado deve referenciar o Total do curso.']);
            } else {
                $out[] = self::f(self::CONFORME, self::label($c), 'nota ≥ ' . $g60[0]['min'] . ' no Total do curso (item ' . $g60[0]['id'] . ')', ['cmid' => $c['cmid']]);
            }
            if (($c['availability']['op'] ?? '&') !== '&') {
                $out[] = self::f(self::NAO_CONFORME, self::label($c), 'operador raiz=' . $c['availability']['op'], ['cmid' => $c['cmid'], 'esperado' => '&']);
            }
        }
        return $out;
    }

    /**
     * X-INT-02: file records whose content is not readable from the file store (distinct from missing references).
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xint02(array $s, array $p): array {
        $out = [];
        $total = 0;
        foreach ($s['modules'] as $m) {
            $total += $m['files']['count'];
            foreach ($m['files']['missing_in_storage'] as $fe) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), "arquivo \"{$fe['filename']}\" ({$fe['component']}/{$fe['filearea']}, {$fe['filesize']} bytes" . ($fe['is_reference'] ? ', alias' : '') . ') registrado mas sem conteúdo legível no armazenamento',
                    ['cmid' => $m['cmid'], 'orientacao' => 'Reenviar o arquivo. Se for alias (Arquivos do servidor), o original foi removido ou não foi copiado.']);
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Arquivos dos módulos', "$total arquivos com conteúdo legível no armazenamento")];
    }

    /**
     * X-INT-03.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xint03(array $s, array $p): array {
        $out = [];
        $std = \core_plugin_manager::standard_plugins_list('mod') ?: [];
        foreach ($s['plugins']['used']['mod'] as $mod) {
            $installed = isset($s['plugins']['installed']['mod'][$mod]);
            $out[] = self::f($installed ? self::CONFORME : self::NAO_CONFORME, "módulo $mod", ($installed ? 'instalado (versão ' . $s['plugins']['installed']['mod'][$mod] . ')' : 'NÃO instalado')
                . (in_array($mod, $std) ? '' : ' — plugin de terceiros'), ['gravidade' => in_array($mod, $std) ? 'Informativa' : 'Alta']);
        }
        $stdav = \core_plugin_manager::standard_plugins_list('availability') ?: [];
        foreach ($s['plugins']['used']['availability'] as $type) {
            $installed = isset($s['plugins']['installed']['availability'][$type]);
            $out[] = self::f($installed ? self::CONFORME : self::NAO_CONFORME, "condição de acesso \"$type\"", $installed ? 'plugin availability_' . $type . ' instalado' : 'plugin availability_' . $type . ' AUSENTE: condições deste tipo são ignoradas silenciosamente',
                ['gravidade' => in_array($type, $stdav) ? 'Informativa' : 'Alta']);
        }
        return $out ?: [self::f(self::NA, 'Plugins', 'curso sem módulos nem condições de acesso')];
    }

    /**
     * X-CONT-01: empty resources.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xcont01(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            if (in_array($m['modname'], ['page', 'label', 'book'])) {
                $all = html_entity_decode(implode(' ', $m['texts']));
                if (self::text($all) === '' && !preg_match('/<(img|iframe|video|audio|a)\b/i', $all)) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'sem texto e sem mídia/links', ['cmid' => $m['cmid'], 'orientacao' => 'Preencher ou remover o recurso.']);
                }
                if ($m['modname'] === 'book' && empty($m['extra']['chapters'])) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'livro sem capítulos', ['cmid' => $m['cmid']]);
                }
            }
            if ($m['modname'] === 'quiz' && ($m['extra']['slots'] ?? 0) == 0) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), 'questionário sem questões', ['cmid' => $m['cmid'], 'gravidade' => 'Alta']);
            }
            if ($m['modname'] === 'feedback' && ($m['extra']['items'] ?? 0) == 0) {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), 'pesquisa sem itens', ['cmid' => $m['cmid']]);
            }
            if (in_array($m['modname'], ['resource', 'folder'])) {
                $content = array_filter($m['files']['list'], fn($fe) => $fe['filearea'] === 'content');
                if (!$content) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'recurso de arquivo sem nenhum arquivo associado', ['cmid' => $m['cmid'], 'gravidade' => 'Alta',
                        'orientacao' => 'Reenviar o arquivo. Na restauração do curso 670 isto ocorreu com aliases para "Arquivos do servidor" que não foram incluídos no backup.']);
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Recursos', 'nenhum recurso vazio detectado')];
    }

    /**
     * X-CONT-04.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xcont04(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            $paths = [];
            foreach ($m['files']['list'] as $fe) {
                $paths[($fe['filepath'] ?: '/') . $fe['filename']] = true;
            }
            foreach ($m['texts'] as $field => $t) {
                if (!preg_match_all('~@@PLUGINFILE@@(/[^"\'\s)<>]+)~', html_entity_decode((string) $t), $mm)) {
                    continue;
                }
                foreach (array_unique($mm[1]) as $ref) {
                    $path = rawurldecode(preg_replace('/[?#].*$/', '', $ref));
                    if (!isset($paths[$path])) {
                        $out[] = self::f(self::NAO_CONFORME, self::label($m) . " campo $field", "@@PLUGINFILE@@$ref não encontrado entre os arquivos do módulo",
                            ['cmid' => $m['cmid'], 'orientacao' => 'Imagem/arquivo quebrado: reenviar no editor. Típico de aliases não restaurados ("unable to restore alias").']);
                    }
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Conteúdos', 'todas as referências @@PLUGINFILE@@ resolvidas')];
    }

    /**
     * X-AV-01.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xav01(array $s, array $p): array {
        $out = [];
        foreach (self::graded($s) as [$m, $gi]) {
            $intro = self::norm(self::text($m['texts']['intro'] ?? ''));
            if (preg_match('/valor\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*pontos?/u', $intro, $mm)) {
                $declared = (float) str_replace(',', '.', $mm[1]);
                if (abs($declared - $gi['grademax']) > 0.001) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), "enunciado declara {$mm[1]} pontos; nota máxima={$gi['grademax']}", ['cmid' => $m['cmid'], 'orientacao' => 'Alinhar enunciado e nota máxima.']);
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Enunciados', 'sem divergências entre enunciado e pontuação')];
    }

    /**
     * X-DEP-01.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xdep01(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') === 'completion' && !isset($s['modules'][(int) $c['cm']])) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), "condição completion cm={$c['cm']} não existe no curso", ['cmid' => $m['cmid'], 'orientacao' => 'Remover/recriar a restrição órfã.']);
                }
            }
        }
        foreach ($s['sections'] as $sec) {
            foreach (snapshot::walk_conditions($sec['availability']) as $c) {
                if (($c['type'] ?? '') === 'completion' && !isset($s['modules'][(int) $c['cm']])) {
                    $out[] = self::f(self::NAO_CONFORME, "Seção {$sec['number']}", "condição completion cm={$c['cm']} não existe no curso");
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Restrições de acesso', 'todas as referências a módulos existem')];
    }

    /**
     * X-DEP-02.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xdep02(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') !== 'completion' || !isset($s['modules'][(int) $c['cm']])) {
                    continue;
                }
                $t = $s['modules'][(int) $c['cm']];
                if ($t['completion'] == 0) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'exige conclusão de ' . self::label($t) . ' que NÃO tem acompanhamento de conclusão', ['cmid' => $m['cmid'],
                        'orientacao' => 'Habilitar conclusão no módulo-alvo ou remover a condição; do contrário ela nunca será satisfeita.']);
                } else if ($t['visible'] == 0) {
                    $out[] = self::f(self::ATENCAO, self::label($m), 'exige conclusão de módulo OCULTO ' . self::label($t), ['cmid' => $m['cmid'], 'gravidade' => 'Alta']);
                }
                if (in_array($c['e'] ?? 1, [2, 3]) && !$t['grade_items']) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'exige conclusão com aprovação/reprovação de módulo sem nota ' . self::label($t), ['cmid' => $m['cmid']]);
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Restrições de acesso', 'todas as condições de conclusão apontam para módulos com conclusão habilitada e visíveis')];
    }

    /**
     * X-DEP-03.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xdep03(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') === 'grade' && !isset($s['grade']['all_items'][(int) ($c['id'] ?? 0)])) {
                    $out[] = self::f(self::NAO_CONFORME, self::label($m), 'condição de nota aponta para item inexistente id=' . ($c['id'] ?? 'null'), ['cmid' => $m['cmid']]);
                }
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Restrições de acesso', 'todas as condições de nota resolvidas')];
    }

    /**
     * X-DEP-04.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xdep04(array $s, array $p): array {
        $g = [];
        foreach ($s['modules'] as $m) {
            foreach (snapshot::walk_conditions($m['availability']) as $c) {
                if (($c['type'] ?? '') === 'completion') {
                    $g[$m['cmid']][] = (int) $c['cm'];
                }
            }
        }
        $color = [];
        $cycles = [];
        $dfs = function($u, $path) use (&$dfs, &$color, &$cycles, $g) {
            $color[$u] = 1;
            $path[] = $u;
            foreach ($g[$u] ?? [] as $v) {
                if (($color[$v] ?? 0) === 1) {
                    $cycles[] = array_merge(array_slice($path, array_search($v, $path)), [$v]);
                } else if (($color[$v] ?? 0) === 0) {
                    $dfs($v, $path);
                }
            }
            $color[$u] = 2;
        };
        foreach (array_keys($g) as $u) {
            if (($color[$u] ?? 0) === 0) {
                $dfs($u, []);
            }
        }
        if ($cycles) {
            return array_map(fn($c) => self::f(self::NAO_CONFORME, 'Grafo de restrições', 'ciclo: ' . implode(' → ', $c), ['orientacao' => 'Remover uma das condições para quebrar o ciclo.']), $cycles);
        }
        $edges = array_sum(array_map('count', $g));
        return [self::f(self::CONFORME, 'Grafo de restrições', count($g) . " módulos com condições, $edges arestas, sem ciclos")];
    }

    /**
     * X-CERT-01.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xcert01(array $s, array $p): array {
        $out = [];
        foreach (self::certs($s, $p) as $c) {
            $stack = [$c['cmid']];
            $seen = [];
            $chain = [];
            while ($stack) {
                $u = array_pop($stack);
                if (isset($seen[$u])) {
                    continue;
                }
                $seen[$u] = true;
                foreach (snapshot::walk_conditions($s['modules'][$u]['availability'] ?? null) as $cond) {
                    if (($cond['type'] ?? '') === 'completion') {
                        $chain[(int) $cond['cm']] = true;
                        $stack[] = (int) $cond['cm'];
                    }
                }
            }
            $probs = [];
            foreach (array_keys($chain) as $v) {
                $t = $s['modules'][$v] ?? null;
                if (!$t) {
                    $probs[] = "cm $v inexistente";
                } else if ($t['completion'] == 0) {
                    $probs[] = self::label($t) . ' sem conclusão';
                } else if ($t['visible'] == 0) {
                    $probs[] = self::label($t) . ' oculto';
                } else if ($t['uservisible'] === false) {
                    $probs[] = self::label($t) . ' indisponível para o usuário avaliado';
                }
            }
            $types = [];
            foreach (snapshot::walk_conditions($c['availability']) as $cond) {
                $types[$cond['type']] = ($types[$cond['type']] ?? 0) + 1;
            }
            $out[] = self::f($probs ? self::NAO_CONFORME : self::CONFORME, self::label($c), 'cadeia transitiva de conclusão: ' . count($chain) . ' módulos; tipos de condição: ' . json_encode($types),
                ['cmid' => $c['cmid'], 'encontrado' => $probs ? implode('; ', $probs) : 'cadeia estaticamente satisfazível',
                'orientacao' => 'Verificação estática; a emissão real exige usuário de teste.']);
            if ($c['visible'] == 0) {
                $out[] = self::f(self::NAO_CONFORME, self::label($c), 'certificado oculto', ['cmid' => $c['cmid']]);
            }
        }
        return $out ?: [self::f(self::NAO_CONFORME, 'Atividades do curso', 'sem certificado de conclusão')];
    }

    /**
     * X-STR-01.
     *
     * @param array $s
     * @param array $p
     * @return array
     */
    public static function xstr01(array $s, array $p): array {
        $out = [];
        foreach ($s['modules'] as $m) {
            if ($m['visible'] == 0) {
                $out[] = self::f(self::ATENCAO, self::label($m), 'módulo oculto' . ($m['visibleoncoursepage'] == 0 ? ' (stealth)' : ''), ['cmid' => $m['cmid'], 'orientacao' => 'Confirmar se deve permanecer oculto.']);
            } else if ($m['uservisible'] === false && $s['viewer_userid']) {
                $out[] = self::f(self::ATENCAO, self::label($m), 'indisponível para o usuário ' . $s['viewer_userid'] . ': ' . self::text($m['availableinfo']), ['cmid' => $m['cmid'], 'gravidade' => 'Informativa']);
            }
            if ($m['modname'] !== 'label' && self::text($m['name']) === '') {
                $out[] = self::f(self::NAO_CONFORME, self::label($m), 'nome vazio', ['cmid' => $m['cmid']]);
            }
        }
        return $out ?: [self::f(self::CONFORME, 'Estrutura', 'sem módulos ocultos ou sem nome')];
    }
}
