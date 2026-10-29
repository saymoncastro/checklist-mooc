# local_mooc_audit — auditoria técnica de cursos MOOC dentro do Moodle 4.5

Plugin local **somente leitura** (versão 0.2.0, entrega da spec 001) que audita uma sala existente a partir do seu `courseid`, sem depender do MOOC Admin
nem de arquivos `.mbz`. Reaproveita o catálogo de regras e as decisões institucionais do projeto `checklist-mooc`.

**Estado (0.2.0, maturidade ALPHA):** 29 regras em classes próprias, cada uma com teste PHPUnit de cenário conforme e
não conforme; contrato JSON 1.0 com schema; página do relatório no contexto do curso (menu *Mais > Auditoria técnica*)
com links de correção e reexecução; política institucional ajustável na administração. Validado em Moodle 4.5.15 /
PHP 8.3 / PostgreSQL 17 em ambiente local: 71 testes PHPUnit, 2 cenários Behat com verificação de acessibilidade (axe),
PHPCS padrão `moodle` sem erros nem avisos, e estados por regra idênticos aos da PoC nos cursos A, B, 55 e 670
(`plugin-results/0.2.0/`). Antes da produção: decisão 10 (nomes canônicos), validação com bloco de site real e revisão
da equipe técnica.

## Instalação

```bash
cp -r plugin/local_mooc_audit <moodle>/local/mooc_audit
php admin/cli/upgrade.php --non-interactive
```

Nenhuma alteração no núcleo. Capacidades próprias: `local/mooc_audit:audit` (contexto de curso) e
`local/mooc_audit:compare` (sistema), concedidas por padrão ao arquétipo *manager*. Nenhuma tabela própria.

## Uso

```bash
# Auditoria (texto ou JSON); --userid define a visão usada em "disponível para o usuário"
php local/mooc_audit/cli/audit.php --courseid=3
php local/mooc_audit/cli/audit.php --courseid=3 --json --out=/tmp/curso3.json
php local/mooc_audit/cli/audit.php --courseid=3 --userid=42          # visão de um estudante de teste
php local/mooc_audit/cli/audit.php --courseid=3 --policy=politica.json

# Comparação origem x destino (correspondência por tipo+seção+nome, nunca por cmid)
php local/mooc_audit/cli/compare.php --source=8 --target=10

# Página web (exige login e a capacidade no contexto do curso); também pelo menu do curso: Mais > Auditoria técnica
/local/mooc_audit/index.php?courseid=3            # relatório (inventário, situação geral, inconformidades por gravidade/categoria, alertas, conformes, avisos)
/local/mooc_audit/index.php?courseid=3&format=json
```

Política institucional: padrões em `classes/policy.php` (decisões de 2026-10-28); sobrescrever em
*Administração do site > Plugins > Plugins locais > Auditoria MOOC* (JSON) ou com `--policy`. A página de
administração lista as chaves aceitas com tipo e valor padrão e mostra o JSON padrão da versão, ambos gerados de
`policy::defaults()`; JSON inválido é recusado ao salvar. O relatório mostra a versão da política aplicada (sufixo
`+admin` quando há personalização). O ano da reoferta, quando não informado, é o ano seguinte ao da execução.

Scripts **apenas para ambientes de teste** (recusam rodar fora de `localhost`): `cli/make_fixtures.php`
(cria os cursos A — referência — e B — inconsistente; exige o autoloader do Composer do site de desenvolvimento, porque o
gerador de questões do núcleo carrega classes do PHPUnit) e `cli/make_import.php` (cria sala vazia e importa com o
mecanismo nativo `core_course_external::import_course`).

## Estrutura (0.2.0)

| Arquivo | Papel |
|---|---|
| `classes/snapshot.php` | Retrato JSON-serializável da sala (mesmo modelo do protótipo MBZ) |
| `classes/policy.php`, `classes/admin/setting_policyjson.php` | Parâmetros institucionais (fora do código das regras); avisos de política inválida; o setting rejeita JSON malformado antes de salvar |
| `classes/local/rule/rule_interface.php`, `base.php` | Contrato de uma regra (`id()`, `meta()`, `evaluate()`) e construtor de findings |
| `classes/local/rule/*.php` | Uma classe por regra: 18 `CL-*` (checklist) + 11 `X-*` (adicionais), com categoria, tipo, gravidade, chaves de política, locus e fonte |
| `classes/local/catalog.php` | Lista explícita e ordenada das 29 regras |
| `classes/local/{status,category,helpers,locator}.php` | Estados/gravidades, 8 categorias, helpers compartilhados, mapeamento finding → URL de correção (loci: configurações/página do módulo, configurações do curso, inscrição, livro de notas, blocos, seção, importação, nenhum) |
| `classes/engine.php` | Execução isolada das regras, situação geral, contrato JSON 1.0 (`to_json()` é a única codificação, usada pela página e pelo CLI), renderização texto |
| `schema/report-v1.0.schema.json` | JSON Schema do contrato 1.0 (cópia idêntica à de `specs/001-.../contracts/`); validado por `tests/contract_test.php` |
| `classes/comparator.php` | Comparador experimental origem × destino (inalterado; escopo da spec 003) |
| `cli/audit.php`, `cli/compare.php` | Linha de comando |
| `lib.php` | Callback `local_mooc_audit_extend_navigation_course()`: entrada "Auditoria técnica" no menu do curso para quem tem a capacidade |
| `index.php` | Página do relatório no contexto do curso (`courseid` ou `id`; `format=json`); local de cada resultado é link para a página de correção quando existe; botão "Executar novamente"; sem id, formulário de compatibilidade |
| `classes/output/report.php`, `renderer.php`, `templates/report*.mustache` | Dados do relatório para o template (agrupamento por gravidade → categoria, listas de alertas, não verificados, erros, conformes, avisos) e renderização |
| `tests/generator/lib.php` | Gerador de componente: cursos A (conforme) e B (inconsistente), usado pelo PHPUnit e por `cli/make_fixtures.php` |
| `tests/characterization_test.php` + `tests/fixtures/expected_*.json` | Estados por regra dos cursos A/B congelados (proteção contra mudança de comportamento) |
| `tests/engine_test.php` | Motor, contrato, política, isolamento de regra com exceção (regra injetada), conclusão desabilitada no site, locus de importação, saída texto do CLI |
| `tests/contract_test.php` | Relatórios de A/B válidos contra o schema (validador mínimo sem biblioteca), campos da PoC preservados, ida e volta do JSON |
| `tests/policy_test.php` | Padrões, override do administrador e por arquivo, tipos errados, ano de reoferta automático, validação do setting, nome canônico alterado aplicado a CL-09 |
| `tests/helper/rule_testcase.php` | Base dos testes por regra: curso na categoria da oferta com datas da reoferta, construtores de página/questionário/pesquisa/certificado e árvores de restrição |
| `tests/local/rule/*_test.php` | Um arquivo por regra (29): cenários conforme, não conforme e, quando a regra os produz, atenção/não se aplica/não verificável, construídos por API (ex.: X-INT-03 com condição de plugin inexistente; X-CERT-01 com visão de estudante) |
| `tests/readonly_test.php` | Hashes de 17 tabelas do curso B antes e depois de três auditorias (admin, estudante, texto) idênticos; `timemodified`/`cacherev` inalterados |
| `tests/locator_test.php` | URL esperada por locus; identificadores ausentes → sem link |
| `tests/access_test.php` | Capacidade por arquétipo (estudante/professor não; gerente sim), definição sem risco declarado, nó de navegação só com a capacidade |
| `tests/output_test.php` | Dados do template e renderização da página (cursos A/B) |
| `tests/behat/audit_page.feature` | Gerente abre o relatório pelo menu do curso (com JavaScript e verificação de acessibilidade axe), reexecuta; professor e estudante não veem a entrada |

Fluxo de desenvolvimento: `specs/001-auditoria-tecnica-operacional/` (Spec Kit) e `.specify/memory/constitution.md`.

## Fontes de dados e justificativa dos acessos

| Informação | API usada | Observação |
|---|---|---|
| Curso, categoria, formato | `get_course()`, `core_course_category::get()`, `course_get_format()->get_format_options()` | API |
| Seções e módulos (visibilidade, conclusão, restrições, visão do usuário) | `get_fast_modinfo($course, $userid)` → `section_info`, `cm_info` | API; `availability` é o mesmo JSON do backup |
| Livro de notas | `grade_category::fetch_all()`, `grade_item::fetch_all()` | API (inclui `aggregation` e `aggregateonlygraded`, que os Web Services não expõem) |
| Métodos de inscrição | `enrol_get_instances($courseid, false)` | API (inclui datas e `customint6`, que os Web Services não expõem) |
| Blocos da página do curso, inclusive herdados do site | `block_manager` (`load_blocks`, `create_all_block_instances`); fallback DML em `block_instances` | API |
| Arquivos e existência do conteúdo | `files` por contexto (DML) + `file_storage::get_file_by_id()` + `file_system::is_file_readable_locally_by_storedfile()` | distingue registro sem conteúdo de referência sem registro |
| Plugins instalados | `core_plugin_manager::get_installed_plugins()`; `standard_plugins_list()` | API |
| Conclusão de curso | `completion_info` | API |
| **Configurações específicas por módulo** (tentativas, datas, `completionsubmit`, fonte de jogos, texto do certificado…) | `$DB->get_record('<modname>', …)` com **lista branca de colunas** + `count_records` (`quiz_slots`, `feedback_item`, `glossary_entries`, `book_chapters`, `hvp_libraries`) | **DML justificado:** não existe API cross-módulo para ler configurações; são tabelas estáveis de cada módulo; nenhuma escrita |

## Verificação

```bash
vendor/bin/phpunit --testsuite local_mooc_audit_testsuite      # 71 testes
php admin/tool/behat/cli/run.php --tags=@local_mooc_audit       # exige Selenium; ver moodle-env/README.md
vendor/bin/phpcs --standard=moodle local/mooc_audit             # moodlehq/moodle-cs
```

## Limitações conhecidas

- Regras por nome canônico (fórum, pesquisas, certificado) dependem dos nomes oficiais (decisão 10 pendente).
- `mod_simplecertificate` do GitHub (2024051100) é anterior ao da produção (2024051105); `availability_days` só existe
  publicamente na versão 2016 (instalada apenas para validar a leitura de condições `days`).
- O plugin não vê o pacote `.mbz`: corrupção de backup e arquivos ausentes *no pacote* só são detectáveis antes da
  restauração. Ver `RELATORIO-POC-PLUGIN-MOODLE.md`.
- Textos de evidência das regras ainda são gerados em português no código (orientações e títulos já estão em `lang/`).
- Módulo com nome vazio não aparece no `modinfo` do Moodle; X-STR-01 só detecta nomes formados por espaços ou marcação.
- Sem persistência: cada execução é recalculada; histórico e acompanhamento são da spec 005.
- `cli/compare.php` (comparador origem × destino) segue experimental e fora do contrato 1.0 (spec 003).
