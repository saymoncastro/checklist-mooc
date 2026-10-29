# Research: Auditoria em Lote, Histórico e Acompanhamento

**Feature**: `specs/005-auditoria-lote-historico/spec.md` · **Data**: 2026-10-29 · **Base**: `main` 037fd45

Cada decisão registra o problema, a alternativa escolhida, as rejeitadas e a evidência no código existente.

## R1 — Estado real do repositório

- `main` = 037fd45 (merge do PR #4). PRs #1–#4 **mesclados**; nenhum PR aberto. A branch desta spec parte de `main`
  sem divergência.
- Plugin 0.4.0 (2026102906): 29 regras (`classes/local/rule/`), `engine::audit()` recalcula a cada chamada, sem
  tabela de resultados. Única tabela: `local_mooc_audit_fix` (trilha da Spec 002).
- Linha de base no ambiente isolado `moocaudit-s5-web`: **169 testes PHPUnit, 2401 asserções, OK**.

## R2 — Chave estável de ocorrência

**Problema**: a Spec 003 (`audit_relation::key/subject`) identifica resultados por regra + `cmid` mapeado, ou regra +
local textual sem ids + "assunto" da evidência (texto antes de `:`). Isso depende do idioma e do texto da evidência e
colide quando uma regra emite várias ocorrências no mesmo local (CL-21 blocos, CL-22, CL-12 várias verificações no
"Livro de notas").

**Decisão**: entre execuções da **mesma sala** os ids internos são estáveis. Cada regra passa a declarar, em cada
achado, `check` (código estável da condição) e, quando necessário, `subject` (discriminador semântico). O motor
compõe `occurrence = regra|entidade|check[|subject]`, onde entidade = `cm:<cmid>` se o achado tem `cmid`, senão
`sec:<id da seção>` se tem seção (o id, não o número: inserir ou mover uma seção muda os números — achado da revisão),
senão `course`. `check` e `subject` não ficam no relatório; só a chave composta.

Tabela de verificações (todas as regras):

| Regra | Entidade | `check` | `subject` |
|---|---|---|---|
| CL-04 | course | `summary_link` | URL exigida (valor da política) |
| CL-05 | course | `category` | — |
| CL-06 / CL-07 | course | `startdate` / `enddate` | — |
| CL-08 | course | `self_enrol` (ausência: `self_enrol` sem subject), `guest` | `enrol:<id>` |
| CL-09 | course (ausente) ou cm | `forum_presence`, `forum_completion` | — |
| CL-11 | course ou cm | `profile_presence`, `profile_completion`, `certificate_presence`, `requires_profile` | — |
| CL-12 | course | `total_sum`, `course_item`, `aggregation`, `onlygraded`, `root_category`, `total_grademax`, `subcategories` (sem item de total ou categoria raiz: um `NAO_VERIFICAVEL` no nível da sala, que torna a regra inteira não verificada na evolução) | — |
| CL-13 | cm | `attempts`, `intro_attempts` | — |
| CL-14 | cm | `close_date`; sem avaliativas: `applicability` | `item:<itemnumber>` (vários itens de nota, ex.: workshop) |
| CL-16 | course ou cm | `evaluation_presence`, `requires_graded`, `root_operator` | — |
| CL-17 | course, cm | `summary`, `completion` | — |
| CL-18 | course ou cm | `forbidden` | nome do módulo (nível curso) |
| CL-19 | cm, course | `completion`, `summary` | — |
| CL-20 | course, sec | `hiddensections`, `content`, `generic_name`, `hidden_completion` | — |
| CL-21 | course | `progress_block`, `html_block` | título normalizado do bloco exigido |
| CL-22 | course | `licence_label`, `licence_feedback`, `licence_certificate` | — |
| CL-23 | course ou cm | `certificate_presence`, `requires_evaluation`, `min_grade`, `root_operator` | — |
| X-AV-01 | cm, course | `intro_grade`, `summary` | `item:<itemnumber>` |
| X-CERT-01 | cm, course | `chain`, `hidden`, `certificate_presence` | — |
| X-CONT-01 | cm, course | `empty_content`, `book_chapters`, `quiz_questions`, `feedback_items`, `files`, `summary` | — |
| X-CONT-04 | cm, course | `pluginfile`, `summary` | `<campo>:<caminho>` |
| X-DEP-01 | cm ou sec, course | `missing_target`, `summary` | `cm:<alvo>` |
| X-DEP-02 | cm, course | `target_completion`, `target_grade`, `summary` | `cm:<alvo>` |
| X-DEP-03 | cm, course | `grade_item`, `summary` | `item:<id>` |
| X-DEP-04 | course | `cycle`, `summary` | ciclo canônico (rotação a partir do menor cmid) |
| X-INT-02 | cm, course | `storage`, `summary` | `<componente>/<área>/<caminho>` |
| X-INT-03 | course | `plugin`, `applicability` | `mod:<nome>` / `availability:<tipo>` |
| X-STR-01 | cm, course | `visibility`, `name`, `summary` | — |
| (motor) | course | `_rule` (regra inteira `ERRO` ou não verificável) | — |

Estados mutuamente exclusivos da mesma condição usam o **mesmo** `check` (ex.: CL-13 `intro_attempts` cobre
"enunciado diverge" e "enunciado não menciona"; CL-17 `completion` cobre manual e automática sem critério), de modo
que a troca entre eles é "estado alterado", não "resolvido + novo".

**Rede de segurança**: se ainda assim duas ocorrências colidirem numa execução, o motor acrescenta `#2`, `#3`… e o
teste de unicidade falha nas salas de referência — a colisão é tratada como defeito da regra, não como identidade.

**Rejeitadas**: hash da evidência (muda com idioma e com valores encontrados); posição na lista (muda com a ordem);
tabela própria de ocorrências com id sequencial (exigiria casar ocorrências entre execuções do mesmo jeito, sem ganho);
reaproveitar `audit_relation::key` (frágil, e a Spec 003 está fora de escopo).

## R3 — Ausência não é correção

Uma ocorrência problemática anterior ausente (ou conforme) na nova execução é **resolvida** só se: (a) a regra
executou (nenhum `_rule` `ERRO`/`NAO_VERIFICAVEL` na nova execução e a ocorrência não está `ERRO`/`NAO_VERIFICAVEL`);
(b) a entidade existe (para `cm:` o `cmid` está no inventário da nova execução; para `sec:` o número da seção existe);
(c) as impressões digitais da regra são iguais (R4). Caso contrário: **não verificável agora**, **recurso
removido/substituído** ou **não comparável**. Uma regra com qualquer resultado `ERRO`/`NAO_VERIFICAVEL` no nível da sala
é tratada como não verificada por inteiro naquela execução (ex.: CL-12 sem o item de total). Por isso a execução guarda o inventário de entidades (cmid → tipo +
nome normalizado; números de seção).

**Substituição**: atividade removida X e atividade nova Y do mesmo tipo e mesmo nome normalizado, únicas nos dois
lados (critério do passe 3 do matcher da Spec 003, reaproveitado de `matcher::key_name`). Se a ocorrência de X
reaparece em Y como problema → "recurso substituído" (e não "nova" em Y).

## R4 — Mudança de política ou de regra

**Decisão**: impressão digital por regra em cada execução: `p` = hash dos valores das chaves de política que a regra
declara (`POLICY_KEYS`, mais `reoffer_year` para regras `OFFER_CYCLE`); `r` = hash do arquivo-fonte da classe da
regra. Se `p` ou `r` diferem entre as execuções, ocorrências que surgem/somem naquela regra são "não comparáveis"
(`policy_changed` / `rule_changed`); ocorrências problemáticas nas duas continuam "persistentes".

**Evidência de risco**: CL-11 lê `required_feedback_profile`, `required_certificate_module` e
`licence_certificate_name` via `helpers::certs`; CL-23 declara só parte das chaves que `helpers::certs` lê. Declaração
incompleta = mudança de política classificada como "nova". **Mitigação**: teste de dependência que perturba cada chave
da política nos cursos de referência e exige que toda regra cujo resultado mude declare a chave (corrige as
declarações incompletas encontradas).

**Rejeitadas**: impressão digital global da política (uma mudança de nome tornaria toda a evolução não comparável);
versão manual por regra (depende de disciplina humana; o hash do código é automático e conservador — um ajuste
cosmético marca a regra como alterada, o que é o lado seguro). Após a revisão independente, `r` inclui também o código
compartilhado por todas as regras (`snapshot`, `helpers`, `base`, `status`): se o coletor muda, as ocorrências que
surgem ou somem ficam "não comparáveis" em todas as regras. O motor (`engine.php`) não entra (muda a cada versão); a
página avisa quando as versões do plugin diferem.

`reference_timestamp` não entra na impressão digital da política (é o relógio da execução); o `reoffer_year` resolvido
entra, porque muda resultados das regras do ciclo de oferta.

## R5 — Persistência mínima

Tabelas (XMLDB/DML):

1. `local_mooc_audit_policy` — conteúdo da política por impressão digital (única). Evita repetir ~2 KB por execução.
2. `local_mooc_audit_run` — uma linha por execução; relatório JSON (contrato 1.0, sem snapshot) em `report`;
   impressões digitais por regra e inventário em `details`; resumo e evolução (contagens) em colunas próprias.
3. `local_mooc_audit_batch` — pedido de lote: solicitante, nome, critérios, salas recusadas/reaproveitadas. Andamento e
   término são **derivados** das execuções (`run.batchid`), não repetidos.

**Rejeitadas**: tabela de ocorrências (uma linha por resultado: ~400 linhas por sala grande por execução, sem consulta
que a exija — o painel usa contagens guardadas na execução e a evolução detalhada é calculada ao abrir a página a
partir de dois relatórios); compressão gzip/base64 do relatório (opaca ao privacy export e à depuração; o PostgreSQL já
comprime `text` grande via TOAST; o tamanho real é medido em `results.md`); guardar o snapshot (contém textos de
conteúdo e é muito maior).

## R6 — Execução em segundo plano

**Decisão**: uma `adhoc_task` (`\local_mooc_audit\task\audit_course`) por sala, `customdata = {runid}`,
`set_userid(solicitante)`. Concorrência: `get_default_concurrency_limit()` retorna a configuração
`batch_concurrency` (padrão 2); `$CFG->task_concurrency_limit['\local_mooc_audit\task\audit_course']` continua
sobrepondo (API nativa do Moodle 4.5, `lib/classes/task/adhoc_task.php:135-157`).

Idempotência e interrupção:
- trava por sala (`\core\lock\lock_config::get_lock_factory('local_mooc_audit')`, recurso `course_<id>`, espera 0):
  duas tarefas em segundo plano da mesma sala nunca auditam ao mesmo tempo (a auditoria síncrona da página não
  usa a trava: é outro pedido, somente leitura, com linha própria); quem não obtém a trava lança exceção e o Moodle
  reagenda a tarefa (retentativa nativa);
- a tarefa só age se a execução está `queued` ou `running`; estados finais são ignorados (entrega duplicada);
- `running` com a trava livre = processo anterior morreu: incrementa `attempts` e refaz; após 3 tentativas, falha
  `interrupted`;
- exceções dentro da auditoria são capturadas: a execução termina `failed` com a mensagem e a tarefa **não** é
  reagendada (falha determinística não deve repetir sem fim); o gestor usa "Reexecutar falhas".

**Duplicação**: ao solicitar, uma sala com execução `queued`/`running` não recebe outra; o lote registra a execução
reaproveitada.

**Execuções presas**: a tarefa agendada diária marca `failed` (`stale`) execuções `queued`/`running` há mais de 24 h
cuja tarefa ad hoc não existe mais.

**Rejeitadas**: uma tarefa por lote processando salas em série (uma sala lenta atrasa todas, sem paralelismo
controlado e sem retentativa por sala); Redis/RabbitMQ/Celery (constituição IX; a fila do Moodle basta para dezenas
ou centenas de salas).

## R7 — Segurança das tarefas assíncronas

A tarefa roda com o usuário solicitante (`cron_setup_user` pelo gerenciador de tarefas). Antes de auditar, confere
`has_capability('local/mooc_audit:batchaudit', sistema, solicitante)` e `has_capability('local/mooc_audit:audit',
curso, solicitante)`. Sem permissão → `failed` `permission`. Solicitante removido/suspenso → mesma regra (`has_capability`
falha). Execuções da página (síncronas) já passaram por `require_capability` no próprio pedido.

## R8 — Capacidades

- Histórico da sala: `local/mooc_audit:audit` (curso) — quem audita vê o histórico; não amplia o acesso do professor
  editor a nada que ele já não visse recalculando a auditoria.
- `local/mooc_audit:viewdashboard` (sistema, `read`, gerente): painel e páginas de lote.
- `local/mooc_audit:batchaudit` (sistema, `write`, gerente, sem `riskbitmask` — não altera salas; o custo é carga):
  solicitar lote e reexecutar falhas.
- Painel e lotes filtram as salas por `local/mooc_audit:audit` do usuário (respeita proibições por categoria/curso).

Duas capacidades em vez de uma permitem um papel de acompanhamento sem poder disparar carga no servidor.

## R9 — Registro das auditorias da página

`index.php` passa a registrar a execução (origem `page`), de forma síncrona, pelo mesmo `recorder` das tarefas. GET
com escrita é aceitável aqui: a página **é** a solicitação de auditoria (mesma semântica dos eventos de visualização
do Moodle). Ruído (recarregamentos) é controlado pela retenção; registrar só o botão é a alternativa **[validar]**.
A reauditoria do `verifier` da Spec 002 não passa pelo `recorder`.

## R10 — Painel sem métricas enganosas

Situação por sala (exclusiva), derivada da última execução: `not_audited` → `incomplete` (última execução `failed` ou
`partial`) → `blocking` → `needs_fix` → `none_detected`. "Desatualizada" é um indicador adicional (idade > prazo,
política diferente da vigente, versão do plugin diferente). Rótulos: "Sem inconformidades detectadas", "Requer
correções", "Com bloqueantes", "Auditoria incompleta", "Não auditada". Sem percentuais. Aviso fixo de escopo.

Desempenho: uma consulta pega a última execução de cada sala do escopo (`MAX(id)` agrupado por curso, junção), sem
ler os relatórios (colunas `overall`, `summary`, `evolution`).

## R11 — Retenção e privacidade

Tarefa agendada `cleanup_history` (diária): remove execuções finais mais antigas que `history_retention_days`
(padrão 365, limite 30–1825), exceto a última execução válida de cada sala existente; remove lotes sem execuções e
políticas sem referência; marca execuções presas. Privacy provider: novas tabelas declaradas; `userid` de execução
(contexto do curso) e de lote (contexto de sistema) exportados e anonimizados.

## R12 — Relação com a trilha de correções

A página de evolução consulta `local_mooc_audit_fix` por `courseid` e `timecreated` entre `timefinished` da execução
base e `timestarted` da execução atual. Exibe regra, atividade, campo, valor anterior → novo, resultado. Texto fixo:
"correções registradas no período; a relação com as ocorrências não é verificada". Nada é copiado.

## R13 — Medição de desempenho

Cenário: 40 salas sintéticas (A, B e variações via gerador) + sala de 151 módulos (`plugin-results/0.4.0/make151.php`).
Execução pela fila real (`admin/cli/adhoc_task.php --execute`), medindo por execução: duração (`timefinished -
timestarted`), pico de memória (`memory_get_peak_usage` registrado nos detalhes), consultas ao banco
(`$DB->perf_get_reads()`/`perf_get_writes()`), tamanho do relatório guardado (`octet_length`). Resultado em
`results.md`.
