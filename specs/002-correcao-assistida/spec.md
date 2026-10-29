# Feature Specification: Correção Assistida de Inconformidades

**Feature Branch**: `claude/spec-002-correcao-assistida-9d1925` (diretório `specs/002-correcao-assistida/`)

**Created**: 2026-10-29

**Status**: Draft — implementação experimental autorizada (fases 1 a 5 do pedido); itens **[validar]** dependem de
confirmação institucional antes da homologação.

**Base**: Spec 001 (`specs/001-auditoria-tecnica-operacional/`, plugin 0.2.0, PR #1 **aberto** em 2026-10-29). Esta
especificação parte da branch da Spec 001 e não a recria.

**Input**: User description: "Spec 002 — Correção assistida no Auditor MOOC: transformar inconformidades em propostas
compreensíveis; classificar cada inconformidade em correção executável mediante confirmação (A), correção orientada
(B) ou correção dependente de recurso externo/decisão institucional (C); primeiro incremento com três fluxos —
tentativas de questionário (CL-13, executável), data de encerramento de avaliação (CL-14, executável apenas onde houver
suporte técnico validado) e conclusão de atividade (CL-17, somente orientada); prévia sem efeito colateral,
confirmação protegida, revalidação, execução por mecanismo apropriado do Moodle, registro, reauditoria; motor de
auditoria permanece somente leitura."

## Contexto

A Spec 001 entregou um relatório que localiza cada inconformidade e aponta a tela de correção. O passo seguinte do
ciclo diagnóstico → correção é ajudar o responsável a decidir **o que mudar, por quê, com que impacto** e, quando a
alteração for objetiva e segura, aplicá-la com confirmação explícita, deixando registro e comprovando o resultado por
nova auditoria.

O sucesso desta especificação não é medido pelo número de regras corrigidas automaticamente, e sim por: propostas
compreensíveis, alterações seguras e autorizadas, registro do que foi feito e comprovação por reauditoria.

**Fora de escopo**: correção das demais 26 regras; correção em lote; alteração de notas, critérios de aprovação ou
cadeia de certificação; recuperação de arquivos; geração de conteúdo; integração com MOOC Admin; API externa;
reestruturação da interface; rollback genérico; histórico completo de auditorias (Spec 005); comparação entre ofertas
(Spec 003); mudança do contrato JSON 1.0 do relatório.

## Clarifications

### Session 2026-10-29

Decisões do autor da especificação para não bloquear o incremento experimental. **[validar]** = exige confirmação
institucional antes da homologação; as demais são premissas registradas.

- Q: Quem pode executar uma correção por padrão? → A: **gerentes e professores editores** (decidido pelo responsável
  do projeto em 2026-10-29; a primeira versão da spec propunha só gerentes), por uma capacidade nova
  `local/mooc_audit:applycorrection` no contexto do curso, **somada** à capacidade nativa
  `moodle/course:manageactivities` no contexto da atividade. Preparar/visualizar propostas usa outra capacidade
  (`local/mooc_audit:preparecorrection`), com os mesmos arquétipos. Professores não editores e estudantes não recebem
  nenhuma das duas. Quem só audita não vê nem executa correções. Para chegar às propostas pelo menu do curso o
  professor precisa também de `local/mooc_audit:audit`, que a Spec 001 mantém só para gerentes (**[validar]** na 001).
- Q: Qual é a data de encerramento esperada pelo CL-14? → A: **a data de término do curso**, critério que a regra já
  usa (alerta quando o encerramento ≠ fim do curso). A proposta só é executável quando a própria data de término do
  curso está conforme a política (CL-07) e no futuro; caso contrário, a proposta indica a dependência e não executa.
  **[validar]** se a instituição quer outro marco (por exemplo, véspera do término).
- Q: Como tratar salas com estudantes? → A: conservador. **Recusar a execução** quando a atividade já tem dados de
  estudantes que a alteração pode afetar (tentativas não-prévia, notas registradas; para conclusão, registros de
  conclusão). **Permitir com aviso** quando há apenas estudantes inscritos sem dados na atividade, porque a alteração
  não reescreve dado de estudante (aumentar o limite de tentativas; fixar encerramento futuro igual ao término do
  curso). A existência da sala não é tomada como sinal de "sala em preparação": o planejador sempre consulta inscrições
  e dados.
- Q: Como detectar alteração concorrente? → A: a prévia carrega uma **impressão digital** do estado que a fundamentou
  (revisão do cache do curso, `timemodified` da instância, valores atual e proposto, versão da política). Na
  confirmação o estado é relido do banco; qualquer diferença interrompe a operação e exige nova análise. Nenhum valor
  vindo do navegador é usado para decidir o que gravar.
- Q: Onde fica o registro das correções? → A: tabela mínima própria `local_mooc_audit_fix` (uma linha por execução
  confirmada que chegou à fase de escrita, com sucesso ou falha) **e** evento oficial do Moodle
  (`\local_mooc_audit\event\correction_applied`) nos logs padrão. Sem conteúdo de curso nem dados de estudantes; o
  único dado pessoal é o `userid` de quem executou (provider de privacidade atualizado).
- Q: A correção pode ser desfeita? → A: **não há rollback** automático nesta entrega. O registro guarda o valor
  anterior; desfazer é feito pela tela oficial de edição. A interface não chama a correção de "reversível".
- Q: Que inconformidades exibem "Ver proposta de correção"? → A: resultados `NAO_CONFORME` ou `ATENCAO` **com
  atividade identificada** (cmid) das regras CL-13, CL-14 e CL-17. As demais 26 regras mantêm a orientação e o link
  da Spec 001; sua classificação A/B/C consta da matriz (`correction-matrix.md`).
- Q: E se a correção resolver uma inconformidade mas criar outra? → A: antes de oferecer a confirmação, o planejador
  **simula** a alteração sobre o snapshot em memória e executa as regras; se a simulação não resolver a inconformidade
  alvo ou **introduzir** um novo `NAO_CONFORME`, a proposta deixa de ser executável e vira orientação. Após a
  execução real, a reauditoria completa compara antes × depois e relata resolvidas e introduzidas.
- Q: O relatório JSON muda? → A: **não**. Propostas não entram no contrato 1.0; a auditoria continua com o mesmo
  significado e os mesmos resultados.
- Q: Quais tipos de atividade o CL-14 corrige? → A: **somente questionário** (`quiz.timeclose`), único com rotina de
  atualização do núcleo comprovada para um campo isolado. Tarefa, fórum, escolha, pesquisa, base de dados e jogo
  recebem proposta **orientada**, com o campo específico de cada tipo indicado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver a proposta de correção de uma inconformidade (Priority: P1)

A pessoa responsável pela sala abre "Auditoria técnica", vê uma inconformidade de CL-13, CL-14 ou CL-17 e seleciona
"Ver proposta de correção". A página mostra problema, local, regra, valor atual, valor proposto, justificativa,
impacto esperado (simulado), riscos e dependências, e quem pode executar. Nada é alterado.

**Why this priority**: é o núcleo do valor — transformar uma inconformidade em proposta compreensível — e é útil
mesmo onde a execução é recusada.

**Independent Test**: com um questionário de 1 tentativa, abrir a proposta e conferir os nove elementos; comparar o
registro do questionário, o `cacherev` do curso e o número de linhas da tabela de registro antes e depois.

**Acceptance Scenarios**:

1. **Given** um questionário com 1 tentativa e política "mínimo 2", **When** o usuário com
   `preparecorrection` abre a proposta, **Then** vê regra CL-13, local com link, atual "1", proposto "2",
   justificativa com a política, impacto previsto (inconformidades que deixarão de existir), riscos (inscritos,
   substituições) e os papéis autorizados.
2. **Given** a mesma proposta, **When** ela é apenas exibida (GET), **Then** nenhum dado do curso, da atividade, do
   calendário ou do registro é alterado.
3. **Given** um usuário que só tem `local/mooc_audit:audit`, **When** ele abre o relatório, **Then** não vê a ação
   "Ver proposta de correção" e o acesso direto à página de proposta é negado.
4. **Given** uma inconformidade de regra sem estratégia (ex.: CL-12), **When** o relatório é exibido, **Then** não há
   ação de proposta; a orientação e o link da Spec 001 continuam como antes.

---

### User Story 2 - Confirmar a correção de tentativas de um questionário (Priority: P1)

Diante da proposta CL-13 executável, a pessoa autorizada confirma. O sistema revalida tudo no servidor, aplica a
alteração, registra, reaudita e informa se o problema foi resolvido.

**Why this priority**: é o primeiro fluxo executável e prova o ciclo completo com segurança.

**Independent Test**: confirmar a correção de um questionário de 1 tentativa sem tentativas de estudantes; conferir
`attempts = 2` pela API do questionário, demais campos inalterados, linha no registro, evento no log e CL-13
conforme na reauditoria.

**Acceptance Scenarios**:

1. **Given** proposta executável e usuário com `applycorrection` e `manageactivities`, **When** confirma (POST com
   chave de sessão), **Then** `attempts` passa a 2, nenhum outro campo do questionário muda, o registro guarda
   curso/atividade/regra/usuário/data/valor anterior/valor novo/resultado/reauditoria/versão da política, e a página
   informa "resolvido" somente depois de reler o valor persistido e reauditar.
2. **Given** a página de proposta aberta, **When** o usuário cancela, **Then** volta ao relatório sem qualquer
   alteração.
3. **Given** uma correção já aplicada, **When** a confirmação é reenviada (atualizar a página, voltar no navegador),
   **Then** nada é gravado novamente e a página informa que o estado mudou ou que não há o que corrigir.
4. **Given** o enunciado declara "2 tentativas" e o configurado é 1, **When** a correção é aplicada, **Then** a
   reauditoria mostra as duas inconformidades CL-13 da atividade resolvidas, como a simulação previu.
5. **Given** um questionário com 3 tentativas e política `attempts_rule = exact` com mínimo 2, **When** a proposta é
   aberta, **Then** ela é apenas orientada (não reduz tentativas automaticamente).

---

### User Story 3 - Corrigir a data de encerramento de um questionário (Priority: P2)

Para um questionário sem data de encerramento (ou com data diferente do término do curso), a proposta apresenta a
data atual, a data esperada (término do curso), o período do curso e o fuso horário, e permite confirmar.

**Why this priority**: segundo fluxo executável; exercita datas, fuso horário e dependência de outra regra.

**Independent Test**: curso com término conforme a política e questionário sem encerramento; confirmar; conferir
`timeclose` igual ao término, evento de calendário de encerramento criado pela rotina do questionário e CL-14
conforme.

**Acceptance Scenarios**:

1. **Given** término do curso conforme e no futuro e questionário sem encerramento, **When** a correção é confirmada,
   **Then** `timeclose` = término do curso, o calendário passa a ter o evento de encerramento e CL-14 da atividade fica
   `CONFORME`.
2. **Given** término do curso fora da política (CL-07 não conforme) ou no passado, **When** a proposta é aberta,
   **Then** ela não é executável e indica a dependência ("corrija primeiro a data de término do curso").
3. **Given** abertura do questionário posterior à data proposta, **When** a proposta é aberta, **Then** ela não é
   executável (o encerramento ficaria antes da abertura).
4. **Given** uma tarefa, fórum, escolha, pesquisa, base de dados ou jogo com a mesma inconformidade, **When** a
   proposta é aberta, **Then** ela é orientada, indica o campo do tipo (ex.: "Data limite"/"Data de corte" na tarefa)
   e encaminha à tela de edição.

---

### User Story 4 - Proteções: permissão, concorrência, estudantes e falhas (Priority: P2)

O sistema recusa executar quando a autorização técnica falta, quando o estado mudou desde a prévia, quando há dados
de estudantes que a alteração afetaria, e nunca exibe sucesso sem persistência comprovada.

**Why this priority**: as proteções são requisito da constituição (princípios IV e VIII) e condição de homologação.

**Independent Test**: um teste por proteção (PHPUnit) e um cenário Behat sem permissão.

**Acceptance Scenarios**:

1. **Given** usuário com `preparecorrection` mas sem `applycorrection` (ou sem `manageactivities` na atividade),
   **When** envia a confirmação diretamente, **Then** recebe erro de permissão e nada é gravado.
2. **Given** a prévia aberta, **When** outra pessoa altera a atividade ou o curso antes da confirmação, **Then** a
   execução é interrompida com "o curso mudou desde a prévia; gere nova análise" e nada é gravado.
3. **Given** um questionário com tentativas de estudantes (não-prévia) ou nota registrada, **When** a proposta é
   aberta ou confirmada, **Then** a execução é recusada e a proposta encaminha à edição manual com a explicação.
4. **Given** uma falha durante a escrita, **When** a correção é confirmada, **Then** a transação é desfeita, o
   registro guarda "falha" com o motivo e a página não exibe sucesso.
5. **Given** a escrita gravada mas a reauditoria falha, **When** a página é exibida, **Then** informa "alteração
   gravada; reauditoria não concluída" e não declara o problema resolvido.
6. **Given** uma requisição GET com os parâmetros de confirmação, **When** é recebida, **Then** apenas a prévia é
   exibida.

---

### User Story 5 - Orientação para conclusão de atividade (Priority: P3)

Para CL-17, a proposta mostra tipo da atividade, configuração atual de conclusão, critério recomendado pela política,
atividades e seções que dependem da conclusão desta atividade, relação com a Avaliação do curso e o certificado,
registros de conclusão existentes e riscos, e encaminha à tela oficial de edição. Não há botão de execução.

**Why this priority**: demonstra que o mecanismo é extensível a correções orientadas sem executar mudança arriscada.

**Independent Test**: atividade com conclusão manual exigida pela Avaliação do curso; abrir a proposta; conferir
dependências e ausência do botão de confirmar; conferir que POST de confirmação é recusado.

**Acceptance Scenarios**:

1. **Given** uma página com conclusão manual, **When** a proposta é aberta, **Then** recomenda "automática por
   visualização", lista quem depende dela e aponta a seção "Conclusão de atividade" da edição.
2. **Given** registros de conclusão de estudantes na atividade, **When** a proposta é aberta, **Then** o risco é
   destacado (o Moodle bloqueia/recalcula a conclusão ao mudar o critério).
3. **Given** qualquer proposta CL-17, **When** alguém envia POST de confirmação, **Then** a execução é recusada.

---

### Edge Cases

- Atividade removida entre a prévia e a confirmação → "a atividade não existe mais"; nada gravado.
- Atividade com exclusão em andamento (`deletioninprogress`) → tratada como inexistente.
- Questionário com tentativas apenas de prévia (professor) → não conta como dado de estudante.
- Substituições de usuário/grupo (`quiz_overrides`) → aviso: continuam prevalecendo para quem as tem.
- `attempts = 0` (ilimitadas) → conforme; nunca proposto.
- Política alterada entre prévia e confirmação → impressão digital muda; conflito.
- Conclusão desabilitada no site → CL-17 já é `NAO_VERIFICAVEL` (Spec 001); sem proposta.
- Duas confirmações simultâneas da mesma atividade → trava por atividade; a segunda espera ou falha sem gravar.
- Fuso horário: datas exibidas no fuso do usuário e do servidor; o valor gravado é o mesmo instante do término do
  curso (sem conversão).
- Questionário com "conclusão ao esgotar tentativas" → aviso: aumentar tentativas muda quando a conclusão ocorre.

## Requirements *(mandatory)*

### Functional Requirements

**Separação entre auditoria e correção**

- **FR-001**: O caminho de auditoria (`snapshot`, regras, `engine::audit()`, renderização do relatório) MUST
  permanecer somente leitura; nenhuma escrita MAY ser adicionada a ele.
- **FR-002**: Propostas e execuções MUST viver em componente próprio (`local_mooc_audit\local\correction`), que lê a
  auditoria e nunca é chamado por ela.
- **FR-003**: Uma auditoria MUST NOT disparar correção; abrir proposta MUST NOT gravar nada.

**Classificação**

- **FR-010**: Toda regra do catálogo MUST ter categoria de correção A (executável mediante confirmação), B (orientada)
  ou C (depende de recurso externo ou decisão institucional), documentada na matriz.
- **FR-011**: Somente CL-13 (tentativas) e CL-14 (encerramento de questionário) MAY ser executáveis nesta entrega;
  CL-17 MUST ser apenas orientada.
- **FR-012**: Uma proposta de categoria A MUST tornar-se orientada sempre que uma condição de segurança falhar,
  explicando o motivo.

**Proposta**

- **FR-020**: A proposta MUST exibir: problema, local (com link), regra e fonte, valor atual, valor proposto,
  justificativa, impacto esperado, riscos/dependências/impedimentos e papéis/capacidades autorizados (e se o usuário
  atual pode executar).
- **FR-021**: O impacto esperado MUST vir da simulação das regras sobre o snapshot alterado em memória: inconformidades
  resolvidas e introduzidas.
- **FR-022**: Se a simulação não resolver a inconformidade alvo ou introduzir `NAO_CONFORME`, a proposta MUST NOT ser
  executável.
- **FR-023**: CL-13 MUST NOT propor redução de tentativas nem alterar o método de cálculo da nota.
- **FR-024**: CL-14 MUST usar como data proposta o término do curso e MUST exigir término definido, conforme ao CL-07,
  no futuro e posterior à abertura da atividade.
- **FR-025**: CL-17 MUST indicar o critério da política para o tipo, as dependências de conclusão (restrições de
  acesso de módulos e seções, critérios de conclusão do curso), a relação com a Avaliação do curso e o certificado,
  e os registros de conclusão existentes.

**Confirmação e execução**

- **FR-030**: A execução MUST ocorrer somente por POST com chave de sessão válida e confirmação explícita.
- **FR-031**: Na confirmação o servidor MUST: revalidar capacidades; confirmar que a atividade existe; reconstruir a
  proposta a partir do banco; comparar a impressão digital; reavaliar riscos e impedimentos; só então gravar.
- **FR-032**: A escrita MUST alterar somente o campo previsto (e `timemodified`) da instância, dentro de transação,
  seguida das rotinas do próprio módulo que o núcleo executa para esse campo (eventos de calendário, tentativas
  abertas, cache do curso, evento `course_module_updated`).
- **FR-033**: A execução MUST usar trava por atividade para impedir execuções simultâneas.
- **FR-034**: Após gravar, o sistema MUST reler o valor persistido pela API do módulo; divergência = falha.
- **FR-035**: O sistema MUST reexecutar a auditoria completa e informar: alvo resolvido ou não; inconformidades
  introduzidas; outras resolvidas.
- **FR-036**: Mensagem de sucesso MUST aparecer somente com persistência comprovada; falha na reauditoria MUST ser
  informada como tal.

**Registro**

- **FR-040**: Cada execução que chegou à fase de escrita MUST gerar uma linha em `local_mooc_audit_fix` com curso,
  atividade, regra, campo, valor anterior, valor novo, usuário, data/hora, resultado da execução, resultado da
  reauditoria, versão da política e versão do motor.
- **FR-041**: Cada correção aplicada MUST disparar o evento `correction_applied` (logs padrão do Moodle).
- **FR-042**: O registro MUST NOT conter conteúdo de curso, dados de estudantes ou segredos.
- **FR-043**: O provider de privacidade MUST declarar e exportar o `userid` registrado e anonimizá-lo na exclusão.

**Permissões**

- **FR-050**: Capacidades distintas: `local/mooc_audit:audit` (ver auditoria), `local/mooc_audit:preparecorrection`
  (ver propostas), `local/mooc_audit:applycorrection` (executar). A execução MUST exigir também
  `moodle/course:manageactivities` no contexto da atividade.
- **FR-051**: A ação "Ver proposta de correção" MUST aparecer somente para quem tem `preparecorrection`; o botão
  "Confirmar" somente para quem pode executar; a ausência visual MUST NOT substituir a verificação no servidor.

**Compatibilidade**

- **FR-060**: Contrato JSON 1.0, estados, contagens e resultados da auditoria MUST permanecer idênticos.
- **FR-061**: A suíte da Spec 001 (PHPUnit e Behat) MUST continuar passando.

### Key Entities

- **Proposta de correção**: regra, atividade, estratégia, categoria (A/B/C), executável (sim/não), campo, valor
  atual e proposto (brutos e legíveis), justificativa, impacto simulado, riscos, impedimentos, dependências,
  capacidades exigidas e impressão digital do estado. Efêmera (não é persistida).
- **Registro de correção** (`local_mooc_audit_fix`): uma execução confirmada que chegou à escrita.
- **Estratégia de correção**: conhecimento específico de uma regra e tipo de atividade (como propor, quais riscos,
  como aplicar e como verificar).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das inconformidades CL-13/CL-14/CL-17 com atividade identificada nos cursos A/B e nos cenários de
  teste exibem proposta com os nove elementos exigidos.
- **SC-002**: 0 alterações em curso, atividade, calendário ou registro ao abrir ou cancelar propostas (verificado
  por teste automatizado).
- **SC-003**: Após uma correção executável confirmada, a regra alvo da atividade passa a `CONFORME` na reauditoria e
  a API do módulo lê o novo valor em 100% dos cenários de teste.
- **SC-004**: 100% das tentativas de execução sem permissão, com estado alterado, com dados de estudantes, por GET ou
  repetidas terminam sem escrita.
- **SC-005**: 100% das correções aplicadas e das falhas de escrita têm linha no registro e (quando aplicadas) evento
  no log.
- **SC-006**: Os resultados de auditoria dos cursos A e B são idênticos aos da Spec 001 (testes de caracterização).
- **SC-007**: Uma pessoa sem acesso ao código entende, pela proposta, o que mudará e por quê (verificação manual
  registrada nas evidências).

## Assumptions

- Ambiente de desenvolvimento local (Moodle 4.5.15, PostgreSQL); nada é executado em produção.
- O valor da política vigente é lido no momento da proposta e novamente na confirmação.
- Quem pode corrigir: gerentes e professores editores (decisão de 2026-10-29, registrada como decisão 11 em
  `DECISOES-INSTITUCIONAIS-CHECKLIST.md`); outros papéis pelo mecanismo nativo de permissões.
- Desfazer uma correção é feito pela tela oficial de edição, usando o valor anterior registrado.
