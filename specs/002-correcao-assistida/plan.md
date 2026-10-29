# Implementation Plan: Correção Assistida de Inconformidades

**Branch**: `claude/spec-002-correcao-assistida-9d1925` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-correcao-assistida/spec.md`

## Summary

Acrescentar ao `local_mooc_audit` um componente de correção separado do motor de auditoria
(`local_mooc_audit\local\correction`): um **planejador** que, a partir de uma auditoria fresca, monta uma
**proposta** por estratégia de regra/tipo de atividade, simula o impacto sobre o snapshot em memória e decide se é
executável; um **executor** que, por POST confirmado, revalida permissões e estado (impressão digital), aplica a
alteração pela rotina do núcleo para aquele campo, verifica a persistência, registra (tabela + evento) e reaudita.
Três estratégias reais: tentativas de questionário (CL-13, executável), encerramento de questionário (CL-14,
executável), encerramento de outros tipos (CL-14, orientada) e critério de conclusão (CL-17, orientada).

## Technical Context

**Language/Version**: PHP 8.1+ (ambiente: PHP 8.3), Moodle 4.5.15
**Primary Dependencies**: APIs do núcleo (DML, `course_modinfo`, `mod_quiz\quiz_settings`, calendário do questionário,
`core\lock`, eventos, `core_privacy`), Mustache
**Storage**: uma tabela nova `local_mooc_audit_fix` (XMLDB) — trilha das correções executadas
**Testing**: PHPUnit (`advanced_testcase`), Behat (`@javascript`), PHPCS `moodle`
**Target Platform**: plugin local Moodle 4.5 (PostgreSQL/MySQL)
**Project Type**: plugin Moodle
**Performance Goals**: prévia ≤ 2 auditorias (snapshot + simulação) ≈ < 1 s nos cursos A/B; execução + reauditoria < 2 s
**Constraints**: auditoria somente leitura; nenhuma escrita sem POST + sesskey + capacidades + impressão digital;
nenhuma dependência nova
**Scale/Scope**: 3 regras (CL-13, CL-14, CL-17); 1 tipo executável (quiz); 1 tabela; 2 capacidades; 1 página

## Constitution Check

| Princípio | Avaliação | Situação |
|---|---|---|
| I Independência | Nada externo; correção usa só o Moodle local | ✅ |
| II Compatibilidade | Plugin padrão, upgrade por `db/upgrade.php`; escrita direta em `quiz` justificada (research R1), restrita a lista branca de 2 colunas, registrada no README | ✅ com justificativa |
| III Auditoria não modifica | Escritas só em `local\correction\executor`; `readonly_test` mantido e ampliado (abrir/cancelar proposta) | ✅ |
| IV Correção assistida | Capacidade própria; prévia com atual/esperado/impacto; impressão digital; confirmação explícita; registro; reauditoria; sem lote | ✅ |
| V Regras rastreáveis | Estratégias referenciam o id da regra; propostas citam a fonte; política lida da `policy` | ✅ |
| VI Transparência | Proposta explica impedimentos; resultado distingue aplicada/falha/conflito/reauditoria com erro; nada de sucesso sem persistência | ✅ |
| VII Testabilidade | Testes PHPUnit por estratégia (executável, conflito, permissão, estudantes, falha, reauditoria, registro) e Behat | ✅ |
| VIII Segurança | `require_login`, `require_capability`, `require_sesskey`, POST; capacidades com `riskbitmask`; privacy provider atualizado; sem dados pessoais além do executor | ✅ |
| IX Simplicidade | Uma classe base de estratégia (4 implementações concretas nesta entrega), planejador, executor, verificador, trilha; sem interfaces extras; ver Complexity Tracking | ✅ |
| X Incremental | Spec, plano, tarefas e análise antes do código; fora de escopo declarado; contrato JSON intacto | ✅ |

**Portões de entrega** (constituição): `version.php` incrementado (0.3.0); README do plugin atualizado (escrita
justificada, capacidades, limitações); catálogo de regras atualizado (coluna de correção).

## Phase 0 — Research

Ver [research.md](research.md): R1 mecanismo de escrita (núcleo atualiza campos isolados do quiz pelo callback de
calendário; `quiz_update_instance` exige dados de formulário), R2 dados de estudantes, R3 impressão digital e trava,
R4 simulação, R5 trilha e privacidade, R6 capacidades, R7 interface, R8 reauditoria, R9 datas, R10 reversão, R11
dependências de conclusão.

## Phase 1 — Design

- Modelo de dados: [data-model.md](data-model.md) (proposta, linha da trilha, evento).
- Contrato da página: [contracts/correction-page.md](contracts/correction-page.md).
- Matriz de elegibilidade: [correction-matrix.md](correction-matrix.md).
- Verificação manual: [quickstart.md](quickstart.md).

## Project Structure

### Documentation (this feature)

```text
specs/002-correcao-assistida/
├── spec.md · plan.md · research.md · data-model.md · quickstart.md · tasks.md · analysis.md
├── correction-matrix.md
├── results.md                 # evidências e resultados (fase 5)
├── checklists/requirements.md
└── contracts/correction-page.md
```

### Source Code (plugin/local_mooc_audit)

```text
classes/local/correction/
├── proposal.php              # valor imutável da proposta (+ fingerprint, export para template)
├── planner.php               # auditoria fresca → estratégia → proposta + simulação
├── executor.php              # confirmação: revalidação, trava, transação, verificação, trilha, reauditoria
├── verifier.php              # comparação antes × depois (pura): alvo resolvido, introduzidas, resolvidas
├── trail.php                 # tabela local_mooc_audit_fix + evento
├── facts.php                 # consultas somente leitura de dados de estudantes / dependências
└── strategy/
    ├── base.php              # contrato das estratégias (propor, aplicar, verificar)
    ├── quiz_attempts.php     # CL-13 (A)
    ├── quiz_timeclose.php    # CL-14 questionário (A)
    ├── close_date_guidance.php  # CL-14 demais tipos (B)
    └── completion_guidance.php  # CL-17 (B)
classes/event/correction_applied.php
classes/output/correction.php        # renderable da prévia e do resultado
classes/privacy/provider.php         # null_provider → metadata + request providers
correction.php                       # página GET (prévia) / POST (confirmação)
db/access.php · db/install.xml · db/upgrade.php
templates/correction.mustache · templates/correction_result.mustache · templates/report_group.mustache (ação)
lang/en · lang/pt_br
tests/correction/*_test.php · tests/behat/correction.feature
```

O motor (`engine`, `snapshot`, regras) **não muda**. `output\report` recebe um parâmetro opcional para incluir a
ação de proposta (padrão: sem ação; testes da Spec 001 inalterados).

## Estratégia de testes

| Nível | O que prova | Onde |
|---|---|---|
| Unidade (pura) | `verifier` (resolvidas/introduzidas), `proposal::fingerprint` estável, matriz cobre o catálogo | `tests/correction/verifier_test.php`, `proposal_test.php` |
| Planejador | elegibilidade por regra/tipo; nove elementos; simulação; impedimentos (estudantes, dependência CL-07, abertura, redução); avisos | `planner_test.php` |
| Executor | sucesso (somente campos previstos, API lê o novo valor, calendário), cancelamento/GET sem escrita, permissão, conflito (curso alterado, política alterada, atividade removida), dados de estudantes, idempotência, falha com rollback, falha de verificação, reauditoria com erro, trava | `executor_test.php` |
| Trilha/privacidade | linha completa, evento, export, anonimização | `trail_test.php`, `privacy_test.php` |
| Não modificação | prévia e cancelamento não alteram tabelas; auditoria segue somente leitura | `readonly_test.php` (ampliado) |
| Interface | ver proposta, confirmar, cancelar, sem permissão | `tests/behat/correction.feature` |
| Regressão | suíte da Spec 001 (75 testes) e caracterização A/B | suíte completa |

Critério de sucesso dos testes de execução: além do banco, `\mod_quiz\quiz_settings::create()->get_quiz()` lê o
novo valor e a reauditoria marca a regra alvo como `CONFORME`.

## Matriz de riscos

| # | Risco | Prob. | Impacto | Mitigação | Residual |
|---|---|---|---|---|---|
| 1 | Correção apaga configuração não relacionada (ex.: opções de revisão) | média se usar `quiz_update_instance` | alto | update parcial do campo; teste compara o registro inteiro antes/depois | baixo |
| 2 | Alteração em sala com estudantes muda tentativas/notas | média | alto | impedimento com tentativas/notas; aviso com inscritos | baixo |
| 3 | Estado muda entre prévia e confirmação | média | médio | impressão digital com `cacherev`; releitura em transação | baixo (janela ms com edição manual simultânea) |
| 4 | Dupla submissão / atualização da página | alta | baixo | trava por atividade; idempotência por reconstrução da proposta | nulo |
| 5 | CSRF / execução por link | baixa | alto | POST + `sesskey` + capacidade no servidor | nulo |
| 6 | Correção cria nova inconformidade | média | médio | simulação bloqueia; reauditoria relata introduzidas | baixo |
| 7 | Mensagem de sucesso falsa | baixa | alto | sucesso só após releitura pela API e reauditoria | nulo |
| 8 | Encerramento em fuso errado | baixa | médio | mesmo instante do término do curso; exibição em dois fusos | baixo |
| 9 | Escalada de privilégio (quem audita corrige) | baixa | alto | capacidades separadas + `manageactivities` na atividade | nulo |
| 10 | Trilha com dados pessoais | baixa | médio | só `userid`; privacy provider; anonimização | baixo |
| 11 | Plugin de terceiros (jogo) com campo de data diferente | média | médio | CL-14 executável só para quiz; demais orientados | nulo |
| 12 | Regressão na auditoria | baixa | alto | motor intocado; caracterização A/B; suíte 001 | baixo |

## O que preservar e o que muda

- **Preservado**: motor, regras, snapshot, contrato JSON 1.0, CLI, página de auditoria (exceto a ação opcional),
  capacidades `audit` e `compare`, testes da Spec 001.
- **Muda**: versão 0.3.0 (2026102902); capacidades novas; tabela nova; página `correction.php`; ação no relatório;
  privacy provider deixa de ser `null_provider`.

## Complexity Tracking

| Abstração | Por que é necessária | Alternativa mais simples rejeitada |
|---|---|---|
| `strategy\base` + 4 estratégias | 3 regras com lógica distinta e um tipo com dois comportamentos (CL-14 quiz × demais); a spec exige demonstrar extensibilidade | `switch` no planejador misturaria propor/aplicar/verificar de regras diferentes e cresceria a cada regra |
| `proposal` (classe de valor) | o mesmo objeto alimenta prévia, impressão digital, executor e testes | arrays soltos: sem garantia de campos obrigatórios (nove elementos) |
| `verifier` separado | a mesma comparação serve à simulação (antes) e à reauditoria (depois); testável sem banco | duplicar a comparação no planejador e no executor |
| `facts` separado | consultas somente leitura de estudantes/dependências fora do snapshot (que não deve mudar) e reutilizadas por várias estratégias | acrescentar ao snapshot mudaria o coletor da auditoria (princípio VII exige teste por campo e alteraria a 001) |
| tabela `local_mooc_audit_fix` | exigência de registro mínimo com resultado da reauditoria; log padrão pode ser desativado | apenas evento: não guarda reauditoria nem sobrevive a expurgo de logs |
