---

description: "Task list for 001 — Auditoria Técnica Operacional de Cursos MOOC"
---

# Tasks: Auditoria Técnica Operacional de Cursos MOOC

**Input**: Design documents from `specs/001-auditoria-tecnica-operacional/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUÍDOS — a especificação exige teste por regra (FR-026), teste de não modificação e de permissão
(FR-027); a constituição (VII) exige testes antes da entrega. Testes de caracterização precedem a refatoração.

**Organization**: fases de preparação e fundação (refatoração protegida), depois uma fase por user story (P1 → P3),
depois cobertura por regra e acabamento. Caminhos relativos a `plugin/local_mooc_audit/` salvo indicação.

**Status desta lista**: fases 1 e 2 autorizadas em 2026-10-28 e concluídas (T001–T047b); fase 3 (US1) autorizada em
2026-10-29 e concluída (T048–T055, verificada no navegador contra os cursos A/B gerados); fase 4 (US2) autorizada e
concluída em 2026-10-29 (T056–T059: `locator_test`, locus por finding incl. novo locus `import` →
`/backup/import.php`, local como link com título, botão "Executar novamente"); fase 5 (US3) autorizada e concluída em
2026-10-29 (T060–T064: setting de política validado, `policy_test`, blocos de transparência testados, `xint03_test`,
isolamento de exceção com regra injetada); fase 6 (US4) autorizada e concluída em 2026-10-29 (T065–T066:
`contract_test` valida A/B contra o schema 1.0 — agora com o locus `import` e distribuído em
`plugin/local_mooc_audit/schema/` —, codificação única `engine::to_json()` na página e no CLI); fase 7 (US5) autorizada
e concluída em 2026-10-29 (T067–T068: lista de chaves com tipo e padrão e JSON de referência gerados de
`policy::defaults()` no setting; teste de nome canônico alterado → CL-09 pelo novo nome e versão `+admin`); fases 8–9
aguardam autorização após validação (ver `docs/relatorio-adocao-sdd.md`).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Story]**: user story da spec (US1…US5); ausente nas fases de setup, fundação e acabamento

## Path Conventions

Projeto único (plugin Moodle): código em `plugin/local_mooc_audit/`, testes em `plugin/local_mooc_audit/tests/`,
execução no contêiner `moocaudit-webserver-1` (`/var/www/html/local/mooc_audit`).

---

## Phase 1: Setup (caracterização antes de mover código)

**Purpose**: congelar o comportamento atual das 29 regras e preparar a infraestrutura de testes.

- [x] T001 Extrair a construção dos cursos A/B de `cli/make_fixtures.php` para o gerador do componente `tests/generator/lib.php` (`local_mooc_audit_generator extends component_generator_base`, métodos `create_course_a(int $year)` e `create_course_b(int $year)`, usando `create_course`, `add_moduleinfo`, `block_manager`, `enrol_self`), pulando jogos/certificado quando o módulo não existe
- [x] T002 Fazer `cli/make_fixtures.php` incluir `tests/generator/lib.php` e chamar o gerador (um único código de fixture) mantendo a recusa fora de `localhost`/`debugdeveloper`
- [x] T003 Gerar `tests/fixtures/expected_A.json` e `tests/fixtures/expected_B.json` (mapa `rule → lista ordenada de [status, gravidade]`) executando `engine::audit()` com a versão **atual** de `classes/rules.php` sobre os cursos criados pelo gerador
- [x] T004 Criar `tests/characterization_test.php` que constrói A e B em PHPUnit, audita e compara com `expected_*.json`; deve passar com `rules.php` antes de qualquer migração
- [x] T005 [P] Criar `classes/local/status.php` com as constantes dos 6 estados, 5 gravidades e ordens de exibição (copiadas de `rules.php`/`engine.php`), sem alterar ainda os usos
- [x] T006 [P] Criar `classes/local/category.php` com as 8 categorias (`offer_enrol`, `institutional_resources`, `assessment_grades`, `completion_restrictions`, `certification`, `content_files`, `structure_blocks`, `plugins_environment`) e método `all()`
- [x] T007 [P] Acrescentar em `lang/en/local_mooc_audit.php` e `lang/pt_br/local_mooc_audit.php` as strings de categorias (`category_<id>`), estados (`status_<id>`), gravidades (`severity_<id>`), situação geral (`overall_<id>`) e seções da página (`section_nonconformities`, `section_warnings`, `section_unverifiable`, `section_errors`, `section_conformant`, `section_notices`, `rerun`, `jsonlink`, `auditmenu`, `pagetitle`)
- [x] T008 [P] Incrementar `version.php` para `2026102900`, release `0.2.0`, manter `MATURITY_ALPHA`

**Checkpoint**: `characterization_test` verde com o código atual; suíte existente verde.

---

## Phase 2: Foundational (uma classe por regra, catálogo, metadados; comportamento preservado)

**Purpose**: reorganização protegida pela caracterização; bloqueia as user stories porque o renderer depende de
`category`/`locus`/`link` nos resultados.

- [x] T009 Criar `classes/local/rule/rule_interface.php` (`id(): string`, `meta(): array`, `evaluate(array $snapshot, array $policy): array`) conforme `data-model.md` (campos de `meta()`: id, title, category, origin, type, severity, policy_keys, locus, guidance, source)
- [x] T010 Criar `classes/local/rule/base.php` (abstrata) com o construtor de finding `f()` (status, local, evidencia, extra: encontrado, esperado, orientacao, gravidade, cmid, locus, section) e `meta()` padrão preenchido a partir de constantes da subclasse
- [x] T011 Criar `classes/local/helpers.php` movendo de `rules.php`: `norm()`, `text()`, `label()`, `mods()`, `graded()`, `certs()`, `d()`
- [x] T012 [P] Criar `classes/local/rule/cl04.php` movendo `rules::cl04()`; meta: category `institutional_resources`, type `institucional`, severity Média, policy_keys `[summary_required_links]`, locus `course_settings`
- [x] T013 [P] Criar `classes/local/rule/cl05.php` (category `offer_enrol`, institucional, Baixa, `[target_category_name]`, locus `course_settings`)
- [x] T014 [P] Criar `classes/local/rule/cl06.php` (`offer_enrol`, institucional, Alta, `[reoffer_year, required_start_date, min_days_between_start_and_end]`, `course_settings`)
- [x] T015 [P] Criar `classes/local/rule/cl07.php` (`offer_enrol`, institucional, Alta, `[reoffer_year, allowed_end_dates]`, `course_settings`)
- [x] T016 [P] Criar `classes/local/rule/cl08.php` (`offer_enrol`, institucional, Alta, `[]`, locus `enrol`)
- [x] T017 [P] Criar `classes/local/rule/cl09.php` (`institutional_resources`, institucional, Média, `[required_forum_name]`, `module_edit`)
- [x] T018 [P] Criar `classes/local/rule/cl11.php` (`institutional_resources`, institucional, Alta, `[required_feedback_profile, required_certificate_module, licence_certificate_name]`, `module_edit`)
- [x] T019 [P] Criar `classes/local/rule/cl12.php` (`assessment_grades`, institucional, Alta, `[course_total_max, required_aggregation, aggregateonlygraded_allowed]`, locus `gradebook`)
- [x] T020 [P] Criar `classes/local/rule/cl13.php` (`assessment_grades`, institucional, Alta, `[min_quiz_attempts, attempts_rule]`, `module_edit`)
- [x] T021 [P] Criar `classes/local/rule/cl14.php` (`assessment_grades`, institucional, Média, `[]`, `module_edit`)
- [x] T022 [P] Criar `classes/local/rule/cl16.php` (`completion_restrictions`, institucional, Alta, `[required_feedback_evaluation]`, `module_edit`)
- [x] T023 [P] Criar `classes/local/rule/cl17.php` (`completion_restrictions`, institucional, Média, `[completion_expected]`, `module_edit`)
- [x] T024 [P] Criar `classes/local/rule/cl18.php` (`structure_blocks`, institucional/mista, Média, `[forbidden_modules]`, `module_edit`)
- [x] T025 [P] Criar `classes/local/rule/cl19.php` (`completion_restrictions`, institucional, Baixa, `[]`, `module_edit`)
- [x] T026 [P] Criar `classes/local/rule/cl20.php` (`structure_blocks`, institucional, Baixa, `[generic_section_names]`, locus `course_settings` para hiddensections e `section` para seções, com `section` preenchido)
- [x] T027 [P] Criar `classes/local/rule/cl21.php` (`structure_blocks`, institucional, Média, `[required_blocks]`, locus `blocks`)
- [x] T028 [P] Criar `classes/local/rule/cl22.php` (`institutional_resources`, institucional, Média, `[licence_label_text, licence_feedback_name, licence_certificate_name]`, `module_edit`)
- [x] T029 [P] Criar `classes/local/rule/cl23.php` (`certification`, institucional, Bloqueante, `[certificate_min_grade, required_feedback_evaluation, required_certificate_module]`, `module_edit`)
- [x] T030 [P] Criar `classes/local/rule/xint02.php` (`content_files`, tecnica, Alta, `[]`, `module_edit`)
- [x] T031 [P] Criar `classes/local/rule/xint03.php` (`plugins_environment`, tecnica, Alta, `[]`, locus `none`)
- [x] T032 [P] Criar `classes/local/rule/xcont01.php` (`content_files`, tecnica, Média, `[]`, `module_edit`)
- [x] T033 [P] Criar `classes/local/rule/xcont04.php` (`content_files`, tecnica, Média, `[]`, `module_edit`)
- [x] T034 [P] Criar `classes/local/rule/xav01.php` (`assessment_grades`, tecnica, Média, `[]`, `module_edit`)
- [x] T035 [P] Criar `classes/local/rule/xdep01.php` (`completion_restrictions`, tecnica, Alta, `[]`, `module_edit`; findings de seção com locus `section`)
- [x] T036 [P] Criar `classes/local/rule/xdep02.php` (`completion_restrictions`, tecnica, Bloqueante, `[]`, `module_edit`)
- [x] T037 [P] Criar `classes/local/rule/xdep03.php` (`completion_restrictions`, tecnica, Alta, `[]`, `module_edit`)
- [x] T038 [P] Criar `classes/local/rule/xdep04.php` (`completion_restrictions`, tecnica, Bloqueante, `[]`, locus `none`)
- [x] T039 [P] Criar `classes/local/rule/xcert01.php` (`certification`, tecnica, Bloqueante, `[required_certificate_module, licence_certificate_name]`, `module_edit`)
- [x] T040 [P] Criar `classes/local/rule/xstr01.php` (`structure_blocks`, tecnica, Baixa, `[]`, `module_edit`)
- [x] T041 Acrescentar em `lang/en` e `lang/pt_br` as strings `rule_<id>_title` e `rule_<id>_guidance` das 29 regras (títulos atuais de `rules::catalog()`; orientações padrão atuais), e trocar nos findings os textos fixos de orientação pelo uso de `get_string` (manter o texto em pt_br idêntico ao atual para a caracterização)
- [x] T042 Criar `classes/local/catalog.php` com a lista explícita ordenada das 29 classes (mesma ordem de `rules::catalog()`) e `instances(): rule_interface[]`
- [x] T043 Alterar `classes/engine.php`: `run_rules()` itera `catalog::instances()`, aplica `meta()` (titulo, origem, classe `A`, category, tipo, gravidade padrão, locus padrão, orientacao padrão quando ausente), mantém isolamento de exceção → ERRO; `summarize()` acrescenta `overall_status` (regra do `data-model.md`); `audit()` acrescenta `contract_version` `"1.0"`
- [x] T044 Remover `classes/rules.php` e `use` residuais; `engine_test` passa a referenciar `\local_mooc_audit\local\status`
- [x] T045 Rodar `characterization_test` e `engine_test`: estados por regra em A e B idênticos a `expected_*.json`; corrigir qualquer desvio antes de prosseguir (desvio intencional exige atualização documentada do JSON esperado e nota em `docs/catalogo-regras.md`)
- [x] T046 Alterar `classes/policy.php`: `load()` devolve `warnings[]` (`policyjson inválido: …`; chave conhecida com tipo inesperado → padrão mantido + aviso); remover `policy_error`; `engine::audit()` copia para `policy_warnings`
- [x] T047 Criar `classes/local/locator.php` (`url_for(array $result, int $courseid, array $snapshot_modules, array $snapshot_sections): ?moodle_url`) conforme research R5, resolvendo o id da seção a partir do número; `engine::audit()` preenche `link` (string ou null) em cada resultado
- [x] T047b Em `engine::run_rules()`, quando `$snapshot['site']['enableavailability']` ou `['enablecompletion']` for 0, substituir os resultados das regras das categorias `completion_restrictions` e `certification` por um NAO_VERIFICAVEL com motivo ("acompanhamento de conclusão/restrições de acesso desabilitado no site"); teste em `engine_test.php` com `set_config('enablecompletion', 0)`; a caracterização (T045) continua válida porque A/B rodam com ambos habilitados

**Checkpoint**: refatoração concluída; `characterization_test` verde; JSON com `contract_version`, `category`, `locus`, `link`, `overall_status`, `policy_warnings`.

---

## Phase 3: User Story 1 — Executar a auditoria a partir da sala e compreender o resultado (Priority: P1) 🎯 MVP

**Goal**: entrada "Auditoria técnica" na administração do curso; página no contexto do curso com situação geral,
inconformidades por gravidade e categoria, evidência completa, alertas, não verificáveis, erros e conformes.

**Independent Test**: quickstart §5.2 (curso B) e §4 (curso A); `access_test`.

- [x] T048 [US1] Criar `lib.php` com `local_mooc_audit_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context)` adicionando o nó `auditmenu` (TYPE_SETTING, ícone `i/report`) apenas se `has_capability('local/mooc_audit:audit', $context)`
- [x] T049 [P] [US1] Criar `classes/output/report.php` (`renderable`, `templatable`): `export_for_template()` converte o array do engine em dados do template — cabeçalho, inventário, situação geral, contagens, grupos por gravidade → categoria de NAO_CONFORME, lista ATENCAO, lista NV+NA com motivo, lista ERRO, lista compacta CONFORME, avisos (coleta + política), URLs de reexecução e JSON; strings via `get_string`
- [x] T050 [P] [US1] Criar `templates/report.mustache` com as seções do contrato `contracts/page.md` (h3/h4, tabelas com `<th>`, estado como texto + classe CSS, evidência sem truncamento, `{{#str}}` para rótulos) e `classes/output/renderer.php` com `render_report()`
- [x] T051 [US1] Reescrever `index.php`: `courseid|id` obrigatório (formulário de compatibilidade se ausente), `require_login($course)`, `require_capability` no contexto do curso, `set_context`/`set_pagelayout('incourse')`, título `pagetitle`, `format=json` mantido, renderização via renderer; remover todo texto fixo
- [x] T052 [US1] Revisar `db/access.php`: remover `riskbitmask` indevido (research R11), manter `captype read`, arquétipo `manager`; atualizar strings das capacidades em `lang/*`
- [x] T053 [US1] Criar `tests/access_test.php`: estudante e professor inscritos não têm `local/mooc_audit:audit` por padrão; gerente tem; `local_mooc_audit_extend_navigation_course()` não adiciona o nó sem a capacidade e adiciona com ela
- [x] T054 [US1] Atualizar `engine_test.php`: cada resultado tem `category` válida, `status` e `gravidade` válidos, `contract_version` presente, `overall_status` coerente (B → BLOQUEADA, A → SEM_INCONFORMIDADES), NAO_CONFORME tem local/evidencia/orientacao não vazios e ATENCAO tem local/evidencia não vazios
- [x] T055 [US1] `engine::render_text()` passa a incluir a situação geral e os avisos de política, com strings de idioma (saída de texto do CLI)

**Checkpoint**: US1 verificável no navegador (quickstart §5.2) e por `access_test`/`engine_test`.

---

## Phase 4: User Story 2 — Ir direto ao ponto de correção e reexecutar (Priority: P2)

**Goal**: cada inconformidade com destino navegável quando existir; botão "Executar novamente".

**Independent Test**: quickstart §5.3; `locator_test`.

- [x] T056 [P] [US2] Criar `tests/locator_test.php`: para cada `locus` (module_edit, module_view, course_settings, enrol, gradebook, blocks, section, none) a URL esperada; cmid inexistente → null; `section` sem número → null
- [x] T057 [US2] Revisar o `locus` por finding nas 29 classes de regra onde o padrão da regra não serve (ex.: CL-20 `course_settings` vs `section`; X-CONT-01/X-CONT-04 `module_view` para conteúdo vs `module_edit` para configuração; CL-12 `gradebook`; CL-08 `enrol`; CL-21 `blocks`; X-DEP-04 `none`) e confirmar na caracterização que estados não mudaram
- [x] T058 [US2] No template e em `output/report.php`, renderizar `local` como link quando `link` não for null (texto do link = local; `title` com o tipo de destino), e como texto quando null
- [x] T059 [US2] Acrescentar o botão "Executar novamente" (`single_button` GET para a mesma URL) e o link "JSON" no cabeçalho do template; nova execução mostra nova `executed_at`

**Checkpoint**: links e reexecução verificáveis em B (quickstart §5.3).

---

## Phase 5: User Story 3 — Resultados incompletos, falhas e acesso negado com transparência (Priority: P2)

**Goal**: ERRO e NV/NA em blocos próprios; política inválida visível; plugin ausente tratado; permissão negada.

**Independent Test**: `policy_test`, `engine_test` (isolamento), teste NC de X-INT-03, quickstart §5.4–5.5.

- [x] T060 [P] [US3] Criar `classes/admin/setting_policyjson.php` (extends `admin_setting_configtextarea`) com `validate()` que aceita vazio ou objeto JSON e devolve mensagem com `json_last_error_msg()`; usar em `settings.php`
- [x] T061 [P] [US3] Criar `tests/policy_test.php`: JSON inválido no setting → padrões aplicados + `policy_warnings` não vazio; chave desconhecida ignorada; chave com tipo errado → aviso + padrão; `reoffer_year` automático = ano + 1; `policy_version` com `+admin`
- [x] T062 [US3] Garantir no template que `policy_warnings` e `warnings` da coleta aparecem na seção Avisos e que o bloco "Não verificados ou não aplicáveis" (subtítulos "Não foi possível verificar" para NV e "Não se aplica a esta sala" para NA, com motivo) e o bloco "Erro na verificação" (ERRO, com mensagem) existem e não entram nas contagens de inconformidade (`summarize()` já separa por estado; `overall_status` ignora esses estados)
- [x] T063 [US3] Criar `tests/rule/xint03_test.php` com cenário NC: módulo cujo `availability` cita tipo de condição inexistente (`{"type":"naoexiste"}`) → NAO_CONFORME Alta para a condição; módulo padrão instalado → CONFORME Informativa; curso vazio → NAO_APLICAVEL
- [x] T064 [US3] Teste em `engine_test.php` de que uma regra que lança exceção (injetar via subclasse de `catalog` ou regra de teste registrada em `tests/fixtures/`) produz um único resultado ERRO para ela e as demais regras são avaliadas

**Checkpoint**: US3 coberta por testes; quickstart §5.4 e §5.5 manuais.

---

## Phase 6: User Story 4 — Relatório em formato estruturado (Priority: P3)

**Goal**: JSON idêntico entre página e CLI, com `contract_version` e campos novos; contrato validado.

**Independent Test**: quickstart §4; teste de schema.

- [x] T065 [P] [US4] Criar `tests/contract_test.php`: o JSON de `engine::audit()` para A e B contém todos os campos obrigatórios de `specs/001-auditoria-tecnica-operacional/contracts/report-v1.0.schema.json` (validação manual dos `required` e enums, sem biblioteca externa), e todos os campos da PoC (`engine`, `policy_version`, `reoffer_year`, `executed_at`, `course`, `viewer_userid`, `timing`, `inventory`, `summary`, `results`, `warnings`) continuam presentes
- [x] T066 [US4] Confirmar em `index.php` (`format=json`) e `cli/audit.php` (`--json`) que ambos serializam o mesmo array com `JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT`; atualizar `cli/audit.php --help` para citar o contrato 1.0

---

## Phase 7: User Story 5 — Ajustar a política institucional sem alterar código (Priority: P3)

**Goal**: administrador vê os parâmetros aceitos, erro ao salvar JSON inválido e versão aplicada no relatório.

**Independent Test**: quickstart §5.5; `policy_test`.

- [x] T067 [US5] Atualizar `settings.php`: descrição do setting lista cada chave de `policy::defaults()` com tipo e exemplo (string `policyjson_desc` gerada a partir de `defaults()` para não divergir), e exibe o JSON padrão atual como referência (somente leitura)
- [x] T068 [US5] Acrescentar em `policy_test.php` o cenário "nome canônico do fórum alterado na política → CL-09 avalia pelo novo nome" (usa o gerador: curso A com fórum renomeado)

---

## Phase 8: Cobertura por regra (FR-026) — pode correr em paralelo às fases 3–7 após a fase 2

**Purpose**: um arquivo de teste por regra com cenário conforme e não conforme (e NA/NV/ATENCAO quando a regra os
produz), construídos por API; mínimos descritos por regra.

- [ ] T069 [P] `tests/rule/cl04_test.php`: sumário com os dois links → C; sem um deles → NC
- [ ] T070 [P] `tests/rule/cl05_test.php`: categoria "Inscrições Abertas" → C; outra → NC
- [ ] T071 [P] `tests/rule/cl06_test.php`: 01/01/ano → C; outro ano → NC; 0 → NC; início < 30 dias do fim → NC
- [ ] T072 [P] `tests/rule/cl07_test.php`: 31/12/ano → C; 30/06 → NC; 0 → NC
- [ ] T073 [P] `tests/rule/cl08_test.php`: self habilitado, datas = curso, customint6=1 → C; sem instância → NC; desabilitado/sem datas → NC; guest ativo → ATENCAO Informativa
- [ ] T074 [P] `tests/rule/cl09_test.php`: fórum canônico sem conclusão → C; com conclusão → NC; ausente → NC; dois fóruns homônimos → avalia o primeiro, sem duplicata
- [ ] T075 [P] `tests/rule/cl11_test.php`: pesquisa com completionsubmit e exigida pelo certificado → C; sem completionsubmit → NC; não exigida → NC; ausente → NC (certificado pulado se `mod_simplecertificate` ausente)
- [ ] T076 [P] `tests/rule/cl12_test.php`: soma 100, Natural, aggregateonlygraded=0 → C; soma 90 → NC; aggregateonlygraded=1 → NC; agregação ≠ 13 → NC; item em subcategoria → ATENCAO
- [ ] T077 [P] `tests/rule/cl13_test.php`: 2 e 0 tentativas → C; 1 → NC; enunciado "Tentativas permitidas: 2" com 1 → NC extra; enunciado sem menção → ATENCAO Baixa; sem quiz → NA
- [ ] T078 [P] `tests/rule/cl14_test.php`: quiz com timeclose = fim → C; sem timeclose → NC; ≠ fim → ATENCAO; assign com cutoffdate → C; sem atividades com nota → NA
- [ ] T079 [P] `tests/rule/cl16_test.php`: Avaliação exige todos os cmids com nota → C; falta um → NC; operador `|` → ATENCAO; ausente → NC
- [ ] T080 [P] `tests/rule/cl17_test.php`: página com conclusão por visualização → C (síntese); conclusão manual → NC; automática sem critério → ATENCAO; critério inesperado para o tipo → ATENCAO Baixa
- [ ] T081 [P] `tests/rule/cl18_test.php`: sem glossário → C; glossário sem jogo → NC; glossário vazio fonte de jogo → NC Bloqueante; com entradas → ATENCAO (pular jogo se `mod_game` ausente; ainda assim cobrir "sem jogo")
- [ ] T082 [P] `tests/rule/cl19_test.php`: rótulo sem conclusão → C; com conclusão → NC
- [ ] T083 [P] `tests/rule/cl20_test.php`: hiddensections=1 → C; 0 → NC; seção vazia → ATENCAO; nome "Topic 3" → ATENCAO; seção oculta com atividade com conclusão → ATENCAO Média
- [ ] T084 [P] `tests/rule/cl21_test.php`: progresso + 3 HTML → C; sem progresso → NC; sem "Acessibilidade" → NC (pular progresso se `block_completion_progress` ausente)
- [ ] T085 [P] `tests/rule/cl22_test.php`: rótulo + pesquisa + declaração → C ×3; cada ausência → NC
- [ ] T086 [P] `tests/rule/cl23_test.php`: certificado exige Avaliação e nota ≥ 60 no total → C; sem nota → NC; nota em item errado → NC; operador `|` → NC; sem certificado → NC
- [ ] T087 [P] `tests/rule/xint02_test.php`: arquivo presente → C; registro com conteúdo removido do `filedir` → NC
- [ ] T088 [P] `tests/rule/xcont01_test.php`: página com texto → C; página vazia → NC; quiz sem questões → NC Alta; feedback sem itens → NC; resource sem arquivo → NC Alta; livro sem capítulos → NC
- [ ] T089 [P] `tests/rule/xcont04_test.php`: `@@PLUGINFILE@@` com arquivo → C; sem arquivo → NC; com `?time=` e arquivo existente → C
- [ ] T090 [P] `tests/rule/xav01_test.php`: "Valor: 50 pontos" com grademax 50 → C; 40 → NC
- [ ] T091 [P] `tests/rule/xdep01_test.php`: condições válidas → C; cm inexistente em módulo → NC; em seção → NC
- [ ] T092 [P] `tests/rule/xdep02_test.php`: mover de `engine_test` o cenário existente e acrescentar `e=2` sobre módulo sem nota → NC; módulo oculto → ATENCAO Alta
- [ ] T093 [P] `tests/rule/xdep03_test.php`: condição de nota para item existente → C; inexistente → NC
- [ ] T094 [P] `tests/rule/xdep04_test.php`: mover de `engine_test` o cenário existente (sem ciclo → C; ciclo → NC)
- [ ] T095 [P] `tests/rule/xcert01_test.php`: cadeia satisfazível → C; elo sem conclusão → NC; elo oculto → NC; certificado oculto → NC; sem certificado → NC; com `userid` de estudante sem acesso a um elo → NC
- [ ] T096 [P] `tests/rule/xstr01_test.php`: tudo visível e nomeado → C; módulo oculto → ATENCAO; nome vazio → NC

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T097 Criar `tests/readonly_test.php`: hashes de `course`, `course_sections`, `course_modules`, `grade_items`, `grade_categories`, `enrol`, `block_instances`, `files`, `quiz`, `feedback`, `glossary` (filtrados pelo curso) e `course.timemodified`/`cacherev` antes e depois de `engine::audit()` em B → idênticos
- [ ] T098 [P] Criar `tests/behat/audit_page.feature` (@local_mooc_audit): gerente vê "Auditoria técnica" no menu do curso e abre o relatório com a situação geral; estudante não vê a entrada; passos verificam que as seções têm cabeçalhos e que os locais com link têm texto significativo (FR-023)
- [ ] T099 [P] Rodar `php -l` em todos os `.php` e PHPCS padrão `moodle` no contêiner; corrigir avisos
- [ ] T100 Rodar a suíte completa `vendor/bin/phpunit local/mooc_audit/tests` e registrar a saída em `plugin-results/phpunit-0.2.0.txt`
- [ ] T101 [P] Executar o CLI nos cursos A, B, 2 (55) e 3 (670) e gravar `plugin-results/0.2.0/*.{txt,json}`; comparar estados por regra com `plugin-results/` da PoC (SC-007) e registrar diferenças; medir `timing.total_s` em um curso sintético com 150 módulos criado pelo gerador (SC-002 < 5 s) e a proporção de NAO_CONFORME/ATENCAO com `link` não nulo nos quatro cursos (SC-003 ≥ 90 %)
- [ ] T102 [P] Atualizar `plugin/local_mooc_audit/README.md`: estrutura nova (`classes/local/rule/`), página na navegação do curso, contrato 1.0, limitações; remover "prova de conceito" do nome do plugin em `lang/*` (`pluginname` = "Auditoria MOOC")
- [ ] T103 [P] Atualizar `docs/catalogo-regras.md` com status "Implementado e testado" e coluna de testes por regra; registrar correções de comportamento (se houver) e o id X-AV-04 embutido em CL-12
- [ ] T104 Executar `specs/001-auditoria-tecnica-operacional/quickstart.md` ponta a ponta e anotar o resultado no fim do arquivo (data, versão, desvios)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (1)**: T001 → T002, T003 → T004; T005–T008 independentes.
- **Foundational (2)**: T009 → T010 → (T011) → T012–T040 em paralelo → T041 → T042 → T043 → T044 → T045 (portão) → T046, T047, T047b. Bloqueia todas as user stories.
- **US1 (3)**: depois da fase 2; T049/T050 em paralelo; T051 depende de T049/T050; T053/T054 após T051/T043.
- **US2 (4)**: depende de T047 (locator) e T050 (template); T056 pode começar após T047.
- **US3 (5)**: depende de T046 (warnings) e T050; T060/T061 em paralelo.
- **US4 (6)**: depende de T043; T065 em paralelo com fases 3–5.
- **US5 (7)**: depende de T060/T061.
- **Cobertura por regra (8)**: depende de T045; todos [P]; pode correr em paralelo às fases 3–7.
- **Polish (9)**: T097 após fase 2; T100/T104 por último.

### Portões (constituição)

- Portão de código: T045 (caracterização), T097 (não modificação), T053 (acesso), T099 (lint/PHPCS), T100 (suíte).
- Portão de entrega: T008 (versão), T102 (README), T103 (catálogo), T101 (saídas de referência).

### Parallel Opportunities

- Fase 2: 29 classes de regra (T012–T040) são arquivos independentes.
- Fase 8: 28 arquivos de teste (T069–T096) independentes entre si e das fases 3–7.
- Fase 3: T049 e T050; Fase 5: T060 e T061; Fase 9: T098, T099, T101, T102, T103.

---

## Implementation Strategy

### MVP First

1. Fases 1 e 2 completas (refatoração protegida; nada visível muda ainda).
2. Fase 3 (US1): primeiro valor visível — entrada no curso e relatório legível. **Parar e validar com a equipe.**
3. Fases 4 e 5 (US2, US3): links, reexecução, transparência.
4. Fases 6 e 7 (US4, US5): contrato e política.
5. Fase 8 ao longo de tudo (em paralelo), fase 9 por último.

### Estimativa de esforço (ordem de grandeza, para avaliação)

| Fase | Tarefas | Esforço estimado |
|---|---|---|
| 1 Setup | 8 | 1–2 dias |
| 2 Fundação | 40 | 3–4 dias (mecânico, protegido pela caracterização) |
| 3 US1 | 8 | 2–3 dias |
| 4 US2 | 4 | 1 dia |
| 5 US3 | 5 | 1 dia |
| 6 US4 | 2 | 0,5 dia |
| 7 US5 | 2 | 0,5 dia |
| 8 Testes por regra | 28 | 4–5 dias |
| 9 Acabamento | 8 | 1–2 dias |
| **Total** | **105** | **≈ 14–19 dias de uma pessoa** |

## Notes

- Nenhuma tarefa cria tabela, tarefa agendada, Web Service ou escrita em curso.
- Toda tarefa de regra preserva id, estados e evidências; mudança de comportamento exige atualização documentada de `expected_*.json` e do catálogo.
- `comparator.php`, `cli/compare.php` e `cli/make_import.php` não são tocados (escopo da spec 003).
