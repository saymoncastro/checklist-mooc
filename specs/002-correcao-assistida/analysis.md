# Análise de consistência e aderência — Spec 002

**Data**: 2026-10-29 · **Artefatos**: spec.md, plan.md, research.md, data-model.md, contracts/correction-page.md,
correction-matrix.md, tasks.md, código em `plugin/local_mooc_audit` (0.3.0) · **Método**: `/speckit-analyze`
(cobertura requisito → tarefa → teste; violações de MUST da constituição = CRITICAL).

## 1. Cobertura requisito → implementação → teste

| Requisito | Implementação | Teste |
|---|---|---|
| FR-001/002/003 separação | `local\correction\*`; motor intocado (diff de `engine.php` = só `VERSION`) | `readonly_test` (auditoria e prévias), caracterização A/B |
| FR-010 matriz A/B/C | `correction-matrix.md` (29 regras) | revisão documental |
| FR-011 só CL-13/CL-14 executáveis; CL-17 orientada | `planner::default_strategies()` | `planner_test::test_rules_and_eligibility`, `executor_test::test_refused_with_student_data_and_for_guided_proposals` |
| FR-012 A vira orientada quando falha segurança | `proposal::is_executable()` | `proposal_test::test_is_executable`, testes de impedimento |
| FR-020 nove elementos | `correction_proposal` + template | `planner_test::test_cl13_executable_proposal`, `output_test::test_proposal_page`, Behat |
| FR-021/022 simulação | `planner::plan()` + `verifier` | `test_cl13_statement_declares_current_value`, `verifier_test` |
| FR-023 sem redução, sem método de nota | `quiz_attempts::describe()` | `test_cl13_never_reduces`; comparação do registro inteiro |
| FR-024 CL-14 (término conforme, futuro, abertura) | `quiz_timeclose::describe()` | `test_cl14_quiz_executable`, `test_cl14_impediments` |
| FR-025 CL-17 dependências | `completion_guidance` | `test_cl17_guidance_lists_dependencies` |
| FR-030 POST + sesskey | `correction.php` (`data_submitted`, `require_sesskey`) | Behat (form POST), `output_test` (sesskey no formulário) |
| FR-031 revalidação | `executor::execute()/run()` | `test_conflicts_never_write`, `test_activity_gone`, `test_capabilities_are_checked_on_the_server` |
| FR-032 só o campo + rotinas do núcleo | estratégias `apply()` | `test_cl13_applies_only_the_planned_field`, `test_cl14_sets_close_date_and_calendar` |
| FR-033 trava | `lock_config` `fix_<cmid>` | `test_busy_activity` |
| FR-034 releitura pela API | `persisted()` (`quiz_settings`, evento de calendário) | `test_value_not_read_back_is_a_failure` |
| FR-035/036 reauditoria, sem falso sucesso | `executor` + `correction_result` | `test_reaudit_failure_is_reported`, `output_test::test_result_messages` |
| FR-040/041/042 trilha e evento | `trail`, `correction_applied` | `test_cl13_applies_only_the_planned_field`, `test_failure_rolls_back_and_is_recorded` |
| FR-043 privacidade | `privacy\provider` | `privacy\provider_test` |
| FR-050/051 capacidades | `db/access.php`, `index.php`, `correction.php`, `executor` | `test_capabilities_are_checked_on_the_server`, Behat (professor) |
| FR-060/061 compatibilidade | JSON 1.0 inalterado | `contract_test`, `characterization_test`, suíte 001 (75), `output_test::test_report_action` |

Nenhum requisito sem tarefa; nenhuma tarefa sem requisito. Os 12 cenários experimentais pedidos estão cobertos
(ver `results.md` §3).

## 2. Constituição (v1.0.0)

| Princípio | Verificação | Resultado |
|---|---|---|
| I | Nenhuma dependência externa nova | ✅ |
| II | `db/upgrade.php` testado (0.2.0 → 0.3.0 no site da spec 002); escrita direta em 2 colunas de `quiz` justificada no README (R1) | ✅ |
| III | Nenhuma escrita em `snapshot`, regras, `engine::audit()`; prévia sem efeito (teste) | ✅ |
| IV | Capacidade própria distinta de `:audit`; prévia com atual/esperado/impacto; conflito; confirmação; trilha; reauditoria; sem lote | ✅ |
| V | Ids estáveis; política lida de `policy`; nenhuma regra alterada | ✅ |
| VI | Estados inalterados; proposta e resultado explicam motivos; falha nunca exibida como sucesso | ✅ |
| VII | Testes por estratégia (conforme/nada a corrigir, não conforme, sem permissão, conflito, falha); suíte completa registrada | ✅ |
| VIII | `require_login`, `require_capability`, `require_sesskey`; `riskbitmask` em `applycorrection`; sem segredos na trilha; privacy provider atualizado | ✅ |
| IX | Abstrações justificadas em Complexity Tracking (estratégias, proposta, verificador, facts, tabela) | ✅ |
| X | Spec, plano, tarefas e análise antes do código (commit `bed2031` precede `ad44c0b`) | ✅ |

Achados: nenhum CRITICAL. **MEDIUM** — decisão institucional sobre quem recebe `applycorrection` e marco de
encerramento do CL-14 seguem **[validar]**. **LOW** — janela residual de concorrência com edição manual simultânea
(documentada). **LOW** — `access_test` da spec 001 continua afirmando "sem risco" apenas para `:audit`/`:compare`;
`applycorrection` declara `RISK_CONFIG`.

## 3. Comparação com a Spec 001

| Aspecto | Spec 001 (0.2.0) | Spec 002 (0.3.0) |
|---|---|---|
| Escrita | nenhuma | somente `executor`, 2 colunas de `quiz`, após confirmação |
| Tabelas | nenhuma | `local_mooc_audit_fix` |
| Capacidades | `audit`, `compare` | + `preparecorrection`, `applycorrection` |
| Contrato JSON | 1.0 | 1.0 (inalterado) |
| Resultados das 29 regras | referência | idênticos (caracterização A/B; curso A antes/depois idêntico) |
| Testes | 75 PHPUnit, 2 Behat | 111 PHPUnit, 5 Behat |
| Privacidade | `null_provider` | provider com metadados, exportação e anonimização |
