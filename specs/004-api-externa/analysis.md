# Specification Analysis Report — Spec 004

**Data**: 2026-10-30 · **Artefatos**: spec.md, plan.md, tasks.md, research.md, data-model.md,
contracts/web-services.md, quickstart.md · **Constituição**: v1.0.0

Análise não destrutiva antes da implementação (`/speckit-analyze`). As correções A1–A7 foram aprovadas e aplicadas
em 2026-10-30 (coluna "Situação"); A8 fica como está.

## Findings

| ID | Categoria | Severidade | Local | Resumo | Recomendação / Situação |
|---|---|---|---|---|---|
| A1 | Inconsistência | MEDIUM | tasks T020 × spec FR-011 × `batch_manager::max_courses()` | T020 diz "fora da faixa ou vazio → 100" e também "seguindo `max_courses()`", que limita acima de 2000 a 2000 (não a 100) | Fixar: ≤ 0 ou vazio → 100; > 2000 → 2000 (igual a `max_courses()`); validar na configuração (`admin_setting_configtext` com PARAM_INT) — **Aplicado**: T020, FR-011, R6, data-model |
| A2 | Subespecificação | MEDIUM | spec Edge Cases; research R6/R7 | Execuções `api` dividem a fila e a concorrência (padrão 2) com os lotes: um lote de 100 salas atrasa o fim de uma execução pedida pela API, e a spec não diz isso ao consumidor | Acrescentar caso de borda + aviso no `API.md` (T033): o tempo até o fim depende da fila; sem prioridade nesta versão — **Aplicado**: Edge Cases, R6, T033 |
| A3 | Cobertura | LOW | FR-012 | Nenhum teste confere que a execução `api` usa o ano de reoferta da política vigente (sem sobreposição de lote) | Em T024, conferir `reofferyear` da execução = o da política vigente — **Aplicado**: T024 |
| A4 | Cobertura | LOW | FR-029 | Ausência de segredos é herdada do relatório (Spec 001), sem asserção na API | Em T024/T029, sala com senha de inscrição: a resposta não contém o valor — **Aplicado**: T024, T029 |
| A5 | Cobertura | LOW | FR-002 | Nenhuma tarefa garante que só as três funções existem | Em T030, conferir que as únicas funções `local_mooc_audit_*` declaradas são as três e todas no serviço — **Aplicado**: T030 |
| A6 | Ambiguidade | LOW | FR-020 × R5 | `validate_context` faz `require_login`, que grava o último acesso do usuário ao curso (`user_lastaccess`, núcleo); "consultas não gravam" refere-se às tabelas do plugin e à fila | Explicitar em FR-020 que registros de acesso/log do núcleo não contam; teste de contagem continua só nas tabelas do plugin e `task_adhoc` — **Aplicado**: FR-020, T029 |
| A7 | Inconsistência | LOW | quickstart §2.6 × research R3 | Variante por categoria omite `webservice/rest:use` no papel de sistema | Papel de sistema da variante: `api` + `webservice/rest:use` — **Aplicado**: quickstart §2.6, R3, T033 |
| A8 | Terminologia | LOW | spec/plan/tasks | "sala" e "curso" usados como sinônimos (herdado das Specs 001–005) | Manter "sala" no texto e "curso" para contexto/ids do Moodle, como nas specs anteriores — mantido |

Nenhum achado CRITICAL ou HIGH.

## Coverage Summary

| Requisito | Tarefas | Observação |
|---|---|---|
| FR-001 serviço e funções | T016, T022, T028, T030 | |
| FR-002 sem escrita/correção/lote | T030 | A5 aplicado |
| FR-003 capacidade `api` | T004, T007, T030, T031 | |
| FR-004 acesso de página | T007, T031 | |
| FR-005 contexto da execução | T007, T029 | |
| FR-006 `runnotfound` igualado / `invalidcourseid` | T007, T029, T031 | |
| FR-007 reaproveitar + serializar | T021, T023 | |
| FR-008 criar na fila sem executar | T021, T024 | |
| FR-009 resposta da solicitação | T022, T024 | |
| FR-010 reconferência na tarefa | T019, T025 | |
| FR-011 limite por solicitante | T005, T020, T021, T023 | A1 aplicado |
| FR-012 política vigente | T018, T024 | A3 aplicado |
| FR-013/FR-014 campos e relatório | T008, T011, T029 | |
| FR-015 itens da evolução | T009, T028, T029 | |
| FR-016 sem identificação | T008, T011 | |
| FR-017/FR-018/FR-019 última auditoria | T013–T015, T017 | |
| FR-020 consultas não gravam | T017, T029 | A6 aplicado |
| FR-021 sem escrita em sala | T026 | |
| FR-022 independência | T032 | |
| FR-023/FR-024 versões e estruturas | T008, T011, T035 | |
| FR-025 versionamento | T033 (documentação) | política; não há `_v2` nesta versão |
| FR-026 erros estáveis | T007, T010, T021, T023, T029 | |
| FR-027 privacy | T010, T036 | |
| FR-028 rótulo da origem | T010, T027 | |
| FR-029 sem segredos | T024, T029 | A4 aplicado |
| FR-030 guia | T033, T034 | |
| FR-031 testes | T006, T011, T014, T017, T023–T026, T029–T031, T035, T036 | |
| SC-001/SC-002 desempenho | T040 | |
| SC-003 acesso negado | T031 | |
| SC-004 igual à página | T017 | |
| SC-005 consultas sem gravação | T017, T029 | |
| SC-006 limites | T023 | |
| SC-007 guia em ≤ 30 min | T041 | manual |
| SC-008 suíte existente | T012, T032, T039 | |

## Constitution Alignment

Sem violação de MUST. Pontos conferidos: I (serviço desabilitado, FR-022); II (Web Services nativos, sem núcleo
alterado, sem esquema novo); III (consultas sem gravação, `readonly_test` estendido em T026); IV (nenhuma função de
correção); VI (motivos de desatualização, `found=false` ≠ erro); VII (matriz de testes por função; resultado da suíte
em `results.md`, T039); VIII (`require_login` + `require_capability` por sala, capacidade própria no sistema com
`captype` coerente e ausência de risco justificada em R3, reconferência na tarefa, sem segredos nem `userid`); IX
(classes novas em Complexity Tracking); X (spec/plano/tarefas antes do código; `api_version` versionado). Portões de
entrega cobertos por T003, T034, T037, T039. Decisões D1–D3 aguardam o responsável; D5 (decisão 13) em T038.

## Unmapped Tasks

T001–T003 (setup), T012 (regressão da extração), T037–T038 (documentação do repositório) — de apoio, sem FR próprio.

## Metrics

- Requisitos: 31 FR + 8 SC = 39
- Tarefas: 41
- Cobertura: 39/39 com ao menos uma tarefa explícita
- Ambiguidades: 1 (A6) · Duplicações: 0 · Inconsistências: 2 (A1, A7)
- CRITICAL: 0 · HIGH: 0 · MEDIUM: 2 · LOW: 6 (A1–A7 aplicados; restam 0 MEDIUM e 1 LOW, A8)

## Next Actions

- Nada bloqueia a implementação do ponto de vista da análise; ela só começa após aprovação de D1–D3 (regra do
  projeto).
- A1–A7 aplicados em spec.md (FR-011, FR-020, Edge Cases), tasks.md (T020, T024, T029, T030, T033), research.md (R3,
  R6), data-model.md e quickstart.md §2.6; nenhuma nova tarefa nem mudança de escopo.
