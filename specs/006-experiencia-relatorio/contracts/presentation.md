# Contrato da apresentação (fase 1)

Contrato interno entre os resultados gravados (contrato JSON 1.0, inalterado) e o relatório. Implementado em
`classes/local/presentation/{catalog,finding}.php`.

## Mensagens

Para cada `regra + check + variante` existem, em `lang/en` e `lang/pt_br`:

- `msg_<regra>_<check>_<variante>_problem` — o problema em uma frase;
- `msg_<regra>_<check>_<variante>_why` — por que importa;
- `msg_<regra>_<check>_<variante>_fix` — como corrigir (alternativas quando a decisão é pedagógica);
- opcional `msg_<regra>_<check>_<variante>_verify` — como confirmar (padrão: `msg_verify_default`).

`<regra>` é `base::key()` (CL‑13 → `cl13`). Parâmetros (`{$a->…}`): `name` (atividade do resultado), `target`
(atividade relacionada), `nodes` (ciclo), `field`, `section`, `option`, `header` (rótulos do núcleo), `min` e outros
valores da política carregada. Nenhuma mensagem contém nome de coluna do banco, valor codificado ou JSON (FR‑007).

## Ações

| Tipo | Destino | Capacidade de quem vê |
|---|---|---|
| `cm_restrict` / `subject_restrict` | `modedit.php?update=<cm>&showonly=availabilityconditionsheader` | `moodle/course:manageactivities` (módulo) |
| `cm_completion` / `subject_completion` | `…&showonly=activitycompletionheader` | idem |
| `cm_grade` | `…&showonly=modstandardgrade` | idem |
| `cm_timing` | `…&showonly=timing` (questionário) | idem |
| `cm_visibility` / `subject_visibility` | `…&showonly=modstandardelshdr` | idem |
| `cm_settings` / `subject_settings` | `modedit.php?update=<cm>` | idem |
| `quiz_questions` | `/mod/quiz/edit.php?cmid=<cm>` | `mod/quiz:manage` |
| `mod_view` | `/mod/<modname>/view.php?id=<cm>` | `moodle/course:manageactivities` |
| `cycle_restrict` | uma `subject_restrict` por atividade do ciclo | idem |
| `section_edit` / `section_restrict` | `editsection.php?id=<sectionid>[&showonly=availabilityconditions]` | `moodle/course:update` |
| `enrol_self` | `enrol/editinstance.php?courseid=<c>&id=<id>&type=self&returnurl=<relatório>` | `enrol/self:config` |
| `enrol_list` | `enrol/instances.php?id=<c>` | `moodle/course:enrolreview` |
| `course_settings` | `course/edit.php?id=<c>&returnto=url&returnurl=<relatório>` | `moodle/course:update` |
| `gradebook` | `grade/edit/tree/index.php?id=<c>` | `moodle/grade:manage` |
| `import` | `backup/import.php?id=<c>` | `moodle/backup:backuptargetimport` |
| `course_page` | `course/view.php?id=<c>` (o texto pede o Modo de edição) | `moodle/site:manageblocks` |

Uma ação só é exibida quando o destino existe no curso atual e quem vê tem a capacidade. Não há links com `sesskey`.

## Responsável

`teacher` → "Professor editor da sala" (ou "Você pode fazer isto" quando quem vê tem a capacidade da primeira ação);
`team` → "Equipe da reoferta (acesso à Sala Padrão ou às categorias)"; `admin` → "Administrador do site".

## Plugins exigidos

`required_certificate_module` da política, `mod_game` e `block_completion_progress`. Ausente um deles, aviso no topo e
variante `noplugin` nas verificações dependentes (research R6).

## Precedências e relações

Lista fechada (research R7, R8). Notas: `msg_before_restriction`, `msg_before_dates`, `msg_before_total`,
`msg_related_completion`.

## Compatibilidade

- O JSON gravado e o `link` gravado não mudam (contrato 1.0).
- Sem mensagem para a verificação (ou sem `occurrence`), a linha mostra o texto gravado.
- `presentation_catalog_test` garante que toda verificação produzida pelos cenários de referência tem mensagem nas duas
  línguas e que nenhuma mensagem tem marcadores proibidos (`cmid=`, `completion=`, `status=`, `{"`, `@@PLUGINFILE@@`).
