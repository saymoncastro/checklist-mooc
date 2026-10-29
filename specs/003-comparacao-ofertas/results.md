# Resultados da Spec 003 — Comparação entre Ofertas (plugin 0.4.0)

**Data:** 2026-10-29 · **Branch:** `claude/spec-003-comparacao-ofertas-b89195` (a partir de `main` 82d6608; PRs #1, #2
e #3 já mesclados) · **Ambiente:** site isolado `moocaudit-s3-web` (porta 8094, Moodle 4.5.15, PHP 8.3, PostgreSQL 17)
· **Evidências:** `plugin-results/0.4.0/`.

## 1. Entregáveis

| # | Entregável | Onde |
|---|---|---|
| 1 | Spec 003 completa (com decisões de esclarecimento registradas) | [spec.md](spec.md), [checklists/requirements.md](checklists/requirements.md) |
| 2 | Plano de implementação | [plan.md](plan.md), [research.md](research.md), [tasks.md](tasks.md) |
| 3 | Contrato JSON `comparison` 1.0 | [contracts/comparison-v1.0.schema.json](contracts/comparison-v1.0.schema.json) (= `plugin/local_mooc_audit/schema/`), [data-model.md](data-model.md) |
| 4 | Comparador evoluído e testado | `classes/comparator.php`, `classes/local/comparison/{matcher,differ,audit_relation}.php` |
| 5 | Interface no Moodle | `compare.php` (Administração do site > Cursos > Comparar ofertas; link no relatório de auditoria), [contracts/compare-page.md](contracts/compare-page.md) |
| 6 | Testes PHPUnit e Behat | 43 testes PHPUnit novos (161 no total), `tests/behat/compare.feature` (3 cenários; 9 no total) |
| 7 | Evidências dos cenários experimentais | `plugin-results/0.4.0/cenario_{A..G}.{txt,json}`, `cli/make_scenarios.php` |
| 8 | Resultados de comparação | §3 abaixo e `plugin-results/0.4.0/medicoes.md` |
| 9 | Revisão de segurança | §5 |
| 10 | Avaliação de desempenho | §4 e `plugin-results/0.4.0/medicoes.md` |
| 11 | Limitações | §6 |
| 12 | PR para revisão | aberto ao final desta entrega (sem merge) |

## 2. O comparador experimental: avaliação e evolução

Ver [research.md](research.md) §R0. Resumo do que mudou:

| Aspecto | PoC (`comparator.php` 0.3.0) | 0.4.0 |
|---|---|---|
| Correspondência | Laço guloso; unicidade só no destino; ordem dos módulos podia decidir | Passes globais com unicidade **nos dois lados**; determinístico (teste embaralha módulos) |
| Ambiguidade | Candidatos viravam "ambíguo" **e** "adicionais" | Grupo ambíguo com motivo (duplicado no destino/origem, conteúdo equivalente, mesmo nome), sem contar como ausente/adicional; posição e conteúdo nunca decidem dentro de um grupo |
| Movida / renomeada | Não representava | Tipo+nome (alta) e tipo+conteúdo / tipo+seção+posição (média), com diferença informativa |
| Libras com mesmo nome | Ambíguo | Casada pela impressão de conteúdo (alta) |
| Correspondência manual | — | `pair[]` na URL / `--pairs` na CLI, validada, sem persistência |
| `problematic` | **Sempre verdadeiro** (`\|\| true`) | Verdadeiro só com diferença relevante; testes distinguem relevante, informativa e esperada |
| Classificação | Só "esperada" para campos do curso | Tabela explícita (research R3) com código e explicação por diferença |
| Restrições | Remapeava cm/nota; datas viravam diferença | Também seções; datas mascaradas (deslocamento = esperado); referência sem par nomeada |
| Arquivos | Lista área/caminho/nome/tamanho | Registro ausente × conteúdo ausente no armazenamento × indisponível ao estudante |
| Glossário/jogo | `glossaryid 3 → 4` como diferença | Remapeamento reconhecido; perda de entradas cita o jogo dependente |
| Auditorias | Não relacionava | Persistente, novo, pendência da oferta, resolvido/ausente; origem no ano dela |
| Contrato | Array ad hoc | `comparison` 1.0 com schema |
| Testes | Nenhum | 13 matcher, 8 differ, 6 relação, 8 integração (A–G), acesso, somente leitura, contrato, saída, Behat |

## 3. Cenários experimentais (laboratório)

Origem = oferta 2026; destino = importação nativa para 2027; política `2027-decisoes-2026-10-28`. Saídas completas em
`plugin-results/0.4.0/cenario_<X>.txt|json`; os mesmos cenários rodam no PHPUnit (`tests/comparator_test.php`).

| Cenário | O que se esperava | O que a comparação mostrou |
|---|---|---|
| **A** importação correta | Preservadas e ids alterados reconhecidos | 12 preservadas (confiança alta), 0 ausentes; fórum "Avisos" ambíguo (duplicado no destino, 2 candidatos, nenhum escolhido); sigla/datas/inscrição como **esperadas**; 32 identificadores remapeados contados sem ruído. Achado: os encerramentos dos questionários continuam em 31/12/2026 → **CL-14 como pendência da nova oferta** (não como herdado) |
| **B** avisos duplicado + problemas | Duplicidade registrada, sem correspondência arbitrária | Ambiguidade `duplicado_no_destino`; 21 inconformidades **persistentes** (X-DEP-04, CL-12, CL-13, X-INT-02…); CL-06/CL-07 da origem (2026) **não** aparecem como herdadas; 1 resolvida no destino (seções ocultas) |
| **C** glossário de jogo | Entradas perdidas ligadas ao jogo | Glossário: `entradas 3 → 0` relevante, "usado como fonte por: game Palavras cruzadas"; jogo: `entradas disponíveis 3 → 0`; `glossaryid` remapeado reconhecido como esperado; CL-18 **novo no destino** (ATENÇÃO na origem → NÃO CONFORME) |
| **D** avaliação alterada | Diferenças com evidência | Questionário 1: tentativas 2 → 1, nota 50 → 40, item de nota 50 → 40, conclusão automática → manual, todas relevantes; CL-13, CL-12, CL-17, X-AV-01 **novos no destino** |
| **E** arquivos | Distinguir registro, armazenamento e disponibilidade | Apostila 1 `file_record_missing`; Apostila 2 `file_content_missing`; Apostila 3 `file_unavailable` + visibilidade; X-CONT-01, X-INT-02, X-STR-01 novos no destino |
| **F** nomes repetidos | Nada de correspondência arbitrária | "Aula 1" × 2 (uma com Libras) casadas cada uma com a sua versão por conteúdo; "Leitura complementar" × 2 idênticas em `conteudo_equivalente` (configurações idênticas); com pares manuais → confiança "manual" |
| **G** oferta temporal | Datas/inscrição pela política de cada oferta | Sem ajustes: CL-06, CL-07, CL-08 como **pendências da nova oferta**; origem conforme no seu ano; nenhum item de ciclo como persistente |

Sala de 151 módulos importada: 151 preservadas, 1 ambiguidade (Avisos), 0 ausentes.

## 4. Desempenho

Duas salas de 151 módulos (+ Avisos): `total_s` 0,24–0,29 s em três execuções (retratos 0,13–0,17 s; regras nas duas
salas 0,02 s; correspondência + diferenças + relação 0,10 s), pico de 30 MB; página completa em 0,99 s com
`debugdeveloper` ligado. Auditoria individual da mesma sala: 0,10 s → a comparação custa ≈ 2,5× uma auditoria (medido,
não presumido). Meta SC-005 (< 10 s) atendida. Detalhes: `plugin-results/0.4.0/medicoes.md`.

## 5. Revisão de segurança e de código

Verificação própria (constituição III, VI, VII, VIII):

| Item | Resultado |
|---|---|
| Somente leitura | Caminho da comparação sem DML de escrita (`grep` em `comparator`, `local/comparison`, saída, página, CLI); `compare_readonly_test`: 18 tabelas das duas salas, `timemodified` e `cacherev` idênticos antes/depois de `run()`, texto, JSON e página; nenhum evento disparado |
| Correção | Nenhuma ação de correção na página (teste); a Spec 002 continua o único caminho de escrita |
| Permissões | `require_login`; seleção exige `compare` no sistema ou em algum curso; comparação exige `compare` **nos dois** contextos de curso antes de ler qualquer dado (inclusive `format=json`); teste com gerente de site, gerente de categoria (só suas salas), professor editor (negado) e estudante (negado); busca de cursos restrita pela capacidade |
| CSRF | Formulário de seleção em POST com `sesskey` (formslib) → redireciona para URL GET; a comparação em GET não altera estado |
| XSS | Templates só com `{{ }}` (escape); nomes com `<script>` e `<b>` escapados (teste); nenhum `{{{ }}}` nos templates da comparação |
| Parâmetros | `PARAM_INT`, `PARAM_BOOL`, `PARAM_ALPHA`, `pair[]` com `PARAM_ALPHANUMEXT` + regex `^\d+-\d+$` convertida para inteiros; pares validados contra os retratos |
| Dados pessoais | Retratos sem dados de estudantes; teste com estudante inscrito, tentativa e nota: nome, e-mail, nota e `uniqueid` ausentes de texto, JSON e HTML; sem `userid`/`viewer_userid` no contrato; senha de inscrição só como `has_password` |
| Contrato individual | `report` 1.0 inalterado (teste) |

Revisão independente (subagente sem acesso às conclusões acima): ver §5.1.

### 5.1 Achados da revisão independente

(preenchido após a revisão)

## 6. Limitações

1. **Política por ano**: a política não tem versões por ano; a origem é avaliada com a política vigente e o ano da sua
   data de início. Parâmetros não temporais (nomes canônicos, tentativas, blocos) são os atuais **[validar]**.
2. **CL-14 no ciclo de oferta** **[validar]**: encerramentos são tratados como pendência da nova oferta mesmo quando a
   origem também não tinha data (ex.: B).
3. **Causa**: nenhuma diferença é atribuída à importação. Sem histórico (Spec 005) não há como saber se a sala nova foi
   editada depois de importada; o relatório diz "novo no destino", não "introduzido pela importação".
4. **Correspondência média**: renomeação detectada por conteúdo idêntico ou mesma posição; renomeada **e** editada
   **e** movida não é reconhecida (vira ausente + adicional). Similaridade textual foi rejeitada de propósito
   (princípio VI).
5. **Grupos equivalentes**: cópias idênticas de mesmo nome ficam ambíguas até a confirmação manual; a confirmação não
   é gravada (vale para a URL).
6. **Agrupamento de resultados sem atividade**: resultados de uma regra no mesmo local textual (ex.: vários blocos
   ausentes no CL-21) formam um item; a evidência dos dois lados é mostrada, mas o item não diferencia "um bloco a
   mais faltando".
7. **Livro de notas não materializado** (salas sintéticas sem nota, cenários E/F): o CL-12 pode aparecer como novo no
   destino porque a restauração cria a categoria raiz que a origem nunca teve. Não ocorre em salas reais.
8. **Textos**: comparação por assinatura (texto visível + nomes de arquivos referenciados), sem diff palavra a palavra;
   texto alterado é informativo, esvaziado é relevante.
9. **Plugins de terceiros**: campos de módulos de plugins ausentes em um lado não são comparados (só os comuns);
   ambiente de teste com `mod_simplecertificate` mais antigo que o da produção.
10. **Arquivos**: compara por área/caminho/nome/tamanho; conteúdo diferente com mesmo tamanho não é detectado.

## 7. Respostas às perguntas finais

- **O plugin consegue comparar duas ofertas independentemente do MOOC Admin?** Sim. Só os dois ids de curso, dentro do
  Moodle (página, CLI, JSON); funciona com qualquer par de salas, importadas ou não.
- **Como identifica atividades correspondentes?** Por passes determinísticos sobre atributos estáveis, exigindo
  unicidade nos dois lados: tipo+seção+nome; dentro de nomes repetidos, impressão de conteúdo; tipo+nome (movida);
  tipo+conteúdo (renomeada); tipo+seção+posição (renomeada e editada). Nunca por `cmid`. Cada par registra estratégia
  e confiança.
- **Como trata duplicatas e ambiguidades?** Grupos com mais de um candidato plausível são congelados antes dos passes
  fracos e relatados com motivo e candidatos; não contam como ausentes nem adicionais; problemas da auditoria nesses
  módulos ficam "indeterminados". A pessoa pode indicar o par manualmente e comparar de novo (sem gravação).
- **Quais configurações são preservadas pela importação?** Nos cenários: estrutura, nomes, seções, conteúdo textual,
  arquivos, conclusão, restrições (remapeadas corretamente), itens de nota, configurações de questionário, blocos,
  agregação do livro de notas, referência jogo → glossário (remapeada). Não preservadas/ajustadas: entradas de
  glossário (dados de usuário), datas de encerramento das atividades (ficam na oferta anterior), datas e estado da
  autoinscrição, opção de seções ocultas (fica o padrão da sala nova), e surge um segundo fórum "Avisos".
- **Quais diferenças relevantes foram detectadas?** Entradas de glossário perdidas com jogo dependente; tentativas,
  nota, item de nota e conclusão alterados; arquivo sem registro, arquivo sem conteúdo no armazenamento, recurso
  oculto; mudança de `hiddensections`; e, pela relação com a auditoria, encerramentos não deslocados para a nova
  oferta.
- **É possível identificar problemas persistentes e novos?** Sim: persistentes (origem pelo menos tão grave),
  novos no destino (inclusive ATENÇÃO → NÃO CONFORME), novos em atividade adicional, indeterminados, pendências da nova
  oferta e, do lado da origem, resolvidos, ausentes e não aplicáveis.
- **Como diferencia alterações esperadas de não conformidades?** Diferença esperada (ids, datas, sigla, inscrição,
  categoria) é só uma constatação estrutural; a conformidade vem das regras: o destino com a política da nova oferta,
  a origem no ano dela. As regras do ciclo de oferta no destino viram "pendência da nova oferta", nunca "herdado".
- **O comparador é confiável o suficiente para orientar a equipe de reoferta?** Para orientar a revisão, sim, nos
  cenários reproduzidos: nenhuma correspondência arbitrária, nenhuma ausência falsa por mudança de id/posição, todas as
  diferenças introduzidas detectadas com os dois valores, e cada item da auditoria classificado com a política certa.
  Para homologação faltam: revisão do relatório com a equipe (SC-006), validação das decisões [validar] e um teste com
  uma reoferta real (salas 2026 → 2027 da produção ou restauradas).
- **Quais limitações impedem atribuir uma falha à importação?** Ausência de histórico e de registro de quem alterou a
  sala nova após a importação; a comparação vê só o estado atual das duas salas. Além disso, diferenças podem vir da
  sala vazia de destino (fórum nativo, padrões de formato) e não da importação em si.
- **A Spec 003 está pronta para revisão e homologação?** Pronta para **revisão** (PR aberto, suítes verdes, evidências
  executáveis). Para **homologação**: decidir os itens [validar] (política por ano, CL-14 no ciclo), revisar o
  relatório com a equipe de reoferta e executar uma comparação sobre um par real de ofertas.
