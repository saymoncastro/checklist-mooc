# Tasks: API Externa (Web Services) do Auditor MOOC

**Input**: `specs/004-api-externa/` (spec, plan, research, data-model, contracts/web-services.md, quickstart)
**Tests**: obrigatórios (constituição VII; FR-031). Caminhos relativos a `plugin/local_mooc_audit/`, salvo indicação.
**Pré-condição de implementação**: decisões D1–D3 de `research.md` aprovadas pelo responsável (regra do projeto: uma
fase por vez, só após autorização).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Conferir a base: se o PR #5 foi mesclado, rebasear a branch sobre `main`; confirmar que `run_store::
  alive_pending`/`reusable_pending`, `recorder::queue`, `audit_course::authorised`, `dashboard::stale_reasons` e o GET
  de `index.php` continuam como descritos em `research.md` R1/R6/R7/R10; registrar divergências em `research.md`.
- [ ] T002 Ambiente isolado `moocaudit-s4-web` (porta 8096, banco `moodle_s4`, rede `moocaudit_s4`) com a receita da
  Spec 005; acrescentar a seção "Ambiente da spec 004" a `moodle-env/README.md` (repositório); habilitar Web Services e
  REST no site de teste; linha de base PHPUnit registrada em `specs/004-api-externa/results.md`.
- [ ] T003 `version.php` 0.6.0 (`$plugin->version = 2026103100`, `release = '0.6.0'`); `classes/engine.php`
  `VERSION = '0.6.0'` (`CONTRACT_VERSION` permanece `'1.0'`).

## Phase 2: Foundational (bloqueia US1–US5)

- [ ] T004 `db/access.php`: capacidade `local/mooc_audit:api` — `'captype' => 'write'`, `'contextlevel' =>
  CONTEXT_SYSTEM`, **sem `archetypes`**, sem `riskbitmask`; comentário do arquivo atualizado (Spec 004).
- [ ] T005 `classes/local/history/run_store.php`: `const ORIGIN_API = 'api';` (cabe em `origin char(10)`); extrair de
  `alive_pending()` a regra de vivacidade para um método protegido reutilizável e criar
  `count_alive_pending_by_origin(int $userid, string $origin, ?int $now = null): int` (só `queued`/`running` vivas).
- [ ] T006 [P] `tests/local/history/run_store_test.php`: `count_alive_pending_by_origin` conta só vivas do solicitante e
  da origem (tarefa existente; `running` < `STALE_RUNNING`), ignora mortas e de outras origens; `alive_pending`
  inalterado.
- [ ] T007 `classes/local/api/service.php`: `const API_VERSION = '1.0'`; `require_api(): void`
  (`validate_context(context_system)` + `require_capability('local/mooc_audit:api')`); `course_for(int $courseid):
  \stdClass` (inexistente ou `SITEID` → `moodle_exception('invalidcourseid', 'error')`; existente →
  `validate_context(context_course)` + `require_capability('local/mooc_audit:audit')`); `find_run(int $runid):
  \stdClass` (inexistente, sala sem acesso/sem `audit`, sala removida sem `viewdashboard` → `moodle_exception
  ('runnotfound', 'local_mooc_audit')`, mesma mensagem em todos os casos).
- [ ] T008 `classes/local/api/run_view.php`: `export(\stdClass $run, bool $includereport, bool $includeitems): array`
  (campos de `contracts/web-services.md` RunView; mapas `por_status`/`nao_conformes_por_gravidade` → listas
  `by_status`/`by_severity`; datas nulas → 0; `policy` de `policy_store::get`; `evolution` de `run_store::json($run,
  'evolution')`; `report` = coluna `report` sem reserialização, só para `complete`/`partial`; **sem `userid`**) e
  `structure(): external_single_structure` (campos opcionais com `VALUE_OPTIONAL`, `overall` com `NULL_ALLOWED`;
  contagens de evolução como estrutura fixa das 8 classes de `evolution::classes()`).
- [ ] T009 `classes/local/history/evolution.php`: extrair de `history.php` a escolha da base (mesma sala, id ≠, base
  terminada até o início da execução, estado válido) e o `compare()` para `for_run(\stdClass $run, int $baseid = 0):
  ?array` (null quando não há base válida); `history.php` passa a usá-lo sem mudar a saída.
- [ ] T010 [P] Strings em `lang/en/local_mooc_audit.php` e `lang/pt_br/local_mooc_audit.php`: `mooc_audit:api`,
  `runnotfound`, `queuefull`, `busy`, `history_origin_api` ("API"), `api_max_pending` + `_desc`,
  `privacy:metadata:run:origin` citando a API; ordem alfabética do arquivo mantida.
- [ ] T011 [P] `tests/local/api/run_view_test.php`: projeção de execução concluída, parcial, falhou, na fila; relatório
  igual byte a byte à coluna; nenhuma chave `userid`; `clean_returnvalue(structure(), export(...))` sem erro em todos os
  estados.
- [ ] T012 Rodar `tests/history_access_test.php` e os testes de `history.php` existentes (refatoração T009 sem mudança
  de comportamento).

**Checkpoint**: base pronta — capacidade, origem, acesso e projeção testados.

## Phase 3: User Story 1 — Consultar a última auditoria de uma sala (P1) 🎯 MVP

**Goal**: cliente com token lê o resultado da última auditoria guardada, com desatualização, sem executar nada.
**Independent Test**: auditar B pela página; `get_latest_audit` devolve o mesmo relatório de `index.php?format=json`;
nenhuma linha/tarefa criada.

- [ ] T013 [US1] `classes/local/history/latest.php`: `for_course(\stdClass $course, ?int $now = null): array` com
  `valid` (de `latest_valid`), `stale` (`dashboard::stale_reasons` com impressão digital da política da execução,
  `policy_store::current_fingerprint()`, `run_store::plugin_version()`, `$course`), `lastfailure` (execução final mais
  recente quando é `failed` e posterior à válida, via `latest_per_course(final_states())`), `pending`
  (`reusable_pending`); `index.php` (GET HTML e `format=json`) passa a usá-lo sem mudar a saída.
- [ ] T014 [P] [US1] `tests/local/history/latest_test.php`: sala nunca auditada; válida sem mudança (`stale` vazio);
  sala alterada depois (`course_changed`); idade > `history_stale_days` (`age`); política mudada
  (`policy_changed`); parcial seguida de falha (`lastfailure`); pendente viva; resultados iguais aos que `index.php`
  mostrava antes da extração (caracterização).
- [ ] T015 [US1] `classes/external/get_latest_audit.php`: parâmetros `courseid` (PARAM_INT, obrigatório),
  `includereport` (PARAM_BOOL, padrão 1), `includeevolutionitems` (PARAM_BOOL, padrão 0); `service::require_api()`,
  `service::course_for()`, `latest::for_course()`; resposta `api_version`, `found`, `run` (RunView, opcional),
  `stale_reasons` (lista, opcional), `last_failure` {id, timefinished, errors[]} (opcional), `pending` {id, status,
  origin, timecreated} (opcional); itens da evolução por `evolution::for_run()`.
- [ ] T016 [US1] `db/services.php`: `$functions['local_mooc_audit_get_latest_audit']` (`classname`, `methodname`
  `execute`, `description`, `type` `read`, `capabilities` `local/mooc_audit:api, local/mooc_audit:audit`, `ajax` false,
  `loginrequired` true) e `$services['Auditor MOOC']` (`shortname` `local_mooc_audit`, `functions` com as funções já
  criadas, `requiredcapability` `local/mooc_audit:api`, `restrictedusers` 1, `enabled` 0, `downloadfiles` 0,
  `uploadfiles` 0).
- [ ] T017 [P] [US1] `tests/external/get_latest_audit_test.php` (`\core_external\tests\externallib_testcase`): sucesso
  com relatório válido em `tests/helper/schema_validator.php` e igual ao JSON de `index.php` sem `run`;
  `includereport=0`; `includeevolutionitems=1`; `found=false` com e sem pendente; `stale_reasons`; `last_failure`;
  contagem de `local_mooc_audit_run`/`_policy`/`_batch` e `task_adhoc` igual antes/depois (FR-020, SC-005);
  `clean_returnvalue` em todos.

**Checkpoint**: MVP — leitura da última auditoria pela API.

## Phase 4: User Story 2 — Pedir uma auditoria e acompanhar até o fim (P1)

**Goal**: cliente pede a auditoria, recebe o id imediatamente, a fila executa e a execução fica com origem `api`.
**Independent Test**: `request_audit` → `queued`; `runAdhocTasks` → `get_run` (US3) ou consulta direta à execução
mostra `complete`, `origin = api`.

- [ ] T018 [US2] `classes/local/history/recorder.php`: `queue(int $courseid, int $userid, int $batchid, string $origin
  = run_store::ORIGIN_BATCH): int`; chamadas existentes inalteradas.
- [ ] T019 [US2] `classes/task/audit_course.php`: `authorised(int $userid, int $courseid, string $origin =
  run_store::ORIGIN_BATCH)` — conta ativa + `audit` no curso + `batchaudit` (origem `batch`) ou `api` (origem `api`);
  `execute()` passa `$run->origin`; docblock da classe atualizado.
- [ ] T020 [US2] `settings.php`: `admin_setting_configtext` `local_mooc_audit/api_max_pending` (PARAM_INT, padrão 100)
  e, em `service`, leitura com faixa **1–2000** (fora da faixa ou vazio → 100), seguindo `batch_manager::max_courses()`.
- [ ] T021 [US2] `classes/local/api/service.php`: `request(int $courseid, int $userid): array` — trava
  `\core\lock\lock_config::get_lock_factory('local_mooc_audit')->get_lock('request_course_' . $courseid, 5)` (sem trava
  → `moodle_exception('busy', 'local_mooc_audit')`); `reusable_pending` → `reused = true`; senão
  `count_alive_pending_by_origin($userid, ORIGIN_API) >= max` → `moodle_exception('queuefull', 'local_mooc_audit')`;
  senão `recorder::queue($courseid, $userid, 0, run_store::ORIGIN_API)`; liberar a trava em `finally`; devolve
  `runid`, `courseid`, `status`, `reused`, `timecreated`.
- [ ] T022 [US2] `classes/external/request_audit.php` (`type` write): parâmetro `courseid`; `require_api()`,
  `course_for()`, `service::request()`; resposta `api_version`, `runid`, `courseid`, `status`, `reused`, `timecreated`;
  registrar em `db/services.php` e no serviço.
- [ ] T023 [P] [US2] `tests/local/api/service_test.php`: reaproveita pendente viva de lote; não reaproveita morta;
  duas chamadas seguidas → mesmo id; limite: `api_max_pending = 1`, segunda sala → `queuefull`, mesma sala →
  `reused`; trava ocupada (obtida no teste) → `busy` sem criar nada.
- [ ] T024 [P] [US2] `tests/external/request_audit_test.php`: sucesso (`queued`, `reused=false`, linha com `origin =
  api`, `batchid = 0`, tarefa `audit_course` criada); nenhuma regra executada na chamada (nenhuma linha passa a
  `running`/final e `local_mooc_audit_policy` inalterada); após `runAdhocTasks(audit_course::class)` a execução fica
  `complete` com evolução contra a válida anterior; `clean_returnvalue`.
- [ ] T025 [P] [US2] `tests/task/audit_course_test.php`: execução `api` roda com `api` + `audit`; `api` revogada antes
  da tarefa → `failed`/`permission` e sala não auditada; conta suspensa → `permission`; execuções `batch` continuam
  exigindo `batchaudit` (sem `batchaudit` mas com `api` → `permission`).
- [ ] T026 [US2] `tests/readonly_test.php`: `request_audit` + tarefa não alteram `timemodified` nem o conteúdo das
  tabelas de curso examinadas (constituição III).
- [ ] T027 [P] [US2] Rótulo da origem: `output/history_page.php`, `output/run_page.php`, `output/run_summary.php` já
  usam `history_origin_<origin>`; teste em `tests/output_test.php` (ou teste de saída do histórico existente) com uma
  execução `api` mostrando "API".

**Checkpoint**: pedir e acompanhar funcionam; a fila respeita permissões por origem.

## Phase 5: User Story 3 — Consultar uma execução por identificador (P1)

**Goal**: qualquer execução de sala no escopo é lida por id, com relatório e evolução opcionais.
**Independent Test**: execuções de duas salas (uma fora do escopo); `get_run` de cada; fora do escopo = inexistente.

- [ ] T028 [US3] `classes/external/get_run.php` (`type` read): parâmetros `runid` (PARAM_INT), `includereport`
  (PARAM_BOOL, 1), `includeevolutionitems` (PARAM_BOOL, 0); `require_api()`, `service::find_run()`,
  `run_view::export()`; itens por `evolution::for_run()`; base removida → `items_unavailable = true` com contagens
  guardadas; resposta `api_version`, `run`; registrar em `db/services.php` e no serviço.
- [ ] T029 [P] [US3] `tests/external/get_run_test.php`: concluída com itens iguais aos de `evolution::for_run()` usados
  por `history.php`; `includereport=0`; falhou (código e mensagem, sem relatório); na fila/em execução (sem relatório);
  base removida pela retenção (`items_unavailable`); inexistente e outra sala fora do escopo → mesmo `errorcode` e
  mesma mensagem `runnotfound`; sala removida com `viewdashboard` (sucesso) e sem (`runnotfound`); consulta não grava
  (contagens); `clean_returnvalue`.

**Checkpoint**: as três funções existem e estão cobertas.

## Phase 6: User Story 4 — Acesso sob controle do administrador (P2)

**Goal**: ninguém usa a API sem decisão do administrador; escopo por sala igual ao das páginas.
**Independent Test**: instalação nova sem papel com `api`; serviço desabilitado e restrito; matriz de acesso negado.

- [ ] T030 [US4] `tests/external/access_test.php`: após instalação, `local/mooc_audit:api` sem arquétipo (nenhum papel
  padrão a tem); serviço `local_mooc_audit` em `external_services` com `enabled = 0`, `restrictedusers = 1`,
  `requiredcapability = local/mooc_audit:api` e as três funções.
- [ ] T031 [US4] `tests/external/access_test.php`: matriz para as três funções — professor editor com `audit` sem
  `api` → `nopermissions` e nada gravado; conta com `api` no sistema e `audit`/`course:view`/`viewhiddencourses` só na
  categoria A: sala da categoria B → `nopermissions` ou `requireloginerror` (`request_audit`, `get_latest_audit`) e
  `runnotfound` (`get_run`); sala oculta sem `viewhiddencourses` → `requireloginerror`; curso do site e id inexistente
  → `invalidcourseid` (SC-003).
- [ ] T032 [US4] Independência (FR-022, SC-008): suíte existente roda com o serviço desabilitado e nenhuma conta com
  `api` (padrão da instalação de testes) — registrar no `results.md`.

## Phase 7: User Story 5 — Documentação e contrato estável (P2)

**Goal**: quem não participou configura e usa a API só com o guia.
**Independent Test**: roteiro do `quickstart.md` §2 seguido por outra pessoa no ambiente s4.

- [ ] T033 [P] [US5] `API.md`: habilitar Web Services/REST; papel "Integração Auditor MOOC" (sistema:
  `local/mooc_audit:api`, `local/mooc_audit:audit`, `moodle/course:view`, `moodle/course:viewhiddencourses`,
  `webservice/rest:use`; opcional `local/mooc_audit:viewdashboard`) e variante por categoria; autorizar a conta no
  serviço (restrito sempre, reaplicado no upgrade) ou usar serviço personalizado; gerar token; as três funções com
  parâmetros, respostas e erros de `contracts/web-services.md`; exemplos `curl`; acompanhamento (10–30 s,
  `includereport=0` até o estado final); significado de `stale_reasons` ("resultado da última auditoria", nunca
  conformidade atual); versionamento (`api_version`, clientes ignoram valores desconhecidos, `_v2`, obsolescência).
- [ ] T034 [P] [US5] `README.md` do plugin: seção "API externa (0.6.0)" com link para `API.md`, capacidade nova,
  configuração `api_max_pending`, limitações (sem lote, ciclo, notificações, limite de taxa).
- [ ] T035 [P] [US5] `tests/external/contract_test.php`: toda resposta de sucesso das três funções traz `api_version
  = '1.0'` e, com relatório, `contract_version = '1.0'`; os nomes de função e parâmetros batem com
  `db/services.php`; relatório valida no esquema.

## Phase 8: Polish & Cross-Cutting

- [ ] T036 [P] `tests/privacy/provider_test.php`: execução `origin = api` exportada no contexto do curso e anonimizada
  (`userid = 0`) sem remoção; metadados citam a origem.
- [ ] T037 [P] `docs/backlog.md` (repositório): 004 especificada/entregue; `docs/visao-produto.md` linha 8 (API) com
  o estado.
- [ ] T038 Após aprovação de D1–D3: decisão 13 em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` (repositório) — capacidade da
  API, serviço desabilitado/restrito, limite da fila.
- [ ] T039 `php -l` em todos os arquivos alterados; PHPCS `moodle` sem erros; PHPUnit completo verde; resultado e
  número de testes em `specs/004-api-externa/results.md`.
- [ ] T040 Medições do `quickstart.md` §3 (tempo de `request_audit` na sala de 151 módulos, `get_run`/`get_latest_audit`
  com relatório, tamanho, custo de `includeevolutionitems`) em `results.md`; conferir SC-001/SC-002.
- [ ] T041 Roteiro manual do `quickstart.md` §2 no ambiente s4 (US1–US4 e SC-007 cronometrado) com evidências em
  `results.md`.

## Dependencies & Execution Order

- Setup (T001–T003) → Foundational (T004–T012) → histórias.
- US1 (T013–T017) depende só da base; é o MVP. T016 cria `db/services.php`; T022 e T028 acrescentam suas funções a ele
  (mesmo arquivo: sequenciais).
- US2 (T018–T027) depende da base; independente de US1 exceto por `db/services.php`.
- US3 (T028–T029) depende da base e de T009 (`evolution::for_run`); usa execuções criadas diretamente no teste, sem
  depender de US2.
- US4 (T030–T032) depende de US1–US3 (testa as três funções).
- US5 (T033–T035) depende do contrato estável (US1–US3).
- Polish depois de tudo; T038 depende da aprovação do responsável.

## Parallel Opportunities

- Base: T006, T010, T011 em paralelo após T005/T008.
- US1: T014 e T017 em paralelo depois de T013/T015.
- US2: T023, T024, T025, T027 em paralelo depois de T021/T022.
- US5: T033, T034, T035 em paralelo.
- Polish: T036, T037 em paralelo.

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 → validar leitura da última auditoria (quickstart §2.3) e parar para revisão.
2. US2 + US3 → acompanhamento completo (quickstart §2.4–2.5).
3. US4 → matriz de acesso; US5 → guia; Polish → medições, revisão independente, entrega 0.6.0.
4. Cada fase só começa com autorização do responsável (regra do projeto).
