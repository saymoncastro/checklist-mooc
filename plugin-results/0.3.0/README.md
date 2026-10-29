# Evidências da correção assistida (plugin 0.3.0, spec 002)

Ambiente: site local isolado da spec 002 (`http://localhost:8093`, Moodle 4.5.15, PHP 8.3, PostgreSQL 17, fuso do
servidor Europe/London), cursos sintéticos A (id 2) e B (id 3) de `cli/make_fixtures.php`, ano da reoferta 2027,
política `2027-decisoes-2026-10-28`. Nada foi executado no laboratório principal nem em produção. Data: 2026-10-29.

| Arquivo | Conteúdo |
|---|---|
| `A-antes.json`, `A-depois.json` | Curso A antes/depois de toda a sessão: 48 CONFORME, resultados idênticos (nenhuma proposta é oferecida) |
| `B-0-antes.json` | Curso B inicial: 25 NAO_CONFORME, 5 ATENCAO, situação BLOQUEADA |
| `B-1-apos-CL13-e-ajuste-manual-CL07.json` | Após a correção CL-13 pela interface e o ajuste manual do término do curso (CL-07) |
| `B-2-apos-CL14.json` | Após a correção CL-14 pela interface; inclui o "Questionário 3 (com tentativa)" criado para a evidência de dados de estudantes |
| `trilha.txt` | Linhas de `local_mooc_audit_fix` ao final (#1–#2 da sequência abaixo; #3–#4 da reexecução após a revisão de segurança) |

## Sequência executada (interface web, usuário administrador local)

| # | Ação | Resultado observado |
|---|---|---|
| 1 | Relatório do curso B | 7 ações "Ver proposta de correção" (CL-13 ×2 e CL-14 do Questionário 1, CL-14 do Questionário 2 e do jogo, CL-17 ×2); nenhuma em outras regras |
| 2 | Prévia CL-13, Questionário 1 (cmid 24) | Executável; atual 1, proposto 2; simulação: as 2 inconformidades CL-13 deixam de ocorrer, nenhuma nova. Banco inalterado (`attempts=1`, `cacherev` igual, trilha vazia) |
| 3 | Confirmar | "Correção aplicada e confirmada"; `attempts: 1 → 2`; reauditoria `CL-13: NAO_CONFORME, NAO_CONFORME → CONFORME`. Banco: só `attempts` e `timemodified` mudaram (`reviewattempt`, `reviewmarks`, `grademethod`, senha intactos); `cacherev` incrementado; trilha #1; log `correction_applied` + `course_module_updated`. Tela oficial de edição mostra "Tentativas permitidas = 2" |
| 4 | Prévia CL-14, Questionário 1 | Não executável: "A data de término do curso não está conforme a política (CL-07)"; proposto 30/06/2027 01:00 (término então vigente) |
| 5 | Ajuste humano do término do curso para 31/12/2027 23:59 (`update_course`, mesma API do formulário) | CL-07 CONFORME; CL-14 do Questionário 2 passa a CONFORME sem correção |
| 6 | Prévia CL-14, Questionário 1 | Executável; proposto 31/12/2027 23:59 |
| 7 | Renomear o questionário em paralelo (`set_coursemodule_name`) e então confirmar a prévia aberta | "O curso mudou desde a prévia. Nada foi alterado"; `timeclose=0`, trilha com 1 linha, nenhum evento de calendário |
| 8 | "Gerar nova análise" → confirmar | "Correção aplicada e confirmada"; `timeclose = 1830297540` (= término do curso, 31/12/2027 23:59 Europe/London); evento de calendário `close`; trilha #2; reauditoria `CL-14: NAO_CONFORME → CONFORME` |
| 9 | Prévia CL-17, fórum "Espaço de interação" (cmid 16) | Orientada; atual "Manual", proposto "Automática por postagens… ou sem conclusão"; sem botão de confirmar; link para a edição |
| 10 | Questionário 3 com 1 tentativa e 1 tentativa real de estudante (não prévia) | Prévia CL-13 não executável: "A atividade já tem 1 tentativa(s) de estudantes…"; aviso de 1 estudante inscrito |
| 11 | Após as correções da revisão de segurança: Questionário 4 (cmid 36), CL-13 e CL-14 pelo planejador/executor (CLI, administrador) | `applied`/`resolved` nos dois; `attempts=2`, `timeclose=1830297540`, 1 evento `close`; trilha #3 e #4 |

Comparação B-0 → B-2 nas regras corrigidas: CL-13 (cmid 24) NC×2 → C; CL-14 (cmid 24) NC → C; CL-14 (cmid 25)
ATENCAO → C (efeito do ajuste manual do término); CL-07 NC → C (ajuste manual). O aumento de NAO_CONFORME entre B-1 e
B-2 (22 → 23) vem do Questionário 3 criado no passo 10, não das correções (a reauditoria de cada correção relatou
"nenhuma inconformidade nova").
