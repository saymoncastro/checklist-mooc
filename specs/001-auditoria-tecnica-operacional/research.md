# Research — 001 Auditoria Técnica Operacional

Todas as incertezas do Technical Context resolvidas abaixo. Fontes: código do Moodle 4.5 (`MOODLE_405_STABLE`),
PoC (`plugin/local_mooc_audit/`), `RELATORIO-POC-PLUGIN-MOODLE.md`, constituição do projeto.

## R1 — Organização das regras

- **Decision**: uma classe por regra em `classes/local/rule/`, implementando `rule_interface` (`id(): string`,
  `meta(): array`, `evaluate(array $snapshot, array $policy): array`), com `base` abstrata que fornece o construtor
  de findings; catálogo **explícito** em `classes/local/catalog.php` (array ordenado de nomes de classe).
- **Rationale**: metadados obrigatórios por regra (princípio V), um arquivo de teste por regra (VII), diff pequeno
  por regra nova (X). Catálogo explícito evita autodescoberta por `glob`/reflexão (ordem determinística, sem custo de
  I/O, sem surpresa com arquivos esquecidos).
- **Alternatives considered**: manter `rules.php` + array de metadados (duas fontes de verdade); autodescoberta por
  `core_component::get_component_classes_in_namespace` (funciona, mas ordem e inclusão implícitas); atributos PHP 8
  (`#[Rule]`) para metadados (elegante, mas exige reflexão e ainda não é padrão Moodle).

## R2 — Preservar o comportamento durante a migração

- **Decision**: testes de caracterização: o gerador `local_mooc_audit_generator` (`tests/generator/lib.php`) cria cursos A e B por API em PHPUnit;
  `characterization_test` compara, por regra, a lista ordenada de estados com `expected_A.json`/`expected_B.json`
  gerados **antes** da migração com `rules.php`. A migração só é aceita com o teste verde.
- **Rationale**: SC-007 exige mesmos estados por regra que a PoC; é o único critério objetivo de "refatoração sem
  mudança de comportamento".
- **Alternatives considered**: comparar com `plugin-results/cursoA.json` diretamente (cmids e datas diferem a cada
  criação; frágil); confiar nos testes por regra (escritos depois; não cobrem interações).

## R3 — Entrada na administração do curso

- **Decision**: `lib.php` com `local_mooc_audit_extend_navigation_course(navigation_node $navigation, stdClass
  $course, context_course $context)`: adiciona nó "Auditoria técnica" (`navigation_node::TYPE_SETTING`, ícone
  `i/report`) quando `has_capability('local/mooc_audit:audit', $context)`. No Moodle 4.x aparece no menu "Mais" da
  navegação secundária do curso.
- **Rationale**: callback oficial para plugins locais; sem alteração no núcleo; respeita a capacidade.
- **Alternatives considered**: `*_extend_settings_navigation` (mesmo resultado, assinatura menos específica); bloco
  próprio (exige adicionar bloco em cada sala, viola a experiência pedida); link só no admin (já existe, não atende).

## R4 — Página do relatório

- **Decision**: `index.php` recebe `courseid` (ou `id`), faz `require_login($course)`, `require_capability` no
  contexto do curso, `$PAGE->set_context($context)`, `set_pagelayout('incourse')`; constrói `output\report`
  (`renderable`, `templatable`) a partir do array do `engine` e renderiza `templates/report.mustache` via
  `output\renderer`. Seções do template: cabeçalho/inventário, situação geral + contagens, inconformidades por
  gravidade → categoria, alertas, não verificáveis, erros, conformes (lista compacta), avisos; botão "Executar
  novamente" (GET para a mesma URL com `sesskey` não é necessário: operação de leitura) e link "JSON".
- **Rationale**: padrão Moodle (escape automático no Mustache, strings via `{{#str}}`, acessibilidade do tema);
  separa dados (engine) de apresentação; permite Behat.
- **Alternatives considered**: continuar com `html_writer` (sem template, strings fixas, difícil de testar);
  relatório via `report_*` plugin type (exigiria outro plugin).

## R5 — Links para o ponto de correção

- **Decision**: cada regra declara em `meta()['locus']` o tipo de local que seus findings usam, e cada finding pode
  sobrescrever com `locus` próprio; `classes/local/locator.php` converte `(locus, cmid, section, courseid)` em `moodle_url|null`, recebendo as
  seções do snapshot para resolver o id da seção:
  `module_edit` → `/course/modedit.php?update=cmid`; `module_view` → `/mod/<modname>/view.php?id=cmid`;
  `course_settings` → `/course/edit.php?id=`; `enrol` → `/enrol/instances.php?id=`; `gradebook` →
  `/grade/edit/tree/index.php?id=`; `blocks` → `/course/view.php?id=&notifyeditingon=1`; `section` →
  `/course/view.php?id=#section-n` (ou `/course/section.php?id=sectionid` no 4.4+); `summary` → `course_settings`;
  `none` → sem link. O `engine` acrescenta `link` (string ou null) a cada resultado; o CLI mantém o campo (útil no
  JSON).
- **Rationale**: FR-019; conhecimento de rotas em um só lugar; testável isoladamente (`locator_test`).
- **Alternatives considered**: URL dentro de cada regra (dispersão); só `cmid` sem link (não atende US2).

## R6 — Validação da política no admin

- **Decision**: `classes/admin/setting_policyjson.php extends admin_setting_configtextarea` com `validate($data)`
  que aceita vazio ou objeto JSON válido (`json_decode(..., true)` é array associativo); mensagem de erro com
  `json_last_error_msg()`. `policy::load()` passa a devolver `warnings[]` (JSON inválido; chave com tipo
  inesperado) e `engine` os inclui em `policy_warnings` e no bloco de avisos da página.
- **Rationale**: FR-009; o Moodle já mostra erros de `validate()` ao salvar; o relatório deixa de ocultar o problema
  (D8 do baseline).
- **Alternatives considered**: só validar no `load()` (administrador não vê ao salvar); `admin_setting_configtext`
  com `PARAM_RAW` + regex (insuficiente para JSON).

## R7 — Versão do contrato JSON

- **Decision**: campo `contract_version: "1.0"` no topo do relatório; todos os campos da PoC mantidos; acréscimos
  aditivos: `category` e `link` em cada resultado, `policy_warnings`, `overall_status`
  (`BLOQUEADA|REQUER_CORRECOES|SEM_INCONFORMIDADES`). Regra: mudança que remova ou renomeie campo → `2.0`.
- **Rationale**: princípio X; base da spec 004.
- **Alternatives considered**: versionar pela versão do plugin (acopla contrato a release); sem versão (impede
  consumidores).

## R8 — Construtor de cursos de teste

- **Decision**: extrair de `cli/make_fixtures.php` a lógica de criação (A conforme, B inconsistente) para o gerador
  oficial do componente, `tests/generator/lib.php` (`local_mooc_audit_generator extends component_generator_base`,
  métodos `create_course_a(int $year)` / `create_course_b(int $year)`, acessível por
  `$this->getDataGenerator()->get_plugin_generator('local_mooc_audit')` e por `require_once` no CLI), parametrizada
  por ano; o CLI passa a chamar o construtor (um único código). Cenários que exigem
  `mod_game`/`mod_simplecertificate` são marcados com `markTestSkipped` quando o módulo não está instalado.
- **Rationale**: FR-026 exige cenários por API; evita duplicar 300 linhas; cursos A/B viram oráculo automatizado.
- **Alternatives considered**: fixtures `.mbz` sintéticos (restauração lenta em PHPUnit, dependência de formato);
  reescrever cenários do zero em cada teste (duplicação).

## R9 — Teste de não modificação

- **Decision**: `readonly_test` lê, antes e depois de `engine::audit($courseid)`, `$DB->get_records()` das
  tabelas `course`, `course_sections`, `course_modules`, `grade_items`, `grade_categories`, `enrol`,
  `block_instances`, `files`, `quiz`, `feedback`, `glossary` (as que o snapshot toca) filtradas pelo curso quando
  houver coluna, serializa e compara hashes `sha1`. Também verifica `course.timemodified` e `cacherev` inalterados.
- **Rationale**: FR-005/FR-027/SC-008; barato; detecta escrita acidental em qualquer regra futura.
- **Alternatives considered**: interceptar `$DB` com mock (frágil em `advanced_testcase`); confiar em revisão de
  código.

## R10 — Behat

- **Decision**: um `.feature` mínimo (gerente vê "Auditoria técnica" no menu do curso e abre o relatório; estudante
  não vê), executado no `moodle-docker` quando disponível; não bloqueia o portão PHPUnit.
- **Rationale**: a navegação e o template só são validados de verdade no navegador; custo baixo.
- **Alternatives considered**: só PHPUnit (não cobre navegação secundária real); Behat completo (fora de proporção).

## R11 — `riskbitmask` das capacidades

- **Decision**: manter `RISK_PERSONAL` em `local/mooc_audit:audit`? **Não**: a auditoria lê configuração de curso e
  conteúdo pedagógico, não dados de usuários; trocar para sem risco declarado (`riskbitmask` omitido) e documentar;
  `:compare` igual. Se a spec 005 persistir "quem executou", reavaliar.
- **Rationale**: coerência com `privacy/provider` (D9).
- **Alternatives considered**: manter como está (conservador, mas incorreto na semântica do Moodle).

## R12 — Categorias das regras

| Categoria | Regras |
|---|---|
| Oferta e inscrição | CL-05, CL-06, CL-07, CL-08 |
| Recursos institucionais | CL-04, CL-09, CL-11 (presença), CL-22 |
| Avaliação e notas | CL-12, CL-13, CL-14, X-AV-01 |
| Conclusão e restrições | CL-16, CL-17, CL-19, X-DEP-01, X-DEP-02, X-DEP-03, X-DEP-04 |
| Certificação | CL-23, X-CERT-01 (e a parte "exigida pelo certificado" de CL-11 permanece em CL-11) |
| Conteúdo e arquivos | X-INT-02, X-CONT-01, X-CONT-04 |
| Estrutura, seções e blocos | CL-18, CL-20, CL-21, X-STR-01 |
| Plugins e ambiente | X-INT-03 |

Cada regra pertence a exatamente uma categoria (`meta()['category']`); a categoria é um `category::*` com string de
idioma própria.
