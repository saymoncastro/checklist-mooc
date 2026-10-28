# Baseline técnico e funcional da PoC `local_mooc_audit`

**Data do levantamento:** 2026-10-28 · **Commit de referência:** `dc12796` (plugin) · **Método:** leitura integral do
código em `plugin/local_mooc_audit/`, execução da suíte PHPUnit no ambiente local (`moodle-env/`), confronto com
`plugin-results/` e com os relatórios anteriores.

**Legenda de maturidade** (estados objetivos usados em todo o documento):

| Estado | Significado |
|---|---|
| **Implementado e testado** | existe no código e há teste automatizado cobrindo caminho conforme e não conforme |
| **Implementado com testes insuficientes** | existe no código; validado apenas manualmente (cursos sintéticos A/B ou cursos restaurados) ou com teste parcial |
| **Parcialmente implementado** | existe parte da capacidade; o restante está ausente |
| **Planejado** | descrito em relatório ou README, inexistente no código |
| **Não validado** | existe no código, mas nenhuma evidência de funcionamento foi produzida |

---

## 1. Estado do repositório e do ambiente (verificado)

| Item | Resultado |
|---|---|
| Repositório | `checklist-mooc`, branch de trabalho `claude/install-spec-kit-01eeb1` (worktree), base `main` em `dc12796`; working tree limpo antes da adoção do Spec Kit |
| Raiz do projeto SDD | a raiz do repositório `checklist-mooc` (o plugin vive em `plugin/local_mooc_audit/`; o Moodle fica em `moodle-local/`, ignorado pelo git). As especificações **não** dependem da árvore do Moodle |
| Não versionados (por projeto) | `*.mbz` (4 backups institucionais na raiz da cópia principal), planilha do checklist, `moodle-local/`, `extract/`, `poc-work/` |
| Ambiente Moodle local | contêineres `moocaudit-webserver-1`, `moocaudit-db-1`, `moocaudit-mailpit-1` em execução (Colima); Moodle 4.5.15 (Build 20261005), PHP 8.3.35, PostgreSQL 17.11; plugin instalado na versão `2026102802`; plugins de terceiros nas versões de `moodle-env/README.md` (`mod_simplecertificate` 2024051100, `mod_game` 2026100800, `mod_hvp` 2026090101, `block_completion_progress` 2026101200, `availability_days` 2016060100) |
| Cursos presentes no laboratório | 2 (curso 55 restaurado), 3 (curso 670 restaurado), 8 (A), 9 (B), 10 e 11 (A importado), 12 (B importado) |
| Montagem | `local.yml` monta `plugin/local_mooc_audit` da **cópia principal** em `/var/www/html/local/mooc_audit`; a cópia do worktree é byte a byte idêntica (`diff -rq`) |
| Lint | `php -l` sem erros em todos os `.php` do plugin |
| **PHPUnit (executado em 2026-10-28)** | `vendor/bin/phpunit local/mooc_audit/tests/engine_test.php` → **OK (3 tests, 12 assertions)**, 1,9 s, igual a `plugin-results/phpunit.txt` |
| Ambientes de outros projetos | contêineres `coursecare-*` existentes e parados; não foram tocados |

## 2. Inventário de código

| Arquivo | Linhas | Papel | Maturidade |
|---|---|---|---|
| `classes/snapshot.php` | 473 | coletor somente leitura: curso, formato, seções, módulos (`cm_info`), configurações por módulo (lista branca DML), notas, inscrições, blocos (inclusive herdados), arquivos × `filedir`, plugins instalados/usados, critérios de conclusão | Implementado com testes insuficientes (exercitado indiretamente pelos 3 testes) |
| `classes/policy.php` | 116 | política institucional padrão (decisões de 2026-10-28) + sobrescrita por setting JSON e por arquivo CLI; `reoffer_year` calculado | Implementado com testes insuficientes (sobrescrita testada; JSON inválido não testado) |
| `classes/rules.php` | 1 080 | 29 regras como métodos estáticos de uma única classe; helpers de normalização; vocabulário de 6 estados | ver catálogo (`docs/catalogo-regras.md`) |
| `classes/engine.php` | 184 | executa regras com isolamento de exceções, inventário, síntese, renderização texto | Implementado e testado (isolamento e cobertura do catálogo) |
| `classes/comparator.php` | 325 | comparador origem × destino por tipo+seção+nome, remapeamento de `availability` | Não validado por teste automatizado; validado manualmente (A→10/11, B→12) |
| `cli/audit.php` | 59 | CLI texto/JSON, `--userid`, `--policy`, `--out`, `--snapshot` | Implementado com testes insuficientes (sem teste; usado em todas as execuções registradas) |
| `cli/compare.php` | 44 | CLI de comparação | idem |
| `cli/make_fixtures.php` | 361 | **só teste**: cria cursos A (conforme) e B (inconsistente); recusa fora de `localhost` | utilitário de laboratório |
| `cli/make_import.php` | 85 | **só teste**: cria sala vazia, importa com `core_course_external::import_course`, aplica ajustes; **apaga curso homônimo existente** | utilitário de laboratório |
| `index.php` | 70 | página mínima: formulário `courseid`, tabela de resultados, `&format=json` | Parcialmente implementado (ver §4) |
| `settings.php` | 39 | setting `policyjson` + link para a página | Implementado com testes insuficientes |
| `db/access.php` | — | `local/mooc_audit:audit` (curso) e `local/mooc_audit:compare` (sistema), `captype read`, `manager` por padrão | Implementado com testes insuficientes (sem teste de negação) |
| `lang/en`, `lang/pt_br` | — | 9 strings | Parcialmente implementado: `index.php` e `engine::render_text` contêm texto fixo em português (cabeçalhos da tabela, "Resumo:", "Estado", …) |
| `classes/privacy/provider.php` | — | `null_provider` (nenhum dado pessoal armazenado) | coerente com a ausência de persistência |
| `version.php` | — | `2026102802`, `requires 2024100700` (4.5), `MATURITY_ALPHA`, `0.1.0-poc` | — |
| `tests/engine_test.php` | 103 | 3 testes / 12 asserções | ver §3 |
| **Total** | **2 971** linhas PHP (inclui 446 dos scripts de laboratório) | | |

Fora do plugin: `poc/mbz_inspect.py` (protótipo MBZ com 51 regras, oráculo e origem dos ids), `poc/moodle_api_client.py`
(cliente REST nunca executado), relatórios e `plugin-results/` (saídas de referência).

## 3. Cobertura de testes automatizados (estado real)

| Teste | O que prova | Regras cobertas |
|---|---|---|
| `test_quiz_attempts_and_grade_sum` | quiz com 1 tentativa → `NAO_CONFORME`; com 2 → `CONFORME`; soma 60+40 → `CONFORME`; cabeçalho `engine` | CL-13 (tentativas), CL-12 (apenas a soma) |
| `test_dependency_rules` | cadeia válida → `CONFORME`; condição sobre módulo sem conclusão → `NAO_CONFORME`; ciclo → `NAO_CONFORME` | X-DEP-02, X-DEP-04 |
| `test_policy_override_and_isolation` | sobrescrita de `reoffer_year`/`policy_version`; nenhum `ERRO`; toda regra do catálogo produz ao menos um resultado | engine, policy |

**Consequência:** 4 das 29 regras têm teste automatizado (2 delas completos); 25 regras foram validadas apenas pelos
cursos sintéticos A/B e pelas salas restauradas (tabela do Entregável 4 do `RELATORIO-POC-PLUGIN-MOODLE.md`), e 5
delas (CL-04, CL-05, X-DEP-01, X-DEP-03, X-INT-03) só no caminho conforme. Não existe teste de que a auditoria não
modifica o curso, nem teste de capacidade negada, nem teste da página, nem teste do comparador.

## 4. Capacidades: o que existe, onde, como, evidência, limitações

| Capacidade | Estado | Onde / como | Evidência | Limitações |
|---|---|---|---|---|
| Auditoria de uma sala por `courseid` | **Implementado com testes insuficientes** | `engine::audit()` → `snapshot::build()` → `rules::catalog()`; CLI `cli/audit.php`; página `index.php?courseid=N` | `plugin-results/curso55_restaurado.*`, `curso670_restaurado.*`, `cursoA.*`, `cursoB.*` (A: 48 CONFORME; B: 25 NC + 5 ATENCAO); 0,08–0,24 s | sem persistência; sem histórico; a página exige digitar o id |
| Motor de regras com 6 estados e isolamento de erro | **Implementado e testado** | `engine::run_rules()`; exceção → `ERRO` da regra | `test_policy_override_and_isolation` | uma classe única com 29 métodos; sem metadados de regra além de título/origem/classe/gravidade |
| Política institucional fora do código | **Implementado com testes insuficientes** | `policy::defaults()` + setting `local_mooc_audit/policyjson` + `--policy` | teste de sobrescrita; A/B usam padrão | `array_merge` raso (sobrescrever `required_blocks` substitui a estrutura inteira); `policy_error` (JSON inválido) é gravado na política mas **não aparece no relatório** |
| Relatório JSON | **Implementado com testes insuficientes** | `engine::audit()` devolve array; CLI `--json`/`--out`; página `&format=json` | todos os `.json` em `plugin-results/` | contrato não versionado formalmente (campo `engine` carrega a versão do motor) |
| Relatório texto | **Implementado com testes insuficientes** | `engine::render_text()` (não conformes primeiro, conformes resumidos) | todos os `.txt` | strings fixas em português |
| Página web | **Parcialmente implementado** | `index.php`: `require_login`, `require_capability('local/mooc_audit:audit', contexto do curso)`, tabela única ordenada por estado | `plugin-results/screenshot-index-cursoB.jpg`; relato de redirecionamento sem login | contexto da página é `system` (não aparece na navegação do curso); sem agrupamento por categoria/gravidade; evidência truncada em 300 caracteres; sem links para o recurso/configuração; texto fixo; sem botão "reexecutar" além de reenviar o formulário |
| Controle de acesso | **Implementado com testes insuficientes** | `db/access.php`; `require_capability` na página | observado manualmente (falha "capability not found" antes do upgrade comprovou o uso do mecanismo nativo) | sem teste de negação; CLI roda como admin (`set_user(get_admin())`) |
| Visão de um usuário de teste (`uservisible`) | **Implementado com testes insuficientes** | `snapshot::build($courseid, $userid)`; `--userid` | B com estudante de teste (X-CERT-01, X-STR-01) | só por CLI |
| Comparador origem × destino | **Não validado (experimental)** | `comparator::compare()`; `cli/compare.php`; capacidade `:compare` | `compare_A_importado.*`, `compare_B_importado.txt` (12/13 e 20/21 casados; "Avisos" ambíguo) | sem testes; expressão `problematic` sempre verdadeira (`|| true`, defeito); só CLI; sem capacidade na página |
| Cursos sintéticos conformes/inconsistentes | **Implementado (laboratório)** | `cli/make_fixtures.php` | A/B criados e auditados | não é fixture PHPUnit; depende de `mod_game`/`mod_simplecertificate` instalados |
| Experimento de importação | **Implementado (laboratório)** | `cli/make_import.php` | 8→10/11, 9→12 | apaga curso homônimo; só laboratório |
| Testes PHPUnit | **Parcial** | `tests/engine_test.php` | OK 3/12 em 2026-10-28 | ver §3 |
| Navegação administrativa do curso | **Planejado** | — | — | nenhum `lib.php` com `local_mooc_audit_extend_navigation_course` |
| Relatório por categoria e gravidade | **Planejado** (parcial no texto) | `render_text` ordena por estado+gravidade; página só por estado | — | sem categoria |
| Links para recurso/configuração | **Planejado** | `cmid` está no finding mas não é usado para links | — | — |
| Histórico persistido | **Planejado** | `RELATORIO-POC-PLUGIN-MOODLE.md` propõe tabela `local_mooc_audit_run` | — | nenhuma tabela, nenhum `db/install.xml` |
| Auditoria em lote | **Planejado** | proposta de tarefa ad hoc | — | — |
| Web Service de leitura | **Planejado** | proposta `local_mooc_audit_get_report` | — | nenhum `db/services.php` |
| Correção assistida | **Planejado** | — | — | — |
| Emissão real de certificado / funcionamento de H5P e jogos | **Não validado** | fora do alcance de leitura | — | exige usuário de teste e navegador |

## 5. Divergências entre documentação e implementação (registradas)

| # | Onde | Divergência | Efeito |
|---|---|---|---|
| D1 | `README.md` da raiz, seção "Estado" | ainda recomenda "motor PHP/Laravel como pacote interno do MOOC Admin"; a decisão vigente (PoC) é o plugin autônomo | leitor novo é induzido à arquitetura abandonada |
| D2 | `RELATORIO-AUDITORIA-VIA-API-MOODLE.md` §4 e §9 | conclui "não propor `local_mooc_audit` na primeira versão"; superado pelo `RELATORIO-POC-PLUGIN-MOODLE.md` | documento histórico; deve ser lido como tal |
| D3 | `RELATORIO-POC-PLUGIN-MOODLE.md` Entregável 2 | "≈2 300 linhas" | contagem real: 2 971 (2 525 sem os scripts de laboratório) |
| D4 | `RELATORIO-POC-PLUGIN-MOODLE.md` Entregável 4 | cita "CL-15/16 restrições" como validadas | o plugin implementa só CL-16; CL-15 não existe no catálogo do plugin |
| D5 | ids entre protótipos | X-AV-04 (excluir vazias) está embutido em CL-12; "nomes genéricos de seção" saiu de X-STR-01 e entrou em CL-20 no plugin | ids iguais com conteúdo diferente entre `poc/mbz_inspect.py` e `rules.php`; a comparação um-a-um prometida é aproximada |
| D6 | `plugin/local_mooc_audit/README.md` "Página mínima" | não menciona que a página não está na navegação do curso nem que tem strings fixas | expectativa de uso administrativo não atendida |
| D7 | `comparator.php` linha 159 | `'problematic' => (bool) array_filter(..., fn($d) => ... || true)` | campo sempre verdadeiro; o relatório da comparação não distingue mudanças esperadas (textos) de problemáticas |
| D8 | `policy.php` | `policy_error` definido e nunca exibido | política inválida no admin passa despercebida (os padrões continuam valendo, silenciosamente) |
| D9 | `db/access.php` | `riskbitmask RISK_PERSONAL` para uma capacidade de leitura de configuração | conservador, mas incoerente com o `privacy/provider` que declara não haver dados pessoais; revisar na especificação de acesso |
| D10 | `lang/*` vs `index.php`/`engine.php` | strings de interface fixas em português | viola a convenção Moodle e a constituição (seção de restrições) |

## 6. Limitações permanentes (confirmadas nesta leitura)

- Regras por nome canônico (CL-09, CL-11, CL-16, CL-22, CL-23) dependem da decisão institucional 10, pendente; cursos antigos geram `NAO_CONFORME` legítimo mas ruidoso.
- Versões locais de `mod_simplecertificate` (2024051100) e `availability_days` (2016060100) diferem das institucionais (2024051105 e desconhecida).
- O plugin não vê o pacote `.mbz` (X-INT-01/04 só existem no protótipo Python).
- Emissão de certificado, renderização de H5P/jogos e conclusão efetiva continuam exigindo usuário de teste.
- Bloco "Acessibilidade": no laboratório não há bloco de site; a leitura de blocos herdados foi implementada (`block_manager`) mas só validada com blocos de curso.

## 7. Resposta direta: o que temos funcionando hoje

Uma auditoria somente leitura, por `courseid`, de 29 regras, em menos de 0,3 s, com 6 estados, política
parametrizável, saída JSON e texto, página mínima protegida por capacidade, visão de usuário de teste por CLI, e um
comparador experimental. Validada manualmente em 2 cursos institucionais restaurados e 2 cursos sintéticos, com 3
testes automatizados que cobrem 4 regras e o motor. Nada disso está em homologação ou produção.
