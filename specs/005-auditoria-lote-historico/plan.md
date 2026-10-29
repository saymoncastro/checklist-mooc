# Implementation Plan: Auditoria em Lote, Histórico e Acompanhamento

**Branch**: `claude/spec-005-auditoria-lote-948e09` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/005-auditoria-lote-historico/spec.md`

## Summary

Persistir cada auditoria, identificar cada ocorrência de forma estável e classificar sua evolução, auditar salas em
lote pela fila nativa do Moodle e oferecer um painel de acompanhamento — usando o motor, as regras, o renderer e a
trilha de correções existentes:

- **Chave de ocorrência** (research R2): cada regra declara `check`/`subject` nos achados; `engine::run_rules()`
  compõe `occurrence` e a remove dos campos auxiliares. Corrige declarações incompletas de `POLICY_KEYS` (R4).
- `local\history\fingerprint` — impressão digital canônica da política e, por regra, da fatia de política e do código.
- `local\history\policy_store`, `local\history\run_store` — persistência (DML) da política aplicada e das execuções,
  com estados finais imutáveis.
- `local\history\recorder` — executa uma auditoria registrada (página, CLI, tarefa): carrega a política, roda o
  motor, monta `details`, calcula a evolução contra a execução anterior válida e finaliza a execução.
- `local\history\evolution` — compara duas execuções (tabela de classes do data-model).
- `local\history\batch_manager` — resolve a seleção, confere permissões, evita duplicação, cria lote, execuções e
  tarefas; reexecuta falhas.
- `local\history\dashboard` — consulta das últimas execuções por sala do escopo e situação derivada.
- `task\audit_course` (ad hoc) e `task\cleanup_history` (agendada).
- Páginas `history.php`, `dashboard.php`, `batch.php`; `index.php` passa a registrar; saídas
  `output\history_page`, `output\dashboard_page`, `output\batch_page`, `output\evolution` + templates.
- Capacidades `viewdashboard`/`batchaudit`, configurações, privacy provider, CLI `--record` e `cli/batch.php`.

## Technical Context

**Language/Version**: PHP 8.1+ (ambiente: PHP 8.3), Moodle 4.5.15
**Primary Dependencies**: núcleo do Moodle (DML/XMLDB, `\core\task\adhoc_task`/`scheduled_task`, `\core\lock`,
`admin_externalpage`, renderer/Mustache, `html_table`, `paging_bar`); componentes existentes (`engine`, `policy`,
`catalog`, `snapshot`, `output\report`, `local\correction\trail`, `local\comparison\matcher::key_name`)
**Storage**: 3 tabelas novas (`local_mooc_audit_policy`, `_run`, `_batch`); upgrade 2026103000
**Testing**: PHPUnit (`advanced_testcase`, gerador A/B, `cron`/`adhoc` via `\core\task\manager`), Behat
(`@javascript @accessibility`), PHPCS `moodle`
**Target Platform**: plugin local Moodle 4.5 (PostgreSQL/MySQL)
**Project Type**: plugin Moodle
**Performance Goals**: sala típica ≤ 5 s, 151 módulos ≤ 15 s por execução; pico < 256 MB por tarefa; painel com 100
salas ≤ 3 s (SC-005, SC-006)
**Constraints**: somente leitura nas salas; sem serviço externo; contrato 1.0 aditivo; sem dados de estudantes;
retenção finita por omissão; nenhuma dependência nova
**Scale/Scope**: dezenas a poucas centenas de salas por lote; ~10 classes novas, 3 páginas, 4 templates, 2 tarefas;
versão 0.5.0

## Constitution Check

| Princípio | Avaliação | Situação |
|---|---|---|
| I Independência | Só Moodle + ids de curso; nenhuma integração; lote pela fila do próprio Moodle | ✅ |
| II Compatibilidade | Plugin padrão; tabelas via XMLDB; consultas DML portáveis (sem SQL específico de banco); API de tarefas e travas do núcleo | ✅ |
| III Auditoria não modifica | A única escrita nova é em tabelas do próprio plugin (permitida pelo III quando a spec justifica); `readonly_test` estendido para execução registrada e tarefa de lote | ✅ |
| IV Correção assistida | Nenhuma correção em lote nem automática; a trilha da Spec 002 é só lida; reauditoria do verificador não é registrada | ✅ |
| V Regras rastreáveis | Ids e significados preservados; `check` estável por condição; `POLICY_KEYS` completadas e protegidas por teste; política versionada e com impressão digital em cada execução | ✅ |
| VI Transparência | Evolução separa resolvido de não verificável, removido e não comparável; execução parcial ≠ concluída; painel sem selo/percentual; aviso de escopo | ✅ |
| VII Testabilidade | Testes de unicidade de chave, dependência de política, evolução por classe, recorder, lote (fila real), tarefa repetida/interrompida, permissões, privacidade, retenção; caracterização A/B intacta | ✅ |
| VIII Segurança | `require_login`/`require_capability` por página; capacidades novas no sistema (lotes); reconferência na tarefa; `sesskey` nas ações; ids de execução conferidos contra o curso; único dado pessoal = solicitante, com privacy provider | ✅ |
| IX Simplicidade | Sem fila externa; 3 tabelas mínimas; andamento do lote derivado; ocorrências dentro do relatório (sem tabela) — classes novas listadas em Complexity Tracking | ✅ com justificativa |
| X Incremental | Spec, plano, tarefas e análise antes do código; contrato aditivo; fora de escopo declarado | ✅ |

**Portões de entrega**: `version.php` 0.5.0 (2026103000) com passo de upgrade; README do plugin (histórico, lote,
painel, retenção, operação); catálogo/backlog atualizados; `specs/005-…/results.md` com evidências e desempenho.

## Phase 0 — Research

Ver [research.md](research.md): R1 estado do repositório, R2 chave de ocorrência, R3 ausência ≠ correção, R4 política
e regra, R5 persistência mínima, R6 segundo plano, R7 segurança assíncrona, R8 capacidades, R9 registro da página,
R10 painel, R11 retenção/privacidade, R12 trilha de correções, R13 medição.

## Phase 1 — Design

- Modelo de dados: [data-model.md](data-model.md).
- Páginas, tarefas e CLI: [contracts/pages-and-cli.md](contracts/pages-and-cli.md).
- Validação: [quickstart.md](quickstart.md).

**Re-check pós-design**: nenhuma violação. O registro da execução pela página é escrita em tabela própria (III
permite); a decisão de registrar toda abertura fica **[validar]**.

## Project Structure

### Documentation (this feature)

```text
specs/005-auditoria-lote-historico/
├── spec.md, plan.md, research.md, data-model.md, quickstart.md
├── contracts/pages-and-cli.md
├── checklists/requirements.md
├── tasks.md, analysis.md
└── results.md          # evidências, desempenho, revisão independente, respostas finais
```

### Source Code

```text
plugin/local_mooc_audit/
├── classes/
│   ├── engine.php                       # occurrence em run_rules()
│   ├── local/rule/*.php                 # check/subject nos achados; POLICY_KEYS completadas
│   ├── local/history/fingerprint.php    # impressões digitais (política, regra)
│   ├── local/history/policy_store.php   # política aplicada
│   ├── local/history/run_store.php      # execuções (estados, imutabilidade, consultas)
│   ├── local/history/recorder.php       # auditoria registrada
│   ├── local/history/evolution.php      # comparação entre execuções
│   ├── local/history/batch_manager.php  # lotes
│   ├── local/history/dashboard.php      # dados do painel
│   ├── task/audit_course.php            # ad hoc
│   ├── task/cleanup_history.php         # agendada
│   ├── output/history_page.php, evolution.php, dashboard_page.php, batch_page.php
│   └── privacy/provider.php             # + execuções e lotes
├── index.php                            # registra; quadro de evolução; link do histórico
├── history.php, dashboard.php, batch.php
├── cli/audit.php (--record), cli/batch.php
├── db/install.xml, upgrade.php, access.php, tasks.php
├── settings.php                         # páginas e configurações novas
├── templates/history.mustache, evolution.mustache, dashboard.mustache, batch.mustache
├── lang/{en,pt_br}/local_mooc_audit.php
└── tests/
    ├── occurrence_test.php              # unicidade, estabilidade (ordem, idioma)
    ├── policy_dependency_test.php       # POLICY_KEYS completas
    ├── local/history/evolution_test.php, recorder_test.php, run_store_test.php, batch_manager_test.php,
    │   dashboard_test.php, fingerprint_test.php
    ├── task/audit_course_test.php, cleanup_history_test.php
    ├── history_access_test.php, history_readonly (em readonly_test), privacy/provider_test.php
    └── behat/history.feature, dashboard.feature
```

**Structure Decision**: mesma organização das Specs 001–003: domínio em `classes/local/history/`, apresentação em
`output/`, tarefas em `task/`, testes espelhando o namespace.

## Complexity Tracking

| Item | Por que é necessário | Alternativa mais simples rejeitada porque |
|---|---|---|
| 7 classes em `local\history` | Persistência, impressão digital, evolução, lote e painel têm regras e testes próprios; `recorder` é o único caminho de auditoria registrada (página, CLI, tarefa) | Concentrar em uma classe geraria ~1.500 linhas sem fronteira testável; duplicar o fluxo em cada página quebraria a imutabilidade |
| Tabela de política por impressão digital | Reproduzir/explicar resultados sem repetir ~2 KB por execução | Guardar a política em cada execução repete o mesmo texto centenas de vezes |
| Trava por sala + `attempts` | Entrega duplicada e processo morto são comportamentos reais da fila do Moodle | Confiar só no estado da linha permite duas tarefas auditarem ao mesmo tempo |
| `check`/`subject` em todas as regras | Identidade estável exigida pela spec; o texto da evidência é frágil (Spec 003) | Derivar do texto repetiria o defeito conhecido |
