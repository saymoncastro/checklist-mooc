# Auditoria automatizada de cursos MOOC a partir de backups Moodle (.mbz) — Relatório da investigação

**Data:** 2026-10-28 · **Escopo:** viabilidade, engenharia reversa dos backups, análise do checklist institucional (29 itens), descoberta de regras adicionais, prova de conceito, arquitetura e roadmap.

**Materiais examinados** (diretório do projeto):

| Arquivo | Tamanho | O que é |
|---|---|---|
| `Modelo - Checklist Técnico de Sala Virtual MOOC.xlsx` | 37 KB | Planilha com 1 aba ("Oficial"), 29 itens em 8 blocos |
| `backup-moodle2-course-55-empp-20261028-1455-nu.mbz` | 13,2 MB | Curso 55 "Educador Maker: Primeiros Passos" (oferta 2020/2) |
| `backup-moodle2-course-55-empp-20261028-1457-nu.mbz` | 13,2 MB | Mesmo curso, gerado 2 min depois (idêntico, exceto timestamps) |
| `backup-moodle2-course-668-cc_mooc_2026-20261014-1555-nu.mbz` | 49,1 MB | Curso 668 "Como criar um MOOC?" (2026) — **arquivo corrompido** |
| `backup-moodle2-course-670-acessibilidetecnologias_2026-20261028-1501-nu.mbz` | 80,1 MB | Curso 670 "Acessibilidade e Tecnologia" (2026) |

O repositório do MOOC Admin **não** está na pasta; os requisitos de integração (Parte VII) foram escritos sem presumir sua implementação.

**Legenda de confiança usada em todo o relatório:**

- **[OBS]** observado diretamente nos arquivos (com caminho/campo/valor).
- **[TESTE]** comprovado pela execução do protótipo `poc/mbz_inspect.py` (saídas em `poc/resultados/`).
- **[DOC]** inferido da documentação/código-fonte do Moodle 4.5 (branch `MOODLE_405_STABLE`), citado na seção de referências.
- **[HIP]** hipótese ainda não verificada.
- **[INST]** depende de informação ou decisão institucional.

---

## Entregável 1 — Diagnóstico executivo

**Viabilidade geral: alta para auditoria estática; parcial para conformidade final da nova oferta.**

1. **O formato é legível e estável.** Os três backups foram gerados pelo mesmo Moodle 4.5.8+ (build 20251219) no formato `tar.gz` ("moodle2", `backup_version` 2024100700), sem dados de usuários (`-nu`). Toda configuração relevante (curso, seções, módulos, restrições de acesso, conclusão, notas, arquivos, blocos, inscrições, questões) está em XML simples e foi interpretada sem ambiguidade. [OBS]
2. **Dos 29 itens do checklist, 17 são automatizáveis diretamente no backup (classe A), 4 dependem de fonte complementar (B), 2 exigem o Moodle em execução (C), 3 são semiautomáticos (D), 2 são manuais (E) e 1 está indeterminado (F).** Detalhes no Entregável 3.
3. **O protótipo já encontrou problemas reais e reproduzíveis** [TESTE]: backup corrompido (668); 4 PDFs referenciados sem conteúdo no pacote (670, 15 referências); jogos H5P com conclusão manual e fora da restrição da "Avaliação do curso" (668); divergência entre o texto institucional de pontuação e o livro de notas (668); curso 2020 sem Pesquisa de Perfil, sem Licença para Capacitação, com glossário e com jogos sem data de encerramento (55); link absoluto com `courseid` antigo (55); 131 imagens sem `alt` nos três cursos; seções com nomes genéricos (670).
4. **Ganho operacional esperado:** das verificações do checklist, as de configuração (datas, inscrição, tentativas, notas, restrições, conclusão, glossário, rótulos, licença, certificado) representam a maior parte do tempo manual e passam a ser instantâneas e uniformes. Sobram para a equipe: conteúdo pedagógico, identidade visual, emissão de certificado de teste, vitrine e URL — e a triagem dos alertas.
5. **Limitações relevantes:** (a) o backup não contém usuários, papéis nem blocos de nível de site, e não sabe o que o MOOC Admin altera após a restauração; (b) várias regras do checklist são ambíguas ou estão desatualizadas em relação à prática dos cursos de 2026 (tentativas, texto do certificado, "Escolha a sua situação"); (c) plugins não nativos (`mod_simplecertificate`, `mod_game`, `mod_hvp`, `availability_days`, `block_completion_progress`) são dependências silenciosas da restauração.
6. **Riscos técnicos:** arquivos não confiáveis (zip bomb, path traversal, XXE) — mitigáveis com extração em streaming com limites e parser XML sem entidades; falsos positivos em regras por nome ou por heurística textual — mitigáveis com estados distintos ("Atenção" vs "Não conforme") e política parametrizada.
7. **Recomendação de abordagem:** implementar o motor como **módulo PHP/Laravel dentro do MOOC Admin**, executado em fila, com política institucional versionada por ciclo e um contrato JSON de resultados; o protótipo Python entregue serve de especificação executável e oráculo de testes. Primeira versão: ~30 regras determinísticas de alta confiança (Entregável 7). Auditoria em dois momentos: **do backup** (antes/durante a reoferta) e **pós-restauração** (datas, categoria, inscrição, blocos, certificado de teste).

---

## Parte I — Engenharia reversa do formato MBZ

### 4.1 Empacotamento e integridade

| Aspecto | Observação | Confiança |
|---|---|---|
| Formato físico | Os 4 arquivos são `gzip` (magic `1f 8b`) contendo `tar`; o `.mbz` do Moodle pode ser `zip` ou `tar.gz` (`mbz_packer` delega a `zip_packer`/`tgz_packer`); `tar.gz` é o padrão desde o 2.9 | [OBS]/[DOC] |
| Manifesto | Primeira entrada do tar é `.ARCHIVE_INDEX`: linha `Moodle archive file index. Count: N` seguida de `caminho\ttipo(d/f)\ttamanho\tmtime` por entrada. Permite verificar completude sem confiar no stream | [OBS]/[DOC] |
| Ordem interna | `activities/`, `badges.xml`, `completion.xml`, `course/`, `files/`, `files.xml`, `grade_history.xml`, `gradebook.xml`, `groups.xml`, `moodle_backup.xml`, `outcomes.xml`, `questions.xml`, `roles.xml`, `scales.xml`, `sections/`, `moodle_backup.log` (vazio). Consequência: uma truncagem em `files/` perde **todos** os XML raiz e as seções | [OBS] |
| Corrupção detectável | O 668 falha com `zlib: invalid literal/length/distance code` na entrada `files/74/748fb56d…`; o índice declara 6 685 entradas, 1 572 arquivos não existem e faltam `moodle_backup.xml`, `files.xml`, `gradebook.xml`, `questions.xml`, `sections/` (31 entradas). `gzip -t` também falha. O arquivo tem 49 MB; o índice implica ~131 MB descompactados | [OBS]/[TESTE] |
| Reprodutibilidade | Os dois backups do curso 55 diferem apenas nos `mtime` do `.ARCHIVE_INDEX` e em `backup_date`/`name`/`backup_id` de `moodle_backup.xml`; todo o resto é byte a byte igual. Logo, o hash do `.mbz` não identifica conteúdo; um hash do modelo normalizado identifica | [OBS] |
| Tamanhos | 55: 916 entradas/14 MB; 670: 757 entradas/94 MB (2 vídeos MP4 de 21 MB e PDFs); 668: 6 685 entradas/131 MB (4 332+ arquivos, bibliotecas H5P) | [OBS] |

### 4.2 Mapa da estrutura (o que está onde e o que é auditável)

| Elemento | Onde está | Informação recuperável (campos reais) | Possibilidade de auditoria |
|---|---|---|---|
| Identificação e origem | `moodle_backup.xml/information` | `moodle_release` 4.5.8+, `backup_version`, `original_wwwroot`, `original_course_id`, `original_course_fullname/shortname`, `startdate/enddate`, `include_files`, `backup_date` | Versão, origem, nome, período; detectar backup de outro site |
| Opções do backup | `moodle_backup.xml/settings` (`level=root`) | `users=0`, `anonymize=0`, `role_assignments=0`, `activities=1`, `blocks=1`, `files=1`, `questionbank=1`, `badges=1`, `calendarevents=1`, `userscompletion=0`, `logs=0`, `grade_histories=0`, `legacyfiles=1`… | Saber o que **não** está no backup (usuários, papéis, conclusões, logs) e marcar regras como não verificáveis |
| Conteúdo declarado | `moodle_backup.xml/contents` | lista de seções (`sectionid`, `title`, `directory`) e atividades (`moduleid`, `sectionid`, `modulename`, `title`, `directory`, `insubsection`) | Inventário rápido; cruzar com diretórios reais |
| Configurações do curso | `course/course.xml` | `fullname`, `shortname`, `summary` (HTML), `format=topics`, `startdate`, `enddate`, `visible`, `lang`, `enablecompletion`, `showcompletionconditions`, `category/{id,name}`, `tags`, `customfields`, `courseformatoptions` (`hiddensections`, `coursedisplay`) | Itens 4, 5, 6, 7, 20 do checklist; botões no sumário; textos institucionais |
| Métodos de inscrição | `course/enrolments.xml` | por `enrol`: `enrol` (manual/self/guest), `status` (0=ativo), `enrolstartdate`, `enrolenddate`, `customint6` (self: permitir novas inscrições), `password`, `roleid` | Item 8; alerta de visitante/senha |
| Padrões de conclusão | `course/completiondefaults.xml` | por tipo de módulo: `completion`, `completionview`, `completionusegrade`… | Explica defaults; apoio ao item 17 |
| Blocos do curso | `course/blocks/<nome>_<id>/block.xml` | `blockname`, `defaultregion`, `configdata` (base64 de serialização PHP: `title`, `text`, `selectactivities`), `block_positions` | Item 21 (parcial); consistência do bloco de progresso |
| Seções | `sections/section_<id>/section.xml` | `number`, `name`, `summary` (HTML com `@@PLUGINFILE@@`), `sequence` (cmids), `visible`, `availabilityjson` | Itens 20, 26; seções vazias/ocultas; nomes genéricos |
| Módulo (genérico) | `activities/<mod>_<cmid>/module.xml` | `modulename`, `sectionid`, `sectionnumber`, `visible`, `completion` (0/1/2), `completionview`, `completiongradeitemnumber`, `completionpassgrade`, `completionexpected`, `availability` (JSON), `version` do plugin | Itens 15, 16, 17, 19, 23; grafo de dependências; plugins |
| Módulo (específico) | `activities/<mod>_<cmid>/<mod>.xml` | quiz: `attempts_number`, `timeopen/timeclose`, `grade`, `sumgrades`, `grademethod`, `question_instances/question_set_reference`; feedback: `completionsubmit`, `items`, `anonymous`; url: `externalurl`; page: `content`; book: `chapters`; forum: `type`, `completion*`; choice: `options`, `completionsubmit`; game: `sourcemodule`, `glossaryid`, `grade`; hvp: `machine_name`, `json_content`; simplecertificate: `certificatetext`, `secondpagetext`, `certificateimage`, `secondimage`, `requiredtime`; assign: `duedate`, `cutoffdate`, `grade` | Itens 9–14, 18, 22, 25; conteúdo; coerência enunciado × configuração |
| Notas | `gradebook.xml` (categorias, item `course`, `grade_settings`) + `activities/*/grades.xml` (itens `itemtype=mod`: `grademax`, `gradetype`, `categoryid`, `aggregationcoef2`, `hidden`) | Categoria raiz `aggregation=13` (Natural), `aggregateonlygraded` (1 no curso 55, 0 no 670), `grademax=100` do item do curso | Item 12; pesos; itens ocultos; alvo das condições de nota |
| Restrições de acesso | `availability` (módulo) e `availabilityjson` (seção) | JSON `{"op":"&|\|!&|!\|","c":[{type:completion,cm,e}|{type:grade,id,min,max}|{type:days,d}|…],"showc":[…]|"show":bool}` | Itens 11, 15, 16, 23; grafo; ciclos; referências órfãs; plugins de condição |
| Critérios de conclusão | `module.xml` + campos `completion*` do `<mod>.xml` | ver acima; `completion.xml` raiz (conclusão de curso) está vazio nos 3 backups | Item 17/19; impossibilidades (condição exige conclusão de módulo com `completion=0`) |
| Arquivos físicos | `files.xml` (metadados: `contenthash`, `contextid`, `component`, `filearea`, `itemid`, `filepath`, `filename`, `filesize`, `mimetype`) + `files/xx/<contenthash>` + `activities/*/inforef.xml` (`fileref`) | conteúdo endereçado por SHA-1; diretórios têm `filename="."` e hash vazio `da39a3ee…` (sem conteúdo, por projeto) | Arquivos referenciados sem conteúdo; `@@PLUGINFILE@@` sem arquivo; imagens do certificado; capa do curso |
| Links internos | textos HTML | tokens `$@PAGEVIEWBYID*93302@$`, `$@COURSEVIEWBYID*…@$`, `$@PLUGINFILEBYCONTEXT*…@$` (remapeados na restauração) vs. URLs absolutas `https://mooc…/question/edit.php?courseid=55` (não remapeadas) | Links que perderão validade |
| Questões | `questions.xml` | categorias, `question_bank_entries` → `question_versions` → `question` (`qtype`, `name`, `questiontext`, `plugin_qtype_*`) | Questionários sem questões; tipos de questão não nativos |
| Badges | `badges.xml` | `name`, `status`, `criteria` com `module_<cmid>` | Referências a módulos (curso 55: 7 badges) |
| Plugins | atributo `version` em `module.xml`/`block.xml`; `activities/hvp_libraries.xml`; tipos de `availability`; `qtype` | `game` 2026051500, `simplecertificate` 2024051105, `hvp` 2026081300, `completion_progress` 2026042700, `days` | Dependências do ambiente de destino |
| Ausentes por opção | — | usuários, papéis/atribuições, conclusões de usuários, logs, histórico de notas, comentários, grupos (`groups.xml` vazio), conclusão de curso | Itens 3 e 25 não verificáveis no backup |

### 4.3 Semântica validada no código-fonte do Moodle 4.5 [DOC]

- **Árvore de disponibilidade** (`availability/classes/tree.php`): operadores `&`, `|`, `!&`, `!|`; `showc` por filho em `&`/`!|`, `show` único em `|`/`!&`; grupo vazio = disponível; **tipo de condição cujo plugin não existe é ignorado silenciosamente quando carregado em modo "lax"** (ou lança `coding_exception` fora dele). Logo, restaurar um curso com `{"type":"days"}` num Moodle sem `availability_days` **remove a exigência de dias sem aviso**.
- **Condição de nota** (`availability/condition/grade/classes/condition.php`): o escore é **percentual calculado sobre `rawgrademin/rawgrademax` da linha `grade_grades` do usuário**, com `min` inclusivo e `max` exclusivo. Com categoria Natural e `aggregateonlygraded=1` (curso 55), `rawgrademax` é a soma dos máximos **apenas dos itens já avaliados**; com `aggregateonlygraded=0` (curso 670), os vazios contam como zero sobre 100. A regra "60 pontos" só equivale a "60% de 100" se todos os itens tiverem nota — no curso 55 isso é garantido indiretamente porque a "Avaliação do curso" exige conclusão (nota recebida) de todos os itens avaliativos.
- **Arquivos ausentes** (`backup/util/helper/backup_file_manager.class.php` + `lib/filestorage/file_system_filedir.php`): o backup não verifica a existência do conteúdo; `copy_content_from_storedfile()` devolve o resultado de `copy()`, que é `false` quando o arquivo não existe no `filedir`, sem exceção nem log. **Isso explica os 15 registros sem conteúdo no backup 670**: os metadados vão para `files.xml`, o conteúdo não vai para `files/`.
- **tgz_packer**: escreve `.ARCHIVE_INDEX` primeiro; não o verifica na extração; nomes >100 caracteres são divididos em prefixo; arquivos >8 GiB são rejeitados; uma falha ao abrir um arquivo pode deixar o cabeçalho escrito e o arquivo corrompido sem erro.
- **Plugins**: `availability_days` ("Restriction by days offset from start", Valery Fremaux) tem no diretório oficial builds apenas até Moodle 3.5; o site roda 4.5, logo a instituição mantém uma versão própria/atualizada [INST]. `mod_simplecertificate` 4.0.7 declara suporte a 4.5; `mod_game` e `mod_hvp` não foram confirmados no diretório.

### 4.4 Restrições de segurança aplicadas na investigação

Inspeção somente de leitura: listagem prévia com `tar -tzvf` e varredura por caminhos absolutos ou `..` (nenhum encontrado); extração em diretório isolado da área de trabalho; parsing XML com rejeição de `DOCTYPE/ENTITY`; nenhum arquivo do backup executado; hashes MD5 dos originais conferidos antes e depois (inalterados). O protótipo implementa extração em streaming com limites (entradas, bytes por membro e totais), rejeição de links simbólicos/dispositivos e nomes inseguros.

---

## Entregável 2 — Inventário dos backups

### Curso 55 — "Educador Maker: Primeiros Passos" (EMPP) [OBS]/[TESTE]

| Campo | Valor |
|---|---|
| Origem | id 55, contexto 29183, `https://mooc.cefor.ifes.edu.br/moodle`, Moodle 4.5.8+, backup 2026-10-28 14:55 |
| Período / categoria | 2020-08-11 → 2020-12-31 23:59 · categoria "2020/2" (id 16) · `lang=pt_br` · visível |
| Seções (8) | 0 Área do curso (4) · 1 Boas-vindas e Mão na Massa! (8) · 2 Cultura Maker (12) · 3 Espaços que Inspiram (14) · 4 Mentalidade Maker (14) · 5 Cultura Maker na Educação (15) · 6 Compartilhe Práticas Maker na Educação (2) · 7 Certificado e conclusão (4); `hiddensections=1` |
| Módulos (73) | 21 label, 18 url, 10 page, 8 forum, 4 quiz, 4 game, 3 book, 1 simplecertificate, 1 glossary, 1 feedback, 1 data, 1 choice |
| Questionários | 4 × (`attempts_number=4`, `timeclose=2020-12-31`, 20 pontos, 4 questões cada, nota mais alta); enunciados declaram "4 tentativas" |
| Itens de nota | 4 quizzes × 20 + 4 games × 5 = **100**; categoria raiz Natural, `aggregateonlygraded=1`; pesos `aggregationcoef2` 0,20/0,05 coerentes |
| Restrições | `feedback_5419` "Avaliação do curso": conclusão de 10 cms (4 quizzes, 4 games, choice 13882, url 13888); `simplecertificate_5420`: conclusão de 5419 **e** nota ≥60 no item 1454 (total do curso) **e** `days=4` |
| Conclusão | 21 rótulos e 17 URLs sem conclusão; quizzes/games/forums de desafio/choice/data com automática; nenhuma manual |
| Recursos institucionais | "Informações sobre o curso", "Como realizar este curso…", "Avisos!", "Avaliação do curso", "Certificado digital de conclusão", "Escolha a sua situação junto ao Ifes" (choice) — **sem** "Pesquisa de Perfil", "Espaço de interação", Licença para capacitação |
| Blocos | completion_progress (12 atividades selecionadas), html "Atenção ao prazo!!!", html "Dúvidas técnicas? Podemos ajudar!" |
| Inscrição | manual; self (datas = curso, novas inscrições sim); **guest ativo com senha** |
| Plugins não nativos | mod_game 2026051500 (3 jogos usam o glossário 151 como fonte), mod_simplecertificate, availability_days, block_completion_progress |
| Arquivos | 239 registros (173 arquivos, 66 diretórios), 23,8 MB, todos presentes; 162 PNG, 11 JPEG |
| Questões | 63 em 14 categorias (truefalse 25, multichoice 17, match 10, shortanswer 7, gapselect 3, ddwtos 1) |
| Badges | 7, todas com critério por módulo |
| Particularidades | 18 iframes (14 YouTube, 4 SoundCloud); 18 URLs externas (vídeos, matérias, Google Forms exigido para a Avaliação); link `question/edit.php?courseid=55` em livro; 52 imagens sem `alt` |

### Curso 670 — "Acessibilidade e Tecnologia" (AcessibilideTecnologias_2026)

| Campo | Valor |
|---|---|
| Origem | id 670, contexto 490513, Moodle 4.5.8+, backup 2026-10-28 15:02, `include_file_references_to_external_content=1` |
| Período / categoria | 2026-01-01 → 2026-12-31 23:59 · "Inscrições Abertas" (id 6) · `lang` vazio |
| Seções (8) | 0 Área do curso (10) · 1 Conteúdos (5) · 2 **Topic 2** (7) · 3 **Tópico 3** (13) · 4 **Tópico 4** (4) · 5 **Topic 5** (22, banner "Material complementar") · 6 **Topic 6** (1, banner "Atividade avaliativa") · 7 **Topic 7** (4, banner "Certificado e conclusão"); todas com banner e `alt` |
| Módulos (66) | 27 page, 21 resource, 4 quiz, 4 label, 4 forum, 3 feedback, 2 simplecertificate, 1 book |
| Questionários | 4 × (`attempts=2`, fecham 2026-12-31): 4 + 4 + 2 + **90** pontos (final com 15 questões); enunciados declaram 2 tentativas |
| Itens de nota | soma **100**; Natural, `aggregateonlygraded=0` |
| Restrições | Avaliação (93360): conclusão dos 4 quizzes; Certificado (93361): Avaliação **e** nota ≥60 no item 73354 (total) **e** Pesquisa de Perfil (93304) **e** `days=9`; "Licença para capacitação" (feedback 93362): `!&` nota ≥1 (só aparece para quem ainda não tem nota) |
| Recursos institucionais | todos os da Área do curso presentes, cada página com versão **em Libras** de mesmo nome; rótulo + feedback + "Declaração licença para capacitação" presentes |
| Blocos | completion_progress (7 atividades), html "Atenção ao prazo!!!", html "Dúvidas técnicas?…" |
| Inscrição | manual; guest **desativado**; self (datas = curso, novas inscrições sim) |
| Arquivos | 127 registros (78 arquivos), 243 MB declarados (41 PDF, 2 MP4, 35 imagens); **15 registros (4 PDFs distintos) sem conteúdo** |
| Questões | 26 (truefalse 21, multichoice 5) |
| Particularidades | 12 pares de páginas com nome idêntico (Libras); 29 iframes YouTube; 6 hosts `http://`; 3 imagens sem `alt` |

### Curso 668 — "Como criar um MOOC?" (CC_MOOC_2026) — backup incompleto

| Campo | Valor |
|---|---|
| Integridade | **Corrompido**: erro de stream em `files/74/748fb56d…`; extraídos 4 765 de 6 685 itens; ausentes `moodle_backup.xml`, `files.xml`, `gradebook.xml`, `questions.xml`, `sections/`, 1 572 arquivos de conteúdo |
| Recuperável | `course/course.xml`, `enrolments.xml`, 4 blocos (inclui `online_users`), `completion.xml`, `badges.xml` (vazio), **74 atividades completas** (XML de configuração), 4 144 arquivos |
| Período / categoria | 2026-01-01 → 2026-12-31 · "Inscrições Abertas" |
| Seções | 10 (reconstruídas por `sectionid/sectionnumber` dos módulos; nomes desconhecidos) |
| Módulos (74) | 25 page, 9 book, 8 label, 7 quiz, 5 url, 4 hvp, 4 forum, 3 resource, 3 feedback, 2 simplecertificate, 2 folder, 1 data, 1 assign |
| Questionários | 7 × (`attempts=2`, fecham 2026-12-31): 10+10+10+10+**30**+10+10 = 90 pontos; enunciados declaram 2 |
| Itens de nota | quizzes 90 + 4 H5P (2+2+4+2) = **100**; assign com `gradetype=0` (sem nota); agregação não verificável (sem `gradebook.xml`) |
| Restrições | 7 quizzes exigem conclusão de páginas/livros do tópico; Avaliação (93226) exige os 7 quizzes (**não** os 4 H5P); Certificado (93227): Avaliação, nota ≥60 (item 73332), Pesquisa (93163), `days` 9 **duplicado** (`"9"` e `9`) |
| Conclusão | páginas/livros automática por visualização; **4 H5P com conclusão manual**; assign/data/fóruns de entrega automática |
| Blocos | completion_progress (46 atividades), 2 html, online_users |
| Particularidades | 198 iframes YouTube; 76 imagens sem `alt` + 21 com `alt=""`; 7 rótulos "Rótulo" idênticos; links a outros cursos do site por nome (`course/view.php?gamificacao-moodle`); texto do sumário diz que o questionário de Desenvolvimento vale **40** e os demais 10, mas o livro de notas tem 30 + 10 dos jogos |

**Dificuldades de interpretação:** no 668 só se chega aos nomes das seções pelo `moodle_backup.xml` ou `sections/`, ambos perdidos; o item de nota do curso (73332) é referenciado por condições mas não existe no pacote. Sem `files.xml` não se verifica nenhuma referência a arquivo. O curso deve ser **reobtido** antes de servir como referência.

---

## Entregável 3 — Matriz dos 29 itens do checklist

Classes: **A** automático via MBZ · **B** com fonte complementar · **C** após restauração · **D** semiautomático · **E** manual · **F** indeterminado. Confiança: alta/média/baixa. Prioridade: pelo impacto no processo (1 = MVP).

| Item | Descrição resumida | Classe | Evidência no MBZ | Regra proposta | Confiança | Prioridade |
|---|---|---|---|---|---|---|
| 1 | "Informações sobre o curso" atualizado conforme documento padrão | D | `page` "Informações sobre o curso" (`content`); sumário em `course.xml` | Verificar presença da página e dos 12 cabeçalhos do padrão (nome, descrição, carga horária, idioma, nível, público, requisitos, pré-requisitos, conteúdos, metodologia, avaliação, professores); conteúdo é humano | média (estrutura) | 2 |
| 2 | Informações iguais ao Projeto do Curso (SIPAC) | B | só o lado do curso | Comparação só é possível se o projeto for fornecido estruturado (nome, CH, conteúdos, professores); caso contrário E | baixa | 3 |
| 3 | Professores cadastrados = projeto; demais → estudante | C | `settings users=0, role_assignments=0` (sem atribuições) | Consulta à API/BD do Moodle no curso de origem ou restaurado | alta (pós-restauração) | 2 |
| 4 | Sumário replica Informações + botões Criar Conta/Turmas anteriores | A | `course.xml/summary` contém os dois `href` esperados nos 3 cursos | Presença dos links da política; similaridade texto sumário × página (heurística, só alerta) | alta (links) / baixa (texto) | 1 |
| 5 | Categoria "Inscrições abertas" | A (pós-restauração) | `course.xml/category/name` ("2020/2" no 55; "Inscrições Abertas" nos 2026) | Igualdade com a categoria da política; se o MOOC Admin define a categoria, auditar após restaurar | alta | 1 |
| 6 | Data de início correta (futura, ≥1 mês antes do fim) | A | `course.xml/startdate` | **decidido:** 01/01 do ano da reoferta (= ano seguinte ao da auditoria); reavaliar após a restauração | alta | 1 |
| 7 | Término 30/06 ou 31/12 | A | `course.xml/enddate` (31/12/2020; 31/12/2026) | **decidido:** sempre 31/12 do ano da reoferta; reavaliar após a restauração | alta | 1 |
| 8 | Autoinscrição habilitada com datas | A | `enrolments.xml` enrol `self`: `status`, `customint6`, `enrolstartdate/enddate` | status=0, customint6=1, datas definidas (e, por interpretação, iguais às do curso); alerta para guest ativo | alta | 1 |
| 9 | Fórum "Espaço de Interação" presente | A | `forum.xml/name` (presente no 670/668; ausente no 55) | nome canônico (o curso "Como criar um MOOC?" proíbe renomear itens da Área do curso, o que legitima a verificação por nome); conclusão desabilitada (item 17) | média | 1 |
| 10 | Vídeos funcionam e estão incorporados | B | iframes em `content/intro`; `url.xml/externalurl` | inventário offline (A); disponibilidade exige rede (fase 2, SSRF-safe); "embedado vs link" é sinal | média | 2 |
| 11 | Pesquisa de Perfil presente e exigida pelo certificado | A | `feedback` "Pesquisa de Perfil" + `simplecertificate/module.xml/availability` (`completion cm=93304` no 670) | existência; `completion=2`+`completionsubmit=1`; cmid presente nas condições do certificado | alta | 1 |
| 12 | Livro de notas soma 100 | A | `grades.xml` (itemtype mod, gradetype 1) + `gradebook.xml` (aggregation 13) | Σ grademax = 100; alertar se agregação ≠ Natural, subcategorias, itens ocultos, `aggregateonlygraded` | alta | 1 |
| 13 | Questionários com ao menos 2 tentativas | A | `quiz.xml/attempts_number` (4 no 55; 2 nos 2026) e enunciado | ≥2 (ou =2, conforme decisão); enunciado × configuração | alta | 1 |
| 14 | Atividades avaliativas com encerramento | A | `quiz/timeclose`, `assign/cutoffdate|duedate`, `game/timeclose`, `data/timeavailableto` | encerramento ≠ 0; alerta se ≠ fim do curso; H5P não tem campo | alta | 1 |
| 15 | Restrições das avaliações corretas; 1º quiz exige "Escolha a sua situação" | D | `availability` dos quizzes; `choice` só existe no 55 | regra do 1º quiz (A); "corretas" é humano; **regra possivelmente desatualizada** (ver ambiguidades) | média | 2 |
| 16 | Avaliação do Curso exige conclusão de todas as atividades que valem nota | A | `feedback/module.xml/availability` × itens de nota (668: faltam 4 H5P) | conjunto de cmids com nota ⊆ condições `completion` com `op=&` | alta | 1 |
| 17 | Conclusão automática em todo o curso | A | `module.xml/completion` (668: 4 H5P com 1=manual) | nenhum `completion=1`; alertar automática sem critério; critério adequado por tipo (ver ambiguidades) | alta | 1 |
| 18 | Sem glossário | A | `activities/glossary_*` (55 tem; jogos dependem dele); `glossary.xml` com **0 entradas** porque `glossary_entries` só é exportada com dados de usuário | **decidido:** glossário colaborativo proibido; glossário-fonte de jogos tolerado, mas glossário vazio usado por jogos é **bloqueante** (jogos restaurados sem palavras) | alta | 1 |
| 19 | Rótulos e materiais complementares sem conclusão | A | `label/module.xml/completion`; seção "Material complementar" | `completion=0` em labels e em módulos de seções "complementar" | alta | 1 |
| 20 | Seções excedentes suprimidas / ocultas invisíveis | A | `courseformatoptions/hiddensections=1` (3 cursos); `sequence` das seções | hiddensections=1; seções vazias/só rótulos → alerta; "poderiam ser suprimidas" é julgamento | alta (config) | 1 |
| 21 | 4 blocos padrão presentes | A (3 blocos) + **C** (Acessibilidade) | `course/blocks`: progresso + 2 html nos 3 cursos; **"Acessibilidade" ausente em todos** | presença por `blockname`/`title`; **decidido:** o bloco deve existir na sala; como não aparece em nenhum backup, verificação definitiva pós-restauração | média | 1 |
| 22 | Licença para Capacitação | A | rótulo na seção 0 com "Licença para capacitação", feedback homônimo, "Declaração licença para capacitação" (670/668 sim; 55 não) | presença dos 3 componentes; conteúdo do dropdown é humano | alta | 1 |
| 23 | Certificado exige Avaliação do Curso e 60 pontos | A | `simplecertificate/module.xml/availability` (`completion` Avaliação + `grade id=<item curso> min 60`) | ambos presentes, `op=&`, item de nota = total do curso; ver nuance de percentual (4.3) | alta | 1 |
| 24 | Assinatura atualizada (diretor-geral) | B | `files.xml` component `mod_simplecertificate` filearea `image` (frente/verso, `contenthash`) | comparar hash com a arte oficial vigente (parâmetro); senão não verificável | alta (se hash fornecido) | 2 |
| 25 | Emitir certificado de teste e conferir dados | C | `certificatetext`, `secondpagetext`, placeholders (`{CAMPUS}`, `{USERNAME}`, `{PROFILE_CPF}`, …) | texto × padrão (A, só alerta); emissão real exige Moodle + usuário de teste | média | 2 |
| 26 | Padrão visual (topo, divisores, imagens de seção) | D | `course/overviewfiles`, `<img>` nos `summary` das seções, rótulos com imagem | presença/ausência (A); conformidade estética é humana | média | 3 |
| 27 | Capa igual ao thumbnail da vitrine, sem texto | B | `overviewfiles` (hash) | comparar com a vitrine (WordPress) por hash/perceptual; "sem texto" exigiria OCR | baixa | 3 |
| 28 | URL única | E | — | fora do backup (CGTI) | — | 3 |
| 29 | Cadastro na vitrine | E | — | sistema externo (WordPress) | — | 3 |

**Síntese:** A = 17 (4, 5, 6, 7, 8, 9, 11, 12, 13, 14, 16, 17, 18, 19, 20, 22, 23) · B = 4 (2, 10, 24, 27) · C = 2 (3, 25) · D = 3 (1, 15, 26) · E = 2 (28, 29) · F = 1 (21, pelo bloco "Acessibilidade").

### Ambiguidades e contradições do checklist [INST]

> **Atualização 2026-10-28:** 9 das 10 ambiguidades foram decididas pela equipe; as decisões e seus efeitos estão em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` e já foram aplicadas à política do protótipo (término sempre 31/12, início 01/01 do ano seguinte ao da auditoria, tentativas ≥ 2, glossário-fonte tolerado mas vazio = bloqueante, bloco Acessibilidade verificado pós-restauração, texto do certificado dos cursos de 2026 oficializado, "Escolha a sua situação" retirada, H5P contam como avaliativas). Pendente: lista de nomes canônicos (item 10).

1. **Tentativas (item 13):** a descrição diz "ao menos duas"; o "como fazer" diz "coloque 2" e "enunciado informando apenas duas". O curso 55 tem 4 tentativas declaradas e configuradas. O protótipo parametriza `attempts_rule = min|exact`.
2. **Soma de 100 (item 12):** interpretado como soma dos `grademax` dos itens de valor sob agregação Natural (os três cursos somam 100). A nota efetiva do curso depende de `aggregateonlygraded` e de pesos; a condição "≥60" do certificado é percentual sobre o máximo do usuário (4.3).
3. **Datas e categoria (itens 5–8):** são exigências do ciclo, não do backup. Se o MOOC Admin as define na reoferta, devem ser auditadas **após** a restauração; no backup só cabe informar.
4. **Glossário (item 18):** o texto diz "para evitar problemas na reoferta" — parece limitação técnica do processo (entradas de usuários/aprovação), não regra pedagógica. No 55 os jogos `mod_game` **dependem** do glossário: removê-lo quebra 3 atividades avaliativas.
5. **Conclusão automática (item 17):** o critério "adequado" por tipo não está escrito. Prática observada: page/book/url/resource → visualização; quiz/game/hvp → nota recebida; feedback/choice → envio; forum de entrega → postagem; fóruns institucionais e rótulos → sem conclusão. O curso "Como criar um MOOC?" (página "Configurações comuns") confirma essa orientação.
6. **"Escolha a sua situação junto ao Ifes" (item 15):** exigida pelo checklist e pela página "Organizando o curso" do curso 668 ("não a exclua"), mas **nenhum dos cursos de 2026 — incluindo o próprio "Como criar um MOOC?" — a possui**. Ou a regra foi abandonada e o checklist está desatualizado, ou os cursos atuais estão em desacordo.
7. **Texto do certificado (item 25):** o checklist pede "…certifica que {USERNAME} participou do curso…"; os três cursos usam "…concluiu e foi aprovado(a) no curso…" e os de 2026 acrescentam "{PROFILE_CPF}". Um dos dois lados está desatualizado.
8. **Bloco "Acessibilidade" (item 21):** inexistente nos três backups (`course/blocks` só tem progresso e 2 HTML; o 668 tem também `online_users`). Provavelmente bloco de site; a regra pertence à auditoria pós-restauração ou ao ambiente.
9. **Item 16 vs. H5P:** "atividades que valem nota" inclui os jogos H5P do 668 (10 pontos), que não estão na restrição da Avaliação. Se a intenção for "apenas questionários", a regra deve dizê-lo.
10. **Item 9:** verificação por nome é legítima porque a página "Organizando o curso" proíbe renomear itens da Área do curso — mas o curso 55 (2020) tem fóruns equivalentes com outros nomes.

---

## Entregável 4 — Catálogo de regras adicionais (além do checklist)

Legenda de viabilidade: A/B/C/D como acima. "Comprovada" = o protótipo encontrou ocorrência real nos backups; "Oportunidade" = regra implementada ou proposta, sem ocorrência nos exemplos.

### 6.1 Integridade do backup

| ID | Problema detectável | Fonte de evidência | Viab. | FP | Valor | Prio. | Situação |
|---|---|---|---|---|---|---|---|
| X-INT-01 | Pacote truncado/corrompido; manifesto ≠ conteúdo; XML raiz ausentes | stream gzip/tar, `.ARCHIVE_INDEX`, presença de `moodle_backup.xml`… | A | nulo | bloqueante: evita restaurar lixo | 1 | **Comprovada** (668) |
| X-INT-02 | Arquivo referenciado em `files.xml` sem conteúdo em `files/` | `contenthash` × disco | A | nulo (diretórios `.` excluídos) | alto: links quebrados após restaurar | 1 | **Comprovada** (670: 15 refs, 4 PDFs, 1 deles usado em 7 lugares) |
| X-INT-03 | Plugins não nativos (módulo, bloco, condição, tipo de questão) | `version` em module/block.xml; `availability.type`; `qtype` | A | baixo (lista de nativos por versão) | alto: restauração omite ou altera regras silenciosamente | 1 | **Comprovada** (game, hvp, simplecertificate, days, completion_progress) |
| X-INT-04 | `inforef` aponta para `file id` inexistente | `inforef.xml` × `files.xml` | A | nulo | médio | 2 | Oportunidade (0 ocorrências) |
| X-INT-05 | Backup de outro site/versão incompatível | `original_site_identifier_hash`, `backup_version` × destino | B | nulo | médio | 2 | Oportunidade |
| X-INT-06 | Ausência de componentes por opção de exportação (`files=0`, `questionbank=0`…) | `settings` raiz | A | nulo | alto | 1 | Oportunidade (todos com `files=1`, `questionbank=1`) |

Diferença essencial: **integridade estrutural** (acima) não garante **restauração**: isso depende de plugins, versão, permissões e limites do destino (fase 4).

### 6.2 Integridade dos conteúdos

| ID | Problema | Evidência | Viab. | FP | Valor | Prio. | Situação |
|---|---|---|---|---|---|---|---|
| X-CONT-01 | Página/rótulo/livro sem texto nem mídia; quiz sem questões; feedback sem itens; resource/folder sem arquivo | `content/intro`, `question_instances`, `items`, `files.xml` por contexto | A | baixo (não usa tamanho de texto) | médio | 1 | Oportunidade (0) |
| X-CONT-02 | Nomes duplicados | `name` por tipo | D | **alto se só por nome** | baixo | 3 | **Comprovada como falso positivo**: no 670 os 12 pares são versões em Libras; no 668 os 7 "Rótulo" são iguais (divisores). Regra deve comparar conteúdo e sugerir sufixo "(Libras)" |
| X-CONT-03 | URL absoluta ao próprio Moodle com id numérico (não remapeada) | regex em textos | A | baixo | médio | 1 | **Comprovada** (55: `question/edit.php?courseid=55` em `book_9605`) |
| X-CONT-04 | `@@PLUGINFILE@@` sem arquivo no contexto | textos × `files.xml` | A | baixo (**atenção a `?time=` — gerou 7 FP na 1ª versão**) | médio | 1 | Oportunidade (0 após correção) |
| X-CONT-05 | Anos anteriores em campos institucionais (sumário, Informações, certificado) | regex anos < ano da reoferta | D | médio (referências bibliográficas) | baixo | 3 | Oportunidade |
| X-CONT-06 | Inventário de hosts externos; `http://` | hrefs/srcs | B | — | informativo | 2 | **Comprovada** (670: 6 hosts http) |
| X-CONT-07 | Links externos quebrados, vídeos indisponíveis | rede | B | médio (bloqueios, rate limit) | alto | 2 (fase 2) | Oportunidade |
| X-CONT-08 | Dependência de serviço externo obrigatória para certificado (ex.: Google Forms) | `url.externalurl` em cadeia de restrições | A | baixo | médio | 2 | **Comprovada** (55: `url_13888` → forms.gle exigido pela Avaliação) |

### 6.3 Coerência das avaliações

| ID | Problema | Evidência | Viab. | FP | Valor | Prio. | Situação |
|---|---|---|---|---|---|---|---|
| X-AV-01 | Enunciado × configuração (tentativas, pontos) | `intro` × `attempts_number`/`grademax` | A | baixo | médio | 1 | Oportunidade (enunciados coerentes nos 3) |
| X-AV-02 | Texto institucional × livro de notas | `course.summary` "Processo de avaliação" × itens | D | médio | médio | 2 | **Comprovada manualmente** (668: "Desenvolvimento vale 40 pontos" vs. 30 + jogos) |
| X-AV-03 | Quiz avaliativo com `grade=0`; item de nota oculto; `gradetype=0` em atividade "avaliativa" | `quiz.grade`, `grades.xml` | A | baixo | médio | 2 | Oportunidade (668: assign sem nota — intencional?) |
| X-AV-04 | `aggregateonlygraded=1` na categoria raiz (altera o significado de "60 pontos") | `gradebook.xml` | A | nulo | alto | 1 | **Comprovada** (55 = 1; 670 = 0) |
| X-AV-05 | Datas de abertura/fechamento incoerentes com o curso | `timeopen/timeclose` × `startdate/enddate` | A | baixo | médio | 2 | Oportunidade |
| X-AV-06 | Questões dependentes de plugins | `qtype` | A | nulo | médio | 2 | Oportunidade (só nativos) |

### 6.4 Dependências e restrições de acesso (grafo)

Representação adotada no protótipo: nós = módulos (+ seções); arestas = condições `completion` (A exige B); atributos = `e` (1 concluído, 0 não concluído, 2 aprovado, 3 reprovado), condições `grade`, `date`, `days`, operadores por nó. Semântica real do Moodle (4.3) respeitada: `|`/`!&` tornam o nó satisfazível por alternativas; `showc/show` só afetam exibição.

| ID | Problema | Viab. | FP | Valor | Prio. | Situação |
|---|---|---|---|---|---|---|
| X-DEP-01 | Condição aponta para cm inexistente | A | nulo | alto | 1 | Oportunidade (0) |
| X-DEP-02 | Condição exige conclusão de módulo com `completion=0` (impossível) ou oculto; `e=2/3` em módulo sem nota | A | nulo | bloqueante | 1 | Oportunidade (0) |
| X-DEP-03 | Condição de nota aponta para item inexistente | A | nulo | alto | 1 | Oportunidade (0; não verificável no 668) |
| X-DEP-04 | Ciclos | A | nulo | bloqueante | 1 | Oportunidade (0; grafos com até 8 nós/46 arestas no 668) |
| X-DEP-05 | Condições duplicadas/redundantes | A | nulo | baixo | 3 | **Comprovada** (668: `days` 2×) |
| X-DEP-06 | Condição `date` no passado / `days` incompatível com a duração | A | baixo | médio | 2 | Oportunidade |
| X-DEP-07 | Condições alternativas (`\|`) em que todas as alternativas são impossíveis | A | baixo | alto | 2 | Oportunidade |

### 6.5 Certificação e conclusão

| ID | Problema | Viab. | Situação |
|---|---|---|---|
| X-CERT-01 | Alcançabilidade estática: cadeia transitiva de conclusão a partir do certificado: todos os módulos existem, visíveis, com conclusão habilitada; tipos de condição listados | A | Oportunidade (3 cursos satisfazíveis estaticamente; a cadeia transitiva exige 11 módulos no 55, 6 no 670 e 42 no 668, porque lá cada questionário exige as páginas/livros do tópico) |
| X-CERT-02 | Certificado exige Pesquisa/Avaliação **e** essas dependem do certificado (ciclo) | A | coberto por X-DEP-04 |
| X-CERT-03 | Placeholders inválidos no texto; `{COURSENAME}`/`{CARGA_HORARIA}` ausentes; carga horária do texto ≠ "Informações" | D | Oportunidade (CH está no sumário: 30 h/60 h/60 h — comparável com o projeto) |
| X-CERT-04 | Imagens do certificado ≠ arte oficial (assinatura/template anterior) | B | ver item 24 |
| X-CERT-05 | `requiredtime` do simplecertificate × `days` (duas formas de exigir tempo) | A | Oportunidade (`requiredtime=0` nos 3; tempo via `days`) |

**O estudante consegue, em tese, obter o certificado?** Estaticamente demonstrável: existência e visibilidade da cadeia, conclusão habilitada em cada elo, ausência de ciclos, item de nota alvo = total do curso, soma = 100. **Não demonstrável sem execução:** que a nota ≥60 seja atingível (depende das questões, do `grademethod`, de `aggregateonlygraded`), que `days` esteja instalado e configurado (referência: início do curso ou inscrição é configuração global), que a feedback aceite envio (datas), que a conclusão por "visualização" dispare para páginas com `display` específicos, e a emissão em si (PDF, placeholders, campus). Isso exige usuário de teste no Moodle restaurado (fase 4).

### 6.6 Configurações da nova oferta

Todas auditáveis no backup (`course.xml`, `enrolments.xml`), mas **devem ser classificadas como "estado do backup" e reavaliadas após a restauração**, pois o MOOC Admin pode alterá-las. Regras: datas (CL-06/07), autoinscrição (CL-08), categoria (CL-05), visibilidade, `lang` vazio (670/668 vs `pt_br` no 55 — alerta informativo), guest ativo (55), referências ao ciclo anterior (X-CONT-05), `shortname` com sufixo de ano (`_2026`) coerente com a reoferta.

### 6.7 Estrutura e experiência

| ID | Problema | Viab. | Situação |
|---|---|---|---|
| X-STR-01 | Módulos ocultos; nomes vazios; seções com nome genérico | A | **Comprovada** (670: "Topic 2/5/6/7", "Tópico 3/4") |
| X-STR-02 | Seções vazias ou só com rótulos; seção oculta com atividades obrigatórias | A | Oportunidade |
| X-BLK-01 | Bloco de progresso referencia atividades inexistentes ou sem conclusão | A | Oportunidade (3 cursos ok; ids de instância não são portáveis entre restaurações) |
| X-STR-03 | Fragmentação excessiva (ex.: 22 recursos numa seção) | D | recomendação de usabilidade, não regra (670: seção 5 com 22) |

### 6.8 Acessibilidade e apresentação (sinais estáticos, não auditoria WCAG/eMAG)

| ID | Problema | Situação |
|---|---|---|
| X-ACC-01 | `<img>` sem `alt` | **Comprovada**: 55 = 52/52; 668 = 76 sem + 21 vazios /136; 670 = 3/20 (curso sobre acessibilidade, com banners de seção com `alt`) |
| X-ACC-02 | Texto de link genérico | **Comprovada** (668: "link") |
| X-ACC-03 | iframes sem `title`; vídeos sem evidência de legenda/Libras | Oportunidade (YouTube: só com rede) |
| X-ACC-04 | HTML inadequado (tabelas para layout, cabeçalhos fora de ordem, estilos inline de cor) | Oportunidade (D) |

---

## Entregável 5 — Resultados da análise dos backups

Execução: `python3 -I poc/mbz_inspect.py <mbz> --out …` em 2026-10-28, política `2027-decisoes-2026-10-28` (ano da reoferta 2027, momento "backup": regras de datas/categoria/inscrição produzem Atenção). Relatórios completos em `poc/resultados/curso-*.txt|json`.

| Backup | Resultados | Conforme | Não conforme | Atenção | Não verificável | N/A |
|---|---|---|---|---|---|---|
| 55 | 129 | 40 | 52 (1 bloqueante, 1 alta, 50 médias) | 28 | 9 | 0 |
| 670 | 108 | 50 | 17 (15 altas, 2 médias) | 31 | 9 | 1 |
| 668 | 114 | 52 | 25 (6 bloqueantes, 1 alta, 18 médias) | 16 | 21 | 0 |

(Os totais do 55 estão inflados pelas 23 ocorrências de imagens sem `alt` e pelas 4 datas dos jogos; o relatório gerencial deve agrupar por regra.)

### 5.1 Inconformidades comprovadas (evidência reproduzível)

**Curso 668 — "Como criar um MOOC?"**

1. **Backup corrompido** [TESTE]: `tar: Truncated input file`; `gzip -t` → "data stream error"; última entrada `files/74/748fb56dfc8d7293ebb8901126b7201c2840b3af`; `.ARCHIVE_INDEX` declara 6 685, faltam 1 572 arquivos + XML raiz. Gravidade **bloqueante**. Ação: gerar novo backup e transferir com conferência de hash (o arquivo no servidor pode estar íntegro; a cópia local não está).
2. **Jogos H5P com conclusão manual** (`activities/hvp_100895..100898/module.xml` `completion=1`) — contraria o item 17. Gravidade média.
3. **Avaliação do curso não exige os 4 jogos H5P avaliados** (`feedback_93226/module.xml` `availability` lista só os 7 quizzes; `grades.xml` dos H5P têm `grademax` 2/2/4/2) — item 16. Gravidade alta (10 pontos podem ficar sem ser feitos antes da avaliação; como a conclusão é manual, mesmo a exigência não os garantiria).
4. **Divergência texto × notas** (`course.xml/summary`: "o do tópico Desenvolvimento vale 40 pontos e os demais valem 10 pontos cada"; `quiz_93201/quiz.xml` `grade=30`; H5P somam 10). Gravidade média. Encontrada por leitura; a regra X-AV-02 precisa ser escrita para capturá-la.
5. **Condição `days` duplicada** no certificado (`simplecertificate_93227/module.xml`: `{"type":"days","d":"9"}` e `{"type":"days","d":9}`). Baixa.
6. **97 imagens sem `alt` útil** (76 sem atributo, 21 vazias), concentradas nos livros ("Livro Acessibilidade Digital" cap. 6: 37/37). Média.

**Curso 670 — "Acessibilidade e Tecnologia"**

7. **15 referências a 4 PDFs sem conteúdo no pacote** [TESTE]: `files.xml` ids 1466113–1466133 (ex.: "Manual de Acessibilidade em Documentos Digitais (1).pdf", 7,5 MB, `contenthash c238492a7e…`, referenciado em `page_93308`, `page_93309`, `book_93322`, `page_93333`, `page_93334`, `resource_93338`), "Livro - O Uso Pedagógico dos Recursos de TA (1).pdf" (14,9 MB, `resource_93342`), "Políticas públicas de educação inclusiva.pdf" (`resource_93357`), "Processo de inclusão…pdf" (`resource_93358`). Os hashes não estão nem em `files/` nem no `.ARCHIVE_INDEX` → o próprio Moodle não os empacotou, o que indica **conteúdo ausente no `filedir` de origem** [DOC]. Após restauração, esses recursos ficarão quebrados. Gravidade **alta**. Ação: verificar no curso de origem e reenviar os arquivos.
8. **Seções com nomes genéricos** ("Topic 2", "Tópico 3", "Tópico 4", "Topic 5", "Topic 6", "Topic 7") enquanto os banners trazem os títulos reais. Baixa (navegação, leitores de tela, índice do curso).
9. **3 imagens sem `alt`** (páginas "Quem somos", "Tipos de Deficiências e Barreiras"). Média.

**Curso 55 — "Educador Maker: Primeiros Passos" (oferta 2020)**

10. **Sem "Pesquisa de Perfil"**; certificado não a exige (`simplecertificate_5420/module.xml`). Item 11. Alta.
11. **Sem Licença para Capacitação** (rótulo, feedback, declaração). Item 22. Média.
12. **Glossário presente e vazio no backup** (`glossary_10899/glossary.xml`: 3 categorias, **0 entradas**, porque `moodle_backup.xml` tem `users=0` e o Moodle só exporta `glossary_entries` com dados de usuário) e **3 jogos dependem dele** (`game_11506`, `game_11509`, `game_11685`: `sourcemodule=glossary`, `glossaryid=151`, categorias 1–3). Restaurado assim, os três jogos avaliativos (15 pontos) ficam sem palavras. **Bloqueante** após a decisão institucional; o quarto jogo (`game_12974`, `sourcemodule=question`) não é afetado.
13. **4 jogos avaliativos sem data de encerramento** (`game_*/game.xml` `timeclose=0`). Item 14. Média.
14. **Primeiro questionário sem restrição à "Escolha a sua situação"** (`quiz_10076/module.xml` `availability=$@NULL@$`, choice 13882 existe). Item 15. Média.
15. **Sem fórum "Espaço de interação"** (fóruns com outros nomes). Item 9. Média — possível falso positivo de nome.
16. **Link absoluto com id antigo**: `book_9605` capítulo 3 → `https://mooc.cefor.ifes.edu.br/moodle/question/edit.php?courseid=55`. Média.
17. **Datas/categoria de 2020** (esperado na auditoria de um backup antigo; ilustra a necessidade de separar "estado do backup" de "estado da reoferta").
18. **52/52 imagens sem `alt`**. Média.
19. **Acesso de visitante ativo com senha em texto claro** no backup (`enrolments.xml`). Informativo/segurança.

### 5.2 Suspeitas (não comprovadas) e pontos de atenção

- **Texto do certificado** difere do padrão do checklist nos três cursos ("concluiu e foi aprovado(a)", `{PROFILE_CPF}`); similaridade 0,55. Pode ser o checklist que está desatualizado.
- **Bloco "Acessibilidade"** ausente nos três backups: provavelmente não é bloco de curso.
- **"Escolha a sua situação"** ausente nos cursos de 2026 (inclusive no curso que ensina a regra).
- **`aggregateonlygraded=1`** no 55: muda o significado de "60 pontos" (ver 4.3); protegido pela cadeia da Avaliação, mas frágil.
- **Vídeo externo obrigatório**: no 55, a Avaliação exige "visualizar" o `url_13888` (Google Forms) — dependência de serviço externo de 2020.
- **Sumário × "Informações"** (similaridade 0,55 no 55): heurística, só alerta.
- **Hosts `http://`** (670: 6) podem ser bloqueados como conteúdo misto.
- **`lang` vazio** nos cursos de 2026 vs `pt_br` no 55.

### 5.3 Verificações inconclusivas

- No 668: soma de notas por agregação, item total do curso, referências de arquivos, capa/imagens, nomes de seções — tudo por ausência de arquivos no pacote (marcado como "não verificável", não como falha).
- Itens 2, 3, 24, 25 (emissão), 27, 28, 29 — por dependerem de fontes externas ou do Moodle em execução.
- Funcionamento de vídeos/links (sem rede nesta fase).

### 5.4 Falsos positivos aprendidos durante a prova de conceito

- Duplicatas por nome → versões em Libras (670) e rótulos divisores (668).
- `@@PLUGINFILE@@/arquivo.png?time=…` → sufixo de cache gerava 7 "arquivos ausentes" inexistentes.
- Hash vazio `da39a3ee…` dos diretórios em `files.xml` → não é arquivo ausente.
- Páginas "Informações sobre o curso" em Libras → não têm os cabeçalhos do padrão (por desenho).
- Títulos de blocos: `configdata` é serialização PHP com comprimentos em **bytes**; decodificar como UTF-8 antes de desserializar quebra os títulos acentuados.

### 5.5 Respostas às 10 perguntas da Parte IV

1. **Regras avaliáveis diretamente por MBZ:** das 51 regras do protótipo, produziram resultado conclusivo (conforme/não conforme/atenção) 45 no curso 55, 44 no 670 e 40 no 668 (corrompido); entre as 29 regras derivadas do checklist, 23, 22 e 22 respectivamente (as demais ficaram "não verificável" por dependerem de fonte externa ou do Moodle em execução).
2. **Regras que exigem informação externa:** 2 (projeto SIPAC), 10 (rede), 24 (arte oficial), 27/29 (vitrine), 28 (URL), política de ciclo (ano, datas, categoria, nota mínima, tentativas).
3. **Informações ausentes dos backups:** usuários, papéis, conclusões, logs, blocos de site, configurações globais (inclui referência do `days`), plugins instalados no destino, estado da vitrine/URL.
4. **Divergências estruturais entre cursos:** 55 (2020) usa choice + Google Forms + games/glossário + badges, sem Pesquisa/Licença; 2026 usam H5P/feedbacks/declaração, páginas em Libras, sem choice; 668 tem assign sem nota e `online_users`; `aggregateonlygraded` difere; `lang` difere.
5. **Regras que funcionam num backup e não em outro:** por nome (item 9 falha no 55 por nomes diferentes); item 15 (sem choice nos 2026); item 14 (H5P não tem data de encerramento); qualquer regra com `files.xml` no 668.
6. **Problemas reais encontrados:** sim — lista 5.1 (19 achados).
7. **Comprovados:** todos os de 5.1 têm arquivo/campo/valor.
8. **Suspeitas:** lista 5.2.
9. **Do MOOC Admin/Moodle precisamos:** política do ciclo (ano, datas, categoria-alvo, blocos de site, hashes das artes do certificado), o que o MOOC Admin altera após restaurar (para suprimir alarmes falsos), lista de plugins/versões do destino, papéis do curso (API), e um usuário de teste para a fase 4.
10. **Cobertura realista da v1:** 17 itens do checklist + ~15 regras adicionais determinísticas, com 2 estados de incerteza explícitos; ~60–70% do tempo de checagem manual em configurações.

---

## Entregável 6 — Comparação arquitetural

| Critério | A — Módulo Laravel (no MOOC Admin) | B — Serviço PHP independente (API/CLI) | C — Serviço Python |
|---|---|---|---|
| Manipulação de `.mbz` (tar.gz e zip) | `ext-zlib` (`gzopen` streaming) + parser tar próprio (formato ustar simples, 512 B) ou adaptação do `tgz_extractor` do Moodle (GPL, mesma licença exigiria cuidado); `ZipArchive` para zip. `PharData` **não** recomendado (phar.readonly, memória, nomes). | idem | `tarfile`/`zipfile` nativos, streaming, com checagens (o protótipo já faz) |
| XML | `XMLReader` (streaming, para `files.xml` 6 MB e `questions.xml` 1,4 MB) + `DOMDocument`/`SimpleXML` com `LIBXML_NONET` e sem carregamento de entidades (padrão no PHP ≥ 8) | idem | `xml.etree`/`lxml` + `defusedxml` |
| Segurança de arquivos | mesma lógica nas três; isolamento por processo de fila e diretório temporário com permissões restritas | igual + superfície de rede extra (API) | igual + superfície extra |
| Arquivos grandes/desempenho | 94 MB processados em segundos no protótipo; PHP com streaming é equivalente; limitar memória por job | igual | igual |
| Testabilidade | PHPUnit + fixtures sintéticas; mesma suíte do MOOC Admin | PHPUnit, suíte própria | pytest; **dois ecossistemas de teste** |
| Manutenção | 1 base de código, 1 linguagem, equipe existente | 2 deploys PHP, contrato HTTP a manter | 2 linguagens, 2 pipelines, 2 conjuntos de dependências |
| Implantação | nenhuma infra nova além de fila (database/redis) | novo serviço/processo + rede | runtime Python + serviço + rede |
| Reuso | modelos, auth, UI, filas do MOOC Admin | pouco | nenhum (o protótipo Python é reaproveitável como **spec**) |
| Integração | nativa (Eloquent, Jobs, Events) | HTTP/CLI + serialização | HTTP + serialização |
| Risco | acoplamento ao ciclo de release do MOOC Admin (mitigável com pacote Composer interno) | duplicação de autenticação/log | perda de contexto institucional; equipe sem Python |

**Recomendação: Alternativa A**, estruturada como um **pacote PHP interno** (`src/Audit/…` ou `packages/mooc-audit`) com interface de linha de comando (`php artisan mooc:audit arquivo.mbz`) e sem dependência do framework no núcleo (leitor, normalizador, regras), o que preserva a opção B no futuro sem reescrita. A alternativa C não se justifica: nenhuma biblioteca Python oferece algo que o PHP não faça aqui, e a análise é determinística (sem IA). Filas do Laravel para o processamento assíncrono; nenhum microsserviço.

---

## Entregável 7 — Proposta de MVP

**Critérios:** confiança alta, impacto no processo, baixo custo, cobertura dos cursos existentes, redução de trabalho manual, evidência legível.

**Regras do MVP (fase 1, ~30):**

| Grupo | Regras | Observação |
|---|---|---|
| Integridade | X-INT-01, X-INT-02, X-INT-03, X-INT-04, X-INT-06 | bloqueantes/altas; custo mínimo |
| Configurações de oferta | CL-05, CL-06, CL-07, CL-08 (estado do backup, rotulado "reavaliar pós-restauração") | parâmetros de política |
| Recursos institucionais | CL-04 (links), CL-09, CL-11, CL-22, CL-21 (3 blocos) | por nome canônico |
| Avaliação | CL-12 (+X-AV-04), CL-13 (+ enunciado), CL-14, CL-16, X-AV-01 | determinísticas |
| Conclusão e restrições | CL-17, CL-19, CL-20 (hiddensections), X-DEP-01/02/03/04/05, X-BLK-01 | grafo simples |
| Certificação | CL-23, X-CERT-01, CL-25 (texto, só alerta) | alcançabilidade estática |
| Conteúdo/estrutura | CL-18, X-CONT-01, X-CONT-03, X-CONT-04, X-STR-01, X-ACC-01 (agregado por módulo) | sinais objetivos |
| Inventário | sempre (seções, módulos, plugins, arquivos, hosts externos, vídeos) | base dos relatórios e de regras futuras |

**Fora do MVP:** verificação de URLs (rede), comparação com vitrine/projeto, hash de arte do certificado (entra assim que a política fornecer os hashes), similaridade textual, acessibilidade além de `alt`/texto de link, emissão de certificado de teste, papéis (API).

**Entregas do MVP:** pacote PHP com leitor + normalizador + motor + ~30 regras; política em JSON versionada (`policies/2026.json`); comando artisan; job em fila; persistência do resultado JSON; página simples de listagem no MOOC Admin (síntese + detalhe por regra). Sem bloqueio de liberação (apenas informação) até decisão institucional.

---

## Entregável 8 — Arquitetura recomendada

### Componentes e responsabilidades

```
MOOC Admin (Laravel)
 ├─ UI/Controllers ──► AuditController (solicita, lista, detalha, reexecuta)
 ├─ Jobs: AuditBackupJob (fila) ──────────────────────────────┐
 └─ Eloquent: audits, audit_findings, policies, audit_runs    │
                                                              ▼
 Pacote mooc-audit (PHP puro, sem framework)
 ├─ Reader      : detecta formato, extrai em streaming com limites, verifica .ARCHIVE_INDEX,
 │                parse XML seguro (sem DOCTYPE/entidades, sem rede), nunca executa conteúdo
 ├─ Normalizer  : XML "moodle2" → CourseModel (curso, seções, módulos, notas, arquivos, blocos,
 │                inscrições, restrições já decodificadas, textos HTML)
 ├─ RuleEngine  : carrega regras (classes), aplica Policy, isola exceções por regra → Finding[]
 ├─ DependencyAnalyzer : grafo de availability/conclusão (ciclos, órfãos, impossibilidades,
 │                alcançabilidade do certificado)
 ├─ Aggregator  : deduplica (mesma regra+local), agrupa por módulo/seção, calcula síntese
 └─ Reporters   : JSON (contrato), HTML (view), PDF (futuro, a partir do HTML)
```

### Fluxo de processamento

1. Upload/seleção do `.mbz` (ou caminho acessível) → registro `audit_runs` (status `queued`, hash SHA-256 do arquivo, versão do motor, id da política).
2. Job: Reader → diretório temporário exclusivo → integridade (se bloqueante, ainda assim segue com o que foi extraído, como o protótipo).
3. Normalizer → `CourseModel` (serializável; opcionalmente persistido para comparações futuras).
4. RuleEngine (+ DependencyAnalyzer) → lista de `Finding` com estado, gravidade, local, evidência.
5. Aggregator → síntese (contagens por estado e gravidade, principais riscos) → persistência.
6. Reporter → JSON armazenado; HTML renderizado sob demanda.
7. Limpeza do temporário; status `done|failed`.

### Estruturas de dados principais

- **Policy** (JSON versionado por ciclo/tipo de curso): `reoffer_year`, `allowed_end_dates`, `target_category_name`, `min_quiz_attempts` + `attempts_rule`, `course_total_max`, `certificate_min_grade`, `forbidden_modules`, nomes canônicos (fórum, pesquisa, avaliação, licença, declaração), `required_blocks`, `summary_required_links`, `certificate_expected_text`, `certificate_reference_image_hashes`, listas de componentes nativos por versão, `origin_wwwroot`. Nada disso no código.
- **Rule** (metadados estáveis): `id` (ex.: `MOOC-AV-013`), `name`, `description`, `category`, `origin` (`checklist:13` | `extra:6.3`), `rationale`, `requires` (arquivos do backup), `supports` (versões Moodle/plugins), `params` (chaves da política), `severity_default`, `remediation`, `owner_suggested` (proponente | equipe técnica | CGTI).
- **Finding**: `rule_id`, `status` ∈ {CONFORME, NAO_CONFORME, ATENCAO, NAO_APLICAVEL, NAO_VERIFICAVEL, ERRO}, `severity` ∈ {Bloqueante, Alta, Média, Baixa, Informativa}, `location` (`{type: module|section|course|file|block, cmid, modname, name, path}`), `evidence` (`{file, field, value}`), `found`, `expected`, `remediation`, `owner`, `fingerprint` (hash de rule+location para comparar execuções).
- **Contrato JSON** (já produzido pelo protótipo): `{engine, policy_version, executed_at, target, archive, archive_index, inventory, summary, results[]}`.

### Taxonomia de gravidade (por impacto demonstrável)

Bloqueante: pacote corrompido, condição impossível/ciclo na cadeia do certificado, certificado inexistente. Alta: arquivo referenciado ausente, plugin ausente, Avaliação sem exigir avaliativas, certificado sem Pesquisa/Avaliação/60, datas de oferta erradas. Média: tentativas, encerramento, conclusão manual, glossário, licença, links com id antigo, `alt`. Baixa: nomes genéricos, condições duplicadas, hiddensections. Informativa: inventários, hosts, `lang`, guest. A gravidade final é a da regra ajustada pela política (ex.: um item pode ser elevado a bloqueante por decisão institucional).

### Relatórios (Parte VI)

- **Nível 1 (síntese):** curso, situação (Íntegro/Requer correções/Bloqueado), contagens **separadas** por estado (nunca um percentual que misture não aplicável/não verificável com testado), top riscos por gravidade, pendências manuais (regras D/C/E listadas como "verificação humana").
- **Nível 2 (técnico-operacional):** um bloco por inconformidade com os campos do Finding; "Local" sempre com nome + cmid + arquivo do backup; **sem links para a nova sala** (ids mudam na restauração); links para a sala de origem apenas se o MOOC Admin souber o id de origem (está em `original_course_id`).
- Formatos: JSON (contrato, MVP), HTML (MVP, view Blade), PDF (fase 3, a partir do HTML).

### Integração ao processo (Parte VII)

Momentos: **antes** (auditar a sala de origem via backup gerado pelo MOOC Admin ou pelo Moodle: pega problemas que seriam propagados e dá tempo ao proponente), **durante** (auditar o `.mbz` que será restaurado: integridade e plugins são decisivos aqui), **depois** (auditoria pós-restauração via API/BD do Moodle: datas, categoria, inscrição, blocos de site, papéis, emissão de certificado de teste). Recomendação: **híbrido** — fase 1 no backup e fase 4 na sala restaurada, com o mesmo contrato de resultados e regras marcadas por "momento aplicável". O MVP registra `evaluation_moment = backup` e rotula as regras de oferta como "reavaliar após restauração".

Funcionalidades futuras e requisitos que justificam decisão já no MVP: histórico por curso/ciclo (guardar `audit_runs` e `findings` com `fingerprint`), comparação entre execuções (fingerprint estável → novo/resolvido/persistente), responsáveis por pendência (campo `owner` + atribuição), reexecução sob demanda (job idempotente), painel consolidado (consultas sobre `findings`), exportação (JSON/HTML já; PDF depois), liberação condicionada (somente com política explícita `blocking_rules[]`).

### Requisitos não funcionais (Parte VIII)

- **Segurança:** extração em streaming com limites (entradas, bytes por membro/total, razão de compressão), rejeição de caminhos absolutos/`..`/links; diretório temporário por execução com permissões 0700 e remoção garantida; XML sem DOCTYPE/entidades/rede; nunca `include`/`eval`/execução de arquivos; `unserialize` PHP **proibido** sobre `configdata` (usar desserializador próprio restrito a tipos escalares/array, como no protótipo); segredos (`password` de inscrição) nunca no relatório; dados pessoais mínimos (o backup `-nu` já não tem usuários, mas `files.xml` traz `author`/`userid`: não exibir); verificação de URLs (fase 2) com allowlist de domínios, resolução DNS prévia, bloqueio de IPs privados/link-local, sem redirecionamentos para dentro, timeouts curtos, sem envio de conteúdo; nenhum envio de backup a serviços externos.
- **Confiabilidade:** arquivo original intocado (hash antes/depois); resultado determinístico (mesma entrada + mesma política + mesma versão do motor = mesmo JSON, salvo `executed_at`); registrar `engine_version` e `policy_version`; estados explícitos para dado ausente; exceções isoladas por regra (estado ERRO); reprocessamento idempotente; nenhuma ação automática de bloqueio sem política explícita.
- **Testabilidade:** fixtures **sintéticas** geradas por script a partir de um esqueleto mínimo de `.mbz` (course.xml + 1 seção + N módulos), cobrindo: curso com/sem glossário; quiz com 1 e 2 tentativas; certificado sem Pesquisa; arquivo referenciado ausente; dependência circular; restrição alternativa (`|`) válida; condição sobre módulo sem conclusão; backup sem `files.xml` (opção de exportação); pacote truncado (cortar bytes); zip em vez de tgz; `configdata` com acentos; curso correto. Backups reais apenas em testes locais não versionados (contêm nomes de autores e senhas de inscrição). Oráculo: os JSON do protótipo para os 3 backups.

### Limitações da arquitetura

Não vê usuários, papéis, blocos de site nem configurações globais; não prova emissão de certificado; depende de nomes canônicos; política deve ser mantida por ciclo; detecção de plugins exige lista de nativos por versão do Moodle; regras textuais (similaridade, anos) são heurísticas e devem permanecer como "Atenção".

### Diagrama do grafo de dependências (exemplo real, curso 670)

```
Certificado 93361 ──&──► Avaliação 93360 ──&──► quiz 93319 (compl. por nota)
      │                        ├──────────► quiz 93332
      │                        ├──────────► quiz 93336
      │                        └──────────► quiz 93359
      ├──► nota(item 73354 = total do curso) ≥ 60 %
      ├──► Pesquisa de Perfil 93304 (compl. por envio)
      └──► days = 9   (plugin availability_days)
Declaração licença 93363 (sem restrição) · Feedback licença 93362: NOT(nota ≥ 1 %)
```

---

## Entregável 9 — Roadmap incremental

| Fase | Conteúdo | Pré-requisitos | Resultado |
|---|---|---|---|
| **1 — Auditoria estática (MVP)** | pacote PHP (leitor seguro, normalizador, motor, ~30 regras, grafo), política 2026 em JSON, comando artisan, job em fila, JSON + HTML, listagem no MOOC Admin | decisões sobre as 10 ambiguidades; lista de plugins/versões do destino; nomes canônicos; **novo backup do 668** | relatório por backup em segundos; histórico básico |
| **2 — Validação complementar** | verificação de URLs/vídeos com proteção SSRF e cache; hashes das artes do certificado; comparação com projeto estruturado (se existir); regras textuais calibradas (X-AV-02, X-CONT-05); acessibilidade ampliada | allowlist de domínios; artes oficiais; formato do projeto | reduz as verificações manuais 10, 24, 2 |
| **3 — Integração ao fluxo de reoferta** | auditoria automática ao receber o backup e ao concluir a restauração; pendências com responsável; comparação entre execuções (novo/resolvido); exportação PDF; painel do ciclo | eventos do MOOC Admin; modelo de pendências | processo de reoferta com auditoria embutida |
| **4 — Validação pós-restauração** | regras C executadas contra o Moodle (web services/BD): papéis (item 3), categoria/datas/inscrição efetivas, blocos de site (item 21), emissão de certificado com usuário de teste (item 25), verificação de `days` configurado | credenciais de serviço; usuário de teste; política de liberação | conformidade da sala real, não só do backup; liberação condicionada se a política exigir |

Ajuste em relação à sequência proposta: a fase 4 pode começar **antes** da 3 para os itens 3/21/25, porque são os que o backup nunca cobrirá; a fase 2 é a de menor valor por esforço e pode ser parcial.

---

## Recomendação final (pergunta orientadora da seção 16)

**Vale automatizar agora:** integridade do pacote e dos arquivos; dependências de plugins; datas/inscrição/categoria (com rótulo de "estado do backup"); presença dos recursos institucionais por nome; livro de notas (soma, agregação, pesos); tentativas, encerramento e coerência de enunciados; conclusão (manual/ausente/sem critério) e rótulos; glossário; restrições da Avaliação e do Certificado, com grafo (órfãos, impossibilidades, ciclos, alcançabilidade estática); links com ids antigos; `@@PLUGINFILE@@` sem arquivo; recursos vazios; nomes genéricos; `alt` ausente.

**Não vale (ou não ainda):** comparar textos com o projeto do curso sem fonte estruturada; julgar identidade visual; verificar vitrine/URL; detectar duplicatas só por nome; usar LLM para qualquer regra acima (todas são determinísticas); bloquear liberação com base em heurísticas.

**Ainda precisa ser investigado/decidido:** as 10 ambiguidades do checklist (em especial tentativas, texto do certificado, "Escolha a sua situação", bloco "Acessibilidade", H5P no item 16); comportamento do `availability_days` instalado (referência: início do curso ou inscrição); o que o MOOC Admin altera após a restauração; lista de plugins/versões do Moodle de destino; obtenção de um backup íntegro do curso 668 e, idealmente, de mais 2–3 cursos para calibrar falsos positivos.

**Tecnologia:** PHP/Laravel, como pacote interno do MOOC Admin com núcleo independente do framework, fila para execução, política em JSON versionada; o protótipo Python (`poc/`) fica como especificação executável e oráculo de testes.

**Primeira versão implementável:** Entregável 7 (≈30 regras, JSON + HTML, política 2026, comando + job + listagem).

**Resultados realistas:** nos cursos examinados o motor já apontaria, sem intervenção humana, o backup corrompido, 4 PDFs perdidos, 4 atividades avaliadas fora da avaliação final e com conclusão manual, um curso antigo fora do padrão em 6 itens e um link com id obsoleto — e deixaria para a equipe apenas a triagem de alertas e as checagens humanas (conteúdo, visual, emissão de teste, vitrine). O que ele **não** fará é garantir que a sala reofertada funcione: isso continua exigindo a fase 4 e um usuário de teste.

---

## Referências consultadas

- Código-fonte Moodle 4.5 (`MOODLE_405_STABLE`): `availability/classes/tree.php`; `availability/condition/grade/classes/condition.php`; `backup/util/helper/backup_file_manager.class.php`; `lib/filestorage/tgz_packer.php`; `lib/filestorage/file_system.php`; `lib/filestorage/file_system_filedir.php`.
- Diretório de plugins Moodle: [Restriction by days offset from start (availability_days)](https://moodle.org/plugins/availability_days); [Simple Certificate (mod_simplecertificate) 4.0.7](https://moodle.org/plugins/mod_simplecertificate/4.0.7/38739); [versões do mod_simplecertificate](https://moodle.org/plugins/mod_simplecertificate/versions?lang=or).
- Formato `.mbz`: [mbz_packer.php (espelho)](https://git.interlegis.leg.br/ILB/moodle/src/branch/master/lib/filestorage/mbz_packer.php); [discussão sobre tar.gz como padrão desde o Moodle 2.9](https://moodle.org/mod/forum/discuss.php?d=321278).
- Curso "Como criar um MOOC?" (backup 668): páginas "Organizando o curso", "Configurações comuns de Recursos e Atividades", "Livro de Notas", "Checklist de conferência pelo professor", "Avaliação técnica pela CGTE" — usadas como fonte das regras institucionais (nomes da Área do curso não devem ser alterados; 1º questionário restrito à "Escolha"; rótulos sem conclusão; soma 100; CGTE faz a avaliação técnica final com teste de certificado).

## Reprodução

```bash
python3 -I poc/mbz_inspect.py "backup-moodle2-course-670-acessibilidetecnologias_2026-20261028-1501-nu.mbz" --out /tmp/670.json
```

Saídas de referência desta investigação: `poc/resultados/curso-55.txt`, `curso-670.txt`, `curso-668.txt` (e `.json`).
