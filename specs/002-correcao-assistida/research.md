# Research: Correção Assistida

**Spec**: 002 · **Data**: 2026-10-29 · **Moodle de referência**: 4.5.15 (Build 20261005), `moodle-local/moodle`

## R1 — Mecanismo de escrita das correções de questionário

**Pergunta**: qual é o "mecanismo apropriado do Moodle" para alterar `quiz.attempts` e `quiz.timeclose`?

**Evidência**:

- `update_moduleinfo()` (course/modlib.php:570) chama `quiz_update_instance($quiz, $mform)` (mod/quiz/lib.php:131),
  que **espera dados no formato do formulário**: `quiz_process_options()` (lib.php:968) recompõe as colunas
  `review*` a partir de caixas de seleção do formulário (`attemptduring`, `correctnessimmediately`…) e reescreve
  `password` a partir de `quizpassword`; `quiz_after_add_or_update()` (lib.php:1132) **apaga e recria** os feedbacks
  gerais a partir de `feedbacktext`. Alimentar essa rotina com o registro do banco zeraria opções de revisão, senha e
  feedbacks — perda de dados silenciosa. Reproduzir o formulário programaticamente (instanciar `mod_quiz_mod_form`,
  `data_preprocessing`, simular envio) acopla o plugin a detalhes internos do formulário e é frágil entre versões.
- O próprio núcleo atualiza campos isolados do questionário fora do formulário:
  - `mod_quiz_core_calendar_event_timestart_updated()` (lib.php:2233): ao arrastar o evento no calendário, exige
    `moodle/course:manageactivities`, altera `timeopen`/`timeclose` e `timemodified` com `update_record`, chama
    `quiz_update_open_attempts()` se o encerramento mudou, `quiz_delete_previews()`, `quiz_update_events()` e dispara
    `\core\event\course_module_updated`.
  - `shift_course_mod_dates()` (lib/moodlelib.php:5054), usado pelo reset de curso (`quiz_reset_userdata`,
    lib.php:1539), executa `UPDATE {quiz} SET timeclose = timeclose + ?` diretamente.

**Decisão**: atualização parcial da instância por `$DB->update_record('quiz', {id, campo, timemodified})` — somente o
campo previsto —, dentro de transação, seguida exatamente das rotinas do núcleo para aquele campo:

| Campo | Rotinas posteriores |
|---|---|
| `attempts` | `\course_modinfo::purge_course_module_cache` + `rebuild_course_cache(curso, true)`; evento `course_module_updated` |
| `timeclose` | `quiz_update_open_attempts(['quizid'=>…])`; `quiz_update_events($quiz)` (calendário); cache do curso; evento `course_module_updated` |

`quiz_delete_previews()` **não** é chamado: apagaria tentativas de prévia de professores, efeito fora do campo
previsto e desnecessário para a correção. Justificativa registrada no `README.md` do plugin (princípio II).

**Alternativas rejeitadas**: `update_moduleinfo` com registro do banco (perda de dados); simulação do formulário
(frágil); `$DB->execute('UPDATE …')` genérico (sem lista branca nem rotinas posteriores).

## R2 — Dados de estudantes e cursos ativos

**Decisão**: o planejador consulta, somente leitura e por atividade:

- `quiz_attempts` com `preview = 0` (qualquer estado) → **impedimento** para CL-13 e CL-14;
- `grade_grades` com `finalgrade IS NOT NULL` no item de nota da atividade → **impedimento**;
- `quiz_overrides` → **aviso** (substituições prevalecem para usuários/grupos);
- inscritos ativos com `mod/quiz:attempt` (`count_enrolled_users($context, 'mod/quiz:attempt', 0, true)`) → **aviso**;
- `course_modules_completion` com `completionstate > 0` → informação de risco no CL-17.

Racional: aumentar o limite de tentativas ou fixar encerramento futuro igual ao término do curso não reescreve
nenhum dado de estudante quando não há tentativas nem notas; com tentativas, mudar tentativas altera quem pode tentar
de novo e o momento de conclusão ("esgotar tentativas"), e mudar o encerramento altera tentativas abertas
(`quiz_update_open_attempts`). Esses casos vão para edição manual.

## R3 — Detecção de conflito e concorrência

**Decisão**: impressão digital `sha256` de: curso, atividade, regra, estratégia, campo, valor atual, valor proposto,
`course.cacherev`, `timemodified` da instância, versão da política, condições de bloqueio. `cacherev` é incrementado
pelo núcleo a cada `rebuild_course_cache` (edição de atividade, seção, configurações do curso), então cobre "o curso
foi modificado". Na confirmação, a proposta é reconstruída do banco e a impressão digital comparada; diferença →
conflito, nada é gravado. Concorrência entre duas confirmações: `\core\lock\lock_config::get_lock_factory()` com
recurso `fix_<cmid>` (espera de 5 s). Dentro da transação o valor atual é relido antes do `update_record`.

**Limite conhecido**: entre a releitura dentro da transação e o `update_record`, uma edição simultânea pela tela de
edição não usa a mesma trava; a janela é de milissegundos e o resultado é detectado pela verificação de
persistência/reauditoria. Registrado como risco residual baixo.

## R4 — Simulação do impacto

**Decisão**: aplicar o valor proposto a uma cópia do snapshot em memória (`modules[cmid].extra[campo]`) e executar o
catálogo com `engine::run_rules()` (função pura de snapshot + política). Comparar com o resultado atual por chave
(regra, cmid, evidência normalizada): resolvidas, introduzidas, alvo resolvido. Custo: uma execução de regras
(< 0,1 s nos cursos A/B). A mesma comparação é usada após a execução real (R8).

## R5 — Registro (trilha) e eventos

**Decisão**: tabela `local_mooc_audit_fix` + evento `\local_mooc_audit\event\correction_applied` (contexto do módulo,
`crud = u`, `edulevel = LEVEL_OTHER`). A tabela é necessária porque o log padrão pode ser desativado/expurgado e não
guarda o resultado da reauditoria; o evento integra com relatórios de log e observadores. Não é histórico de
auditorias (Spec 005). `privacy\provider`: metadados da tabela, exportação no contexto do curso, exclusão por
anonimização (`userid = 0`), preservando a trilha institucional sem dado pessoal.

## R6 — Capacidades

| Capacidade | captype | Contexto | riskbitmask | Arquétipos |
|---|---|---|---|---|
| `local/mooc_audit:preparecorrection` | read | curso | — | manager |
| `local/mooc_audit:applycorrection` | write | curso | `RISK_CONFIG` | manager |

Além disso, a execução exige `moodle/course:manageactivities` no contexto da atividade (a mesma verificação de
`can_update_moduleinfo()` e do callback de calendário do questionário). `RISK_CONFIG` sinaliza que a capacidade altera
o comportamento de atividades.

## R7 — Interface

`/local/mooc_audit/correction.php?courseid=&rule=&cmid=`: GET exibe a prévia (template
`local_mooc_audit/correction`); POST com `sesskey`, `confirm=1` e `fingerprint` executa e exibe o resultado (template
`local_mooc_audit/correction_result`). A ação "Ver proposta de correção" é acrescentada ao relatório pelo
`output\report` somente quando o usuário tem `preparecorrection` e a regra tem estratégia. Cancelar = link para o
relatório.

## R8 — Reauditoria e verificação

Após o `commit`: (1) a estratégia relê o valor pela API do módulo (`\mod_quiz\quiz_settings::create($quizid)`) e,
para `timeclose`, confere o evento de calendário de encerramento; (2) `engine::audit()` completo; (3) comparação R4
entre a auditoria anterior (a da proposta) e a nova. Falha na releitura → resultado `failed`; exceção na reauditoria →
`applied` com reauditoria `error` e mensagem sem sucesso.

## R9 — Datas e fuso horário

`timeclose` recebe o **mesmo instante** de `course.enddate` (inteiro Unix); não há conversão. A prévia exibe a data no
fuso do usuário (`userdate`) e no fuso do servidor (o mesmo usado pelas regras). Os cursos de teste usam término
"31/12 23:59" no fuso do servidor, que é o que o CL-07 aceita.

## R10 — Reversão

Não há rollback automático: reverter `attempts` é trivial pela tela, mas reverter `timeclose` reconstrói eventos de
calendário e poderia interagir com tentativas abertas posteriores. O registro guarda o valor anterior para reversão
manual. A interface não usa a palavra "reversível".

## R11 — Fontes das dependências do CL-17

Do snapshot (somente leitura): condições `completion` com `cm = cmid` nas restrições de módulos e seções; módulo
"Avaliação do curso" (`required_feedback_evaluation`) e certificado (`required_certificate_module`) entre os
dependentes. Do banco (somente leitura, no planejador): `course_completion_criteria` com `moduleinstance = cmid` e
contagem de `course_modules_completion` com `completionstate > 0`.
