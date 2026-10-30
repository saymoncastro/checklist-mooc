# Tasks: Experiência do relatório individual e da correção assistida (Spec 006)

**Input**: `specs/006-experiencia-relatorio/` — spec.md, plan.md, research.md, data-model.md, contracts/presentation.md,
quickstart.md.

**Escopo**: fase 1 (textos e links) detalhada; fases 2 e 3 como blocos a detalhar depois da entrega da fase 1. Testes
incluídos (constituição, princípio VII). Caminhos relativos a `plugin/local_mooc_audit/`.

**Regra transversal**: nenhuma tarefa altera `classes/local/rule/*`, `classes/engine.php` nem `classes/snapshot.php`
(FR‑025, SC‑015). Se uma tarefa precisar disso, ela pertence à fase 3 ou 4.

## Phase 1: Setup

- [x] T001 Apontar o Spec Kit para a 006 (`.specify/feature.json`) e registrar na spec a decisão de implementar em paralelo à linha de base (FR‑029, fase 0) em `specs/006-experiencia-relatorio/spec.md`
- [x] T002 Subir um laboratório 4.5 com os plugins de referência montando este ramo (não o `moocaudit-f0-web`) conforme `moodle-env/README.md`, com PHPUnit inicializado

## Phase 2: Foundational

- [x] T003 Criar `classes/local/presentation/catalog.php`: mapa `regra → check → variante → {who, actions[], needs_plugin?}` para as 29 regras (research R3, contracts/presentation.md), função `variant(array $result): ?string` (estado, gravidade, `locus`, `subject`), `parse_occurrence(string): array` (`rule`, `entity`, `check`, `subject`, sem `#n`), lista de plugins exigidos e lista fechada de precedências (R7)
- [x] T004 Criar `classes/local/presentation/finding.php`: recebe o resultado gravado, o id do curso, o `course_modinfo` e o contexto de quem vê; devolve a ocorrência apresentável (data-model.md) — textos com parâmetros, nomes atuais de `cmid` e `subject`, "removida depois desta auditoria" quando a atividade não existe, ações filtradas por existência e capacidade, `who`, `verify`, `before`, `related`, `fallback`
- [x] T005 Teste `tests/presentation_catalog_test.php`: para Cursos A e B, a sala com alertas de `validacao/preparar_sessao.php` e os cenários de `tests/local/rule/`, toda ocorrência não conforme, de atenção ou não verificável tem `variant` e strings `problem`, `why`, `fix` em `en` e `pt_br`; nenhuma string `msg_*` contém `cmid=`, `completion=`, `status=`, `{"`, `@@PLUGINFILE@@`

## Phase 3: User Story 2 — Compreender cada problema sem conhecer o banco de dados (P1)

**Goal**: cada linha diz o problema, por que importa e o que fazer, em linguagem do Moodle e no idioma de quem vê.

**Independent Test**: relatório do Curso B: nenhuma linha principal com nomes de coluna, valores codificados, JSON ou id
interno como única referência; detalhes técnicos com a evidência gravada.

- [x] T006 [P] [US2] Escrever as strings `msg_*` (problem, why, fix e verify quando específico) das regras de oferta, recursos institucionais e livro de notas — CL‑04, CL‑05, CL‑06, CL‑07, CL‑08, CL‑09, CL‑11, CL‑12, CL‑22 — em `lang/en/local_mooc_audit.php` e `lang/pt_br/local_mooc_audit.php`, mantendo a ordem alfabética
- [x] T007 [P] [US2] Escrever as strings `msg_*` das regras de avaliação, conclusão e estrutura — CL‑13, CL‑14, CL‑16, CL‑17, CL‑18, CL‑19, CL‑20, CL‑21, CL‑23 — nas duas línguas
- [x] T008 [P] [US2] Escrever as strings `msg_*` das regras técnicas — X‑AV‑01, X‑CERT‑01, X‑CONT‑01, X‑CONT‑04, X‑DEP‑01, X‑DEP‑02, X‑DEP‑03, X‑DEP‑04, X‑INT‑02, X‑INT‑03, X‑STR‑01 — nas duas línguas
- [x] T009 [US2] Passar rótulos de campo do núcleo como parâmetros (research R9) em `classes/local/presentation/finding.php`
- [x] T010 [US2] Usar `presentation\finding` em `classes/output/report.php::item()` e acrescentar ao contexto: `problem`, `why`, `fix`, `who`, `verify`, `actions[]`, `before`, `related`, `fallback`, `technical` (regra, título, `check`, evidência, encontrado, esperado, orientação gravada, chave)
- [x] T011 [US2] Atualizar `templates/report_group.mustache`: coluna "Problema" (frase + código da regra discreto), "Local" (nome atual), "O que fazer" (por que importa, como corrigir, quem, como confirmar, ações, notas) e detalhes técnicos em `<details>` nativo; com `fallback`, a linha atual
- [x] T012 [US2] Atualizar `templates/report.mustache` (tabelas de não verificáveis) para usar `problem`/`fix` quando houver mensagem

## Phase 4: User Story 7 — Corrigir na tela certa, na ordem certa, e confirmar (P1)

**Goal**: ações que abrem a seção certa, alternativas com a decisão explícita, precedências e confirmação.

**Independent Test**: no Curso B, o X‑DEP‑02 oferece "Ativar a conclusão em Aprofundamento" e "Restringir acesso do
Questionário 1" com `showonly`; o CL‑13 da mesma atividade traz a nota de precedência; X‑DEP‑04 mostra os nomes e uma
ação por atividade.

- [x] T013 [US7] Implementar as ações (contracts/presentation.md) em `classes/local/presentation/finding.php`, com `moodle_url`, ids inteiros, `returnurl` do relatório onde a rota aceita, e sem `sesskey`
- [x] T014 [US7] Implementar as precedências R7 (restrição inválida na atividade; datas antes de inscrição/encerramento; soma 100 antes da nota mínima) e a relação R8 (CL‑17 com CL‑09/CL‑19 ou rótulo) em `classes/local/presentation/finding.php`, consultando as demais ocorrências do mesmo relatório
- [x] T015 [P] [US7] Teste `tests/presentation_finding_test.php`: X‑DEP‑02 com nome do alvo e duas ações com `showonly`; X‑DEP‑04 com nomes; atividade removida sem link; CL‑13 com nota de precedência; CL‑17 de rótulo com a variante "desativar"; `returnurl` presente nas rotas que o aceitam
- [x] T016 [US7] Teste Behat `tests/behat/report_fix.feature`: professor editor abre o relatório do Curso B, segue "Ativar a conclusão" do X‑DEP‑02, salva, executa novamente e não vê mais o problema (`@javascript @accessibility`)

## Phase 5: User Story 8 — Saber quando a correção é de outra pessoa (P1)

**Goal**: responsável explícito; nenhuma ação que quem vê não pode usar; aviso de plugin exigido ausente.

**Independent Test**: usuário só com `local/mooc_audit:audit` vê textos e responsável, sem ações; site sem
`mod_simplecertificate` mostra o aviso no topo e a variante "administrador" no CL‑23, X‑CERT‑01 e CL‑11.

- [x] T017 [US8] Implementar `who` com capacidade de quem vê e a regra da categoria (R5) em `classes/local/presentation/finding.php`
- [x] T018 [US8] Implementar a detecção de plugins exigidos (R6) e a variante `noplugin` em `classes/local/presentation/catalog.php` e `finding.php`; aviso no topo em `classes/output/report.php` e `templates/report.mustache`
- [x] T019 [P] [US8] Casos em `tests/presentation_finding_test.php`: usuário sem capacidade de edição (sem ações, responsável presente); plugin ausente simulado (lista de instalados injetável) troca a variante e gera o aviso; CL‑05 indica a equipe quando o seletor só tem a categoria atual

## Phase 6: Polish (fase 1)

- [x] T020 Ajustar `tests/output_test.php` e os Behat existentes que conferem textos da tabela antiga, sem mudar o que eles verificam sobre estados e links
- [x] T021 Rodar a suíte PHPUnit inteira (caracterização sem regravar), os Behat de `@local_mooc_audit`, o PHPCS `moodle` e o axe no relatório do Curso B; corrigir o que aparecer
- [x] T022 Conferir no 5.3 (site com o plugin deste ramo) que as rotas `showonly` e os rótulos do núcleo funcionam
- [x] T023 Versão 0.7.0 em `version.php` e `engine::VERSION`, `CHANGES.md` e nova captura do relatório no `README.md` da raiz e em `docs/guia-do-usuario.md`
- [x] T024 Registrar resultados e evidências da fase 1 em `specs/006-experiencia-relatorio/results.md`

## Fase 2 do plano — Estrutura do relatório

- [x] T025 Detalhar as tarefas de US1 (topo e "Comece por aqui"), US3 (visões e causas agrupadas, FR‑038), US5 (celular) e FR‑040/FR‑041 a partir dos resultados da fase 1 (T027–T040)

**Decisões de detalhe:** a visão escolhida fica num cookie do navegador (`local_mooc_audit_view`, FR‑014/FR‑028; sem
JavaScript); visão inicial "por prioridade" (**[validar]** pela linha de base); causas pela lista fechada de relações
R12 (abaixo), contadas sempre que houver problemas, porque ocorrências sem relação demonstrada contam cada uma como sua
própria causa (definição de causa da spec); impacto por regra (FR‑041) sem mudar gravidades.

**Relações R12 (mesma causa):** (a) CL‑13 `attempts` e `intro_attempts` na mesma atividade (a mesma correção
simulada resolve as duas); (b) conclusão da mesma atividade: CL‑09 `forum_completion`, CL‑17 `completion`, CL‑19
`completion`; (c) `certificate_presence` no CL‑11, CL‑23 e X‑CERT‑01; (d) atividade oculta: X‑DEP‑02
`target_completion` em atenção com `subject cm:X` e X‑STR‑01 `visibility` da atividade X.

### US1 — Situação e primeira ação no topo (P1)

- [x] T027 [US1] `classes/local/presentation/summary.php`: contagens por natureza (problemas, bloqueantes, pontos para conferir), atividades e configurações envolvidas, causas (R12), correções assistidas disponíveis e "Comece por aqui" (até 3 problemas: gravidade, depois os sem nota de precedência)
- [x] T028 [US1] Topo do relatório em `templates/report.mustache`: situação, frase de contagem (FR‑002), "Comece por aqui" com link para a linha e para a primeira ação; mensagem própria para sala sem problemas, sem "aprovada" (FR‑005); avisos de desatualizado/incompleto continuam acima (FR‑004)
- [x] T029 [US1] Metadados (motor, contrato, política, ano, tempo), inventário e contagens por estado num `<details>` "Detalhes técnicos da auditoria" no fim (FR‑003)

### US3 — Corrigir por atividade (P2)

- [x] T030 [US3] `classes/local/presentation/causes.php`: chave de causa por ocorrência (R12), principal e "também resolve"; usada pelo resumo e pelas duas visões
- [x] T031 [US3] Visão "por atividade" em `classes/output/report.php` e `templates/report_activity.mustache`: cada atividade uma vez (ordem do curso), gravidade mais alta, link de configuração, problemas e alertas; seções; "Configurações da sala" para o resto (FR‑013); mesmas ocorrências e estados da visão por prioridade (FR‑015)
- [x] T032 [US3] Linha comum às duas visões em `templates/report_item.mustache` (parcial), com âncora por ocorrência e a nota "mesma causa de…"/"também resolve…"
- [x] T033 [US3] Troca de visão por link (`view=priority|activity`) em `index.php` e `history.php`, lembrada no cookie; valor do cookie validado (FR‑012, FR‑014)

### FR‑040 / FR‑041 — Verificações manuais e impacto

- [x] T034 Seção "Verificações manuais" em `templates/report.mustache`: alcançabilidade do certificado sem impedimento estático (X‑CERT‑01 `chain` conforme) com o procedimento de teste; itens não verificáveis; selo "conferência de texto"/"busca por nome" nas verificações heurísticas (CL‑13 `intro_attempts`, X‑AV‑01, CL‑20 `generic_name`; CL‑09, CL‑11, CL‑16, CL‑22 de presença)
- [x] T035 Frase de impacto ao lado da gravidade (certificação, acesso de estudantes, oferta e inscrição, política institucional, conferir) em `classes/local/presentation/catalog.php` e no template

### US5 — Celular e acessibilidade (P3)

- [x] T036 [US5] `styles.css`: a menos de 768 px, linhas viram blocos com rótulo e valor (`data-label`); sem rolagem horizontal a 320 px (FR‑021)
- [x] T037 [US5] Hierarquia de títulos sem saltos (h2 → h3 → h4) no relatório e no resumo da execução (FR‑022); estados também em texto

### Testes e polimento da fase 2

- [x] T038 [P] Testes: `tests/presentation_summary_test.php` (contagens e causas do Curso B; sala sem problemas; "Comece por aqui"); visão por atividade com o Questionário 1 uma vez e as mesmas ocorrências da visão por prioridade (`tests/output_test.php`); Behat: troca de visão lembrada, títulos e axe
- [x] T039 Ajustar os Behat que conferem `h4#…`; rodar PHPUnit, Behat, PHPCS; conferir 320 px, 375 px e 1366 px no navegador e no 5.3
- [x] T040 Versão 0.8.0, `CHANGES.md`, guia do usuário, captura, `results.md`

## Fase 3 do plano — Contrato 1.1 e correção em linguagem do Moodle

- [x] T026 Detalhar as tarefas de FR‑026/FR‑043, US4 e a exposição na API (T041–T050)

**Decisão de detalhe:** `params`, `subjects` e `evidence_level` são calculados pelo motor a partir do retrato e da
política (`classes/local/details.php`), **sem alterar nenhuma regra**: a impressão digital do código das regras
(`fingerprint::code`, que cobre as regras e o código compartilhado, não o `engine.php`) não muda, e nenhuma execução
anterior passa a aparecer como desatualizada. Lista fechada de verificações com valores: CL‑06, CL‑07, CL‑08
`self_enrol`, CL‑12 `total_sum`, CL‑13 `attempts`, CL‑14 `close_date`, CL‑23 `min_grade`; com entidades relacionadas
(`subjects`): CL‑16 `requires_graded`, X‑CERT‑01 `chain`, X‑DEP‑02, X‑DEP‑04.

### FR‑026 / FR‑043 — Contrato 1.1

- [x] T041 `classes/local/details.php`: `params`, `subjects` e `evidence_level` (`proven`, `heuristic`, `name_match`, `static`) por verificação, a partir do retrato e da política
- [x] T042 `classes/engine.php`: `CONTRACT_VERSION = '1.1'`; manter `check` e `subject` nos resultados (hoje apagados) e acrescentar os campos de `details`, sem mudar os existentes
- [x] T043 `schema/report-v1.1.schema.json` (cópia aditiva do 1.0 com os campos opcionais; o 1.0 continua publicado); `tests/contract_test.php` valida A e B contra o 1.1 e confere os campos novos; execuções 1.0 continuam exibíveis
- [x] T044 `classes/local/presentation/catalog.php` e `finding.php`: `check`/`subject` lidos do resultado quando existirem (1.1) e da chave de ocorrência nas execuções 1.0

### US2 (complemento) — Encontrado × exigido em frase

- [x] T045 `finding.php`: linha "Encontrado: … · Exigido: …" em linguagem do Moodle quando houver `params` (datas no formato do usuário, "ilimitadas" para 0 tentativas, estado da autoinscrição); X‑CERT‑01 liga para os itens das atividades da cadeia (`subjects`); execuções 1.0 sem a linha

### US4 — Prévia e resultado da correção sem códigos (P2)

- [x] T046 `classes/local/correction/planner.php` e `verifier.php`: local com o nome da atividade; "problema encontrado" e listas de resolvidos/introduzidos com o texto do problema (descrição injetável no `verifier`, usada pelo planejador e pelo executor); sem mudar a impressão digital da proposta
- [x] T047 `classes/output/correction_proposal.php`, `correction_result.php`, `templates/correction*.mustache`: sem a letra da categoria; capacidades técnicas só para quem não pode executar (FR‑018/FR‑019); campo pelo rótulo do formulário ("Tentativas permitidas", "Encerramento do questionário"); estados antes → depois em palavras

### Testes, API e polimento da fase 3

- [x] T048 Testes: `tests/details_test.php` (valores e entidades do Curso B; nenhuma regra alterada: impressão digital igual à de `main`); `tests/local/correction/output_test.php` (sem `NAO_CONFORME`, `attempts:`, letra da categoria ou capacidades para quem pode executar)
- [x] T049 `API.md` (contrato 1.1, campos opcionais), README do plugin; PHPUnit, Behat, PHPCS; conferência no 5.3
- [x] T050 Versão 0.9.0, `CHANGES.md`, `results.md`

## Dependencies

- T003 → T004 → (T006, T007, T008 em paralelo) → T009–T012 → T013–T014 → T017–T018 → T020–T024.
- T005 depende de T003 e das strings (T006–T008); T015 e T019 dependem de T013/T014 e T017/T018.
- US2 é o MVP: sem as mensagens, ações e responsáveis não têm onde aparecer.

## Parallel examples

- T006, T007 e T008: blocos de regras distintos nos mesmos dois arquivos de idioma; escrever em arquivos temporários e
  juntar com ordenação alfabética no fim.
- T015 e T019 podem ser escritos juntos, depois de T014 e T018.

## Implementation strategy

1. Fundação (T003–T005), com o teste de cobertura falhando até as strings existirem.
2. US2 (mensagens e template) — o relatório já fica legível.
3. US7 (ações e precedências) e US8 (responsável e plugins).
4. Polimento, versão 0.7.0 e evidências. Parar e validar com o responsável antes da fase 2.
