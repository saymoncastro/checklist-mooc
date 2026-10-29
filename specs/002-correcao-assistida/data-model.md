# Data Model: Correção Assistida

## Proposta de correção (`local\correction\proposal`, efêmera)

| Campo | Tipo | Descrição |
|---|---|---|
| `ruleid` | string | `CL-13`, `CL-14`, `CL-17` |
| `courseid`, `cmid` | int | curso e atividade |
| `strategy` | string | `quiz_attempts`, `quiz_timeclose`, `close_date_guidance`, `completion_guidance` |
| `category` | `A`/`B`/`C` | categoria da matriz para este caso |
| `executable` | bool | verdadeiro só se categoria A **e** sem impedimentos **e** simulação favorável |
| `field` | string\|null | coluna alterada (`attempts`, `timeclose`) ou null (orientada) |
| `current`, `proposed` | int\|string\|null | valores brutos (gravação usa só valores recalculados no servidor) |
| `currentdisplay`, `proposeddisplay` | string | valores legíveis (datas no fuso do usuário e do servidor) |
| `problem` | string | evidência do achado da regra |
| `location` | string + URL | rótulo da atividade e link para a edição |
| `rule` | título + fonte | da metadata da regra |
| `justification` | string | política/decisão que fundamenta o valor proposto |
| `impact` | lista | resolvidas / introduzidas previstas pela simulação; `targetresolved` |
| `risks` | lista de strings | avisos (inscritos, substituições, conclusão por esgotar tentativas) |
| `blockers` | lista de strings | impedimentos que tornam a proposta não executável |
| `dependencies` | lista de strings | o que depende da atividade / do que a correção depende |
| `capabilities` | lista | capacidades exigidas para executar |
| `roles` | lista | papéis que têm essas capacidades no curso |
| `canexecute` | bool | se o usuário atual tem todas as capacidades (só para exibição) |
| `policyversion` | string | versão da política usada |
| `fingerprint` | sha256 | ver research R3 |

Invariantes: `executable ⇒ category = A ∧ blockers = ∅ ∧ impact.targetresolved ∧ impact.introduced = ∅ ∧ field ≠ null`.

## Trilha (`local_mooc_audit_fix`)

| Coluna | Tipo XMLDB | Nulo | Descrição |
|---|---|---|---|
| `id` | int(10) seq | não | |
| `courseid` | int(10) | não | curso |
| `cmid` | int(10) | não | atividade |
| `ruleid` | char(20) | não | regra |
| `strategy` | char(40) | não | estratégia |
| `field` | char(40) | não | coluna alterada |
| `oldvalue` | char(255) | sim | valor anterior |
| `newvalue` | char(255) | sim | valor novo |
| `outcome` | char(20) | não | `applied` · `failed` |
| `reaudit` | char(20) | não | `resolved` · `not_resolved` · `introduced` · `error` · `skipped` |
| `details` | text | sim | JSON curto: regras resolvidas/introduzidas ou mensagem de erro (sem conteúdo do curso) |
| `policyversion` | char(100) | não | versão da política |
| `engineversion` | char(40) | não | versão do motor |
| `userid` | int(10) | não | quem executou (0 após anonimização) |
| `timecreated` | int(10) | não | data/hora |

Índices: `courseid`, `cmid`, `userid`. Linhas são gravadas para execuções que chegaram à escrita; conflitos,
impedimentos e falta de permissão terminam antes e não geram linha (são exibidos ao usuário).

## Evento `\local_mooc_audit\event\correction_applied`

- `context`: módulo; `objecttable`: `local_mooc_audit_fix`; `objectid`: id da linha; `crud`: `u`; `edulevel`:
  `LEVEL_OTHER`.
- `other`: `ruleid`, `field`, `oldvalue`, `newvalue`, `reaudit`.
- Disparado após o `commit` de uma correção `applied`.
