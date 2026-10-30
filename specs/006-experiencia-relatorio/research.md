# Research — Spec 006

Base empírica: `docs/auditoria-ux-correcao/README.md` (casos A–D, U‑01 a U‑22), com verificações no Moodle 4.5.15 e
5.3. Cada item: **Decision**, **Rationale**, **Alternatives considered**.

## R1 — Que fatos o JSON gravado já tem

**Decision**: a fase 1 usa só o que o contrato 1.0 grava: `rule`, `status`, `gravidade`, `cmid`, `locus`, `section`,
`local`, `evidencia`, `encontrado`, `esperado`, `orientacao`, `link` e `occurrence`. `check` e `subject` são lidos da
chave de ocorrência `regra|entidade|check[|subject][#n]` (`engine::occurrence`, specs/005 R2).

**Rationale**: a chave existe em todas as execuções gravadas desde 0.5.0 e é documentada como estável. Dá a variante
(qual condição falhou) e o objeto relacionado (`cm:38` no X‑DEP‑02, `36>37` no X‑DEP‑04) sem tocar nas regras.

**Alternatives considered**: gravar `check`/`subject` como campos (fase 3, contrato 1.1); reavaliar as regras ao exibir
(caro e mudaria o resultado mostrado em relação ao gravado).

## R2 — Renderizar ao exibir

**Decision**: textos e ações são montados ao exibir, no idioma de quem vê, com `get_fast_modinfo()` do curso. O texto
gravado (`evidencia`, `encontrado`, `esperado`, `orientacao`) vai para "Detalhes técnicos", como registro.

**Rationale**: resolve a mistura de idiomas (U‑07), permite nomes atuais e links válidos no site atual, e deixa a
redação ajustável sem regravar execuções (FR‑033).

**Alternatives considered**: congelar o texto novo no JSON (repetiria U‑07).

## R3 — Variantes de mensagem

**Decision**: a mensagem é escolhida por `regra + check + variante`, em que a variante vem do estado e, onde o `check`
cobre situações diferentes, de um discriminador estável:

| Regra / check | Variantes |
|---|---|
| X‑DEP‑02 `target_completion` | `nc` (alvo sem conclusão) · `at` (alvo oculto) |
| CL‑14 `close_date` | `nc` (sem data) · `at` (diferente do fim do curso) · `nv` (tipo sem campo) |
| CL‑13 `intro_attempts` | `nc` (enunciado diverge) · `at` (enunciado não menciona) |
| CL‑17 `completion` | `nc` (manual) · `at` (sem critério, gravidade padrão) · `atlow` (critério diferente, gravidade baixa) |
| CL‑18 `forbidden` | `source` (locus `module_view`: glossário fonte de jogos; NC se vazio, Atenção se tem entradas) · `nc` (sem uso por jogos) |
| CL‑20 `content` | `empty` (com orientação de seção vazia) · `labels` (só rótulos) |
| CL‑08 `self_enrol` | `missing` (sem `subject`: não há autoinscrição) · `nc` |
| X‑DEP‑01 `missing_target` | `cm` · `section` (pelo `locus`) |
| X‑STR‑01 `visibility` | `at` (oculta) · `atinfo` (indisponível para o usuário de teste) |
| X‑INT‑03 `plugin` | `mod` · `availability` (prefixo do `subject`) |
| demais | uma variante por estado |

**Rationale**: as situações exigem ações diferentes; os discriminadores escolhidos são estado, gravidade, `locus` e
`subject`, todos gravados e estáveis (não o texto da evidência).

**Alternatives considered**: analisar o texto da evidência (frágil, em português fixo).

## R4 — Rotas nativas

**Decision**: ações por verificação, calculadas ao exibir:

| Ação | Rota |
|---|---|
| Restringir acesso de uma atividade | `/course/modedit.php?update=<cmid>&showonly=availabilityconditionsheader` |
| Conclusão de atividade | `/course/modedit.php?update=<cmid>&showonly=activitycompletionheader` |
| Nota / tentativas do questionário | `/course/modedit.php?update=<cmid>&showonly=modstandardgrade` |
| Datas do questionário | `/course/modedit.php?update=<cmid>&showonly=timing` |
| Configurações da atividade | `/course/modedit.php?update=<cmid>` |
| Questões do questionário | `/mod/quiz/edit.php?cmid=<cmid>` |
| Restringir acesso de uma seção / editar seção | `/course/editsection.php?id=<sectionid>[&showonly=availabilityconditions]` |
| Autoinscrição | `/enrol/editinstance.php?courseid=<c>&id=<id>&type=self&returnurl=<relatório>` (sem id: `/enrol/instances.php?id=<c>`) |
| Configurações do curso, voltando ao relatório | `/course/edit.php?id=<c>&returnto=url&returnurl=<relatório>` |
| Configuração do livro de notas | `/grade/edit/tree/index.php?id=<c>` |
| Importar | `/backup/import.php?id=<c>` |
| Página do curso (blocos) | `/course/view.php?id=<c>` (o texto diz "ative o Modo de edição") |

**Rationale**: o núcleo usa `showonly` nos próprios selos de conclusão e restrição
(`course/format/classes/output/local/content/cm/*.php`); seção inexistente é ignorada (`filter_shown_headers`), então a
falha é segura. Âncoras (`#id_…`) não expandem a seção (verificado no 5.3).

**Alternatives considered**: âncoras; JavaScript para abrir a seção.

## R5 — Permissões e responsável

**Decision**: cada ação declara a capacidade da tela de destino (`moodle/course:manageactivities` no módulo;
`moodle/course:update`; `enrol/self:config`; `moodle/grade:manage`; `moodle/backup:backuptargetimport`;
`moodle/site:manageblocks`). Sem a capacidade, a ação não aparece e o texto diz quem pode. O responsável de cada
variante é `teacher`, `team` (equipe da reoferta: categoria, Sala Padrão) ou `admin` (plugins). Para a categoria, a
equipe é indicada quando `make_categories_list('moodle/course:changecategory')` de quem vê só tem a categoria atual.

**Rationale**: FR‑036; caso C e U‑09 da auditoria.

**Alternatives considered**: mostrar sempre o link e deixar o Moodle negar (aparenta ação disponível).

## R6 — Plugins exigidos ausentes

**Decision**: ao exibir, `core_plugin_manager` confere o módulo do certificado da política
(`required_certificate_module`), `mod_game` e `block_completion_progress`. Faltando, o relatório avisa no topo
(responsável: administrador) e as verificações que dependem do plugin trocam de mensagem: `certificate_presence`
(CL‑11, CL‑23, X‑CERT‑01), `licence_certificate` (CL‑22), `progress_block` (CL‑21) e CL‑18 `forbidden` sem jogo.

**Rationale**: U‑01 (dist53: quatro orientações impossíveis e uma destrutiva). Não muda o resultado da regra; a
detecção no motor (X‑INT‑03) fica para a fase 4.

**Alternatives considered**: esperar a fase 4.

## R7 — Precedências demonstráveis

**Decision**: lista fechada, testada:

1. X‑DEP‑02 `target_completion` não conforme na atividade X precede qualquer outra ocorrência da atividade X:
   comprovado no 4.5 e no 5.3 que o formulário não salva.
2. CL‑06 ou CL‑07 não conforme precede CL‑08 `self_enrol` e CL‑14 `close_date` (as datas propostas dependem do curso).
3. CL‑12 `total_sum` não conforme precede CL‑23 `min_grade` (a condição de nota é percentual do máximo).

A nota aparece na ocorrência dependente.

**Rationale**: FR‑037; nada fora dessa lista é inferido.

## R8 — Contradições entre regras

**Decision**: CL‑17 `completion` (manual) num rótulo, ou numa atividade que também tem ocorrência do CL‑09 ou do CL‑19
no mesmo relatório, usa a variante "desative a conclusão" e cita a outra ocorrência. A regra continua igual.

**Rationale**: U‑05; a ação que satisfaz as duas verificações prevalece (edge case da spec). Correção da regra: fase 4.

## R9 — Rótulos do núcleo

**Decision**: as mensagens recebem os rótulos de campo como parâmetros vindos do núcleo: `attemptsallowed`,
`quizclose`, `timing` (quiz); `activitycompletion` (completion); `restrictaccess` (availability); `aggregation`,
`aggregateonlygraded`, `gradebooksetup`, `coursetotal` (grades); `pluginname`, `newenrols`, `enrolstartdate`,
`enrolenddate` (enrol_self); `startdate`, `enddate`, `coursecategory`, `hiddensections`, `hiddensectionsinvisible`,
`gradenoun`, `editsettings` (moodle). Conferidos no 4.5 e no 5.3. Nomes de plugins ausentes vêm de strings do
plugin (o componente ausente não tem strings).

**Rationale**: FR‑042; CL‑20 no 5.3 (U‑18).

## R10 — Execuções antigas e casos sem dados

**Decision**: sem `occurrence` (JSON anterior a 0.5.0) ou com `check` desconhecido, a linha usa o texto gravado, como
hoje. Atividade que não existe mais: "removida depois desta auditoria (id N)", sem link. Execução de outro site ou com
`link` gravado: o link calculado substitui o gravado; sem cálculo possível, nenhum link.

## R11 — O que fica para as fases 2–3

Valores encontrado × exigido em frase (precisa de `params`, fase 3); agrupar ocorrências em causas e contar causas
(FR‑038, fase 2); "Comece por aqui", visões, detalhes recolhidos, celular (fase 2); `evidence_level` (fase 3).
