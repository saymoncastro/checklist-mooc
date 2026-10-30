# Data Model: API Externa (Spec 004)

**Nenhuma tabela nem coluna nova.** A API lê e cria registros das tabelas da Spec 005
(`specs/005-auditoria-lote-historico/data-model.md`). Esta página descreve só o que muda ou passa a ser exposto.

## Execução (`local_mooc_audit_run`) — alterações

| Item | Antes (0.5.3) | Depois (0.6.0) |
|---|---|---|
| `origin` | `page`, `batch`, `cli`, `correction` (0.5.1) | + **`api`** (pedida por Web Service; `batchid = 0`) |
| Quem cria na fila | `batch_manager::create`/`retry_failed` via `recorder::queue` | + `local\api\service::request` via `recorder::queue(..., ORIGIN_API)` |
| Reconferência na tarefa | conta ativa + `batchaudit` (sistema) + `audit` (curso) | origem `api`: conta ativa + **`api`** (sistema) + `audit` (curso); `batch` inalterada |

Estados e transições inalterados (`queued → running → complete | partial | failed`; finais imutáveis). Uma execução
`api` falha com os mesmos códigos (`course_missing`, `permission`, `exception`, `interrupted`, `stale`).

### Regras de validação novas

- Por sala, no máximo uma execução pendente **viva** criada pela API (reaproveitamento + trava `request_course_<id>`).
- Por solicitante, no máximo `api_max_pending` execuções `api` pendentes vivas (padrão 100).
- Uma consulta nunca cria, altera ou remove linha de `_run`, `_policy`, `_batch` nem de `task_adhoc`.

## Configuração nova

| Nome | Tipo | Padrão | Faixa | Uso |
|---|---|---|---|---|
| `local_mooc_audit/api_max_pending` | inteiro | 100 | 1–2000 (vazio ou ≤ 0 → 100; > 2000 → 2000) | limite de pendentes `api` por solicitante (FR-011) |

## Capacidade nova

| Capacidade | captype | Contexto | Arquétipos | Risco |
|---|---|---|---|---|
| `local/mooc_audit:api` | write | sistema | nenhum | nenhum |

## Serviço externo novo (`external_services`, gerido pelo núcleo)

| Campo | Valor |
|---|---|
| name / shortname | `Auditor MOOC` / `local_mooc_audit` |
| functions | `local_mooc_audit_request_audit`, `local_mooc_audit_get_run`, `local_mooc_audit_get_latest_audit` |
| enabled / restrictedusers | 0 / 1 |
| requiredcapability | `local/mooc_audit:api` |
| downloadfiles / uploadfiles | 0 / 0 |

## Entidades de resposta (não persistidas)

Definidas em detalhe em [contracts/web-services.md](contracts/web-services.md).

- **RunView** — projeção de uma execução: id, courseid, origin, status, overall, timecreated/started/finished,
  attempts, pluginversion, contractversion, policy {version, fingerprint}, reofferyear, summary {by_status[],
  by_severity[], rules, overall_status}, errors[] {code, rule?, message}, evolution {baserunid, counts{8 classes}?,
  notes[], items[]?}, report (texto JSON)?. Sem `userid`.
- **LatestView** — found (bool), run (RunView)?, stale_reasons[] (`course_changed`, `age`, `policy_changed`,
  `plugin_changed`), last_failure {id, timefinished, errors[]}?, pending {id, status, origin, timecreated}?.
- **RequestResult** — runid, courseid, status, reused, timecreated.
- Todas trazem `api_version`.

## Fonte de cada campo

| Campo | Fonte |
|---|---|
| policy.version / fingerprint | `policy_store::get($run->policyid)` |
| summary | `run_store::json($run, 'summary')` convertido de mapas para listas |
| evolution.counts / notes / baserunid | `run_store::json($run, 'evolution')` (`base`, `counts`, `notes`) |
| evolution.items | `evolution::for_run($run)` (extraído de `history.php`) |
| report | coluna `report` tal como gravada |
| stale_reasons, last_failure, pending | `local\history\latest::for_course()` (extraído de `index.php`) |
