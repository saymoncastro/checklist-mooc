<!--
Sync Impact Report
- Version change: (template, sem versão) → 1.0.0
- Modified principles: todos os placeholders do modelo substituídos por 10 princípios do Auditor MOOC
  (I Independência operacional; II Compatibilidade e extensibilidade; III Auditoria não modifica cursos;
  IV Correção assistida e autorizada; V Regras rastreáveis; VI Confiabilidade e transparência;
  VII Testabilidade; VIII Segurança; IX Simplicidade arquitetural; X Evolução incremental)
- Added sections: "Restrições técnicas e de ambiente"; "Fluxo de desenvolvimento e portões de qualidade"
- Removed sections: nenhuma
- Templates: plan-template.md (Constitution Check) e tasks-template.md leem este arquivo em tempo de execução;
  nenhuma alteração de template necessária.
- Follow-up TODOs: nenhum placeholder pendente.
-->

# Constituição do Auditor MOOC

Plugin local Moodle `local_mooc_audit` do Cefor/Ifes: audita salas de cursos MOOC do Moodle 4.5 e
relata inconformidades técnicas, estruturais, de avaliação, conclusão, certificação, acessibilidade e
configuração, para apoiar o processo anual de reoferta. Esta constituição governa toda especificação,
plano, tarefa e código do projeto a partir da prova de conceito validada em 2026-10-28.

## Core Principles

### I. Independência operacional

- O Auditor MOOC MUST executar uma auditoria completa usando apenas o Moodle em que está instalado e o
  identificador do curso. Nenhuma outra aplicação (MOOC Admin, vitrine, SIPAC, serviço externo) MAY ser
  pré-requisito para auditar.
- Integrações com outros sistemas MUST ser opcionais, consumidoras do resultado do plugin, e MUST
  falhar sem impedir a auditoria.
- Toda regra que dependa de informação externa (projeto do curso, arte oficial, vitrine) MUST declarar
  essa dependência e produzir `NAO_VERIFICAVEL` quando a informação não estiver disponível, nunca
  bloquear a execução.

Racional: a investigação mostrou que a verificação por backup `.mbz` e por Web Services externos
depende de artefatos, credenciais e momento específicos; a PoC demonstrou que a sala real, lida de
dentro do Moodle, cobre o catálogo quase inteiro sem dependências. Reabrir essa decisão exige evidência
técnica nova.

### II. Compatibilidade e extensibilidade

- O produto MUST ser um plugin local Moodle padrão (`local/mooc_audit`), instalável por
  `admin/cli/upgrade.php`, sem qualquer alteração em arquivos do núcleo ou de outros plugins.
- Dados MUST ser lidos preferencialmente por APIs suportadas do Moodle (`get_fast_modinfo`,
  `grade_item`/`grade_category`, `enrol_get_instances`, `block_manager`, `file_storage`,
  `core_plugin_manager`, `completion_info`).
- Acesso direto a tabelas via DML MAY ser usado somente quando não existir API equivalente, MUST ser
  restrito a colunas explicitamente listadas (lista branca por módulo) e MUST ter a justificativa
  registrada no `README.md` do plugin, como hoje.
- Suporte a plugins de terceiros (`mod_game`, `mod_hvp`, `mod_simplecertificate`,
  `block_completion_progress`, `availability_days`) MUST tolerar ausência do plugin: a regra produz
  `NAO_APLICAVEL` ou `NAO_VERIFICAVEL`, nunca `ERRO` nem falha fatal.
- A versão mínima suportada é Moodle 4.5 (`$plugin->requires = 2024100700`); suporte a outras versões
  MUST ser declarado em `version.php` e coberto por testes antes de ser anunciado.

Racional: o Cefor mantém o Moodle institucional e plugins de terceiros com versões próprias; o custo
recorrente aceitável é acompanhar upgrades, não reescrever integrações.

### III. Auditoria não modifica cursos

- Executar uma auditoria MUST ser uma operação exclusivamente de leitura: nenhuma regra, coletor,
  renderizador ou comando de auditoria MAY escrever em tabelas de curso, módulo, nota, inscrição, bloco,
  arquivo ou configuração do site.
- A única escrita permitida pelo caminho de auditoria é a persistência do próprio resultado em tabelas
  do plugin, quando uma especificação a justificar.
- Scripts que criam ou alteram cursos (fixtures, importação de teste) MUST permanecer fora do caminho
  de auditoria, MUST recusar execução fora de ambientes de desenvolvimento/teste e MUST ser
  identificados como tal em seu cabeçalho.
- Testes automatizados MUST comprovar que uma auditoria não altera `timemodified` nem o conteúdo das
  tabelas de curso que examina.

Racional: a ferramenta será executada por servidores sobre salas institucionais em produção; um
diagnóstico com efeito colateral é inaceitável e indistinguível de um defeito.

### IV. Correção assistida e autorizada

- Qualquer funcionalidade de correção MUST ser uma operação separada do diagnóstico, com capacidade
  Moodle própria e distinta de `local/mooc_audit:audit`.
- Antes de aplicar uma correção o sistema MUST apresentar valor atual, valor esperado e impactos
  previstos, MUST verificar que o estado atual ainda corresponde ao diagnosticado (detecção de
  conflito) e MUST exigir confirmação explícita de um usuário com a capacidade adequada no contexto do
  curso.
- Toda correção aplicada MUST ser registrada (quem, quando, curso, regra, valor anterior, valor novo) e
  MUST ser seguida de nova auditoria do curso.
- Correções automáticas, silenciosas ou em lote sem confirmação individual por curso MUST NOT existir.

Racional: correções alteram salas que podem já ter estudantes inscritos; a rastreabilidade e a
autorização são exigências institucionais, não detalhes de implementação.

### V. Regras rastreáveis

- Toda regra MUST possuir: identificador estável (`CL-nn` para itens do checklist institucional,
  `X-ÁREA-nn` para verificações adicionais), título, descrição, fonte normativa ou técnica (item do
  checklist, decisão institucional, documentação do Moodle), condição de aplicação, critério de
  conformidade, evidência produzida, gravidade padrão, recomendação de correção e testes automatizados.
- O identificador de uma regra MUST NOT mudar de significado; uma regra descontinuada é marcada como
  tal, não reaproveitada.
- Regras institucionais (dependem de decisão do Cefor: nomes canônicos, datas, categoria, tentativas,
  blocos) MUST ler seus parâmetros da política (`policy`), nunca de constantes no código, e MUST ser
  distinguidas no catálogo de verificações técnicas universais (dependências impossíveis, arquivos sem
  conteúdo, ciclos).
- A política institucional MUST ser versionada (`policy_version`) e registrada em cada relatório.

Racional: o checklist institucional tem ambiguidades já decididas e uma pendente (nomes canônicos);
sem rastreabilidade, não é possível explicar um resultado nem ajustar a política sem tocar no código.

### VI. Confiabilidade e transparência

- Todo resultado MUST usar exatamente um dos estados `CONFORME`, `NAO_CONFORME`, `ATENCAO`,
  `NAO_APLICAVEL`, `NAO_VERIFICAVEL`, `ERRO`, com o significado fixado no catálogo.
- Dado ausente, plugin não instalado, permissão insuficiente ou verificação que exige ação humana MUST
  produzir `NAO_VERIFICAVEL` ou `NAO_APLICAVEL`; MUST NOT ser interpretado como `NAO_CONFORME`.
- Heurísticas textuais (nomes, similaridade, expressões regulares sobre enunciados) MUST produzir no
  máximo `ATENCAO`, salvo quando a decisão institucional registrada autorizar `NAO_CONFORME`.
- Uma exceção em uma regra MUST ser isolada como `ERRO` daquela regra e MUST NOT abortar a auditoria.
- Cada resultado MUST trazer local (nome e `cmid` quando houver), evidência, valor encontrado, valor
  esperado e orientação, de forma que uma pessoa sem acesso ao código compreenda onde está o problema e
  o que fazer.
- O relatório MUST registrar versão do motor, versão da política, ano de reoferta e data de execução;
  mesma sala, mesma política e mesma versão MUST produzir os mesmos resultados (exceto carimbos de
  tempo).

Racional: contagens que misturam "não verificado" com "falha" geram alarmes falsos e perda de
confiança; a PoC já registrou falsos positivos evitáveis (nomes duplicados em Libras, `?time=` em
referências de arquivo).

### VII. Testabilidade

- Toda regra nova ou alterada MUST ter testes PHPUnit cobrindo ao menos um cenário conforme e um não
  conforme, e MUST cobrir limites relevantes (ausência do módulo, plugin não instalado, dado nulo) quando
  a regra os distinguir.
- Cenários de teste MUST ser criados pelas APIs do Moodle (geradores de dados, `modlib`) e MUST NOT
  depender de backups institucionais, que não são versionados.
- Alterações no coletor de dados (snapshot) MUST manter os testes existentes passando e MUST adicionar
  teste para cada novo campo lido.
- A suíte completa MUST passar antes de qualquer entrega; o resultado da execução MUST ser registrado
  no artefato da entrega.

Racional: a PoC tem 29 regras e 3 testes; a maior parte das regras foi validada só manualmente contra
cursos sintéticos. Sem testes por regra, refatorar o catálogo é inseguro.

### VIII. Segurança

- Todo acesso MUST ser protegido por `require_login` e `require_capability` com capacidades próprias do
  plugin no contexto adequado (curso para auditar; sistema para comparar e para lotes); as capacidades
  MUST declarar `riskbitmask` e `captype` coerentes com a operação.
- O plugin MUST aplicar privilégio mínimo: nenhuma funcionalidade MAY exigir `moodle/site:config` além
  da página de configurações do próprio plugin.
- Segredos (senhas de inscrição, tokens, chaves) MUST NOT aparecer em relatório, log, JSON ou texto;
  apenas sua presença (booleano) MAY ser relatada.
- Dados pessoais MUST ser minimizados: a auditoria lê configuração de curso, não dados de estudantes;
  qualquer persistência que inclua dados pessoais MUST atualizar o `privacy/provider` e a
  especificação correspondente.
- Conteúdo de curso (HTML, `configdata` de blocos) MUST ser tratado como dado não confiável: nunca
  executado, sempre escapado na saída; desserialização MUST ser restrita a tipos escalares e arrays.
- Scripts de fixtures/importação MUST recusar execução quando `wwwroot` não indicar ambiente local e
  `debugdeveloper` estiver desligado.

Racional: o plugin roda dentro do Moodle institucional com acesso a todas as tabelas; o controle de
acesso nativo e a leitura estrita são o que o torna instalável em produção.

### IX. Simplicidade arquitetural

- O produto MUST usar recursos nativos do Moodle sempre que forem suficientes: capacidades, settings,
  tarefas agendadas/ad hoc, Web Services, DML, renderers e templates Mustache, PHPUnit e Behat.
- Microsserviços, filas externas, aplicações paralelas, bancos de dados adicionais e dependências
  Composer em tempo de execução MUST NOT ser introduzidos.
- Novas abstrações (interfaces, camadas, padrões) MUST ser justificadas por uma necessidade concreta
  da especificação em curso e registradas na seção Complexity Tracking do plano; abstração para uso
  futuro hipotético MUST NOT ser aceita.
- Refatorações MUST estar associadas a um benefício verificável (teste que passa a existir, regra que
  passa a ser isolada, duplicação removida) e MUST preservar o comportamento observável das regras
  (mesmos ids, estados e evidências nos cenários de teste), salvo correção documentada.

Racional: a PoC resolve o problema em cerca de 3.000 linhas de PHP sem infraestrutura adicional;
a complexidade a mais seria custo de manutenção para uma equipe pequena.

### X. Evolução incremental

- Cada funcionalidade MUST ter, antes da implementação: especificação com critérios de aceite
  testáveis, plano técnico, estratégia de testes e lista de tarefas, segundo o fluxo do Spec Kit
  adotado neste repositório (`specs/NNN-nome/`).
- O catálogo de regras MUST evoluir em incrementos especificados; nenhuma entrega MAY tentar
  implementar todo o catálogo ampliado de uma vez.
- Toda especificação MUST declarar o que fica fora de escopo e MUST ser verificável por alguém que
  não participou da implementação.
- O contrato JSON do relatório MUST ser versionado; mudanças incompatíveis exigem incremento de versão
  do contrato e nota de migração para consumidores.

Racional: há oito capacidades pretendidas (auditoria, relatório, orientação, correção assistida,
comparação, histórico, lote, API) e um catálogo de mais de 50 regras; entregar em fatias testáveis é
a única forma de preservar o investimento da PoC.

## Restrições técnicas e de ambiente

- Plataforma alvo: Moodle 4.5 (`MOODLE_405_STABLE`), PHP 8.1+ conforme exigido pelo Moodle 4.5,
  PostgreSQL e MySQL/MariaDB (nenhum SQL específico de banco fora do DML).
- Código segue as Moodle Coding Guidelines (PHP CodeSniffer com padrão `moodle`), com cabeçalho GPL,
  `defined('MOODLE_INTERNAL') || die()` e docblocks em todas as classes e métodos.
- Idioma da interface: `pt_br` e `en` obrigatórios em `lang/`; nenhuma string de interface fixa no
  código.
- Ambiente de desenvolvimento reproduzível: `moodle-env/` (moodle-docker, PostgreSQL 17, PHP 8.3) com
  os plugins de terceiros listados em `moodle-env/README.md`; divergências de versão em relação à
  produção MUST ser registradas e consideradas limitação conhecida.
- Backups `.mbz` e planilhas institucionais MUST NOT ser versionados (`.gitignore`); cenários de teste
  são sintéticos.
- O plugin não lê pacotes `.mbz`; integridade de pacote antes da restauração está fora do produto.

## Fluxo de desenvolvimento e portões de qualidade

- Fluxo por funcionalidade: `/speckit-specify` → `/speckit-clarify` (quando houver ambiguidade
  relevante) → `/speckit-plan` → `/speckit-tasks` → `/speckit-analyze` → implementação → revisão.
- Portão de especificação: critérios de aceite cobrem caminho conforme, não conforme, sem permissão,
  dado incompleto e falha isolada de regra, quando aplicável à funcionalidade.
- Portão de plano: seção Constitution Check preenchida; toda violação justificada em Complexity
  Tracking ou removida.
- Portão de código: PHPUnit completo passando; `php -l` e PHPCS padrão `moodle` sem erros; nenhuma
  escrita em tabelas de curso no caminho de auditoria (teste de não modificação).
- Portão de entrega: `version.php` incrementado; `README.md` do plugin atualizado (fontes de dados,
  limitações); catálogo de regras atualizado com status de implementação e cobertura de testes.
- Decisões institucionais pendentes (hoje: nomes canônicos, decisão 10) MUST ser registradas em
  `DECISOES-INSTITUCIONAIS-CHECKLIST.md` e refletidas na política padrão quando decididas; até então a
  regra afetada permanece com gravidade e estado que reflitam a incerteza.

## Governance

- Esta constituição prevalece sobre qualquer outra prática, documento ou preferência do projeto.
  Conflito entre constituição e especificação, plano ou código é resolvido ajustando o artefato, não
  reinterpretando o princípio.
- Emenda: proposta por escrito (o que muda, por quê, impacto em especificações vigentes), revisão pela
  equipe técnica do Cefor, registro no Sync Impact Report e incremento de versão. Emendas a princípios
  I, III, IV e VIII exigem também registro em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` ou documento
  institucional equivalente.
- Versionamento semântico: MAJOR para remoção ou redefinição incompatível de princípio; MINOR para
  princípio ou seção nova ou orientação materialmente ampliada; PATCH para esclarecimentos e redação.
- Revisão de conformidade: todo plano (`/speckit-plan`) MUST conter a seção Constitution Check com
  cada princípio avaliado; toda análise (`/speckit-analyze`) trata violação de MUST como CRITICAL;
  toda revisão de código verifica III, VI, VII e VIII explicitamente.
- Orientação operacional complementar fica em `README.md` do repositório e do plugin e em
  `moodle-env/README.md`; eles não alteram esta constituição.

**Version**: 1.0.0 | **Ratified**: 2026-10-28 | **Last Amended**: 2026-10-28
