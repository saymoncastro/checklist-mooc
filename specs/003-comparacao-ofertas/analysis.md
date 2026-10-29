# Specification Analysis Report — Spec 003 (Comparação entre Ofertas)

**Data:** 2026-10-29 · **Artefatos:** spec.md, plan.md, research.md, data-model.md, contracts/, tasks.md, código e
testes da branch · **Constituição:** v1.0.0

## Achados

| ID | Categoria | Severidade | Local | Resumo | Resolução |
|---|---|---|---|---|---|
| A1 | Inconsistência | MEDIUM | data-model `Ambiguity.settings_identical` × matcher | Definição original (multiconjunto igual nos dois lados) dava falso para duplicatas 1×2 idênticas | Redefinido como "todos os módulos do grupo com a mesma impressão de configuração"; data-model e teste atualizados |
| A2 | Lacuna de regra | MEDIUM | data-model regra 3 de classificação | ATENÇÃO na origem que vira NÃO CONFORME no destino era "persistente" (cenário C, CL-18) e escondia o agravamento | Persistente só quando a origem é pelo menos tão grave; teste `test_worsening_is_new_and_improvement_persists` |
| A3 | Inconsistência | LOW | contracts/compare-page.md (CLI) × código | Contrato dizia `--pair` repetível; `cli_get_params` não aceita repetição | Contrato e tarefa T012 alterados para `--pairs=a-b,c-d` |
| A4 | Cobertura | LOW | FR-005 | Aviso de inversão sem teste | `differ_test::test_inverted_order_warning` |
| A5 | Ambiguidade | LOW | spec US1.1 ("12 preservadas") | Depende de a sala nova nascer com o fórum "Avisos"; no PHPUnit isso também ocorre | Teste A verifica a invariante (preservadas + ambíguas = módulos da origem), não um número fixo |
| A6 | Achado de comportamento | INFO | US1/US6 × cenário A | A importação mantém os encerramentos da oferta anterior: CL-14 aparece como pendência mesmo na importação "correta" | Comportamento correto (pendência da nova oferta); registrado em results.md e medicoes.md |
| A7 | Subespecificação | INFO | US2 aceite 1 | Cenários E/F (salas sem nota) mostram CL-12 "novo" por materialização tardia do livro de notas | Registrado como limitação de sala sintética (results §6.7) |

## Cobertura de requisitos

| Requisito | Tarefas | Testes / evidência |
|---|---|---|
| FR-001 página + link | T014, T016 | Behat `compare.feature` (admin e link do relatório); `comparison_output_test::test_report_link` |
| FR-002 seleção por nome/id restrita | T013 | Behat (autocomplete); `compare_access_test::test_course_search_is_restricted`; parâmetros `source`/`target` |
| FR-003 confirmação, origem ≠ destino | T013, T014 | Behat (mensagem de confirmação); validação do formulário; CLI `cmp_same_course` |
| FR-004 capacidade nas duas salas | T014, T028 | `compare_access_test` |
| FR-005 inversão | T014 | `differ_test::test_inverted_order_warning` |
| FR-006–010 correspondência | T009, T023 | `matcher_test` (13), cenários A, B, F |
| FR-011 pares manuais | T024 | `matcher_test::test_manual_pairs`, cenário F, Behat |
| FR-012–017 diferenças | T010, T026 | `differ_test` (9), cenários C, D, E |
| FR-018–021 relação e política | T020, T021 | `audit_relation_test` (6), cenários B, G, `test_source_without_start_date` |
| FR-022 ordem das seções | T015 | `comparison_output_test::test_sections_order_and_escaping` |
| FR-023–024 contrato | T004, T017 | `comparison_contract_test`, validação em todos os cenários, `contract_test` |
| FR-025–027 segurança | T029 | `compare_readonly_test`, `comparison_output_test` (escape, sem correção) |
| SC-005 desempenho | T034 | `plugin-results/0.4.0/medicoes.md` |
| SC-007 regressão | T036 | PHPUnit 162 (118 anteriores + 44 novos), Behat 9 cenários |

Requisitos sem tarefa: nenhum. Tarefas sem requisito: T001–T003 (infraestrutura), T032–T035 (evidências e entrega).

## Constituição

| Princípio | Situação |
|---|---|
| I Independência | Conforme: só ids de curso |
| II Compatibilidade | Conforme: nenhum SQL novo; elementos do núcleo (`course`, `admin_externalpage`) |
| III Não modifica | Conforme: `compare_readonly_test`; scripts de cenário fora do caminho e com guarda |
| IV Correção | Conforme: nenhuma ação de correção |
| V Rastreável | Conforme: itens citam a regra; política lida da `policy` |
| VI Transparência | Conforme: estratégia/confiança por par; heurística = confiança média; NV separado |
| VII Testabilidade | Conforme: teste por estratégia, código, classe; suíte completa registrada |
| VIII Segurança | Conforme: ver results.md §5 |
| IX Simplicidade | Conforme com justificativa (Complexity Tracking do plano) |
| X Incremental | Conforme: contrato versionado, fora de escopo declarado |

Nenhum achado CRITICAL ou HIGH pendente.
