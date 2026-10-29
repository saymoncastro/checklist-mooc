# local_mooc_audit — prova de conceito de auditoria técnica de cursos MOOC dentro do Moodle 4.5

Plugin local **somente leitura** (versão 0.2.0, em evolução pela spec 001) que audita uma sala existente a partir do seu `courseid`, sem depender do MOOC Admin
nem de arquivos `.mbz`. Reaproveita o catálogo de regras e as decisões institucionais do projeto `checklist-mooc`.

**Estado:** PoC reorganizada (0.2.0): regras em classes próprias, catálogo com metadados, contrato JSON 1.0, caracterização dos cursos A/B, página do relatório no contexto do curso (menu *Mais > Auditoria técnica*). Validado em Moodle 4.5.15 / PHP 8.3 / PostgreSQL 17 em ambiente local.
Não instalar em produção sem revisão.

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
*Administração do site > Plugins > Plugins locais > Auditoria MOOC* (JSON) ou com `--policy`. O ano da reoferta,
quando não informado, é o ano seguinte ao da execução.

Scripts **apenas para ambientes de teste** (recusam rodar fora de `localhost`): `cli/make_fixtures.php`
(cria os cursos A — referência — e B — inconsistente; exige o autoloader do Composer do site de desenvolvimento, porque o
gerador de questões do núcleo carrega classes do PHPUnit) e `cli/make_import.php` (cria sala vazia e importa com o
mecanismo nativo `core_course_external::import_course`).

## Estrutura (0.2.0)

| Arquivo | Papel |
|---|---|
| `classes/snapshot.php` | Retrato JSON-serializável da sala (mesmo modelo do protótipo MBZ) |
| `classes/policy.php` | Parâmetros institucionais (fora do código das regras); avisos de política inválida |
| `classes/local/rule/rule_interface.php`, `base.php` | Contrato de uma regra (`id()`, `meta()`, `evaluate()`) e construtor de findings |
| `classes/local/rule/*.php` | Uma classe por regra: 18 `CL-*` (checklist) + 11 `X-*` (adicionais), com categoria, tipo, gravidade, chaves de política, locus e fonte |
| `classes/local/catalog.php` | Lista explícita e ordenada das 29 regras |
| `classes/local/{status,category,helpers,locator}.php` | Estados/gravidades, 8 categorias, helpers compartilhados, mapeamento finding → URL de correção (loci: configurações/página do módulo, configurações do curso, inscrição, livro de notas, blocos, seção, importação, nenhum) |
| `classes/engine.php` | Execução isolada das regras, situação geral, contrato JSON 1.0, renderização texto |
| `classes/comparator.php` | Comparador experimental origem × destino (inalterado; escopo da spec 003) |
| `cli/audit.php`, `cli/compare.php` | Linha de comando |
| `lib.php` | Callback `local_mooc_audit_extend_navigation_course()`: entrada "Auditoria técnica" no menu do curso para quem tem a capacidade |
| `index.php` | Página do relatório no contexto do curso (`courseid` ou `id`; `format=json`); local de cada resultado é link para a página de correção quando existe; botão "Executar novamente"; sem id, formulário de compatibilidade |
| `classes/output/report.php`, `renderer.php`, `templates/report*.mustache` | Dados do relatório para o template (agrupamento por gravidade → categoria, listas de alertas, não verificados, erros, conformes, avisos) e renderização |
| `tests/generator/lib.php` | Gerador de componente: cursos A (conforme) e B (inconsistente), usado pelo PHPUnit e por `cli/make_fixtures.php` |
| `tests/characterization_test.php` + `tests/fixtures/expected_*.json` | Estados por regra dos cursos A/B congelados (proteção contra mudança de comportamento) |
| `tests/engine_test.php` | Motor, contrato, política, isolamento, conclusão desabilitada no site, saída texto do CLI |
| `tests/locator_test.php` | URL esperada por locus; identificadores ausentes → sem link |
| `tests/access_test.php` | Capacidade por arquétipo (estudante/professor não; gerente sim), definição sem risco declarado, nó de navegação só com a capacidade |
| `tests/output_test.php` | Dados do template e renderização da página (cursos A/B) |

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

## Limitações conhecidas

- Regras por nome canônico (fórum, pesquisas, certificado) dependem dos nomes oficiais (decisão 10 pendente).
- `mod_simplecertificate` do GitHub (2024051100) é anterior ao da produção (2024051105); `availability_days` só existe
  publicamente na versão 2016 (instalada apenas para validar a leitura de condições `days`).
- O plugin não vê o pacote `.mbz`: corrupção de backup e arquivos ausentes *no pacote* só são detectáveis antes da
  restauração. Ver `RELATORIO-POC-PLUGIN-MOODLE.md`.
- Textos de evidência das regras ainda são gerados em português no código (orientações e títulos já estão em `lang/`).
