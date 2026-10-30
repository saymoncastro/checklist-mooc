# Relatório A — Conformidade técnica Moodle

**Auditor MOOC (`local_mooc_audit`) · revisão independente · 2026-10-29**

Escopo: estado de `main` em `8c1fe03` (PR #6 mesclado). Plugin `0.5.0`, versão `2026103001`, maturidade `ALPHA`,
`requires = 2024100700`, `supported = [405, 405]`. Nenhum código foi alterado nesta revisão.

---

## 1. Escopo e metodologia

| Etapa | O que foi feito |
|---|---|
| Estado real | `git log origin/main`, `gh pr list` (PRs #1–#6 mesclados, nenhum aberto), leitura de `version.php`, `tasks.md` de cada spec |
| Documentação do projeto | Constituição v1.0.0, specs 001–005, `DECISOES-INSTITUCIONAIS-CHECKLIST.md`, `docs/`, `results.md` da spec 005, `moodle-env/README.md` |
| Código | Leitura integral das páginas (`index.php`, `correction.php`, `compare.php`, `history.php`, `dashboard.php`, `batch.php`), `db/`, `lib.php`, `settings.php`, privacy provider, tarefas, executor e estratégias de correção, `history/*`, `output/report.php` e templates; leitura dirigida de regras, `snapshot`, comparador |
| Ferramentas | PHPCS `moodle` e `moodle-extra` (moodle-cs 3.7.0), `php -l`, PHPUnit, Behat com axe, axe‑core embutido no Moodle (`lib/behat/axe`) com `best-practice`, contagem aproximada de complexidade ciclomática, leitura do log do CI do GitHub |
| Execução | Laboratório isolado `moocaudit-s5-web` (Moodle 4.5.15, PHP 8.3.35, PostgreSQL 17; plugin idêntico ao `main`, conferido por `diff -rq`) |
| Teste de fluxo | Uma correção assistida executada na sala sintética `LOTE_B_002` (curso 5) do laboratório s5, para comprovar o achado A‑01; valor restaurado em seguida (tentativas = 1). A linha da trilha (`local_mooc_audit_fix.id = 1`) ficou no banco do laboratório como evidência |

Classificação usada: **P0** crítico, **P1** alto, **P2** médio, **P3** baixo. Cada achado indica se é **comprovado**
(observado em execução ou demonstrado por leitura inequívoca do código), **hipótese** (risco plausível não reproduzido)
ou **oportunidade**.

## 2. Referências oficiais consultadas

Acesso em 2026-10-29. As páginas foram lidas por ferramenta de busca/leitura que devolve resumos; os pontos usados como
critério foram conferidos em mais de uma página quando possível. Classes: (1) requisito técnico/segurança; (2)
recomendação oficial; (3) exigência só do diretório de plugins (não aplicável: o plugin é institucional).

| Tema | URL | Uso nesta revisão |
|---|---|---|
| Checklist de contribuição (página marcada como legada) | https://moodledev.io/general/community/plugincontribution/checklist | (1) segurança de entrada/SQL/sesskey; (2) strings, MySQL+PostgreSQL; (3) itens de publicação, ignorados |
| Coding style | https://moodledev.io/general/development/policies/codingstyle | (1) namespaces, PHPDoc, `config.php` via `__DIR__`; (2) PSR‑12, type hints |
| Segurança / XSS | https://moodledev.io/general/development/policies/security · …/security/crosssite-scripting | (1) `require_login`, `require_capability`, `PARAM_*`, `sesskey`, escape; (2) "every script should log an event" |
| Riscos de capacidades | https://docs.moodle.org/dev/Hardening_new_Roles_system | (2) significado de `RISK_*` (página migrada, possivelmente desatualizada) |
| Access API / common files | https://moodledev.io/docs/4.5/apis/subsystems/access · https://moodledev.io/docs/4.5/apis/commonfiles | (1) archetypes só valem para capacidades novas; (2) mudar permissões de sites existentes "not recommended" |
| Privacy API | https://moodledev.io/docs/4.5/apis/subsystems/privacy | (1) toda tabela com `userid` declarada, exportável e apagável; `core_userlist_provider` |
| DML / DDL / XMLDB / upgrade | https://moodledev.io/docs/4.5/apis/core/dml · …/dml/ddl · https://moodledev.io/docs/4.5/guides/upgrade | (1) placeholders, savepoints; (2) declarar FKs, recordsets, transações |
| Events API | https://docs.moodle.org/dev/Events_API | (1) propriedades obrigatórias; (2) eventos por ação |
| Task API (4.5) | https://moodledev.io/docs/4.5/apis/subsystems/task · …/task/adhoc · https://docs.moodle.org/405/en/Cron | (1) adhoc com `set_userid`; (2) retentativas, concorrência |
| Output / templates | https://moodledev.io/docs/4.5/apis/subsystems/output · https://moodledev.io/docs/4.5/guides/templates | (2) renderer próprio, `templatable`, contexto de exemplo |
| Forms | https://moodledev.io/docs/4.5/apis/subsystems/form | (1) `moodleform`, `setType` |
| Report builder | https://moodledev.io/docs/4.5/apis/core/reportbuilder | Oportunidade apenas; a página não o torna obrigatório |
| Testes / acessibilidade | https://moodledev.io/general/development/policies/accessibility · …/accessibility/testing · https://moodlehq.github.io/moodle-plugin-ci/CLI.html | (1) cor não pode ser o único meio; WCAG 2.2 AA; (2) axe com `best-practice`; testes automáticos cobrem 30–50% dos problemas |
| moodle-cs | https://github.com/moodlehq/moodle-cs | (2) `moodle-extra` = `moodle` + boas práticas recomendadas |
| Moodle 4.5 | https://moodledev.io/general/releases/4.5 · https://moodledev.io/general/releases | (1) PHP 8.1–8.3; PostgreSQL 13+, MySQL 8.0+, MariaDB 10.6.7+; suporte geral até 06/10/2025, **segurança até 04/10/2027** |
| Navegação / plugins locais | https://moodledev.io/docs/4.5/apis/core/navigation · https://moodledev.io/docs/4.5/apis/plugintypes/local | (1) `extend_navigation_course`; `settings.php` sem consultas; (2) `lib.php` mínimo |
| Web services (spec 004) | https://moodledev.io/docs/4.5/apis/subsystems/external/writing-a-service | Só para registro: nada implementado |

Não verificado: lista exata de regras do `moodle-extra`; recomendação explícita de eventos de visualização para
relatórios; versão do Bootstrap do 4.5 (o código usa classes Bootstrap 4, compatíveis com o tema Boost do 4.5).

## 3. Estado real × documentação

| Item | Documentação | Código / comportamento observado | Divergência |
|---|---|---|---|
| Specs 001, 002, 003, 005 | Implementadas | Implementadas; 214 PHPUnit e 13 Behat passam; CI verde em `main` | — |
| Spec 004 (API externa) | "Draft — nada implementado" | Nenhum `db/services.php` nem classe `external`; 41 tarefas abertas | Coerente. **Não considerar concluída** |
| Contagem de testes | README raiz: "75 testes PHPUnit, 2 cenários Behat"; README do plugin abre com "Estado (0.2.0…)" | 214 testes / 3.417 asserções; 13 cenários / 210 passos | Documentação desatualizada (A‑15) |
| `specs/003/tasks.md` T039 "Abrir PR" | Pendente | PR #4 mesclado | Tarefa não marcada |
| Capacidades com `riskbitmask` | Constituição VIII: "MUST declarar `riskbitmask`" | Só `applycorrection` declara (`RISK_CONFIG`) | Conflito constituição × código (A‑07) |
| "Nenhuma string de interface fixa no código" | Constituição, restrições técnicas | Evidências, títulos e orientações das 29 regras estão em português dentro das classes | Divergência (A‑09) |
| Correção "seguida de nova auditoria" | Constituição IV; spec 002; README (limitação declarada) | Reauditoria feita, mas **não gravada**; a página do relatório mostra o resultado anterior com aviso de sala alterada | Limitação documentada, com efeito de UX (A‑01) |
| Bancos suportados | Constituição: PostgreSQL e MySQL/MariaDB | CI e laboratório só com PostgreSQL | Não verificado em MySQL/MariaDB (A‑06) |

**Funcionalidades experimentais ou dependentes de decisão institucional**: regras ligadas à decisão 10 (nomes
canônicos, pendente); marco do CL‑14 (`[validar]`); heurísticas de enunciado (CL‑13 enunciado, X‑AV‑01); correção
executável validada só para questionários; comparação com pares manuais por URL (sem persistência); toda a Spec 004.

## 4. Arquitetura observada

```
Navegação do curso (lib.php) ─► index.php ─┬─ GET: run_store (último resultado gravado)
                                           └─ POST+sesskey: recorder::record_now ─► engine ─► snapshot + 29 regras (catalog)
Relatório (output/report + templates) ──► correction.php ─► planner ─► strategy (simulate) ─► proposal (fingerprint)
                                                         └► executor (lock, transação, releitura) ─► trail + evento
                                                             └► reaudit = engine::audit (NÃO grava no histórico)
compare.php ─► comparator ─► matcher + differ + audit_relation (classes próprias da spec 003)
history.php ─► run_store + evolution (classes próprias da spec 005)
dashboard.php / batch.php ─► dashboard, batch_manager ─► task\audit_course (adhoc) ─► recorder
cleanup_history (agendada) ─► retenção de execuções, lotes e políticas
```

| Camada | Componentes | Responsabilidade | Observação |
|---|---|---|---|
| Coleta | `snapshot` (491 linhas) | Lê a sala por APIs (`get_fast_modinfo`, grades, enrol, blocks, files) e DML com lista branca | Justificativas no README do plugin |
| Regras | `local/rule/*` (29 classes) + `base`, `catalog`, `policy` | Avaliar e devolver resultados com chave estável | Boa extensibilidade: uma classe + teste + entrada no catálogo |
| Motor | `engine` | Isolar exceções por regra (`ERRO`), resumo, contrato JSON 1.0 | Contrato com schema versionado |
| Correção | `correction/*` | Prévia, simulação, impressão digital, execução controlada | Única escrita em dados de curso; desenho sólido |
| Comparação | `comparator`, `comparison/*` | Pareamento de atividades, diferenças, relação entre auditorias | `differ.php` (873 linhas) é o maior arquivo |
| Histórico/lote | `history/*`, `task/*` | Persistência, evolução, lote em segundo plano, painel, retenção | 3 tabelas novas |
| Apresentação | `output/*`, 12 templates | Renderables + Mustache | Sem JavaScript; tabelas HTML simples |

**Complexidade** (contagem aproximada; evidência em `evidencias/complexidade-ciclomatica.txt`): 332 funções; 60 com
complexidade > 10, 10 com > 20. As maiores são `evolution::compare` (41), `audit_relation::relate` (37),
`dashboard::build` (36) e `snapshot::build` (33, 216 linhas). Os números são altos, mas localizados e cobertos por
testes. Não justificam refatoração por si mesmos.

**Redundância conceitual a registrar**: `evolution` (spec 005, mesma sala em datas diferentes) e `audit_relation`
(spec 003, origem × destino) classificam problemas com vocabulários e chaves diferentes: 8 classes contra 9, chave
estável contra chave de local sem ids. A separação tem justificativa técnica (comparar salas diferentes exige outra
chave), mas o usuário vê a palavra "persistente" com dois significados (ver Relatório B, B‑14). **Não recomendo unificar
o código.** Recomendo apenas alinhar o vocabulário exibido.

## 5. Resultados das ferramentas

| Ferramenta | Ambiente | Resultado | Evidência |
|---|---|---|---|
| `php -l` | s5, 172 arquivos | Sem erros | CI e execução local |
| PHPCS `moodle` | moodle-cs 3.7.0 | **0 erros, 0 avisos** | `evidencias/phpcs-moodle.txt` |
| PHPCS `moodle-extra` | moodle-cs 3.7.0 | 0 erros, **414 avisos**, todos de uma única regra (PSR‑12 "constant visibility not found") | `evidencias/phpcs-moodle-extra-resumo.txt` |
| PHPUnit | s5 | **OK, 214 testes, 3.417 asserções** (2 min 30 s) | `evidencias/phpunit-s5.txt` |
| Behat (Chrome + axe WCAG 2.x A/AA) | s5 + Selenium | **13 cenários, 210 passos aprovados** | `evidencias/behat-s5.txt` |
| axe‑core do Moodle com `best-practice`, em páginas reais | s5, dados do laboratório | Relatório: só `heading-order`. Comparação: `heading-order` e `empty-table-header`. **Painel: `color-contrast` (grave, 50 nós) e `target-size`** | Relatório B, B‑19 |
| moodle-plugin-ci no GitHub | PHP 8.3, MOODLE_405_STABLE, pgsql | Último run em `main` verde: phplint, phpcs, phpdoc, validate, savepoints, mustache, phpunit, behat | run 40970587772 |
| phpmd / phpstan | — | Não executados: indisponíveis no contêiner, e o phpmd do moodle-plugin-ci não está no workflow | Limitação |
| Plugin Check (`local_moodlecheck`) | — | Não instalado no laboratório. O passo `phpdoc` do CI cobre a mesma verificação | Limitação |
| grunt (AMD/CSS) | — | Não aplicável: o plugin não tem JavaScript nem CSS próprios | — |

Leitura: a conformidade com o padrão de código do Moodle é alta e verificada continuamente. O `moodle-extra` só
acrescenta uma regra cosmética. Ausência de avisos no PHPCS não prova conformidade funcional; os achados abaixo vêm de
leitura e execução.

## 6. Achados priorizados

### A‑01 — P2 (revisado; era P1) — A reauditoria após a correção não é gravada: o relatório continua mostrando a inconformidade corrigida

- **Dimensão:** técnica / produto · **Confiança:** alta · **Situação:** **comprovado em execução**; **limitação já documentada** no README do plugin ("Limitações conhecidas")
- **Errata (2026-10-30):** a primeira versão deste relatório classificou o achado como P1 e não registrou que o README já o declarava, com mitigação: a correção incrementa `course.cacherev` e, para execuções gravadas a partir da versão 2026103001, a página avisa "a sala foi alterada depois desta auditoria". No laboratório o aviso não apareceu só porque a execução era anterior à coluna. Com a mitigação, o achado é P2: a tarefa não fica impedida, mas o relatório continua listando o problema corrigido, com o botão de proposta, ao lado do aviso.
- **Local:** `classes/local/correction/executor.php:180-181` (`reaudit()` chama `engine::audit`, não `recorder`); `index.php:101`
  (`run_store::latest_valid` — a página mostra só o último resultado gravado, decisão 12.1)
- **Evidência:** sala `LOTE_B_002` (s5), CL‑13 em "Questionário 1" (cmid 58). A tela de resultado disse "Correção
  aplicada e confirmada: a reauditoria não aponta mais a inconformidade" (captura `06`). Ao voltar ao relatório,
  CL‑13 continuou listado duas vezes como **Não conforme**, com os botões "Ver proposta de correção"
  (captura `07`, tirada depois de restaurar o valor no laboratório; a página mostra o mesmo resultado gravado que antes
  da restauração, conferido em texto antes de restaurar). Não apareceu o aviso de "sala alterada": a execução gravada
  era anterior à coluna `coursecacherev` (upgrade 2026103001) e a correção não muda `course.timemodified`.
- **Referência:** Constituição IV ("MUST ser seguida de nova auditoria"); Nielsen, visibilidade do estado do sistema.
- **Esperado:** depois de uma correção aplicada, o relatório e o histórico refletem a reauditoria.
- **Consequência prática:** o professor pode achar que a correção falhou e tentar de novo (recebe "Não há mais
  inconformidade a corrigir"), ou o painel continua contando o problema até alguém clicar em "Executar novamente".
  A evolução e o painel subestimam o trabalho feito.
- **Recomendação:** gravar a reauditoria como execução (origem nova, por exemplo `correction`, ou reutilizar `page`) via
  `recorder`. Isso tem a vantagem adicional de ligar a trilha da correção à execução seguinte. Alternativa mínima: "Voltar
  ao relatório" faz um POST que reexecuta a auditoria. Adicionar um passo Behat "corrige → volta ao relatório → não vê
  mais CL‑13".
- **Esforço:** P (≤ 1 dia) · **Decisão institucional:** não.

### A‑02 — P2 — Trilha de correções de salas removidas fica fora da Privacy API e não tem retenção

- **Dimensão:** privacidade · **Confiança:** alta · **Situação:** comprovado por leitura (não reproduzido em execução)
- **Local:** `classes/privacy/provider.php` (o tratamento de órfãos, `orphan_runs`, cobre só `local_mooc_audit_run`);
  `classes/task/cleanup_history.php` (não toca em `local_mooc_audit_fix`).
- **Evidência:** `get_contexts_for_userid` encontra linhas de `local_mooc_audit_fix` só por `JOIN {context}` com
  `contextlevel = COURSE`. Quando o curso é apagado, o contexto deixa de existir, e as linhas com `userid` (quem
  confirmou a correção) não são exportadas nem apagadas por pedidos de privacidade. A limpeza agendada também não as
  remove.
- **Referência:** Privacy API (1): todo dado ligado a usuário declarado **e** exportável/apagável.
- **Recomendação:** tratar órfãos de `_fix` no contexto de sistema, como já se faz com `_run`, e incluir `_fix` na
  retenção (ou decidir institucionalmente que a trilha é permanente e anonimizar `userid` após o prazo).
- **Esforço:** P · **Decisão institucional:** sim, sobre o prazo de guarda da trilha de correções.

### A‑03 — P2 — Qualquer atualização do plugin marca todas as auditorias como "desatualizadas"

- **Dimensão:** operação / produto · **Confiança:** alta · **Situação:** comprovado em execução
- **Local:** `classes/local/history/run_store.php:80-83` (versão inclui `versiondisk`); `classes/local/history/dashboard.php:205-206`
- **Evidência:** a mudança de `2026103000` para `2026103001` só acrescentou a coluna `coursecacherev`, sem alterar
  nenhuma regra. Mesmo assim o painel do s5 mostra **104 de 104 salas "Desatualizada: plugin atualizado desde então"**
  (captura `09`), e o relatório de cada sala exibe o mesmo aviso (captura `01`).
- **Esperado:** o sinal de desatualização distingue o que pode ter mudado o resultado.
- **Consequência prática:** a cada atualização, o indicador "Desatualizada" deixa de ajudar a escolher o que reauditar.
  A equipe aprende a ignorá-lo, inclusive quando a sala realmente mudou.
- **Recomendação:** comparar a impressão digital das regras, que já é gravada em `details.rules` (`fingerprint::rules`),
  ou `engine::VERSION`, em vez de `versiondisk`.
- **Esforço:** P · **Decisão institucional:** não.

### A‑04 — P2 — Scripts que criam cursos de teste são distribuídos com o plugin, com uma guarda permissiva

- **Dimensão:** operação / segurança · **Confiança:** média · **Situação:** hipótese de risco operacional (nada foi
  executado fora do laboratório)
- **Local:** `cli/make_fixtures.php:56` e `cli/make_import.php:45`: recusam só se `wwwroot` não contém `localhost`
  **e** `debugdeveloper` está desligado. `cli/make_scenarios.php:62`, que apaga cursos, exige as duas condições.
- **Evidência:** em produção, ligar temporariamente o depurador de desenvolvedor para investigar um problema basta para
  que `make_fixtures.php` crie cursos "MOOC teste A/B" no site institucional. A constituição VIII descreve exatamente
  essa regra (OU), então o código cumpre a constituição; o risco está na própria regra.
- **Recomendação:** retirar os scripts de fixtures do pacote instalado em produção (empacotar sem `cli/make_*` e
  `tests/generator` se a política de deploy permitir) ou exigir `localhost` **e** depurador, mais um argumento
  `--i-know-this-is-dev`.
- **Esforço:** P · **Decisão institucional:** sim, sobre o processo de empacotamento e deploy.

### A‑05 — P2 — O painel carrega todas as salas antes de filtrar e paginar

- **Dimensão:** desempenho · **Confiança:** média · **Situação:** hipótese (medido só com 104 salas)
- **Local:** `dashboard::scope()` (lê todos os cursos do filtro, depois cruza com `get_user_capability_course`);
  `dashboard::build()` decodifica o resumo JSON de cada sala e filtra em PHP; a paginação de 50 é aplicada só na saída.
- **Evidência:** `results.md` da spec 005 registra 72 ms para 104 salas e "revisitar acima de ~1.000 salas". O número
  de cursos do Moodle institucional não foi informado.
- **Recomendação:** antes da homologação, medir com 2.000–5.000 cursos (o gerador de lote já existe). Se o tempo
  passar de cerca de 2 s, filtrar por categoria por padrão e mover a filtragem por situação para SQL.
- **Esforço:** medição P; otimização M · **Decisão institucional:** informar o tamanho real do site.

### A‑06 — P2 — Janela de suporte do Moodle 4.5 e cobertura de plataforma

- **Dimensão:** compatibilidade · **Confiança:** alta · **Situação:** comprovado (datas oficiais e configuração do CI)
- **Evidência:** o Moodle 4.5 LTS tem suporte apenas de segurança desde 06/10/2025, até **04/10/2027**.
  `$plugin->supported = [405, 405]` é uso válido, mas fecha o plugin no 4.5. O CI só testa PHP 8.3 + PostgreSQL. O 4.5
  também suporta PHP 8.1/8.2 e MySQL/MariaDB, e a constituição promete MySQL/MariaDB.
- **Recomendação:** registrar a versão de PHP e o banco da produção. Acrescentar ao CI a combinação da produção (e
  MariaDB, se for o banco institucional). Planejar a migração para a próxima LTS, conferindo a data no calendário
  oficial, antes de 10/2027.
- **Esforço:** CI P; migração M–G · **Decisão institucional:** sim (plataforma de produção e calendário de upgrade).

### A‑07 — P3 — `riskbitmask`: a constituição exige, o código declara só em uma capacidade

- **Dimensão:** segurança / governança · **Confiança:** alta · **Situação:** comprovado (divergência documental)
- **Local:** `db/access.php`. A justificativa está em "specs/001 research R11".
- **Análise:** para o Moodle, declarar riscos é recomendação (2), não requisito. `audit`, `compare` e
  `preparecorrection` não expõem dados pessoais. `viewdashboard` e as páginas de histórico mostram o **nome de quem
  pediu** cada execução, dado pessoal de servidores, mínimo.
- **Recomendação:** ajustar a constituição para "declarar o risco quando aplicável, com justificativa" ou declarar
  `RISK_PERSONAL` em `viewdashboard`. Não é defeito de segurança.
- **Esforço:** P · **Decisão institucional:** não.

### A‑08 — P3 — Sem eventos de log para executar auditoria, criar lote ou comparar

- **Dimensão:** rastreabilidade · **Confiança:** alta · **Situação:** comprovado por leitura (só existe `correction_applied`)
- **Referência:** página de segurança: "every script should log an event" (2).
- **Mitigação existente:** `local_mooc_audit_run` e `_batch` guardam quem pediu e quando.
- **Recomendação:** disparar eventos `audit_run_requested` e `batch_created`, que são baratos e aparecem nos logs
  padrão. Para visualização de relatório, avaliar o volume antes.
- **Esforço:** P.

### A‑09 — P3 — Textos de evidência e orientação fixos em português dentro das 29 regras

- **Dimensão:** manutenção / i18n / UX · **Confiança:** alta · **Situação:** comprovado
- **Local:** `classes/local/rule/*.php` (ex.: `cl13.php`: `"tentativas permitidas=$a"`; `cl08.php:79`).
- **Consequência:** o pacote `en` fica parcial. Mais importante: **as mensagens só mudam alterando código e testes de
  caracterização**, o que encarece a reescrita em linguagem simples proposta no Relatório B.
- **Recomendação:** transformar a evidência em dados estruturados (`found`, `expected`, `unit`, parâmetros) e montar o
  texto a partir de strings em `lang/`. Fazer isso junto com a revisão de linguagem, não como refatoração isolada. O
  contrato JSON pode ganhar campos aditivos (1.x).
- **Esforço:** M–G (29 regras) · **Decisão institucional:** não.

### A‑10 — P3 — Heurísticas de texto que produzem `NAO_CONFORME`

- **Local:** CL‑13 (regex do enunciado) e X‑AV‑01 (regex "Valor: N pontos").
- **Análise:** a constituição VI limita heurísticas a `ATENCAO`, salvo decisão institucional. A decisão 1 cobre o
  enunciado do CL‑13; não encontrei decisão equivalente para X‑AV‑01. O próprio catálogo já registra o risco
  (`docs/catalogo-regras.md:199`).
- **Recomendação:** confirmar institucionalmente X‑AV‑01 ou rebaixá-lo para `ATENCAO`.
- **Decisão institucional:** sim.

### A‑11 — P3 — `helpers::text` cola parágrafos

- **Local:** `classes/local/helpers.php:102-104` (`strip_tags` sem separar blocos).
- **Evidência:** na comparação aparece "Nome do curso: Curso A — referênciaCarga horária: 30 horas" e
  "…horasCriar ContaTurmas…". O mesmo texto alimenta as regex das regras de enunciado, o que pode gerar falso
  negativo raro.
- **Recomendação:** trocar tags de bloco e `<br>` por espaço antes de `strip_tags` (ou usar `html_to_text`).
  Revalidar a caracterização.
- **Esforço:** P.

### A‑12 — P3 — Mensagens técnicas de exceção exibidas ao usuário

- **Local:** `index.php` (`audit_failed` com `get_class($error) . ': ' . getMessage()`); `executor::short()` grava o
  mesmo formato na trilha, exibida no histórico.
- **Risco:** baixo. As exceções DML do Moodle não trazem o SQL na mensagem. O problema é sobretudo de linguagem: o
  professor vê `dml_read_exception: …`.
- **Recomendação:** mensagem amigável na tela, detalhe técnico no `debuginfo` ou no registro da execução.

### A‑13 — P3 — `install.xml` sem chaves estrangeiras declaradas

- `courseid`, `userid`, `batchid`, `policyid`, `previousrunid` são declarados só como índices. A recomendação oficial
  (2) é declarar as FKs no XMLDB, que já criam os índices. Não há efeito funcional. Fazer quando houver outro upgrade de
  esquema.

### A‑14 — P3 — `moodle-extra`: 414 avisos de visibilidade de constante

- É cosmético. Sugestão: adotar `public const` gradualmente, ou registrar a exceção. Não deve virar uma spec.

### A‑15 — P3 — Documentação desatualizada

- O README raiz e o primeiro parágrafo de estado do README do plugin citam a versão 0.2.0, 75 testes e 2 cenários
  Behat. A tarefa T039 da spec 003 está aberta com o PR já mesclado. Atualizar no próximo PR.

### A‑16 — P3 — A página e o lote podem auditar a mesma sala ao mesmo tempo

- **Local:** `recorder::record_now` (página) não usa a trava `course_<id>` que `task\audit_course` usa.
- **Situação:** hipótese de impacto baixo. As duas execuções são somente leitura e a base da evolução é escolhida por
  tempo. Registrar e não agir, a menos que apareçam evoluções incoerentes.

## 7. Segurança, autorização e privacidade (visão transversal)

| Fluxo | Login/contexto | Capacidade | CSRF | Escape/SQL | Conclusão |
|---|---|---|---|---|---|
| Auditoria (página) | `require_login($course)` | `audit` no curso | Executar = POST + `require_sesskey` (decisão 12.1); GET só consulta | Mustache escapa; `{{{ }}}` só para HTML renderizado pelo próprio Moodle | Adequado |
| JSON do relatório | idem | idem | GET de leitura | `engine::to_json`; segredos só como booleano (`has_password`) | Adequado |
| Correção | `require_login($course)` | `preparecorrection` para ver; `applycorrection` + `moodle/course:manageactivities` no módulo, conferidos de novo no executor | POST + `data_submitted` + `require_sesskey`; impressão digital com `hash_equals` impede reutilizar prévia antiga | Escrita só no executor, em transação, com releitura; trava por atividade | **Sólido** |
| Comparação | `require_login` | `compare` nas **duas** salas; sala inexistente recebe a mesma resposta que a falta de permissão | GET de leitura | — | Adequado |
| Histórico | contexto do curso; sala removida só para `viewdashboard` | `audit` | leitura | `run_of_course` impede ver execução de outra sala pela URL | Adequado |
| Painel / lote | `admin_externalpage_setup` | `viewdashboard`; criar/reexecutar exige `batchaudit` | POST/ação com `require_sesskey` | `sql_like` + `sql_like_escape`; listas cruzadas com salas auditáveis | Adequado |
| Tarefa adhoc | executa como o solicitante (`set_userid`) | reconfere `batchaudit` + `audit`, usuário ativo | — | trava por sala, máximo de 3 tentativas próprias | Adequado |
| CLI | servidor | admin | — | — | Ver A‑04 |

**Nenhum P0 encontrado.** Não há injeção SQL: todos os `SELECT` usam placeholders, e os nomes de campos dinâmicos de
`run_store::finish` passam por lista branca. Não encontrei XSS: os dados de curso chegam aos templates com escape. As
operações de escrita exigem POST com `sesskey`. A proteção contra reutilização de propostas antigas
(impressão digital + releitura do valor atual + checagem de dados de estudantes dentro da transação) está acima da média
de plugins institucionais e **deve ser preservada**. Riscos de privacidade: A‑02 e, em menor grau, A‑07.

## 8. Testabilidade

- 214 testes PHPUnit cobrem cada regra (conforme e não conforme), caracterização dos cursos A/B, contrato JSON,
  somente leitura (`readonly_test`), acesso, executor (conflito, rollback, permissões), histórico, tarefas e privacidade.
- 13 cenários Behat, 5 deles com axe. O axe dos testes não pegou o contraste do painel porque os dados de teste não têm
  sala "desatualizada" (ver B‑19). **Lacuna:** nenhum Behat cobre "corrigir → voltar ao relatório" (A‑01), e nenhum teste
  usa a opção `best-practice` recomendada pela documentação.
- O CI no GitHub roda os oito verificadores do `moodle-plugin-ci` em cada PR. É uma decisão correta e deve ser mantida.

## 9. Instalação e upgrade

- A cadeia `2026102902 → 2026102905 → 2026103000 → 2026103001` usa savepoints e `table_exists` (idempotente).
  O CI instala do zero e o laboratório principal já passou pelos upgrades 0.2 → 0.3.
- O upgrade `2026102905` concede `local/mooc_audit:audit` ao professor editor em sites antigos, respeitando qualquer
  registro já existente (inclusive `PROHIBIT`). A documentação oficial diz que alterar permissões de sites existentes "is
  not recommended". Aqui é uma decisão institucional registrada (decisão 11), idempotente e testada. **Sem impacto em
  produção**, que ainda não tem o plugin. Manter, mas não repetir o padrão sem nova decisão.
- O upgrade a partir de um Moodle com **histórico e permissões customizadas reais** não foi testado (não há cópia do
  banco institucional). Recomendo um ensaio de instalação em uma cópia anonimizada de produção na homologação.

## 10. Operação e desempenho (medidos na spec 005, conferidos no s5)

- Auditoria: 0,024 s por sala (A/B), 0,14 s na sala de 151 módulos, cerca de 420 leituras e 29 MB de pico.
- Armazenamento: relatório médio de 29 kB (máximo 41 kB) e detalhes de 2 kB. 205 execuções ocupam 2,0 MB no
  PostgreSQL. A retenção padrão é de 365 dias e guarda sempre a última execução válida.
- Tarefas: adhoc com concorrência configurável (padrão 2, máximo 10). A limpeza diária fecha execuções presas há mais
  de 24 h.
- Recuperação: execução interrompida é marcada `failed` após 3 tentativas ou pela limpeza. O lote permite "reexecutar
  falhas". **Não observei um lote com falha parcial real**; todos os lotes do laboratório terminaram completos.

## 11. Decisões corretas a preservar

1. Auditoria somente leitura comprovada por teste e GET sem efeitos (decisão 12.1).
2. Executor de correção: dupla checagem de capacidades, trava, impressão digital, transação, releitura pela API do
   módulo e reauditoria.
3. Separação entre `NAO_VERIFICAVEL` e `NAO_CONFORME`, e isolamento de exceção por regra (`ERRO`).
4. Uma classe por regra, catálogo explícito, chave de ocorrência estável e testes de caracterização.
5. Política versionada com impressão digital gravada em cada execução.
6. Contrato JSON versionado com schema e evolução aditiva.
7. CI com `moodle-plugin-ci` e ambientes isolados por spec.
8. Comparação que não atribui causa à importação, e aviso de que "sem inconformidades" não é aprovação.

## 12. Limitações desta avaliação

- Sem acesso ao Moodle institucional, ao seu número de cursos, às versões de PHP/banco nem a permissões customizadas.
- Sem MySQL/MariaDB. Sem phpmd/phpstan.
- As referências oficiais foram lidas por ferramenta que devolve resumos; as citações literais devem ser conferidas na
  fonte antes de uso formal.
- A Privacy API (A‑02) foi avaliada por leitura, sem reprodução em execução.
- Um único perfil (administrador) foi usado no navegador. Os perfis de professor foram avaliados pelos testes Behat e
  pela leitura de código.
