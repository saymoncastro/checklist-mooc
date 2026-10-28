# Plano da prova comparativa MBZ × API (a executar em homologação)

**Pré-condições**

1. Moodle 4.5 de homologação com o mesmo conjunto de plugins da produção (`mod_simplecertificate`, `mod_game`, `mod_hvp`, `availability_days`, `block_completion_progress`).
2. Curso conhecido restaurado a partir de um `.mbz` já auditado pelo protótipo (sugestão: curso 670 "Acessibilidade e Tecnologia", cujo relatório MBZ está em `resultados/curso-670.json`).
3. Conta de serviço `mooc_audit_ws` com papel personalizado (somente leitura) atribuído na **categoria** dos cursos MOOC, token do serviço "MOOC Audit (read-only)" restrito às funções da allowlist de `moodle_api_client.py`, protocolo REST habilitado, HTTPS.
4. Um usuário de teste `mooc_teste_aluno` inscrito no curso (para a parte funcional).

Capacidades mínimas do papel de serviço (derivadas do código-fonte 4.5): `moodle/course:view`, `moodle/course:viewhiddencourses`, `moodle/course:viewhiddenactivities` (devolve `availability`), `moodle/course:update` (campos de configuração do curso em `get_courses_by_field`/`get_courses`), `moodle/course:manageactivities` (registro completo em `get_course_module`, campos `section/visible` dos `mod_*_get_*_by_courses`, `shuffleanswers/password` do quiz), `moodle/grade:viewall` + `gradereport/user:view` (itens de nota de todos), `report/progress:view` (conclusão de outro usuário), `moodle/course:viewparticipants` + `moodle/role:review` (professores), `mod/quiz:view`, `mod/feedback:edititems` (campos opcionais da pesquisa), `mod/forum:viewdiscussion`, `mod/glossary:view`. Nenhuma capacidade de escrita.

**Regras selecionadas (14) e o que comparar**

| # | Regra | Chamadas API | Campo(s) | Comparar com MBZ |
|---|---|---|---|---|
| 1 | CL-05 categoria | `core_course_get_courses_by_field` | `categoryid`, `categoryname` | `course.xml/category` |
| 2 | CL-06/07 datas | idem | `startdate`, `enddate` | `course.xml` |
| 3 | CL-08 autoinscrição | `core_enrol_get_course_enrolment_methods` + `enrol_self_get_instance_info` | `type=self`, `status`; **datas indisponíveis** | `enrolments.xml` (tem datas) |
| 4 | CL-20 hiddensections | `get_courses_by_field` | `courseformatoptions[hiddensections]` | `courseformatoptions` |
| 5 | CL-21 blocos | `core_block_get_course_blocks(returncontents=1)` | `name`, `configs/contents.title` | `course/blocks` — aqui aparecem também blocos de site |
| 6 | CL-12 soma 100 | `gradereport_user_get_grade_items(userid=0)` | `grademax`, `categoryid`, `weightraw`, `gradeishidden`; **agregação indisponível** | `grades.xml` + `gradebook.xml` |
| 7 | CL-13 tentativas | `mod_quiz_get_quizzes_by_courses` | `attempts`, `intro` | `quiz.xml/attempts_number` |
| 8 | CL-14 encerramento | idem; `mod_assign_get_assignments` | `timeclose`; `cutoffdate/duedate` | `quiz.xml`, `assign.xml` |
| 9 | CL-16/23/X-DEP-* restrições | `core_course_get_contents` | `availability` (JSON idêntico ao do backup) | `module.xml/availability` |
| 10 | CL-17/19 conclusão | `core_course_get_contents` + `core_course_get_course_module` | `completion`, `completionview`, `completiongradeitemnumber` | `module.xml` |
| 11 | CL-11/22/09 recursos institucionais | `get_contents` (nomes/modnames) + `mod_feedback_get_feedbacks_by_courses` | `name`, `modname`, `completionsubmit` | `activities/*` |
| 12 | X-INT-02/X-CONT-04 arquivos | `get_contents[].contents[].fileurl` + HEAD/GET com token | HTTP 200 e `Content-Length` = `filesize` | `files.xml` × `files/` |
| 13 | X-INT-03 plugins | `core_course_get_course_content_items`; módulos ausentes em `get_contents`; `core_webservice_get_site_info.functions` | tipos disponíveis; cms restaurados | `module.xml/version` |
| 14 | CL-18 glossário vazio | `mod_glossary_get_glossaries_by_courses` + `get_entries_by_letter(ALL)` | `count` | `glossary.xml/entries` |

**Parte funcional (usuário de teste, somente leitura do estado):** `core_course_get_contents` com token do usuário de teste → `uservisible` e `availabilityinfo` por módulo (o que o estudante vê de fato); `core_completion_get_activities_completion_status(courseid, userid_teste)` → `details` das regras de conclusão; `mod_quiz_get_quiz_access_information` → motivos de bloqueio. A **emissão do certificado** não tem Web Service (`mod_simplecertificate` só expõe `verify_code`); fica como teste manual ou via navegador automatizado em homologação.

**Registro por verificação** (preencher ao executar): resultado MBZ · resultado API · divergência · dados indisponíveis · chamadas · permissões exigidas · complexidade · confiabilidade.

**Critérios de sucesso da prova:** (a) nenhuma divergência em 1, 2, 4, 7, 8, 9, 10, 11; (b) lacunas 3 e 6 confirmadas (datas de inscrição e agregação) ou refutadas; (c) 12 e 13 revelam o que o backup não vê (arquivo quebrado no servidor, plugin ausente).
