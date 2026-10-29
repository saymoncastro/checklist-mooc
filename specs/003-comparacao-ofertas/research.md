# Research: Comparação entre Ofertas MOOC

**Data**: 2026-10-29 · **Base**: `main` 82d6608 (plugin 0.3.0) · **Spec**: [spec.md](spec.md)

## R0 — Avaliação do comparador experimental (`classes/comparator.php`, 360 linhas)

| Pergunta | Situação encontrada |
|---|---|
| O que compara | Campos do curso (todos os do snapshot; ids/sigla/datas/categoria como "esperados"); por par de módulos: `visible`, `visibleoncoursepage`, conclusão (`completion`, `completionview`, `completiongradeitemnumber`, `completionpassgrade`), `sectionnumber`, `extra` (exceto 4 chaves), restrições remapeadas, itens de nota (`itemnumber`, `gradetype`, `grademax`), arquivos (área/caminho/nome/tamanho), textos (md5); métodos de inscrição, blocos e agregação da categoria raiz |
| Como casa módulos | Laço guloso por módulo de origem: tipo+seção+nome; se não único, tipo+nome; se nenhum candidato, tipo+seção+posição. A unicidade só é verificada **no destino** e depende da ordem (`$used`) |
| Duplicatas | Candidatos > 1 → "ambíguo" (correto no caso do fórum "Avisos" da PoC); os candidatos viram também "adicionais", o que conta duas vezes a mesma coisa |
| Restrições | Remapeia `completion.cm` pelo mapa e `grade.id` (total do curso e itens de módulos casados); normaliza números em string e ordem de chaves; não trata condições de data (sempre mudam na reoferta), nem restrições de seção |
| Notas e arquivos | Itens de nota sem `gradepass`/`hidden`; arquivos sem distinguir registro ausente × conteúdo ausente no armazenamento × indisponível ao estudante |
| Configurações alteradas | Qualquer valor diferente em `extra`, sem classificar; `glossaryid` do jogo aparece como diferença (3 → 4) embora seja só o remapeamento para o glossário correspondente |
| Não representa | Renomeação; movimentação de seção (se o nome também casar em outra seção, funciona por tipo+nome, mas sem dizer que moveu); grupos de módulos idênticos; ambiguidade de origem; correspondência manual; relação com a auditoria; política por sala; dependência glossário → jogo; seções |
| Defeito `problematic` | `array_filter($diffs, fn($d) => !in_array(...) \|\| true)` → **sempre verdadeiro** para toda atividade modificada (confirmado pela leitura; nenhum teste cobria) |
| Testes | **Nenhum** teste do comparador (a suíte 0.3.0 tem 117 testes, nenhum toca `comparator`) |
| Preservável | Ideia dos três critérios de chave, remapeamento de restrições, normalização de árvore, comparação de inscrição/blocos/agregação, `render_text` como base da CLI, `cli/compare.php` |

**Decisão**: evoluir, não reescrever do zero: manter `comparator` como ponto de entrada e os algoritmos de chave,
remapeamento e normalização; extrair a correspondência e a classificação para classes testáveis (R2, R3) porque o
laço guloso não garante determinismo nem unicidade nos dois lados, e a classificação exige uma tabela explícita.
**Alternativas**: corrigir só a linha do `problematic` (não atende a ambiguidade/renomeação/relação com a auditoria);
reescrever tudo (descartaria a validação da PoC).

## R1 — Correção do `problematic`

**Decisão**: cada diferença recebe `kind ∈ {esperada, relevante, informativa}` por uma tabela de campos (R3); uma
atividade modificada tem `relevance` = o tipo mais grave presente e `problematic = (relevance === 'relevante')`. Um
par só com diferenças esperadas (ex.: datas de questionário deslocadas para o novo ano) é **preservado** com
diferenças esperadas, não "modificado".
**Testes**: par só com texto alterado → `problematic=false`; par com tentativas alteradas → `true`; par só com data
de encerramento deslocada → preservado.

## R2 — Algoritmo de correspondência

**Decisão**: passes globais sobre os conjuntos de módulos ainda sem par, cada um aceitando um par só quando a chave é
**única nos dois lados**:

1. `tipo|seção|nome` — confiança alta.
2. Dentro de grupos `tipo|seção|nome` com mais de um módulo: `impressão de conteúdo` única no grupo dos dois lados —
   alta (resolve versões em Libras com mesmo nome).
3. `tipo|nome` — alta; gera diferença informativa de seção (atividade movida).
4. Congelamento: grupos `tipo|nome` restantes com módulos dos dois lados e cardinalidade > 1 em algum lado viram
   **ambiguidades** (motivo: `duplicado_no_destino` se 1×n, `duplicado_na_origem` se n×1, `conteudo_equivalente` se
   todas as impressões iguais, `mesmo_nome` nos demais) e saem dos passes seguintes.
5. `tipo|impressão de conteúdo` (impressão não trivial) — média; diferença informativa de nome (renomeada).
6. `tipo|seção|posição na seção` — média (renomeada e editada).

Restantes da origem → ausentes; restantes do destino → adicionais. Correspondências manuais (R10) entram antes do
passe 1. Os passes iteram em ordem de `cmid` apenas para produzir saída estável; a decisão depende só das contagens,
por isso a ordem não muda o resultado (teste: embaralhar módulos → mesmo mapa).

**Impressão de conteúdo**: sha1 de tipo + textos normalizados (sem tags, espaços colapsados, `@@PLUGINFILE@@`
mantido) + lista ordenada de arquivos (área/caminho/nome/tamanho) + campos de conteúdo (`externalurl`, `slots`,
`items`, `chapters`, `machine_name`, `gamekind`, `sourcemodule`). Exclui configurações (datas, tentativas, conclusão)
e identificadores, para que uma configuração alterada não impeça a correspondência. Trivial (sem texto, arquivo nem
campo de conteúdo) → não usada no passe 5.

**Alternativas**: similaridade de nomes (Levenshtein) — heurística textual, violaria o princípio VI (no máximo
ATENÇÃO) e geraria pares arbitrários; algoritmo húngaro com pesos — ótimo global, mas opaco para a equipe e capaz de
"forçar" pares; `idnumber` do módulo — os cursos MOOC não o preenchem (verificado em 55/670).

## R3 — Classificação das diferenças

| Escopo/campo | Tipo | Código |
|---|---|---|
| ids de curso, contexto, módulo, instância, item de nota; referência remapeada ao par | esperada | `identifier_remapped` (só contado) |
| datas de módulo (`timeopen`, `timeclose`, `duedate`, `cutoffdate`, `allowsubmissionsfromdate`, `timeavailableto`, `completionexpected`) e condições de data em restrições | esperada | `date_shifted` |
| `fullname`, `shortname`, `startdate`, `enddate`, `category`, `visible` do curso; datas de inscrição; estado e novas inscrições da autoinscrição | esperada (avaliada pela política no destino) | `offer_setting` |
| papel de inscrição; estado de outros métodos (visitante etc.); menos instâncias de um método | relevante | `course_setting_changed` / `enrol_count_changed` |
| nome do módulo | informativa | `renamed` |
| seção / posição | informativa | `moved` |
| texto alterado (não vazio) | informativa | `text_changed` |
| texto esvaziado | relevante | `text_emptied` |
| opções de exibição (`display`, `numbering`, `printintro`…) | informativa | `display_changed` |
| `visible`, `visibleoncoursepage` do módulo | relevante | `visibility_changed` |
| conclusão (`completion*` do cm e do módulo) | relevante | `completion_changed` |
| restrição diferente após remapeamento / referência sem par / JSON ilegível | relevante | `availability_changed` / `availability_unmapped` / `availability_invalid` |
| itens de nota (`gradetype`, `grademax`, `gradepass`, `hidden`, quantidade) | relevante | `grade_item_changed` |
| avaliação (`attempts`, `grade`, `grademethod`, `sumgrades`, `timelimit`, `anonymous`, `type`, …) | relevante | `setting_changed` |
| contagens de conteúdo (`slots`, `items`, `entries`, `categories`, `chapters`, `glossary_entries_available`) diminuídas | relevante | `content_lost` (aumento: informativa `content_added`) |
| referência de jogo (`glossaryid`, `quizid`, `bookid`) apontando para o correspondente | esperada | `identifier_remapped`; sem correspondente → relevante `reference_unmapped` |
| arquivo sem registro no destino / conteúdo ausente no armazenamento / módulo com arquivos indisponível ao estudante | relevante | `file_record_missing` / `file_content_missing` / `file_unavailable` |
| arquivo novo no destino | informativa | `file_added` |
| instância ausente em um lado | relevante | `instance_missing` |
| curso: `format`, `enablecompletion`, `showcompletionconditions`, `lang`, `formatoptions` | relevante | `course_setting_changed` |
| curso: `summary` | informativa | `text_changed` |
| método de inscrição ausente no destino | relevante | `enrol_missing` |
| bloco ausente no destino / adicional | relevante / informativa | `block_missing` / `block_added` |
| agregação ou `aggregateonlygraded` da categoria raiz | relevante | `grade_aggregation_changed` |
| seção: nome / quantidade | informativa | `section_renamed` / `sections_reorganized` |
| seção: visibilidade, restrição | relevante | `section_visibility_changed` / `availability_changed` |
| atividade adicional avaliativa (com item de nota) ou com conclusão | relevante | `extra_graded` |
| atividade adicional sem nota nem conclusão | informativa | `extra_resource` |

Campos de módulo desconhecidos para a tabela caem em **relevante** (conservador, `setting_changed`), para que um
campo novo do snapshot nunca esconda uma diferença.

## R4 — Restrições de acesso

**Decisão**: remapear `completion.cm` e `grade.id` pelo mapa (como hoje, inclusive itens de módulos casados e total
do curso), aplicar também às restrições de **seções** (casadas por número), e normalizar condições de data
substituindo `t` por um marcador antes de comparar; se a única diferença for a data, a diferença é esperada
(`date_shifted`). Referência a módulo sem par → `availability_unmapped` (relevante), com o nome do módulo de origem.

## R5 — Arquivos

**Decisão**: comparar o multiconjunto `área|caminho|nome|tamanho` por par. Para cada arquivo da origem: ausente no
destino → `file_record_missing`; presente mas `readable === false` no destino (e legível na origem) →
`file_content_missing`; módulo com arquivos, visível na origem e oculto (cm ou seção) no destino →
`file_unavailable`. Fonte: snapshot (já distingue registro e legibilidade via `file_system`).

## R6 — Política por sala e regras do ciclo de oferta

**Decisão**: destino com `policy::load()` (idêntico à auditoria individual). Origem com
`policy::load(['reoffer_year' => ano da data de início da origem])`, ano calculado como no CL-06
(`userdate($startdate, '%Y', 99, false)`). Sem data de início → regras do ciclo da origem marcadas `sem_contexto` e
ignoradas na classificação. Regras do ciclo: `CL-05`, `CL-06`, `CL-07`, `CL-08`, `CL-14` (constante
`comparator::CYCLE_RULES`, com teste que impede divergência silenciosa). **Alternativa**: pedir ao usuário as duas
políticas — a política não tem versões por ano; registrado como [validar].

## R7 — Chave de local para relacionar auditorias

**Decisão**: `regra|m:<id do par>` para resultados com `cmid` (o par é o cmid de origem; no destino, o cmid de
origem correspondente); `regra|s:<número>` para resultados de seção; `regra|t:<local sem identificadores>` para os
demais (remove `(id N)`, `(cmid=N)`, dígitos de ids). Resultados com a mesma chave são agrupados; o estado do grupo é
o pior estado (`status_rank`). "Problema" = `NAO_CONFORME` ou `ATENCAO`. Classificação conforme a spec.

## R8 — Permissões e busca de cursos

**Decisão**: `require_login()`; `require_capability('local/mooc_audit:compare', context_course)` para **cada** curso;
a página de seleção exige a capacidade no contexto do sistema **ou** em alguma categoria/curso (usa
`get_user_capability_course('local/mooc_audit:compare', null, true, '', '', 1)` para saber se há algum curso). O
seletor usa o elemento de formulário `course` do Moodle (autocomplete com pesquisa por nome/sigla e
`requiredcapabilities => ['local/mooc_audit:compare']`), e os parâmetros `source`/`target` aceitam ids diretamente.
Sem capacidade nova; `compare` continua `captype read`, `CONTEXT_SYSTEM`, arquétipo `manager`.

## R9 — Navegação

**Decisão**: `admin_externalpage('local_mooc_audit_compare', 'Comparar ofertas', /local/mooc_audit/compare.php,
'local/mooc_audit:compare')` na categoria `courses` da administração do site (visível a quem tem a capacidade, sem
`moodle/site:config`), e link "Comparar com a oferta anterior" no cabeçalho do relatório de auditoria quando o
usuário pode comparar (destino pré-preenchido).

## R10 — Correspondência manual sem persistência

**Decisão**: parâmetro `pair[]=<cmid origem>-<cmid destino>` (GET). O comparador valida (existem, mesmo tipo, nenhum
dos dois repetido) e aplica antes dos passes; inválidos geram aviso. A seção de ambiguidades da página traz um
formulário GET com um seletor por módulo de origem ambíguo. A URL resultante é compartilhável e reproduz o relatório.

## R11 — Contrato

**Decisão**: `contract: "comparison"`, `contract_version: "1.0"`, JSON Schema `schema/comparison-v1.0.schema.json`,
codificação única `comparator::to_json()` (mesmas flags de `engine::to_json`). Teste valida a saída dos cenários com
o validador mínimo de `contract_test`. Contrato individual 1.0 intacto.

## R12 — Desempenho

**Decisão**: medir `timing` (snapshot origem, snapshot destino, regras, comparação, total) e
`memory_get_peak_usage(true)` na CLI com duas salas sintéticas de 151 módulos (script de medição fora do plugin,
como na Spec 001, versionado em `plugin-results/0.4.0/`). Custo esperado ≈ 2 auditorias + O(n) de indexação. Meta
SC-005: < 10 s.

## R13 — Somente leitura

**Decisão**: estender o padrão de `readonly_test` (17 tabelas + `timemodified`/`cacherev`) às duas salas antes e
depois de `comparator::run()`; a página e a CLI só chamam `run()`.

## R14 — Cenários experimentais

**Decisão**: `cli/make_scenarios.php` (somente laboratório, com as mesmas guardas de `make_import.php`) monta os
cenários A–G sobre as salas A/B do gerador, usando a importação nativa (`core_course_external::import_course`) e
mutações controladas pelas APIs; os testes PHPUnit reproduzem os mesmos cenários com o gerador e a importação por
`backup_controller`/`restore_controller` (modo importação), sem depender de backups institucionais.
