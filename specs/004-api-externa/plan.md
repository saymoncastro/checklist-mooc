# Implementation Plan: API Externa (Web Services) do Auditor MOOC

**Branch**: `claude/spec-004-api-externa-96cf46` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-api-externa/spec.md`

## Summary

Expor por Web Services nativos do Moodle três operações sobre o histórico da Spec 005 — pedir a auditoria de uma
sala (só enfileira), consultar uma execução por id e consultar a última auditoria de uma sala — sob uma capacidade
própria `local/mooc_audit:api` somada às regras de acesso das páginas, sem tabela nova e sem segunda implementação das
regras de negócio:

- `classes/external/request_audit.php`, `get_run.php`, `get_latest_audit.php` — funções finas (`\core_external\
  external_api`): parâmetros, conferências de contexto/capacidade, chamada ao serviço, estrutura de retorno.
- `classes/local/api/service.php` — `API_VERSION`, `require_api()`, `request()` (trava curta por sala,
  reaproveitamento, limite por solicitante, `recorder::queue(..., ORIGIN_API)`), `find_run()` (acesso igualado a
  `runnotfound`), `latest()`.
- `classes/local/api/run_view.php` — projeção de uma execução para a resposta (mapas → listas, relatório como texto
  JSON gravado) e as `external_description` compartilhadas.
- Extrações com testes existentes como rede: `local\history\latest::for_course()` (de `index.php`) e
  `evolution::for_run()` (de `history.php`).
- Ajustes: `run_store::ORIGIN_API` + `count_alive_pending_by_origin()`; `recorder::queue()` com `$origin`;
  `task\audit_course::authorised()` por origem; `db/services.php`; `db/access.php` (`api`); configuração
  `api_max_pending`; strings; privacy provider; rótulo de origem; `API.md`; versão 0.6.0.

## Technical Context

**Language/Version**: PHP 8.1+ (ambiente: PHP 8.3), Moodle 4.5.15

**Primary Dependencies**: núcleo do Moodle (`\core_external\external_api`, `external_function_parameters`,
`external_single_structure`, `external_multiple_structure`, `external_value`, `db/services.php`, `\core\lock`,
`\core\task\manager`); componentes da Spec 005 (`run_store`, `recorder`, `evolution`, `dashboard::stale_reasons`,
`policy_store`, `history\access`, `task\audit_course`)

**Storage**: nenhuma tabela/coluna nova; nova origem `api` em `local_mooc_audit_run.origin` (char 10); configuração
`api_max_pending`; serviço em `external_services` (núcleo)

**Testing**: PHPUnit (`\core_external\tests\externallib_testcase`, `clean_returnvalue`, `runAdhocTasks`,
`tests/helper/schema_validator.php`), PHPCS `moodle`; sem Behat novo (não há interface nova além do rótulo de origem,
coberto por teste de saída)

**Target Platform**: plugin local Moodle 4.5 (PostgreSQL/MySQL)

**Project Type**: plugin Moodle (camada de integração)

**Performance Goals**: `request_audit` ≤ 1 s (nenhuma regra executada); consultas com relatório ≤ 2 s (SC-001/SC-002)

**Constraints**: somente leitura nas salas; consultas sem gravação; sem dependência nova; contrato do relatório 1.0
inalterado; respostas sem dados pessoais; plugin funcional com o serviço desabilitado

**Scale/Scope**: 3 funções, 2 classes de serviço, 2 extrações, ~25 testes; versão 0.6.0 (2026103100)

## Constitution Check

*GATE: avaliado antes da Fase 0 e de novo após a Fase 1.*

| Princípio | Avaliação | Situação |
|---|---|---|
| I Independência | API é canal opcional; serviço criado desabilitado; nada do MOOC Admin é lido; FR-022 + SC-008 testam o plugin sem a API | ✅ |
| II Compatibilidade | Web Services nativos; `db/services.php`; nenhuma alteração de núcleo; DML portável; sem esquema novo | ✅ |
| III Auditoria não modifica | Consultas não gravam (teste de contagem); solicitação grava só execução + tarefa (tabelas do plugin/fila); auditoria é a da 005; `readonly_test` estendido | ✅ |
| IV Correção assistida | Nenhuma função de correção, preparação ou lote (FR-002) | ✅ |
| V Regras rastreáveis | Relatório entregue como gravado (ids, política versionada, impressão digital) | ✅ |
| VI Transparência | Desatualização explícita com motivos; "resultado da última auditoria" com data; parcial/falha distintos; `found:false` ≠ erro | ✅ |
| VII Testabilidade | Matriz de testes por função (FR-031), fila real, esquema validado, privacy | ✅ |
| VIII Segurança | Token nativo; capacidade própria sem arquétipo; `require_login` + `audit` por sala; `runnotfound` igualado; reconferência na tarefa; sem `userid` nem segredos nas respostas; privacy atualizado | ✅ (decisões D1–D2 **[validar]**) |
| IX Simplicidade | Recurso nativo; 2 classes novas de domínio justificadas abaixo; refatorações removem duplicação com testes existentes | ✅ com justificativa |
| X Incremental | Spec/plano/tarefas/análise antes do código; `api_version` com política de versionamento; fora de escopo declarado | ✅ |

**Portões de entrega**: `version.php` 0.6.0 (2026103100), sem passo de upgrade (capacidade, serviço e configuração
são instalados pelo núcleo); `README.md` do plugin (seção API + limitações) e `API.md`; `docs/backlog.md` atualizado;
`specs/004-api-externa/results.md` com execução da suíte e medições; decisão 13 registrada após aprovação.

## Phase 0 — Research

Ver [research.md](research.md): R1 base, R2 mecanismo, R3 capacidade e papel, R4 serviço, R5 contexto e erros, R6
solicitação/serialização/limite, R7 tarefa por origem, R8 forma das respostas, R9 versionamento, R10 lógica
compartilhada, R11 privacidade, R12 tamanho, R13 testes, R14 documentação; tabela de decisões pendentes D1–D5.
Nenhum NEEDS CLARIFICATION restante.

## Phase 1 — Design

- Modelo de dados: [data-model.md](data-model.md) (sem esquema novo).
- Contrato da API 1.0: [contracts/web-services.md](contracts/web-services.md).
- Validação: [quickstart.md](quickstart.md).

**Re-check pós-design**: nenhuma violação. A solicitação escreve só em tabela do plugin e na fila nativa (III
permite); as duas extrações preservam o comportamento de `index.php` e `history.php` (testes existentes devem passar
sem alteração).

## Project Structure

### Documentation (this feature)

```text
specs/004-api-externa/
├── spec.md, plan.md, research.md, data-model.md, quickstart.md
├── contracts/web-services.md
├── checklists/requirements.md
├── tasks.md, analysis.md
└── results.md          # (implementação) evidências e medições
```

### Source Code

```text
plugin/local_mooc_audit/
├── API.md                                   # guia do consumidor (novo)
├── classes/
│   ├── external/request_audit.php           # novo
│   ├── external/get_run.php                 # novo
│   ├── external/get_latest_audit.php        # novo
│   ├── local/api/service.php                # novo: acesso, solicitação, consultas, API_VERSION
│   ├── local/api/run_view.php               # novo: projeção + descrições de retorno
│   ├── local/history/latest.php             # novo: extraído de index.php
│   ├── local/history/evolution.php          # + for_run() (extraído de history.php)
│   ├── local/history/run_store.php          # + ORIGIN_API, count_alive_pending_by_origin()
│   ├── local/history/recorder.php           # queue(..., $origin)
│   ├── task/audit_course.php                # authorised() por origem
│   └── privacy/provider.php                 # (só strings/teste; código inalterado salvo revisão)
├── index.php, history.php                   # passam a usar latest / evolution::for_run
├── db/services.php                          # novo
├── db/access.php                            # + local/mooc_audit:api
├── settings.php                             # + api_max_pending
├── lang/{en,pt_br}/local_mooc_audit.php     # capacidade, erros, origem, configuração, privacy
├── version.php                              # 0.6.0
└── tests/
    ├── external/request_audit_test.php      # novo
    ├── external/get_run_test.php            # novo
    ├── external/get_latest_audit_test.php   # novo
    ├── local/api/service_test.php           # novo: trava, limite, contagem viva
    ├── local/history/latest_test.php        # novo: paridade com index.php
    ├── task/audit_course_test.php           # + origem api, revogação
    ├── readonly_test.php                    # + solicitação + tarefa
    └── privacy/provider_test.php            # + execução api
```

**Structure Decision**: mesma organização das Specs 001–005: funções externas em `classes/external/` (convenção do
Moodle), regra de acesso/fluxo da API em `classes/local/api/`, histórico em `classes/local/history/`, testes
espelhando o namespace.

## Complexity Tracking

| Item | Por que é necessário | Alternativa mais simples rejeitada porque |
|---|---|---|
| `local\api\service` separado das funções externas | Três funções compartilham a conferência de `api`, a igualação `runnotfound` e a montagem da resposta; testável sem a camada de Web Services | Repetir em cada função triplicaria a regra de acesso mais sensível |
| `local\api\run_view` (projeção + descrição) | `get_run` e `get_latest_audit` devolvem a mesma estrutura; a descrição precisa casar com a projeção | Duas descrições divergiriam silenciosamente |
| Extrações `latest::for_course` e `evolution::for_run` | FR-015/FR-017 exigem a mesma regra da página; uma implementação é a prova | Copiar a lógica cria duas verdades sobre desatualização e base da evolução |
| Trava `request_course_<id>` | Serializa "reaproveitar ou criar" por sala sem índice único parcial (não portável no XMLDB) | Só transação não impede duas leituras simultâneas sem pendente |
