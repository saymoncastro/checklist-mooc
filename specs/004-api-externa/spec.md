# Feature Specification: API Externa (Web Services) do Auditor MOOC

**Feature Branch**: `claude/spec-004-api-externa-96cf46` (diretório `specs/004-api-externa/`)

**Created**: 2026-10-30

**Status**: Implementada (plugin 0.6.0, 2026-10-30). Os itens marcados **[validar]** (D1–D3: capacidade, serviço,
limite da fila) foram aprovados pelo responsável do projeto em 2026-10-30 e registrados como decisão 13.

**Base**: `main` em 5fe93b3 (Spec 005 mesclada pelo PR #5; plugin 0.5.0, versão 2026103001). Especificada sobre a
branch da Spec 005 em d211191; o que entrou depois no PR #5 (bc96235) só altera a CI. Esta especificação depende do
histórico persistido da Spec 005 (execuções, política aplicada, chave de ocorrência, evolução, fila em segundo plano).
Não cria outro motor, outra fila nem outro formato de relatório.

**Input**: User description: "Spec 004 — API externa do plugin local_mooc_audit. Web Services nativos do Moodle,
autenticados por token, respeitando capacidades. Três operações: (1) solicitar auditoria de uma sala — enfileira uma
execução (origem própria `api`), devolve o id da execução, nunca executa síncrono pesado, reaproveita execução
pendente viva; (2) consultar execução por id — estado, metadados, resumo, evolução e relatório guardado (contrato 1.0 +
`occurrence`), conferindo `local/mooc_audit:audit` no curso da execução; (3) consultar a última auditoria de uma sala
sem reexecutar (mesma regra do GET de `index.php`: última execução válida, com motivos de desatualização). Sem
correções automáticas; MOOC Admin é só cliente opcional. Avaliar capacidade própria vs. reaproveitar `audit`/
`batchaudit`, versionamento do contrato da API, limites (fila, tamanho), privacy provider (nova origem), testes das
funções (acesso negado, curso de outro escopo, execução inexistente) e documentação para o consumidor."

## Contexto

Desde a Spec 005 o Auditor MOOC guarda cada auditoria solicitada como uma **execução** imutável, com a política
aplicada, o relatório no contrato JSON 1.0 (cada resultado com a chave `occurrence`) e a evolução em relação à
execução anterior. Abrir a página de auditoria apenas **consulta** a última execução guardada (decisão 12.1); executar
é sempre uma ação explícita.

Outros sistemas do Cefor — em primeiro lugar o MOOC Admin, que cria as salas das reofertas — querem saber, sem que
uma pessoa abra a página, **se a sala recém-criada já foi auditada, qual foi o resultado da última auditoria, se esse
resultado está desatualizado** e, quando necessário, **pedir uma nova auditoria e acompanhar até o fim**. Hoje a única
saída para máquinas é `index.php?format=json`, que exige sessão de navegador, não permite pedir uma auditoria e não
diz se o resultado está desatualizado.

Três condições orientam a especificação:

1. **Independência (constituição I).** A API é um canal a mais de consulta e de solicitação; o plugin continua
   auditando sem ela, e nenhum dado de outro sistema é pré-requisito. O MOOC Admin é um cliente opcional.
2. **Mesmas regras de acesso das páginas (constituição VIII).** Um token não pode ver nem pedir mais do que a pessoa
   (ou conta de serviço) dona do token veria pelas páginas; habilitar a API é uma decisão explícita do administrador.
3. **Nada pesado nem nada escrito na sala dentro da chamada (constituição III e IX).** Pedir uma auditoria só
   enfileira; a auditoria roda na mesma fila em segundo plano dos lotes. Consultar nunca executa nem grava.

**Fora de escopo**: qualquer função de escrita em salas; correção assistida ou automática pela API (preparar ou
aplicar); auditoria em lote pela API (lotes continuam na página e na CLI da Spec 005); painel consolidado pela API;
listagem do histórico completo de uma sala; comparação entre ofertas (Spec 003) pela API; notificações ativas
(webhooks, mensagens, e-mail) quando uma execução termina — o cliente consulta; escolha explícita do ciclo (ano de
reoferta) na solicitação; referência própria do cliente gravada na execução; localizar sala por sigla ou `idnumber` (o cliente usa as funções nativas do Moodle para isso);
integração implementada no lado do MOOC Admin; limitação de taxa por tempo (requisições por minuto); autenticação
diferente da nativa do Moodle (OAuth, chaves próprias); inteligência artificial; serviços externos.

## Clarifications

### Session 2026-10-30

Decisões do autor da especificação para não bloquear o planejamento, no mesmo formato das Specs 001–005.
**[validar]** = exige confirmação do responsável do projeto antes da implementação; as demais são premissas
registradas.

- Q: Quem pode usar a API? → A: uma **capacidade nova `local/mooc_audit:api`** no contexto de sistema, **sem arquétipo
  padrão** (ninguém a recebe na instalação), exigida por todas as funções, **somada** às regras de acesso das páginas:
  `local/mooc_audit:audit` no contexto da sala (e acesso ao curso, como a página exige); para o histórico de uma sala
  removida, `local/mooc_audit:viewdashboard` no sistema, como na Spec 005. Alternativas descartadas: (a) só
  `audit` — qualquer professor editor com token de Web Services passaria a enfileirar auditorias por máquina, sem
  decisão do administrador; (b) `batchaudit` — tem outro significado (lotes, gerentes) e não é necessária para uma sala
  só. Com a capacidade própria, o administrador concede o uso da API a uma **conta de integração** (papel de sistema
  documentado), e professores e gerentes não ganham nada novo. **[validar]**
- Q: Como a API é exposta? → A: um **serviço externo próprio "Auditor MOOC"** com as três funções, criado
  **desabilitado** e **restrito a usuários autorizados**; o administrador habilita o serviço, autoriza a conta de
  integração e gera o token pelo mecanismo nativo do Moodle. As funções também podem ser acrescentadas a serviços
  personalizados do site; as conferências de capacidade valem em qualquer serviço. **[validar]**
- Q: O que acontece ao pedir uma auditoria? → A: a chamada confere o acesso, e (1) se a sala já tem uma execução **na
  fila ou em execução que ainda está viva** (de qualquer origem: página não, porque é síncrona; lote, CLI ou API),
  devolve essa execução, marcada como **reaproveitada**, sem criar outra; (2) senão cria uma execução **na fila** com
  origem **`api`**, solicitante = dono do token, e a tarefa em segundo plano, e devolve o id. A chamada nunca executa a
  auditoria. Um resultado recente e válido **não** impede a nova execução: pedir é explícito, como o botão "Executar
  novamente".
- Q: A permissão é conferida de novo quando a tarefa roda? → A: sim, como nos lotes: no momento da execução a tarefa
  confere, para o solicitante, conta ativa, `local/mooc_audit:api` no sistema e `local/mooc_audit:audit` na sala. Se
  algo foi revogado, a execução falha com motivo `permission` e nada é auditado. Trava por sala, concorrência,
  retomada após interrupção e limpeza de execuções presas são as da Spec 005, sem mudança.
- Q: Que limites protegem a fila? → A: (1) reaproveitamento da execução viva (no máximo uma pendente por sala); (2) no
  máximo **100 execuções pendentes de origem `api` por solicitante** (configurável, 1–2000: vazio ou ≤ 0 vale 100,
  acima de 2000 vale 2000, como o limite de salas por lote; mesmo valor de partida da decisão 12.5); ao exceder, a chamada falha com erro próprio (`queuefull`) e nada é criado; reaproveitar não conta;
  (3) a concorrência da fila continua a configuração da Spec 005 (padrão 2). Não há limite de requisições por minuto
  nesta versão. **[validar]**
- Q: Qual ciclo (ano de reoferta) uma auditoria pedida pela API usa? → A: o da política vigente, como a página. O
  ciclo explícito fica para uma versão futura, se o MOOC Admin precisar.
- Q: O que a consulta de uma execução devolve? → A: metadados (id, sala, origem, estado, datas de criação, início e
  término, tentativas, versão do plugin, versão do contrato do relatório, versão e impressão digital da política, ano
  de reoferta, resultado geral), resumo, erros e limitações, evolução guardada (execução de base, contagens por
  classe, notas) e, para execução concluída ou parcial, o **relatório guardado exatamente como foi gravado** (contrato
  1.0 + `occurrence`). Opções: omitir o relatório (padrão: incluir) e incluir os **itens** da evolução, calculados
  como na página de histórico contra a execução de base, se ela ainda existir (padrão: não incluir). A resposta **não**
  identifica o solicitante (minimização de dados).
- Q: Como responder a uma execução que a pessoa não pode ver? → A: execução inexistente e execução de uma sala fora
  do escopo da pessoa recebem **a mesma resposta** (`runnotfound`), para não confirmar a existência de execuções de
  outras salas. Já a falta de `local/mooc_audit:api` (sistema) é informada como falta de permissão. Para operações
  por sala, segue-se o comportamento da página: sala inexistente ou o curso do site → `invalidcourseid`; sala
  existente sem permissão → falta de permissão.
- Q: O que a consulta da última auditoria devolve? → A: a mesma regra do GET de `index.php`: a **última execução
  válida** (concluída ou parcial, pela data de término), com os **motivos de desatualização calculados no momento da
  consulta** (sala alterada, idade, política mudou, versão do plugin mudou), a **falha mais recente** quando for
  posterior a ela e a **execução pendente viva**, se houver. Sala nunca auditada não é erro: a resposta diz que não há
  execução válida e mostra pendente/falha, se houver. Os textos e a documentação falam em "resultado da última
  auditoria", com data, nunca em conformidade atual (decisão 12.3).
- Q: Como a API é versionada? → A: a API tem versão própria (**`api_version` 1.0**), independente da versão do
  contrato do relatório (`contract_version` 1.0); toda resposta traz as duas. Dentro de 1.x só entram mudanças
  aditivas (campo opcional novo, parâmetro opcional novo com padrão que preserva o comportamento). Mudança
  incompatível cria **funções com nome novo** (sufixo de versão), mantidas em paralelo com as antigas por pelo menos
  uma versão do plugin, com nota de migração e marcação de obsolescência das antigas. Uma nova versão do contrato do
  relatório só chega à API com nota de migração (constituição X).
- Q: Como entregar o relatório, que é grande e aninhado? → A: os metadados têm estrutura declarada e tipada; o
  relatório é entregue como **documento JSON** do contrato 1.0, validável pelo esquema já publicado
  (`schema/report-v1.0.schema.json`), em vez de replicar todo o esquema na declaração das funções. Relatório medido
  na Spec 005: média 29 kB, máximo 41 kB (sala de 151 módulos); não há paginação nesta versão.
- Q: Que dados pessoais a API acrescenta? → A: nenhum campo novo: a execução pedida pela API guarda o solicitante na
  mesma coluna das demais, com a nova origem `api`. O privacy provider passa a descrever a origem `api` e o teste de
  exportação/anonimização a cobre. Tokens, serviços e o registro das chamadas são do núcleo do Moodle. As respostas
  não trazem identificação de pessoas; os relatórios já não trazem segredos nem dados de estudantes (Spec 001).
- Q: Onde a origem `api` aparece? → A: onde a interface já mostra a origem — histórico da sala, página da execução e
  resumo da última auditoria na página da sala —, com o rótulo "API" (pt_br e en). O painel e as páginas de lote não
  mostram origem e não mudam. A retenção da Spec 005 vale igualmente para ela.

Revisão de ambiguidades (`/speckit-clarify`, mesma sessão). Esta especificação foi preparada por uma sessão
delegada, sem o responsável disponível para responder perguntas uma a uma; as respostas abaixo são as recomendações
do autor, e as perguntas de maior impacto já estão marcadas **[validar]** acima (capacidade, serviço, limite da
fila).

- Q: A API exige o mesmo "acesso ao curso" que a página exige, inclusive para salas ocultas? → A: sim. A função valida
  o contexto da sala como a página (`require_login` no curso): a conta de integração precisa poder entrar na sala e ver
  salas ocultas (permissões nativas de ver curso e ver cursos ocultos, atribuídas no papel de sistema documentado). Uma
  verificação só de capacidade, sem o acesso ao curso, daria à API mais do que a página dá.
- Q: O cliente pode mandar uma referência própria (id do pedido no MOOC Admin) para correlacionar com a execução? →
  A: não nesta versão. O cliente correlaciona pelo id da execução devolvido; um campo de referência exigiria guardar
  dado do cliente e entra, se necessário, como mudança aditiva da API 1.x.
- Q: A API cria evento ou registro próprio do plugin para cada chamada? → A: não. O núcleo do Moodle já registra as
  chamadas de Web Services; a solicitação deixa a execução com solicitante e origem `api`, que é o registro
  institucional. Nenhum evento novo nesta versão.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar a última auditoria de uma sala (Priority: P1)

Um sistema integrado (ex.: MOOC Admin, depois de criar a sala da reoferta) quer saber, pela conta de integração, qual
foi o resultado da última auditoria daquela sala, quando ela foi feita e se o resultado ainda pode ser usado, sem
disparar nenhuma auditoria.

**Why this priority**: sozinha já entrega valor (o cliente lê resultados que a equipe produz pela página, lote ou CLI)
e não escreve nada; é o MVP da integração.

**Independent Test**: auditar uma sala pela página; chamar a consulta da última auditoria com o token da conta de
integração; comparar o relatório devolvido com o `index.php?format=json` da mesma sala e conferir que nenhuma execução
ou tarefa foi criada.

**Acceptance Scenarios**:

1. **Given** uma sala com uma execução concluída e sem mudanças desde então, **When** a conta de integração consulta
   a última auditoria, **Then** recebe a execução (id, data, estado, resultado geral, resumo), o relatório guardado
   idêntico ao da página e a lista de motivos de desatualização vazia.
2. **Given** a mesma sala alterada (uma atividade editada) depois da auditoria, **When** consulta de novo, **Then** o
   motivo "sala alterada" aparece, e o resultado devolvido continua o mesmo, com a data original.
3. **Given** uma sala cuja execução mais recente falhou depois de uma execução parcial, **When** consulta, **Then**
   recebe a parcial como última válida e a falha mais recente com o motivo.
4. **Given** uma sala nunca auditada, **When** consulta, **Then** a resposta indica que não há execução válida (sem
   erro), e informa a execução pendente, se houver.
5. **Given** qualquer consulta, **When** ela termina, **Then** o número de execuções e de tarefas na fila é o mesmo de
   antes (consulta não grava).

---

### User Story 2 - Pedir uma auditoria e acompanhar até o fim (Priority: P1)

O sistema integrado pede uma nova auditoria de uma sala, recebe imediatamente o identificador da execução e consulta
esse identificador de tempos em tempos até a execução terminar, para então ler o relatório.

**Why this priority**: é o que o MOOC Admin precisa logo após criar ou alterar a sala; sem isso o cliente depende de
alguém clicar em "Executar auditoria".

**Independent Test**: pedir a auditoria de uma sala; conferir que a resposta chega sem a auditoria ter rodado (estado
"na fila"); rodar a fila de tarefas; consultar a execução e encontrar estado "concluída" com relatório e origem `api`.

**Acceptance Scenarios**:

1. **Given** uma sala sem execução pendente, **When** a conta de integração pede a auditoria, **Then** recebe o id de
   uma execução nova, no estado "na fila", origem `api`, indicação "não reaproveitada", e nenhuma regra foi executada
   durante a chamada.
2. **Given** essa execução, **When** a fila de tarefas roda e o cliente consulta o id, **Then** o estado é "concluída"
   (ou "parcial"), o relatório guardado está presente e a evolução em relação à execução válida anterior também.
3. **Given** uma sala com execução de lote ainda na fila, **When** a conta de integração pede a auditoria, **Then**
   recebe o id dessa execução, marcada "reaproveitada", e nenhuma execução nova é criada.
4. **Given** duas chamadas seguidas para a mesma sala, **When** a primeira ainda está na fila, **Then** a segunda
   devolve o mesmo id (reaproveitada).
5. **Given** que a capacidade de API da conta é removida entre o pedido e a execução da tarefa, **When** a tarefa
   roda, **Then** a execução termina "falhou" com motivo `permission` e a sala não é auditada.
6. **Given** a conta com 100 execuções `api` pendentes (limite padrão), **When** pede mais uma para outra sala,
   **Then** recebe o erro `queuefull` e nada é criado; pedir a auditoria de uma sala que já tem pendente viva continua
   devolvendo a pendente.

---

### User Story 3 - Consultar uma execução por identificador (Priority: P1)

O sistema integrado consulta qualquer execução de uma sala que pode ver — a que pediu, uma de lote ou uma de página —
para mostrar ao usuário dele o estado, as datas, o resultado guardado e a evolução.

**Why this priority**: completa o acompanhamento da US2 e permite ao cliente guardar só o id e reler o registro
imutável depois.

**Independent Test**: criar execuções de duas salas (uma no escopo e outra fora); consultar cada id; conferir dados,
opções de omitir relatório e de incluir itens da evolução, e que a execução fora do escopo responde como inexistente.

**Acceptance Scenarios**:

1. **Given** uma execução concluída com execução anterior válida, **When** consulta pedindo os itens da evolução,
   **Then** recebe metadados, resumo, contagens, notas e os itens classificados (nova, persistente, resolvida …) iguais
   aos da página de histórico.
2. **Given** a mesma execução, **When** consulta pedindo para omitir o relatório, **Then** a resposta não traz o
   relatório e traz todo o resto.
3. **Given** uma execução "falhou", **When** consulta, **Then** recebe estado, código e mensagem do erro e nenhum
   relatório.
4. **Given** uma execução "na fila" ou "em execução", **When** consulta, **Then** recebe estado, datas e tentativas, sem
   relatório.
5. **Given** um id que não existe e um id de execução de sala em que a conta não tem `audit`, **When** consulta cada
   um, **Then** as duas respostas são o mesmo erro `runnotfound`.
6. **Given** a execução de uma sala removida, **When** consulta uma conta com `viewdashboard`, **Then** recebe a
   execução guardada; sem `viewdashboard`, recebe `runnotfound`.

---

### User Story 4 - Acesso sob controle do administrador (Priority: P2)

O administrador do Moodle decide se a API existe no site e quem a usa: habilita o serviço, cria a conta de
integração com o papel documentado e gera o token. Quem não foi autorizado não usa a API, mesmo tendo `audit` em salas.

**Why this priority**: é a condição para instalar a API em produção (constituição VIII); as US1–US3 dependem dela para
serem seguras, mas podem ser demonstradas antes com uma conta administradora em ambiente de teste.

**Independent Test**: instalar o plugin e conferir o serviço desabilitado; com um professor editor com token de outro
serviço que inclua as funções, chamar cada uma e receber falta de permissão; atribuir o papel de integração e repetir
com sucesso.

**Acceptance Scenarios**:

1. **Given** o plugin recém-instalado, **When** o administrador olha os serviços externos, **Then** o serviço "Auditor
   MOOC" existe, desabilitado e restrito a usuários autorizados, e nenhum papel tem `local/mooc_audit:api`.
2. **Given** um professor editor (tem `audit` na sala) sem `local/mooc_audit:api`, **When** chama qualquer função,
   **Then** recebe falta de permissão e nada é gravado.
3. **Given** uma conta de integração com `api` e `audit` apenas na categoria A, **When** pede ou consulta uma sala da
   categoria B, **Then** recebe falta de permissão (sala) ou `runnotfound` (execução).
4. **Given** o serviço desabilitado, **When** a equipe usa a página, o lote, o painel e a CLI, **Then** tudo funciona
   como antes (independência).

---

### User Story 5 - Documentação e contrato estável para o consumidor (Priority: P2)

A equipe que desenvolve um cliente (ex.: MOOC Admin) configura o acesso, chama as três funções e trata as respostas e
erros lendo apenas a documentação do plugin, e pode confiar que a versão 1.x da API não muda de forma incompatível.

**Why this priority**: a integração é feita por outra equipe, sem acesso ao código; sem contrato documentado a API não
é utilizável nem auditável.

**Independent Test**: uma pessoa que não participou da implementação segue o guia em um ambiente isolado de teste,
configura a conta, pede uma auditoria, acompanha até o fim e lê o relatório, e confere que as respostas batem com o
contrato publicado.

**Acceptance Scenarios**:

1. **Given** o guia do consumidor, **When** a pessoa o segue do zero, **Then** obtém um token e faz as três operações
   sem consultar o código.
2. **Given** qualquer resposta de sucesso, **When** é conferida, **Then** traz `api_version` e `contract_version`, e o
   relatório valida no esquema publicado.
3. **Given** a lista de erros do guia, **When** cada cenário de erro é provocado, **Then** o código recebido é o
   documentado.

---

### Edge Cases

- Sala removida depois do pedido e antes da tarefa: a execução falha como `course_missing` (Spec 005); a consulta da
  execução segue as regras de sala removida (só com `viewdashboard`).
- Curso do site (id 1) ou id inexistente na solicitação ou na última auditoria: `invalidcourseid`.
- Sala oculta (comum na preparação da reoferta): a conta de integração precisa poder ver salas ocultas, como a página
  exige; o papel documentado inclui essa permissão. Sem ela, falta de permissão.
- Conta de integração suspensa ou excluída depois do pedido: a tarefa falha com `permission` (Spec 005).
- Execução pendente "morta" (tarefa perdida ou processo morto além do prazo): não é reaproveitada; o pedido cria uma
  nova; a limpeza diária encerra a morta como `stale`.
- Execução de base da evolução removida pela retenção: a consulta com itens da evolução devolve as contagens
  guardadas e uma nota de que os itens não estão disponíveis.
- Execução anterior à Spec 005 sem `occurrence`: não existe (o histórico começa na 0.5.0); relatório guardado sempre
  tem `occurrence`.
- Relatório com textos de curso em UTF-8 inválido: já substituído na gravação (Spec 005); a API devolve o que foi
  gravado.
- Mesmo cliente pedindo a mesma sala em paralelo (duas chamadas simultâneas): a decisão "reaproveitar ou criar" é
  serializada por sala, de modo que a segunda chamada recebe a execução criada pela primeira (reaproveitada); se a
  serialização não puder ser obtida a tempo, a chamada falha com erro temporário (`busy`) e nada é criado.
- Política inválida no site: a execução termina com o aviso de política nas limitações, como nas demais origens.
- Fila ocupada por um lote grande: as execuções pedidas pela API entram na mesma fila, com a mesma concorrência
  (padrão 2) e sem prioridade; o tempo até o fim cresce com a fila, e o guia do consumidor avisa isso. A chamada de
  solicitação continua respondendo na hora.
- Parâmetros com tipo errado ou ausentes: rejeitados pela validação nativa de parâmetros, com o erro padrão do Moodle.
- Execução de sala para a qual a conta tem `audit`, mas que pertence a um lote de outra pessoa: visível (o acesso é por
  sala, como na página de histórico).

## Requirements *(mandatory)*

### Functional Requirements

**Exposição e acesso**

- **FR-001**: O plugin MUST oferecer três funções de Web Services nativos do Moodle — **solicitar auditoria de uma
  sala**, **consultar execução** e **consultar a última auditoria de uma sala** — agrupadas em um serviço externo
  próprio criado desabilitado e restrito a usuários autorizados, usando a autenticação por token nativa. **[validar]**
- **FR-002**: O plugin MUST NOT oferecer pela API nenhuma função que escreva em salas, prepare ou aplique correções,
  crie lotes ou altere configurações.
- **FR-003**: O plugin MUST definir a capacidade `local/mooc_audit:api` (contexto de sistema, sem arquétipo padrão)
  e toda função MUST exigi-la no contexto de sistema antes de qualquer outra verificação. **[validar]**
- **FR-004**: Além de `local/mooc_audit:api`, as funções MUST aplicar as mesmas regras de acesso das páginas: acesso
  ao curso (validação do contexto da sala como na página, inclusive para salas ocultas) e `local/mooc_audit:audit` no
  contexto da sala para operações sobre uma sala existente;
  `local/mooc_audit:viewdashboard` no sistema para execuções de sala removida.
- **FR-005**: Na consulta de execução, o acesso MUST ser conferido no contexto da sala **da execução guardada**, nunca
  em um contexto informado pelo cliente.
- **FR-006**: Execução inexistente e execução fora do escopo da conta MUST produzir a mesma resposta de erro
  (`runnotfound`). Sala inexistente ou curso do site MUST produzir `invalidcourseid`; sala existente sem permissão
  MUST produzir falta de permissão.

**Solicitar auditoria**

- **FR-007**: A solicitação MUST receber o identificador da sala e, se a sala tem uma execução na fila ou em execução
  ainda viva (qualquer origem, conforme a regra de "pendente viva" da Spec 005), MUST devolvê-la marcada como
  reaproveitada sem criar outra. A decisão entre reaproveitar e criar MUST ser serializada por sala; sem serialização
  a tempo, a chamada MUST falhar com `busy` sem criar nada.
- **FR-008**: Sem pendente viva, a solicitação MUST criar uma execução na fila, com origem `api` e solicitante igual ao
  dono do token, e a tarefa em segundo plano correspondente, e MUST responder sem executar nenhuma regra da auditoria.
- **FR-009**: A resposta da solicitação MUST conter: id da execução, id da sala, estado, indicação de reaproveitada,
  data de criação, `api_version`.
- **FR-010**: Ao executar uma execução de origem `api`, a tarefa MUST conferir de novo, para o solicitante, conta
  ativa, `local/mooc_audit:api` no sistema e `local/mooc_audit:audit` na sala; se falhar, MUST encerrar a execução
  como falhou com motivo `permission` sem auditar. As demais garantias da fila (trava por sala, concorrência,
  retomada, máximo de tentativas, limpeza de presas) MUST ser as da Spec 005.
- **FR-011**: O plugin MUST limitar o número de execuções pendentes de origem `api` por solicitante (configuração,
  padrão 100, intervalo 1–2000); ao exceder, a solicitação MUST falhar com `queuefull` sem criar nada. Reaproveitar uma
  pendente MUST NOT contar nem ser bloqueado pelo limite. **[validar]**
- **FR-012**: A auditoria pedida pela API MUST usar a política vigente e o ano de reoferta dela, como a página.

**Consultar execução**

- **FR-013**: A consulta de execução MUST devolver: id, id da sala, origem, estado, datas de criação, início e término,
  tentativas, versão do plugin, versão do contrato do relatório, versão e impressão digital da política aplicada, ano
  de reoferta, resultado geral, resumo, erros e limitações (código e mensagem), evolução guardada (execução de base,
  contagens por classe, notas) e `api_version`.
- **FR-014**: Para execução concluída ou parcial, a consulta MUST devolver o relatório guardado exatamente como
  gravado (contrato 1.0 com `occurrence`), salvo quando o cliente pedir para omiti-lo; para as demais, MUST NOT
  devolver relatório.
- **FR-015**: A pedido do cliente, a consulta MUST devolver os itens da evolução calculados contra a execução de base,
  com o mesmo resultado da página de histórico; se a base não existir mais, MUST devolver as contagens guardadas e uma
  nota de indisponibilidade.
- **FR-016**: As respostas MUST NOT identificar o solicitante nem outras pessoas.

**Consultar a última auditoria**

- **FR-017**: A consulta da última auditoria MUST aplicar a regra do GET da página de auditoria: última execução válida
  (concluída ou parcial, pela data de término) com os motivos de desatualização calculados no momento da consulta
  (sala alterada, idade, política, versão do plugin), a falha mais recente quando posterior a ela e a execução
  pendente viva, se houver.
- **FR-018**: A execução válida devolvida MUST ter os mesmos campos e opções da consulta de execução (FR-013 a FR-015).
- **FR-019**: Sala sem execução válida MUST produzir resposta de sucesso indicando a ausência (com pendente e falha, se
  houver), não erro.

**Somente leitura e independência**

- **FR-020**: As consultas MUST NOT executar auditorias nem gravar nada nas tabelas do plugin nem na fila
  (execuções, tarefas, políticas, lotes); a solicitação MUST gravar apenas a execução na fila e a tarefa. Registros
  que o próprio Moodle faz em qualquer chamada autenticada (último acesso ao curso, log de Web Services) não contam.
- **FR-021**: Nenhuma função MUST escrever em tabelas de curso, módulo, nota, inscrição, bloco, arquivo ou configuração
  (constituição III); a auditoria em si continua a da Spec 005.
- **FR-022**: Páginas, lotes, painel e CLI MUST funcionar igualmente com o serviço desabilitado ou sem nenhuma conta com
  `local/mooc_audit:api`.

**Contrato e versionamento**

- **FR-023**: Toda resposta de sucesso MUST trazer `api_version` (inicial "1.0") e, quando houver relatório,
  `contract_version` do relatório.
- **FR-024**: Os metadados MUST ter estrutura de resposta declarada e tipada; o relatório MUST ser entregue como
  documento JSON válido no esquema publicado do contrato 1.0.
- **FR-025**: Dentro de `api_version` 1.x só MUST entrar mudança aditiva; mudança incompatível MUST criar funções com
  nome novo, mantidas em paralelo com as anteriores por pelo menos uma versão do plugin, com nota de migração e
  marcação de obsolescência.
- **FR-026**: Os erros específicos do plugin (`invalidcourseid`, `runnotfound`, `queuefull`, `busy`, falta de permissão) MUST
  ter códigos estáveis, documentados, com mensagem em pt_br e en.

**Privacidade, registro e exibição**

- **FR-027**: O privacy provider MUST descrever a origem `api` e a exportação/anonimização MUST cobrir execuções dessa
  origem, sem novos campos pessoais.
- **FR-028**: A origem `api` MUST aparecer com rótulo próprio (pt_br e en) onde a interface mostra a origem
  (histórico da sala, página da execução, resumo da última auditoria); a retenção da Spec 005 MUST valer para ela.
- **FR-029**: Respostas MUST NOT conter segredos (senhas de inscrição, tokens, chaves), herdando a garantia do relatório
  (Spec 001).

**Documentação e testes**

- **FR-030**: O plugin MUST publicar um guia do consumidor: habilitar o serviço, papel da conta de integração (lista de
  capacidades), token, as três funções com parâmetros, respostas, erros e exemplos de chamada, fluxo recomendado de
  acompanhamento (intervalo de consulta), significado da desatualização ("resultado da última auditoria", não
  conformidade atual) e política de versionamento.
- **FR-031**: Testes automatizados MUST cobrir, para cada função: sucesso; sem `local/mooc_audit:api`; sem `audit` na
  sala; sala de outro escopo (permitida na categoria A, recusada na B); execução inexistente e execução de outra sala
  com a mesma resposta; sala removida com e sem `viewdashboard`; curso do site; e, na solicitação: reaproveitamento,
  limite `queuefull`, revogação antes da tarefa, nenhuma regra executada na chamada; nas consultas: nada gravado;
  respostas conformes às estruturas declaradas e relatório válido no esquema; privacy provider com origem `api`.

### Key Entities

- **Execução** (Spec 005): registro imutável de uma auditoria de uma sala; ganha a origem **`api`**. Nenhum campo novo.
- **Serviço externo "Auditor MOOC"**: agrupamento nativo das três funções, desabilitado e restrito por padrão.
- **Capacidade `local/mooc_audit:api`**: autorização, no sistema, para usar as funções; somada a `audit` por sala.
- **Conta de integração**: usuário do Moodle (geralmente de serviço) com o papel documentado e um token do serviço.
- **Contrato da API 1.0**: nomes, parâmetros, respostas tipadas, códigos de erro e política de versionamento das três
  funções; o relatório dentro dele segue o contrato do relatório 1.0.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A solicitação de auditoria responde em até 1 segundo em qualquer sala, inclusive a de 151 módulos, e em
  100% dos casos nenhuma regra é executada durante a chamada.
- **SC-002**: A consulta da última auditoria e a consulta de execução, com relatório, respondem em até 2 segundos para
  as salas de referência.
- **SC-003**: Em 100% dos testes de acesso negado (sem capacidade de API, sem `audit`, outro escopo, execução de outra
  sala, sala removida sem `viewdashboard`) nenhum dado da sala ou da execução é devolvido.
- **SC-004**: Para 100% das salas de referência, o relatório devolvido pela API é igual ao relatório guardado mostrado
  pela página (`format=json`), exceto pelo bloco de metadados da execução.
- **SC-005**: Em 100% das consultas o número de execuções, tarefas, políticas e lotes antes e depois é igual.
- **SC-006**: Com o limite padrão, nenhuma conta mantém mais de 100 execuções `api` pendentes, e nenhuma sala tem mais
  de uma execução pendente viva criada pela API.
- **SC-007**: Uma pessoa que não participou da implementação configura a conta de integração e completa as três
  operações seguindo só o guia, em um ambiente isolado, em até 30 minutos.
- **SC-008**: Toda a suíte existente (Specs 001–005) continua passando com o serviço desabilitado.

## Assumptions

- A Spec 005 foi mesclada (PR #5) sem mudança de comportamento no histórico, na fila e na regra de "pendente viva" em
  relação à versão especificada; uma mudança futura nessas regras exige revisar esta especificação.
- O Moodle de produção permite habilitar Web Services e um protocolo (REST) e gerar tokens; essa configuração é do
  administrador do site, fora do plugin.
- O MOOC Admin (ou outro cliente) localiza a sala pelo id do Moodle; se só tiver sigla ou `idnumber`, usa as funções
  nativas do Moodle para obter o id.
- O cliente acompanha a execução consultando o id; um intervalo de 10 a 30 segundos é suficiente (auditoria medida na
  Spec 005: 0,02–0,14 s por sala, mais a espera na fila).
- O registro das chamadas de Web Services é o do núcleo do Moodle; o plugin não cria outro log.
- A conta de integração precisa, além de `local/mooc_audit:api` e `local/mooc_audit:audit`, das permissões nativas
  para usar o protocolo e para acessar salas, inclusive ocultas; o guia lista o papel completo.
- O tamanho do relatório (até ~41 kB medidos) não exige paginação nem compressão própria.
- Lote via API, ciclo explícito, notificações ativas e limite de taxa podem ser especificados depois, como incrementos,
  se um cliente real precisar.
