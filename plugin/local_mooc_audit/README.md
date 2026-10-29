# local_mooc_audit — auditoria técnica de cursos MOOC dentro do Moodle 4.5

Plugin local (versão 0.4.0, entregas das specs 001, 002 e 003) que audita uma sala existente a partir do seu `courseid`, sem depender do MOOC Admin
nem de arquivos `.mbz`. A **auditoria é somente leitura**; a **correção assistida** (spec 002) é um componente separado
que só escreve após prévia, confirmação explícita e revalidação. Reaproveita o catálogo de regras e as decisões institucionais do projeto `checklist-mooc`.

**Estado (0.2.0, maturidade ALPHA):** 29 regras em classes próprias, cada uma com teste PHPUnit de cenário conforme e
não conforme; contrato JSON 1.0 com schema; página do relatório no contexto do curso (menu *Mais > Auditoria técnica*)
com links de correção e reexecução; política institucional ajustável na administração. Validado em Moodle 4.5.15 /
PHP 8.3 / PostgreSQL 17 em ambiente local: 75 testes PHPUnit, 2 cenários Behat com verificação de acessibilidade (axe),
PHPCS padrão `moodle` sem erros nem avisos, e estados por regra idênticos aos da PoC nos cursos A, B, 55 e 670
(`plugin-results/0.2.0/`). Antes da produção: decisão 10 (nomes canônicos), validação com bloco de site real e revisão
da equipe técnica.

**Estado (0.3.0, spec 002 — correção assistida, experimental):** inconformidades CL-13, CL-14 e CL-17 com atividade
identificada oferecem "Ver proposta de correção" (problema, local, regra, valor atual, valor proposto, justificativa,
impacto simulado, riscos/dependências/impedimentos, quem pode executar). Executáveis mediante confirmação: tentativas de
questionário (CL-13) e encerramento de questionário igual ao término do curso (CL-14). Orientadas: CL-14 dos demais
tipos e critério de conclusão (CL-17). 118 testes PHPUnit, 6 cenários Behat, PHPCS limpo. Matriz das 29 regras em
`specs/002-correcao-assistida/correction-matrix.md`; evidências em `plugin-results/0.3.0/`.

**Estado (0.4.0, spec 003 — comparação entre ofertas, experimental):** página *Administração do site > Cursos >
Comparar ofertas* (e link "Comparar com a oferta anterior" no relatório de auditoria), CLI e contrato JSON próprio
`comparison` 1.0. Compara a sala da oferta anterior (origem) com a da nova oferta (destino) só pelos ids: atividades
preservadas, ausentes, adicionais, modificadas e correspondências ambíguas (casamento em passes determinísticos, nunca
por `cmid`; correspondência manual sem persistência); diferenças classificadas em esperadas, relevantes e informativas;
relação entre as duas auditorias (persistente, novo no destino, pendência da nova oferta, resolvido/ausente no destino),
com a origem avaliada no ano da sua própria oferta. Restrita a gerentes, com a capacidade nas duas salas; somente
leitura. 161 testes PHPUnit, 9 cenários Behat, PHPCS limpo. Evidências e medições em `plugin-results/0.4.0/`;
especificação em `specs/003-comparacao-ofertas/`.

## Instalação

```bash
cp -r plugin/local_mooc_audit <moodle>/local/mooc_audit
php admin/cli/upgrade.php --non-interactive
```

Nenhuma alteração no núcleo. Capacidades próprias (arquétipos padrão na última coluna):

| Capacidade | Contexto | Tipo | Para | Padrão |
|---|---|---|---|---|
| `local/mooc_audit:audit` | curso | read | executar a auditoria e ver o relatório | manager, editingteacher |
| `local/mooc_audit:compare` | sistema | read | comparar origem × destino | manager |
| `local/mooc_audit:preparecorrection` | curso | read | ver propostas de correção | manager, editingteacher |
| `local/mooc_audit:applycorrection` | curso | write, `RISK_CONFIG` | confirmar correções (exige também `moodle/course:manageactivities` na atividade) | manager, editingteacher |

Padrões da decisão 11 (2026-10-29); professores não editores e estudantes não recebem nenhuma capacidade do plugin.
O Moodle só aplica os arquétipos quando a capacidade é criada; por isso o upgrade para 2026102905
(`db/upgradelib.php`) concede `local/mooc_audit:audit` aos papéis de arquétipo professor editor em sites que já tinham
a capacidade, sem alterar papéis em que o administrador já escolheu uma permissão.

Tabela própria: `local_mooc_audit_fix` (trilha das correções confirmadas; ver privacidade).

## Uso

```bash
# Auditoria (texto ou JSON); --userid define a visão usada em "disponível para o usuário"
php local/mooc_audit/cli/audit.php --courseid=3
php local/mooc_audit/cli/audit.php --courseid=3 --json --out=/tmp/curso3.json
php local/mooc_audit/cli/audit.php --courseid=3 --userid=42          # visão de um estudante de teste
php local/mooc_audit/cli/audit.php --courseid=3 --policy=politica.json

# Comparação entre ofertas (origem = oferta anterior, destino = nova oferta); --json = contrato comparison 1.0
php local/mooc_audit/cli/compare.php --source=8 --target=10
php local/mooc_audit/cli/compare.php --source=8 --target=10 --json --out=/tmp/comparacao.json
php local/mooc_audit/cli/compare.php --source=8 --target=10 --pairs=179-214   # correspondência manual (não gravada)

# Página web (exige login e a capacidade no contexto do curso); também pelo menu do curso: Mais > Auditoria técnica
/local/mooc_audit/index.php?courseid=3            # relatório (inventário, situação geral, inconformidades por gravidade/categoria, alertas, conformes, avisos)
/local/mooc_audit/index.php?courseid=3&format=json
/local/mooc_audit/correction.php?courseid=3&rule=CL-13&cmid=24   # prévia (GET, nunca grava); confirmar = POST com sesskey
/local/mooc_audit/compare.php                                  # seleção (Administração do site > Cursos > Comparar ofertas)
/local/mooc_audit/compare.php?source=8&target=10&confirm=1     # comparação (exige local/mooc_audit:compare nas duas salas)
/local/mooc_audit/compare.php?source=8&target=10&confirm=1&format=json
```

### Correção assistida (spec 002)

1. **Prévia** (`GET`): o planejador reconstrói a auditoria, escolhe a estratégia da regra/tipo, descreve a proposta,
   consulta dados de estudantes (tentativas, notas, inscritos, substituições, registros de conclusão) e **simula** a
   alteração executando as regras sobre o snapshot em memória. Executável só se for categoria A, sem impedimentos, e a
   simulação resolver a inconformidade sem criar outra.
2. **Confirmação** (`POST` + `sesskey` + impressão digital): o executor revalida capacidades, existência da atividade,
   trava por atividade, reconstrói a proposta do banco e compara a impressão digital (cobre `cacherev` do curso,
   `timemodified` da instância, valores e versão da política); qualquer diferença = conflito, nada é gravado.
3. **Escrita** em transação, relendo o valor atual; depois o valor é lido de volta pela API do questionário; reauditoria
   completa compara antes × depois; linha em `local_mooc_audit_fix` e evento `correction_applied`. Sucesso só é
   exibido quando a reauditoria confirma.

Sem lote, sem rollback automático (o valor anterior fica registrado), sem execução de CL-17.

Política institucional: padrões em `classes/policy.php` (decisões de 2026-10-28); sobrescrever em
*Administração do site > Plugins > Plugins locais > Auditoria MOOC* (JSON) ou com `--policy`. A página de
administração lista as chaves aceitas com tipo e valor padrão e mostra o JSON padrão da versão, ambos gerados de
`policy::defaults()`; JSON inválido é recusado ao salvar. O relatório mostra a versão da política aplicada (sufixo
`+admin` quando há personalização). O ano da reoferta, quando não informado, é o ano seguinte ao da execução.

Scripts **apenas para ambientes de teste** (recusam rodar fora de `localhost`): `cli/make_fixtures.php`
(cria os cursos A — referência — e B — inconsistente; exige o autoloader do Composer do site de desenvolvimento, porque o
gerador de questões do núcleo carrega classes do PHPUnit) e `cli/make_import.php` (cria sala vazia e importa com o
mecanismo nativo `core_course_external::import_course`).

## Estrutura (0.4.0)

| Arquivo | Papel |
|---|---|
| `classes/snapshot.php` | Retrato JSON-serializável da sala (mesmo modelo do protótipo MBZ) |
| `classes/policy.php`, `classes/admin/setting_policyjson.php` | Parâmetros institucionais (fora do código das regras); avisos de política inválida; o setting rejeita JSON malformado antes de salvar |
| `classes/local/rule/rule_interface.php`, `base.php` | Contrato de uma regra (`id()`, `meta()`, `evaluate()`) e construtor de findings |
| `classes/local/rule/*.php` | Uma classe por regra: 18 `CL-*` (checklist) + 11 `X-*` (adicionais), com categoria, tipo, gravidade, chaves de política, locus e fonte |
| `classes/local/catalog.php` | Lista explícita e ordenada das 29 regras |
| `classes/local/{status,category,helpers,locator}.php` | Estados/gravidades, 8 categorias, helpers compartilhados, mapeamento finding → URL de correção (loci: configurações/página do módulo, configurações do curso, inscrição, livro de notas, blocos, seção, importação, nenhum) |
| `classes/engine.php` | Execução isolada das regras, situação geral, contrato JSON 1.0 (`to_json()` é a única codificação, usada pela página e pelo CLI), renderização texto |
| `schema/report-v1.0.schema.json` | JSON Schema do contrato 1.0 (a spec aponta para este arquivo por link simbólico: fonte única); validado por `tests/contract_test.php` |
| `classes/comparator.php` | Comparação entre ofertas (spec 003): `run()` (dois retratos, auditoria do destino com a política vigente e da origem no ano dela, relação), `compare()` estrutural, `require_access()`, contrato `comparison` 1.0 (`to_json()`), texto da CLI |
| `classes/local/comparison/matcher.php` | Correspondência sem ids: passes com unicidade nos dois lados (tipo+seção+nome, +conteúdo, tipo+nome, tipo+conteúdo, tipo+seção+posição), congelamento de ambiguidades, pares manuais, impressões de conteúdo e de configuração |
| `classes/local/comparison/differ.php` | Diferenças por par e da sala, cada uma com tipo (esperada/relevante/informativa), código e explicação; restrições remapeadas, arquivos (registro/conteúdo/disponibilidade), glossário → jogos |
| `classes/local/comparison/audit_relation.php` | Relaciona as auditorias pela chave de local sem ids; classes persistente, novo, pendência da oferta, resolvido, ausente etc. |
| `compare.php`, `classes/form/compare_form.php`, `classes/output/comparison_report.php`, `templates/comparison*.mustache` | Página de seleção (POST com sesskey → URL GET reproduzível) e relatório da comparação |
| `schema/comparison-v1.0.schema.json` | JSON Schema do contrato `comparison` 1.0 |
| `cli/audit.php`, `cli/compare.php` | Linha de comando |
| `cli/make_scenarios.php` | Somente laboratório: cenários A–G da comparação (gerador + importação nativa) |
| `lib.php` | Callback `local_mooc_audit_extend_navigation_course()`: entrada "Auditoria técnica" no menu do curso para quem tem a capacidade |
| `index.php` | Página do relatório no contexto do curso (`courseid` ou `id`; `format=json`); local de cada resultado é link para a página de correção quando existe; botão "Executar novamente"; sem id, formulário de compatibilidade |
| `classes/output/report.php`, `renderer.php`, `templates/report*.mustache` | Dados do relatório para o template (agrupamento por gravidade → categoria, listas de alertas, não verificados, erros, conformes, avisos) e renderização |
| `tests/generator/lib.php` | Gerador de componente: cursos A (conforme) e B (inconsistente), usado pelo PHPUnit e por `cli/make_fixtures.php` |
| `tests/characterization_test.php` + `tests/fixtures/expected_*.json` | Estados por regra dos cursos A/B congelados (proteção contra mudança de comportamento) |
| `tests/engine_test.php` | Motor, contrato, política, isolamento de regra com exceção (regra injetada), conclusão desabilitada no site, locus de importação, saída texto do CLI |
| `tests/contract_test.php` | Relatórios de A/B válidos contra o schema (validador mínimo sem biblioteca), campos da PoC preservados, ida e volta do JSON |
| `tests/policy_test.php` | Padrões, override do administrador e por arquivo, tipos errados, ano de reoferta automático, validação do setting, nome canônico alterado aplicado a CL-09 |
| `tests/helper/rule_testcase.php` | Base dos testes por regra: curso na categoria da oferta com datas da reoferta, construtores de página/questionário/pesquisa/certificado e árvores de restrição |
| `tests/local/rule/*_test.php` | Um arquivo por regra (29): cenários conforme, não conforme e, quando a regra os produz, atenção/não se aplica/não verificável, construídos por API (ex.: X-INT-03 com condição de plugin inexistente; X-CERT-01 com visão de estudante) |
| `tests/readonly_test.php` | Hashes de 17 tabelas do curso B antes e depois de três auditorias (admin, estudante, texto) idênticos; `timemodified`/`cacherev` inalterados; idem ao abrir todas as propostas e o relatório com ações (sem evento, sem linha na trilha) |
| `tests/locator_test.php` | URL esperada por locus; identificadores ausentes → sem link |
| `tests/access_test.php` | Capacidade de auditoria por arquétipo (estudante/professor não editor não; professor editor/gerente sim), definição sem risco declarado, nó de navegação só com a capacidade; capacidades de correção (gerente e professor editor sim; professor não editor e estudante não; `applycorrection` write com `RISK_CONFIG`) |
| `tests/output_test.php` | Dados do template e renderização da página (cursos A/B) |
| `classes/local/correction/proposal.php` | Proposta (valor, impressão digital, invariante de executabilidade) |
| `classes/local/correction/planner.php` | Monta a proposta a partir de uma auditoria fresca; simulação; capacidades e papéis |
| `classes/local/correction/strategy/*.php` | Estratégias: `quiz_attempts` (CL-13, A), `quiz_timeclose` (CL-14 questionário, A), `close_date_guidance` (CL-14 demais tipos, B), `completion_guidance` (CL-17, B) |
| `classes/local/correction/executor.php` | Única classe que escreve em dados de curso: revalidação, trava, transação, releitura, reauditoria, trilha |
| `classes/local/correction/{verifier,facts,trail,conflict_exception}.php` | Comparação antes × depois (pura); consultas somente leitura de dados de estudantes; trilha + evento; conflito na escrita |
| `classes/event/correction_applied.php`, `db/install.xml`, `db/upgrade.php` | Evento de log e tabela `local_mooc_audit_fix` |
| `classes/privacy/provider.php` | Declara/exporta o `userid` da trilha; exclusão anonimiza (`userid = 0`) |
| `correction.php`, `classes/output/correction_{proposal,result}.php`, `templates/correction*.mustache` | Página de prévia/confirmação e resultado |
| `tests/local/correction/*_test.php`, `tests/helper/correction_testcase.php`, `tests/fixtures/correction/` | Planejador, executor (sucesso, conflitos, permissões, estudantes, falha com rollback, releitura, reauditoria, trava), verificador, proposta, saída |
| `tests/privacy/provider_test.php` | Metadados, exportação e anonimização |
| `tests/behat/correction.feature` | Ver proposta, confirmar (com axe), cancelar, professor editor aplica, professor não editor vê mas não aplica |
| `tests/local/comparison/*_test.php`, `tests/helper/snapshot_builder.php` | Correspondência, diferenças e relação entre auditorias sobre retratos sintéticos (sem banco) |
| `tests/comparator_test.php` | Cenários A–G com cursos reais e importação por backup/restore; cada saída validada contra o schema |
| `tests/compare_access_test.php`, `tests/compare_readonly_test.php`, `tests/comparison_contract_test.php`, `tests/comparison_output_test.php` | Capacidade nas duas salas e busca restrita; não modificação das duas salas e ausência de dados pessoais; contrato; página e texto |
| `tests/helper/schema_validator.php` | Validador mínimo de JSON Schema (contratos 1.0 e comparison 1.0) |
| `tests/behat/compare.feature` | Seleção pela administração, confirmação obrigatória, relatório (com axe), correspondência manual, link a partir da auditoria, professor editor sem acesso |
| `tests/behat/audit_page.feature` | Gerente abre o relatório pelo menu do curso (com JavaScript e verificação de acessibilidade axe), reexecuta; professor editor abre o relatório; professor não editor e estudante não veem a entrada |

Fluxo de desenvolvimento: `specs/001-auditoria-tecnica-operacional/` (Spec Kit) e `.specify/memory/constitution.md`.

## Fontes de dados e justificativa dos acessos

| Informação | API usada | Observação |
|---|---|---|
| Curso, categoria, formato | `get_course()`, `core_course_category::get()`, `course_get_format()->get_format_options()` | API |
| Seções e módulos (visibilidade, conclusão, restrições, visão do usuário) | `get_fast_modinfo($course, $userid)` → `section_info`, `cm_info` | API; `availability` é o mesmo JSON do backup |
| Livro de notas | `grade_category::fetch_all()`, `grade_item::fetch_all()` | API (inclui `aggregation` e `aggregateonlygraded`, que os Web Services não expõem) |
| Métodos de inscrição | `enrol_get_instances($courseid, false)` | API (inclui datas e `customint6`, que os Web Services não expõem) |
| Blocos da página do curso, inclusive herdados do site | `block_manager` (`load_blocks`, `create_all_block_instances`); fallback DML em `block_instances` | API |
| Arquivos e existência do conteúdo | `files` por contexto (DML) + `file_storage::get_file_by_id()` + `file_system::is_file_readable_locally_by_storedfile()` | distingue registro sem conteúdo de referência sem registro |
| Plugins instalados | `core_plugin_manager::get_installed_plugins()`; `standard_plugins_list()` | API |
| Conclusão de curso | `completion_info` | API |
| **Configurações específicas por módulo** (tentativas, datas, `completionsubmit`, fonte de jogos, texto do certificado…) | `$DB->get_record('<modname>', …)` com **lista branca de colunas** + `count_records` (`quiz_slots`, `feedback_item`, `glossary_entries`, `book_chapters`, `hvp_libraries`) | **DML justificado:** não existe API cross-módulo para ler configurações; são tabelas estáveis de cada módulo; nenhuma escrita |
| Dados de estudantes para a correção (contagens) | `quiz_attempts` (`preview = 0`), `grade_grades`, `quiz_overrides`, `count_enrolled_users()`, `course_modules_completion`, `course_completion_criteria` | só contagens, só no planejador de correção |

### Escrita (somente `local\correction\executor`, spec 002)

| Campo | Como é escrito | Justificativa |
|---|---|---|
| `quiz.attempts` | `$DB->update_record('quiz', {id, attempts, timemodified})` + `course_modinfo::purge_course_module_cache` + `rebuild_course_cache` + evento `course_module_updated` | `quiz_update_instance()` exige dados no formato do formulário: com o registro do banco, `quiz_process_options()` zera as opções de revisão e `quiz_after_add_or_update()` apaga/recria os feedbacks. O núcleo atualiza campos isolados do questionário fora do formulário em `mod_quiz_core_calendar_event_timestart_updated()` (mesma sequência) e `shift_course_mod_dates()` |
| `quiz.timeclose` | idem + `quiz_update_open_attempts()` + `quiz_update_events()` (calendário) | mesma rotina do núcleo para mudança de encerramento; prévias de professores são mantidas |

Lista branca: apenas essas duas colunas. Testes comparam o registro inteiro antes/depois (só o campo e `timemodified`
mudam).

## Verificação

```bash
vendor/bin/phpunit --testsuite local_mooc_audit_testsuite      # 161 testes
php admin/tool/behat/cli/run.php --tags=@local_mooc_audit       # exige Selenium; ver moodle-env/README.md
vendor/bin/phpcs --standard=moodle local/mooc_audit             # moodlehq/moodle-cs
```

## Limitações conhecidas

- Regras por nome canônico (fórum, pesquisas, certificado) dependem dos nomes oficiais (decisão 10 pendente).
- `mod_simplecertificate` do GitHub (2024051100) é anterior ao da produção (2024051105); `availability_days` só existe
  publicamente na versão 2016 (instalada apenas para validar a leitura de condições `days`).
- O plugin não vê o pacote `.mbz`: corrupção de backup e arquivos ausentes *no pacote* só são detectáveis antes da
  restauração. Ver `RELATORIO-POC-PLUGIN-MOODLE.md`.
- Textos de evidência das regras ainda são gerados em português no código (orientações e títulos já estão em `lang/`).
- Módulo com nome vazio não aparece no `modinfo` do Moodle; X-STR-01 só detecta nomes formados por espaços ou marcação.
- Sem persistência de auditorias: cada execução é recalculada; histórico e acompanhamento são da spec 005. A única
  tabela guarda a trilha das correções confirmadas.
- Correção assistida experimental: executável só para questionários (CL-13, CL-14); demais tipos e CL-17 são
  orientados. O marco de encerramento do CL-14 (término do curso) depende de validação institucional.
- Detecção de conflito: entre a releitura do valor dentro da transação e a gravação, uma edição simultânea pela tela de
  edição não usa a mesma trava (janela de milissegundos); a releitura pela API e a reauditoria acusariam a divergência.
- Sem desfazer automático; o valor anterior está na trilha para reversão manual.
- Comparação entre ofertas (spec 003, experimental): a origem é avaliada com a política vigente no ano da sua data de
  início (a política não tem versões por ano); a correspondência por conteúdo/posição tem confiança média;
  correspondências manuais não são gravadas; nenhuma diferença é atribuída à importação (pode haver edição posterior);
  resultados de regras sem local de atividade são agrupados por regra + local textual (vários blocos ausentes de
  CL-21 formam um único item); sem histórico de comparações (spec 005). Detalhes em
  `specs/003-comparacao-ofertas/results.md`.
