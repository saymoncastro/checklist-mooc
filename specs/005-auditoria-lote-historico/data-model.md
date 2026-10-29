# Data Model: Auditoria em Lote, Histórico e Acompanhamento

Três tabelas novas (XMLDB, `db/install.xml` + `db/upgrade.php`), nenhuma alteração em tabelas existentes. Inteiros de
data são carimbos Unix. `userid = 0` significa "processo" (CLI) ou solicitante anonimizado pelo privacy provider.

## local_mooc_audit_policy — política aplicada

| Campo | Tipo | Nulo | Descrição |
|---|---|---|---|
| id | int(10) seq | não | |
| fingerprint | char(40) | não | sha1 do JSON canônico (chaves ordenadas recursivamente) da política efetiva, sem `warnings` e sem `reference_timestamp` |
| policyversion | char(100) | não | `policy_version` declarada (ex.: `2027-decisoes-2026-10-28+admin`) |
| policy | text | não | JSON canônico da política efetiva (inclui `reoffer_year` resolvido) |
| timecreated | int(10) | não | primeira execução que usou esta política |

Índice único: `fingerprint`. Inserida sob demanda (`policy_store::ensure()`), nunca alterada.

## local_mooc_audit_batch — pedido de lote

| Campo | Tipo | Nulo | Descrição |
|---|---|---|---|
| id | int(10) seq | não | |
| userid | int(10) | não (padrão 0) | solicitante |
| name | char(255) | não | rótulo dado pelo gestor (padrão: critério + data) |
| criteria | text | não | JSON: `{"mode": "category"\|"ids"\|"startyear"\|"selection", "categoryid": int, "subcategories": bool, "courseids": [int], "startyear": int}` |
| skipped | text | sim | JSON: lista de `{"courseid": int, "reason": "not_found"\|"site"\|"permission"\|"duplicate", "runid": int?}`; `duplicate` aponta a execução reaproveitada |
| timecreated | int(10) | não | |

Índices: `userid`, `timecreated`. Andamento, estado e término **derivados** de `local_mooc_audit_run` (`batchid`).

## local_mooc_audit_run — execução de auditoria

| Campo | Tipo | Nulo | Descrição |
|---|---|---|---|
| id | int(10) seq | não | identificador da execução |
| courseid | int(10) | não | sala auditada (pode deixar de existir) |
| batchid | int(10) | não (padrão 0) | lote; 0 = fora de lote |
| userid | int(10) | não (padrão 0) | solicitante |
| origin | char(10) | não | `page`, `batch`, `cli` |
| status | char(10) | não | `queued`, `running`, `complete`, `partial`, `failed` |
| overall | char(30) | sim | `BLOQUEADA`, `REQUER_CORRECOES`, `SEM_INCONFORMIDADES` (nulo se não houve relatório) |
| policyid | int(10) | não (padrão 0) | → `local_mooc_audit_policy.id`; 0 enquanto na fila ou se falhou antes de carregar a política |
| pluginversion | char(40) | não | `release (version)` do plugin, ex.: `0.5.0 (2026103000)` |
| contractversion | char(10) | não | versão do contrato do relatório (`1.0`) |
| reofferyear | int(4) | sim | ano de reoferta efetivo |
| summary | text | sim | JSON: `summary` do relatório (`por_status`, `nao_conformes_por_gravidade`, `regras`, `overall_status`) |
| report | text | sim | JSON do relatório (contrato 1.0, sem `snapshot`, resultados com `occurrence`) |
| details | text | sim | JSON: `{"rules": {"CL-13": {"p": hash12, "r": hash12}}, "modules": {"<cmid>": "<modname>\|<nome normalizado>"}, "sections": [0,1,…], "metrics": {"seconds": f, "peak_mb": f, "db_reads": n, "db_writes": n}}` |
| previousrunid | int(10) | não (padrão 0) | execução usada como base da evolução (0 = primeira) |
| evolution | text | sim | JSON: contagens por classe (abaixo) e `notes` (ex.: `plugin_version_changed`) |
| errors | text | sim | JSON: lista de `{"code": str, "message": str, "rule": str?}` (falha, regras em `ERRO`, avisos de coleta e de política) |
| attempts | int(4) | não (padrão 0) | vezes que uma tarefa começou esta execução |
| timecreated | int(10) | não | pedido |
| timestarted | int(10) | sim | início da auditoria |
| timefinished | int(10) | sim | estado final |

Índices: `courseid, id` (última execução por sala), `batchid`, `userid`, `status`, `timefinished`.

### Estados e transições

```
queued ──(tarefa obtém trava)──▶ running ──▶ complete | partial | failed
   │                               │  (processo morto: trava liberada; tarefa refeita, attempts+1; >3 → failed/interrupted)
   └──(curso removido / permissão revogada / limpeza de presas)──▶ failed
page/cli: registro criado já em running (síncrono)
```

- `complete`: relatório produzido, nenhum resultado `ERRO`.
- `partial`: relatório produzido, ≥1 resultado `ERRO` (regra com falha interna).
- `failed`: sem relatório. Códigos em `errors`: `course_missing`, `permission`, `exception`, `interrupted`, `stale`.
- Estados finais são imutáveis: `run_store::finish()` só atualiza linhas `queued`/`running` (condição na cláusula
  WHERE + releitura).

## Ocorrência (dentro de `report.results[]`)

`occurrence` = `RULE|ENTITY|CHECK[|SUBJECT]` (ver research R2), única por execução. ENTITY ∈ `course`, `cm:<cmid>`,
`sec:<número>`. Exemplo: `CL-13|cm:42|attempts`, `CL-21|course|html_block|acessibilidade`,
`X-DEP-02|cm:57|target_completion|cm:41`.

## Evolução (calculada por `local\history\evolution::compare(base, atual)`)

Entradas: os dois relatórios, os `details` das duas execuções. Para cada chave K problemática (`NAO_CONFORME` ou
`ATENCAO`) em ao menos uma execução:

| Base | Atual | Condição | Classe |
|---|---|---|---|
| problema | problema | mesmo estado e gravidade | `persistent` |
| problema | problema | estado ou gravidade diferentes | `changed` |
| problema | `ERRO`/`NAO_VERIFICAVEL`, ou regra `_rule` não verificável/erro na atual | — | `unverifiable` |
| problema | ausente/conforme/N.A. | entidade `cm:`/`sec:` não existe na atual; sucessora com o mesmo problema | `replaced` |
| problema | ausente/conforme/N.A. | entidade não existe na atual | `removed` |
| problema | ausente/conforme/N.A. | impressão digital da regra mudou | `not_comparable` (`policy_changed`/`rule_changed`) |
| problema | ausente/conforme/N.A. | demais casos | `resolved` |
| ausente/não problema | problema | regra não verificada na base (`_rule`, `ERRO` na base, regra inexistente na base) | `not_comparable` (`previously_unverified`) |
| ausente/não problema | problema | impressão digital da regra mudou | `not_comparable` |
| ausente/não problema | problema | demais casos (inclui atividade nova) | `new` |

Item de evolução: `{class, reason?, key, rule, title, severity_before, severity_after, status_before, status_after,
local, cmid, link, evidence_before, evidence_after, successor_cmid?}`. Contagens guardadas em `run.evolution`:
`{"base": runid, "counts": {"new": n, "persistent": n, "resolved": n, "changed": n, "unverifiable": n,
"not_comparable": n, "removed": n, "replaced": n}, "notes": [...]}`.

Relatório base sem `occurrence` (execução de versão anterior): `notes: ["base_without_occurrences"]`, contagens nulas,
nenhuma classificação.

## Situação da sala no painel (derivada)

| Situação | Condição (última execução da sala) |
|---|---|
| `not_audited` | nenhuma execução final |
| `incomplete` | última execução final é `failed` ou `partial` |
| `blocking` | `overall = BLOQUEADA` |
| `needs_fix` | `overall = REQUER_CORRECOES` |
| `none_detected` | `overall = SEM_INCONFORMIDADES` |

Indicadores adicionais: `pending` (execução `queued`/`running` existente), `stale` com motivos `age`,
`policy_changed`, `plugin_changed` (com base na última execução **válida** — `complete`/`partial`).

## Configurações novas (`local_mooc_audit/…`)

| Chave | Padrão | Limites |
|---|---|---|
| `history_retention_days` | 365 | 30–1825 |
| `history_stale_days` | 7 | 1–365 |
| `batch_concurrency` | 2 | 1–10 |
| `batch_max_courses` | 300 | 1–2000 |

## Capacidades novas

| Capacidade | Contexto | captype | Arquétipos |
|---|---|---|---|
| `local/mooc_audit:viewdashboard` | sistema | read | manager |
| `local/mooc_audit:batchaudit` | sistema | write | manager |

## Privacidade

Dados pessoais: `run.userid`, `batch.userid`. Contextos: execução → contexto do curso; lote → contexto de sistema.
Exportação: execuções (id, sala, data, origem, estado, resultado geral) e lotes (nome, data) do usuário. Exclusão:
`userid = 0` (anonimização), histórico mantido. Os relatórios guardados são gerados com usuário de teste 0 e não
contêm dados de estudantes.
