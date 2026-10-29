# Feature Specification: Comparação entre Ofertas MOOC

**Feature Branch**: `claude/spec-003-comparacao-ofertas-b89195` (diretório `specs/003-comparacao-ofertas/`)

**Created**: 2026-10-29

**Status**: Draft — implementação experimental para revisão; itens **[validar]** dependem de confirmação
institucional antes da homologação.

**Base**: `main` em 82d6608 (Specs 001 e 002 mescladas pelos PRs #1, #2 e #3; plugin 0.3.0, versão 2026102905). Esta
especificação evolui o comparador experimental da PoC (`classes/comparator.php`) e não cria outro motor.

**Input**: User description: "Spec 003 — Comparação entre ofertas: permitir que a equipe compare tecnicamente duas
salas Moodle — origem (oferta anterior) e destino (nova oferta) — usando apenas os identificadores dos cursos, para
identificar atividades preservadas, ausentes, adicionais, modificadas e correspondências ambíguas; distinguir
diferenças esperadas, relevantes e informativas; relacionar os resultados das auditorias das duas salas (problema
persistente, novo no destino, resolvido/ausente no destino, pendência da nova oferta) sem aplicar à origem a política
temporal da nova oferta; corrigir o defeito do indicador `problematic`; interface Moodle restrita à gerência;
somente leitura; contrato JSON versionado próprio; cenários experimentais A–G; desempenho com a sala de 151 módulos."

## Contexto

Na reoferta anual o Cefor cria uma sala vazia, importa os conteúdos da sala da oferta anterior e ajusta datas,
inscrição e categoria. O Auditor MOOC já examina cada sala isoladamente; falta responder à pergunta da equipe de
reoferta: **o que mudou entre a sala antiga e a nova, o que se perdeu, o que já estava errado e o que precisa ser
revisado na nova sala, e por quê.**

A PoC demonstrou que a importação muda todos os identificadores internos (curso, contexto, `cmid`, instâncias, itens
de nota), duplica o fórum "Avisos" (a sala nova nasce com o seu e a importação traz outro) e não transfere entradas de
glossário. O comparador experimental já casa módulos por atributos estáveis, mas marca toda modificação como
problemática (`problematic` sempre verdadeiro), casa de forma gulosa (a ordem dos módulos pode decidir um empate),
não detecta renomeação nem movimentação, não relaciona os resultados das auditorias e não tem testes.

O sucesso desta especificação é medido por um relatório que uma pessoa que prepara salas de reoferta entenda sem
conhecer o Moodle por dentro, que não invente correspondências e que não chame de defeito o que é mudança esperada
da nova oferta.

**Fora de escopo**: histórico de comparações e persistência de resultados (Spec 005); comparação em lote; auditoria
em lote; integração com MOOC Admin; API externa (Spec 004); qualquer correção a partir de uma diferença (a Spec 002
continua sendo o único caminho de correção); comparação de conteúdo textual palavra a palavra; comparação com pacotes
`.mbz`; atribuição de causa à importação; mudança do contrato JSON 1.0 da auditoria individual; novas regras de
auditoria.

## Clarifications

### Session 2026-10-29

Decisões do autor da especificação para não bloquear o incremento experimental, no mesmo formato das Specs 001 e
002. **[validar]** = exige confirmação institucional antes da homologação; as demais são premissas registradas.

- Q: Quem pode comparar e como se verificam as duas salas? → A: a decisão vigente é mantida: comparar é da
  **gerência** (capacidade existente `local/mooc_audit:compare`, arquétipo `manager`). A capacidade é verificada **no
  contexto de cada um dos dois cursos** (não só no sistema): gerente do site passa em qualquer curso; gerente de
  categoria só compara salas das suas categorias; professor editor, mesmo podendo auditar cada sala, não compara. A
  busca de salas lista apenas cursos em que o usuário tem a capacidade. Nenhuma capacidade nova é criada.
- Q: Que política se aplica a cada sala? → A: a **sala de destino** é avaliada com a política vigente, exatamente
  como na auditoria individual (mesmo ano de reoferta, mesma versão de política; o bloco de destino do relatório
  coincide com a auditoria individual da sala). A **sala de origem** é avaliada com a mesma política, porém com o
  **ano de reoferta igual ao ano da data de início da própria origem** (contexto da oferta anterior). Se a origem
  não tiver data de início, as regras do ciclo de oferta da origem ficam "sem contexto" e não alimentam a
  classificação. **[validar]**: a política não guarda versões por ano; parâmetros não temporais (nomes canônicos,
  tentativas, blocos) da política vigente são aplicados também à origem, e o relatório declara isso.
- Q: Quais regras pertencem ao "ciclo de oferta"? → A: as que dependem do ano/datas da oferta: **CL-05** (categoria),
  **CL-06** (início), **CL-07** (término), **CL-08** (autoinscrição) e **CL-14** (encerramento de avaliações × término
  do curso). Inconformidades dessas regras no destino são sempre **pendência da nova oferta**, nunca "persistentes",
  qualquer que seja o estado da origem. **[validar]** a inclusão do CL-14.
- Q: Como casar atividades sem usar `cmid`? → A: em passes determinísticos, do critério mais forte ao mais fraco,
  sempre exigindo **unicidade nos dois lados** entre os módulos ainda sem par: (1) tipo + seção + nome normalizado;
  (2) dentro de um grupo de mesmo tipo/seção/nome, a impressão digital de conteúdo; (3) tipo + nome (atividade
  movida de seção); (4) tipo + impressão digital de conteúdo (atividade renomeada); (5) tipo + seção + posição
  (renomeada e editada). Os passes 1–3 produzem correspondência de confiança **alta**; 4–5, **média**. Módulos que
  disputam o mesmo tipo + nome sem unicidade são **congelados como ambíguos** antes dos passes 4–5, para que a
  posição nunca decida um empate (caso do fórum "Avisos").
- Q: A interface deve permitir confirmar manualmente uma correspondência ambígua? → A: **sim, sem persistência**. A
  seção de ambiguidades oferece, para cada módulo de origem ambíguo, a escolha de um dos candidatos e o botão
  "Comparar novamente com estas correspondências". As escolhas viajam na URL (podem ser compartilhadas), são
  validadas no servidor (módulos existentes, mesmo tipo, sem outro par) e aparecem no relatório com confiança
  **manual**. Nada é gravado.
- Q: O que é diferença esperada, relevante ou informativa? → A: **esperada**: identificadores (curso, contexto,
  módulos, instâncias, itens de nota, referências remapeadas para o par correspondente), datas de curso, de inscrição
  e de atividades, nome/sigla da sala, categoria, visibilidade da sala. **Relevante** (indica revisão): atividade
  ausente, conteúdo perdido (entradas de glossário, questões, itens, capítulos, arquivos), arquivo sem conteúdo no
  armazenamento, critério de conclusão alterado, restrição de acesso perdida ou alterada após o remapeamento,
  referência para atividade sem correspondente, item de nota alterado, visibilidade de atividade alterada, tentativas,
  nota e método de avaliação, configurações de certificado, bloco ou método de inscrição ausente, agregação do livro
  de notas. **Informativa**: renomeação, mudança de seção ou posição, reorganização/renomeação de seções, texto
  alterado (não vazio), atividade adicional sem papel avaliativo, opções de exibição. Diferença esperada **não**
  significa conforme: a conformidade da nova sala vem sempre das regras com a política da nova oferta.
- Q: Qual o defeito de `problematic` e como corrigir? → A: a expressão `!in_array(...) || true` torna o indicador
  sempre verdadeiro. Cada diferença passa a ter um tipo (esperada/relevante/informativa) e a modificação de uma
  atividade é "relevante" somente se ao menos uma diferença for relevante. O campo `problematic` é mantido no
  contrato como sinônimo booleano dessa condição.
- Q: Como relacionar os resultados das auditorias das duas salas? → A: cada resultado recebe uma **chave de local**
  estável: regra + par de atividades correspondentes (quando houver `cmid`) ou regra + local textual sem
  identificadores (curso, livro de notas, inscrição, blocos, seção). Classes no destino: **persistente** (problema na
  origem e no destino), **novo no destino** (origem sem problema no mesmo local), **novo em atividade adicional**
  (atividade sem par na origem), **indeterminado** (atividade em correspondência ambígua), **pendência da nova
  oferta** (regras do ciclo de oferta). Na origem: **resolvido no destino** (destino conforme no mesmo local),
  **ausente no destino** (atividade sem par no destino), **não aplicável/não verificável no destino**. "Problema" =
  `NAO_CONFORME` ou `ATENCAO`; o relatório mostra os dois estados. Nenhuma classe atribui causa à importação.
- Q: O resultado é persistido? → A: **não**. A comparação é sob demanda (página, JSON e CLI), sem tabela nova; o
  histórico fica para a Spec 005.
- Q: Que contrato JSON? → A: contrato próprio **`comparison` versão 1.0** (`contract: "comparison"`,
  `contract_version: "1.0"`), com JSON Schema em `plugin/local_mooc_audit/schema/`. O contrato 1.0 da auditoria
  individual não muda.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Comparar duas salas e entender o que mudou (Priority: P1)

Uma pessoa da gerência abre "Comparar ofertas" na administração do site (ou a partir do relatório de auditoria de
uma sala), escolhe a sala de origem (oferta anterior) e a sala de destino (nova oferta) por pesquisa de nome ou
identificador, confirma qual é a anterior e qual é a nova e aciona **Comparar ofertas**. O relatório mostra a
identificação das duas salas, a data, a política e o ano de cada lado, a quantidade de módulos, as atividades
preservadas, ausentes no destino, adicionais no destino, modificadas (com evidência origem → destino) e as
correspondências ambíguas, numa linguagem que explica o que fazer.

**Why this priority**: é a pergunta central da reoferta e o mínimo útil da funcionalidade.

**Independent Test**: importar a sala A para uma sala vazia, comparar e conferir que as 13 atividades de A aparecem
como preservadas ou ambíguas (fórum "Avisos"), nenhuma como ausente, e que os identificadores alterados não aparecem
como problema.

**Acceptance Scenarios**:

1. **Given** a sala A importada para uma sala vazia (cenário A), **When** a gerência compara A → importada, **Then**
   12 atividades aparecem preservadas com confiança alta, o fórum "Avisos" aparece em ambiguidades com dois
   candidatos, nenhuma atividade aparece ausente, e ids, sigla e datas aparecem em "diferenças esperadas".
2. **Given** a página de comparação sem salas escolhidas, **When** a pessoa tenta comparar sem confirmar a ordem
   origem/destino, **Then** a comparação não é executada e o formulário pede a confirmação.
3. **Given** origem e destino iguais, **When** a pessoa compara, **Then** a página recusa com mensagem clara.
4. **Given** a origem com data de início posterior à do destino, **When** a comparação é exibida, **Then** um aviso
   indica possível inversão e oferece o link de comparação invertida.
5. **Given** qualquer comparação, **When** a pessoa pede o JSON, **Then** recebe o contrato `comparison` 1.0 com as
   mesmas informações da página.

---

### User Story 2 - Distinguir problema persistente, novo e pendência da nova oferta (Priority: P1)

Na mesma página, a seção "Pendências da sala de destino" relaciona as auditorias: o que já estava errado na origem e
continua (persistente), o que aparece só no destino (novo), o que é do ciclo da nova oferta (datas, inscrição,
categoria, encerramentos) e o que existia na origem e não aparece no destino (resolvido ou ausente). Cada item traz
regra, gravidade, local, evidência dos dois lados e orientação.

**Why this priority**: sem essa relação a equipe não sabe o que é herança antiga e o que foi introduzido na nova
sala; é o diferencial em relação a duas auditorias lado a lado.

**Independent Test**: comparar B (2026, datas da oferta 2026) com a sua importação ajustada para 2027 e conferir que
CL-06/CL-07 da origem não aparecem como "persistentes" e que os problemas estruturais de B aparecem como
persistentes.

**Acceptance Scenarios**:

1. **Given** a sala B (problemas conhecidos) importada para uma sala nova, **When** comparadas, **Then** os
   problemas estruturais presentes nas duas (ex.: X-DEP-04 ciclo, CL-12 agregação, X-CONT-01 página vazia) aparecem
   como persistentes.
2. **Given** uma origem de 2026 conforme à política no seu próprio ano e um destino com datas de 2026 não ajustadas,
   **When** comparadas com a política de reoferta 2027, **Then** CL-06/CL-07 do destino aparecem como pendência da
   nova oferta, e nenhum resultado de ciclo da origem aparece como persistente.
3. **Given** um questionário conforme na origem cujo número de tentativas foi reduzido no destino (cenário D),
   **When** comparadas, **Then** CL-13 aparece como "novo no destino" e a atividade aparece modificada com a evidência
   "tentativas: 2 → 1" marcada como relevante.
4. **Given** um problema da origem em uma atividade que não existe no destino, **When** comparadas, **Then** o item
   aparece como "ausente no destino", não como "resolvido".
5. **Given** um problema do destino em atividade ambígua, **When** comparadas, **Then** o item aparece como
   "indeterminado", sem afirmar se é novo ou persistente.

---

### User Story 3 - Não inventar correspondências (Priority: P2)

Quando a importação duplica atividades, quando há atividades de mesmo nome (versões em Libras), quando uma atividade
foi movida de seção ou renomeada, o relatório não declara ausência por mudança de posição/identificador e não
apresenta correspondência arbitrária como comprovada. A pessoa pode indicar manualmente qual candidato corresponde e
comparar novamente.

**Why this priority**: uma correspondência errada leva a conclusões erradas sobre perda de conteúdo; a confiança no
relatório depende disso.

**Independent Test**: cenários B (Avisos duplicado) e F (módulos de mesmo nome, inclusive versão alternativa) e
testes unitários de movimentação e renomeação.

**Acceptance Scenarios**:

1. **Given** o fórum "Avisos" uma vez na origem e duas no destino (cenário B), **When** comparadas, **Then** surge uma
   ambiguidade "duplicado no destino" com os dois candidatos e nenhum dos dois é dado como correspondente; a
   duplicidade aparece no resumo.
2. **Given** duas páginas "Aula 1" na mesma seção, uma delas com vídeo em Libras, preservadas pela importação
   (cenário F), **When** comparadas, **Then** cada uma casa com a sua versão pelo conteúdo (confiança alta).
3. **Given** duas páginas idênticas de mesmo nome nos dois lados, **When** comparadas, **Then** aparecem como grupo
   ambíguo de "conteúdo equivalente", indicando que as configurações do grupo são idênticas ou não.
4. **Given** uma atividade movida da seção 2 para a 3, **When** comparadas, **Then** ela aparece como preservada com a
   diferença informativa "seção 2 → 3", não como ausente + adicional.
5. **Given** uma atividade renomeada com conteúdo idêntico, **When** comparadas, **Then** ela casa com confiança
   média e a renomeação é informativa.
6. **Given** uma ambiguidade, **When** a pessoa escolhe um candidato e compara novamente, **Then** o par aparece com
   confiança "manual", suas diferenças são calculadas e nada é gravado.

---

### User Story 4 - Evidência correta de conteúdo, arquivos e avaliação (Priority: P2)

Para cada atividade modificada, o relatório mostra o que mudou com valores dos dois lados e explica a consequência:
entradas de glossário perdidas e o jogo que dependia delas (cenário C), tentativas/nota/conclusão alteradas
(cenário D), arquivo sem registro, arquivo registrado sem conteúdo no armazenamento e arquivo indisponível para o
estudante (cenário E).

**Why this priority**: são os defeitos reais de importação observados; sem evidência a equipe não consegue agir.

**Independent Test**: cenários C, D e E reproduzidos por teste automatizado e no laboratório.

**Acceptance Scenarios**:

1. **Given** um glossário com 3 entradas usado como fonte de um jogo, importado para uma sala nova (cenário C),
   **When** comparadas, **Then** o glossário aparece modificado com "entradas: 3 → 0" (relevante) e a diferença cita o
   jogo dependente; o jogo aparece com "entradas disponíveis na fonte: 3 → 0".
2. **Given** um questionário com tentativas, nota máxima ou critério de conclusão alterados no destino (cenário D),
   **When** comparadas, **Then** cada campo alterado aparece com origem → destino, marcado como relevante.
3. **Given** um recurso cujo arquivo foi removido do destino, outro cujo conteúdo sumiu do armazenamento e outro
   oculto para estudantes (cenário E), **When** comparadas, **Then** as três situações aparecem com códigos e textos
   distintos.
4. **Given** uma restrição de acesso que referencia atividades e itens de nota, **When** a importação remapeia os
   identificadores corretamente, **Then** a restrição é considerada preservada; **When** uma condição é perdida ou
   aponta para atividade sem correspondente, **Then** a diferença é relevante.

---

### User Story 5 - Proteções: permissão, somente leitura, dados pessoais (Priority: P2)

Comparar exige a capacidade de comparação nas duas salas; a comparação não altera nenhuma das duas; o relatório não
contém dados de estudantes.

**Why this priority**: requisito institucional e da constituição (princípios III e VIII).

**Independent Test**: testes de acesso com gerente do site, gerente de categoria, professor editor; teste de não
modificação das tabelas das duas salas.

**Acceptance Scenarios**:

1. **Given** um professor editor das duas salas, **When** abre a página de comparação, **Then** o acesso é negado.
2. **Given** um gerente da categoria da sala de destino mas não da origem, **When** pede a comparação, **Then** o
   acesso é negado, sem revelar dados da origem.
3. **Given** qualquer comparação, **When** executada pela página, JSON ou CLI, **Then** nenhuma tabela de curso,
   módulo, nota, inscrição, bloco ou arquivo das duas salas muda, e o relatório não oferece ação de correção.
4. **Given** salas com estudantes inscritos e tentativas, **When** comparadas, **Then** o relatório não contém nomes,
   identificadores de usuário, respostas ou notas individuais.

---

### User Story 6 - Oferta temporal (Priority: P3)

Salas de anos diferentes são comparadas sem penalizar a origem pela política do ano seguinte, e as exigências da nova
oferta são avaliadas no destino (cenário G).

**Why this priority**: evita o falso "herdado" mais frequente; depende das US1/US2.

**Independent Test**: comparar A 2026 → importação ajustada 2027 e A 2026 → importação não ajustada.

**Acceptance Scenarios**:

1. **Given** A de 2026 (conforme em 2026) e importação ajustada para 2027, **When** comparadas com a política 2027,
   **Then** não há pendência da nova oferta e as diferenças de datas aparecem como esperadas.
2. **Given** A de 2026 e importação não ajustada (datas e inscrição de 2026), **When** comparadas, **Then** CL-06,
   CL-07 e CL-08 do destino aparecem como pendência da nova oferta, com a política e o ano usados em cada lado
   declarados no cabeçalho.

### Edge Cases

- Origem ou destino inexistente, ou igual ao curso do site → mensagem de curso inválido.
- Usuário com a capacidade em só uma das salas → acesso negado (US5.2).
- Origem sem data de início → regras de ciclo da origem "sem contexto"; o cabeçalho informa.
- Sala de destino vazia (antes da importação) → todas as atividades da origem ausentes; resumo explica.
- Módulo com instância ausente na tabela do módulo → diferença relevante "instância ausente" quando só um lado.
- Plugin de terceiros ausente em um dos lados (jogo, H5P, certificado) → atividade comparada só pelos campos comuns;
  aviso no relatório.
- Restrição de acesso com JSON inválido → diferença relevante "restrição ilegível".
- Correspondência manual inválida (cmid inexistente, tipos diferentes, módulo já casado) → ignorada com aviso.
- Exceção em uma regra durante a auditoria de uma das salas → resultado `ERRO` isolado, como na auditoria
  individual; a comparação estrutural continua.
- Sala com 150+ módulos → comparação concluída dentro da meta de tempo (SC-005).

## Requirements *(mandatory)*

### Functional Requirements

**Seleção e acesso**

- **FR-001**: O sistema MUST oferecer uma página "Comparar ofertas" acessível pela administração do site e, para
  quem pode comparar, por um link no relatório de auditoria de uma sala (pré-preenchendo essa sala como destino).
- **FR-002**: A página MUST permitir escolher origem e destino por pesquisa de nome/sigla e por identificador,
  listando somente cursos em que o usuário tem a capacidade de comparação.
- **FR-003**: A comparação MUST exigir confirmação explícita de que a origem é a oferta anterior e o destino a nova
  oferta, e MUST recusar origem igual a destino.
- **FR-004**: O acesso MUST exigir login e a capacidade `local/mooc_audit:compare` no contexto **de cada uma** das
  duas salas; a negação em qualquer uma impede a comparação inteira.
- **FR-005**: O sistema MUST alertar quando a origem começa depois do destino (possível inversão) e oferecer a
  comparação invertida, sem inverter automaticamente.

**Correspondência**

- **FR-006**: A correspondência MUST NOT usar `cmid` ou outros identificadores internos como critério.
- **FR-007**: A correspondência MUST seguir os passes descritos nas Clarifications, exigindo unicidade nos dois
  lados em cada passe, e MUST ser determinística (mesmas salas → mesmas correspondências, independentemente da ordem
  dos módulos).
- **FR-008**: Cada correspondência MUST registrar a estratégia usada e a confiança (alta, média, manual).
- **FR-009**: Módulos com candidatos múltiplos plausíveis MUST ser reportados como ambíguos, com motivo
  (duplicado no destino, duplicado na origem, mesmo nome, conteúdo equivalente) e candidatos, e MUST NOT ser contados
  como ausentes ou adicionais.
- **FR-010**: Uma atividade movida de seção ou renomeada MUST NOT ser reportada como ausente quando um passe a
  identificar de forma única.
- **FR-011**: O sistema MUST aceitar correspondências manuais pela URL, validá-las no servidor, aplicá-las antes dos
  passes automáticos e marcá-las como "manual", sem persistência.

**Diferenças**

- **FR-012**: Para cada par, o sistema MUST comparar: visibilidade, seção e posição, nome, conclusão, restrições de
  acesso (com remapeamento de atividades e itens de nota pelo mapa de correspondências), itens de nota, arquivos
  (registro, tamanho e disponibilidade no armazenamento), configurações específicas lidas pela auditoria, contagens de
  conteúdo e textos (por impressão digital).
- **FR-013**: Cada diferença MUST ter tipo (esperada, relevante, informativa), código estável, valores de origem e
  destino e explicação em linguagem não técnica.
- **FR-014**: Uma atividade modificada MUST ser marcada como relevante (`problematic`) somente se tiver ao menos uma
  diferença relevante (correção do defeito conhecido).
- **FR-015**: O sistema MUST comparar também configurações da sala (formato, conclusão, idioma), seções, métodos de
  inscrição, blocos e agregação do livro de notas, com a mesma classificação.
- **FR-016**: Diferenças de conteúdo perdido em glossários MUST citar as atividades que dependem dele (jogos).
- **FR-017**: Diferenças de arquivo MUST distinguir: registro ausente no destino, conteúdo ausente no armazenamento
  e atividade indisponível para o estudante (oculta ou em seção oculta) com arquivos.

**Relação entre auditorias**

- **FR-018**: O sistema MUST auditar as duas salas com o motor e o catálogo existentes (sem regras novas), o destino
  com a política vigente e a origem com o ano de reoferta da sua própria data de início.
- **FR-019**: O sistema MUST classificar os resultados conforme as Clarifications (persistente, novo no destino, novo
  em atividade adicional, indeterminado, pendência da nova oferta; resolvido, ausente, não aplicável/não verificável
  no destino).
- **FR-020**: Inconformidades de regras do ciclo de oferta no destino MUST ser classificadas como pendência da nova
  oferta e MUST NOT ser classificadas como persistentes.
- **FR-021**: O relatório MUST declarar a política, a versão e o ano de reoferta usados para cada sala.

**Relatório e contrato**

- **FR-022**: O relatório MUST apresentar, nesta ordem: identificação das salas, data, política, síntese
  (quantidades e veredito), pendências da sala de destino, atividades ausentes, ambiguidades, modificadas,
  adicionais, diferenças da sala (configurações, inscrição, blocos, notas, seções), diferenças esperadas,
  preservadas e avisos.
- **FR-023**: O sistema MUST oferecer o mesmo resultado em JSON segundo o contrato `comparison` 1.0, com JSON Schema
  versionado, e pela CLI (texto e JSON).
- **FR-024**: O contrato 1.0 da auditoria individual MUST permanecer inalterado.

**Segurança**

- **FR-025**: A comparação MUST ser somente leitura em todos os caminhos (página, JSON, CLI) e MUST NOT oferecer ação
  de correção.
- **FR-026**: O relatório MUST NOT conter dados pessoais (usuários, respostas, notas individuais) nem segredos
  (apenas a presença de senha de inscrição).
- **FR-027**: Conteúdo de curso exibido (nomes, trechos) MUST ser escapado.

### Key Entities

- **Comparação**: origem, destino, data, motor, políticas aplicadas a cada lado, síntese, avisos.
- **Correspondência**: par origem/destino de atividades, estratégia, confiança, situação (preservada/modificada),
  diferenças.
- **Ambiguidade**: motivo, tipo de atividade, nome, módulos candidatos de cada lado, configurações idênticas ou não.
- **Diferença**: escopo (atividade, sala, inscrição, bloco, notas, seção), campo, valores, tipo, código, explicação,
  dependentes.
- **Item de pendência**: regra, gravidade, classe, estado na origem e no destino, local, evidências, orientação, link.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nos cenários A–G, 100 % das atividades preservadas pela importação são identificadas como preservadas,
  modificadas ou ambíguas; nenhuma é dada como ausente por mudança de identificador, posição ou seção.
- **SC-002**: Em nenhum cenário uma correspondência é apresentada com confiança alta quando há mais de um candidato
  plausível (zero correspondências arbitrárias).
- **SC-003**: Nos cenários C, D e E, 100 % das diferenças introduzidas aparecem como relevantes, com valor de origem e
  de destino.
- **SC-004**: Na comparação A → importação ajustada, zero itens aparecem como relevantes ou pendências por causa de
  identificadores ou datas.
- **SC-005**: A comparação de duas salas de 151 módulos termina em menos de 10 segundos no laboratório, com tempo e
  memória registrados.
- **SC-006**: Uma pessoa da equipe de reoferta consegue, a partir do relatório, listar o que revisar na nova sala sem
  consultar o código (verificado pela revisão do relatório dos cenários com a equipe) **[validar]**.
- **SC-007**: As suítes PHPUnit e Behat das Specs 001 e 002 continuam passando; a comparação não altera nenhuma das
  duas salas (teste de não modificação).

## Assumptions

- A importação segue o mecanismo nativo do Moodle (Administração do curso > Importar), mas a comparação não depende
  dele: funciona com quaisquer dois cursos.
- A sala nova nasce com o seu próprio fórum de avisos (comportamento padrão do Moodle), causa conhecida da
  duplicidade.
- A política não tem versões por ano; a avaliação da origem usa a política vigente com o ano da origem.
- O snapshot da Spec 001 é a única fonte de dados; campos adicionais só entram se a comparação os exigir, com teste.
- Gerentes podem ver configurações de qualquer sala que comparam; a comparação não cria acesso novo a conteúdo.
- O laboratório (Moodle 4.5.15, PHP 8.3, PostgreSQL 17) é representativo para medir desempenho relativo.
