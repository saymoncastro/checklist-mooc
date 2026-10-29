# Implementation Plan: Comparação entre Ofertas MOOC

**Branch**: `claude/spec-003-comparacao-ofertas-b89195` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-comparacao-ofertas/spec.md`

## Summary

Evoluir o comparador experimental para um componente testado, sobre os mesmos snapshots e o mesmo motor:

- `local\comparison\matcher` — correspondência em passes determinísticos com unicidade nos dois lados, impressão de
  conteúdo, congelamento de ambiguidades e pares manuais (research R2, R10).
- `local\comparison\differ` — diferenças por par, de sala, seções, inscrição, blocos e notas, cada uma com tipo,
  código e explicação (R1, R3–R5); corrige `problematic`.
- `local\comparison\audit_relation` — relaciona os resultados do motor nas duas salas pela chave de local, com a
  política do destino e a da origem no próprio ano, e as regras do ciclo de oferta (R6, R7).
- `comparator` — orquestra (`run()` a partir de ids; `compare()` a partir de snapshots), monta o contrato
  `comparison` 1.0 e a saída de texto.
- Página `compare.php` + formulário + `output\comparison_report` + `templates/comparison*.mustache`; entrada na
  administração do site e link no relatório de auditoria; CLI atualizada; `cli/make_scenarios.php` (laboratório).

## Technical Context

**Language/Version**: PHP 8.1+ (ambiente: PHP 8.3), Moodle 4.5.15
**Primary Dependencies**: núcleo do Moodle (formslib com elemento `course`, `admin_externalpage`, renderer/Mustache,
`get_user_capability_course`); componentes existentes do plugin (`snapshot`, `engine`, `policy`, `locator`, `catalog`)
**Storage**: nenhum (sob demanda; sem tabela nova; sem upgrade de banco)
**Testing**: PHPUnit (`advanced_testcase`; testes unitários sobre snapshots sintéticos e de integração com o gerador +
importação por backup/restore), Behat (`@javascript @accessibility`), PHPCS `moodle`
**Target Platform**: plugin local Moodle 4.5 (PostgreSQL/MySQL)
**Project Type**: plugin Moodle
**Performance Goals**: duas salas de 151 módulos < 10 s (SC-005); A/B < 2 s
**Constraints**: somente leitura em todos os caminhos; sem dados pessoais; contrato individual 1.0 intacto; nenhuma
dependência nova; correção só pela Spec 002
**Scale/Scope**: 3 classes novas de comparação + 1 formulário + 1 classe de saída + 1 página + 2 templates; 1 schema;
~10 arquivos de teste; nenhuma capacidade ou tabela nova; versão 0.4.0

## Constitution Check

| Princípio | Avaliação | Situação |
|---|---|---|
| I Independência | Apenas os dois ids de curso; nada de MOOC Admin, `.mbz` ou serviço externo | ✅ |
| II Compatibilidade | Plugin padrão; sem SQL novo (usa o snapshot existente); elementos de formulário e admin do núcleo | ✅ |
| III Auditoria não modifica | Comparação = 2 snapshots + 2 execuções de regras + cálculo em memória; teste de não modificação das duas salas (página, CLI, `run()`) | ✅ |
| IV Correção assistida | Comparação não oferece correção; nenhuma ligação com `local\correction` | ✅ |
| V Regras rastreáveis | Nenhuma regra nova; itens de pendência citam o id da regra e usam a política (destino vigente; origem no ano dela) | ✅ |
| VI Transparência | Correspondência com estratégia e confiança; ambiguidade explícita; heurística (passes 4–5) marcada como confiança média; NV/ERRO separados de problema; política de cada lado declarada | ✅ |
| VII Testabilidade | Testes por estratégia de correspondência, por código de diferença, por classe de pendência, de acesso, de contrato e de não modificação; Behat do fluxo | ✅ |
| VIII Segurança | `require_login` + `require_capability(compare)` em cada curso; `captype read`; nenhum dado pessoal; senhas só como booleano; escape do Mustache | ✅ |
| IX Simplicidade | 3 classes de domínio (matcher, differ, audit_relation) justificadas em Complexity Tracking; sem interfaces; sem persistência | ✅ com justificativa |
| X Incremental | Spec, plano, tarefas e análise antes do código; contrato novo versionado; fora de escopo declarado | ✅ |

**Portões de entrega**: `version.php` 0.4.0 (2026102906, sem passo de upgrade de banco); README do plugin (comparação,
fontes, limitações); `docs/backlog.md` e catálogo (comparação entregue); `plugin-results/0.4.0/` com evidências.

## Phase 0 — Research

Ver [research.md](research.md): R0 avaliação do comparador existente, R1 `problematic`, R2 correspondência, R3
classificação, R4 restrições, R5 arquivos, R6 política por sala e ciclo de oferta, R7 chave de local, R8 permissões,
R9 navegação, R10 pares manuais, R11 contrato, R12 desempenho, R13 somente leitura, R14 cenários.

## Phase 1 — Design

- Modelo de dados: [data-model.md](data-model.md).
- Contrato JSON: [contracts/comparison-v1.0.schema.json](contracts/comparison-v1.0.schema.json) (cópia de
  `plugin/local_mooc_audit/schema/`).
- Página e CLI: [contracts/compare-page.md](contracts/compare-page.md).
- Validação: [quickstart.md](quickstart.md).

**Re-check pós-design**: nenhuma violação nova; a única escrita no laboratório é feita por `cli/make_scenarios.php`,
fora do caminho de comparação e com as guardas do princípio VIII.

## Project Structure

### Documentation (this feature)

```text
specs/003-comparacao-ofertas/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── comparison-v1.0.schema.json
│   └── compare-page.md
├── checklists/requirements.md
├── tasks.md
├── analysis.md
└── results.md          # evidências, desempenho, revisão de segurança, respostas finais
```

### Source Code

```text
plugin/local_mooc_audit/
├── classes/
│   ├── comparator.php                     # orquestração, contrato comparison 1.0, texto (evoluído)
│   ├── local/comparison/matcher.php       # correspondência (novo)
│   ├── local/comparison/differ.php        # diferenças classificadas (novo)
│   ├── local/comparison/audit_relation.php# relação entre auditorias (novo)
│   ├── form/compare_form.php              # seleção (novo)
│   └── output/comparison_report.php       # dados do template (novo); renderer.php (+1 método)
├── compare.php                            # página (novo)
├── cli/compare.php                        # CLI (atualizada); cli/make_scenarios.php (novo, laboratório)
├── templates/comparison.mustache, comparison_diffs.mustache
├── schema/comparison-v1.0.schema.json
├── settings.php                           # admin_externalpage
├── index.php / output/report.php / templates/report.mustache  # link "Comparar com a oferta anterior"
├── lang/{en,pt_br}/local_mooc_audit.php
└── tests/
    ├── helper/schema_validator.php        # validador extraído de contract_test
    ├── local/comparison/matcher_test.php, differ_test.php, audit_relation_test.php
    ├── comparator_test.php                # integração com importação real (cenários A–G)
    ├── comparison_contract_test.php, compare_access_test.php, compare_readonly_test.php, comparison_output_test.php
    └── behat/compare.feature
```

**Structure Decision**: mesma organização das Specs 001/002 (`classes/local/<área>/` para domínio, `output/` para
apresentação, testes espelhando o namespace).

## Complexity Tracking

| Item | Por que é necessário | Alternativa mais simples rejeitada porque |
|---|---|---|
| 3 classes em `local\comparison` | Correspondência, classificação e relação com auditoria têm testes e regras próprias; o arquivo único da PoC já tinha 360 linhas sem nada disso | Manter tudo em `comparator.php` geraria ~1.200 linhas sem fronteira testável |
| Correspondência manual por URL | Exigência da spec (avaliar confirmação manual) sem persistência | Persistir confirmações exigiria tabela e privacidade (Spec 005) |
| `cli/make_scenarios.php` | Evidências reproduzíveis dos cenários A–G no laboratório | Montagem manual pela interface não é reproduzível |
