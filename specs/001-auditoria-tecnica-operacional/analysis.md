# Specification Analysis Report — 001 Auditoria Técnica Operacional

**Data:** 2026-10-28 · **Artefatos:** `spec.md`, `plan.md` (+ `research.md`, `data-model.md`, `contracts/`),
`tasks.md`, `.specify/memory/constitution.md` v1.0.0 · **Modo:** análise somente leitura (`/speckit-analyze`);
este arquivo registra o resultado para avaliação, nenhum artefato foi alterado por ela.

## Findings

| ID | Category | Severity | Location(s) | Summary | Recommendation |
|----|----------|----------|-------------|---------|----------------|
| C1 | Coverage | **HIGH** | spec.md Edge Cases ("acompanhamento de conclusão desabilitado globalmente → regras de conclusão NV") | Nenhuma tarefa implementa esse comportamento; hoje nenhuma regra consulta `site.enablecompletion` (já coletado no snapshot). Mudaria o comportamento sem passar pela caracterização | Adicionar tarefa na fase 2 (após T045): `engine` marca como NAO_VERIFICAVEL, com motivo, as regras de `completion_restrictions`/`certification` quando `$CFG->enablecompletion` = 0, com teste em `engine_test`; ou remover o edge case da spec |
| C2 | Coverage | MEDIUM | spec.md SC-002; tasks.md | Meta de desempenho (< 5 s para 150 módulos) sem tarefa que a meça | Acrescentar a T101 a medição de `timing.total_s` num curso sintético com 150 módulos (gerado pelo `course_builder`) e registrar em `plugin-results/0.2.0/` |
| C3 | Coverage | MEDIUM | spec.md SC-003 (≥ 90 % com link); tasks T057/T058 | Links são implementados, mas a proporção não é medida | Em T101, contar `link != null` entre NAO_CONFORME/ATENCAO de A, B, 55 e 670 e registrar |
| C4 | Coverage | MEDIUM | spec.md FR-023 (acessibilidade da página) | Só T050 menciona `th`/cabeçalhos; nenhuma verificação | Incluir em T098 (Behat) passos de verificação de cabeçalhos/links com texto, ou checklist manual em T104 |
| I1 | Inconsistency | MEDIUM | data-model.md (Resultado.local obrigatório para NAO_CONFORME **e** ATENCAO) × contracts/report-v1.0.schema.json e T054 (só NAO_CONFORME) | Regra de validação divergente entre modelo, contrato e teste | Alinhar: exigir `local` e `evidencia` também para ATENCAO no schema (`allOf`) e em T054; `orientacao` continua obrigatória só para NAO_CONFORME |
| I2 | Inconsistency | LOW | research R5/T047 (`locator` recebe só `snapshot_modules`) × data-model (`locus = section` precisa do id da seção para `/course/section.php`) | Assinatura insuficiente para montar o link de seção | T047: passar `snapshot['sections']` ao `locator` (ou resolver o id da seção no `engine`) |
| I3 | Inconsistency | LOW | tasks T001/T002 (`tests/fixtures/course_builder.php` usado por um CLI) | Arquivo sob `tests/` exigido por script de laboratório; fora do autoload | Preferir o gerador oficial de componente: `tests/generator/lib.php` (`local_mooc_audit_generator`), acessível por `get_plugin_generator('local_mooc_audit')` em PHPUnit e por `require_once` no CLI |
| T1 | Terminology | LOW | spec.md US1 cenário 5 e FR-017 ("não verificáveis") × contracts/page.md e T062 (bloco "Não foi possível verificar" inclui NAO_APLICAVEL) | NAO_APLICAVEL não é "não foi possível verificar": a regra simplesmente não se aplica | Rotular o bloco "Não verificados ou não aplicáveis" com subtítulos distintos, ou separar em dois blocos; atualizar page.md, T049/T050/T062 |
| D1 | Duplication | LOW | spec.md FR-005 × FR-027; FR-026 × SC-006 | Mesma exigência (teste de não modificação; teste por regra) em requisito e em critério de sucesso | Aceitável (rastreabilidade); se consolidar, manter FR como fonte e SC como medida |
| A1 | Ambiguity | LOW | spec.md FR-004 ("papéis de gerência") | "gerência" = arquétipo `manager`; não dito explicitamente | Acrescentar "(arquétipo gerente do Moodle)" |
| U1 | Underspecification | LOW | spec.md Edge Case "dois recursos com o mesmo nome … aceita o primeiro encontrado" | Comportamento herdado da PoC (`$f[0]`) sem teste | Acrescentar cenário a T074/T075 (dois fóruns/pesquisas homônimos → avalia o primeiro, sem duplicata) |
| Q1 | Constitution | LOW | tasks T041 (orientações via `get_string`) × caracterização (estados/gravidades) | Textos de orientação podem mudar sem detecção; aceitável porque a caracterização compara estados, mas SC-007 fala em "estados por regra" (coerente) | Nenhuma ação; registrar que textos não são parte do critério SC-007 |

Nenhum achado CRITICAL. Nenhum conflito com princípios MUST da constituição foi encontrado: II (callback oficial), III
(T097), V (metadados por regra), VI (blocos NV/ERRO, `policy_warnings`), VII (fase 8), VIII (T052, T053), IX
(Complexity Tracking preenchido com duas abstrações justificadas), X (fluxo completo, contrato versionado).

## Coverage Summary

| Requirement Key | Has Task? | Task IDs | Notes |
|---|---|---|---|
| FR-001 | ✓ | T048, T053 | |
| FR-002 | ✓ | T051 | |
| FR-003 | ✓ | T051, T053 | |
| FR-004 | ✓ | T052, T053 | ver A1 |
| FR-005 | ✓ | T097 | |
| FR-006 | ✓ | T003–T004, T012–T045 | caracterização |
| FR-007 | ✓ | T009, T010, T012–T040, T042 | |
| FR-008 | ✓ | T046 (política inalterada), T067 | |
| FR-009 | ✓ | T046, T060, T061, T062 | |
| FR-010 | ✓ | T061 | |
| FR-011 | ✓ | T005, T054 | |
| FR-012 | ✓ | T054 | ver I1 (ATENCAO) |
| FR-013 | ✓ | T043, T064 | |
| FR-014 | ✓ | T063, fase 8 (NA/NV por regra) | |
| FR-015 | ✓ | T062 | |
| FR-016 | ✓ | T043, T049, T054 | |
| FR-017 | ✓ | T049, T050, T062 | ver T1 |
| FR-018 | ✓ | T050 | |
| FR-019 | ✓ | T047, T056, T057, T058 | ver I2 |
| FR-020 | ✓ | T059 | |
| FR-021 | ✓ | T049, T050 | |
| FR-022 | ✓ | T007, T041, T051, T055 | verificação só por grep (quickstart §7) |
| FR-023 | ✓ (fraco) | T050, T098 | ver C4 |
| FR-024 | ✓ | T043, T065, T066 | |
| FR-025 | ✓ | T066 | CLI inalterado |
| FR-026 | ✓ | T063, T069–T096 | |
| FR-027 | ✓ | T053, T097 | |
| FR-028 | ✓ | T103 | |
| SC-002 | ✗ | — | ver C2 |
| SC-003 | ✗ (medida) | T057, T058 | ver C3 |
| SC-005 | ✓ | T043, T054, T062 | |
| SC-006 | ✓ | fase 8, T097, T053, T100 | |
| SC-007 | ✓ | T045, T101 | |
| SC-008 | ✓ | T097 | |
| SC-009 | ✓ (fraco) | T007, T041, T104 | grep heurístico |
| SC-001, SC-004 | n/a | — | verificação manual / pós-entrega (não exigem construção) |

**Unmapped Tasks:** nenhuma; T008 (versão), T099 (lint), T102 (README) e T104 (quickstart) atendem aos portões da
constituição.

**Metrics:**

- Total Requirements: 28 FR + 9 SC (7 SC exigem construção/medição)
- Total Tasks: 104
- Coverage: FR 28/28 (100 %; 2 com verificação fraca); SC construíveis 5/7 (SC-002 e SC-003 sem medição)
- Ambiguity Count: 1 (A1)
- Duplication Count: 1 (D1)
- Critical Issues Count: 0 · High: 1 (C1) · Medium: 4 · Low: 6

## Ajustes aplicados após a análise (2026-10-28)

- C1 → tarefa **T047b** adicionada à fase 2 (NV para regras de conclusão/certificação quando conclusão ou restrições
  estiverem desabilitadas no site) com teste em `engine_test`.
- C2, C3 → medições acrescentadas a **T101**. C4 → verificação acrescentada a **T098**.
- I1 → schema JSON e **T054** exigem `local`/`evidencia` para ATENCAO. I2 → **T047** recebe as seções do snapshot.
- I3 → gerador oficial `tests/generator/lib.php` em **T001/T002/T003/T068** e no plano/pesquisa.
- T1 → bloco "Não verificados ou não aplicáveis" com subtítulos em `page.md`, spec (US1-5, FR-017) e **T062**.
- A1 → FR-004 explicita "arquétipo gerente". U1 → cenário de homônimos em **T074**. D1, Q1 → sem ação.
- Total de tarefas: 105. Nenhum achado aberto acima de LOW.

## Next Actions

1. Validar institucionalmente os itens **[validar]** da spec (permissão para proponentes; decisão 10) e as decisões
   listadas em `docs/relatorio-adocao-sdd.md` §2.
2. Com a autorização, iniciar `/speckit-implement` pela fase 1 de `tasks.md`; parar e validar com a equipe após a
   fase 3 (US1).
3. Não é necessário refazer `/speckit-specify` nem `/speckit-plan`; uma nova rodada de `/speckit-analyze` é opcional
   após qualquer edição futura dos artefatos.
