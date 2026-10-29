# Specification Analysis Report — Spec 005

**Data**: 2026-10-29 · **Artefatos**: spec.md, plan.md, tasks.md, research.md, data-model.md, contracts/ ·
**Constituição**: v1.0.0

Análise não destrutiva antes da implementação (`/speckit-analyze`), atualizada ao final com o que mudou.

## Findings

| ID | Categoria | Severidade | Local | Resumo | Recomendação / Situação |
|---|---|---|---|---|---|
| A1 | Constituição VIII | MEDIUM | spec Clarifications (capacidades) | Pedido fala em "permissões de gestão das categorias"; a constituição fixa contexto de sistema para lotes | Capacidades novas no sistema **e** filtro por `audit` em cada curso (respeita papéis de categoria). Gerente só de categoria fica sem painel nesta versão — registrado **[validar]** |
| A2 | Constituição III | LOW | research R9 | Registrar a execução na abertura da página é escrita | Escrita apenas em tabela própria (III permite quando a spec justifica); `readonly_test` cobre a sala |
| A3 | Ambiguidade | MEDIUM | spec FR-011/FR-012 | "Regra executou normalmente" precisa de critério objetivo | Definido em data-model (tabela de classes): `_rule` ERRO/NV ou ocorrência ERRO/NV ⇒ não verificável |
| A4 | Cobertura | MEDIUM | FR-013 | Declarações de `POLICY_KEYS` podem estar incompletas (CL-11, CL-23 via `helpers::certs`) | T006 perturba cada chave e corrige declarações |
| A5 | Duplicação | LOW | data-model `run.summary` × `run.report.summary` | Resumo repetido na coluna | Aceito: o painel não deve decodificar relatórios inteiros (R10); o relatório é a fonte, a coluna é cópia imutável |
| A6 | Subespecificação | LOW | FR-028 | "Versão do plugin mudou" depende de comparar texto da versão | `pluginversion` gravado como `release (version)`; comparação exata com o instalado |
| A7 | Cobertura | LOW | SC-008 | Critério depende de homologação com a equipe | Mantido **[validar]**; roteiro em quickstart |
| A8 | Inconsistência | LOW | backlog (`docs/backlog.md`) | Backlog descreve 005 como proposta | T039 atualiza o backlog |

## Coverage Summary

| Requisito | Tarefas |
|---|---|
| FR-001–FR-007 (histórico) | T008–T016 |
| FR-008–FR-009 (chave) | T003–T005 |
| FR-010–FR-015 (evolução) | T006, T017–T020 |
| FR-016–FR-023 (lote) | T021–T026 |
| FR-024–FR-028 (painel) | T027–T029 |
| FR-029–FR-034 (segurança, privacidade, retenção) | T030–T033 |
| FR-035–FR-036 (operação) | T016, T025, T034–T035 |

## Constitution Alignment

Nenhuma violação de MUST. Princípio IX: classes novas justificadas em Complexity Tracking. Princípio IV: nenhuma
correção em lote (fora de escopo explícito).

## Unmapped Tasks

Nenhuma. T001–T002 e T036–T040 são transversais.

## Metrics

- Requisitos funcionais: 36 · Critérios de sucesso: 8 · Tarefas: 40
- Cobertura (requisitos com ≥1 tarefa): 100%
- Ambiguidades remanescentes: 5 **[validar]** (institucionais) · CRITICAL: 0

## Next Actions

Prosseguir para a implementação na ordem do `tasks.md`. Reexecutar esta análise ao final (seção "Pós-implementação"
em `results.md`).
