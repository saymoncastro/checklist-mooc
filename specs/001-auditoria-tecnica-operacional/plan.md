# Implementation Plan: Auditoria Técnica Operacional de Cursos MOOC

**Branch**: `001-auditoria-tecnica-operacional` | **Date**: 2026-10-28 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-auditoria-tecnica-operacional/spec.md`

## Summary

Transformar a PoC `local_mooc_audit` em MVP: (1) tornar a auditoria acessível pela administração do curso, no
contexto do curso, com permissão nativa; (2) reorganizar o catálogo em uma classe por regra com metadados
(categoria, tipo, gravidade, parâmetros de política, orientação, destino de navegação) sem alterar o comportamento
das 29 regras, protegido por testes de caracterização; (3) substituir a página mínima por um renderer com template
Mustache que agrupa por gravidade e categoria, mostra evidência completa, links para o ponto de correção e botão de
reexecução; (4) expor avisos de política inválida e versão do contrato JSON (1.0); (5) elevar a cobertura a um teste
por regra (conforme + não conforme), mais testes de não modificação e de permissão negada, com um construtor de
cursos de teste reaproveitado dos cursos sintéticos A/B. Sem tabelas, sem tarefas, sem Web Services, sem escrita.

## Technical Context

**Language/Version**: PHP 8.1+ (ambiente local 8.3.35), conforme requisito do Moodle 4.5

**Primary Dependencies**: APIs do núcleo Moodle 4.5 (`get_fast_modinfo`, `course_get_format`, `grade_item`/
`grade_category`, `enrol_get_instances`, `block_manager`, `file_storage`, `core_plugin_manager`, `completion_info`,
`navigation_node`, `renderer_base`/Mustache, `admin_setting_configtextarea`); nenhuma biblioteca externa

**Storage**: N/A (nenhuma tabela própria; relatório gerado por execução; configuração em `config_plugins` via settings)

**Testing**: PHPUnit 9.6 com `advanced_testcase` e geradores de dados do Moodle (`testing_data_generator`); Behat
mínimo para navegação e página (opcional no MVP, recomendado); `php -l`; PHPCS padrão `moodle` (via
`moodle-cs`/`moodle-plugin-ci` quando disponível no contêiner)

**Target Platform**: plugin `local_mooc_audit` em Moodle 4.5 (`MOODLE_405_STABLE`), PostgreSQL e MySQL/MariaDB via
DML; ambiente reproduzível `moodle-env/` (moodle-docker)

**Project Type**: plugin Moodle (biblioteca + página web + CLI) — projeto único

**Performance Goals**: relatório em < 5 s para salas com até 150 módulos (PoC: 0,08–0,24 s para 13–73 módulos);
sem cache, sem persistência

**Constraints**: somente leitura (princípio III); nenhuma alteração no núcleo; strings em `lang/`; preservar ids,
estados e critérios das 29 regras (SC-007); contrato JSON retrocompatível com a PoC + `contract_version`

**Scale/Scope**: ~100 salas MOOC por ciclo anual; 29 regras; 2 papéis (gerente; outros por atribuição); 1 página,
1 CLI de auditoria (o CLI de comparação permanece como está, experimental, fora do escopo)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação | Situação |
|---|---|---|
| I. Independência operacional | nenhuma dependência externa; regras com fonte externa continuam NV | PASS |
| II. Compatibilidade e extensibilidade | plugin local padrão; navegação via callback `*_extend_navigation_course` (API oficial); DML continua restrito à lista branca existente; terceiros ausentes → NA/NV (X-INT-03 + testes) | PASS |
| III. Auditoria não modifica cursos | nenhuma escrita acrescentada; teste de não modificação (FR-005/FR-027) incluído | PASS |
| IV. Correção assistida | fora de escopo; nenhuma escrita | PASS (N/A) |
| V. Regras rastreáveis | metadados por regra via interface (`id`, `meta()`), categoria e tipo obrigatórios; política continua fora do código | PASS |
| VI. Confiabilidade e transparência | 6 estados mantidos; blocos separados para NV/ERRO; `policy_error` passa a ser exibido; evidência sem truncamento | PASS |
| VII. Testabilidade | 1 teste por regra; construtor de fixtures por API; caracterização antes da refatoração | PASS |
| VIII. Segurança | `require_login($course)` + `require_capability` no contexto do curso; `riskbitmask` revisto; saída escapada via Mustache; nenhum segredo | PASS |
| IX. Simplicidade | nenhum serviço/fila/tabela; abstrações novas: `rule_interface` + `catalog` (justificadas: 1 teste por regra, metadados obrigatórios, remoção da tabela estática `catalog()` com strings) | PASS com registro em Complexity Tracking |
| X. Evolução incremental | spec → plan → tasks → analyze; nenhuma regra nova; contrato versionado | PASS |

Pós-design (Phase 1): sem alteração; o renderer/template e o `locator` de links são recursos nativos.

## Phase 0 — Research (resumo; detalhes em [research.md](research.md))

Decisões: (R1) uma classe por regra com interface e catálogo explícito, sem autodescoberta; (R2) testes de
caracterização por snapshot JSON dos cursos A/B antes de mover código; (R3) navegação por
`local_mooc_audit_extend_navigation_course` no menu "Mais" do curso; (R4) renderer + Mustache com `renderable`/
`templatable`; (R5) `locator` de URLs por tipo de local; (R6) validação do setting JSON por subclasse de
`admin_setting_configtextarea`; (R7) versão de contrato `1.0` aditiva; (R8) gerador de componente em
`tests/generator/lib.php` reaproveitando `make_fixtures.php`; (R9) teste de não modificação por hash de tabelas; (R10)
Behat mínimo opcional.

## Phase 1 — Design

- [data-model.md](data-model.md): estruturas em memória (relatório, resultado, metadados de regra, política,
  situação geral) e regras de validação.
- [contracts/report-v1.0.schema.json](contracts/report-v1.0.schema.json): contrato JSON do relatório (aditivo em
  relação à PoC).
- [contracts/cli.md](contracts/cli.md): opções da linha de comando.
- [contracts/page.md](contracts/page.md): parâmetros e comportamento da página e da navegação.
- [quickstart.md](quickstart.md): como validar a entrega ponta a ponta.

## Project Structure

### Documentation (this feature)

```text
specs/001-auditoria-tecnica-operacional/
├── spec.md
├── plan.md              # este arquivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── report-v1.0.schema.json
│   ├── cli.md
│   └── page.md
├── checklists/requirements.md
└── tasks.md             # gerado por /speckit-tasks
```

### Source Code (repository root)

```text
plugin/local_mooc_audit/
├── version.php                      # bump 2026102900, release 0.2.0
├── lib.php                          # NOVO: local_mooc_audit_extend_navigation_course()
├── index.php                        # REESCRITO: contexto do curso, require_login($course), renderer
├── settings.php                     # usa setting_policyjson (validação) e descreve parâmetros
├── db/access.php                    # riskbitmask revisto; strings
├── lang/{en,pt_br}/local_mooc_audit.php   # todas as strings da interface e metadados de regras
├── templates/
│   └── report.mustache              # NOVO
├── classes/
│   ├── engine.php                   # usa catalog; acrescenta contract_version, category, link, policy_warnings
│   ├── snapshot.php                 # INALTERADO (salvo correções documentadas)
│   ├── policy.php                   # expõe warnings estruturados; mantém load()
│   ├── comparator.php               # INALTERADO (fora do escopo)
│   ├── admin/setting_policyjson.php # NOVO: validação do JSON
│   ├── output/report.php            # NOVO: renderable/templatable
│   ├── output/renderer.php          # NOVO
│   ├── local/
│   │   ├── status.php               # NOVO: constantes dos 6 estados e 5 gravidades (hoje em rules)
│   │   ├── category.php             # NOVO: 8 categorias
│   │   ├── locator.php              # NOVO: finding → moodle_url|null
│   │   ├── catalog.php              # NOVO: lista explícita das 29 classes de regra
│   │   ├── helpers.php              # NOVO: norm(), text(), label(), mods(), graded(), certs(), d()
│   │   └── rule/
│   │       ├── rule_interface.php   # NOVO: id(), meta(), evaluate(array $s, array $p): array
│   │       ├── base.php             # NOVO: finding builder f(), meta padrão
│   │       ├── cl04.php … cl23.php  # NOVO: 18 classes (código movido de rules.php)
│   │       └── xint02.php … xstr01.php  # NOVO: 11 classes
│   ├── rules.php                    # REMOVIDO ao fim da migração (após testes de caracterização passarem)
│   └── privacy/provider.php         # INALTERADO (nenhum dado pessoal)
├── cli/audit.php                    # inalterado em opções; usa engine
├── cli/compare.php, make_fixtures.php, make_import.php   # inalterados (laboratório)
└── tests/
    ├── generator/lib.php            # NOVO: local_mooc_audit_generator cria cursos A/B por API (extraído de make_fixtures.php)
    ├── fixtures/expected_A.json, expected_B.json   # NOVO: estados por regra (caracterização)
    ├── characterization_test.php    # NOVO: A/B → estados por regra idênticos aos esperados
    ├── engine_test.php              # mantido
    ├── readonly_test.php            # NOVO: hash de tabelas antes/depois
    ├── access_test.php              # NOVO: capacidade negada; navegação oculta
    ├── policy_test.php              # NOVO: JSON inválido → warning; override
    ├── locator_test.php             # NOVO
    ├── rule/cl04_test.php … xstr01_test.php   # NOVO: 29 arquivos, C + NC (+ NA/NV quando houver)
    └── behat/audit_page.feature     # NOVO (opcional): entrada no menu e relatório
```

**Structure Decision**: projeto único (o plugin). Classes internas em `classes/local/` (convenção Moodle para
API não pública), saída em `classes/output/` + `templates/`. `snapshot.php` e `comparator.php` não são tocados
para limitar o risco; a divisão do snapshot em coletores fica para quando uma regra nova exigir.

## Estratégia de testes

1. **Caracterização primeiro**: o gerador `local_mooc_audit_generator` cria A e B em PHPUnit; `characterization_test` registra estados por
   regra e compara com `expected_A.json`/`expected_B.json` gerados a partir da versão atual (`rules.php`). Só então
   o código é movido.
2. **Um teste por regra**: cenário mínimo por API (curso + módulos necessários), caminho C e NC; NA/NV/ATENCAO
   quando a regra os produz. Regras só validadas em C na PoC (CL-04, CL-05, X-DEP-01, X-DEP-03, X-INT-03) ganham
   NC explícito (X-INT-03: simular plugin ausente injetando tipo de condição inexistente no `availability`).
3. **Não modificação**: `readonly_test` computa hash de `course`, `course_sections`, `course_modules`,
   `grade_items`, `grade_categories`, `enrol`, `block_instances`, `files` (e `timemodified`) antes e depois de
   `engine::audit()`.
4. **Acesso**: `access_test` verifica `has_capability` para estudante e professor (sem a capacidade por padrão) e
   que `lib.php` não adiciona o nó sem capacidade; Behat (opcional) cobre a página real.
5. **Política**: `policy_test` cobre JSON inválido (warning + padrões), chave desconhecida (ignorada), `reoffer_year`
   automático.
6. **Contrato**: `engine_test` passa a validar presença de `contract_version`, `category`, `link` em cada resultado.
7. Portão: suíte completa verde no contêiner (`vendor/bin/phpunit local/mooc_audit/tests`), `php -l`, PHPCS.

## O que preservar e o que muda

| Preservar | Muda | Por quê |
|---|---|---|
| ids, estados, gravidades e evidências das 29 regras | a localização do código (uma classe por regra) | 1 teste por regra; metadados obrigatórios (princípio V) |
| `snapshot::build()` e sua lista branca | nada | risco × benefício: nenhuma regra nova exige campo novo |
| `engine::audit()` (assinatura) e campos JSON existentes | acréscimo de `contract_version`, `category`, `link`, `policy_warnings` | contrato aditivo |
| `policy::defaults()` e `load()` | `policy_error` vira lista `warnings` exposta | FR-009 |
| CLI `audit.php` (opções) | nada | FR-025 |
| `comparator.php`, `compare.php`, scripts de laboratório | nada (defeito `problematic` fica registrado para 003) | fora do escopo |
| `db/access.php` capacidades | revisão de `riskbitmask`; strings | D9 do baseline |
| página `index.php` | reescrita: contexto do curso, renderer, agrupamento, links, reexecutar | US1–US3 |

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Nova interface `rule_interface` + 29 classes + `catalog` | princípio V exige metadados por regra e VII exige teste por regra; a classe única de 1 080 linhas com array de strings impede ambos sem acoplamento | manter `rules.php` e adicionar um array paralelo de metadados: duplica a fonte de verdade (id em dois lugares), não isola regra para teste e mantém o arquivo crescendo com cada regra nova |
| `locator` (mapeamento finding → URL) | FR-019 exige links por tipo de local; a URL não pertence à regra (que vale também no CLI) | montar URLs dentro de cada regra: espalha conhecimento de rotas em 29 lugares e quebra no CLI |
