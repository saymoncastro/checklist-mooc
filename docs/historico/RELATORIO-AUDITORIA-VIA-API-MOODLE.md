# Auditoria de cursos MOOC via Web Services do Moodle — investigação complementar

**Data:** 2026-10-28 · **Complementa:** `RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md` (auditoria via `.mbz`) e `DECISOES-INSTITUCIONAIS-CHECKLIST.md`.
**Pergunta:** auditar a sala restaurada por API é mais simples, confiável ou abrangente do que analisar o backup?

**Base de evidência:** código-fonte do Moodle 4.5 (`MOODLE_405_STABLE`: `lib/db/services.php`, `course/externallib.php`, `course/classes/external/helper_for_get_mods_by_courses.php`, `blocks/classes/external.php`, `blocks/html/block_html.php`, `grade/classes/external/get_grade_tree.php`, `grade/classes/external/get_gradeitems.php`, `grade/report/user/classes/external/user.php`, `completion/classes/external.php`, `enrol/externallib.php`, `enrol/self/externallib.php`, `webservice/externallib.php`, `mod/{quiz,feedback,forum,url,resource,glossary,page}` externals, `mod/book/lib.php`), a página "Web service API functions" da documentação de desenvolvedor, e os repositórios dos plugins `mod_simplecertificate` (davidherney), `mod_hvp` (h5p) e `mod_game` (bdaloukas).

**Legenda:** **[DOC]** confirmado no código-fonte/documentação · **[EXEC]** comprovado em execução · **[HIP]** hipótese · **[INST]** depende de decisão/ambiente institucional. **Nesta etapa nada foi executado contra um Moodle**: não havia instalação de homologação nem token disponíveis. O cliente `poc/moodle_api_client.py` e o plano `poc/plano-prova-comparativa-api.md` ficam prontos para a prova; nenhum resultado de API foi simulado.

---

## 1. Diagnóstico executivo

1. **A API nativa do Moodle 4.5 cobre, sem plugin, a maior parte do que o backup cobre** — configurações do curso, seções, módulos, visibilidade, restrições de acesso (o mesmo JSON do backup), conclusão, itens de nota, questionários, pesquisas, fóruns, páginas, URLs, arquivos e blocos — e acrescenta o que o backup **não** tem: estado real da sala nova, blocos de nível de site, papéis dos professores, arquivos efetivamente servidos, tipos de atividade instalados e a visão de um usuário de teste. [DOC]
2. **Ela não substitui o backup em seis pontos:** integridade do pacote antes de restaurar; **datas e "permitir novas inscrições" da autoinscrição** (nenhuma função devolve `enrolstartdate/enrolenddate/customint6`); **tipo de agregação e "excluir notas vazias" da categoria de notas**; configuração e imagens do certificado (`mod_simplecertificate` só expõe `verify_code`); campos específicos de `mod_game` e `mod_hvp` (sem Web Services); configuração do bloco de progresso. [DOC]
3. **A API é mais complexa de operar:** exige conta de serviço com ~12 capacidades no contexto da categoria, um serviço com ~30 funções habilitadas, 15 a 25 chamadas por curso, tratamento de campos que mudam conforme a capacidade do chamador, semântica "por usuário" em notas e conclusão (uma sala recém-restaurada sem alunos devolve **zero** itens em `gradereport_user_get_grade_items`), e disponibilidade do Moodle no momento da auditoria. O backup é um arquivo autocontido, determinístico e auditável offline.
4. **A API só existe depois da restauração.** Problemas que seriam propagados (arquivos ausentes no pacote, plugin exigido, glossário vazio) precisam ser vistos **antes**, no backup, para orientar a correção na sala de origem.
5. **Testes funcionais continuam fora da API:** a emissão de certificado não tem Web Service; "conclusão funcionando" e "jogo renderiza" exigem ações de um usuário de teste que as funções de leitura não executam (e as de escrita não devem ser usadas em auditoria).
6. **Um plugin `local_mooc_audit` não se justifica na primeira versão.** Das seis lacunas, quatro são preenchidas pelo próprio backup (agregação, certificado, game/hvp, bloco de progresso não mudam na restauração) e uma pelo MOOC Admin, que é quem define as datas de inscrição. Resta a lista de plugins de disponibilidade instalados, verificável indiretamente.
7. **Recomendação:** um único motor de regras com dois adaptadores de leitura — **MBZ** (antes/durante a reoferta: integridade, dependências, estado do curso de origem) e **Moodle API nativa** (depois: estado efetivo da sala nova, 12 a 15 regras). Nada de acesso ao banco de produção. Plugin somente se a prova em homologação mostrar que as lacunas restantes importam.

---

## 2. Inventário das funções nativas relevantes (Moodle 4.5) [DOC]

Todas de leitura, protocolo REST (`webservice/rest/server.php`, `moodlewsrestformat=json`). "Capacidade" = o que o código verifica ou que condiciona campos; "serviço" = todas constam do `MOODLE_OFFICIAL_MOBILE_SERVICE`, mas o ideal é um serviço próprio com só estas funções.

| Função | O que devolve (campos relevantes) | Capacidades / condições | Uso na auditoria |
|---|---|---|---|
| `core_webservice_get_site_info` | `release`, `version`, `functions[]` (nome + versão de cada função disponível ao serviço), `advancedfeatures` (`enablecompletion`, `enablebadges`, `usetags`…), `userid`, `userissiteadmin` | token válido | versão do destino; funções habilitadas; conclusão/badges ativas no site |
| `core_course_get_courses_by_field` | públicos: `id`, `fullname`, `shortname`, `categoryid`, `categoryname`, `summary`, `overviewfiles`, `courseimage`, `enrollmentmethods`, `customfields`; com acesso ao curso: `format`, `startdate`, `enddate`, `visible`, `enablecompletion`, `lang`, `courseformatoptions` (`hiddensections`, `coursedisplay`); com `moodle/course:update`: `idnumber`, `timecreated`, `timemodified`… | `moodle/course:view`; `course:update` p/ campos extras | itens 4, 5, 6, 7, 20, 26 |
| `core_course_get_contents` | por seção: `id`, `section`, `name`, `visible`, `uservisible`, `availabilityinfo`, `summary`; por módulo: `id` (cmid), `name`, `modname`, `instance`, `contextid`, `visible`, `visibleoncoursepage`, `uservisible`, `availabilityinfo`, **`availability`** (JSON, só se `$CFG->enableavailability` e `moodle/course:viewhiddenactivities` ou `course:update`), `completion`, `completiondata` (do usuário do token), `dates`, `contents[]` (arquivos: `filename`, `filesize`, `mimetype`, `fileurl`, `timemodified`), `contentsinfo`; opções `includestealthmodules`, `excludecontents`, `sectionnumber`, `cmid`, `modname` | `course:update`/`viewhiddencourses` p/ curso oculto; módulos invisíveis ao usuário são omitidos | espinha dorsal: estrutura, restrições, conclusão, arquivos, visibilidade |
| `core_course_get_course_module` / `_by_instance` | com `moodle/course:manageactivities`: registro completo do `course_modules` (`completion`, `completionview`, `completiongradeitemnumber`, `completionpassgrade`, `completionexpected`, `availability`, `visible`, `visibleoncoursepage`, `showdescription`, `downloadcontent`) + `grade`, `gradepass`, `gradecat`, `advancedgrading`, `outcomes`; sem: só `id`, `course`, `module`, `modname`, `instance`, `section`, `groupmode`, `completion` | `manageactivities` | critérios de conclusão por nota; categoria de nota do módulo |
| `core_course_get_course_content_items` | itens do seletor de atividades = tipos de módulo instalados/habilitados para o curso | (verificar capacidade na prova) | plugins `mod_*` disponíveis no destino |
| `core_block_get_course_blocks(courseid, returncontents)` | por bloco: `instanceid`, `name`, `region`, `weight`, `visible`, `configs` (`get_config_for_external()`; `block_html` devolve toda a config: `title`, `text`, `format`, `classes`), `contents` se `returncontents` | `validate_context` do curso; **blocos ocultos não são devolvidos** (`includeinvisible=false`) | item 21 — inclui blocos herdados do site exibidos na página do curso |
| `core_enrol_get_course_enrolment_methods` | `id`, `courseid`, `type`, `name`, `status`, `wsfunction` — **apenas instâncias habilitadas** (`enrol_get_instances(..., true)`); **sem datas** | — | item 8 (parcial) |
| `enrol_self_get_instance_info` | `id`, `courseid`, `type`, `name`, `status`, `enrolpassword` (só indica exigência) — **sem `enrolstartdate/enrolenddate/customint6`** | curso visível ou acessível | item 8 (parcial) |
| `core_enrol_get_enrolled_users` | usuários com `roles[]` (`shortname`: editingteacher, teacher, student) | `course_require_view_participants`; `moodle/role:review` p/ filtro por capacidade | item 3 (professores) |
| `gradereport_user_get_grade_items(courseid, userid=0)` | por usuário e item: `id`, `itemname`, `itemtype`, `itemmodule`, `iteminstance`, `categoryid`, `cmid`, `grademin`, `grademax`, `weightraw`, `gradeishidden`, `locked`… — **sem tipo de agregação**; `userid=0` = todos os usuários (exige `moodle/grade:viewall`); **sem usuários inscritos devolve vazio** | `gradereport/user:view` + `grade:viewall` | itens 12, 16, 23 |
| `core_grades_get_grade_tree` | JSON da árvore (categorias e itens: `id`, `name`, `iscategory`, `children`) — **sem agregação, grademax ou pesos** | `moodle/grade:viewall` | estrutura de categorias |
| `core_grades_get_gradeitems` | `id`, `itemname`, `category` (nome) dos itens de valor, exceto item de curso/categorias | `validate_context` | inventário leve dos itens |
| `mod_quiz_get_quizzes_by_courses` | com `mod/quiz:view`: `timeopen`, `timeclose`, `attempts`, `timelimit`, `grademethod`, `sumgrades`, `grade`, `preferredbehaviour`, `hasfeedback`; sem bloqueio de acesso: `hasquestions`, `completionattemptsexhausted`, `completionpass`, `navmethod`, revisão…; com `manageactivities`: `shuffleanswers`, `password`, `subnet` | `mod/quiz:view`; `manageactivities` | itens 13, 14; X-CONT-01 (`hasquestions`, sem contagem) |
| `mod_quiz_get_quiz_access_information` | motivos de bloqueio para o usuário do token | `mod/quiz:view` | teste de acesso |
| `mod_feedback_get_feedbacks_by_courses` + `get_items` | via `feedback_summary_exporter`; campos opcionais só com `mod/feedback:edititems` (inclui `completionsubmit`, `anonymous`, `timeopen/close`) | `edititems` p/ campos completos | itens 11, 22 |
| `mod_forum_get_forums_by_courses` | `type`, `name`, `intro`, `duedate`, `cutoffdate`, `completiondiscussions/replies/posts`, `grade_forum`, `cmid` | `mod/forum:viewdiscussion` | item 9, 17 |
| `mod_page_get_pages_by_courses`, `mod_label_get_labels_by_courses`, `mod_url_get_urls_by_courses` (`externalurl`, `display`), `mod_resource_get_resources_by_courses` (`contentfiles` com `fileurl`, `filesize`, `mimetype`), `mod_folder_…`, `mod_book_get_books_by_courses`, `mod_glossary_get_glossaries_by_courses`, `mod_data_get_databases_by_courses`, `mod_choice_get_choices_by_courses`, `mod_assign_get_assignments` | padrão `helper_for_get_mods_by_courses`: `id`, `coursemodule`, `course`, `name`, `intro`, `introfiles`; `section`, `visible`, `groupmode` só com `manageactivities`; + campos da tabela do módulo | capacidade de visualização do módulo | conteúdo e configuração por tipo |
| `mod_glossary_get_entries_by_letter(id, 'ALL', from, limit)` | `count` total de entradas | `mod/glossary:view` | item 18 (glossário vazio) |
| `mod_book` via `get_contents.contents` | `structure` (JSON de capítulos: `title`, `href`, `level`, `hidden`), `index.html` por capítulo (corpo via `fileurl`), arquivos do capítulo; capítulos ocultos só com `mod/book:viewhiddenchapters` | — | conteúdo de livros (2 requisições extras por capítulo) |
| `core_completion_get_activities_completion_status(courseid, userid)` | por módulo visível ao usuário: `state`, `tracking`, `hascompletion`, `isautomatic`, `details[]` (`rulename`, `rulevalue.status/description`), `uservisible`, `isoverallcomplete` | `report/progress:view` para outro usuário | critérios efetivos de conclusão (usuário de teste) |
| `core_completion_get_course_completion_status` | `completed`, `aggregation`, `completions[]` | `completion_can_view_data` | conclusão de curso (não usada: `completion.xml` vazio nos cursos) |
| `core_badges_get_user_badges`, `core_files_get_files`, `tool_mobile_call_external_functions` | badges do usuário; navegação de arquivos; **lote de várias funções em uma requisição** | — | apoio |

**Sem Web Service de leitura útil:** `mod_simplecertificate` (apenas `mod_simplecertificate_verify_code`, serviço desabilitado por padrão), `mod_game` (diretório `db/` sem `services.php`), `mod_hvp` (sem `db/services.php`; só suporte móvel via `mobile.php`), `block_completion_progress` (configs dependem de `get_config_for_external()`, não confirmado), `availability_days` (não há função que liste plugins de disponibilidade). Nenhuma função devolve a lista de plugins instalados no site; `core_webservice_get_site_info.functions` lista apenas funções do serviço.

**Funções que escrevem e ficam fora da auditoria:** todas as `*_view_*` (disparam eventos e podem marcar conclusão), `core_completion_update_activity_completion_status_manually`, `enrol_self_enrol_user`, `mod_quiz_start_attempt/process_attempt`, `mod_feedback_launch_feedback/process_page`, `core_course_update_courses/edit_module`.

---

## 3. Matriz comparativa de cobertura (29 itens + regras adicionais)

Cobertura: **C** completa · **P** parcial · **ND** não disponível · **NV** não verificada (não aplicável/externo). "Teste funcional" = exige ação de um usuário de teste ou navegador. Tudo em "API nativa" e "plugin" é [DOC]; "MBZ" é [EXEC] (protótipo).

| Regra | MBZ | API nativa (função → campo) | Plugin personalizado | Teste funcional | Melhor abordagem |
|---|---|---|---|---|---|
| CL-01 Informações sobre o curso | C (estrutura) | C `mod_page_get_pages_by_courses.content` | — | humano (conteúdo) | igual; API na sala nova |
| CL-02 = projeto SIPAC | NV | NV | — | — | fonte externa |
| CL-03 professores | **ND** (sem usuários) | **C** `core_enrol_get_enrolled_users.roles` | — | — | **API** |
| CL-04 sumário + botões | C | C `get_courses_by_field.summary` | — | — | igual |
| CL-05 categoria | C (estado do backup) | **C** `get_courses_by_field.categoryid/categoryname` | — | — | **API** (sala nova) |
| CL-06/07 datas início/fim | C (estado do backup) | **C** `startdate/enddate` | — | — | **API** (sala nova) |
| CL-08 autoinscrição | C (`enrolments.xml` com datas e `customint6`) | **P** só tipo/status/nome habilitados; **sem datas** | C (`enrol` table) | inscrição real de teste (escrita; evitar) | MBZ + o próprio MOOC Admin (ele define as datas); plugin só se necessário |
| CL-09 fórum "Espaço de interação" | C | C `get_contents`/`get_forums_by_courses` | — | — | igual |
| CL-10 vídeos | P (inventário) | P (inventário idem) | — | rede (HEAD/oEmbed) | igual; fase de rede |
| CL-11 Pesquisa de Perfil + certificado | C | **C** `get_contents.availability` + `get_feedbacks_by_courses.completionsubmit` (edititems) | — | conclusão com usuário de teste | igual |
| CL-12 soma 100 / agregação | C (itens + `aggregation`, `aggregateonlygraded`) | **P** `gradereport_user_get_grade_items` (`grademax`, `categoryid`, `weightraw`) — exige ≥1 usuário inscrito; **sem agregação** | C (`grade_categories`) | — | **MBZ** (agregação não muda na restauração) |
| CL-13 tentativas (+ enunciado) | C | C `get_quizzes_by_courses.attempts`, `intro` | — | — | igual |
| CL-14 encerramento avaliativas | C (quiz, assign, game, data) | **P** quiz/assign/forum/data sim; **game e hvp não** | C | — | MBZ; API para nativos |
| CL-15 restrições das avaliações | C | C `availability` JSON | — | `uservisible`/`availabilityinfo` com usuário de teste | igual + teste |
| CL-16 Avaliação exige avaliativas | C | C `availability` + itens de nota | — | — | igual |
| CL-17 conclusão automática | C | **C** `get_contents.completion` + `get_course_module` (critérios); critérios específicos de game/hvp **ND** | P | `get_activities_completion_status.details` | igual + teste |
| CL-18 glossário (vazio) | C (0 entradas no pacote) | **C+** `get_glossaries_by_courses` + `get_entries_by_letter.count` (estado real pós-restauração) | — | — | **ambos** (MBZ antecipa; API confirma) |
| CL-19 rótulos sem conclusão | C | C | — | — | igual |
| CL-20 hiddensections / seções | C | C `courseformatoptions`, `get_contents` | — | — | igual |
| CL-21 blocos padrão | P (3 blocos; "Acessibilidade" nunca aparece) | **C+** `core_block_get_course_blocks` inclui blocos herdados do site; `configs.title` do HTML; blocos ocultos omitidos | — | — | **API** |
| CL-22 licença (rótulo, feedback, declaração) | C | C nomes via `get_contents`; texto via `get_labels_by_courses` | — | — | igual |
| CL-23 certificado: Avaliação + 60 | C | C `availability` + `gradereport_user_get_grade_items` (item do curso `itemtype=course`) | — | emissão de teste | igual |
| CL-24 assinatura (imagens) | P (hash) | **ND** (simplecertificate sem WS; sem `export_contents`) | C | visual | **MBZ** (hash) |
| CL-25 texto/emissão do certificado | P (texto) | **ND** | C (config) | **sim** (emissão manual/navegador em homologação; `verify_code` valida o código emitido) | MBZ (texto) + teste |
| CL-26 padrão visual | P | P `overviewfiles`, `summary` das seções | — | humano | igual |
| CL-27 capa = vitrine | NV | NV | — | — | externo |
| CL-28/29 URL, vitrine | NV | NV | — | — | externo |
| X-INT-01 integridade do pacote | **C** | ND (só existe após restaurar) | — | — | **MBZ** |
| X-INT-02 arquivos sem conteúdo | C (no pacote) | **C+** `contents[].fileurl` + HEAD com token → 404/tamanho (estado no servidor) | — | HEAD = teste leve | **ambos** |
| X-INT-03 plugins exigidos × instalados | C (exigidos) | **P** `get_course_content_items` (tipos disponíveis); módulos ausentes no `get_contents` após restaurar; **plugins de disponibilidade e blocos: ND** | C | — | **ambos** |
| X-INT-04 inforef | C | ND | — | — | MBZ |
| X-DEP-01..05 grafo de restrições | C | **C** mesmo JSON (cmids novos) | — | `details` da conclusão | igual |
| X-CERT-01 alcançabilidade | C (estática) | C (estática) | — | **sim** (usuário de teste) | igual + teste |
| X-CONT-01 recursos vazios | C | C páginas/rótulos; `hasquestions` (sem contagem); livros via `index.html` | — | — | igual |
| X-CONT-02 duplicados | C | C | — | — | igual |
| X-CONT-03 links absolutos com id antigo | C | C (conteúdo) | — | — | MBZ (antes de propagar) |
| X-CONT-04 `@@PLUGINFILE@@` quebrado | C | **C** URLs reescritas + HEAD | — | HEAD | ambos |
| X-CONT-05/06, X-ACC-01/02, X-AV-01, X-STR-01 | C | C (conteúdo/estrutura via API) | — | — | igual |
| X-AV-04 `aggregateonlygraded` | C | **ND** | C | — | **MBZ** |
| X-BLK-01 bloco de progresso × atividades | C | **ND**/P (configs dependem do bloco) | C | — | MBZ |
| X-SEC-01 senhas de inscrição | C (valor presente) | C (`enrolpassword` só indica exigência — melhor para privacidade) | — | — | API p/ relatório |

**Leitura da matriz:** 31 regras com cobertura equivalente; 6 em que a API é melhor (CL-03, 05, 06/07, 21, X-INT-02, X-INT-03/CL-18 no estado real); 8 em que só o MBZ cobre (CL-08 datas, CL-12 agregação, CL-14 game/hvp, CL-24, CL-25 texto, X-INT-01, X-INT-04, X-AV-04, X-BLK-01); 4 que exigem teste funcional além de qualquer leitura (CL-15/23/X-CERT-01 com usuário de teste; CL-25 emissão).

---

## 4. Lacunas que exigiriam plugin personalizado (ou outra fonte)

| Lacuna | Por que a API nativa não cobre | Alternativa sem plugin | Veredito |
|---|---|---|---|
| Datas e `customint6` da autoinscrição | `get_course_enrolment_methods` e `enrol_self_get_instance_info` não expõem `enrolstartdate/enrolenddate/customint6` | o MOOC Admin **define** esses valores na reoferta: ele conhece o que gravou; no backup, `enrolments.xml` | sem plugin |
| Agregação e "excluir vazias" da categoria raiz | `get_grade_tree`/`get_gradeitems`/`gradereport_user` não devolvem `aggregation`/`aggregateonlygraded` | valores não mudam na restauração → ler do MBZ | sem plugin |
| Configuração e imagens do `simplecertificate` | plugin só tem `verify_code` | MBZ (`simplecertificate.xml`, hashes em `files.xml`) | sem plugin |
| Campos de `mod_game`/`mod_hvp` (encerramento, fonte, conclusão) | sem WS | MBZ | sem plugin |
| Configuração do `block_completion_progress` | depende de `get_config_for_external()` do bloco | MBZ | sem plugin |
| Lista de plugins instalados (mod/block/availability/qtype) | não há função de inventário | `get_course_content_items` (mods), módulos ausentes pós-restauração; para `availability_days`: comparar `availability` do curso com a presença do tipo no relatório de restauração… **indireto** | plugin **somente** se a prova mostrar que a inferência falha |
| Emissão de certificado | sem WS | teste em homologação (navegador) | não é problema de API |

Conclusão: **não propor `local_mooc_audit` na primeira versão**. Reavaliar após a prova em homologação; se vier a existir, deve expor uma única função de leitura (`local_mooc_audit_get_course_snapshot`) com exatamente estes seis blocos, protegida por capacidade própria.

---

## 5. Catálogo adicional — verificações só possíveis na sala ativa

Separação: **T** = problema técnico comprovável · **R** = recomendação (UX/pedagógica). FP = risco de falso positivo.

| ID | Verificação | Como (API) | Tipo | FP | Valor |
|---|---|---|---|---|---|
| ENV-01 | **Restauração completa**: todo módulo do inventário MBZ existe na sala (por `modname`+nome+seção) | `get_contents(includestealthmodules)` × inventário do `.mbz` | T | baixo | alto — detecta módulos descartados por plugin ausente ou erro |
| ENV-02 | Plugin de atividade ausente no destino | `get_course_content_items` × `modname`s do MBZ | T | baixo | alto |
| ENV-03 | Arquivo quebrado no servidor | HEAD em `contents[].fileurl` (token) → status/`Content-Length` ≠ `filesize` | T | baixo | alto |
| ENV-04 | Diferença entre visão administrativa e do estudante | `get_contents` com token de usuário de teste: `uservisible`, `availabilityinfo`; seção `-1` (stealth) | T | baixo | alto |
| ENV-05 | Condição de disponibilidade de plugin ausente (`days`) ignorada silenciosamente | `availability` ainda cita o tipo, mas `availabilityinfo` do estudante não o menciona / módulo liberado sem a condição | T | médio | alto |
| ENV-06 | Conclusão efetiva por critério | `get_activities_completion_status(userid_teste).details` após o usuário de teste agir (manualmente) | T | baixo | alto, mas exige ações |
| ENV-07 | Blocos de site exigidos presentes e visíveis na página do curso | `get_course_blocks` (lembrar: ocultos são omitidos) | T | baixo | médio |
| ENV-08 | Configurações globais que afetam a sala | `get_site_info.advancedfeatures` (`enablecompletion`, `enablebadges`); `enableavailability` inferido pela presença de `availability` em `get_contents` | T | baixo | médio |
| ENV-09 | Permissões insuficientes da conta de auditoria | campos ausentes nas respostas → regra em "não verificável" com a capacidade faltante | T | — | operacional |
| ENV-10 | Emissão do certificado | sem API; roteiro em homologação com usuário de teste + `verify_code` | T | — | alto |
| ENV-11 | Recursos interativos não renderizam (H5P sem biblioteca, jogo sem fonte) | sem API; navegador em homologação; indiretamente: glossário com `count=0` (ENV via CL-18) | T | — | alto |
| ENV-12 | Curso visível e categoria/inscrição ativas na data de lançamento | `get_courses_by_field.visible`, `get_course_enrolment_methods` | T | baixo | alto |
| ENV-13 | Datas das atividades coerentes com a nova oferta | `get_contents.dates`, `get_quizzes_by_courses.timeclose` × `enddate` | T | baixo | médio |
| ENV-14 | Usuário de teste consegue chegar ao certificado | encadear ENV-04/06/10 | T | — | o teste final |
| ENV-15 | Seções com muitos itens / nomes genéricos / ordem | `get_contents` | R | — | baixo |

---

## 6. Prova comparativa

**Não executada** (sem Moodle de homologação nem token nesta etapa). Entregues: `poc/moodle_api_client.py` (cliente REST somente leitura com allowlist de 29 funções, HTTPS obrigatório, sem redirecionamentos, token por variável de ambiente, registro de chamadas, retrato JSON no mesmo formato do inventário MBZ; sintaxe validada e achatamento de parâmetros testado offline) e `poc/plano-prova-comparativa-api.md` (14 regras, chamadas, campos, capacidades, critérios de sucesso, parte funcional com usuário de teste).

Expectativas a confirmar na prova [HIP]: (a) `availability` retornado é byte a byte igual ao do backup com os cmids remapeados; (b) `gradereport_user_get_grade_items(userid=0)` vazio em sala sem inscritos; (c) `get_course_blocks` lista o bloco "Acessibilidade" (se for de site) e omite blocos ocultos; (d) HEAD nos `fileurl` dos 4 PDFs ausentes do curso 670 devolve erro, confirmando que o backup antecipa o problema real.

---

## 7. Segurança e integração com o MOOC Admin

- **Conta de serviço** dedicada (`mooc_audit_ws`), autenticação "web services", papel personalizado com as capacidades do plano (todas de leitura), atribuído na **categoria** dos MOOC — controla o acesso por curso sem conceder site-admin. Sem `moodle/site:config`, sem capacidades de escrita.
- **Serviço próprio** ("MOOC Audit read-only") com apenas as funções da allowlist; token por serviço, com IP restrito (campo "IP restriction" do token) e validade; rotação anual junto com o ciclo de reoferta.
- **Transporte:** HTTPS obrigatório (o cliente recusa `http://`), sem seguir redirecionamentos, timeout curto, `User-Agent` identificável.
- **Credenciais no MOOC Admin:** token em configuração cifrada (Laravel `encrypted` cast / `.env` fora do repositório), nunca em log; o cliente só lê de variável de ambiente.
- **Auditoria das consultas:** registrar função, parâmetros (sem token), duração e código de retorno por execução (`calls[]` do cliente); o Moodle também registra em `logstore` as chamadas de WS.
- **Minimização de dados pessoais:** `userfields=id,fullname` em `get_enrolled_users`; não persistir listas de alunos; usuário de teste próprio para ENV-04/06; `enrolpassword` como booleano.
- **Limites:** ~25 chamadas por curso; usar `tool_mobile_call_external_functions` para lote; fila com concorrência 1–2 e espaçamento; horário fora do pico de restaurações.
- **Somente leitura:** nenhuma função `*_view_*`, `*_update_*`, `*_enrol_*`, `start/process_attempt`; a verificação funcional com usuário de teste é feita por pessoa (ou navegador em homologação), não pelo auditor.
- **Banco de produção:** não recomendado. As APIs cobrem o necessário pós-restauração; as lacunas são supridas pelo MBZ e pelo próprio MOOC Admin. Acesso direto ao BD criaria acoplamento ao esquema interno, exigiria rede privilegiada e tornaria a auditoria dependente de versão do Moodle.

---

## 8. Decisão arquitetural

| Critério | A. Só MBZ | B. API nativa prioritária | C. API de plugin próprio | D. Híbrida (MBZ + API nativa) |
|---|---|---|---|---|
| Cobertura das 29 + extras | 17 itens A + extras; nada do estado real | ~22 itens, mas perde integridade pré-restauração, datas de inscrição, agregação, certificado, game/hvp | como B + lacunas, ao custo de um plugin a manter no Moodle | **máxima**: tudo de A no backup; estado real, blocos de site, professores, arquivos no servidor via API |
| Confiabilidade | determinística, offline, reproduzível | depende de capacidades do token, de usuários inscritos (notas) e da disponibilidade do Moodle | idem + versão do plugin | a do MBZ para o que é estrutural; a da API para o que só a sala real sabe |
| Custo de desenvolvimento | 1 leitor + regras (já especificado) | 1 cliente + mapeamento de ~30 funções + tratamento de permissões | B + plugin Moodle (PHP, instalação, atualização a cada versão) | A + cliente para ~12 regras, reaproveitando o motor |
| Operação | nenhuma dependência externa | conta de serviço, token, serviço habilitado, rede | idem + deploy no Moodle | conta de serviço + token (sem plugin) |
| Manutenção | formato `moodle2` é estável há 10+ anos | funções estáveis desde 3.x; campos variam por capacidade | plugin acompanha upgrades do Moodle | duas superfícies, mas ambas estáveis |
| Momento | antes/durante a reoferta | só depois de restaurar | só depois | antes (MBZ) e depois (API) |
| Risco principal | não vê o que o MOOC Admin/Moodle mudam | descobre problemas tarde; não vê o pacote | manutenção de plugin | disciplina para não duplicar regras |

**Recomendação: D, com um único motor de regras e dois adaptadores**, e **sem plugin** na primeira versão.

```
                 ┌────────────── Motor de regras (PHP, núcleo sem framework) ──────────────┐
                 │  Regra = f(CourseSnapshot, Policy) → Finding[]  (mesma lógica p/ ambos)   │
                 └───────────▲───────────────────────────────────────▲──────────────────────┘
                             │ CourseSnapshot                        │ CourseSnapshot
                 ┌───────────┴───────────┐               ┌───────────┴─────────────┐
                 │ Adaptador MBZ          │               │ Adaptador Moodle WS      │
                 │ leitor + normalizador  │               │ cliente REST (allowlist) │
                 │ (integridade, plugins, │               │ get_contents, courses,   │
                 │  inforef, agregação,   │               │ blocks, enrol, grades,   │
                 │  certificado, game/hvp)│               │ quiz, feedback, files…   │
                 └───────────▲───────────┘               └───────────▲─────────────┘
                        .mbz da origem                       sala restaurada (homolog/prod)
```

O `CourseSnapshot` é o modelo já usado pelo protótipo (curso, seções, módulos com `availability`/`completion`, itens de nota, arquivos, blocos, inscrições). Cada regra declara `momentos` (`backup`, `restored`) e `fontes_necessarias` (ex.: `gradebook.aggregation` só existe no snapshot MBZ → na sala, estado "não verificável por esta fonte"). Assim: zero duplicação de lógica; o relatório da sala restaurada reutiliza as evidências do backup para o que não muda.

---

## 9. Revisão do MVP e do roadmap

**MVP (fase 1) — inalterado:** adaptador MBZ + ~30 regras + política 2027 + JSON/HTML + fila + listagem no MOOC Admin. Acrescentar ao modelo de regra os campos `momentos` e `fontes_necessarias`, e ao `CourseSnapshot` a marca de origem (`mbz` | `moodle-ws`), para que a fase 2 não exija refatoração.

**Fase 2 (antecipada; antes era "fase 4") — verificação pós-restauração via API nativa:** cliente REST + conta de serviço + 12–15 regras no momento `restored`: CL-03, CL-05, CL-06/07, CL-08 (parcial + dados do MOOC Admin), CL-11/15/16/23 (restrições reais), CL-17, CL-18 (contagem real), CL-21 (blocos de site), ENV-01, ENV-02, ENV-03, ENV-04, ENV-08, ENV-12. Pré-requisito: prova em homologação (`poc/plano-prova-comparativa-api.md`).

**Fase 3 — validação complementar:** URLs/vídeos externos com proteção SSRF; hashes das artes do certificado; comparação com projeto estruturado.

**Fase 4 — testes funcionais com usuário de teste:** roteiro em homologação (ENV-06, ENV-10, ENV-11, ENV-14), leitura do resultado via `get_activities_completion_status` e `verify_code`; eventual plugin `local_mooc_audit` **só** se as lacunas da seção 4 se mostrarem relevantes.

**Integração no fluxo:** MOOC Admin → recebe `.mbz` → auditoria `backup` (bloqueantes aparecem antes de restaurar) → restauração → auditoria `restored` (mesmo relatório, segunda coluna) → pendências → reexecução. O MOOC Admin passa ao motor o que ele próprio configurou (datas de inscrição, categoria) para fechar a lacuna de CL-08 sem plugin.

---

## Respostas objetivas

**A auditoria via API simplifica o projeto ou adiciona complexidade?** Adiciona complexidade operacional (conta de serviço, token, capacidades, semântica por usuário, dependência do Moodle online) e **não reduz** o trabalho do leitor MBZ, que continua necessário. Compensa apenas para o conjunto restrito de verificações que só a sala real responde.

**Podemos substituir uma parte relevante da leitura do MBZ por consultas ao Moodle?** Não de forma vantajosa. A API cobre quase tudo que o MBZ cobre, mas chega tarde (só após restaurar), devolve menos em seis pontos (datas de inscrição, agregação, certificado, game/hvp, bloco de progresso, inventário de plugins) e custa mais para operar. O ganho real está em **complementar**: estado efetivo da sala, blocos de site, professores, arquivos servidos, visão do estudante.

**Quais verificações precisam obrigatoriamente continuar no backup?** Integridade do pacote e do manifesto; arquivos referenciados sem conteúdo (antes de propagar); plugins exigidos; `inforef`; agregação e "excluir vazias"; configuração/texto/imagens do certificado; campos de `mod_game`/`mod_hvp`; configuração do bloco de progresso; links absolutos com ids antigos (para corrigir na origem); glossário vazio (para planejar o repovoamento antes do lançamento).

**Qual a arquitetura mais simples e confiável para o Cefor/Ifes?** Um motor de regras PHP dentro do MOOC Admin com dois adaptadores de leitura, MBZ e API nativa do Moodle, sem plugin Moodle e sem acesso ao banco: o backup garante o que é estrutural e antecipa problemas; a API nativa, com uma conta de serviço somente leitura, confirma o estado da sala nova em 12–15 regras; os testes funcionais ficam com um usuário de teste em homologação.
