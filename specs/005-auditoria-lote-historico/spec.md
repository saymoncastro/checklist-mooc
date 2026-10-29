# Feature Specification: Auditoria em Lote, Histórico e Acompanhamento

**Feature Branch**: `claude/spec-005-auditoria-lote-948e09` (diretório `specs/005-auditoria-lote-historico/`)

**Created**: 2026-10-29

**Status**: Draft — incremento experimental entregue para revisão; itens **[validar]** dependem de confirmação
institucional antes da homologação.

**Base**: `main` em 037fd45 (Specs 001, 002 e 003 mescladas pelos PRs #1–#4; plugin 0.4.0, versão 2026102906). A
numeração 005 segue o backlog (`docs/backlog.md`), que reserva a 004 para a API e recomenda entregar a 005 antes dela.
Esta especificação usa o motor de regras, os relatórios, a trilha de correções e o contrato JSON existentes; não cria
outro motor nem reescreve a comparação entre ofertas.

**Input**: User description: "Spec 005 — Auditoria em lote, histórico e acompanhamento: permitir que a equipe do Cefor
acompanhe a conformidade técnica de dezenas de salas MOOC durante a preparação das reofertas, com auditorias sob
demanda, execução em lote em segundo plano (tarefas nativas do Moodle), armazenamento imutável dos resultados com a
política efetivamente aplicada, identificação estável de cada ocorrência entre execuções (novo, persistente,
resolvido, gravidade alterada, não verificável, não comparável por mudança de política, recurso removido ou
substituído), painel gerencial com filtros e sem métricas enganosas, histórico acessível no relatório individual,
permissões preservadas também nas tarefas assíncronas, retenção configurável e privacy provider atualizado; sem
MOOC Admin, API externa, notificações, correção em lote, IA ou serviços externos."

## Contexto

O Auditor MOOC audita uma sala por vez e recalcula o relatório a cada abertura da página; nada é guardado. Na
preparação das reofertas a equipe do Cefor acompanha dezenas de salas ao mesmo tempo e precisa responder, sem abrir
sala por sala: **quais salas exigem atenção, quais problemas continuam abertos, quais foram resolvidos desde a última
verificação e quais salas ainda não foram verificadas ou têm resultado velho.**

Três dificuldades orientam a especificação:

1. **Identidade das ocorrências.** A Spec 003 relacionou resultados de duas salas por "regra + local textual +
   assunto da evidência"; isso falha quando uma regra produz várias ocorrências no mesmo local e depende do texto e
   do idioma da evidência. Entre auditorias da **mesma** sala os identificadores internos (`cmid`, número da seção,
   id do método de inscrição) são estáveis, o que permite uma chave estruturada sem texto.
2. **Política e regras evoluem.** A mesma sala auditada com outra política (ou depois de uma correção no código de
   uma regra) pode mudar de resultado sem que nada tenha mudado na sala. Essa mudança não pode aparecer como
   "problema novo" nem como "resolvido".
3. **Ausência não é correção.** Uma ocorrência que some porque a regra falhou, ficou não verificável ou porque a
   atividade foi removida não foi corrigida.

**Fora de escopo**: integração com MOOC Admin; API externa ou Web Services (Spec 004); notificações por e-mail,
WhatsApp ou mensagens do Moodle; correção em lote; bloqueio automático de publicação; qualquer modificação automática
das salas; inteligência artificial; gestão de tarefas, responsáveis ou prazos; novo motor de regras; novas regras;
reescrita da comparação entre ofertas (Spec 003); comparação de histórico entre salas diferentes; agendamento
recorrente de lotes (cada lote é solicitado por uma pessoa); exportação em planilha.

## Clarifications

### Session 2026-10-29

Decisões do autor da especificação para não bloquear o incremento experimental, no mesmo formato das Specs 001–003.
**[validar]** = exige confirmação institucional antes da homologação; as demais são premissas registradas.

- Q: O que é uma "execução" e quando ela é registrada? → A: toda auditoria completa de uma sala solicitada por uma
  pessoa ou processo: abertura da página "Auditoria técnica" da sala (inclusive o botão "Executar novamente" e o
  formato JSON), cada sala de um lote e a CLI com a opção de registro. Cada execução vira um registro próprio, com
  identificador, sala, data e hora, solicitante (usuário ou "processo"), origem (página, lote, CLI), versões do plugin
  e do contrato, política aplicada, ano de reoferta, estado, resultado geral, contagens, resultados estruturados,
  comparação com a execução anterior e erros/limitações. A reauditoria interna que a correção assistida (Spec 002)
  faz após aplicar uma correção **não** é registrada como execução: ela já tem registro próprio na trilha de
  correções. **[validar]** registrar toda abertura da página (alternativa: só o botão "Executar novamente").
- Q: Quais estados uma execução pode ter? → A: **na fila**, **em execução**, **concluída** (todas as regras
  executaram), **parcial** (relatório produzido, mas ao menos uma regra terminou em `ERRO`) e **falhou** (nenhum
  relatório: sala removida, permissão revogada, exceção fora das regras, interrupção repetida). `NAO_VERIFICAVEL`
  por recurso do site desligado é resultado legítimo, não torna a execução parcial.
- Q: O histórico é imutável? → A: sim. Depois de chegar a concluída, parcial ou falhou, uma execução nunca é alterada.
  Reexecutar cria outra execução. A única remoção prevista é a da retenção configurada e a do privacy provider (que
  anonimiza o solicitante, sem apagar a execução).
- Q: Como preservar a política aplicada? → A: o conteúdo efetivo da política (padrões + configuração do site + ano de
  reoferta resolvido) é gravado uma vez por **impressão digital** (hash do conteúdo canônico) e cada execução aponta
  para ele. Assim é possível reproduzir e explicar qualquer resultado e saber quais execuções usaram a mesma
  política, sem repetir o mesmo texto em todas as execuções.
- Q: Como identificar o mesmo problema em duas execuções? → A: cada resultado recebe uma **chave de ocorrência**
  estruturada `regra | entidade | verificação | discriminador`: a **regra** (ex.: `CL-13`); a **entidade** do Moodle
  (`cm:<cmid>` para atividade, `sec:<número>` para seção, `course` para a sala); a **verificação** (código estável da
  condição verificada, declarado pela regra, ex.: `attempts`, `intro_attempts`); e um **discriminador semântico**
  quando a mesma verificação se repete na mesma entidade (ex.: título do bloco exigido, link obrigatório, id da
  instância de inscrição, atividade alvo de uma condição de restrição, caminho do arquivo). Nunca o texto da
  evidência, o idioma ou a posição de apresentação. A chave é a mesma quando a verificação passa ou falha, de modo
  que "resolvido" e "persistente" comparam a mesma coisa. Um teste garante que nenhuma execução das salas de
  referência produz duas ocorrências com a mesma chave.
- Q: Quais classes de evolução existem? → A: comparando uma execução com a execução anterior válida (concluída ou
  parcial) da mesma sala, cada ocorrência que é problema (`NAO_CONFORME` ou `ATENCAO`) em ao menos uma delas recebe uma
  classe: **nova**, **persistente**, **resolvida**, **estado ou gravidade alterados**, **não verificável agora**,
  **não comparável** (política ou regra mudou para aquela regra, ou a execução anterior não a verificou), **recurso
  removido** (a atividade/seção não existe mais) e **recurso substituído** (a atividade removida tem uma sucessora do
  mesmo tipo e nome em que o mesmo problema aparece). A ausência de uma ocorrência só é "resolvida" quando a regra
  executou normalmente na nova execução, a entidade ainda existe e nem a política nem a regra mudaram.
- Q: Como evitar falsos "novos" por mudança de política ou de regra? → A: cada execução guarda, por regra, uma
  impressão digital composta pela **fatia da política** que a regra lê (as chaves que ela declara, mais o ano de
  reoferta para regras do ciclo de oferta) e pela **implementação da regra** (hash do código da regra). Se uma delas
  difere entre as duas execuções, as ocorrências daquela regra que surgiram ou sumiram são "não comparáveis", com o
  motivo (política ou regra). Ocorrências presentes nas duas continuam "persistentes" (o problema existe nas duas,
  qualquer que seja a causa). Um teste perturba cada chave da política e confirma que toda regra afetada a declara.
  Mudanças no coletor comum (snapshot) não são capturadas por regra: a página avisa quando as duas execuções têm
  versões diferentes do plugin.
- Q: Como relacionar com a correção assistida? → A: o histórico mostra, ao lado da evolução, as correções da trilha
  da Spec 002 aplicadas na sala **entre** as duas execuções (lidas da trilha existente, sem cópia). A página diz
  explicitamente que a relação é temporal e não comprovada; nenhuma ocorrência é marcada como "resolvida pela
  correção X".
- Q: Quem acessa o quê? → A: o **histórico de uma sala** usa a capacidade existente `local/mooc_audit:audit` no
  contexto do curso (gerente e professor editor, decisão 11): quem audita a sala vê o seu histórico. Duas capacidades
  novas no contexto de sistema (constituição VIII), só para o arquétipo gerente: **`local/mooc_audit:viewdashboard`**
  (painel consolidado e páginas de lote, leitura) e **`local/mooc_audit:batchaudit`** (solicitar lotes, escrita). O
  painel e os lotes mostram apenas salas em que a pessoa também tem `local/mooc_audit:audit`, de modo que
  atribuições e proibições por categoria ou curso são respeitadas. Professores editores não ganham capacidade nova.
  **[validar]** gerente de categoria sem papel no sistema não usa o painel nesta versão.
- Q: Como as tarefas em segundo plano mantêm o escopo autorizado? → A: cada sala do lote é uma tarefa ad hoc
  executada em nome de quem solicitou; ao executar, a tarefa confere de novo `batchaudit` no sistema e `audit` no
  curso para aquela pessoa. Se a permissão foi revogada entre a solicitação e a execução, a execução falha com esse
  motivo e nada é auditado.
- Q: Como selecionar salas para um lote? → A: por **categoria** (com ou sem subcategorias), por **lista de
  identificadores**, por **ano de início** da sala (data de início do curso; usada como ciclo porque é o único dado
  confiável disponível no Moodle) e por **seleção** de salas no painel. Salas fora da permissão, inexistentes ou o
  curso do site são recusadas e listadas com o motivo. Um lote tem limite configurável de salas (padrão 300).
- Q: Como evitar execuções duplicadas e controlar recursos? → A: (1) se a sala já tem execução na fila ou em
  execução, o lote não cria outra e aponta para a existente; (2) uma trava por sala impede duas auditorias simultâneas
  da mesma sala; (3) o limite de concorrência nativo das tarefas ad hoc restringe quantas salas são auditadas ao mesmo
  tempo (configuração do plugin, padrão 2; o administrador pode sobrepor por `task_concurrency_limit`); (4) uma sala
  que falha não interrompe as demais; (5) uma tarefa interrompida (processo morto) é retomada pela própria fila do
  Moodle; após 3 tentativas a execução falha como "interrompida"; (6) a limpeza diária marca como falhas execuções
  presas (sem tarefa correspondente) além de um prazo.
- Q: O que o painel mostra e como evita métricas enganosas? → A: para as salas no filtro: quantidade, auditadas, não
  auditadas, **com bloqueantes**, **requer correções**, **sem inconformidades detectadas**, **auditoria incompleta**
  (última execução parcial ou falha), **desatualizadas** e a evolução somada (resolvidas, novas, persistentes). Nunca
  "aprovado", "conforme" como selo da sala, percentual de conformidade ou nota de qualidade. O texto do painel
  explica que "sem inconformidades detectadas" refere-se apenas às regras implementadas e não substitui a revisão
  pedagógica nem os testes funcionais de certificação.
- Q: Quando um resultado está desatualizado? → A: quando a última execução válida é mais antiga que o prazo
  configurado (padrão 7 dias), quando a política vigente difere da política da execução, ou quando a versão do plugin
  mudou desde a execução. O motivo é exibido. **[validar]** prazo padrão.
- Q: Qual a retenção? → A: configurável, **padrão 365 dias** (mínimo 30, máximo 1825), aplicada por tarefa agendada
  diária: execuções concluídas há mais tempo que o prazo são removidas, **exceto a última execução válida de cada sala
  existente** (que representa o estado atual mostrado no painel). Execuções de salas removidas seguem o prazo sem
  exceção. Lotes sem execuções e políticas não referenciadas são removidos. Não há retenção indefinida por omissão.
  **[validar]** prazo e exceção da última execução; decisão sobre remover o histórico junto com a sala.
- Q: Que dados pessoais são guardados? → A: apenas o identificador de quem solicitou cada execução e cada lote. Os
  relatórios guardados são os da visão de configuração (sem usuário de teste), sem dados de estudantes, respostas,
  notas individuais ou conclusões. O privacy provider exporta e anonimiza (solicitante = 0) sem apagar o histórico
  institucional, como já faz com a trilha de correções.
- Q: Que formato tem o resultado guardado? → A: o próprio relatório do contrato JSON 1.0 da auditoria individual
  (sem o snapshot), acrescido, de forma compatível, do campo `occurrence` em cada resultado. O contrato continua 1.0
  (mudança aditiva). O resumo, o resultado geral e a comparação com a execução anterior também ficam na execução para
  que o painel não precise reprocessar relatórios.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar cada auditoria e consultar o histórico da sala (Priority: P1)

Uma pessoa que audita uma sala (gerente ou professor editor) abre a auditoria técnica como hoje e passa a ver, no
mesmo relatório, o resumo da evolução desde a auditoria anterior e o link para o histórico, que lista as execuções da
sala e permite abrir cada relatório guardado exatamente como foi produzido.

**Why this priority**: sem histórico persistido não há acompanhamento; é a base de todas as outras histórias.

**Independent Test**: auditar a sala B duas vezes sem alterá-la; o histórico mostra duas execuções concluídas com a
mesma política, a segunda com todas as ocorrências "persistentes" e nenhuma nova ou resolvida.

**Acceptance Scenarios**:

1. **Given** uma sala nunca auditada, **When** a pessoa abre a auditoria técnica, **Then** uma execução concluída é
   registrada com sala, data, solicitante, versões, política (impressão digital), ano de reoferta, resultado geral,
   contagens e resultados, e o relatório informa "primeira auditoria registrada".
2. **Given** uma execução anterior, **When** a sala é auditada de novo sem alterações, **Then** a nova execução mostra
   0 novas, 0 resolvidas e todas as ocorrências problemáticas como persistentes.
3. **Given** o histórico de uma sala, **When** a pessoa abre uma execução antiga, **Then** vê o relatório guardado,
   identificado como registro histórico (data, política, versão), sem recalcular nada.
4. **Given** uma auditoria aberta pela página, **When** ela termina, **Then** nenhuma tabela da sala foi alterada
   (somente as tabelas do próprio plugin recebem a execução).

---

### User Story 2 - Acompanhar a evolução das inconformidades (Priority: P1)

Depois de corrigir parte dos problemas, a pessoa audita a sala de novo e vê, para cada ocorrência, se foi resolvida,
continua aberta, é nova, mudou de gravidade, não pôde ser verificada, não é comparável por mudança de política ou de
regra, ou se a atividade foi removida ou substituída.

**Why this priority**: é a funcionalidade central pedida — acompanhar a resolução, não só listar problemas.

**Independent Test**: sala com um questionário de 1 tentativa, data de encerramento incorreta e pesquisa obrigatória
ausente; após corrigir as tentativas e reauditar, a evolução mostra 1 resolvida e 2 persistentes.

**Acceptance Scenarios**:

1. **Given** três problemas na execução 1, **When** um é corrigido e a sala é reauditada, **Then** a execução 2 mostra
   1 resolvido e 2 persistentes, identificados pela mesma chave nas duas execuções.
2. **Given** uma regra que produz várias ocorrências na mesma atividade ou no mesmo local (ex.: três blocos
   obrigatórios ausentes), **When** apenas um bloco é adicionado, **Then** exatamente essa ocorrência é resolvida e as
   outras persistem, independentemente da ordem ou do texto da evidência.
3. **Given** um problema novo introduzido na sala, **When** ela é reauditada, **Then** ele aparece como novo.
4. **Given** uma ocorrência que passou de `ATENCAO` para `NAO_CONFORME` (ou mudou de gravidade), **When** comparada,
   **Then** aparece como "estado ou gravidade alterados", com os dois valores.
5. **Given** uma regra que terminou em `ERRO` ou ficou não verificável na nova execução, **When** comparada, **Then**
   suas ocorrências antes problemáticas aparecem como "não verificáveis agora", nunca como resolvidas, e a execução
   é "parcial" quando houver `ERRO`.
6. **Given** a política mudou entre as execuções numa chave lida por uma regra, **When** comparada, **Then** as
   ocorrências daquela regra que surgiram ou sumiram aparecem como "não comparáveis (política alterada)", e as das
   demais regras são classificadas normalmente.
7. **Given** a atividade com problema foi removida, **When** comparada, **Then** a ocorrência aparece como "recurso
   removido"; **Given** foi trocada por outra de mesmo tipo e nome com o mesmo problema, **Then** aparece como
   "recurso substituído", não como resolvida + nova.
8. **Given** correções assistidas aplicadas entre as duas execuções, **When** a pessoa abre a evolução, **Then** vê
   essas correções listadas a partir da trilha da Spec 002, com o aviso de que a relação com as ocorrências é apenas
   temporal.

---

### User Story 3 - Auditar um conjunto de salas em segundo plano (Priority: P1)

O gestor das reofertas escolhe as salas (categoria, lista de ids, ano de início ou seleção no painel), solicita a
auditoria e acompanha o andamento do lote enquanto o Moodle audita as salas em segundo plano.

**Why this priority**: auditar dezenas de salas uma a uma não é viável; o lote alimenta o painel.

**Independent Test**: lote com as salas A (sem inconformidades), B (com bloqueantes) e um id inexistente; após a fila
rodar, o lote mostra A e B concluídas com os resultados corretos e o id inexistente recusado com o motivo.

**Acceptance Scenarios**:

1. **Given** uma categoria com salas conformes e não conformes, **When** o gestor solicita o lote, **Then** cada sala
   recebe uma execução "na fila", processada em segundo plano, e o lote mostra o andamento (na fila, em execução,
   concluídas, parciais, falhas).
2. **Given** uma sala do lote cuja auditoria lança exceção fora das regras, **When** o lote roda, **Then** só essa
   execução falha (com o erro registrado) e as demais concluem.
3. **Given** uma sala que já tem execução na fila, **When** outro lote a inclui, **Then** nenhuma execução duplicada é
   criada; o segundo lote aponta para a execução existente.
4. **Given** uma tarefa entregue duas vezes ou retomada após interrupção, **When** executa, **Then** a execução não é
   duplicada nem regravada depois de terminada; após 3 interrupções falha como "interrompida".
5. **Given** a permissão do solicitante sobre uma sala foi revogada antes da execução, **When** a tarefa roda, **Then**
   a execução falha como "permissão revogada" e a sala não é auditada.
6. **Given** uma sala removida depois da solicitação, **When** a tarefa roda, **Then** a execução falha como "sala
   indisponível", sem interromper o lote.

---

### User Story 4 - Painel de acompanhamento (Priority: P1)

O gestor abre o painel central e vê, para as salas do filtro, quantas foram auditadas, quantas não foram, quais têm
bloqueantes, quais requerem correções, quais não têm inconformidades detectadas, quais têm auditoria incompleta e
quais estão desatualizadas, além da evolução das pendências; filtra por categoria, curso, situação, gravidade e
período; abre o relatório e o histórico de cada sala; seleciona salas e solicita o lote.

**Why this priority**: é o critério principal de sucesso: saber onde agir sem abrir sala por sala.

**Independent Test**: com A auditada, B auditada e C nunca auditada, o painel mostra 3 selecionadas, 2 auditadas,
1 não auditada, 1 com bloqueantes, 1 sem inconformidades detectadas, e os filtros por situação isolam cada grupo.

**Acceptance Scenarios**:

1. **Given** salas em todas as situações, **When** o painel abre, **Then** cada sala aparece em exatamente uma
   situação: não auditada, auditoria incompleta, com bloqueantes, requer correções, sem inconformidades detectadas;
   "desatualizada" é um indicador adicional com motivo.
2. **Given** os filtros de categoria, curso, situação, gravidade (salas com inconformidade daquela gravidade) e período
   da última auditoria, **When** aplicados, **Then** contagens e tabela refletem só as salas filtradas.
3. **Given** o painel, **When** a pessoa procura a palavra "aprovado" ou um percentual de conformidade, **Then** não
   encontra; o aviso de escopo das regras está visível.
4. **Given** uma sala cuja última execução tem evolução, **When** o painel mostra a sala, **Then** exibe resolvidas,
   novas e persistentes desde a execução anterior, com link para a evolução detalhada.

---

### User Story 5 - Permissões, privacidade e retenção (Priority: P2)

O sistema impede acesso indevido, guarda o mínimo de dados pessoais e não retém o histórico indefinidamente.

**Why this priority**: requisito de instalação em produção (constituição VIII), mas não muda o fluxo principal.

**Independent Test**: um professor editor da sala A abre o histórico de A, recebe acesso negado ao histórico de B e ao
painel; um gerente vê ambos; a limpeza remove execuções antigas e mantém a última de cada sala.

**Acceptance Scenarios**:

1. **Given** um usuário sem `local/mooc_audit:audit` numa sala, **When** tenta abrir o histórico, uma execução ou a
   evolução daquela sala (inclusive trocando o id da execução na URL), **Then** o acesso é negado.
2. **Given** um usuário sem `viewdashboard`, **When** abre o painel ou a página de um lote, **Then** o acesso é negado;
   **Given** sem `batchaudit`, **Then** não vê nem consegue enviar a solicitação de lote.
3. **Given** um gestor com `batchaudit` mas sem `audit` em uma sala selecionada, **When** solicita o lote, **Then**
   essa sala é recusada com o motivo e as demais seguem.
4. **Given** um pedido do privacy provider, **When** exporta ou apaga os dados de um usuário, **Then** exporta as
   execuções e os lotes que ele solicitou e, ao apagar, anonimiza o solicitante sem apagar o histórico.
5. **Given** execuções mais antigas que a retenção, **When** a limpeza diária roda, **Then** são removidas, exceto a
   última execução válida de cada sala existente; lotes vazios e políticas sem referência também são removidos.

---

### User Story 6 - Medir e operar (Priority: P3)

A equipe técnica sabe quantas salas o laboratório audita por lote, com que tempo, memória e impacto no banco, e tem
documentação para operar a fila, a retenção e a reexecução de falhas.

**Why this priority**: necessária para a homologação, não para o uso diário.

**Independent Test**: lote de dezenas de salas sintéticas (incluindo a sala de 151 módulos) processado pela fila
local; tempos, memória e tamanho das execuções registrados em `results.md`.

**Acceptance Scenarios**:

1. **Given** um lote de 40+ salas, **When** processado pela fila, **Then** o tempo por sala, o pico de memória, o
   tamanho médio de uma execução e o número de consultas por auditoria são registrados.
2. **Given** execuções com falha num lote, **When** o gestor usa "Reexecutar falhas", **Then** novas execuções são
   criadas só para as salas cuja última execução do lote falhou; as falhas antigas permanecem no histórico.

### Edge Cases

- Sala removida: o histórico guardado continua consultável pelo painel (nome guardado no relatório); a sala aparece
  como "indisponível", sem link para auditar; novas execuções falham com esse motivo.
- Primeira execução de uma sala: sem comparação; a evolução diz "primeira auditoria registrada".
- Execução anterior que falhou: não serve de base; a comparação usa a última concluída ou parcial.
- Execução anterior parcial: regras que terminaram em `ERRO` nela tornam suas ocorrências atuais "não comparáveis".
- Política alterada só no ano de reoferta (virada de ano): só as regras do ciclo de oferta ficam não comparáveis.
- Atualização do plugin entre execuções: regras com código alterado ficam não comparáveis; aviso de versão diferente.
- Duas pessoas abrindo a auditoria da mesma sala ao mesmo tempo: duas execuções (pedidos distintos), nunca uma
  execução corrompida.
- Lote com zero salas válidas: recusado com mensagem; nada é enfileirado.
- Lote acima do limite de salas: recusado com mensagem indicando o limite.
- Cron parado: execuções ficam "na fila"; o painel mostra quantas aguardam e há quanto tempo.
- Sala enorme (150+ módulos): concluída dentro da meta de tempo; tamanho da execução medido.
- Relatório guardado de uma versão antiga do plugin (sem chave de ocorrência): o histórico exibe, a evolução declara
  "não comparável (versão sem identificação de ocorrências)".

## Requirements *(mandatory)*

### Functional Requirements

**Histórico (prioridade máxima)**

- **FR-001**: Toda auditoria iniciada pela página da sala, por um lote ou pela CLI com registro MUST gerar uma
  execução persistida com: identificador, sala, data e hora de criação, início e término, solicitante (usuário ou 0 =
  processo), origem, versão do plugin, versão do contrato, referência à política aplicada (versão declarada e
  impressão digital do conteúdo), ano de reoferta, estado, resultado geral, contagens por estado e por gravidade,
  resultados estruturados, comparação com a execução anterior e erros/limitações.
- **FR-002**: A política efetivamente aplicada MUST ser guardada com conteúdo completo uma única vez por impressão
  digital e referenciada pelas execuções.
- **FR-003**: Uma execução em estado final (concluída, parcial, falhou) MUST NOT ser alterada; reexecutar MUST criar
  nova execução.
- **FR-004**: A data de execução MUST ser distinta da data da oferta; o histórico MUST mostrar o ano de reoferta e o
  período da sala separadamente da data da auditoria.
- **FR-005**: O relatório individual MUST mostrar o resumo da evolução desde a execução anterior e um link para o
  histórico da sala.
- **FR-006**: O histórico MUST listar as execuções da sala (mais recente primeiro) e permitir abrir o relatório
  guardado de qualquer uma delas, sem recalcular.
- **FR-007**: A reauditoria interna da correção assistida MUST NOT gerar execução no histórico.

**Identidade e evolução**

- **FR-008**: Todo resultado MUST ter uma chave de ocorrência `regra|entidade|verificação[|discriminador]` construída
  de dados estruturados, nunca do texto da evidência, do idioma ou da posição.
- **FR-009**: A chave MUST ser única dentro de uma execução; um teste MUST verificar isso nas salas de referência e
  nos cenários de teste.
- **FR-010**: A comparação entre duas execuções da mesma sala MUST classificar cada ocorrência problemática em uma das
  classes: nova, persistente, resolvida, estado/gravidade alterados, não verificável agora, não comparável, recurso
  removido, recurso substituído.
- **FR-011**: Uma ocorrência MUST ser "resolvida" somente quando a regra executou normalmente (sem `ERRO` nem
  não verificável no nível da regra ou da ocorrência), a entidade ainda existe e as impressões digitais da regra
  (fatia da política e implementação) são iguais nas duas execuções.
- **FR-012**: Ocorrências que surgem ou somem numa regra cuja fatia de política ou implementação mudou MUST ser "não
  comparáveis", com o motivo.
- **FR-013**: Cada regra MUST declarar todas as chaves de política que lê; um teste MUST perturbar cada chave e
  verificar a declaração.
- **FR-014**: A base de comparação MUST ser a última execução concluída ou parcial anterior; execuções que falharam
  MUST NOT servir de base.
- **FR-015**: A evolução MUST listar as correções da trilha da Spec 002 aplicadas na sala entre as duas execuções, com
  aviso de relação não comprovada, sem duplicar a trilha.

**Lote**

- **FR-016**: O gestor MUST poder selecionar salas por categoria (com/sem subcategorias), lista de ids, ano de início
  e seleção no painel, com prévia das salas aceitas e recusadas (motivo) antes de confirmar.
- **FR-017**: A solicitação MUST exigir `local/mooc_audit:batchaudit` (sistema) e, por sala, `local/mooc_audit:audit`;
  salas sem permissão, inexistentes ou o curso do site MUST ser recusadas e listadas.
- **FR-018**: Cada sala aceita MUST ser auditada por uma tarefa ad hoc do Moodle executada em nome do solicitante, que
  MUST reconferir as permissões no momento da execução.
- **FR-019**: O sistema MUST evitar execuções simultâneas equivalentes (mesma sala na fila ou em execução) e MUST
  impedir duas auditorias simultâneas da mesma sala.
- **FR-020**: A concorrência das tarefas MUST ser limitada por configuração (padrão 2) e o tamanho do lote por
  configuração (padrão 300).
- **FR-021**: A falha de uma sala MUST NOT interromper o lote; erros MUST ser registrados na execução.
- **FR-022**: Tarefas repetidas MUST ser idempotentes; tarefas interrompidas MUST ser retomadas até 3 tentativas e
  depois marcadas como falha "interrompida"; execuções presas sem tarefa MUST ser marcadas como falha pela limpeza.
- **FR-023**: A página do lote MUST mostrar critérios, solicitante, data, salas recusadas, andamento por estado e o
  resultado de cada sala, e MUST oferecer "Reexecutar falhas" a quem tem `batchaudit`.

**Painel**

- **FR-024**: O painel MUST mostrar, para as salas do filtro: selecionadas, auditadas, não auditadas, com bloqueantes,
  requer correções, sem inconformidades detectadas, auditoria incompleta, desatualizadas, data da última auditoria e
  evolução somada (resolvidas, novas, persistentes).
- **FR-025**: O painel MUST filtrar por categoria, texto do curso, situação, gravidade e período da última auditoria,
  e MUST listar só salas em que o usuário tem `local/mooc_audit:audit`.
- **FR-026**: A partir do painel o usuário MUST poder abrir o relatório e o histórico de cada sala e, com
  `batchaudit`, selecionar salas e solicitar o lote.
- **FR-027**: O painel MUST NOT usar "aprovado", selo de conformidade, percentual de conformidade ou nota; MUST exibir
  o aviso de escopo das regras.
- **FR-028**: Uma sala MUST ser marcada como desatualizada, com motivo, quando a última execução válida excede o
  prazo configurado, usou política diferente da vigente ou outra versão do plugin.

**Segurança, privacidade e retenção**

- **FR-029**: Histórico, execução e evolução de uma sala MUST exigir `local/mooc_audit:audit` no contexto do curso
  daquela sala; o id da execução na URL MUST ser conferido contra o curso.
- **FR-030**: Painel e páginas de lote MUST exigir `local/mooc_audit:viewdashboard`; ações de lote MUST exigir
  `local/mooc_audit:batchaudit` e `sesskey`.
- **FR-031**: Auditorias, inclusive em lote, MUST continuar somente leitura em relação às salas (teste de não
  modificação estendido).
- **FR-032**: As execuções guardadas MUST NOT conter dados de estudantes; o único dado pessoal é o solicitante.
- **FR-033**: O privacy provider MUST declarar, exportar e anonimizar o solicitante das execuções e dos lotes.
- **FR-034**: A retenção MUST ser configurável (padrão 365 dias, mínimo 30, máximo 1825), aplicada por tarefa
  agendada, preservando a última execução válida de cada sala existente.

**Operação**

- **FR-035**: A CLI MUST permitir registrar a auditoria de uma sala e enfileirar ou executar um lote (para operação e
  medição).
- **FR-036**: A documentação operacional MUST cobrir cron/fila, concorrência, retenção, reexecução de falhas,
  desempenho medido e decisões pendentes.

### Key Entities

- **Execução de auditoria (AuditRun)**: uma auditoria de uma sala; estado, solicitante, origem, versões, política,
  ano de reoferta, resultado geral, resumo, relatório guardado, impressões digitais por regra, inventário de
  entidades (atividades e seções), comparação com a anterior, erros, tentativas e datas.
- **Lote (AuditBatch)**: um pedido de auditoria de várias salas; solicitante, nome, critérios, salas recusadas e
  reaproveitadas, data. Andamento e resultado são derivados das execuções do lote (não repetidos).
- **Política aplicada (AppliedPolicy)**: conteúdo efetivo da política, versão declarada e impressão digital; uma por
  conteúdo distinto.
- **Ocorrência (Occurrence)**: um resultado identificado pela chave estruturada; não é tabela própria: existe dentro
  dos resultados guardados de cada execução.
- **Evolução (Evolution)**: classificação das ocorrências entre uma execução e a anterior; contagens guardadas na
  execução, itens calculados ao abrir a página.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: No cenário do pedido (3 problemas; 1 corrigido), a evolução mostra exatamente 1 resolvido e 2
  persistentes, em 100% das execuções do teste.
- **SC-002**: Nas salas de referência e cenários, 0 colisões de chave de ocorrência e 0 mudanças de classificação
  quando a ordem dos módulos ou o idioma da interface mudam.
- **SC-003**: Uma mudança de política restrita a uma regra produz 0 ocorrências "novas" ou "resolvidas" nessa regra
  (todas "não comparáveis") e não altera a classificação das demais regras.
- **SC-004**: Um lote de 40 salas com uma falha forçada conclui as outras 39; 0 execuções duplicadas com tarefas
  entregues em dobro.
- **SC-005**: No laboratório, a auditoria de uma sala típica no lote leva no máximo 5 s e a sala de 151 módulos no
  máximo 15 s; o pico de memória por tarefa fica abaixo de 256 MB; valores medidos registrados.
- **SC-006**: O painel com 100 salas abre em no máximo 3 s no laboratório.
- **SC-007**: Usuários sem a capacidade correspondente recebem acesso negado em 100% das páginas e ações novas
  (testes automatizados por página).
- **SC-008**: Um gestor identifica, só pelo painel, quais salas têm bloqueantes, quais têm problemas abertos e quais
  tiveram problemas resolvidos desde a auditoria anterior — verificado no roteiro de homologação **[validar]** com a
  equipe de reofertas.

## Assumptions

- O cron do Moodle está ativo no ambiente de produção; sem cron os lotes ficam na fila (documentado).
- A data de início do curso é o único indicador confiável de ciclo disponível no Moodle; "ano de início" é usado como
  critério de seleção, sem inferir ciclo a partir de nomes ou siglas.
- A sala de teste de 151 módulos (Spec 003) representa o pior caso conhecido de tamanho.
- Retenção, prazo de desatualização, registro de toda abertura da página e escopo de gerentes de categoria são
  decisões institucionais pendentes; os padrões adotados são conservadores e configuráveis.
- O contrato JSON 1.0 aceita propriedades adicionais nos resultados; o campo `occurrence` é aditivo.
- A comparação entre ofertas (Spec 003) continua com sua própria chave de local; adotar a chave de ocorrência nela é
  melhoria futura, fora deste escopo.
