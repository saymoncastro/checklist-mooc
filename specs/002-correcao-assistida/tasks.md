# Tasks: Correção Assistida de Inconformidades

**Input**: `specs/002-correcao-assistida/` (spec, plan, research, data-model, contracts, correction-matrix)
**Tests**: obrigatórios (constituição VII). Caminho do plugin: `plugin/local_mooc_audit/` (abreviado `P/`).

Formato: `[ID] [P?] [História] Descrição`. `[P]` = paralelizável (arquivos distintos, sem dependência).

## Fase 1 — Preparação

- [x] T001 Confirmar estado do PR #1 (aberto) e criar a branch da Spec 002 a partir da branch da Spec 001 (`73f2c78`)
- [x] T002 Ambiente isolado `moocaudit-s2-web` (banco `moodle_s2`, porta 8093, rede `moocaudit_s2`) com fixtures A/B
- [x] T003 Baseline: suíte da Spec 001 no ambiente novo (75 testes, 1720 asserções, OK)

## Fase 2 — Fundação (bloqueia todas as histórias)

- [ ] T010 `P/db/access.php`: capacidades `preparecorrection` (read) e `applycorrection` (write, `RISK_CONFIG`)
- [ ] T011 `P/db/install.xml` + `P/db/upgrade.php`: tabela `local_mooc_audit_fix`
- [ ] T012 `P/version.php` 2026102902 / 0.3.0
- [ ] T013 [P] `P/classes/local/correction/proposal.php` (valor, invariantes, impressão digital, export)
- [ ] T014 [P] `P/classes/local/correction/verifier.php` (comparação antes × depois, pura)
- [ ] T015 [P] `P/classes/local/correction/facts.php` (tentativas, notas, substituições, inscritos, conclusões,
  critérios de conclusão do curso, papéis com capacidade)
- [ ] T016 `P/classes/local/correction/strategy/base.php` (propor, aplicar, verificar persistência; textos)
- [ ] T017 `P/classes/local/correction/planner.php` (auditoria fresca, achados da regra/atividade, estratégia,
  simulação, capacidades)
- [ ] T018 [P] `P/classes/local/correction/trail.php` + `P/classes/event/correction_applied.php`
- [ ] T019 `P/classes/local/correction/executor.php` (revalidação, trava, transação, verificação, trilha, reauditoria)
- [ ] T020 [P] Strings `en` e `pt_br`
- [ ] T021 [P] Testes: `tests/correction/verifier_test.php`, `proposal_test.php`

## Fase 3 — US1 + US2 (P1): prévia e correção de tentativas (CL-13) 🎯 MVP

- [ ] T030 [US2] `strategy/quiz_attempts.php`: elegibilidade (0 < a < mínimo; regra `exact` sem redução), impedimentos
  (tentativas, notas), avisos (inscritos, substituições, conclusão por esgotar tentativas), aplicação e verificação
- [ ] T031 [US1] `P/correction.php` (GET prévia / POST confirmação), `classes/output/correction.php`, templates
  `correction.mustache` e `correction_result.mustache`, renderer
- [ ] T032 [US1] Ação "Ver proposta de correção" no relatório (`output\report`, `report_group.mustache`, `index.php`)
- [ ] T033 [US1/US2] Testes `tests/correction/planner_test.php` (CL-13: nove elementos, simulação, redução, enunciado)
- [ ] T034 [US2] Testes `tests/correction/executor_test.php` (sucesso só no campo previsto, API lê o valor,
  reauditoria, trilha, evento, idempotência, cancelamento/GET sem escrita)

## Fase 4 — US3 (P2): encerramento (CL-14)

- [ ] T040 [US3] `strategy/quiz_timeclose.php` (término do curso conforme CL-07 e futuro, abertura, tentativas,
  calendário)
- [ ] T041 [US3] `strategy/close_date_guidance.php` (tarefa, fórum, escolha, pesquisa, base de dados, jogo: campo do
  tipo, orientação)
- [ ] T042 [US3] Testes de planejamento e execução do CL-14 (evento de calendário, dependência CL-07, abertura, tipos)

## Fase 5 — US4 (P2): proteções

- [ ] T050 [US4] Testes: sem `applycorrection`, sem `manageactivities`, conflito (curso/atividade/política
  alterados, atividade removida), dados de estudantes (tentativa real), falha com rollback, falha de verificação,
  reauditoria com erro, trava ocupada

## Fase 6 — US5 (P3): orientação de conclusão (CL-17)

- [ ] T060 [US5] `strategy/completion_guidance.php` (tipo, configuração atual, critério da política, dependências,
  Avaliação/certificado, registros de conclusão, link para a seção de conclusão)
- [ ] T061 [US5] Testes do CL-17 (dependências, registros, POST recusado)

## Fase 7 — Registro e privacidade

- [ ] T070 `classes/privacy/provider.php` (metadados, contextos, exportação, anonimização)
- [ ] T071 Testes `tests/correction/trail_test.php` e `tests/privacy_test.php`

## Fase 8 — Interface (Behat) e não modificação

- [ ] T080 `tests/behat/correction.feature`: ver proposta, cancelar, confirmar (resolvido), sem permissão
- [ ] T081 `tests/readonly_test.php`: prévia e cancelamento não alteram tabelas

## Fase 9 — Revisão e entrega

- [ ] T090 Suíte completa PHPUnit + Behat da Spec 001 e da 002; PHPCS `moodle`; `php -l`
- [ ] T091 Evidências no Moodle local (antes/depois de cada fluxo) em `results.md`
- [ ] T092 Revisão de segurança e permissões; revisão do diff
- [ ] T093 README do plugin, catálogo de regras (coluna de correção), backlog, `analysis.md`
- [ ] T094 Commit e PR (base: branch da Spec 001), sem merge

## Dependências

Fase 2 → Fases 3–8. Fase 3 é o MVP (pode ser entregue sozinha). Fases 4, 6 e 7 são independentes entre si após a
Fase 2. Fase 5 depende de 3 e 4. Fase 9 por último.
