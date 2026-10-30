# Implementation Plan: Experiência do relatório individual e da correção assistida

**Branch**: `claude/spec-006-implementacao` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/006-experiencia-relatorio/spec.md` (revisão de 2026-10-30, com a auditoria
`docs/auditoria-ux-correcao/README.md`).

## Summary

Transformar cada resultado do motor numa ocorrência que o professor consegue resolver: o problema em linguagem do
Moodle, por que importa, como corrigir (com alternativas quando a decisão é pedagógica), quem pode, como confirmar e
um link que abre a seção certa do formulário. Tudo é montado **ao exibir**, a partir dos fatos já gravados (regra,
verificação, entidade, objeto relacionado), do `modinfo` atual e das permissões de quem vê. **Nenhuma regra muda**:
estados, gravidades, ids, chaves de ocorrência e impressão digital do código das regras ficam iguais (FR‑025, SC‑015).

Este plano cobre as fases 1–3 da spec. **A fase 1 (textos e links) é a primeira entrega e está detalhada**; as fases 2
(estrutura do relatório) e 3 (contrato 1.1) estão planejadas em linhas gerais e serão detalhadas em `tasks.md` depois
da fase 1. A fase 4 (correções de regra) fica fora deste plano. A linha de base com usuários (fase 0) corre em paralelo
no site de sessão congelado em 0.6.1 (FR‑029).

## Technical Context

**Language/Version**: PHP 8.1–8.3 (Moodle 4.5) e 8.2–8.4 (Moodle 5.3); Mustache; sem JavaScript próprio.

**Primary Dependencies**: APIs do núcleo: `get_fast_modinfo`, `moodle_url`, `has_capability`, `core_plugin_manager`,
`core_course_category::make_categories_list`, strings do núcleo (`quiz`, `completion`, `availability`, `grades`,
`enrol_self`, `moodle`). Nada novo de terceiros.

**Storage**: nenhum. O JSON gravado em `local_mooc_audit_run` é a fonte de fatos; nada novo é persistido (FR‑028).

**Testing**: PHPUnit (testes novos por regra/verificação e de apresentação; caracterização A/B intacta), Behat da
jornada X‑DEP‑02, axe no relatório; PHPCS `moodle`.

**Target Platform**: Moodle 4.5 LTS e 5.3 LTS (`supported` [405, 503]).

**Project Type**: plugin local do Moodle (`local_mooc_audit`).

**Performance Goals**: o relatório do Curso B continua abaixo de 1 s; uma chamada a `get_fast_modinfo` por página.

**Constraints**: somente leitura; sem mudança de regra; contrato JSON 1.0 intacto na fase 1; links só para telas que
existem e que quem vê pode usar; textos em `pt_br` e `en`.

**Scale/Scope**: 29 regras, 71 verificações (`check`), cerca de 75 variantes de mensagem.

## Constitution Check

| Princípio | Avaliação | Situação |
|---|---|---|
| I Independência | Só APIs do Moodle; nenhum serviço externo nem IA | ✅ |
| II Compatibilidade | Rotas e strings verificadas no 4.5 e no 5.3 (`showonly` em `modedit.php`/`editsection.php`, `enrol/editinstance.php`, `mod/quiz/edit.php`); rótulos de campo pelas strings do núcleo | ✅ |
| III Auditoria não modifica | Apresentação pura; `readonly_test` continua valendo | ✅ |
| IV Correção assistida | Links levam ao formulário do Moodle; nenhuma alteração automática; a correção assistida existente não muda | ✅ |
| V Regras rastreáveis | Código da regra, verificação e evidência original continuam nos detalhes técnicos; mensagens ligadas a `regra + check` | ✅ |
| VI Transparência | Não verificável ≠ falha preservado; "verificação manual" e conferências de texto ditas como tal (FR‑040, fase 2); limitação dita quando o dado não basta | ✅ |
| VII Testabilidade | Teste de cobertura de mensagens por verificação, de links e de permissões; caracterização intacta | ✅ |
| VIII Segurança | Links gerados com `moodle_url` e ids inteiros; nada de `sesskey` em link; capacidade de quem vê conferida antes de exibir a ação | ✅ |
| IX Simplicidade | Duas classes novas (`presentation\finding`, `presentation\catalog`); sem tabela, sem JS | ✅ |
| X Incremental | Spec revisada, plano, tarefas; fase 1 entregue e validada antes da 2 | ✅ |

**Re-check pós-design**: nenhuma violação.

## Phase 0 — Research

Ver [research.md](research.md): R1 fatos disponíveis no JSON gravado, R2 renderizar ao exibir, R3 variantes de
mensagem, R4 rotas nativas, R5 permissões e responsável, R6 plugins exigidos ausentes, R7 precedências demonstráveis,
R8 contradições entre regras, R9 rótulos do núcleo, R10 execuções antigas, R11 o que fica para as fases 2–3.

## Phase 1 — Design

- Modelo: [data-model.md](data-model.md).
- Contrato da apresentação (mensagens, ações, precedências): [contracts/presentation.md](contracts/presentation.md).
- Validação: [quickstart.md](quickstart.md).

## Project Structure

### Documentation (this feature)

```text
specs/006-experiencia-relatorio/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/presentation.md
├── tasks.md              (speckit-tasks)
└── validacao/            (linha de base com usuários; fase 0)
```

### Source Code

```text
plugin/local_mooc_audit/
├── classes/local/presentation/
│   ├── catalog.php        # regra + check + estado → variante; responsável; precedências; plugins exigidos
│   └── finding.php        # monta a ocorrência apresentável ao exibir (textos, nomes, ações, avisos)
├── classes/output/report.php          # passa a usar presentation\finding para cada item
├── templates/report_group.mustache    # coluna do problema e da orientação com as novas partes; detalhes técnicos
├── lang/{en,pt_br}/local_mooc_audit.php  # strings msg_* (problema, por que, como corrigir, como confirmar)
└── tests/
    ├── presentation_catalog_test.php  # toda verificação produzida nos cenários tem mensagem nas duas línguas
    ├── presentation_finding_test.php  # nomes, links, permissões, removidas, precedências, plugins, contradições
    └── behat/report_fix.feature       # jornada X‑DEP‑02 (link abre só a seção; confirmação)
```

**Structure Decision**: a apresentação ganha um namespace próprio (`local\presentation`) para não misturar com as
regras (`local\rule`) nem com o renderer. O `locator` atual continua produzindo o `link` gravado (contrato 1.0); a
apresentação calcula as ações novas sem alterá‑lo.

## Fases

| Fase | Entrega | Requisitos | Muda o que se vê? |
|---|---|---|---|
| **1 — Textos e links** | Mensagens por verificação; nomes no lugar de ids; ações com rotas nativas e permissão; quem pode; como confirmar; avisos de precedência; aviso de plugin exigido ausente; contradição CL‑17 resolvida na apresentação | FR‑031–FR‑037, FR‑039, FR‑042; parte de FR‑006–FR‑009, FR‑024 | Sim (conteúdo das linhas) |
| 2 — Estrutura | Resumo operacional, "Comece por aqui", visões por atividade/prioridade, causas agrupadas, verificações manuais, detalhes recolhidos, celular, títulos | FR‑001–FR‑023, FR‑038, FR‑040, FR‑041 | Sim (organização) |
| 3 — Contrato 1.1 | `check`, `subject`, `subjects`, `params`, `evidence_level` opcionais no JSON; valores encontrado × exigido estruturados; API | FR‑026, FR‑043 | Pouco |

## Complexity Tracking

| Item | Por que é necessário | Alternativa mais simples rejeitada |
|---|---|---|
| Ler `check`/`subject` da chave de ocorrência | É a única fonte estruturada desses fatos no contrato 1.0; sem ela não há variante nem objeto relacionado | Mudar as regras para gravar campos novos (fase 3; mudaria a impressão digital do código das regras e marcaria todas as execuções como desatualizadas) |
| Variantes por estado/gravidade dentro de um `check` | X‑DEP‑02, CL‑14, CL‑17 e CL‑18 usam o mesmo `check` para situações diferentes | Uma mensagem por `check` (ficaria genérica, o problema que esta spec corrige) |
