# Feature Specification: Experiência do relatório individual e da correção assistida

**Feature Branch**: `claude/spec-006-experiencia-relatorio` (diretório `specs/006-experiencia-relatorio/`)

**Created**: 2026-10-30

**Status**: Draft — especificação para revisão; nada implementado. Itens **[validar]** dependem de confirmação
institucional ou do resultado dos testes com usuários antes do plano. **Revisão 2026-10-30**: ampliada com a auditoria de
capacidade de correção (`docs/auditoria-ux-correcao/README.md`): US7, US8, FR‑031 a FR‑044, SC‑011 a SC‑015 e as fases
de implementação.

**Base**: revisão geral de 2026-10-29 (`docs/revisao-geral/`, PR #7) e etapas 1 e 2 do plano de estabilização (plugin
0.5.2, PRs #8 e #9). Primeiro incremento de experiência do usuário do plano
(`docs/revisao-geral/C-PLANO-DE-ESTABILIZACAO.md`, seção 7), com base nos achados B‑01 a B‑06, B‑12, B‑13 e A‑09 do
Relatório B (`docs/revisao-geral/B-AVALIACAO-UX-COGNITIVA.md`). A numeração 006 segue a 005; a 004 (API) continua
reservada e, pelo plano, vem depois deste incremento.

**Input**: User description: "Spec 006 — Experiência do relatório individual e da correção assistida (Auditor MOOC,
local_mooc_audit). Primeiro incremento de UX do plano de estabilização, com base nos achados B‑01 a B‑06, B‑12, B‑13 e
A‑09 do Relatório B. Objetivo: reduzir o esforço para entender as inconformidades de uma sala, decidir o que fazer e
executar a próxima ação, para professor editor/proponente e equipe técnica do Cefor. Escopo: (1) topo do relatório com
situação geral e o que fazer primeiro, contagens em frase (sem misturar ocorrências e regras), inventário e metadados
técnicos recolhidos no fim; (2) duas visões do mesmo resultado — por prioridade (atual) e por atividade — com linhas
resumidas e detalhes técnicos sob demanda, sem cartões longos; (3) cada ocorrência responde o que está errado, onde,
encontrado × exigido em linguagem do Moodle, o que fazer, se pode corrigir aqui; orientação também nos alertas; um botão
por atividade+regra; (4) evidência estruturada e textos em lang/ (A‑09), mantendo contrato JSON 1.0 compatível (campos
aditivos) e estados/ids das regras inalterados; (5) correção assistida sem códigos internos na prévia e no resultado.
Fora de escopo: comparação entre ofertas, histórico, painel (segundo incremento), API (spec 004), novas regras, mudança
de decisões institucionais, noção de 'curso aprovado'. Exige validação com usuários reais antes e depois (roteiro do
Relatório B §8) e critérios de aceite mensuráveis (tempo até a primeira ação, interpretação correta, celular). Não
implementar."

## Contexto

O relatório individual (*Mais › Auditoria técnica*) é a tela que o professor editor e a equipe técnica mais usam. A
revisão geral observou, no relatório real do Curso B do laboratório (25 não conformidades, 5 alertas):

1. **O resultado não está no topo.** Antes da primeira inconformidade vêm o resumo da execução, os metadados do motor
   e da política e uma tabela de inventário com nove linhas que não leva a nenhuma ação (B‑01).
2. **O mesmo problema fica espalhado.** "Questionário 1" aparece em cinco linhas, em três grupos de gravidade e em duas
   seções; para corrigir, o usuário precisa juntar mentalmente o que está distante (B‑02). Há dois botões de proposta
   que levam à mesma correção (B‑06).
3. **A evidência fala a língua do banco de dados.** `status=1` (que no Moodle significa *desabilitado*),
   `aggregateonlygraded=1`, `completion=1`, `hiddensections=0`, `{"completion":2}`, `ciclo: 32 → 33 → 32`. Os títulos
   são critérios ("Glossário: proibido se colaborativo; vazio se fonte de jogos"), não problemas (B‑03).
4. **As contagens confundem.** "Conforme 28 · Regras 29" numa sala "Bloqueada" mistura ocorrências e regras (B‑04).
5. **Alertas sem orientação.** Três de cinco alertas não dizem se é preciso agir (B‑05).
6. **A correção mostra códigos internos.** `NAO_CONFORME, NAO_CONFORME → CONFORME`, `attempts: 1 → 2`, nomes de
   capacidades para o professor e a letra da categoria da proposta (B‑12, B‑13).
7. **As mensagens estão dentro do código das regras.** Reescrever um texto exige alterar a regra e seus testes de
   caracterização, o que torna caro qualquer ajuste de linguagem (A‑09).

O diagnóstico das regras está correto e testado; o problema é de apresentação. Esta especificação muda **como** o
resultado é mostrado, sem mudar **o que** é avaliado.

**Fora de escopo**: comparação entre ofertas, página de execução do histórico e painel de acompanhamento (segundo
incremento de UX; ver as ressalvas sobre telas que reutilizam o relatório em *Edge Cases*); API externa (Spec 004);
novas regras ou mudança de critério, gravidade ou estado de qualquer regra; mudança de decisões institucionais;
qualquer noção de "curso aprovado", selo ou percentual de conformidade; correção em lote; novas correções executáveis;
desfazer automático; personalização da interface por usuário além de lembrar a visão escolhida; tema visual
institucional.

## Clarifications

### Session 2026-10-30

Decisões do autor da especificação, no mesmo formato das Specs 001–005, para não bloquear o planejamento.
**[validar]** = exige confirmação institucional ou resultado dos testes com usuários antes do plano; as demais são
premissas registradas.

- Q: Qual visão aparece primeiro, "por prioridade" ou "por atividade"? → A: **por prioridade**, que preserva a
  triagem atual; a escolha de cada pessoa é lembrada no mesmo navegador. **[validar]** pela linha de base do roteiro de
  testes: se professores concluírem T2–T4 melhor pela visão por atividade, ela passa a ser a inicial para quem não
  tem a capacidade de comparar ofertas.
- Q: O código da regra (CL‑13, X‑DEP‑02) continua visível? → A: sim, em posição secundária (nos detalhes ou ao lado
  do título, em texto discreto). Ele é a ponte com o catálogo e com a equipe técnica; não pode ser a informação
  principal.
- Q: Quem aprova os textos em linguagem simples das 29 regras? → A: a equipe técnica do Cefor revisa a redação antes
  da homologação. **[validar]** responsável e prazo. Enquanto a decisão 10 (nomes canônicos) estiver pendente, os
  textos das regras afetadas citam o nome encontrado na sala e não afirmam o nome oficial.
- Q: O que acontece com relatórios guardados antes desta versão? → A: continuam legíveis. Quando faltar a informação
  nova (frases e valores estruturados), a tela usa a evidência gravada, como hoje, sem erro e sem inventar texto.
- Q: O contrato JSON muda? → A: só por acréscimo. Os campos atuais (`evidencia`, `encontrado`, `esperado`,
  `orientacao`, estados, gravidades, ids) permanecem com o mesmo significado; os novos são opcionais. A versão do
  contrato passa de 1.0 para 1.1 (compatível).
- Q: "Detalhes técnicos sob demanda" exige JavaScript? → A: não. O recolhimento usa um controle nativo, acessível
  por teclado e por leitores de tela, e o conteúdo continua presente para quem imprime ou não usa JavaScript.
- Q: E o celular? → A: a 320 px de largura nenhuma informação exige rolagem horizontal da página; as linhas de
  ocorrência se reorganizam em blocos empilhados (rótulo e valor). A rolagem dentro de uma tabela, solução provisória
  da etapa 2, deixa de ser necessária no relatório.

### Session 2026-10-30 (revisão: capacidade de correção)

Decisões tomadas a partir da auditoria `docs/auditoria-ux-correcao/README.md` (achados U‑01 a U‑22, casos A–D).

- Q: Criar uma spec nova para "como corrigir"? → A: não. É o mesmo fluxo (relatório individual → correção →
  confirmação); a 006 é ampliada e separada em fases (ver *Fases*), para não fragmentar.
- Q: Os textos continuam sendo gravados no momento da auditoria? → A: não para a apresentação. A tela monta textos e
  links **ao exibir**, no idioma e no site de quem vê, a partir dos fatos gravados (regra, verificação, entidades,
  valores). O texto gravado é usado só quando os fatos não bastam (execuções antigas).
- Q: O relatório pode indicar quantas "ações" existem? → A: só quando a relação entre ocorrências for demonstrável por
  uma lista fechada e testada de relações (mesma correção simulada, mesma entidade e mesmo campo, mesma verificação de
  presença, objeto de uma ocorrência igual à entidade de outra). Sem isso, conta ocorrências. Nunca por semelhança de
  texto.
- Q: Links para o Moodle: âncora (`#id_...`) ou parâmetro? → A: o parâmetro nativo `showonly` de `modedit.php` e
  `editsection.php`, que o próprio núcleo usa; âncoras não expandem a seção (verificado no 5.3).
- Q: O relatório sugere qual dependência remover num ciclo, ou qual alternativa escolher no X‑DEP‑02? → A: não.
  Apresenta as alternativas com a pergunta pedagógica explícita e o link de cada uma.
- Q: Correções de regra encontradas na auditoria (U‑01, U‑05, U‑11, U‑13) entram nesta spec? → A: entram como fase 4,
  separada, com decisão registrada caso a caso; são a única exceção ao FR‑025. **[validar]** aprovação do Cefor.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Entender a situação da sala e a primeira ação (Priority: P1)

Um professor editor abre a auditoria técnica da sua sala e, sem rolar a página, sabe se a sala está bloqueada, quantos
problemas existem e em quantas atividades, quantos podem ser corrigidos ali mesmo e qual é o primeiro problema a
resolver.

**Why this priority**: é a pergunta de toda visita à tela. Hoje a resposta exige rolar mais de uma tela e interpretar
contagens ambíguas (B‑01, B‑04).

**Independent Test**: abrir o relatório do Curso B (referência inconsistente) numa tela de 1366 × 900 e conferir que a
situação, a frase de contagem e o primeiro bloqueante aparecem antes de qualquer rolagem; abrir o do Curso A e conferir
a mensagem de "nenhuma inconformidade detectada pelas 29 verificações" sem linguagem de aprovação.

**Acceptance Scenarios**:

1. **Given** uma sala com problemas bloqueantes, **When** o professor abre o relatório, **Then** o topo mostra a
   situação "Bloqueada", uma frase que separa problemas bloqueantes, demais problemas e pontos para conferir com a
   quantidade de atividades envolvidas, quantos têm correção disponível ali, e o primeiro bloqueante com sua ação.
2. **Given** uma sala sem inconformidades, **When** o relatório abre, **Then** o topo diz que as verificações
   automáticas não encontraram problemas, lembra que isso não substitui a revisão pedagógica nem os testes funcionais,
   e não usa "aprovada", selo ou percentual.
3. **Given** qualquer sala, **When** o relatório abre, **Then** inventário, versão do motor, contrato, política e tempo
   de execução ficam numa área recolhida "Detalhes técnicos da auditoria" no fim da página.
4. **Given** uma sala com resultado desatualizado ou auditoria incompleta, **When** o relatório abre, **Then** o aviso
   correspondente continua visível no topo, antes da lista de problemas.

---

### User Story 2 - Compreender cada problema sem conhecer o banco de dados (Priority: P1)

Para cada ocorrência, o professor lê em linguagem do Moodle o que está errado, em qual atividade ou configuração, o que
foi encontrado e o que é exigido (com o nome do campo como aparece na tela de edição), o que fazer, e se pode corrigir
ali mesmo. Os alertas dizem o que conferir e quando nenhuma ação é necessária.

**Why this priority**: sem isso a equipe técnica continua sendo intérprete obrigatória do relatório (B‑03, B‑05).

**Independent Test**: para cada uma das 29 regras e cada tipo de verificação que elas produzem, gerar ao menos uma
ocorrência nos cenários de teste e conferir que o texto principal não contém nome de coluna do banco, valor numérico
sem unidade nem significado, JSON ou identificador interno como única referência.

**Acceptance Scenarios**:

1. **Given** a autoinscrição desabilitada e sem datas (CL‑08), **When** o professor lê a ocorrência, **Then** vê que
   a autoinscrição está **desativada** e sem datas e o que é exigido (ativada, com o período do ano da reoferta), sem
   `status=1`.
2. **Given** um questionário com 1 tentativa (CL‑13), **When** o professor lê a ocorrência, **Then** vê "permite 1
   tentativa; exigido: pelo menos 2 (ou ilimitadas)", onde fica o campo ("Nota › Tentativas permitidas") e que a
   correção pode ser feita ali.
3. **Given** um ciclo de dependências (X‑DEP‑04), **When** o professor lê a ocorrência, **Then** vê os nomes das
   atividades envolvidas, não os números internos.
4. **Given** um alerta (estado *Atenção*), **When** o professor o lê, **Then** a orientação diz o que conferir e
   deixa claro que, se a configuração for intencional, nenhuma ação é necessária.
5. **Given** qualquer ocorrência, **When** o usuário abre "Detalhes técnicos" dela, **Then** encontra o código da
   regra, a fonte normativa (item do checklist ou decisão institucional) e a evidência técnica original.

---

### User Story 3 - Corrigir por atividade (Priority: P2)

A equipe técnica (ou o professor) troca para a visão "por atividade" e vê, para cada atividade, todos os seus
problemas juntos, um link para a configuração e, quando houver, uma única ação de correção por atividade e regra.

**Why this priority**: corrigir acontece por atividade: abre-se a tela de edição uma vez e acertam-se vários campos.
É o maior ganho previsto para salas com muitas inconformidades (B‑02, B‑06).

**Independent Test**: no Curso B, trocar para "por atividade" e conferir que "Questionário 1" aparece uma única vez,
com seus quatro problemas, um link de configuração e uma única ação de correção para o CL‑13; problemas sem atividade
(datas do curso, inscrição, livro de notas, blocos, seções) aparecem agrupados em "Configurações da sala".

**Acceptance Scenarios**:

1. **Given** uma atividade com problemas de várias regras, **When** a visão por atividade é escolhida, **Then** a
   atividade aparece uma vez, com a gravidade mais alta em destaque e cada problema numa linha.
2. **Given** duas ocorrências da mesma regra na mesma atividade resolvidas pela mesma proposta, **When** o relatório é
   exibido, **Then** existe um único botão de correção para esse par atividade e regra, e a segunda ocorrência indica
   que será resolvida pela mesma correção.
3. **Given** uma proposta apenas orientada, **When** o botão é exibido, **Then** o rótulo diz "Ver como corrigir"; uma
   proposta executável diz "Corrigir aqui (com confirmação)".
4. **Given** que a pessoa escolheu uma visão, **When** volta ao relatório depois, no mesmo navegador, **Then** a mesma
   visão é exibida.

---

### User Story 4 - Corrigir com a prévia e o resultado em linguagem do Moodle (Priority: P2)

O professor abre a proposta de correção e lê o valor atual e o proposto com o nome do campo da tela de edição; depois
de confirmar, vê o resultado em frase ("Tentativas permitidas: 1 → 2. Verificado: o problema não aparece mais") e volta
ao relatório já atualizado.

**Why this priority**: a prévia já é um ponto forte; falta remover os códigos que ainda aparecem (B‑12, B‑13), sem
esconder impedimentos, riscos ou a ausência de desfazer.

**Independent Test**: aplicar a correção do CL‑13 no Curso B e conferir que nenhuma tela do fluxo exibe constantes de
estado, nomes de campo do banco, letras de categoria ou nomes de capacidades para quem pode executar.

**Acceptance Scenarios**:

1. **Given** uma proposta executável, **When** a prévia é exibida, **Then** o modo aparece uma única vez, em frase, e
   os valores usam o rótulo do campo da tela de edição da atividade.
2. **Given** um usuário que **não** pode executar a correção, **When** vê a prévia, **Then** a tela diz o que falta
   (qual permissão pedir) e só nesse caso cita o nome técnico da capacidade.
3. **Given** uma correção aplicada, **When** o resultado é exibido, **Then** o estado antes e depois aparece em
   palavras ("não conforme" → "conforme"), com as inconformidades resolvidas ou introduzidas listadas pelo texto do
   problema.
4. **Given** qualquer proposta, **When** exibida, **Then** impedimentos, riscos, dependências e o aviso de ausência de
   desfazer (quando houver escrita) continuam presentes.

---

### User Story 5 - Ler o relatório no celular (Priority: P3)

O professor consulta o relatório pelo celular e lê cada problema inteiro sem rolar a página para os lados.

**Why this priority**: professores usam o Moodle no celular; a etapa 2 só evitou que a página inteira transbordasse
(B‑07). Fica em P3 porque a correção em si costuma ser feita no computador.

**Independent Test**: abrir o relatório do Curso B a 320 px e 375 px de largura e ler a primeira ocorrência de cada
gravidade sem rolagem horizontal.

**Acceptance Scenarios**:

1. **Given** uma tela de 320 px, **When** o relatório é exibido, **Then** cada ocorrência aparece em blocos empilhados
   (problema, local, encontrado/exigido, ação), sem rolagem horizontal da página nem de tabela.
2. **Given** a mesma tela, **When** o usuário abre os detalhes técnicos de uma ocorrência, **Then** o conteúdo também
   se ajusta à largura.

---

### User Story 6 - Ajustar a redação sem tocar nas regras (Priority: P3)

A equipe técnica do Cefor (por meio de um desenvolvedor) ajusta a redação de uma mensagem sem alterar a avaliação da
regra nem seus testes de resultado.

**Why this priority**: a linguagem simples vai exigir ajustes depois dos testes com usuários; hoje cada ajuste é uma
mudança de código da regra (A‑09).

**Independent Test**: alterar o texto de uma mensagem e conferir que os estados, gravidades, ids e chaves de
ocorrência dos cursos de referência não mudam e que a impressão digital do código das regras também não muda.

**Acceptance Scenarios**:

1. **Given** uma mudança apenas de redação, **When** a suíte de testes roda, **Then** os testes de caracterização das
   regras passam sem regravar expectativas.
2. **Given** a interface em inglês, **When** o relatório é exibido, **Then** as mensagens aparecem em inglês (hoje as
   evidências ficam sempre em português).

---

### User Story 7 - Corrigir na tela certa, na ordem certa, e confirmar (Priority: P1)

Para cada problema que pode resolver, o professor encontra a ação em linguagem do Moodle, as alternativas quando a
escolha é pedagógica, um link que abre exatamente a seção do formulário do Moodle, o aviso quando outra correção precisa
vir antes, e como confirmar que resolveu.

**Why this priority**: hoje 4 de 29 regras têm orientação suficiente, nenhuma diz como confirmar, e uma condição de
acesso inválida impede salvar o formulário da atividade sem que o relatório avise (auditoria, caso A).

**Independent Test**: na cópia do Curso B, seguir só o relatório para resolver o X‑DEP‑02 do "Questionário 1", depois o
CL‑13 pela tela do Moodle, e confirmar com nova auditoria, sem consultar outra fonte.

**Acceptance Scenarios**:

1. **Given** um X‑DEP‑02 (alvo sem conclusão), **When** o professor lê a ocorrência, **Then** vê os nomes das duas
   atividades, a consequência em cadeia (questionário → Avaliação do curso → certificado, quando for o caso), as duas
   alternativas (ativar a conclusão no alvo; remover a condição) com a pergunta que decide entre elas, um link para cada
   uma que abre só a seção pertinente, e como a condição aparece no formulário ("Escolher…").
2. **Given** uma atividade com condição de acesso inválida e outras ocorrências, **When** o relatório é exibido,
   **Then** a ocorrência da condição aparece primeiro e as demais dizem que o formulário não será salvo antes dela.
3. **Given** um ciclo (X‑DEP‑04), **When** o professor o lê, **Then** vê a relação em texto com os nomes, se o ciclo está
   na cadeia do certificado, e uma opção por aresta, sem recomendação de qual remover.
4. **Given** qualquer ocorrência com ação, **When** exibida, **Then** diz como confirmar (o que deve mudar depois de
   "Executar novamente") e, nas rotas que aceitam, o link de edição volta ao relatório depois de salvar.
5. **Given** uma atividade apagada depois da auditoria, **When** o relatório antigo é exibido, **Then** a ocorrência diz
   que a atividade foi removida e não oferece link.

---

### User Story 8 - Saber quando a correção é de outra pessoa e o que o plugin não comprova (Priority: P1)

O professor distingue o que pode fazer do que depende do administrador (plugins) ou da equipe da reoferta (categoria,
Sala Padrão), e distingue o que foi comprovado do que exige verificação manual (emissão do certificado, conferências de
texto, nomes canônicos).

**Why this priority**: no site da captura, 4 ocorrências bloqueantes mandavam importar um certificado cujo plugin não
está instalado; a regra de plugins aparecia como conforme; o professor não tem categoria onde mover o curso
(auditoria, casos C e D, U‑01, U‑09).

**Independent Test**: no site sem `mod_simplecertificate`, abrir o relatório da cópia do Curso B como professor e
conferir que o topo diz que o certificado depende do administrador, que as ocorrências de mesma causa aparecem juntas, e
que "Alcançabilidade do certificado" sem impedimento estático aparece em "Verificações manuais", não como comprovada.

**Acceptance Scenarios**:

1. **Given** um plugin exigido pela política ausente do site, **When** o relatório é exibido, **Then** um aviso no topo
   nomeia o plugin e o responsável (administrador), e as ocorrências que dele decorrem dizem isso em vez de mandar
   importar.
2. **Given** uma ação cuja tela o usuário não pode usar (permissão ou categoria), **When** exibida, **Then** não há link
   de ação; o texto diz quem pode fazer.
3. **Given** uma importação da Sala Padrão como ação, **When** exibida, **Then** diz que exige acesso à Sala Padrão e que
   as restrições do certificado precisam ser refeitas depois de importar.
4. **Given** o X‑CERT‑01 sem impedimento estático, **When** exibido, **Then** fica em "Verificações manuais" com o
   procedimento de teste com estudante de teste (aprovado e reprovado).
5. **Given** uma regra heurística ou por nome, **When** exibida, **Then** o texto diz que é uma conferência ("o enunciado
   parece declarar…", "não encontramos fórum com nome parecido com…").

---

### Edge Cases

- **Sala sem inconformidades e sem alertas**: o topo diz isso em uma frase; as seções vazias não ocupam espaço.
- **Sala com muitas ocorrências** (sala sintética de 151 módulos): a visão por atividade continua navegável; a página
  com os detalhes recolhidos não cresce na proporção do número de campos exibidos hoje.
- **Ocorrências sem atividade** (datas do curso, inscrição, livro de notas, blocos, seções, grafo de restrições):
  agrupadas em "Configurações da sala", com link para a tela correspondente.
- **Uma mesma ocorrência ligada a duas atividades** (dependência entre atividades, ciclo): aparece na atividade em que a
  correção é feita e é citada na outra.
- **Não verificável, não se aplica e erro da regra**: continuam separados de "problema", em seção própria e recolhível;
  "não verificável" nunca é apresentado como falha.
- **Regras heurísticas** (enunciado × configuração): a redação indica que é uma conferência de texto ("o enunciado
  parece declarar…").
- **Regras que dependem da decisão 10** (nomes canônicos pendentes): o texto cita o nome encontrado e não afirma qual
  é o oficial.
- **Relatórios guardados por versões anteriores** (histórico, JSON gravado): exibidos com a evidência antiga, sem
  erro. A página de execução do histórico reutiliza o mesmo relatório; ela muda junto, mas sua organização própria
  (evolução, persistentes) fica para o segundo incremento.
- **Usuário que vê propostas mas não pode aplicar**: o botão leva à prévia, que explica o que falta, como hoje.
- **Usuário sem a capacidade de ver propostas**: nenhum botão de correção; o texto "posso corrigir aqui?" não aparece.
- **Resultado desatualizado, auditoria incompleta ou nenhuma auditoria registrada**: os avisos atuais continuam no
  topo; sem auditoria registrada, o topo convida a executar.
- **Navegador sem armazenamento local** (modo privado): a visão inicial é a padrão; nada quebra.
- **Impressão**: o relatório impresso inclui os detalhes técnicos abertos.
- **Correção que precisa de outra antes** (condição de acesso inválida na mesma atividade; datas do curso antes das
  datas de inscrição e de encerramento; soma 100 antes da nota mínima do certificado; conclusão antes de exigi‑la): a
  ordem aparece na ocorrência dependente e no "Comece por aqui".
- **Mesma causa em várias regras** (`certificate_presence` no CL‑11, CL‑23 e X‑CERT‑01; conclusão do fórum institucional
  no CL‑09 e CL‑17; conclusão do rótulo no CL‑17 e CL‑19): uma ação, com as ocorrências citadas dentro dela.
- **Relação não demonstrável** (mesmo nome de arquivo em X‑CONT‑01 e X‑CONT‑04): não agrupar; no máximo "possivelmente
  relacionadas", sem afetar a contagem de ações.
- **Orientações contraditórias entre regras**: enquanto a fase 4 não corrigir a regra, a ação que satisfaz as duas
  verificações prevalece (desativar a conclusão no rótulo e no fórum institucional).
- **Usuário sem permissão de edição** (visualizador com `local/mooc_audit:audit` apenas): vê o problema e quem pode
  corrigir; nenhum link de edição.
- **Site com outro endereço ou outro idioma que o da execução**: links e textos refletem o site e o idioma atuais.

## Requirements *(mandatory)*

### Functional Requirements

**Topo e hierarquia**

- **FR-001**: O relatório MUST apresentar, antes da lista de problemas e sem rolagem numa tela de 1366 × 900, a
  situação geral, uma frase de contagem e, quando houver, o primeiro problema bloqueante com sua ação.
- **FR-002**: A frase de contagem MUST separar problemas bloqueantes, demais problemas e pontos para conferir,
  informar em quantas atividades ou configurações eles estão e quantos têm correção disponível na própria ferramenta
  para quem está vendo; MUST NOT combinar no mesmo indicador contagens de ocorrências e de regras.
- **FR-003**: Inventário da sala, versão do motor e do contrato, política, ano da reoferta, tempo de execução e
  contagens por estado MUST ficar numa área "Detalhes técnicos da auditoria", recolhida por padrão, no fim do relatório.
- **FR-004**: Os avisos de resultado desatualizado, auditoria incompleta e falha da última tentativa MUST continuar
  visíveis no topo.
- **FR-005**: Para uma sala sem inconformidades, o relatório MUST dizer que as verificações automáticas não encontraram
  problemas e MUST NOT usar "aprovada", "conforme" como selo da sala, percentual ou nota.

**Ocorrências**

- **FR-006**: Cada ocorrência MUST apresentar, em linguagem do Moodle: (a) o problema em uma frase; (b) a atividade ou
  configuração, com link para a tela de edição quando existir; (c) o valor encontrado e o exigido, com unidade e com o
  nome do campo como aparece na tela de edição, quando a regra os tiver; (d) o que fazer; (e) se há correção
  disponível na ferramenta e de que tipo.
- **FR-007**: O texto principal de uma ocorrência MUST NOT conter nomes de colunas do banco de dados, valores
  codificados sem significado (como `1` para "desabilitado"), JSON ou identificadores internos como única referência a
  uma atividade.
- **FR-008**: Todo alerta MUST ter orientação que diga o que conferir e quando nenhuma ação é necessária.
- **FR-009**: Cada ocorrência MUST oferecer, sob demanda, os detalhes técnicos: código e título da regra, fonte
  normativa, evidência técnica original e, quando houver, a chave da ocorrência.
- **FR-010**: Os detalhes sob demanda MUST ser acessíveis por teclado e leitores de tela, MUST funcionar sem
  JavaScript e MUST aparecer abertos na impressão.
- **FR-011**: Ocorrências não verificáveis, não aplicáveis e erros de regra MUST continuar separados dos problemas e
  MUST NOT ser apresentados como falha da sala.

**Visões**

- **FR-012**: O relatório MUST oferecer duas visões do mesmo resultado: "por prioridade" (gravidade › categoria, como
  hoje) e "por atividade" (atividade › problemas), com troca em um único passo e sem nova execução da auditoria.
- **FR-013**: Na visão por atividade, cada atividade MUST aparecer uma única vez, com todos os seus problemas, a
  gravidade mais alta destacada e um link para sua configuração; ocorrências sem atividade MUST ficar em
  "Configurações da sala".
- **FR-014**: A visão escolhida MUST ser lembrada no mesmo navegador; na falta dessa informação, MUST valer a visão
  padrão (por prioridade, **[validar]**).
- **FR-015**: As duas visões MUST conter exatamente as mesmas ocorrências e os mesmos estados.

**Ações de correção**

- **FR-016**: MUST existir no máximo uma ação de correção por par atividade e regra; ocorrências adicionais resolvidas
  pela mesma proposta MUST indicar isso.
- **FR-017**: O rótulo da ação MUST distinguir correção executável ("Corrigir aqui, com confirmação") de orientada ("Ver
  como corrigir").
- **FR-018**: A prévia e o resultado da correção MUST usar o rótulo do campo da tela de edição e estados em palavras;
  MUST NOT exibir constantes de estado, nomes de colunas, a letra da categoria da proposta nem nomes de capacidades a
  quem pode executar.
- **FR-019**: Para quem **não** pode executar, a prévia MUST dizer qual permissão falta, podendo citar o nome técnico da
  capacidade.
- **FR-020**: Impedimentos, riscos, dependências, a simulação e o aviso de ausência de desfazer (quando há escrita) MUST
  permanecer na prévia.

**Celular e acessibilidade**

- **FR-021**: Com 320 px de largura, o relatório e a prévia de correção MUST NOT exigir rolagem horizontal da página
  nem de tabela para ler uma ocorrência completa.
- **FR-022**: Títulos MUST seguir hierarquia sem saltos; estados MUST ser expressos também em texto, não só por cor.
- **FR-023**: O relatório e a prévia MUST passar a verificação automática de acessibilidade (WCAG 2.2 A e AA e boas
  práticas) sem violações nos cenários de referência.

**Mensagens, compatibilidade e preservação**

- **FR-024**: Os textos das mensagens (problema, encontrado, exigido, ação, orientação) MUST ser mantidos separados da
  lógica de avaliação das regras, de modo que ajustar a redação não altere estados, gravidades, ids, chaves de
  ocorrência nem a impressão digital do código das regras; MUST existir em português e inglês.
- **FR-025**: Os ids, estados, gravidades, categorias, chaves de ocorrência e critérios das 29 regras MUST permanecer
  inalterados nos cenários de referência.
- **FR-026**: O contrato JSON MUST permanecer compatível: campos atuais com o mesmo significado; informações novas
  apenas em campos opcionais; versão do contrato incrementada como compatível (1.1).
- **FR-027**: Relatórios guardados por versões anteriores MUST ser exibidos sem erro, usando a evidência gravada quando
  faltarem as informações novas.
- **FR-028**: A auditoria MUST continuar somente leitura; esta funcionalidade MUST NOT criar escrita em dados de curso
  nem nova persistência além da preferência de visão no próprio navegador.

**Validação com usuários**

- **FR-029**: Antes do plano, MUST ser registrada uma linha de base da interface atual com o roteiro do Relatório B §8
  (tarefas T1–T5 e métricas), com participantes reais; a especificação MUST ser revista se a linha de base contrariar
  alguma das hipóteses.
- **FR-030**: Antes da homologação, a nova interface MUST ser avaliada com o mesmo roteiro e os mesmos critérios, e os
  resultados MUST ser registrados com os da linha de base.

**Capacidade de correção (revisão 2026-10-30)**

- **FR-031**: Cada ocorrência com problema MUST apresentar, além do FR‑006: *por que importa* (consequência verificável
  para estudante, certificação ou oferta, derivada da verificação), *como corrigir* (passos executáveis, com alternativas
  e a decisão necessária quando a escolha é pedagógica), *quem pode corrigir* e *como confirmar*. Quando os dados não
  sustentarem uma orientação específica, MUST dizer a limitação e o responsável.
- **FR-032**: As mensagens MUST ser definidas por **regra e verificação** (código `check`), não apenas por regra; MUST
  NOT existir orientação genérica aplicada a uma verificação diferente da que ela descreve.
- **FR-033**: Textos e links MUST ser montados ao exibir, no idioma e no site de quem vê, a partir dos fatos gravados;
  o texto gravado MUST ser usado só como reserva (FR‑027).
- **FR-034**: Atividades relacionadas (alvo de condição, nós de ciclo, elos da cadeia do certificado, atividades
  faltantes) MUST aparecer pelo nome atual, com link quando a pessoa puder editá‑las; atividades removidas depois da
  auditoria MUST ser indicadas como tal, sem link.
- **FR-035**: Os links de ação MUST usar rotas nativas do Moodle e abrir a seção pertinente quando a rota permitir
  (`modedit.php`/`editsection.php` com `showonly`; `enrol/editinstance.php`; `mod/quiz/edit.php`); nas rotas que aceitam
  `returnurl`, MUST voltar ao relatório.
- **FR-036**: Um link de ação MUST NOT ser exibido quando a atividade não existe mais ou quem vê não tem a capacidade
  exigida pela tela de destino; o texto MUST dizer quem pode fazer.
- **FR-037**: O relatório MUST informar as precedências de uma lista fechada e testada (condição de acesso inválida na
  atividade antes de outras correções manuais nela; CL‑06/CL‑07 antes de CL‑08 e CL‑14; CL‑12 soma antes de conferir o
  CL‑23; conclusão da atividade antes de exigi‑la no CL‑16); MUST NOT inferir precedências fora dela.
- **FR-038**: O relatório MUST agrupar em uma ação as ocorrências cuja relação é demonstrável por uma lista fechada e
  testada de relações; MUST NOT agrupar por semelhança de texto; MUST mostrar o número de ações apenas quando todas as
  relações da sala forem demonstráveis por essa lista, e o de ocorrências sempre.
- **FR-039**: Quando um plugin exigido pela política (módulo do certificado, módulo de jogo, bloco de progresso) não
  estiver instalado no site, o relatório MUST avisar no topo, indicar o administrador como responsável e substituir a
  orientação das ocorrências decorrentes.
- **FR-040**: O relatório MUST separar em "Verificações manuais" o que não é comprovado pela auditoria: alcançabilidade
  do certificado sem impedimento estático (com o procedimento de teste funcional), conferências de texto e
  correspondências por nome.
- **FR-041**: Para cada ocorrência, o relatório MUST acrescentar ao rótulo de gravidade uma frase de impacto
  (certificação, acesso de estudantes, inscrição, política institucional, conferir), sem mudar a gravidade.
- **FR-042**: Rótulos de campos e opções do Moodle citados nas mensagens MUST vir das strings do próprio núcleo (por
  exemplo `attemptsallowed` de `quiz`, `hiddensectionsinvisible`, `activitycompletion` de `completion`).
- **FR-043**: O contrato JSON 1.1 MUST acrescentar, como campos opcionais, `check`, `subject`, `subjects` (entidades
  relacionadas), `params` (valores estruturados) e `evidence_level` (`proven`, `heuristic`, `static`, `name_match`);
  execuções 1.0 MUST continuar exibíveis, com `check` e `subject` lidos da chave de ocorrência.
- **FR-044**: As correções de regra U‑01 (plugins exigidos no X‑INT‑03), U‑05 (CL‑17 em rótulos e fóruns
  institucionais), U‑11 (alvo oculto igual no X‑DEP‑02 e no X‑CERT‑01) e U‑13 (CL‑18 sem afirmar colaboração não
  verificada) MAY alterar resultados, como única exceção ao FR‑025, cada uma com decisão registrada e caracterização
  regravada e revisada. **[validar]**

### Key Entities *(include if feature involves data)*

- **Ocorrência apresentável**: o que a tela mostra de um resultado de regra — problema (frase), local (atividade ou
  configuração, com link), encontrado e exigido (valor, unidade e rótulo do campo), ação, disponibilidade e tipo de
  correção, gravidade, estado e detalhes técnicos (código da regra, fonte, evidência original, chave). Deriva do
  resultado da regra; não muda o resultado.
- **Mensagem da regra**: texto por regra e por tipo de verificação, em português e inglês, com parâmetros (nome da
  atividade, valores, limites da política); separada da avaliação.
- **Resumo da sala**: situação geral, contagens por natureza (bloqueante, demais problemas, conferir), número de
  atividades envolvidas, número de correções disponíveis e primeiro bloqueante.
- **Grupo por atividade**: uma atividade (ou "Configurações da sala") com suas ocorrências, a gravidade mais alta, o
  link de configuração e as ações de correção distintas.
- **Preferência de visão**: a visão escolhida, guardada apenas no navegador da pessoa.
- **Mensagem por verificação**: problema, por que importa, como corrigir (com alternativas), quem, como confirmar, por
  regra e `check`, com parâmetros; em `lang/en` e `lang/pt_br`.
- **Ação corretiva**: uma intervenção que resolve uma ou mais ocorrências por relação demonstrável; tem responsável,
  destino (rota e seção), precedências e confirmação. Calculada ao exibir; não é persistida.
- **Relação e precedência declaradas**: lista fechada e testada que liga ocorrências (mesma causa) ou ordena ações.

## Success Criteria *(mandatory)*

### Measurable Outcomes

Os critérios SC‑001 a SC‑005 são medidos com o roteiro do Relatório B §8, com pelo menos 5 professores editores e 3
pessoas da equipe técnica, comparando a linha de base (interface atual) com a nova interface. Os demais são verificáveis
sem participantes.

- **SC-001**: O tempo mediano para apontar o primeiro problema bloqueante do Curso B (tarefa T1) fica abaixo de
  30 segundos e é menor que o da linha de base.
- **SC-002**: Pelo menos 4 de 5 professores explicam corretamente, com suas palavras e sem ajuda, o problema e a ação
  do "Questionário 1" (tarefa T2).
- **SC-003**: Nenhum participante interpreta as contagens de uma sala bloqueada como "quase tudo certo".
- **SC-004**: Todos os participantes concluem a correção do CL‑13 e confirmam, sem pedir ajuda, que o problema foi
  resolvido (tarefa T4).
- **SC-005**: A dificuldade percebida (SEQ, 1–7) das tarefas T1–T4 tem mediana de pelo menos 6, e não piora em
  nenhuma tarefa em relação à linha de base.
- **SC-006**: Com os detalhes recolhidos, o relatório do Curso B ocupa no máximo um terço da altura atual (hoje cerca
  de 7.800 px a 1366 px de largura).
- **SC-007**: Em 100% das verificações das 29 regras exercitadas pelos cenários de teste, o texto principal das
  ocorrências não contém nomes de colunas, valores codificados, JSON ou identificador interno como única referência.
- **SC-008**: A 320 px de largura, 0 ocorrências exigem rolagem horizontal para serem lidas por inteiro.
- **SC-009**: 0 violações na verificação automática de acessibilidade (WCAG 2.2 A/AA e boas práticas) no relatório dos
  Cursos A e B, na prévia e no resultado da correção.
- **SC-010**: 0 mudanças em ids, estados, gravidades e chaves de ocorrência nos cursos de referência A e B e na sala
  sintética de 151 módulos; relatórios guardados pela versão 0.5.x continuam abrindo sem erro.

- **SC-011**: Nos cenários de teste, 100% das verificações das 29 regras que produzem problema ou alerta têm mensagem
  própria com "por que importa", "como corrigir", "quem" e "como confirmar"; 0 ocorrências recebem orientação de outra
  verificação.
- **SC-012**: No Curso B (4.5, com plugins) e na sua cópia no site sem plugins (5.3), 100% dos links de ação abrem a
  tela e a seção previstas, e 0 links são exibidos para quem não tem a capacidade da tela de destino.
- **SC-013**: Com o roteiro de usuários acrescido das tarefas T6 (liberar o Questionário 1 e confirmar) e T7 (o que
  impede ou prejudica o certificado), pelo menos 4 de 5 professores concluem T6 sem ajuda e apontam em T7 as duas
  causas (cadeia de liberação bloqueada; certificado sem nota mínima).
- **SC-014**: Nos cursos de referência, a contagem de ações coincide com a calculada manualmente pelas relações
  declaradas (no Curso B do site sem plugins: 29 ocorrências, 24 ações).
- **SC-015**: Para regras de mensagem alterada apenas (fases 1–3), 0 mudanças em ids, estados, gravidades e chaves de
  ocorrência; relatórios gravados pela versão 0.6.x continuam abrindo sem erro, com nomes e links recalculados.

## Fases de implementação (revisão 2026-10-30)

1. **Textos e links** (FR‑031 a FR‑037, FR‑039, FR‑042): mensagens por verificação, nomes, rotas nativas, precedências,
   responsável, plugin ausente. Não muda resultados.
2. **Estrutura do relatório** (FR‑001 a FR‑023, FR‑038, FR‑040, FR‑041): resumo, "Comece por aqui", visões, verificações
   manuais, detalhes recolhidos, celular. Precedida da linha de base com usuários (FR‑029, com T6 e T7).
3. **Contrato 1.1** (FR‑026, FR‑043): campos opcionais; API passa a expô‑los.
4. **Correções de regra** (FR‑044): uma por vez, com decisão e caracterização regravada.

Testes por fase: caracterização A/B e sala sintética intactas nas fases 1–3; teste por regra e verificação para as
mensagens; Behat das jornadas X‑DEP‑02 e certificado; axe no relatório e na prévia; `contract_test` 1.0 e 1.1.

## Assumptions

- Os perfis e as tarefas são os do Relatório B (§2 e §8); o gestor das reofertas usa principalmente o painel, que fica
  para o segundo incremento.
- O Cefor consegue recrutar ao menos 5 professores editores e 3 pessoas da equipe técnica para a linha de base e para a
  avaliação final. **[validar]** Sem participantes, SC‑001 a SC‑005 ficam pendentes e a entrega não segue para
  homologação apenas com a avaliação heurística.
- A equipe técnica do Cefor revisa a redação das mensagens das 29 regras antes da homologação. **[validar]**
- A plataforma continua sendo o Moodle 4.5 com o tema padrão; o tema institucional pode alterar cores e espaçamentos e
  deve ser conferido na homologação.
- A preferência de visão fica só no navegador da pessoa; não há preferência sincronizada entre dispositivos.
- Interface em português do Brasil como idioma principal; o inglês é mantido completo porque o Moodle o exige como
  idioma de referência dos pacotes de idioma.
- As etapas 1 e 2 do plano de estabilização (PRs #8 e #9) estão mescladas antes do início da implementação: a
  reauditoria pós-correção gravada (A‑01) é pré-requisito do cenário de retorno ao relatório.
- A Spec 004 (API externa) continua depois deste incremento e passa a expor o contrato 1.1.
