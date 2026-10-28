# Relatório executivo — adoção do Spec-Driven Development no Auditor MOOC

**Data:** 2026-10-28 · **Branch:** `claude/install-spec-kit-01eeb1` (worktree sobre `main` em `dc12796`) ·
**Fase:** preparação (nenhuma funcionalidade nova implementada)

## 1. O que foi feito

| Entregável | Onde | Situação |
|---|---|---|
| 1. Spec Kit inicializado no repositório existente | `.specify/` (templates, scripts, workflow, constituição), `.claude/skills/speckit-*` (10 skills) | concluído; commit `dd5e005`; versão `specify-cli 1.2.0`, integração `claude`, scripts `sh`, numeração sequencial; nenhum arquivo pré-existente alterado pela inicialização (verificado por `git status` e diff) |
| 2. Constituição do Auditor MOOC | `.specify/memory/constitution.md` v1.0.0 | 10 princípios pedidos + restrições técnicas + fluxo/portões + governança; cada princípio ligado a um risco concreto do projeto |
| 3. Baseline técnico e funcional da PoC | `docs/baseline-poc.md` | inventário por arquivo, capacidades com estado objetivo, 10 divergências documentação × código, limitações |
| 4. Catálogo consolidado de regras | `docs/catalogo-regras.md` | 29 CL + 36 X-* + 15 ENV com origem, tipo, política, status real, evidência, testes, FP, complexidade, prioridade |
| 5. Visão de produto | `docs/visao-produto.md` | problema, objetivo, usuários, 8 capacidades, experiência mínima, medidas, decisões pendentes |
| 6. Especificação 001 | `specs/001-auditoria-tecnica-operacional/spec.md` (+ `checklists/requirements.md`) | 5 user stories, 28 requisitos, 9 critérios de sucesso, edge cases, premissas, clarificações registradas |
| 7. Plano técnico 001 | `plan.md`, `research.md` (12 decisões), `data-model.md`, `contracts/` (schema JSON 1.0, CLI, página), `quickstart.md` | Constitution Check aprovado; 2 abstrações justificadas |
| 8. Tarefas 001 | `tasks.md` | 105 tarefas em 9 fases, dependências, paralelismo, portões, estimativa (≈ 14–19 dias·pessoa) |
| 9. Backlog priorizado | `docs/backlog.md` | 002 correção assistida, 003 comparação, 004 API, 005 lote/histórico (ordem recomendada 001 → 002 → 003 → 005 → 004) |
| 10. Este relatório + análise de consistência | `docs/relatorio-adocao-sdd.md`, `specs/001-…/analysis.md` | 0 críticos, 1 alto, 4 médios, 6 baixos; todos os achados acima de LOW já aplicados nos artefatos |

**Testes existentes executados** (2026-10-28, contêiner `moocaudit-webserver-1`, Moodle 4.5.15 / PHP 8.3.35 / PostgreSQL 17.11):

```
vendor/bin/phpunit local/mooc_audit/tests/engine_test.php
OK (3 tests, 12 assertions)
```

`php -l` sem erros nos 20 arquivos PHP do plugin. Ambiente Moodle local íntegro (7 cursos de laboratório presentes);
contêineres de outros projetos (`coursecare-*`) não foram tocados.

## 2. Respostas às perguntas orientadoras

**O que temos funcionando hoje?** Uma auditoria somente leitura de 29 regras por `courseid`, em < 0,3 s, com 6
estados, política parametrizável, JSON e texto, página mínima protegida por capacidade (fora da navegação do curso),
visão de usuário de teste por CLI e um comparador experimental. 4 regras com teste automatizado; 25 validadas só
manualmente. Nada em homologação.

**O que falta para o primeiro MVP institucional?** O conteúdo da spec 001: entrada na administração do curso; página
no contexto do curso com agrupamento por gravidade e categoria, evidência completa, links e reexecução; blocos
explícitos para não verificável/erro; política inválida visível; contrato JSON versionado; strings nos arquivos de
idioma; e, sobretudo, um teste por regra, teste de não modificação e teste de permissão. Sem persistência, lote, API
ou correção.

**Quais regras merecem maior atenção?** As bloqueantes (CL-23, X-CERT-01, X-DEP-02, X-DEP-04, CL-18), as que alteram o
significado da nota mínima (CL-12 agregação/excluir vazias, CL-16, CL-11), as que dependem de nome canônico
(decisão 10 pendente: CL-09/11/16/22/23), CL-21 (blocos herdados nunca validados com bloco de site) e X-INT-03
(nunca validada com plugin ausente). Detalhe em `docs/catalogo-regras.md` §I.

**Que partes do código precisam ser reorganizadas?** `rules.php` (1 080 linhas, uma classe, 29 métodos, metadados em
array de strings) → uma classe por regra com interface e catálogo explícito, protegida por testes de caracterização
dos cursos A/B. `index.php` → contexto do curso + renderer/Mustache. `policy.php` → avisos expostos. **Preservar**
`snapshot.php` (lista branca DML justificada), `engine::audit()` e o contrato JSON (aditivo), o CLI, e não tocar
`comparator.php` (spec 003). Nenhuma reescrita.

**Qual é a experiência mínima a entregar?** Servidor abre a sala → "Auditoria técnica" → situação geral,
inconformidades por gravidade/categoria com local (link), evidência, esperado e orientação; alertas; conformes; não
verificáveis com motivo → corrige no Moodle → "Executar novamente".

**Quais são os riscos técnicos e operacionais?**

| Risco | Prob. | Impacto | Mitigação prevista |
|---|---|---|---|
| Refatoração altera comportamento de regras | média | alto | caracterização (T003–T005, T045) antes de mover código; SC-007 |
| Falsos positivos por nome canônico em cursos antigos | alta | médio | política ajustável (US5); decisão 10 pendente registrada |
| Versões de plugins de terceiros no laboratório ≠ produção | alta | médio | limitação declarada; testes pulam quando módulo ausente; validar em homologação |
| Bloco "Acessibilidade" de site não reproduzido no laboratório | média | médio | `block_manager` lê herdados; validar em homologação com bloco real |
| Edge case de conclusão global desabilitada (achado C1) | — | médio | resolvido: tarefa T047b com teste |
| Esforço subestimado na fase 8 (28 arquivos de teste) | média | médio | paralelizável; MVP visível já após a fase 3 |
| Expectativa de "bloqueio" de publicação | baixa | alto | spec deixa claro: classificação informativa; bloqueio não existe nem está decidido |

**Qual será a sequência de implementação?** Fases 1–2 (caracterização e reorganização, invisíveis ao usuário) → fase 3
(US1, primeiro valor visível; validar com a equipe) → fases 4–5 (links/reexecução; transparência) → fases 6–7
(contrato; política) → fase 8 em paralelo (testes por regra) → fase 9 (acabamento, saídas de referência, README,
catálogo). Depois: 002 correção assistida → 003 comparação → 005 lote/histórico → 004 API.

**Quais decisões ainda exigem validação institucional?**

1. Nomes canônicos dos recursos institucionais (decisão 10) — afeta 5 regras.
2. Quem recebe a permissão de auditar além dos gerentes (proponentes já no MVP?).
3. Persistência: confirmada **fora** do MVP (histórico só em 005) — validar.
4. Lista do que o MOOC Admin altera na reoferta (suprimir alarmes redundantes).
5. Insumos para CL-24/CL-25 (imagens e texto oficiais do certificado) e versões institucionais de
   `mod_simplecertificate`/`availability_days`.
6. Autorização para iniciar a implementação da spec 001 conforme `tasks.md`.

## 3. Decisões tomadas nesta fase (e por quê)

- **Raiz do projeto SDD = raiz do repositório `checklist-mooc`**, não a árvore do Moodle: o plugin tem repositório
  próprio e as especificações acompanham o código.
- **Nenhuma regra nova na 001**; apenas caminhos de teste faltantes. O catálogo ampliado continua a referência, em
  incrementos próprios.
- **Sem persistência na 001**: auditoria < 1 s; histórico tem valor para gestores (005), não para o fluxo
  corrigir → reexecutar.
- **Uma classe por regra, catálogo explícito** (não autodescoberta): metadados obrigatórios, teste por regra, ordem
  determinística.
- **Contrato JSON 1.0 aditivo**: tudo da PoC continua; `contract_version`, `category`, `locus`, `link`,
  `overall_status`, `policy_warnings` entram.
- **Ordem do backlog**: 005 antes de 004, porque a API só agrega valor com consumidor pronto.
- Divergência histórica registrada: o relatório de API (2026-10-28, manhã) desaconselhava o plugin; a PoC (mesmo dia)
  o recomendou com evidência executada. O `README.md` da raiz foi atualizado para refletir a decisão vigente.

## 4. Inconsistências encontradas entre PoC e relatórios anteriores

Listadas em `docs/baseline-poc.md` §5 (D1–D10). As mais relevantes: README da raiz recomendava Laravel/MOOC Admin
(corrigido nesta entrega); contagem de linhas (≈2 300 declaradas × 2 971 reais); CL-15 citada como validada mas
inexistente no plugin; ids X-AV-04 e X-STR-01 com conteúdo diferente entre protótipo Python e plugin; defeito
`problematic || true` no comparador; `policy_error` nunca exibido; strings fixas em português na página e no texto.

## 5. Recomendações

1. Autorizar a implementação da 001 pelas fases, com validação da equipe após a fase 3.
2. Em paralelo, obter da instituição: decisão 10, lista de alterações do MOOC Admin, insumos do certificado,
   versões de plugins de produção.
3. Manter o Spec Kit como disciplina mínima: nenhuma funcionalidade sem `spec → plan → tasks → analyze`; nenhuma
   regra sem teste C/NC; nenhuma escrita em curso fora da spec 002.
4. Não ampliar a arquitetura (sem tabelas, tarefas, serviços) até que a 005/004 as justifiquem.

## 6. Estado do repositório ao fim desta fase

Arquivos novos: `docs/` (5 documentos), `specs/001-auditoria-tecnica-operacional/` (spec, plan, research,
data-model, quickstart, contracts/3, checklists/1, tasks, analysis), `.specify/memory/constitution.md` (preenchida),
`README.md` (seção de estado e SDD atualizadas). Código do plugin, testes, fixtures, scripts do ambiente, relatórios
anteriores e resultados experimentais **inalterados**. Histórico Git preservado; tudo em um branch de trabalho.
