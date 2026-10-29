# Tasks: Comparação entre Ofertas MOOC

**Input**: Design documents from `specs/003-comparacao-ofertas/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: obrigatórios (spec Parte IX e constituição VII). Caminhos relativos a `plugin/local_mooc_audit/` salvo
indicação.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [X] T001 Criar o ambiente isolado da Spec 003 (`moocaudit-s3-web` porta 8094, banco `moodle_s3`, rede `moocaudit_s3`, `moocaudit-s3-selenium`) e documentar a receita em `moodle-env/README.md` (seção "Ambiente da spec 003")
- [X] T002 Registrar a linha de base: PHPUnit completo no ambiente s3 (118 testes) antes de qualquer alteração
- [X] T003 [P] Extrair o validador de JSON Schema de `tests/contract_test.php` para `tests/helper/schema_validator.php` (suporta type, const, enum, pattern, required, properties, additionalProperties, items, $ref, allOf/if/then, minLength) e fazer `contract_test` usá-lo sem mudar asserções

## Phase 2: Foundational

- [X] T004 Criar `schema/comparison-v1.0.schema.json` conforme data-model (`contract: "comparison"`, `contract_version: "1.0"`, `problematic` coerente com `relevance`) e copiar para `specs/003-comparacao-ofertas/contracts/`
- [X] T005 Strings de interface pt_br/en da comparação (rótulos de seções, tipos de diferença, códigos, classes de pendência, estratégias, confianças, motivos de ambiguidade, veredito, erros) em `lang/pt_br/local_mooc_audit.php` e `lang/en/local_mooc_audit.php`
- [X] T006 Impressão de conteúdo e chaves de módulo (`fingerprint()`, `settings_fingerprint()`, chaves tipo|seção|nome, tipo|nome, tipo|seção|posição) em `classes/local/comparison/matcher.php`

## Phase 3: User Story 1 — Comparar duas salas e entender o que mudou (P1) 🎯 MVP

**Goal**: comparar duas salas por id e obter preservadas, ausentes, adicionais, modificadas e ambíguas com o
contrato `comparison` 1.0, CLI e página.

**Independent Test**: A → importação de A: 12 preservadas, "Avisos" ambíguo, 0 ausentes, ids/datas esperados.

- [X] T007 [P] [US1] Testes de correspondência em `tests/local/comparison/matcher_test.php`: correspondência correta, ids alterados, ordem embaralhada (determinismo), unicidade nos dois lados
- [X] T008 [P] [US1] Testes de diferenças em `tests/local/comparison/differ_test.php`: `problematic` (só texto → false; tentativas → true; só datas → preservada), conclusão, nota, restrições remapeadas, configurações modificadas, diferenças esperadas de curso
- [X] T009 [US1] Implementar os passes 1–6 e o congelamento de ambiguidades em `classes/local/comparison/matcher.php` (research R2)
- [X] T010 [US1] Implementar `classes/local/comparison/differ.php` com a tabela do research R3 (cada diferença com `scope`, `field`, `source`, `target`, `kind`, `code`, `explanation`), remapeamento de restrições (R4) e correção de `problematic`
- [X] T011 [US1] Reescrever `classes/comparator.php` como orquestrador: `compare(array $src, array $dst, array $manual = [])`, `run(int $sourceid, int $targetid, array $manual = [], ?array $policyoverride = null)`, `to_json()`, `render_text()`, `summary`/veredito do data-model
- [X] T012 [US1] Atualizar `cli/compare.php` (`--pairs`, `--policy`, `--json`, `--out`; saída por `comparator::to_json`/`render_text`)
- [X] T013 [US1] Formulário `classes/form/compare_form.php` (elementos `course` com `requiredcapabilities`, confirmação obrigatória, origem ≠ destino)
- [X] T014 [US1] Página `compare.php` (parâmetros e acesso do contrato da página; JSON por `format=json`; aviso de inversão)
- [X] T015 [US1] `classes/output/comparison_report.php`, `templates/comparison.mustache`, `templates/comparison_diffs.mustache` e `render_comparison_report()` em `classes/output/renderer.php` (seções na ordem do FR-022)
- [X] T016 [US1] `admin_externalpage` "Comparar ofertas" em `settings.php` (categoria `courses`, capacidade `local/mooc_audit:compare`) e link "Comparar com a oferta anterior" em `index.php`/`classes/output/report.php`/`templates/report.mustache`
- [X] T017 [P] [US1] Teste de contrato `tests/comparison_contract_test.php` (saídas de A→importação e B→importação validam o schema; validador rejeita violação; contrato individual 1.0 inalterado)
- [X] T018 [P] [US1] Teste de saída `tests/comparison_output_test.php` (seções presentes, escape de nomes, ausência de botão de correção)

## Phase 4: User Story 2 — Persistente, novo e pendência da nova oferta (P1)

**Goal**: relacionar os resultados das duas auditorias com a política certa para cada sala.

**Independent Test**: B 2026 → importação 2027: problemas estruturais persistentes; CL-06/07 sem "persistente".

- [X] T019 [P] [US2] Testes em `tests/local/comparison/audit_relation_test.php`: cada classe do data-model (persistente, novo, novo em adicional, indeterminado, pendência de oferta, não verificável, resolvido, ausente, não aplicável), chave de local sem ids, regra do ciclo nunca persistente, constante `CYCLE_RULES`
- [X] T020 [US2] Implementar `classes/local/comparison/audit_relation.php` (chave R7, ordem de classificação do data-model)
- [X] T021 [US2] Em `comparator::run()`: política do destino = `policy::load($override)`; da origem = mesma com `reoffer_year` do ano de início da origem (`userdate(..., '%Y', 99, false)`), `sem_contexto` sem data; links pelo `locator`; bloco `policy` do contrato
- [X] T022 [US2] Seção "Pendências da sala de destino" no template e na saída de texto

## Phase 5: User Story 3 — Não inventar correspondências (P2)

**Independent Test**: Avisos duplicado; Libras com mesmo nome; movida; renomeada; manual.

- [X] T023 [P] [US3] Testes em `matcher_test.php`: duplicata 1×2 (`duplicado_no_destino`, nenhum par, posição não decide), 2×1, mesmo nome com conteúdo diferente (casa por conteúdo), clones idênticos (`conteudo_equivalente`, `settings_identical`), movida de seção, renomeada (conteúdo), renomeada+editada (posição), manual válida e inválidas
- [X] T024 [US3] Pares manuais (`pair[]`) em `matcher.php`/`comparator.php`, `manual_pairs` no contrato e formulário de correspondência manual na seção de ambiguidades do template

## Phase 6: User Story 4 — Evidência de conteúdo, arquivos e avaliação (P2)

**Independent Test**: cenários C, D, E.

- [X] T025 [P] [US4] Testes em `differ_test.php`: glossário `content_lost` com `dependents` (jogo), `glossary_entries_available`, referência de jogo remapeada (esperada) e sem par (relevante), três códigos de arquivo, restrição com condição perdida/sem par/data deslocada, seção oculta
- [X] T026 [US4] Implementar dependentes de glossário, arquivos (R5) e restrições de seção em `differ.php`
- [X] T027 [US4] Teste de integração `tests/comparator_test.php` com importação real (backup/restore em modo importação) reproduzindo cenários A, B, C, D, E, F e G

## Phase 7: User Story 5 — Proteções (P2)

- [X] T028 [P] [US5] `tests/compare_access_test.php`: gerente do site permitido; gerente de categoria só nas suas salas; professor editor negado; estudante negado; busca restrita por capacidade
- [X] T029 [P] [US5] `tests/compare_readonly_test.php`: 17 tabelas + `timemodified`/`cacherev` das duas salas idênticos antes/depois de `run()`; relatório sem `userid`/nomes de usuários com estudante inscrito e tentativa
- [X] T030 [US5] Behat `tests/behat/compare.feature` (`@local_mooc_audit @javascript @accessibility`): seleção por pesquisa, confirmação obrigatória, relatório, ambiguidade, correspondência manual, acesso negado ao professor editor

## Phase 8: User Story 6 — Oferta temporal (P3)

- [X] T031 [US6] Casos de G em `tests/comparator_test.php` (ajustada × não ajustada) e verificação do cabeçalho de política nas saídas

## Phase 9: Polish & Cross-Cutting

- [X] T032 `cli/make_scenarios.php` (somente laboratório; guardas de `make_import.php`) montando os cenários A–G
- [X] T033 Executar os cenários no laboratório s3 e gravar evidências em `plugin-results/0.4.0/` (texto e JSON por cenário)
- [X] T034 Medir desempenho com duas salas de 151 módulos (script de medição em `plugin-results/0.4.0/make151.php`), 3 execuções, `plugin-results/0.4.0/medicoes.md`
- [X] T035 `version.php` 0.4.0 (2026102906), `engine::VERSION` 0.4.0, README do plugin, `docs/backlog.md`, `docs/catalogo-regras.md` (seção de comparação)
- [X] T036 PHPUnit completo, Behat completo (Specs 001–003), PHPCS `moodle`, `php -l`; registrar em `plugin-results/0.4.0/`
- [ ] T037 Revisão de segurança e de código; registro em `specs/003-comparacao-ofertas/results.md` com limitações e respostas às perguntas finais
- [ ] T038 `/speckit-analyze` → `specs/003-comparacao-ofertas/analysis.md`
- [ ] T039 Abrir PR para revisão (sem merge)

## Dependencies & Execution Order

- Setup (T001–T003) → Foundational (T004–T006) → US1 (MVP) → US2 → US3/US4 (paralelas entre si) → US5 → US6 → Polish.
- US2 depende de `comparator::run()` (T011). US3 e US4 estendem `matcher`/`differ` criados na US1.
- Testes de cada história antes da implementação correspondente.

## Parallel Example: User Story 1

```text
T007 matcher_test.php  ∥  T008 differ_test.php   (arquivos distintos)
T017 comparison_contract_test.php  ∥  T018 comparison_output_test.php
```

## Implementation Strategy

1. MVP = US1 (comparação estrutural confiável + página + CLI + contrato).
2. US2 entrega o diferencial (relação entre auditorias com a política certa).
3. US3/US4 endurecem correspondência e evidência com os cenários reais.
4. US5/US6 fecham proteção e temporalidade; Polish produz evidências e PR.
