# Feature Specification: Auditoria Técnica Operacional de Cursos MOOC

**Feature Branch**: `001-auditoria-tecnica-operacional`

**Created**: 2026-10-28

**Status**: Draft (aguarda validação institucional dos itens marcados em Clarifications)

**Input**: User description: "Evolução da prova de conceito `local_mooc_audit` para um MVP utilizável
institucionalmente: auditoria de uma sala pelo identificador, acesso pela navegação administrativa do curso,
controle de acesso por capacidades Moodle, aplicação das políticas institucionais, consolidação das regras
prioritárias já implementadas, evidências legíveis, recomendações objetivas, links de navegação, relatório por
categoria e gravidade, reexecução, tratamento de resultados incompletos, reforço da cobertura de testes,
persistência mínima somente se justificada."

## Contexto

O Cefor/Ifes reoferta anualmente cursos MOOC no Moodle 4.5. A equipe técnica confere cada sala contra um checklist
de 29 itens. A prova de conceito `local_mooc_audit` já executa 29 regras sobre uma sala em menos de 0,3 s, mas só por
linha de comando ou por uma página fora da navegação do curso, com uma tabela única, sem links, sem agrupamento e com
4 regras testadas automaticamente. Esta especificação descreve o que a equipe precisa para usar o Auditor MOOC no
trabalho diário de reoferta, sem introduzir correção automática, lote, histórico ou API.

Fora de escopo desta entrega: correção assistida (002), comparação entre ofertas (003), API externa (004),
auditoria em lote e histórico (005), regras novas além das 29 já implementadas (salvo os caminhos de teste
faltantes), análise de pacotes `.mbz`, verificações que exigem rede ou usuário de teste em ação.

## Clarifications

### Session 2026-10-28

Decisões tomadas pelo autor da especificação para não bloquear o planejamento. As marcadas **[validar]** exigem
confirmação institucional antes da implementação começar; as demais são premissas razoáveis registradas.

- Q: O MVP deve persistir resultados de auditoria? → A: **Não.** A auditoria leva menos de 1 s e a reexecução é
  gratuita; não há necessidade operacional de guardar o último resultado para apenas lê-lo. Histórico, comparação
  entre execuções e lote ficam na especificação 005. Nenhuma tabela própria nesta entrega.
- Q: Quem pode executar a auditoria por padrão? → A: **Papéis com arquétipo gerente** (como hoje), no contexto do
  curso; a instituição concede a outros papéis (ex.: professor editor) pelo mecanismo nativo de permissões.
  **[validar]** se proponentes devem receber a permissão já no MVP. **Atualizado em 2026-10-29** (decisão 11,
  spec 002): o padrão passou a ser **gerente e professor editor**.
- Q: A auditoria avalia a sala na visão de quem executa ou na visão de um estudante? → A: **Visão administrativa**
  (todos os módulos visíveis) na página; a visão de um usuário de teste continua disponível só por linha de comando e
  entra na interface em entrega posterior.
- Q: Como as inconformidades são organizadas? → A: primeiro por **gravidade** (Bloqueante, Alta, Média, Baixa,
  Informativa), dentro dela por **categoria** fixa do catálogo: Oferta e inscrição · Recursos institucionais ·
  Avaliação e notas · Conclusão e restrições · Certificação · Conteúdo e arquivos · Estrutura, seções e blocos ·
  Plugins e ambiente. Cada regra pertence a exatamente uma categoria.
- Q: O que acontece com regras que falham internamente ou não podem verificar? → A: aparecem em blocos próprios
  ("Não foi possível verificar", "Erro na verificação") com o motivo; **nunca** são contadas como inconformidade nem
  como conformidade.
- Q: O relatório deve continuar disponível em JSON? → A: **Sim**, na página e na linha de comando, com um campo de
  versão do contrato; é a base da futura API (004).
- Q: O bloco "Acessibilidade" (CL-21) e os nomes canônicos (CL-09/11/16/22/23) continuam gerando não conformidade
  apesar da decisão 10 pendente? → A: **Sim**, com a gravidade atual, porque refletem a prática dos cursos de 2026;
  a política permite ajustar os nomes sem alterar código. **[validar]** decisão 10.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Executar a auditoria a partir da sala e compreender o resultado (Priority: P1)

Um servidor da equipe técnica, com permissão na sala, acessa o curso no Moodle, encontra a opção "Auditoria técnica"
na administração do curso, executa a auditoria e recebe, na mesma página, a situação geral da sala e a lista de
inconformidades, cada uma dizendo onde está o problema (nome do recurso ou configuração), qual a evidência
encontrada, o que era esperado e o que fazer. Alertas, regras conformes e itens não verificáveis aparecem separados.

**Why this priority**: é a capacidade que transforma a PoC em ferramenta de trabalho; sem ela nada mais tem valor.

**Independent Test**: com um curso sintético inconsistente (equivalente ao curso B da PoC) e um usuário com a
permissão, abrir a sala, acionar "Auditoria técnica" e verificar que a página mostra as inconformidades esperadas
agrupadas por gravidade e categoria, cada uma com local, evidência, esperado e orientação; com o curso sintético
conforme (curso A), a página informa que não há inconformidades.

**Acceptance Scenarios**:

1. **Given** um usuário com a permissão de auditar no curso B (inconsistente), **When** acessa a administração do
   curso, **Then** vê a entrada "Auditoria técnica" e, ao acioná-la, a página carrega o relatório daquele curso sem
   exigir que digite o identificador.
2. **Given** o relatório do curso B, **When** o usuário lê a seção de situação geral, **Then** vê a classificação da
   sala ("Bloqueada", "Requer correções" ou "Sem inconformidades"), as contagens separadas por estado (não conformes,
   alertas, conformes, não verificáveis, erros) e a quantidade de não conformes por gravidade, sem nenhum percentual
   que misture estados.
3. **Given** o relatório do curso B, **When** o usuário lê as inconformidades, **Then** elas estão ordenadas por
   gravidade (bloqueantes primeiro) e agrupadas por categoria, e cada uma mostra: identificador e título da regra,
   local (nome do recurso com seu identificador de módulo, ou o caminho da configuração), evidência completa, valor
   encontrado, valor esperado e orientação de correção.
4. **Given** o curso A (conforme), **When** a auditoria é executada, **Then** a situação geral é "Sem
   inconformidades", nenhuma inconformidade é listada e as regras conformes aparecem em lista resumida.
5. **Given** um relatório, **When** o usuário consulta os itens não verificados ou não aplicáveis, **Then** cada um
   informa a regra e o motivo (ex.: "tipo de atividade sem campo de encerramento conhecido"), e nenhum deles é contado como
   inconformidade.
6. **Given** qualquer relatório, **When** o usuário lê o cabeçalho, **Then** vê nome e identificador do curso, data e
   hora da execução, ano de reoferta considerado, versão da política e versão do auditor.

---

### User Story 2 - Ir direto ao ponto de correção e reexecutar (Priority: P2)

Depois de ler uma inconformidade, o servidor clica no link do local indicado, é levado à página do recurso ou à tela
de configuração correspondente no Moodle, corrige pelas ferramentas convencionais, volta ao relatório e reexecuta a
auditoria com um clique, vendo o item desaparecer da lista.

**Why this priority**: encurta o ciclo corrigir → conferir, que é o trabalho real da reoferta; depende da US1.

**Independent Test**: no curso B, para uma inconformidade de módulo (ex.: questionário com 1 tentativa) o link leva
à edição daquele questionário; para uma de configuração do curso (ex.: data de término) leva às configurações do
curso; após corrigir e acionar "Executar novamente", a inconformidade não aparece mais e a contagem é atualizada.

**Acceptance Scenarios**:

1. **Given** uma inconformidade cujo local é um módulo, **When** o usuário aciona o link do local, **Then** é levado
   à página de edição daquele módulo (ou à sua página de visualização, quando a regra se refere ao conteúdo).
2. **Given** uma inconformidade cujo local é uma configuração do curso (datas, categoria, formato, inscrição, livro
   de notas, blocos, seção), **When** o usuário aciona o link, **Then** é levado à tela do Moodle onde aquela
   configuração é alterada.
3. **Given** uma inconformidade sem destino navegável (ex.: ciclo no grafo de restrições), **When** o usuário a lê,
   **Then** o local é apresentado como texto, sem link quebrado, e a orientação indica onde agir.
4. **Given** o relatório exibido, **When** o usuário aciona "Executar novamente", **Then** uma nova auditoria é
   executada sobre o estado atual da sala e a página é atualizada com nova data/hora de execução.
5. **Given** que o usuário corrigiu uma inconformidade no Moodle, **When** reexecuta, **Then** o item deixa de
   aparecer como não conforme e as contagens refletem a mudança; nenhum outro item muda sem alteração na sala.

---

### User Story 3 - Resultados incompletos, falhas e acesso negado tratados com transparência (Priority: P2)

A ferramenta deixa claro quando não pôde concluir parte do diagnóstico (regra com erro interno, plugin de terceiros
ausente, configuração de política inválida) e nega acesso a quem não tem permissão, sem jamais apresentar incerteza
como falha da sala.

**Why this priority**: a confiança da equipe depende de nunca receber um alarme falso por limitação da ferramenta.

**Independent Test**: provocar uma exceção em uma regra (cenário de teste), auditar um curso que usa um tipo de
atividade não instalado, configurar uma política JSON inválida e acessar com um usuário sem a permissão; verificar os
comportamentos abaixo.

**Acceptance Scenarios**:

1. **Given** uma regra que lança exceção durante a auditoria, **When** o relatório é gerado, **Then** as demais
   regras são avaliadas normalmente e a regra com falha aparece no bloco "Erro na verificação" com a mensagem,
   contada separadamente.
2. **Given** um curso que referencia um tipo de atividade ou de condição de acesso cujo plugin não está instalado,
   **When** auditado, **Then** o relatório aponta a ausência do plugin como inconformidade de ambiente e marca as
   verificações que dependem dele como não verificáveis, sem erro fatal.
3. **Given** uma política institucional inválida no painel de administração (JSON malformado), **When** qualquer
   auditoria é executada, **Then** o relatório exibe um aviso de que a política padrão foi usada por falha na
   configuração, e o administrador vê o erro na página de configurações.
4. **Given** um usuário autenticado sem a permissão de auditar no curso, **When** tenta acessar a página da auditoria
   daquele curso, **Then** recebe a mensagem padrão de permissão negada do Moodle e nenhuma auditoria é executada;
   a entrada "Auditoria técnica" não aparece na administração do curso para ele.
5. **Given** um identificador de curso inexistente, **When** a página é acessada, **Then** o Moodle responde com o
   erro padrão de curso inválido.
6. **Given** uma sala com atividades cujo módulo foi apagado parcialmente ou com dados inconsistentes, **When**
   auditada, **Then** a auditoria termina, registra os avisos de coleta no relatório e marca os itens afetados como
   não verificáveis.

---

### User Story 4 - Obter o relatório em formato estruturado (Priority: P3)

Um técnico obtém o mesmo relatório em JSON, pela página ou por linha de comando, para arquivar, comparar manualmente
ou alimentar ferramentas; o formato declara sua versão e mantém compatibilidade com o produzido pela PoC.

**Why this priority**: já existe e é a base da integração futura; só precisa de versão e estabilidade.

**Independent Test**: comparar o JSON da página e o da linha de comando para o mesmo curso: mesmos resultados,
mesma estrutura, campo de versão do contrato presente; validar que todos os campos usados pela PoC continuam
presentes.

**Acceptance Scenarios**:

1. **Given** um usuário com permissão, **When** solicita o relatório em JSON pela página, **Then** recebe um
   documento com cabeçalho (curso, execução, política, versão do auditor, versão do contrato), inventário, síntese e
   a lista de resultados com os mesmos campos exibidos na página.
2. **Given** a linha de comando, **When** executada com a opção JSON para o mesmo curso, **Then** o documento é
   equivalente ao da página, exceto pela data/hora e pelo usuário executor.
3. **Given** uma versão futura que altere o formato de modo incompatível, **When** publicada, **Then** o campo de
   versão do contrato muda e a mudança está documentada; nesta entrega o campo é introduzido com valor inicial.

---

### User Story 5 - Ajustar a política institucional sem alterar código (Priority: P3)

Um administrador do site ajusta parâmetros institucionais (ano de reoferta, nomes canônicos, blocos exigidos,
tentativas mínimas) no painel de administração do plugin e vê, em cada relatório, qual versão da política foi
aplicada.

**Why this priority**: necessário para absorver a decisão 10 (nomes canônicos) e o próximo ciclo sem nova versão.

**Independent Test**: alterar o nome canônico do fórum institucional na política, auditar um curso cujo fórum tem o
novo nome e verificar que CL-09 passa a conforme e o relatório mostra a versão da política com o sufixo de
personalização.

**Acceptance Scenarios**:

1. **Given** uma política personalizada válida, **When** uma auditoria é executada, **Then** as regras usam os
   valores personalizados e o relatório identifica a política como personalizada.
2. **Given** nenhuma personalização, **When** uma auditoria é executada em outubro/novembro, **Then** o ano de
   reoferta considerado é o ano seguinte, e isso é mostrado no cabeçalho.
3. **Given** a página de configurações do plugin, **When** o administrador a abre, **Then** encontra a descrição de
   cada parâmetro aceito e um exemplo, e um aviso se a política atual for inválida.

---

### Edge Cases

- Curso sem nenhuma atividade: regras por tipo retornam "não aplicável"; a situação geral reflete apenas o que foi
  avaliado (datas, categoria, inscrição, blocos).
- Curso com mais de 150 módulos (ex.: cursos de 2026 com 66–74) e dezenas de arquivos: a auditoria conclui dentro do
  limite de tempo definido e a página permanece legível (agrupamento por gravidade/categoria, conformes resumidos).
- Dois recursos com o mesmo nome (versões em Libras): cada resultado exibe o identificador do módulo para
  desambiguar; a regra de nome canônico aceita o primeiro encontrado e não reporta duplicata.
- Módulo oculto referenciado por restrição: aparece como alerta (não bloqueante) com o nome do módulo oculto.
- Política com chave desconhecida: ignorada sem erro; chave conhecida com tipo errado: aviso no relatório e valor
  padrão usado.
- Usuário com permissão em um curso e não em outro: só a sala autorizada mostra a entrada e aceita a execução.
- Site com acompanhamento de conclusão desabilitado globalmente: todas as regras de conclusão/restrição ficam "não
  verificáveis" com esse motivo, e a situação geral avisa.
- Reexecução concorrente por dois usuários: cada um recebe seu próprio relatório; não há estado compartilhado.

## Requirements *(mandatory)*

### Functional Requirements

**Acesso e permissão**

- **FR-001**: O sistema MUST oferecer a entrada "Auditoria técnica" na administração de cada curso para usuários com
  a permissão de auditar naquele curso, e MUST ocultá-la para os demais.
- **FR-002**: O sistema MUST executar a auditoria do curso a partir do qual foi acionado, sem exigir digitação do
  identificador; MUST continuar aceitando o identificador por parâmetro para uso direto.
- **FR-003**: O sistema MUST negar a execução e a visualização a usuários sem a permissão no contexto do curso, com a
  resposta padrão de permissão negada da plataforma.
- **FR-004**: A permissão de auditar MUST ser concedida por padrão apenas a papéis de gerência (arquétipo gerente do
  Moodle) e MUST ser atribuível
  a outros papéis pelo mecanismo nativo de permissões, sem alteração de código. *Alterado em 2026-10-29 pela decisão
  11 (spec 002): padrão = arquétipos gerente e professor editor; professor não editor e estudante não.*

**Execução e política**

- **FR-005**: A auditoria MUST ser uma operação exclusivamente de leitura sobre a sala e o site; nenhuma configuração,
  conteúdo, nota, inscrição, bloco ou arquivo MAY ser alterado, e isso MUST ser comprovado por teste automatizado.
- **FR-006**: A auditoria MUST aplicar as 29 regras do catálogo atual (CL-04, 05, 06, 07, 08, 09, 11, 12, 13, 14, 16,
  17, 18, 19, 20, 21, 22, 23, X-INT-02, X-INT-03, X-CONT-01, X-CONT-04, X-AV-01, X-DEP-01, 02, 03, 04, X-CERT-01,
  X-STR-01) com os mesmos identificadores, estados e critérios validados na PoC; divergências de comportamento MUST
  ser documentadas como correções.
- **FR-007**: Cada regra MUST declarar: identificador, título, categoria, origem (item do checklist ou verificação
  adicional), tipo (institucional ou técnica), gravidade padrão, parâmetros de política usados e orientação padrão.
- **FR-008**: Parâmetros institucionais (ano de reoferta, datas, categoria-alvo, tentativas, total de notas, nota
  mínima, nomes canônicos, blocos exigidos, links do sumário, critérios de conclusão por tipo) MUST vir da política,
  com padrões iguais aos da PoC, personalizáveis pelo administrador do site sem alterar código.
- **FR-009**: Quando a política personalizada for inválida, o sistema MUST usar os padrões, MUST sinalizar o problema
  no relatório e na página de configurações, e MUST NOT falhar a auditoria.
- **FR-010**: O ano de reoferta, quando não informado, MUST ser o ano seguinte ao da execução, e MUST constar no
  relatório.

**Resultados e estados**

- **FR-011**: Cada resultado MUST ter exatamente um estado entre CONFORME, NAO_CONFORME, ATENCAO, NAO_APLICAVEL,
  NAO_VERIFICAVEL e ERRO, e uma gravidade entre Bloqueante, Alta, Média, Baixa e Informativa.
- **FR-012**: Cada resultado NAO_CONFORME ou ATENCAO MUST informar local legível (nome do recurso com identificador
  de módulo, ou caminho da configuração), evidência, valor encontrado quando aplicável, valor esperado e orientação
  de correção.
- **FR-013**: Falha interna em uma regra MUST produzir um resultado ERRO daquela regra com a mensagem, e MUST NOT
  impedir a avaliação das demais.
- **FR-014**: Dado indisponível, plugin ausente ou verificação dependente de ação humana MUST produzir
  NAO_VERIFICAVEL ou NAO_APLICAVEL com o motivo, e MUST NOT ser contado como inconformidade.
- **FR-015**: Avisos da coleta de dados (ex.: instância de módulo inexistente, falha ao ler blocos) MUST constar no
  relatório.

**Relatório na página**

- **FR-016**: A página MUST apresentar a situação geral da sala com uma das classificações: "Bloqueada" (há não
  conforme bloqueante), "Requer correções" (há não conforme não bloqueante), "Sem inconformidades"; e as contagens
  separadas por estado e por gravidade, sem percentuais que misturem estados.
- **FR-017**: A página MUST listar as inconformidades ordenadas por gravidade decrescente e agrupadas pela categoria
  da regra; alertas, não verificados/não aplicáveis (com motivo e subtítulos distintos), erros e conformes MUST aparecer em
  seções distintas, nesta ordem de destaque.
- **FR-018**: A evidência MUST ser exibida integralmente (sem truncar), em formato legível; identificadores internos
  MAY ser mostrados em complemento ao nome, nunca em substituição.
- **FR-019**: Quando o local de um resultado for um módulo, a página MUST oferecer link para a edição desse módulo
  (ou para sua visualização, quando a regra trata de conteúdo); quando for uma configuração de curso (datas,
  categoria, formato, inscrição, livro de notas, blocos, seção), MUST oferecer link para a tela correspondente;
  quando não houver destino, o local MUST ser texto sem link.
- **FR-020**: A página MUST oferecer a ação "Executar novamente", que reavalia o estado atual da sala e atualiza o
  relatório, com nova data/hora.
- **FR-021**: O cabeçalho MUST mostrar nome e identificador do curso, data/hora, ano de reoferta, versão da política e
  versão do auditor; e um inventário resumido (período, categoria, seções, módulos por tipo, blocos, métodos de
  inscrição, arquivos sem conteúdo).
- **FR-022**: Todo texto de interface MUST vir de arquivos de idioma (português do Brasil e inglês); nenhum texto de
  interface MAY ficar fixo no código.
- **FR-023**: A página MUST ser utilizável com teclado e leitor de tela segundo as práticas da plataforma (tabelas
  com cabeçalhos, links com texto significativo, agrupamentos com títulos).

**Formato estruturado e linha de comando**

- **FR-024**: O relatório MUST estar disponível em JSON pela página (para quem tem a permissão) e pela linha de
  comando, com os mesmos resultados, incluindo campo de versão do contrato (valor inicial 1.0) e mantendo os campos
  já produzidos pela PoC.
- **FR-025**: A linha de comando MUST continuar aceitando identificador do curso, política por arquivo, saída JSON,
  arquivo de saída e visão de usuário de teste.

**Qualidade**

- **FR-026**: Cada regra MUST ter teste automatizado cobrindo pelo menos um cenário conforme e um não conforme,
  criados com as ferramentas de geração de dados da plataforma; regras com estados adicionais relevantes (não
  aplicável, não verificável, alerta) MUST cobri-los.
- **FR-027**: MUST existir teste automatizado de que a auditoria não altera o curso (FR-005) e de que usuário sem
  permissão é negado (FR-003).
- **FR-028**: O catálogo de regras (`docs/catalogo-regras.md`) MUST ser atualizado com o status de implementação e
  cobertura de testes de cada regra ao fim da entrega.

### Key Entities

- **Auditoria (relatório)**: execução única sobre uma sala; atributos: curso (id, nome curto, nome completo),
  data/hora, executor, ano de reoferta, versão da política, versão do auditor, versão do contrato, inventário,
  síntese, lista de resultados, avisos de coleta. Não persistida nesta entrega.
- **Resultado**: um achado de uma regra; atributos: regra, estado, gravidade, categoria, local (texto; módulo
  opcional), evidência, encontrado, esperado, orientação, destino de navegação (opcional).
- **Regra**: unidade do catálogo; atributos: identificador estável, título, descrição, categoria, origem, tipo
  (institucional/técnica), gravidade padrão, parâmetros de política usados, orientação padrão, fonte normativa.
- **Categoria**: agrupamento fixo de regras (oito categorias listadas em Clarifications).
- **Política institucional**: conjunto versionado de parâmetros; padrão embutido + personalização do administrador
  + sobrescrita por arquivo (linha de comando).
- **Situação geral**: classificação derivada dos resultados: Bloqueada / Requer correções / Sem inconformidades.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um servidor com permissão chega ao relatório de uma sala em até 3 ações a partir da página do curso,
  sem digitar identificadores.
- **SC-002**: Para uma sala com até 150 módulos, o relatório é apresentado em menos de 5 segundos após o acionamento.
- **SC-003**: Em teste com a equipe técnica usando os cursos sintéticos e os cursos institucionais restaurados, 100 %
  das inconformidades listadas têm local, evidência e orientação preenchidos, e pelo menos 90 % oferecem link
  navegável para o ponto de correção.
- **SC-004**: Um usuário sem experiência prévia na ferramenta identifica, para 9 de 10 inconformidades do curso
  sintético inconsistente, onde está o problema e qual ação tomar, apenas lendo o relatório.
- **SC-005**: Nenhum item NAO_VERIFICAVEL, NAO_APLICAVEL ou ERRO é contado como inconformidade em nenhuma
  classificação ou contagem do relatório.
- **SC-006**: Todas as 29 regras possuem teste automatizado com cenário conforme e não conforme; a suíte completa
  passa; existem testes de não modificação da sala e de permissão negada.
- **SC-007**: Auditar os cursos A e B da PoC produz os mesmos estados por regra registrados em `plugin-results/`
  (A: tudo conforme; B: as 25 não conformidades e 5 alertas), salvo correções documentadas.
- **SC-008**: Uma auditoria não altera a data de modificação nem o conteúdo de nenhuma tabela do curso auditado
  (verificado por teste).
- **SC-009**: Toda string visível na interface está nos arquivos de idioma `pt_br` e `en`.

## Assumptions

- A arquitetura "plugin local Moodle com motor interno" está decidida (PoC) e não é reaberta aqui.
- As decisões institucionais 1–9 registradas em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` valem; a decisão 10 (nomes
  canônicos) permanece pendente e a política padrão usa os nomes observados nos cursos de 2026.
- O ambiente de desenvolvimento é o de `moodle-env/` (Moodle 4.5.15, PHP 8.3, PostgreSQL 17); os testes rodam no
  mesmo ambiente; versões institucionais de plugins de terceiros podem diferir e isso é limitação conhecida.
- Cursos sintéticos A e B continuam sendo o oráculo funcional; os cursos institucionais restaurados (55, 670) servem
  de validação complementar e não são versionados.
- Links de navegação apontam para telas padrão do Moodle 4.5 (edição de módulo, configurações do curso, métodos de
  inscrição, configuração do livro de notas, página do curso em modo de edição para blocos); se uma tela não
  existir para o local, o resultado fica sem link.
- A classificação "Bloqueada" é informativa: nada impede a publicação da sala; bloqueio de liberação não faz parte do
  produto nesta entrega nem está decidido institucionalmente.
