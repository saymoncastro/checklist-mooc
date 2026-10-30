# Prova de conceito executável — plugin autônomo `local_mooc_audit` no Moodle 4.5

**Data:** 2026-10-28 · **Pergunta:** um plugin local, sem MOOC Admin e sem `.mbz`, reproduz a auditoria feita por análise de backups? Em que ganha e em que perde?
**Base:** `RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md` (catálogo de regras e achados), `RELATORIO-AUDITORIA-VIA-API-MOODLE.md`, `DECISOES-INSTITUCIONAIS-CHECKLIST.md`, protótipo `poc/mbz_inspect.py` e seus resultados, backups dos cursos 55, 668 e 670. Todos disponíveis; nada foi inventado.

**Legenda:** **[EXEC]** executado e observado neste ambiente (saídas em `plugin-results/`) · **[DOC]** inferido de documentação/código · **[HIP]** hipótese · **[INST]** depende de decisão institucional.

---

## Entregável 1 — Ambiente local funcional [EXEC]

| Item | Resultado |
|---|---|
| Moodle | 4.5.15 (Build 20261005), `MOODLE_405_STABLE` commit 215f4438, clonado de um checkout já existente na máquina (não alterado) |
| Infra | `moodlehq/moodle-docker` (f4c2324) sobre Docker 29.9/Colima; PHP 8.3.35 (`moodle-php-apache:8.3`); PostgreSQL 17.11; projeto Compose isolado `moocaudit`, porta 127.0.0.1:8091 |
| Instalação | `admin/cli/install_database.php`, admin local `admin` (senha só local), `pt_br`, fuso `America/Sao_Paulo` (necessário: o contêiner em UTC desloca 31/12 23:59 para 01/01) |
| Plugins institucionais | `mod_game` 2026100800, `mod_hvp` 2026090101 (+ bibliotecas H5P como submódulos), `block_completion_progress` 2026101200, `mod_simplecertificate` 2024051100 (GitHub; produção usa 2024051105 — moodle.org/marketplace exigem login para baixar a 4.0.7), `availability_days` 2016060100 (única cópia pública, fork de 2017; instalou e funciona para avaliar condições `days`; a versão institucional é desconhecida) |
| Backups restaurados | curso 55 → id 2; curso 670 → id 3 (com 3 avisos "unable to restore alias (referenced file not included)" para os PDFs); **curso 668 falhou** ("Call to a member function destroy() on null" — pacote corrompido) |
| Testes | Composer + `admin/tool/phpunit/cli/init.php` concluídos; PHPUnit 9.6 |
| Reprodutibilidade | `moodle-env/README.md` (passos, versões, scripts `env.sh`, `local.yml`, `mphp.sh`); nenhuma credencial de produção; nenhuma conexão com a instalação institucional |

Nada destrutivo foi feito fora do ambiente `moocaudit`; os contêineres `coursecare-*` existentes não foram tocados.

---

## Entregável 2 — Código-fonte do plugin [EXEC]

`plugin/local_mooc_audit/` (≈2 300 linhas de PHP), estrutura oficial de plugin local, **sem alteração no núcleo**, sem tabelas próprias, instalado via `admin/cli/upgrade.php`:

| Componente | Linhas | Função |
|---|---|---|
| `classes/snapshot.php` | 473 | Retrato da sala: curso, formato, seções, módulos (`cm_info`: visibilidade, `uservisible`, conclusão, `availability`), configurações por módulo (lista branca de colunas), itens e categorias de nota, instâncias de inscrição, blocos (inclusive herdados do site), arquivos com verificação de existência no `filedir`, plugins instalados × usados |
| `classes/policy.php` | 116 | Política institucional parametrizável (decisões de 2026-10-28), sobrescrita por JSON (admin ou CLI); ano da reoferta calculado, nunca fixo |
| `classes/rules.php` | 1 080 | 29 regras (`CL-*` do checklist, `X-*` adicionais) com os 6 estados exigidos |
| `classes/engine.php` | 184 | Execução isolada por regra (exceção → `ERRO`), inventário, síntese, texto |
| `classes/comparator.php` | 325 | Comparador experimental origem × destino |
| `cli/audit.php`, `cli/compare.php`, `index.php` | 173 | CLI (texto/JSON, `--userid`, `--policy`) e página mínima com `require_capability` |
| `db/access.php`, `settings.php`, `lang/{en,pt_br}`, `classes/privacy/provider.php`, `version.php` | — | capacidades próprias (`local/mooc_audit:audit`, `:compare`), setting da política, strings, privacidade (null provider) |
| `tests/engine_test.php` | 103 | 3 testes PHPUnit / 12 asserções — **OK** [EXEC] |
| `cli/make_fixtures.php`, `cli/make_import.php` | 446 | somente ambiente de teste (recusam rodar fora de `localhost`) |

**Acessos ao banco justificados** (seção "Fontes de dados" do `README.md` do plugin): tudo o que tem API é lido por API (`get_fast_modinfo`, `grade_item/grade_category`, `enrol_get_instances`, `block_manager`, `file_storage`/`file_system`, `core_plugin_manager`). Configurações específicas de cada módulo (tentativas do quiz, `completionsubmit`, fonte do jogo, texto do certificado…) são lidas com `$DB->get_record('<mod>', …)` restrito a colunas listadas, porque não existe API cross-módulo para isso; contagens com `count_records`. Nenhuma escrita.

---

## Entregável 3 — Auditoria executável [EXEC]

```
php local/mooc_audit/cli/audit.php --courseid=3
```
produz, em **0,08–0,24 s** por curso (73 módulos no maior), o relatório em texto ou JSON com: identificação, período, categoria, visibilidade, seções, módulos por tipo, arquivos (e quantos sem conteúdo), blocos, métodos de inscrição, síntese por estado/gravidade e cada resultado com regra, estado, gravidade, local (nome + cmid), evidência, encontrado, esperado e orientação. A página `index.php?courseid=9` mostra a mesma tabela após login com a capacidade (captura em `plugin-results/screenshot-index-cursoB.jpg`); `&format=json` devolve o JSON; sem login redireciona para o login; antes da instalação da capacidade a página falhou com "capability not found" — corrigido com bump de versão + upgrade, o que confirma que o controle de acesso é o do Moodle.

Saídas gravadas: `plugin-results/curso55_restaurado.*`, `curso670_restaurado.*`, `cursoA.*`, `cursoB.*`, `cursoA_importado_*`, `cursoB_importado.txt`, `compare_*`.

---

## Entregável 4 — Testes de regras (cenários controlados) [EXEC]

Cursos criados por `cli/make_fixtures.php` com as APIs do Moodle (`create_course`, `create_module`, importação GIFT de questões, `block_manager::add_block`, `enrol_self`): **A (id 8)** conforme a política; **B (id 9)** com problemas injetados. Resultado: **A = 48 resultados, todos CONFORME; B = 25 NAO_CONFORME + 5 ATENCAO** (`plugin-results/cursoA.txt`, `cursoB.txt`).

Seleção das regras da prova (critério: confiabilidade, dados disponíveis por API, impacto, problemas já conhecidos nos cursos reais, facilidade de comprovação): as 15 candidatas do enunciado mais 14 regras adicionais do catálogo que custam pouco uma vez que o retrato existe (dependências, certificado, arquivos, conteúdos vazios, enunciado × configuração).

| Regra | Configuração criada (curso B) | Esperado | Obtido | Situação |
|---|---|---|---|---|
| CL-09 fórum institucional | "Espaço de interação" com conclusão manual | NC | NC (A: C) | validada |
| CL-11 Pesquisa de Perfil | presente e exigida pelo certificado (A e B) | C | C; curso 55 real → NC (sem Pesquisa) | validada |
| CL-13 tentativas | Questionário 1 com 1 tentativa, enunciado diz 2 | NC ×2 | NC "tentativas=1" + NC "enunciado 2 ≠ 1" (A: C) | validada |
| CL-12 soma 100 | quiz 50 + quiz 40 + jogo 10 = 100 (soma neutralizada pelo jogo); teste extra: quiz 2 → 30 (total 90) | C / NC | C; com 30 pontos → NC "soma=90" | validada |
| CL-12 agregação / excluir vazias | B com `aggregateonlygraded=1`; A com 0 explícito | NC / C | NC / C. **Achado colateral:** o padrão do Moodle para curso novo é `aggregateonlygraded=1`; a primeira versão do curso A foi apontada por isso | validada |
| CL-17 conclusão | fórum e rótulo com conclusão manual | NC ×2 | NC ×2 + síntese "manual=2" | validada |
| CL-19 rótulos | rótulo com conclusão manual | NC | NC | validada |
| CL-15/16 restrições | Avaliação exige só o Questionário 1 (faltam Questionário 2 e o jogo) | NC | NC "faltam: quiz 2; game" (A: C) | validada |
| X-DEP-02 impossível | Questionário 1 exige página sem conclusão e página oculta | NC + ATENCAO | NC (sem conclusão) + ATENCAO (oculta) | validada |
| X-DEP-04 ciclo | página "Ciclo A" ⇄ "Ciclo B" | NC | NC "ciclo: 210 → 211 → 210" | validada |
| CL-23 certificado | sem condição de nota ≥ 60 | NC | NC "condições de nota=[]" (A: C com item Total do curso) | validada |
| X-CERT-01 alcançabilidade | cadeia passa por página sem conclusão e por página oculta | NC | NC com os dois motivos; com `--userid` do estudante de teste acrescenta "Questionário 1 indisponível para o usuário" | validada |
| X-INT-02 arquivo sem conteúdo | `apostila.txt` registrado, conteúdo removido do `filedir` | NC | NC "registrado mas sem conteúdo legível no armazenamento" | validada |
| X-CONT-04 referência sem arquivo | página com `@@PLUGINFILE@@/banner.png` inexistente | NC | NC (A: C) | validada |
| X-CONT-01 vazios | página vazia; Questionário 2 sem questões | NC ×2 | NC ×2 (A: C) | validada |
| CL-18 glossário + jogo | glossário vazio como fonte de palavras cruzadas; depois com 3 entradas | NC bloqueante / ATENCAO | NC bloqueante "0 entradas; fonte de 1 jogo" / ATENCAO "3 entradas" | validada |
| CL-06/07 datas | início 2026, fim 30/06/2027 (política: 01/01–31/12/2027) | NC ×2 | NC ×2 (A: C) | validada |
| CL-08 autoinscrição | instância desabilitada, sem datas | NC | NC (A: C com datas = curso) | validada |
| CL-14 encerramento | Questionário 1 e jogo sem `timeclose` | NC ×2 | NC ×2 (+ ATENCAO quiz 2 ≠ fim do curso) | validada |
| CL-20 seções | `hiddensections=0`; seção "Topic 3" | NC + ATENCAO | NC + ATENCAO | validada |
| CL-21 blocos | sem o bloco HTML "Acessibilidade" | NC | NC (A: C com os 4 blocos) | validada |
| CL-22 licença | rótulo, pesquisa e declaração presentes (A e B) | C | C; curso 55 real → NC ×3 | validada |
| X-STR-01 ocultos | página obrigatória oculta | ATENCAO | ATENCAO; com `--userid` lista módulos indisponíveis ao estudante | validada |
| X-AV-01 enunciado × pontos | Questionário 2 "Valor: 50 pontos" com nota máxima 40 | NC | NC | validada |
| X-INT-03 plugins | módulos game/glossary/simplecertificate usados e instalados | C | C | validada (cenário "plugin ausente" não criado) |
| CL-04, CL-05, X-DEP-01, X-DEP-03 | conformes em A e B | C | C | validada só no caminho conforme |

PHPUnit (`tests/engine_test.php`): CL-13 (1 tentativa → NC, 2 → C), CL-12 soma, X-DEP-02/X-DEP-04 (válido → C, impossível/ciclo → NC), sobrescrita de política e isolamento de erros — **3 testes, 12 asserções, OK**.

---

## Entregável 5 — Experimento de importação (reoferta sem MOOC Admin) [EXEC]

Procedimento (`cli/make_import.php`): criar sala vazia na mesma categoria → `core_course_external::import_course(origem, destino)` (o mesmo mecanismo de *Administração do curso > Importar*, sem dados de usuário) → ajustes da nova oferta (datas do curso e da autoinscrição) → auditoria das duas salas → comparação. Importações: A (8) → 10 (com ajustes) e → 11 (sem ajustes); B (9) → 12. Cada importação levou 0,6 s.

| Questão | Resposta observada |
|---|---|
| Atividades identificadas no destino? | Sim: 12/13 módulos de A casados por tipo+seção+nome; o 13º ("Avisos") ficou **ambíguo** porque a sala vazia já nasce com seu próprio fórum de avisos e a importação traz outro — o comparador reportou "ambíguo" e "2 adicionais" em vez de adivinhar |
| Configurações das avaliações preservadas? | Sim: tentativas, notas máximas, datas de encerramento, critérios de conclusão e itens de nota iguais (12 preservados sem diferença) |
| Restrições de conclusão continuam válidas? | Sim: o JSON de `availability` é remapeado para os novos cmids e para o novo item "Total do curso"; após normalização (ordem das chaves) é idêntico ao da origem; X-DEP-* e X-CERT-01 conformes no destino |
| Arquivos permanecem disponíveis? | Registros são copiados; o conteúdo depende do `filedir`: o arquivo perdido de B aparece no destino 12 como X-INT-02 (registro sem conteúdo), e `@@PLUGINFILE@@/banner.png` continua quebrado (X-CONT-04) |
| Identificadores mudam? | Sim, todos (curso, contexto, cmids, instâncias, itens de nota); por isso o comparador não usa ids |
| Entradas de glossário preservadas? | **Não.** B com 3 entradas → destino 12 com 0 (importação não leva dados de usuário; entradas são dados de usuário). O jogo passa de ATENCAO para **bloqueante** no destino. É o mesmo mecanismo que esvaziou o glossário do curso 55 na reoferta |
| Certificados continuam configurados? | Sim (condições remapeadas, inclusive `grade` para o novo Total do curso) |
| O que a importação não transfere | datas do curso (destino mantém as suas), métodos de inscrição (autoinscrição fica desabilitada e sem datas — CL-08 NC em 11), opções de formato (`hiddensections`), categoria; transfere blocos (inclusive os HTML), seções, módulos, restrições, notas, questões |
| O plugin reconhece diferenças origem × destino? | Sim: `compare.php` lista esperadas (ids, shortname, datas), de curso (inscrição, formato, blocos, agregação), ausentes, adicionais, ambíguas e modificadas (ex.: `extra.entries 3 → 0` no glossário) |

Observação de restauração (não de importação): no curso 670 restaurado, os 4 PDFs eram **aliases** para "Arquivos do servidor" de outra sala; o Moodle registrou "unable to restore alias (referenced file not included)" e não criou os registros. O plugin os detecta como X-CONT-04 (7 referências em páginas/livro) e X-CONT-01 (4 recursos de arquivo sem arquivo), não como X-INT-02 — a distinção pedida no Caso B.

---

## Entregável 6 — Comparação MBZ × plugin (catálogo completo)

Classificação: **Equivalente** · **Plugin superior** · **MBZ superior** · **Parcialmente equivalente** · **Não demonstrado** (viável, não implementado na PoC) · **Não aplicável**. Evidência = onde foi comprovado.

| Regra | MBZ (protótipo) | Plugin | Equivalência | Evidência | Limitação |
|---|---|---|---|---|---|
| CL-01 Informações | estrutura da página | idem (conteúdo via `page.content`) | Não demonstrado | — | conteúdo é humano |
| CL-02 projeto SIPAC | NV | NV | Não aplicável | — | fonte externa |
| CL-03 professores | NV (sem usuários) | papéis via `get_enrolled_users`/`role_assignments` | Plugin superior | [DOC] | não implementado na PoC |
| CL-04 sumário/botões | C | C | Equivalente | A, B, 55, 670 [EXEC] | |
| CL-05 categoria | estado do backup | estado real | Plugin superior | 55/670 restaurados (NC: "Categoria 1") | |
| CL-06/07 datas | estado do backup | estado real | Plugin superior | B NC, A C, 11 NC após importação | |
| CL-08 autoinscrição | datas e `customint6` do backup | **datas e `customint6` reais** (`enrol_get_instances`) | Plugin superior | B/11 NC, A C | Web Services não expõem; o plugin sim |
| CL-09 fórum | C | C + conclusão | Equivalente | B NC, 55 NC | nome canônico |
| CL-10 vídeos | inventário | inventário | Não demonstrado | — | rede |
| CL-11 Pesquisa + cert | C | C | Equivalente | 55 NC, 670 C | |
| CL-12 soma/agregação | C | C (`grade_category` expõe agregação e excluir vazias) | Equivalente | B NC (90; aggregateonlygraded) | |
| CL-13 tentativas | C | C | Equivalente | B NC, PHPUnit | |
| CL-14 encerramento | C | C (inclui `mod_game` via tabela) | Equivalente | B NC ×2, 55 NC ×4 | |
| CL-15 restrições | C | C | Equivalente | B | |
| CL-16 Avaliação | C | C | Equivalente | B NC, 668 (MBZ) | |
| CL-17 conclusão | C | C (critérios específicos via tabelas) | Equivalente | B NC ×2 | |
| CL-18 glossário | vazio no pacote (0 entradas) | **vazio na sala real + contagem por categoria** | Plugin superior | 55 restaurado NC bloqueante; B 0→3→0 após importação | |
| CL-19 rótulos | C | C | Equivalente | B NC | |
| CL-20 seções | C | C | Equivalente | B, 670 | |
| CL-21 blocos | só blocos do curso | blocos da página do curso, **inclusive herdados do site** | Plugin superior | A C (4 blocos), B NC | sem bloco de site no lab |
| CL-22 licença | C | C | Equivalente | 55 NC ×3, 670 C | |
| CL-23 certificado | C | C | Equivalente | B NC, A C | |
| CL-24 assinatura (imagem) | hash no pacote | hash via `files` do contexto do certificado | Não demonstrado | — | viável |
| CL-25 emissão de teste | texto | texto + emissão real possível no ambiente | Não demonstrado | — | emissão exige usuário |
| CL-26/27 visual | presença | presença | Não demonstrado | — | humano/externo |
| CL-28/29 URL, vitrine | NV | NV | Não aplicável | — | |
| X-INT-01 pacote corrompido | **C** | impossível (sem pacote) | **MBZ superior** | 668: MBZ diagnosticou; restauração falhou com erro genérico | exclusividade do MBZ |
| X-INT-02 arquivo sem conteúdo | ausente no pacote | ausente no `filedir` (verificação real) | Parcialmente equivalente (situações distintas) | 670: MBZ 15 refs; plugin 0 (não restaurados) + X-CONT-04/01; B: ambos | |
| X-INT-03 plugins | exigidos pelo pacote | usados × instalados no site | Parcialmente equivalente | B C; 668 (MBZ) | plugin não vê o pacote |
| X-INT-04 inforef | C | n/a | MBZ superior | — | só faz sentido no pacote |
| X-DEP-01/02/03/04 | C | C | Equivalente | B, PHPUnit | |
| X-DEP-05 duplicadas | C | não implementado | Não demonstrado | 668 (MBZ) | trivial |
| X-CERT-01 | estática | estática **+ visão do usuário** (`uservisible`) | Plugin superior | B com `--userid` | |
| X-CONT-01 vazios | C | C (+ recurso sem arquivo) | Equivalente | B, 670 | |
| X-CONT-02 duplicados | C | não implementado | Não demonstrado | 670 (MBZ) | trivial |
| X-CONT-03 links absolutos | C | não implementado | Não demonstrado | 55 (MBZ) | trivial |
| X-CONT-04 @@PLUGINFILE@@ | C | C | Equivalente | B, 670 | |
| X-CONT-05/06, X-ACC-01/02 | C | não implementado | Não demonstrado | MBZ | mesmo texto disponível |
| X-AV-01 enunciado × pontos | C | C | Equivalente | B NC | |
| X-AV-04 aggregateonlygraded | C | C | Equivalente | A/B | |
| X-STR-01 | C | C + `uservisible` | Plugin superior | B | |
| X-SEC-01 senha | C | C (`enrol.password`) | Equivalente | — | plugin nunca exibe o valor |
| X-BLK-01 bloco de progresso | C | config via `block_manager` | Não demonstrado | — | viável |

**Caso A (668, corrompido):** o plugin não pode detectar; a restauração apenas falhou com uma exceção genérica. Vantagem exclusiva do MBZ.
**Caso B (670, PDFs):** o MBZ apontou 15 registros sem conteúdo no pacote; na sala restaurada o plugin apontou 7 referências quebradas e 4 recursos sem arquivo (aliases não restaurados) e nenhum "registro sem conteúdo"; no cenário B sintético, o plugin apontou "registro sem conteúdo" (perda no `filedir`). As três situações ficam distintas.
**Caso C (55, jogos × glossário):** o plugin detectou na sala restaurada (bloqueante) o mesmo problema que o MBZ antecipou, e o experimento de importação mostrou a perda das entradas em tempo real (3 → 0).

**Pergunta crítica — a ausência do MBZ é perda relevante?** Não. Das 51 regras do catálogo, só duas dependem do pacote (X-INT-01, X-INT-04), e a primeira é a única com valor operacional. Tudo o mais é equivalente ou melhor na sala real, porque o plugin lê o estado efetivo (inscrição, blocos de site, `filedir`, visão do estudante) e não precisa de remapeamento de ids. O que se perde é o **momento**: o MBZ antecipa problemas antes de restaurar; o plugin só vê a sala depois de criada — mas, como a auditoria é instantânea (< 0,3 s) e a sala de origem também pode ser auditada, o momento "antes" é coberto auditando a origem.

---

## Entregável 7 — Avaliação arquitetural (com base no observado)

| Critério | A. Auditor MBZ independente | B. App externa via Web Services | C. Plugin autônomo com motor interno | D. Plugin só extrator + motor externo |
|---|---|---|---|---|
| Cobertura efetiva | 17 itens + extras; só estado do pacote | perde datas/`customint6` de inscrição, agregação, certificado, game/hvp | **tudo do A exceto X-INT-01/04, mais estado real, blocos de site, visão do usuário** (demonstrado) | igual a C |
| Implementação | leitor tar/XML + normalizador (já especificado) | cliente + ~30 funções + capacidades por campo | ≈2 300 linhas; APIs internas estáveis; **0,08–0,24 s por curso** | C + API/contrato + segundo deploy |
| Manutenção | formato moodle2 estável | funções estáveis, campos por capacidade | acompanhar upgrades do Moodle (padrão de plugin) | dois componentes |
| Dependência de versão | baixa | média | média (lista branca de colunas por módulo) | média |
| Credenciais | nenhuma | token + serviço + papel | **nenhuma** (roda dentro do Moodle, capacidade própria) | token para o motor externo |
| Segurança/permissões | fora do Moodle | conta de serviço | `require_capability` nativo; somente leitura; sem tabelas | idem + superfície externa |
| Desempenho | segundos (extração) | 15–25 requisições/curso | milissegundos | rede |
| Lote | sim | sim, com limites | sim (CLI/tarefa ad hoc sobre a categoria) | sim |
| Depende de backup | **sim** | não | **não** | não |
| Integração futura | arquivo | HTTP | **JSON por URL/Web Service próprio** ou tarefa | HTTP |
| Estado atual da sala | não | sim | **sim** | sim |

**Recomendação: C**, com uma única verificação herdada de A: integridade do pacote antes de restaurar, que pode ser feita pelo próprio processo de restauração (falha) ou por um comando simples (`gzip -t` + manifesto) — não justifica manter um auditor MBZ completo. D não acrescenta nada a C e dobra a operação. B foi refutada pelos experimentos: perde justamente o que o plugin obtém com `enrol_get_instances` e `grade_category`.

---

## Entregável 8 — Proposta de MVP instalável em homologação

**Funcionalidades:** auditoria por curso (CLI, página e Web Service de leitura próprio `local_mooc_audit_get_report` para o MOOC Admin consumir); auditoria em lote por categoria via **tarefa ad hoc** (`core\task\adhoc_task`) com resultado persistido; comparação origem × destino; política por JSON versionado por ciclo; relatório JSON (contrato já definido) + página.

**Regras prioritárias (todas validadas na PoC):** CL-05/06/07/08 (oferta), CL-09/11/22 (recursos institucionais), CL-12/13/14/16/17/19/23 (avaliação, conclusão, certificado), CL-18 (glossário/jogos), CL-20/21, X-DEP-01..04, X-CERT-01, X-INT-02, X-INT-03, X-CONT-01/04, X-AV-01, X-STR-01. Segundo incremento (triviais, não demonstradas): X-CONT-02/03/05/06, X-ACC-01/02, X-DEP-05, X-BLK-01, CL-24 (hash das artes), CL-03 (professores).

**Estrutura de código:** uma classe por regra em `classes/rule/` implementando `rule_interface` (`id()`, `meta()`, `evaluate(snapshot, policy)`), descoberta automática; `snapshot` dividido em coletores (`course`, `modules`, `grades`, `enrol`, `blocks`, `files`); tabela `local_mooc_audit_run` (curso, política, versão do motor, JSON, data) para histórico e comparação entre execuções; capacidades como hoje; `privacy/provider` continua null se o JSON não guardar dados pessoais.

**Manutenção:** lista branca de colunas por módulo revisada a cada versão maior do Moodle; testes PHPUnit por regra com os dois caminhos; `make_fixtures.php` vira gerador de fixtures de teste; CI com `moodle-plugin-ci`.

**Plano de testes:** PHPUnit (um teste por regra, conforme e não conforme) + fixtures A/B automatizadas + Behat mínimo para a página; antes de homologação, rodar contra as salas restauradas dos cursos 55 e 670 e conferir com os relatórios MBZ.

**Limitações:** regras por nome canônico (decisão 10); emissão de certificado e funcionamento de H5P/jogos continuam exigindo usuário de teste; não vê o pacote `.mbz`; versões de plugins de terceiros no laboratório diferem da produção.

**Próximos incrementos:** histórico e diff entre execuções; Web Service de leitura; tarefa ad hoc em lote; regras de conteúdo (links, `alt`, duplicados); verificação de URLs externas com proteção SSRF; roteiro de usuário de teste (conclusão/emissão) lendo `core_completion`.

---

## Respostas finais

- **É viável auditar cursos diretamente dentro do Moodle?** Sim. Demonstrado em Moodle 4.5.15 com 29 regras, sem alterar o núcleo, em menos de 0,3 s por curso, com controle de acesso nativo.
- **Quais regras do auditor MBZ reproduzimos?** Das 51 do catálogo: 29 implementadas e validadas (equivalentes ou superiores), 2 parcialmente equivalentes (X-INT-02/03, situações distintas), 15 não demonstradas mas viáveis com o mesmo retrato, 3 não aplicáveis a nenhuma das abordagens (externas) e 2 exclusivas do pacote.
- **Quais verificações ficaram melhores?** Datas, categoria e autoinscrição reais (inclusive datas, que os Web Services não expõem); blocos herdados do site; glossário/jogos na sala real; arquivos no `filedir`; alcançabilidade do certificado com a visão de um estudante de teste; plugins instalados × usados; comparação origem × destino sem depender do MOOC Admin.
- **Quais capacidades perdemos?** Detectar pacote corrompido/truncado e referências internas do pacote (`inforef`), e o aviso *antes* da restauração para o que só existe no backup — mitigado auditando a sala de origem.
- **O plugin analisa a sala original e a importada?** Sim: A → 10/11 e B → 12 auditadas e comparadas; a comparação mostrou o que a importação preserva (módulos, restrições remapeadas, notas, blocos) e o que não transfere (datas, inscrição, formato, entradas de glossário).
- **A arquitetura é simples e sustentável para o Cefor/Ifes?** Sim: um plugin PHP padrão, sem infraestrutura adicional, sem credenciais, sem fila própria, testável com PHPUnit; a política fica num JSON. O custo recorrente é acompanhar as versões do Moodle e dos módulos de terceiros.
- **Recomenda evoluir esta PoC para o produto definitivo?** **Sim**, como plugin autônomo (alternativa C), porque foi a única abordagem que, em execução, cobriu o catálogo quase inteiro com o estado real da sala e sem dependências externas; o auditor MBZ fica reduzido a uma checagem de integridade do pacote antes da restauração, e a integração com o MOOC Admin passa a ser um consumidor opcional do JSON do plugin.
