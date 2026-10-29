# Research: API Externa (Spec 004)

Fase 0 do plano. Cada item: **Decisão**, **Racional**, **Alternativas**. Fontes conferidas no código do Moodle 4.5.15
do ambiente `moocaudit-s5-web` (somente leitura) e no plugin 0.5.0 da branch da Spec 005 (d211191).

## R1 — Estado de partida

**Decisão**: partir da branch `claude/spec-005-auditoria-lote-948e09` (PR #5 aberto) e rebasear sobre o `main` quando
ele for mesclado. Reaproveitar, sem mudar comportamento: `run_store` (estados, `alive_pending`, `reusable_pending`,
`latest_valid`, `latest_per_course`), `recorder::queue`/`execute`, `task\audit_course` (trava `course_<id>`,
tentativas, retomada), `evolution::compare`, `dashboard::stale_reasons`, `policy_store`, `history\access`,
`schema/report-v1.0.schema.json` e `tests/helper/schema_validator.php`.

**Racional**: a 004 é camada de integração; toda regra de negócio (o que é pendente viva, última válida,
desatualização, evolução) já existe e tem testes. Duplicá-la na API criaria duas verdades.

**Alternativas**: esperar o merge do PR #5 (atrasaria a especificação sem ganho; o plano não depende de detalhes que a
revisão do PR possa mudar, e a tarefa T001 confere isso).

## R2 — Mecanismo: Web Services nativos do Moodle 4.5

**Decisão**: três classes em `classes/external/` estendendo `\core_external\external_api`, com
`execute_parameters()`, `execute()` e `execute_returns()`; declaração em `db/services.php` (`$functions` + `$services`).
Validação de parâmetros por `validate_parameters()`; retorno validado pelo núcleo (`clean_returnvalue` nos testes).

**Racional**: constituição IX (recurso nativo suficiente); autenticação por token, protocolos (REST, SOAP…), registro
de chamadas (`\core\event\webservice_function_called`) e gestão de tokens ficam com o núcleo. Nenhuma página nova,
nenhum endpoint próprio.

**Alternativas**: endpoint próprio (`api.php` com chave) — reimplementa autenticação, viola VIII/IX;
`index.php?format=json` com sessão — não serve máquina e não permite pedir auditoria.

## R3 — Capacidade e papel de integração

**Decisão**: nova capacidade `local/mooc_audit:api` — `captype` `write` (a solicitação cria execuções e tarefas),
`contextlevel` `CONTEXT_SYSTEM`, **sem `archetypes`**, sem `riskbitmask` (não expõe dado pessoal nem altera
configuração; o relatório é a visão de configuração). Exigida (1) pelo serviço (`requiredcapability`) e (2) por cada
função no contexto de sistema, antes de qualquer outra conferência — o segundo ponto vale quando as funções são postas
em serviços personalizados. Somada a `local/mooc_audit:audit` no curso (e `viewdashboard` para sala removida).

Papel documentado "Integração Auditor MOOC" (contexto de sistema): `local/mooc_audit:api`,
`local/mooc_audit:audit`, `moodle/course:view`, `moodle/course:viewhiddencourses`, `webservice/rest:use`
(opcional `local/mooc_audit:viewdashboard` para execuções de salas removidas). Para restringir a uma categoria, a
conta recebe `api` + `webservice/rest:use` no sistema e um papel com `audit` + `course:view` + `viewhiddencourses` só
na categoria.

**Racional**: privilégio mínimo (VIII) e decisão explícita do administrador. Sem arquétipo, a instalação não concede a
API a ninguém (mesmo padrão das capacidades `webservice/*:use` do núcleo). `audit` sozinha daria a todo professor
editor com token a capacidade de enfileirar auditorias por máquina; `batchaudit` mistura semânticas (lote, gerente).

**Alternativas**: arquétipo gerente (conveniente para testes, mas concede API a todos os gerentes sem decisão);
capacidade no contexto de categoria (a capacidade de escopo já é `audit`; duplicar o escopo em duas capacidades não
acrescenta controle). **[validar]**

## R4 — Serviço externo

**Decisão**: `$services['Auditor MOOC']` com `shortname` `local_mooc_audit`, `functions` = as três,
`requiredcapability` = `local/mooc_audit:api`, `restrictedusers` = 1, `enabled` = 0, `downloadfiles`/`uploadfiles` = 0.

**Racional**: conferido em `lib/upgradelib.php` (`external_update_services`): `enabled` só é aplicado na criação (o
administrador habilita e o upgrade não desfaz); `restrictedusers` e `requiredcapability` são reaplicados a cada
upgrade — o guia avisa que o serviço é sempre restrito e que, para usuários não autorizados explicitamente, as funções
podem ser postas em um serviço personalizado (as conferências das funções continuam valendo).

**Alternativas**: não declarar serviço (o administrador cria manualmente) — mais passos e erros; serviço habilitado e
aberto — contraria VIII. **[validar]**

## R5 — Validação de contexto e erros

**Decisão**:

- Toda função: `validate_context(context_system)` + `require_capability('local/mooc_audit:api')` → sem ela,
  `required_capability_exception` (`nopermissions`).
- Operações por sala (`request_audit`, `get_latest_audit`): sala inexistente ou `SITEID` → `moodle_exception
  ('invalidcourseid', 'error')`; existente → `validate_context(context_course)` (faz `require_login($course)`, como a
  página, inclusive visibilidade) + `require_capability('local/mooc_audit:audit')`. Erros nativos:
  `requireloginerror` (não entra na sala) ou `nopermissions`.
- `get_run`: carregar a execução; se não existe → `runnotfound`. Sala existente: tentar `validate_context(course)` +
  `has_capability(audit)`; **qualquer** falha → `runnotfound`. Sala removida: `has_capability(viewdashboard, system)`,
  senão `runnotfound`. Mesma mensagem, mesmo código, para não confirmar existência.
- Erros do plugin: `moodle_exception('runnotfound'|'queuefull'|'busy', 'local_mooc_audit')`, strings pt_br/en.

**Racional**: paridade com `index.php`/`history.php` (`require_login` + `audit`), o que a spec exige (FR-004/FR-006).
Para execuções, o id é sequencial e enumerável; igualar as respostas fecha o canal lateral.

**Alternativas**: só `has_capability` sem `require_login` (API daria mais que a página: sala oculta sem
`viewhiddencourses`); distinguir "sem permissão" de "não existe" em execuções (vaza existência).

## R6 — Fluxo da solicitação, serialização e limite

**Decisão** (`local\api\service::request()`):

1. Conferências de R5.
2. Trava `local_mooc_audit` / `request_course_<id>` com espera de até 5 s (distinta da trava `course_<id>` que a
   tarefa mantém durante a auditoria, para não esperar uma auditoria inteira); sem trava → `busy`.
3. `run_store::reusable_pending(courseid)` → devolve `{runid, reused: true}` (qualquer origem).
4. `run_store::count_alive_pending_by_origin(userid, 'api')` ≥ `api_max_pending` → `queuefull`.
5. `recorder::queue(courseid, userid, 0, run_store::ORIGIN_API)` → `{runid, reused: false}`.
6. Libera a trava (`finally`).

Configuração `api_max_pending` (padrão 100, 1–2000; vazio ou ≤ 0 → 100, acima de 2000 → 2000, como
`batch_manager::max_courses()`). A fila e a concorrência são as dos lotes, sem prioridade para a API: um lote grande
atrasa o fim das execuções pedidas pela API (o guia avisa). Contagem só de pendentes **vivas** (mesma regra de
`alive_pending`: tarefa ainda existe ou iniciada há menos de `STALE_RUNNING`), para que execuções mortas, que a limpeza
diária encerra, não bloqueiem a conta.

**Racional**: FR-007 a FR-011; reaproveita a definição de "viva" da Spec 005 (sem segunda definição). A trava curta
torna "verificar e criar" atômico por sala sem índice único novo.

**Alternativas**: índice único parcial (não portável entre bancos no XMLDB); transação sem trava (não impede corrida
de leitura); limite global em vez de por solicitante (uma conta travaria as demais). **[validar]** o valor 100.

## R7 — Tarefa: autorização por origem

**Decisão**: `recorder::queue()` ganha o parâmetro `$origin` (padrão `ORIGIN_BATCH`, chamadas atuais intactas);
`run_store::ORIGIN_API = 'api'` (cabe em `char(10)`). `audit_course::authorised($userid, $courseid, $origin)`:
conta ativa + `audit` no curso + **`batchaudit`** (origem `batch`) ou **`api`** (origem `api`). `execute()` passa
`$run->origin`. `policy_override()` devolve `null` para `batchid = 0` (já é o caso).

**Racional**: FR-010; a tarefa é a mesma; só a capacidade "de canal" muda. Nenhuma mudança de esquema.

**Alternativas**: tarefa própria para a API (duplicaria trava, tentativas e retomada).

## R8 — Forma das respostas

**Decisão**: metadados com estrutura declarada (`external_single_structure`); mapas de chave dinâmica viram listas de
pares (`by_status: [{status, count}]`, `by_severity: [{severity, count}]`); contagens de evolução como estrutura fixa
das 8 classes (`new`, `persistent`, `changed`, `resolved`, `unverifiable`, `not_comparable`, `removed`, `replaced`),
ausente quando não comparável; campos opcionais com `VALUE_OPTIONAL`/`NULL_ALLOWED`; datas como inteiros Unix (0 =
ausente) e o relatório como **texto JSON** (`PARAM_RAW`) do contrato 1.0 tal como gravado (a coluna `report` sem
reserialização). Itens da evolução (opcionais) com estrutura declarada a partir de `evolution::item()`.

**Racional**: FR-024; o relatório tem ~15 tipos de resultado com campos variáveis e já tem esquema publicado e
validador de testes; duplicá-lo em `external_description` seria frágil e quebraria a cada mudança aditiva. Entregar a
coluna gravada garante SC-004 (igual ao que a página mostra) e custo zero de recodificação.

**Alternativas**: estrutura tipada completa (frágil, enorme); `report` como objeto não declarado (o núcleo não aceita
retorno não descrito).

## R9 — Versionamento

**Decisão**: `local\api\service::API_VERSION = '1.0'` em toda resposta (`api_version`), e `contract_version` (da
execução) quando há relatório. Regras: 1.x só aditiva; incompatível → funções novas `local_mooc_audit_<nome>_v2`
mantidas com as antigas por ≥ 1 versão do plugin; antigas marcadas com `execute_is_deprecated()` (mecanismo nativo,
conferido em `external_api`, `deprecated_method`) e nota de migração no guia. Nova versão do contrato do relatório
(2.0) exige nota de migração (constituição X) e é anunciada pela API em `contract_version`.

**Racional**: FR-023 a FR-025; o nome da função é a unidade de versionamento que clientes de Web Services Moodle já
entendem; a versão no corpo permite ao cliente conferir.

**Alternativas**: parâmetro `version` na chamada (lógica condicional em cada função); versão só no `version.php`
(cliente não sabe o que muda).

## R10 — Última auditoria: lógica compartilhada com `index.php`

**Decisão**: extrair de `index.php` (GET) para `local\history\latest::for_course(\stdClass $course, int $now)` o
cálculo: última válida (`latest_valid`), impressão digital da política, `dashboard::stale_reasons(...)`, falha mais
recente posterior (`latest_per_course(final_states)`) e pendente viva (`reusable_pending`). `index.php` e a API passam a
usá-lo. Da mesma forma, extrair de `history.php` a escolha da base e o cálculo da evolução para
`evolution::for_run(\stdClass $run, int $baseid = 0)`.

**Racional**: FR-017/FR-015 exigem "mesma regra" da página; uma só implementação é a forma verificável disso
(constituição IX: refatoração com benefício — duplicação removida — e comportamento observável preservado pelos
testes existentes de `index.php`/`history.php`).

**Alternativas**: copiar a lógica para a API (duas verdades); chamar `index.php` internamente (impossível sem sessão).

## R11 — Privacidade, registro e exibição

**Decisão**: nenhuma tabela nem campo novo. `privacy:metadata:run:origin` passa a citar "API" (pt_br/en); o teste do
provider ganha uma execução `api` (exportada e anonimizada como as demais). Respostas sem `userid`. Rótulo
`history_origin_api` ("API") usado onde as origens já aparecem (`output\history_page`, `run_page`,
`run_summary`). Sem evento novo: o
núcleo registra a chamada; a execução registra solicitante e origem.

**Racional**: FR-016, FR-027, FR-028; constituição VIII (minimização).

**Alternativas**: devolver `userid` do solicitante (o cliente sabe quem é; terceiros não precisam).

## R12 — Tamanho e desempenho

**Decisão**: sem paginação; `includereport` (padrão 1) e `includeevolutionitems` (padrão 0). O guia recomenda
acompanhar com `includereport=0` até o estado final e ler o relatório uma vez. Metas: solicitação ≤ 1 s (não roda
regra), consultas ≤ 2 s com relatório. Medidas na 005: relatório médio 29 kB, máx. 41 kB. O custo de calcular os
itens da evolução (dois relatórios decodificados em memória) não foi medido isoladamente; a tarefa de medição (fase
final) o registra em `results.md`.

**Racional**: SC-001/SC-002; o volume não justifica paginação nem compressão próprias (o servidor web pode comprimir).

**Alternativas**: paginar `results` (quebraria a igualdade com o relatório guardado).

## R13 — Testes

**Decisão**: `tests/external/*_test.php` estendendo `\core_external\tests\externallib_testcase` (alias
`externallib_advanced_testcase`), chamando `execute()` e validando com `external_api::clean_returnvalue()`; cenários
com papéis criados no teste (conta de integração com `api` no sistema e `audit` só na categoria A; professor editor
sem `api`; gerente com `viewdashboard`). Fila real com `runAdhocTasks(audit_course::class)`. Relatório devolvido
validado por `tests/helper/schema_validator.php`. Contagem de linhas de `local_mooc_audit_run`/`_policy`/`_batch` e de
`task_adhoc` antes/depois nas consultas. `readonly_test` estendido para a solicitação + tarefa. Privacy provider com
execução `api`. `audit_course_test` com revogação de `api` antes da tarefa.

**Racional**: FR-031; constituição VII (cenários pelas APIs do Moodle, sem backups).

**Alternativas**: testes só de `local\api\service` (não cobririam validação de parâmetros/retorno do núcleo).

## R14 — Documentação do consumidor

**Decisão**: `plugin/local_mooc_audit/API.md` (vai junto com o plugin): habilitar Web Services e REST, criar o papel e
a conta de integração, autorizar no serviço, gerar token, as três funções com parâmetros/respostas/erros, exemplos
`curl` (REST, `moodlewsrestformat=json`), fluxo de acompanhamento (intervalo 10–30 s, `includereport=0`), semântica de
desatualização, versionamento e notas de migração. Link no `README.md` do plugin. O contrato detalhado fica em
`specs/004-api-externa/contracts/web-services.md` e o guia o resume.

**Racional**: FR-030, SC-007; constituição X (verificável por quem não participou).

## Decisões pendentes (para o responsável)

| # | Decisão | Recomendação | Onde |
|---|---|---|---|
| D1 | Capacidade própria `local/mooc_audit:api` sem arquétipo, somada a `audit` | Aprovar | R3, FR-003 |
| D2 | Serviço "Auditor MOOC" criado desabilitado, restrito a usuários autorizados, com `requiredcapability` | Aprovar | R4, FR-001 |
| D3 | Limite de 100 execuções `api` pendentes por solicitante (1–2000) | Aprovar até medir na homologação | R6, FR-011 |
| D4 | Ciclo explícito, lote, referência do cliente e notificações ficam fora da 1.0 | Aprovar; reavaliar com o MOOC Admin | spec, Fora de escopo |
| D5 | Registrar a decisão como nº 13 em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` (afeta o princípio VIII) | Sim, após aprovação | constituição, Governance |
