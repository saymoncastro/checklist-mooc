# Contrato: Web Services do Auditor MOOC — API 1.0

Funções do serviço externo **Auditor MOOC** (`shortname` `local_mooc_audit`). Chamada REST:

```text
POST {wwwroot}/webservice/rest/server.php
  wstoken=<token>&wsfunction=<função>&moodlewsrestformat=json&<parâmetros>
```

Toda função exige, nesta ordem: token válido do serviço (núcleo) → `local/mooc_audit:api` no sistema → regras da sala
ou da execução abaixo. Em caso de erro o núcleo responde `{"exception": "...", "errorcode": "...", "message": "..."}`.

## Tipos comuns

**RunView** (`run`):

| Campo | Tipo | Observação |
|---|---|---|
| `id` | int | |
| `courseid` | int | |
| `origin` | string | `page`, `batch`, `cli`, `correction`, `api` |
| `status` | string | `queued`, `running`, `complete`, `partial`, `failed` |
| `overall` | string\|null | `BLOQUEADA`, `REQUER_CORRECOES`, `SEM_INCONFORMIDADES`; null se sem relatório |
| `timecreated`, `timestarted`, `timefinished` | int | Unix; 0 = ainda não ocorreu |
| `attempts` | int | vezes que uma tarefa iniciou a execução |
| `pluginversion` | string | `release (versão)` |
| `contract_version` | string | versão do contrato do relatório (hoje `1.0`) |
| `policy` | {`version`: string, `fingerprint`: string}\|ausente | ausente se a execução não chegou a carregar a política |
| `reofferyear` | int | 0 se ausente |
| `summary` | objeto\|ausente | `by_status`: [{`status`, `count`}], `by_severity`: [{`severity`, `count`}], `rules`: int, `overall_status`: string |
| `errors` | [{`code`, `rule`?, `message`}] | falhas e limitações (`rule_error`, `policy_warning`, `collection_warning`, `course_missing`, `permission`, `exception`, `interrupted`, `stale`) |
| `evolution` | objeto\|ausente | `baserunid`: int; `counts`?: {`new`, `persistent`, `changed`, `resolved`, `unverifiable`, `not_comparable`, `removed`, `replaced`} (ausente quando não comparável); `notes`: [string]; `items`?: [EvolutionItem] (só com `includeevolutionitems=1`); `items_unavailable`?: bool (base removida) |
| `report` | string\|ausente | **texto JSON** do relatório guardado (contrato 1.0 + `occurrence`, sem `snapshot`), só para `complete`/`partial` e `includereport=1`; valida em `schema/report-v1.0.schema.json` |

**EvolutionItem**: `class`, `reason`?, `key`, `rule`, `title`, `severity`, `status_before`?, `status_after`?,
`severity_before`?, `severity_after`?, `local`, `cmid`?, `link`?, `evidence_before`?, `evidence_after`?,
`successor_cmid`? — os mesmos campos de `evolution::item()` (página de histórico).

Nenhuma resposta traz identificação do solicitante.

## `local_mooc_audit_request_audit` (type `write`)

Pede a auditoria de uma sala. Nunca executa a auditoria na chamada.

**Parâmetros**: `courseid` (int, obrigatório).

**Acesso**: `api` (sistema); sala existente e ≠ site; `require_login` na sala; `local/mooc_audit:audit` na sala.

**Resposta**:

```json
{"api_version": "1.0", "runid": 812, "courseid": 3, "status": "queued", "reused": false, "timecreated": 1793450000}
```

- `reused: true` quando a sala já tinha execução pendente viva (de qualquer origem); `status` é o dela (`queued` ou
  `running`). A execução reaproveitada continua do solicitante original, cujas permissões a tarefa confere; se ela
  falhar com `permission`, um novo pedido cria uma execução `api` da conta.
- Efeito quando `reused: false`: uma linha em `local_mooc_audit_run` (`origin = api`, `status = queued`, `batchid =
  0`) e uma tarefa ad hoc `\local_mooc_audit\task\audit_course`.

## `local_mooc_audit_get_run` (type `read`)

**Parâmetros**: `runid` (int, obrigatório); `includereport` (int 0/1, padrão 1); `includeevolutionitems` (int 0/1,
padrão 0).

**Acesso**: `api` (sistema); sala da execução existente → `require_login` + `audit` nela; sala removida →
`viewdashboard` (sistema). Qualquer falha dessas → `runnotfound` (igual a inexistente).

**Resposta**: `{"api_version": "1.0", "run": RunView}`.

## `local_mooc_audit_get_latest_audit` (type `read`)

Resultado da última auditoria guardada de uma sala, sem executar nada (regra do GET de `index.php`).

**Parâmetros**: `courseid` (int, obrigatório); `includereport` (padrão 1); `includeevolutionitems` (padrão 0).

**Acesso**: como `request_audit`.

**Resposta**:

```json
{
  "api_version": "1.0",
  "found": true,
  "run": { "id": 790, "status": "complete", "...": "RunView" },
  "stale_reasons": ["course_changed"],
  "last_failure": {"id": 801, "timefinished": 1793449000, "errors": [{"code": "exception", "message": "..."}]},
  "pending": {"id": 812, "status": "queued", "origin": "api", "timecreated": 1793450000}
}
```

- `found: false` (sucesso) quando não há execução concluída ou parcial; `run` e `stale_reasons` ausentes; `pending` e
  `last_failure` se houver.
- `stale_reasons` ⊆ {`course_changed`, `age`, `policy_changed`, `plugin_changed`}, calculados no momento da consulta.
- `last_failure` só quando a execução final mais recente é uma falha posterior à última válida.
- `pending`: execução pendente viva da sala, se houver.

## Erros

| `errorcode` | Quando | Origem |
|---|---|---|
| `invalidtoken`, `accessexception`, … | token, serviço desabilitado, usuário não autorizado no serviço | núcleo |
| `nopermissions` | falta `local/mooc_audit:api`, ou `audit` na sala (operações por sala) | núcleo (`required_capability_exception`) |
| `requireloginerror` | a conta não pode entrar na sala (ex.: oculta sem permissão) | núcleo |
| `invalidcourseid` | sala inexistente ou curso do site | núcleo (`error`) |
| `runnotfound` | execução inexistente ou fora do escopo | plugin |
| `queuefull` | limite de execuções `api` pendentes do solicitante atingido | plugin |
| `busy` | outro pedido ou a criação de um lote segura a trava da fila; tente de novo em alguns segundos | plugin |
| `invalidparameter` | parâmetro ausente ou de tipo errado | núcleo |

## Versionamento

- `api_version` = `1.0`. Em 1.x só mudanças aditivas (campo opcional novo; parâmetro opcional novo com padrão que
  preserva o comportamento; novo valor de `origin`, `errors[].code` ou `stale_reasons` — clientes MUST ignorar valores
  desconhecidos).
- Mudança incompatível → funções `..._v2`, as antigas mantidas por ≥ 1 versão do plugin e marcadas obsoletas; nota de
  migração em `API.md`.
- `contract_version` acompanha o relatório; mudança de versão do relatório tem nota de migração própria.
