# Catálogo consolidado de regras do Auditor MOOC

**Data:** 2026-10-28 · **Fontes:** checklist institucional (29 itens), `RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md`
(catálogo ampliado), `RELATORIO-AUDITORIA-VIA-API-MOODLE.md` (verificações ENV-*), `poc/mbz_inspect.py` (51 regras,
origem dos ids), `plugin/local_mooc_audit/classes/rules.php` (29 regras implementadas), `tests/engine_test.php`,
`plugin-results/`.

Este catálogo é a **referência de escopo** do produto. Os 29 itens do checklist não representam todo o escopo; o
catálogo ampliado (X-*) e as verificações só possíveis na sala ativa (ENV-*) fazem parte dele.

## Convenções

- **ID**: `CL-nn` = item nn do checklist; `X-ÁREA-nn` = verificação adicional (INT integridade, CONT conteúdo, AV
  avaliação, DEP dependências, CERT certificação, STR estrutura, BLK blocos, ACC acessibilidade, SEC segurança);
  `ENV-nn` = só na sala ativa (relatório de API). Ids são estáveis; semântica divergente entre protótipos está
  anotada.
- **Origem**: `checklist:n`, `extra:§` (seção do relatório de investigação), `api:§5`.
- **Tipo**: **I** institucional (parâmetro de política do Cefor) · **T** técnica universal (vale para qualquer
  Moodle) · **M** mista.
- **Política**: chaves de `policy.php` lidas pela regra; `—` = nenhuma.
- **Status (plugin)**: Implementado e testado · Implementado com testes insuficientes · Parcialmente implementado ·
  Planejado · Não validado · **Fora do plugin** (só pacote `.mbz`, fonte externa ou verificação humana).
- **Evidência usada**: fonte de dados no `snapshot` (API Moodle ou tabela DML com lista branca).
- **Testes**: `PHPUnit` (automatizado), `A/B` (cursos sintéticos, manual), `55/670` (salas restauradas, manual),
  `só C` (apenas caminho conforme observado), `nenhum`.
- **FP**: risco de falso positivo (nulo / baixo / médio / alto).
- **Compl.**: complexidade estimada de implementação ou de consolidação (B baixa, M média, A alta).
- **Prio.**: 1 = MVP (spec 001), 2 = incremento seguinte, 3 = posterior, — = não automatizável.

---

## A. Itens do checklist institucional (CL-01 … CL-29)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| CL-01 | checklist:1 | I | Página "Informações sobre o curso" presente com a estrutura padrão (12 cabeçalhos) | nome canônico da página (não existe ainda na política) | Planejado | `page.content` já coletado em `texts` | nenhum | médio (páginas em Libras não têm cabeçalhos) | M | 2 |
| CL-02 | checklist:2 | I | Informações iguais ao Projeto do Curso (SIPAC) | — | Fora do plugin (fonte externa) | — | — | — | — | — |
| CL-03 | checklist:3 | I | Professores cadastrados conforme projeto; demais como estudante | papéis esperados | Planejado | `get_enrolled_users`/`role_assignments` (API) | nenhum | baixo | B | 2 |
| CL-04 | checklist:4 | I | Sumário contém os botões institucionais (links Criar Conta / Turmas anteriores) | `summary_required_links` | Implementado com testes insuficientes | `course.summary` | A/B, 55/670 (só C) | baixo | B | 1 |
| CL-05 | checklist:5 | I | Categoria do curso = "Inscrições Abertas" | `target_category_name` | Implementado com testes insuficientes | `core_course_category` | 55/670 (NC), A/B (C) | nulo | B | 1 |
| CL-06 | checklist:6 | I | Data de início = 01/01 do ano da reoferta | `reoffer_year`, `required_start_date`, `min_days_between_start_and_end` | Implementado com testes insuficientes | `course.startdate/enddate` | A (C), B/55/670 (NC) | nulo | B | 1 |
| CL-07 | checklist:7 | I | Data de término = 31/12 do ano da reoferta | `reoffer_year`, `allowed_end_dates` | Implementado com testes insuficientes | `course.enddate` | A (C), B/55/670 (NC) | nulo | B | 1 |
| CL-08 | checklist:8 | I | Autoinscrição habilitada, novas inscrições permitidas, datas = curso; visitante ativo é alerta | — (datas derivadas do curso) | Implementado com testes insuficientes | `enrol_get_instances` (`status`, `customint6`, datas, `password` como booleano) | A (C), B/55/670 (NC) | nulo | B | 1 |
| CL-09 | checklist:9 | I | Fórum "Espaço de interação" presente e sem conclusão | `required_forum_name` | Implementado com testes insuficientes | `cm_info` (forum) | B (NC conclusão), 55 (NC ausente), A/670 (C) | **alto em cursos antigos** (nome; decisão 10 pendente) | B | 1 |
| CL-10 | checklist:10 | T | Vídeos incorporados e funcionando | — | Planejado (inventário); verificação de rede fora do MVP | iframes/URLs em `texts` | nenhum | médio (rede) | A | 3 |
| CL-11 | checklist:11 | I | Pesquisa de Perfil presente, conclusão automática ao enviar, exigida pelo certificado | `required_feedback_profile`, `required_certificate_module`, `licence_certificate_name` | Implementado com testes insuficientes | `cm_info` + `feedback.completionsubmit` (DML) + `availability` do certificado | 55 (NC), A/B/670 (C) | baixo (nome) | M | 1 |
| CL-12 | checklist:12 | I | Soma dos máximos = 100; agregação Natural; "excluir vazias" desligado; total do curso = 100; alerta para subcategorias | `course_total_max`, `required_aggregation`, `aggregateonlygraded_allowed` | Implementado e testado (**soma**); agregação/excluir vazias só A/B | `grade_item`, `grade_category` | PHPUnit (soma), B/55 (NC excluir vazias) | nulo | M | 1 |
| CL-13 | checklist:13 | I | Tentativas ≥ 2 (0 = ilimitadas); enunciado declara o mesmo número | `min_quiz_attempts`, `attempts_rule` | Implementado e testado (tentativas); enunciado só A/B | `quiz.attempts` (DML), `quiz.intro` | PHPUnit, B (NC ×2) | baixo (tentativas) / médio (regex do enunciado) | B | 1 |
| CL-14 | checklist:14 | I | Atividades avaliativas com data de encerramento; alerta se ≠ fim do curso | — | Implementado com testes insuficientes | `quiz/game/choice/feedback.timeclose`, `assign/forum.cutoffdate|duedate`, `data.timeavailableto` (DML) | B (NC ×2 + ATENCAO), 55 (NC ×4) | baixo; H5P sem campo → NV | B | 1 |
| CL-15 | checklist:15 | I | Restrições das avaliações corretas (parte "Escolha a sua situação" retirada pela decisão 6) | — | Fora do plugin como regra; coberto por inventário de restrições + X-DEP-* | `availability` | — | — | — | — |
| CL-16 | checklist:16 | I | Avaliação do curso exige conclusão de todas as atividades com nota; operador `&` | `required_feedback_evaluation` | Implementado com testes insuficientes | `availability` da Avaliação × itens de nota | B (NC), A (C) | baixo (nome) | M | 1 |
| CL-17 | checklist:17 | I | Nenhuma conclusão manual; automática com critério adequado ao tipo; síntese de contagens | `completion_expected` | Implementado com testes insuficientes | `cm_info.completion*` + campos `completion*` por módulo (DML) | B (NC ×2), 668 (MBZ) | baixo (manual) / médio (critério por tipo) | M | 1 |
| CL-18 | checklist:18 | M | Glossário colaborativo proibido; glossário fonte de jogos tolerado, vazio = bloqueante | `forbidden_modules` | Implementado com testes insuficientes | `glossary_entries` (count), `game.sourcemodule/glossaryid/glossarycategoryid` (DML) | B (NC bloq. → ATENCAO com 3 entradas → NC após importação), 55 (NC bloq.) | nulo | M | 1 |
| CL-19 | checklist:19 | I | Rótulos sem conclusão | — | Implementado com testes insuficientes | `cm_info.completion` (label) | B (NC), A (C) | nulo | B | 1 |
| CL-20 | checklist:20 | M | `hiddensections=1`; seções vazias ou só com rótulos; nome genérico; seção oculta com atividades com conclusão | `generic_section_names` | Implementado com testes insuficientes | `course_get_format()->get_format_options()`, `section_info` | B (NC + ATENCAO), 670 (ATENCAO ×6) | baixo | B | 1 |
| CL-21 | checklist:21 | I | Bloco Progresso de Conclusão e blocos HTML padrão ("Atenção ao prazo", "Dúvidas técnicas", "Acessibilidade") na página do curso, inclusive herdados | `required_blocks` | Implementado com testes insuficientes | `block_manager` (fallback DML `block_instances`) | A (C, 4 blocos), B/55/670 (NC Acessibilidade) | médio (bloco de site não reproduzido no laboratório; decisão 8) | M | 1 |
| CL-22 | checklist:22 | I | Rótulo, pesquisa e declaração "Licença para capacitação" presentes | `licence_label_text`, `licence_feedback_name`, `licence_certificate_name` | Implementado com testes insuficientes | `cm_info`, `label.intro` | 55 (NC ×3), A/B/670 (C) | baixo (nome) | B | 1 |
| CL-23 | checklist:23 | I | Certificado exige conclusão da Avaliação **e** nota ≥ 60 no item Total do curso, operador `&` | `certificate_min_grade`, `required_feedback_evaluation`, `required_certificate_module` | Implementado com testes insuficientes | `availability` do certificado × `grade_item course` | B (NC), A (C) | baixo | M | 1 |
| CL-24 | checklist:24 | I | Imagens do certificado (frente/verso) iguais à arte oficial | hashes oficiais (inexistentes na política) | Planejado | `files` do contexto do certificado (`contenthash`) | nenhum | nulo se hash fornecido | B | 2 (depende de insumo) |
| CL-25 | checklist:25 | I | Texto do certificado conforme padrão oficializado (decisão 7); emissão de teste | texto padrão (inexistente na política) | Planejado (texto); emissão **fora do plugin** | `simplecertificate.certificatetext` (DML) | nenhum | médio (similaridade) → só ATENCAO | M | 2 |
| CL-26 | checklist:26 | I | Padrão visual (topo, divisores, imagens de seção) | — | Fora do plugin (humano); inventário possível | `course.overviewfiles`, `section.summary` | — | alto | — | 3 |
| CL-27 | checklist:27 | I | Capa = thumbnail da vitrine | — | Fora do plugin (sistema externo) | — | — | — | — | — |
| CL-28 | checklist:28 | I | URL única | — | Fora do plugin (CGTI) | — | — | — | — | — |
| CL-29 | checklist:29 | I | Cadastro na vitrine | — | Fora do plugin (WordPress) | — | — | — | — | — |

## B. Verificações adicionais — integridade (X-INT)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-INT-01 | extra:6.1 | T | Pacote `.mbz` truncado/corrompido | — | **Fora do plugin** (só pacote; comprovado no 668 pelo protótipo) | — | — | nulo | — | — |
| X-INT-02 | extra:6.1 | T | Arquivo registrado em `files` sem conteúdo legível no `filedir` (inclui alias) | — | Implementado com testes insuficientes | `files` (DML) + `file_storage::get_file_by_id` + `file_system::is_file_readable_*` | B (NC, arquivo removido do filedir), 12 (NC herdado) | nulo | M | 1 |
| X-INT-03 | extra:6.1 | T | Módulos e tipos de condição usados pelo curso instalados no site; terceiros destacados | — | Implementado com testes insuficientes (só C) | `core_plugin_manager::get_installed_plugins`, `standard_plugins_list` | A/B (C) | baixo | B | 1 (acrescentar teste de ausência) |
| X-INT-04 | extra:6.1 | T | `inforef` aponta para arquivo inexistente no pacote | — | **Fora do plugin** (só pacote) | — | — | — | — | — |
| X-INT-05 | extra:6.1 | T | Backup de outro site/versão | — | Fora do plugin | — | — | — | — | — |
| X-INT-06 | extra:6.1 | T | Componentes ausentes por opção de exportação | — | Fora do plugin | — | — | — | — | — |

## C. Verificações adicionais — conteúdo (X-CONT)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-CONT-01 | extra:6.2 | T | Página/rótulo/livro sem texto nem mídia; livro sem capítulos; quiz sem questões; pesquisa sem itens; arquivo/pasta sem arquivo | — | Implementado com testes insuficientes | `texts`, `quiz_slots`, `feedback_item`, `book_chapters` (count), `files` (`filearea=content`) | B (NC ×2), 670 (NC ×4 recursos sem arquivo) | baixo | M | 1 |
| X-CONT-02 | extra:6.2 | T | Nomes duplicados **com conteúdo idêntico** (versões em Libras não são duplicata) | — | Planejado | `texts` (hash) | nenhum | alto se só por nome | B | 3 |
| X-CONT-03 | extra:6.2/6.6 | T | URL absoluta ao próprio Moodle com id numérico do curso antigo | `wwwroot` | Planejado | `texts` (regex) | nenhum | baixo | B | 2 |
| X-CONT-04 | extra:6.2 | T | `@@PLUGINFILE@@` sem arquivo correspondente (ignorando `?time=` e `#`) | — | Implementado com testes insuficientes | `texts` × `files.list` | B (NC), 670 (NC ×7) | baixo | B | 1 |
| X-CONT-05 | extra:6.2/6.6 | I | Anos anteriores em campos institucionais | `reoffer_year` | Planejado | `texts`, `course.summary` | nenhum | médio → ATENCAO | B | 3 |
| X-CONT-06 | extra:6.2 | T | Inventário de hosts externos e `http://` | — | Planejado | `texts`, `url.externalurl` | nenhum | — (informativo) | B | 2 |
| X-CONT-07 | extra:6.2 | T | Links externos quebrados / vídeos indisponíveis (rede, SSRF-safe) | allowlist de domínios | Planejado (fora do MVP) | rede | nenhum | médio | A | 3 |
| X-CONT-08 | extra:6.2 | T | Serviço externo obrigatório na cadeia do certificado (ex.: Google Forms) | — | Planejado | `url.externalurl` × cadeia de restrições | nenhum | baixo | M | 2 |

## D. Verificações adicionais — avaliação (X-AV)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-AV-01 | extra:6.3 | T | Enunciado declara "Valor: N pontos" ≠ nota máxima | — | Implementado com testes insuficientes | `intro` (regex) × `grade_item.grademax` | B (NC) | médio (regex) | B | 1 |
| X-AV-02 | extra:6.3 | I | Texto institucional de pontuação (sumário) × livro de notas | — | Planejado | `course.summary` × itens | nenhum (668 comprovado por leitura) | médio → ATENCAO | M | 2 |
| X-AV-03 | extra:6.3 | T | Quiz avaliativo com `grade=0`; item oculto; `gradetype=0` em atividade "avaliativa" | — | Planejado | `grade_item` | nenhum | baixo | B | 2 |
| X-AV-04 | extra:6.3 | I | `aggregateonlygraded=1` na categoria raiz | `aggregateonlygraded_allowed` | Implementado **dentro de CL-12** (sem id próprio no plugin) | `grade_category` | B/55 (NC), A (C) | nulo | — | 1 (manter em CL-12; registrar no catálogo) |
| X-AV-05 | extra:6.3 | T | Datas de abertura/fechamento incoerentes com o curso | — | Parcialmente implementado (CL-14 alerta ≠ fim do curso) | `timeopen/timeclose` × `startdate/enddate` | B (ATENCAO) | baixo | B | 2 |
| X-AV-06 | extra:6.3 | T | Questões de tipos não nativos | — | Planejado (`qtype` já coletado em `plugins.installed`) | `question_bank_entries` | nenhum | nulo | B | 3 |

## E. Verificações adicionais — dependências e restrições (X-DEP)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-DEP-01 | extra:6.4 | T | Condição `completion` aponta para cm inexistente (módulo ou seção) | — | Implementado com testes insuficientes (só C) | `availability` × `modules` | A/B (C) | nulo | B | 1 (acrescentar teste NC) |
| X-DEP-02 | extra:6.4 | T | Condição exige conclusão de módulo sem conclusão (impossível) ou oculto (alerta); `e=2/3` em módulo sem nota | — | **Implementado e testado** | `availability` × `cm_info.completion/visible/grade_items` | PHPUnit, B | nulo | M | 1 |
| X-DEP-03 | extra:6.4 | T | Condição de nota aponta para item inexistente | — | Implementado com testes insuficientes (só C) | `availability` × `grade.all_items` | A/B (C) | nulo | B | 1 (acrescentar teste NC) |
| X-DEP-04 | extra:6.4 | T | Ciclos no grafo de conclusão | — | **Implementado e testado** | grafo de `availability` (DFS) | PHPUnit, B | nulo | M | 1 |
| X-DEP-05 | extra:6.4 | T | Condições duplicadas/redundantes (ex.: `days` 2×) | — | Planejado | `availability` | nenhum (668 MBZ) | nulo | B | 2 |
| X-DEP-06 | extra:6.4 | T | Condição `date` no passado / `days` incompatível com a duração | `reoffer_year` | Planejado | `availability` × datas | nenhum | baixo | B | 2 |
| X-DEP-07 | extra:6.4 | T | Grupo `\|` cujas alternativas são todas impossíveis | — | Planejado | grafo | nenhum | baixo | M | 3 |

## F. Verificações adicionais — certificação (X-CERT)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-CERT-01 | extra:6.5 | T | Alcançabilidade estática do certificado (cadeia transitiva existe, visível, com conclusão; visão do usuário de teste); certificado oculto | `required_certificate_module`, `licence_certificate_name` | Implementado com testes insuficientes | grafo + `uservisible` | B (NC com dois motivos; `--userid` acrescenta indisponibilidade) | baixo | M | 1 |
| X-CERT-02 | extra:6.5 | T | Ciclo envolvendo o certificado | — | coberto por X-DEP-04 | — | — | — | — | — |
| X-CERT-03 | extra:6.5 | I | Placeholders inválidos; `{COURSENAME}`/carga horária ausentes ou divergentes | texto padrão | Planejado | `certificatetext` | nenhum | médio | M | 2 |
| X-CERT-04 | extra:6.5 | I | Imagens ≠ arte oficial | hashes | = CL-24 | — | — | — | — | 2 |
| X-CERT-05 | extra:6.5 | T | `requiredtime` × `days` (duas formas de exigir tempo) | — | Planejado | `simplecertificate.requiredtime` (já coletado) × `availability` | nenhum | baixo | B | 3 |

## G. Estrutura, blocos, acessibilidade, segurança (X-STR, X-BLK, X-ACC, X-SEC)

| ID | Origem | Tipo | Descrição | Política | Status (plugin) | Evidência usada | Testes | FP | Compl. | Prio. |
|---|---|---|---|---|---|---|---|---|---|---|
| X-STR-01 | extra:6.7 | T | Módulos ocultos (inclusive stealth); indisponíveis para o usuário de teste; nome vazio (nomes genéricos de seção migraram para CL-20) | — | Implementado com testes insuficientes | `cm_info.visible/visibleoncoursepage/uservisible/name` | B (ATENCAO) | baixo | B | 1 |
| X-STR-02 | extra:6.7 | T | Seções vazias / só rótulos / oculta com atividades obrigatórias | — | Implementado **dentro de CL-20** | `section_info` | B, 670 | baixo | — | 1 (manter em CL-20) |
| X-STR-03 | extra:6.7 | T | Fragmentação excessiva (muitos itens por seção) | limiar | Planejado (recomendação, não regra) | `section.sequence` | nenhum | alto | B | 3 |
| X-BLK-01 | extra:6.7 | T | Bloco de progresso referencia atividades inexistentes ou sem conclusão | — | Planejado (config do bloco já coletada em `blocks[].config`) | `block_instances.configdata` (`selectactivities`) | nenhum | baixo | B | 2 |
| X-ACC-01 | extra:6.8 | T | `<img>` sem `alt` (agregado por módulo) | — | Planejado | `texts` | nenhum (55/668/670 MBZ) | baixo | B | 2 |
| X-ACC-02 | extra:6.8 | T | Texto de link genérico | — | Planejado | `texts` | nenhum (668 MBZ) | médio | B | 3 |
| X-ACC-03 | extra:6.8 | T | iframes sem `title`; vídeos sem legenda/Libras | — | Planejado | `texts` | nenhum | médio | M | 3 |
| X-ACC-04 | extra:6.8 | T | HTML inadequado (tabelas de layout, cabeçalhos fora de ordem) | — | Planejado | `texts` | nenhum | alto | A | 3 |
| X-SEC-01 | extra:12 | T | Senha em método de inscrição (apenas presença) | — | Parcialmente implementado (CL-08 relata "com senha" no visitante; `has_password` coletado para todos) | `enrol.password` (booleano) | — | nulo | B | 2 |

## H. Verificações só possíveis na sala ativa (ENV-*, relatório de API §5)

Todas são **Planejadas**; nenhuma existe no plugin. As marcadas com ★ são cobertas, no plugin, por regras já existentes
porque o plugin lê a sala real (a distinção "API externa × backup" deixou de existir).

| ID | Verificação | Cobertura atual no plugin | Prio. |
|---|---|---|---|
| ENV-01 | Restauração completa: todo módulo da origem existe no destino | ★ parcialmente pelo comparador (`missing_in_target`) | 2 (spec 003) |
| ENV-02 | Plugin de atividade ausente no destino | ★ X-INT-03 | 1 |
| ENV-03 | Arquivo quebrado no servidor | ★ X-INT-02 / X-CONT-01 / X-CONT-04 | 1 |
| ENV-04 | Diferença entre visão administrativa e do estudante | ★ `--userid` (X-STR-01, X-CERT-01) | 1 (expor na página: spec 001 decide) |
| ENV-05 | Condição de plugin ausente ignorada silenciosamente (`days`) | ★ X-INT-03 (condições de acesso) | 1 |
| ENV-06 | Conclusão efetiva por critério (usuário de teste agiu) | não coberto (exige ação humana) | 3 |
| ENV-07 | Blocos de site exigidos presentes | ★ CL-21 (herdados) | 1 |
| ENV-08 | Configurações globais (`enablecompletion`, `enableavailability`) | coletadas em `site` e não avaliadas por regra | 2 |
| ENV-09 | Permissões insuficientes da conta | não se aplica (roda dentro do Moodle) | — |
| ENV-10 | Emissão do certificado | não coberto (humano) | 3 |
| ENV-11 | H5P/jogos não renderizam | não coberto (humano); indiretamente CL-18 | 3 |
| ENV-12 | Curso visível e inscrição ativa na data de lançamento | ★ CL-08; `course.visible` coletado sem regra | 2 |
| ENV-13 | Datas das atividades coerentes com a nova oferta | ★ CL-14 (alerta ≠ fim) / X-AV-05 | 2 |
| ENV-14 | Usuário de teste chega ao certificado | X-CERT-01 (estático) + humano | 3 |
| ENV-15 | Seções com muitos itens / nomes genéricos | ★ CL-20 / X-STR-03 | 1 / 3 |

---

## I. Síntese quantitativa

| Conjunto | Total | Implementado e testado | Implementado com testes insuficientes | Parcial / embutido | Planejado | Fora do plugin |
|---|---|---|---|---|---|---|
| Checklist (CL) | 29 | 2 (CL-12 parcial, CL-13 parcial) | 16 | 0 | 4 (CL-01, CL-03, CL-24, CL-25) | 7 (CL-02, CL-15, CL-26, CL-27, CL-28, CL-29, CL-10 rede) |
| Adicionais (X-*) | 36 | 2 (X-DEP-02, X-DEP-04) | 9 | 3 (X-AV-04, X-STR-02, X-AV-05/X-SEC-01 parciais) | 17 | 5 (X-INT-01/04/05/06; X-CERT-02 coberto) |
| Sala ativa (ENV) | 15 | — | — | 9 cobertas por regras existentes | 4 | 2 não automatizáveis |

**Regras que merecem maior atenção** (impacto × fragilidade):

1. **CL-23, X-CERT-01, X-DEP-02, X-DEP-04, CL-18** — bloqueantes: decidem se o estudante consegue o certificado; têm teste só em 2 casos.
2. **CL-12 (agregação/excluir vazias), CL-16, CL-11** — altas, mudam o significado da nota mínima; sem teste automatizado da parte crítica.
3. **CL-09, CL-11, CL-16, CL-22, CL-23 por nome canônico** — dependem da decisão 10; maior fonte de falsos positivos em cursos antigos.
4. **CL-21 (blocos herdados)** — nunca validada com bloco de site real.
5. **X-INT-03** — nunca validada com plugin ausente (cenário mais perigoso: condição `days` ignorada em silêncio).
6. **CL-13 enunciado, X-AV-01** — regex sobre texto; produzem `NAO_CONFORME` com base em heurística.

## J. Prioridade sugerida por entrega

- **Spec 001 (MVP)**: consolidar as 29 implementadas com teste por regra (C e NC), acrescentar os caminhos NC de
  CL-04/05, X-DEP-01/03, X-INT-03; nenhuma regra nova além das necessárias ao diagnóstico atual.
- **Incremento seguinte (regras, spec própria)**: CL-24 e CL-25 (assim que houver insumo), CL-03, X-CONT-03,
  X-CONT-06, X-CONT-08, X-AV-02/03/05, X-DEP-05/06, X-CERT-03, X-BLK-01, X-ACC-01, ENV-08/12/13.
- **Posterior**: CL-01, CL-10/X-CONT-07 (rede), X-CONT-02/05, X-AV-06, X-DEP-07, X-CERT-05, X-STR-03, X-ACC-02/03/04.
