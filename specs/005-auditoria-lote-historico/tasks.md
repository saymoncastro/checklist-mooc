# Tasks: Auditoria em Lote, Histórico e Acompanhamento

**Input**: `specs/005-auditoria-lote-historico/` (spec, plan, research, data-model, contracts)
**Tests**: obrigatórios (constituição VII). Caminhos relativos a `plugin/local_mooc_audit/`.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [X] T001 Ambiente isolado `moocaudit-s5-web` (porta 8095, banco `moodle_s5`, rede `moocaudit_s5`) + Selenium;
  receita em `moodle-env/README.md`; linha de base PHPUnit registrada (169 testes).
- [X] T002 `version.php` 0.5.0 (2026103000); `engine::VERSION` 0.5.0.

## Phase 2: Foundational — identidade das ocorrências (bloqueia US1–US4)

- [X] T003 `classes/local/rule/*.php`: `check`/`subject` em todos os achados conforme research R2.
- [X] T004 `classes/engine.php`: compor `occurrence` em `run_rules()` (entidade, `_rule` para ERRO/NV de regra, sufixo
  `#n` como rede de segurança), remover `check`/`subject` do resultado.
- [X] T005 [P] `tests/occurrence_test.php`: unicidade em A, B e cenários; mesmas chaves com ordem de módulos e idioma
  diferentes; mesma chave para conforme e não conforme da mesma verificação.
- [X] T006 [P] `tests/policy_dependency_test.php`: perturbar cada chave da política; toda regra afetada a declara;
  corrigir `POLICY_KEYS` incompletas.
- [X] T007 Caracterização A/B e contrato 1.0 continuam passando (o schema aceita `occurrence`; adicionar a
  propriedade documentada ao schema).

## Phase 3: User Story 1 — Registrar e consultar o histórico (P1) 🎯 MVP

- [X] T008 `db/install.xml` + `db/upgrade.php` (2026103000): tabelas `policy`, `run`, `batch`.
- [X] T009 [P] `classes/local/history/fingerprint.php`: política canônica, fatia por regra, hash do código da regra.
- [X] T010 [P] `classes/local/history/policy_store.php`: `ensure()` por impressão digital.
- [X] T011 `classes/local/history/run_store.php`: criar, iniciar, finalizar (só de `queued`/`running`), última válida,
  lista por curso, pendente por curso.
- [X] T012 `classes/local/history/recorder.php`: `queue()`, `execute()` (motor + details + métricas + evolução +
  estado), `record_now()` (página/CLI).
- [X] T013 `index.php`: registrar; `run` no JSON; quadro "Desde a auditoria anterior"; link do histórico
  (`output/report.php`, `templates/report.mustache`).
- [X] T014 `history.php` + `output/history_page.php` + `templates/history.mustache`: lista e execução guardada.
- [X] T015 [P] Testes: `tests/local/history/run_store_test.php`, `recorder_test.php`, `fingerprint_test.php`
  (primeira auditoria, segunda sem alterações, imutabilidade, política guardada uma vez).
- [X] T016 `cli/audit.php --record`.

## Phase 4: User Story 2 — Evolução (P1)

- [X] T017 `classes/local/history/evolution.php`: classes do data-model; substituição por `matcher::key_name`.
- [X] T018 `output/evolution.php` + `templates/evolution.mustache`; correções da trilha no intervalo (sem cópia).
- [X] T019 [P] `tests/local/history/evolution_test.php`: cenário do pedido (1 resolvida, 2 persistentes), nova,
  gravidade/estado alterados, ERRO/NV, política alterada (só a regra afetada), regra alterada, recurso removido,
  substituído, base sem ocorrências, múltiplas ocorrências no mesmo local (CL-21).
- [X] T020 [P] Teste de integração com sala real: corrigir via API do Moodle e reauditar; correção assistida (trilha)
  entre execuções aparece na evolução sem atribuição.

## Phase 5: User Story 3 — Lote em segundo plano (P1)

- [X] T021 `db/access.php`: `viewdashboard`, `batchaudit` (+ strings); `db/tasks.php`.
- [X] T022 `classes/task/audit_course.php`: reconferência de permissão, trava, idempotência, tentativas, falhas.
- [X] T023 `classes/local/history/batch_manager.php`: seleção (categoria, ids, ano de início, lista), recusas,
  duplicação, limite, criação de execuções e tarefas, reexecutar falhas, andamento derivado.
- [X] T024 `batch.php` + `output/batch_page.php` + `templates/batch.mustache`.
- [X] T025 `cli/batch.php`.
- [X] T026 [P] Testes: `tests/local/history/batch_manager_test.php`, `tests/task/audit_course_test.php` (lote
  conforme/não conforme, falha parcial, curso removido, permissão revogada, tarefa repetida, interrompida, duplicação).

## Phase 6: User Story 4 — Painel (P1)

- [X] T027 `classes/local/history/dashboard.php`: escopo por capacidade, última execução por sala, situação,
  desatualização, filtros, contadores, evolução somada.
- [X] T028 `dashboard.php` + `output/dashboard_page.php` + `templates/dashboard.mustache`; `settings.php`
  (admin_externalpage + configurações).
- [X] T029 [P] `tests/local/history/dashboard_test.php` (situações exclusivas, filtros, desatualização, sem
  "aprovado"/percentual).

## Phase 7: User Story 5 — Permissões, privacidade, retenção (P2)

- [X] T030 `tests/history_access_test.php`: histórico/execução/evolução/painel/lote negados sem capacidade; id de
  execução de outro curso.
- [X] T031 `classes/privacy/provider.php` + `tests/privacy/provider_test.php`.
- [X] T032 `classes/task/cleanup_history.php` + `tests/task/cleanup_history_test.php`.
- [X] T033 `tests/readonly_test.php`: execução registrada e tarefa de lote não alteram a sala.

## Phase 8: User Story 6 — Medir e operar (P3)

- [X] T034 Medição: lote de 40+ salas sintéticas + sala de 151 módulos pela fila real; tempo, memória, consultas,
  tamanho; painel com 100 salas.
- [X] T035 Documentação operacional (README do plugin, `moodle-env/README.md`, quickstart).

## Phase 9: Polish & Cross-Cutting

- [X] T036 [P] Behat: `tests/behat/history.feature`, `tests/behat/dashboard.feature`.
- [X] T037 Suíte completa PHPUnit, Behat (incluindo 001–003), PHPCS `moodle`, `php -l`.
- [X] T038 Revisão independente de código e segurança; correções.
- [X] T039 `results.md`, `docs/backlog.md`, `docs/catalogo-regras.md` (chave de ocorrência), `DECISOES` pendentes.
- [X] T040 PR para revisão (sem merge).

## Dependencies & Execution Order

- Phase 2 bloqueia todas as histórias (a evolução depende da chave).
- US1 (Phase 3) bloqueia US2, US3 e US4 (todas leem execuções).
- US2 e US3 são independentes entre si; US4 depende de US3 para o botão de lote (os dados vêm de US1).
- US5 depende de US1–US4; US6 de US3.

## Parallel Example: Phase 2

T005 e T006 em paralelo depois de T003–T004 (arquivos de teste distintos).

## Implementation Strategy

MVP = Phases 1–3 (histórico registrado e consultável). Depois US2 (valor central), US3 e US4 juntas (o painel sem
lote ainda é útil com auditorias da página), US5 antes de qualquer homologação, US6 para a homologação.
