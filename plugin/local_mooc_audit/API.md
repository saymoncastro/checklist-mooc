# API externa do Auditor MOOC (Web Services) — versão 1.0

Guia de configuração e uso da API do plugin `local_mooc_audit` (spec 004, plugin 0.6.0). A API permite que outro
sistema (por exemplo, o MOOC Admin) **peça a auditoria de uma sala**, **acompanhe a execução** e **leia o resultado da
última auditoria guardada**, usando os Web Services nativos do Moodle. Nenhuma função corrige nada na sala: a auditoria
continua somente leitura e a correção assistida (spec 002) só existe pela interface do Moodle.

Contrato detalhado: `specs/004-api-externa/contracts/web-services.md` (repositório do projeto).

## 1. Configuração pelo administrador

Nada funciona sem estes passos; na instalação o serviço vem **desabilitado** e nenhum papel tem a capacidade da API.

1. **Habilitar os Web Services**: *Administração do site > Recursos avançados > Habilitar serviços web*.
2. **Habilitar o protocolo REST**: *Administração do site > Servidor > Serviços web > Gerenciar protocolos* → REST.
3. **Criar a conta de integração** (uma conta própria, não a de uma pessoa) e um papel para ela.
   - **Papel "Integração Auditor MOOC" no sistema** (acesso a todas as salas):
     `local/mooc_audit:api`, `local/mooc_audit:audit`, `moodle/course:view`, `moodle/course:viewhiddencourses`,
     `webservice/rest:use`; opcional `local/mooc_audit:viewdashboard` (para ler execuções de salas já removidas).
   - **Variante por categoria** (acesso só às salas de uma categoria): no sistema, `local/mooc_audit:api` e
     `webservice/rest:use`; na categoria, `local/mooc_audit:audit`, `moodle/course:view` e
     `moodle/course:viewhiddencourses`.
   - Sem `moodle/course:view` a conta só alcança salas em que esteja inscrita; sem `viewhiddencourses`, salas ocultas
     respondem `requireloginerror`.
4. **Habilitar o serviço "Auditor MOOC"** (*Serviços web > Serviços externos*) e **autorizar a conta** em *Usuários
   autorizados*. O serviço é sempre restrito a usuários autorizados: a atualização do plugin reaplica essa restrição e a
   capacidade exigida (`local/mooc_audit:api`). Quem preferir pode criar um serviço personalizado com as mesmas três
   funções.
5. **Gerar o token** da conta para o serviço (*Serviços web > Gerenciar tokens*). Guarde-o como senha: quem tem o token
   age como a conta.
6. Opcional: ajustar **Auditorias pendentes pela API por conta** (`local_mooc_audit/api_max_pending`, padrão 100,
   faixa 1–2000) nas configurações do plugin.

## 2. Chamada

```text
POST {wwwroot}/webservice/rest/server.php
  wstoken=<token>&wsfunction=<função>&moodlewsrestformat=json&<parâmetros>
```

Toda resposta de sucesso traz `api_version` (`"1.0"`). Em caso de erro o Moodle responde
`{"exception": "...", "errorcode": "...", "message": "..."}`.

### 2.1 `local_mooc_audit_get_latest_audit` — última auditoria guardada

Lê o resultado da última auditoria concluída ou parcial da sala, **sem executar nada** (mesma regra da página
*Auditoria técnica*).

| Parâmetro | Tipo | Padrão | |
|---|---|---|---|
| `courseid` | int | — | sala |
| `includereport` | 0/1 | 1 | inclui o relatório guardado (`run.report`, texto JSON) |
| `includeevolutionitems` | 0/1 | 0 | inclui os itens da evolução (`run.evolution.items`) |

```bash
curl -s "$WWWROOT/webservice/rest/server.php" -d wstoken="$TOKEN" -d moodlewsrestformat=json \
  -d wsfunction=local_mooc_audit_get_latest_audit -d courseid=3 -d includereport=0
```

```json
{"api_version": "1.0", "found": true,
 "run": {"id": 790, "courseid": 3, "origin": "page", "status": "complete", "overall": "BLOQUEADA", "...": "..."},
 "stale_reasons": ["course_changed"]}
```

- `found: false` quando a sala nunca teve auditoria concluída ou parcial; nesse caso `run` e `stale_reasons` não vêm.
- `stale_reasons` diz **por que o resultado pode estar desatualizado**, calculado no momento da consulta:
  `course_changed` (a sala mudou depois da auditoria), `age` (mais antigo que o limite do histórico),
  `policy_changed` (a política institucional mudou), `plugin_changed` (o código das regras mudou). É sempre o
  **resultado da última auditoria**, nunca a conformidade atual da sala: para saber o estado de agora, peça uma nova
  auditoria.
- `last_failure` `{id, timefinished, errors[]}`: a tentativa mais recente falhou depois do último resultado válido.
- `pending` `{id, status, origin, timecreated}`: há uma auditoria da sala na fila ou em execução.

### 2.2 `local_mooc_audit_request_audit` — pedir uma auditoria

| Parâmetro | Tipo | |
|---|---|---|
| `courseid` | int | sala |

```bash
curl -s "$WWWROOT/webservice/rest/server.php" -d wstoken="$TOKEN" -d moodlewsrestformat=json \
  -d wsfunction=local_mooc_audit_request_audit -d courseid=3
```

```json
{"api_version": "1.0", "runid": 812, "courseid": 3, "status": "queued", "reused": false, "timecreated": 1793450000}
```

- A auditoria **nunca roda na chamada**: a execução fica na fila (`queued`) e uma tarefa em segundo plano a executa no
  próximo cron, como a conta que pediu. Na hora de executar, a tarefa confere de novo `local/mooc_audit:api` e
  `local/mooc_audit:audit`; se a conta perdeu a permissão, a execução termina `failed` com código `permission`.
- `reused: true`: a sala já tinha uma execução pendente viva (de qualquer origem: página, lote ou API) e o id devolvido
  é o dela. Pedidos repetidos não criam filas duplicadas.
- Limite: cada conta pode ter no máximo `api_max_pending` execuções pendentes pela API; acima disso, `queuefull`.

### 2.3 `local_mooc_audit_get_run` — acompanhar uma execução

| Parâmetro | Tipo | Padrão | |
|---|---|---|---|
| `runid` | int | — | execução |
| `includereport` | 0/1 | 1 | relatório guardado (só `complete`/`partial`) |
| `includeevolutionitems` | 0/1 | 0 | itens da evolução |

```bash
curl -s "$WWWROOT/webservice/rest/server.php" -d wstoken="$TOKEN" -d moodlewsrestformat=json \
  -d wsfunction=local_mooc_audit_get_run -d runid=812 -d includereport=0
```

**Acompanhamento recomendado**: consulte a cada 10–30 s com `includereport=0` até `status` ser `complete`, `partial`
ou `failed`; então leia uma vez com `includereport=1`. O tempo até o fim depende do cron e da fila, que é
**compartilhada com os lotes** e não tem prioridade nesta versão.

Uma execução inexistente e uma execução de sala fora do alcance da conta respondem o mesmo erro (`runnotfound`).
Execuções de salas removidas só são lidas por contas com `local/mooc_audit:viewdashboard`.

## 3. Execução (`run`)

| Campo | |
|---|---|
| `id`, `courseid` | |
| `origin` | `page`, `batch`, `cli`, `correction`, `api` (clientes devem ignorar valores desconhecidos) |
| `status` | `queued`, `running`, `complete`, `partial`, `failed` |
| `overall` | `BLOQUEADA`, `REQUER_CORRECOES`, `SEM_INCONFORMIDADES`; `null` sem relatório |
| `timecreated`, `timestarted`, `timefinished` | Unix; 0 = ainda não ocorreu |
| `attempts`, `pluginversion`, `contract_version`, `reofferyear` | |
| `policy` | `{version, fingerprint}` da política aplicada (ausente se a execução não chegou a carregá-la) |
| `summary` | `by_status` [{status, count}], `by_severity` [{severity, count}], `rules`, `overall_status` |
| `errors` | [{code, rule?, message}]: `rule_error`, `policy_warning`, `collection_warning`, `course_missing`, `permission`, `exception`, `interrupted`, `stale` |
| `evolution` | `baserunid`, `counts` (8 classes: new, changed, persistent, replaced, unverifiable, not_comparable, removed, resolved; ausente quando não comparável), `notes`, `items` (com `includeevolutionitems=1`), `items_unavailable` (a execução base já foi removida pela retenção) |
| `report` | **texto JSON** do relatório guardado (contrato 1.0, valida em `schema/report-v1.0.schema.json`) |

Nenhuma resposta identifica quem pediu a auditoria.

## 4. Erros

| `errorcode` | Quando |
|---|---|
| `invalidtoken`, `accessexception`, … | token inválido, serviço desabilitado ou conta não autorizada no serviço (núcleo) |
| `nopermissions` | falta `local/mooc_audit:api` no sistema ou `local/mooc_audit:audit` na sala |
| `requireloginerror` | a conta não pode entrar na sala (ex.: sala oculta sem `viewhiddencourses`) |
| `invalidcourseid` | sala inexistente ou curso do site |
| `runnotfound` | execução inexistente ou fora do alcance da conta |
| `queuefull` | limite de execuções pendentes pela API atingido para a conta |
| `busy` | outro pedido da mesma sala em andamento; tente de novo em alguns segundos |
| `invalidparameter` | parâmetro ausente ou de tipo errado |

## 5. Versionamento

- `api_version` = `1.0`. Nas versões 1.x só entram mudanças aditivas: campo opcional novo, parâmetro opcional novo com
  padrão que preserva o comportamento, novos valores de `origin`, `errors[].code` ou `stale_reasons`. Clientes devem
  ignorar campos e valores que não conhecem.
- Mudança incompatível → novas funções `..._v2`; as antigas continuam por pelo menos uma versão do plugin, marcadas
  como obsoletas, com nota de migração neste guia.
- `contract_version` é a versão do relatório; mudança dela tem nota de migração própria.

## 6. Fora desta versão

Ciclo (ano de reoferta) explícito por pedido, pedidos em lote, referência do cliente, notificações e limite de taxa
(decisão D4 da spec 004). Para auditar várias salas, use o lote do painel (spec 005) ou chame `request_audit` por sala.
