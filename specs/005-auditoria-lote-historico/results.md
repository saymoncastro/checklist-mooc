# Resultados da Spec 005 — Auditoria em Lote, Histórico e Acompanhamento (plugin 0.5.0)

**Data:** 2026-10-29/30 · **Branch:** `claude/spec-005-auditoria-lote-948e09` (a partir de `main` 037fd45; PRs #1–#4
mesclados, nenhum aberto) · **Ambiente:** site isolado `moocaudit-s5-web` (porta 8095, Moodle 4.5.15, PHP 8.3,
PostgreSQL 17) · **Evidências:** `plugin-results/0.5.0/`.

## 1. Entregáveis

| # | Entregável | Onde |
|---|---|---|
| 1 | Spec 005 pelo Spec Kit | [spec.md](spec.md) (decisões em Clarifications), [checklists/requirements.md](checklists/requirements.md), [analysis.md](analysis.md) |
| 2 | Plano técnico e modelo de dados | [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/pages-and-cli.md](contracts/pages-and-cli.md), [tasks.md](tasks.md) |
| 3 | Persistência do histórico | tabelas `local_mooc_audit_run`, `_policy`, `_batch` (upgrade 2026103000); `classes/local/history/{run_store,policy_store,recorder}.php` |
| 4 | Identificação estável das ocorrências | `check`/`subject` nas 29 regras; `engine::occurrence()`; `tests/occurrence_test.php`, `tests/policy_dependency_test.php` |
| 5 | Comparação entre auditorias sucessivas | `classes/local/history/evolution.php`; `tests/local/history/evolution_test.php`, `recorder_test.php` |
| 6 | Execução em lote | `classes/task/audit_course.php`, `classes/local/history/batch_manager.php`, `batch.php`, `cli/batch.php` |
| 7 | Interface central | `dashboard.php` (*Administração do site > Cursos > Acompanhamento das auditorias*) |
| 8 | Histórico no relatório individual | quadro "Desde a auditoria anterior" em `index.php`; `history.php` |
| 9 | Controle de permissões | capacidades `viewdashboard`/`batchaudit`; `classes/local/history/access.php`; `tests/history_access_test.php` |
| 10 | Testes automatizados e evidências | §3 |
| 11 | Medições de desempenho | §4 e `plugin-results/0.5.0/` |
| 12 | Revisão independente de código e segurança | §5 |
| 13 | Documentação operacional | README do plugin (seção "Histórico, lote e acompanhamento"), [quickstart.md](quickstart.md), `moodle-env/README.md`, `DECISOES-INSTITUCIONAIS-CHECKLIST.md` (decisão 12) |
| 14 | PR para revisão | aberto ao final (sem merge) |

## 2. O que foi construído

- **Histórico imutável.** Cada auditoria da página, do lote ou da CLI (`--record`) vira uma execução com sala, datas
  (criação/início/término), solicitante, origem, versão do plugin e do contrato, política (versão + impressão digital
  do conteúdo, guardada uma vez), ano de reoferta, estado (`queued/running/complete/partial/failed`), resultado geral,
  resumo por estado e gravidade, relatório (contrato 1.0 + `occurrence`), impressões digitais por regra, inventário de
  atividades/seções, métricas e erros/limitações. Estados finais nunca são reescritos (UPDATE condicional; teste).
- **Chave de ocorrência** `regra|entidade|verificação[|discriminador]`, declarada pela regra, igual para conforme e não
  conforme; entidade = `cm:<cmid>`, `sec:<id da seção>` ou `course`. Nunca texto, idioma ou posição.
- **Evolução** em oito classes: nova, persistente, resolvida, estado/gravidade alterados, não verificável agora, não
  comparável (política, código da regra ou do coletor mudou; base não verificou), recurso removido, recurso
  substituído. As correções assistidas do período aparecem ao lado, lidas da trilha da Spec 002, com aviso explícito
  de que a relação não é verificada.
- **Lote** por categoria (± subcategorias), ids, ano de início ou seleção no painel; prévia com recusas e motivo;
  uma tarefa ad hoc por sala em nome do solicitante; reconferência de permissão na execução; trava por sala;
  concorrência limitada; retomada até 3 tentativas; falhas isoladas; "Reexecutar falhas".
- **Painel** com situação exclusiva por sala, desatualização com motivo, filtros (categoria, texto, situação,
  gravidade, período), evolução somada, lotes recentes, salas removidas com histórico, aviso de escopo e nenhum
  "aprovado"/percentual.
- **Retenção** diária configurável (365 dias por padrão, 30–1825), preservando a última execução válida de cada sala
  existente; privacy provider para execuções (curso; sistema se a sala foi removida) e lotes (sistema).

## 3. Testes

| Suíte | Resultado |
|---|---|
| PHPUnit (`local_mooc_audit_testsuite`) | **212 testes** (169 da linha de base + 43 novos), ver §3.1 — todos passando na execução final |
| Behat (`@local_mooc_audit`) | **13 cenários, 193 passos**: 10 das Specs 001–003 (regressão) + 3 novos (`history.feature`), com verificação de acessibilidade (axe) |
| PHPCS `moodle` | 0 erros, 0 avisos |
| Caracterização A/B (Spec 001) | inalterada (`expected_A/B.json` sem regravação) |

### 3.1 Cenários pedidos × testes

| Cenário | Teste |
|---|---|
| Primeira auditoria | `recorder_test::test_first_and_unchanged_second_audit`; Behat "audits twice" |
| Segunda sem alterações / auditoria sem modificação da sala | idem (todas persistentes, política guardada uma vez); `readonly_test::test_recorded_and_batch_audits_do_not_modify_the_course` |
| Resolução de uma inconformidade | `recorder_test::test_resolution_and_new_problem`; `evolution_test::test_request_example_one_resolved_two_persistent` (exemplo do pedido: 1 resolvida, 2 persistentes) |
| Nova inconformidade | `recorder_test::test_resolution_and_new_problem`; `evolution_test::test_new_and_changed` |
| Mudança de gravidade | `evolution_test::test_new_and_changed` |
| Mudança de política | `recorder_test::test_policy_change_is_not_new`; `evolution_test::test_policy_change_only_affects_its_rule`; `policy_dependency_test` |
| Erro em uma regra | `recorder_test::test_partial_and_failed_runs` (execução parcial); `evolution_test::test_error_and_unverifiable_are_not_resolved` |
| Regra que deixa de ser verificável | `evolution_test::test_error_and_unverifiable_are_not_resolved`, `test_partially_unverified_rule` |
| Curso removido ou indisponível | `recorder_test::test_partial_and_failed_runs`; `audit_course_test::test_partial_failure_and_retry`; `history_access_test` (histórico de sala removida) |
| Lote com cursos conformes e não conformes | `audit_course_test::test_batch_conformant_and_non_conformant`; Behat "queues a batch" |
| Lote com falha parcial | `audit_course_test::test_partial_failure_and_retry` |
| Tarefa repetida ou interrompida | `audit_course_test::test_no_duplicates`, `test_interrupted_runs`, `test_dead_pending_runs_are_not_reused` |
| Usuário sem permissão | `audit_course_test::test_permission_revoked_before_execution`, `test_selection_modes_and_limits`; `history_access_test`; Behat (professor sem painel) |
| Correção assistida relacionada a auditoria posterior | `recorder_test::test_corrections_between_runs` (listada, não atribuída) |
| Várias ocorrências no mesmo local | `occurrence_test::test_several_findings_at_one_location`; `evolution_test::test_several_occurrences_same_location` |
| Seção inserida / ordem por tempo | `recorder_test::test_section_insertion_keeps_keys`, `test_base_is_chosen_by_time` |
| Retenção e privacidade | `cleanup_history_test`; `privacy/provider_test` (3 testes) |
| Painel sem métricas enganosas | `dashboard_test::test_no_misleading_wording`, `test_situations_filters_and_counters` |

## 4. Desempenho (laboratório: Colima 4 CPU / 6 GB, arm64)

| Medição | Resultado | Meta |
|---|---|---|
| Lote serial de 100 salas A/B (`cli/batch.php --run`) | 2,9 s no total; **0,024 s/sala em média**, máx. 0,12 s (primeira, cache frio); ~103 leituras de banco por auditoria; pico do processo 34 MB | ≤ 5 s/sala; < 256 MB |
| Sala de 151 módulos (e a cópia importada) | **0,14 s** e 0,07 s; ~420 leituras; pico 29 MB; relatório 41 kB | ≤ 15 s |
| Lote de 100 salas pela fila real (3 executores, limite 2) | 100 concluídas em ~2 s entre o primeiro início e o último término; nenhuma tentativa duplicada | — |
| Painel com 104 salas (`dashboard::build` + render) | 72 ms a frio, 8 ms a quente; página completa 0,74 s até o primeiro byte | ≤ 3 s |
| Armazenamento | relatório médio 29 kB (máx. 40 kB) + detalhes 2 kB; tabela de execuções com 205 linhas = 2,0 MB em disco (TOAST comprime) | — |

Projeção (estimativa, não medida): 100 salas auditadas semanalmente por um ano ≈ 5.200 execuções ≈ 50 MB; com
recarregamentos frequentes da página o volume cresce proporcionalmente (decisão 12.1). Arquivos:
`plugin-results/0.5.0/{lote100_serial.txt,lote100_fila.txt,sala151.txt,painel_e_armazenamento.txt}`; scripts
`make_lote.php` e `measure.php` (só laboratório). As salas sintéticas são pequenas; salas reais com muitos arquivos e
textos podem levar mais tempo (a 151 módulos é o pior caso conhecido).

## 5. Revisão independente de código e segurança

Feita por um agente revisor sem acesso às conclusões do implementador, somente leitura, sobre `git diff 037fd45..HEAD`.
**Nenhum defeito crítico de segurança**: todas as páginas novas checam capacidade, toda escrita exige `sesskey`, ids
de execução/base são conferidos contra o curso, nenhum SQL é montado com entrada do usuário, Mustache só usa chaves
triplas para HTML gerado no servidor, `install.xml` e `upgrade.php` coincidem.

| Achado | Severidade | Situação |
|---|---|---|
| Seções identificadas pelo número: inserir/mover seção gerava "resolvida" + "nova" falsas | alta | **Corrigido**: entidade `sec:<id>`; inventário por id; teste de inserção |
| CL-12 sem item de total: verificações dependentes sumiam e viravam "resolvidas" | alta | **Corrigido**: CL-12 emite `NAO_VERIFICAVEL` com a própria chave; regra com NV/ERRO no nível da sala = não verificada |
| Ordem por id em vez de tempo (lote enfileirado antes e terminado depois de uma auditoria da página) | média | **Corrigido**: base = última válida terminada antes do início; painel e retenção por término; teste |
| Lote reaproveitava execuções mortas; "reexecutar falhas" ignorava as reaproveitadas | média | **Corrigido**: reaproveita só fila com tarefa viva ou execução iniciada há < 15 min; reexecução inclui reaproveitadas; teste |
| Impressão digital da regra ignorava código compartilhado | média | **Corrigido**: inclui `snapshot`, `helpers`, `base`, `status` |
| CL-14 (e X-AV-01) colidiam em atividade com vários itens de nota | média | **Corrigido**: `subject = item:<itemnumber>` |
| Execuções de salas removidas fora do privacy provider | baixa/média | **Corrigido**: tratadas no contexto de sistema; teste |
| `finish()` podia reportar sucesso sem ter alterado | baixa | **Corrigido**: relê e confere o estado gravado |
| Trilha de correções visível a quem só tem `audit` | baixa | **Corrigido**: exibida só com `preparecorrection` |
| JSON com UTF-8 inválido podia gravar relatório vazio | risco | **Corrigido**: `JSON_INVALID_UTF8_SUBSTITUTE` + `JSON_THROW_ON_ERROR` |
| `cli/batch.php --run` executava tarefas de execuções reaproveitadas de outros lotes | risco | **Corrigido** |
| Cada abertura da página grava uma execução (volume; GET sem `sesskey`; o painel passa a mostrar "persistentes" após recarregar) | média | **Mantido como decisão institucional pendente (12.1)**; o impacto de CSRF é criar uma linha de histórico para quem já pode auditar |
| Contexto pré-carregado no painel/lote (N+1 de `has_capability`) | média | **Não alterado**: medido 72 ms para 104 salas; revisitar acima de ~1.000 salas |
| Página do lote mostra contagens incluindo salas fora do escopo do leitor; histórico de sala removida visível a todo `viewdashboard` | risco | **Aceito e documentado**: só gerentes têm a capacidade por padrão |
| Corrida da limpeza de políticas órfãs (> 1 dia) com execução em andamento | risco | Aceito (janela de segundos vs. 24 h) |

### 5.1 Segunda passada: code review do PR #5 (2026-10-30)

| Achado | Situação |
|---|---|
| Discriminadores com ids (`cm:<alvo>` de X-DEP-01/02, `enrol:<id>` de CL-08) não eram remapeados na substituição: alvo trocado por cópia idêntica gerava "resolvida + nova" | **Corrigido**: toda referência `cm:` da chave (entidade ou discriminador) é remapeada para a sucessora; alvo apagado = "recurso removido"; inscrição recriada = "removido + nova"; `details.enrols` passou a ser gravado; teste |
| Relatório guardado no histórico tinha "Executar novamente" e JSON apontando para `index.php` (gravava nova auditoria e devolvia o relatório de hoje) | **Corrigido**: relatório guardado sem botão de reexecução e com JSON da própria execução; teste |
| `moodle_exception('locktimeout')` sem string correspondente | **Corrigido**: string `error_lockbusy` do plugin; teste |
| "Auditadas" contava salas cujas execuções só falharam | **Corrigido**: só salas com relatório |
| Painel contava execuções mortas como "Na fila ou em execução" | **Corrigido**: mesma regra de "execução viva" do lote (`run_store::alive_pending`) |
| Painel e lote verificavam a capacidade sala a sala sem pré-carregar contextos | **Corrigido**: `get_user_capability_course()` (`access::auditable_course_ids`) |
| Andamento dos lotes lia as colunas JSON de todas as execuções | **Corrigido**: leitura só de id/curso/estado e uma consulta para as reaproveitadas |
| CL-12 repetia três vezes o mesmo "não verificável" | **Corrigido**: um resultado no nível da sala (o tratamento genérico da evolução cobre o caso) |
| `run_store::pending()` sem uso | **Removido** |

## 6. Limitações

- Registrar toda abertura da página é provisório (decisão 12.1); recarregar a página sem mudanças gera execuções com
  tudo "persistente" e o painel mostra a evolução mais recente, não a "última com mudança".
- A impressão digital por regra não cobre o motor (`engine.php`); a evolução avisa quando a versão do plugin difere.
- Uma ocorrência pode sumir porque a atividade saiu do escopo da regra (ex.: atividade deixou de ter nota) e será
  classificada como "resolvida": a regra executou e a entidade existe. A página mostra o estado "Antes/Agora" para
  conferência.
- Excluir o módulo proibido do CL-18 (que é a correção) aparece como "recurso removido", não "resolvida".
- Recriar um método de inscrição gera "recurso removido" + "nova" (não há correspondência entre instâncias de
  inscrição); execuções gravadas antes desta versão não têm a lista de inscrições e não fazem essa distinção.
- Gerentes só de categoria não acessam painel/lotes (constituição VIII; decisão 12.4).
- A comparação entre ofertas (Spec 003) continua com sua chave de local; adotar a chave de ocorrência nela é melhoria
  futura.
- Relatórios guardados são no idioma das evidências (português) e dos títulos no momento da execução.

## 7. Respostas às perguntas finais

**É possível acompanhar a resolução das inconformidades de forma confiável?** Sim, para as 29 regras implementadas e
dentro dos limites do §6. A resolução só é declarada quando a regra executou normalmente, a atividade/seção ainda
existe e nem a política lida pela regra nem o código mudaram; os demais casos têm classe própria. O cenário do pedido
(1 resolvida, 2 persistentes) é teste automatizado.

**Como o sistema identifica o mesmo problema em duas execuções?** Pela chave `regra|entidade|verificação[|discriminador]`:
entidade = `cmid`, id da seção ou a sala; verificação = código estável declarado pela regra (o mesmo para conforme e
não conforme); discriminador = dado semântico quando a verificação se repete (título do bloco exigido, link, id da
inscrição, atividade alvo, caminho do arquivo, item de nota). Testes garantem unicidade e independência de ordem e
idioma.

**Como evita falsos problemas novos quando a política muda?** Cada execução guarda, por regra, o hash dos valores de
política que a regra lê (mais o ano de reoferta nas regras do ciclo) e do código da regra e do coletor. Se mudou, o que
surge ou some naquela regra é "não comparável" com o motivo; as demais regras seguem normais. Um teste perturba cada
chave da política e exige a declaração — ele encontrou e corrigiu duas declarações incompletas (CL-22, CL-23).

**Quantos cursos o laboratório consegue auditar em lote e com que consumo?** 100 salas sintéticas em ~3 s em série
(0,024 s/sala, 34 MB de pico) e em ~2 s pela fila com concorrência 2; a sala de 151 módulos em 0,14 s; ~10 kB em disco
por execução. Salas reais devem ser mais lentas que as sintéticas; mesmo 50× mais lentas caberiam com folga numa
janela de cron.

**O histórico registra adequadamente falhas e auditorias incompletas?** Sim: `failed` (sala indisponível, permissão
revogada, exceção, interrompida, abandonada) com código e mensagem, sem relatório; `partial` quando uma regra termina
em `ERRO`, com a regra listada; avisos de coleta e de política como limitações. Falhas não servem de base de
comparação.

**As permissões impedem acesso indevido?** Sim, nos testes: histórico exige `audit` no curso (id de execução de outro
curso é recusado); painel e lote exigem `viewdashboard`/`batchaudit` (só gerentes por padrão) e listam só salas
auditáveis pelo usuário; a tarefa reconfere as permissões ao executar; professores editores não ganharam capacidade.

**O painel oferece valor operacional concreto para a equipe do Cefor?** Responde, sem abrir as salas, quais têm
bloqueantes, quais requerem correções, quais não foram auditadas, quais estão desatualizadas e quantos problemas
foram resolvidos, são novos ou persistem; filtra e dispara lotes. O valor real depende da homologação com a equipe de
reofertas (SC-008) e da decisão 12.1.

**A solução continua independente do MOOC Admin?** Sim: usa apenas o Moodle (tabelas próprias, fila de tarefas,
travas, capacidades); nenhuma chamada externa.

**Quais decisões institucionais ainda são necessárias?** Decisão 12 em `DECISOES-INSTITUCIONAIS-CHECKLIST.md`:
registrar toda abertura da página ou só o botão; prazo de retenção e destino do histórico de salas removidas; prazo de
desatualização; acesso de gerentes de categoria; concorrência e tamanho de lote em produção; critério de ciclo. Seguem
pendentes a decisão 10 (nomes canônicos) e a validação do CL-14.

**A Spec 005 está pronta para homologação?** Está pronta para **homologação técnica no laboratório** (testes, Behat,
PHPCS, revisão e medições concluídos). Não está pronta para produção: faltam a decisão 12, o roteiro de homologação
com a equipe de reofertas ([quickstart.md](quickstart.md) §2) e uma medição em cópia do Moodle institucional com salas
reais.
