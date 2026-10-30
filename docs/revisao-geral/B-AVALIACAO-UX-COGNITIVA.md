# Relatório B — Avaliação de UX/UI e carga cognitiva

**Auditor MOOC (`local_mooc_audit` 0.5.0) · revisão independente · 2026-10-29**

> Esta avaliação é heurística: foi feita por um agente de IA sobre páginas reais renderizadas. **Ela não substitui testes
> com usuários.** As hipóteses de melhoria precisam ser validadas com o roteiro da seção 8 antes de virar especificação.

## 1. Método e material

- **Ambiente:** laboratório `moocaudit-s5-web`, Moodle 4.5.15, plugin igual ao `main`, tema Boost padrão, idioma
  `pt_br`, usuário administrador. Larguras de 1366 px e 375 px (emulação de celular).
- **Estados examinados:** sala com bloqueantes e muitas inconformidades (Curso B: 25 não conformes, 5 alertas); sala sem
  inconformidades (Curso A, via comparação e painel); prévia de correção executável (CL‑13) e orientada (CL‑14 em jogo);
  resultado de correção aplicada; comparação A → B (invertida, com aviso); histórico com duas execuções; página de
  execução com evolução; painel com 104 salas; página de lote.
- **Estados não observados:** auditoria incompleta ou com `ERRO`; sala com muitos `NAO_VERIFICAVEL`; correspondência
  ambígua na comparação (os cenários A–G existem só no laboratório s3); lote com falha parcial; histórico extenso. Para
  esses estados, a análise se baseia nos templates.
- **Ferramentas:** leitura do texto renderizado, capturas (pasta `capturas/`), medição de altura da página e de
  transbordamento das tabelas por JavaScript, axe‑core do próprio Moodle com `best-practice`.
- **Critérios:** heurísticas de Nielsen; divulgação progressiva; reconhecimento em vez de memorização; prevenção de
  erros; linguagem simples (orientações do governo brasileiro de linguagem simples e de redação de interface); WCAG 2.2 AA,
  o compromisso declarado do Moodle.

## 2. Perfis de usuários

| Perfil | O que precisa responder | Densidade adequada | Telas principais |
|---|---|---|---|
| **Professor editor / proponente** | "O que está errado na *minha* sala, onde, e como conserto?" | Baixa: um problema por vez, linguagem do Moodle que ele conhece (nomes das telas e campos), nada de códigos | Relatório, correção |
| **Equipe técnica do Cefor** | "Qual a causa, onde está a configuração, posso corrigir aqui, ficou certo?" | Média a alta: códigos de regra e evidência técnica úteis, mas sob demanda | Relatório, correção, comparação, histórico |
| **Gestor das reofertas** | "Quais salas exigem atenção agora, o que piorou, o que falta fazer?" | Baixa por sala, alta no conjunto | Painel, lote |

Hoje as três telas centrais usam **a mesma densidade técnica para todos**. O relatório foi organizado para inspeção (por
estado → gravidade → categoria → regra), não para resolução. A hipótese do enunciado se confirma (seção 4).

## 3. Jornadas analisadas

1. **Professor:** curso → *Mais › Auditoria técnica* → lê o resultado → abre a configuração ou a proposta → corrige →
   volta → confirma que resolveu.
2. **Equipe técnica:** comparação origem × destino → pendências da nova oferta → correções → reauditoria → histórico.
3. **Gestor:** painel → filtra a categoria da reoferta → identifica bloqueantes e desatualizadas → cria lote → acompanha.

**Ruptura comprovada na jornada 1:** depois de uma correção aplicada com sucesso, "Voltar ao relatório" mostra o mesmo
problema ainda listado, com o botão de proposta (capturas `06` e `07`; detalhes em A‑01). Hoje esse é o maior defeito de
experiência, porque mina a confiança no ponto em que o usuário mais precisa dela. *Errata de 2026-10-30:* o README do
plugin já declarava essa limitação; em execuções novas a página mostra também "a sala foi alterada depois desta
auditoria", o que atenua mas não elimina a confusão.

## 4. Achados por interface

Formato: **ID — prioridade — título** · evidência · consequência · hipótese de melhoria. O esforço está no Relatório C.

### 4.1 Relatório individual (`index.php`)

**B‑01 — P2 — O inventário e os metadados técnicos vêm antes do resultado**
- Ordem atual (captura `02`): resumo da última execução → cabeçalho com motor, contrato e política → **tabela de
  inventário com 9 linhas** → "Situação geral" → duas tabelas de contagem → inconformidades. A 1366 px, a primeira
  inconformidade só aparece depois de **cerca de 1,3 tela** de rolagem.
- O inventário ("blocos: completion_progress, html, html"; "feedback: 3 · forum: 2 · game: 1…") usa nomes internos
  de módulo e não leva a nenhuma ação.
- **Hipótese:** situação geral e "o que fazer primeiro" no topo; inventário e metadados (motor, contrato, tempo de
  execução) num bloco recolhido "Detalhes técnicos da auditoria" no fim.

**B‑02 — P2 — O mesmo problema fica espalhado pelo relatório**
- "Questionário 1" aparece em **5 linhas, em 3 grupos de gravidade e 2 seções**: X‑DEP‑02 (bloqueante), CL‑13 × 2
  (alta), CL‑14 (média) e X‑DEP‑02 de novo em Alertas (alta). Para entender o que fazer com essa atividade, o usuário
  precisa reunir mentalmente cinco linhas distantes.
- A organização *gravidade › categoria* é boa para triagem, mas fragmenta o trabalho de correção, que acontece *por
  atividade*: abre-se a configuração uma vez e acertam-se vários campos.
- **Hipótese:** oferecer duas visões do mesmo resultado: "Por prioridade" (atual, para triagem) e "Por atividade" (para
  corrigir), com troca simples e memória da escolha. Ver seção 6.

**B‑03 — P2 — Evidências em linguagem de banco de dados**
- Exemplos reais: `status=1 novas_inscricoes=1 início=(não definida)`; `aggregateonlygraded=1 (excluir notas vazias)`
  / Esperado `0`; `completion=1` / Esperado `0`; `hiddensections=0` / Esperado `1`; `condições de nota=[]`;
  `tipos de condição: {"completion":2}`; `ciclo: 32 → 33 → 32` (ids internos, sem nomes).
- No CL‑08, `status=1` significa *desabilitado* no Moodle. O leitor que não conhece o banco entende o contrário. O
  campo "Encontrado", que diz "método desabilitado", corrige a leitura, mas está em outra linha.
- Os títulos são critérios, não problemas: "Glossário: proibido se colaborativo; vazio se fonte de jogos" não diz qual
  das duas coisas aconteceu.
- **Hipótese (o exemplo do enunciado se confirma):** cada ocorrência responde em linguagem do Moodle: *o que está errado*
  (frase), *onde* (atividade com link), *encontrado × exigido* (valores com unidade e nome do campo como aparece na tela
  de edição), *o que fazer* e *posso corrigir aqui?*. A evidência técnica bruta fica disponível sob demanda para a
  equipe técnica. Depende de A‑09 (textos para `lang/`).

**B‑04 — P2 — Contagens que misturam unidades**
- A tabela "Resultados por estado" mostra `Não conforme 25 · … · Conforme 28 · Regras 29`. Os 25 e os 28 são
  **ocorrências**; as 29 são **regras**. Logo abaixo, "Regras conformes" lista só **7** regras. Quem lê "Conforme 28 de
  29" conclui que a sala está quase toda certa, quando está "Bloqueada".
- **Hipótese:** no topo, uma frase: "5 problemas bloqueantes e 20 outros em 12 atividades; 7 de 29 verificações sem
  problemas". As contagens por estado vão para os detalhes técnicos.

**B‑05 — P2 — Alertas sem orientação**
- Nos 5 alertas do Curso B, a coluna *Orientação* está vazia em 3 (X‑DEP‑02 oculto, CL‑14 de encerramento ≠ fim,
  CL‑17 informativo). O usuário não sabe se precisa agir.
- **Hipótese:** todo alerta diz explicitamente "verifique X; se for intencional, nenhuma ação" (distingue observação,
  risco e obrigação).

**B‑06 — P2 — Botões duplicados para a mesma proposta**
- CL‑13 gera duas linhas (tentativas e enunciado) para o mesmo questionário. Os dois botões "Ver proposta de correção"
  levam à **mesma URL** (`rule=CL-13&cmid=24`), e a proposta resolve as duas de uma vez. CL‑17 mostra "Ver proposta"
  também quando a proposta é só orientada.
- **Hipótese:** um botão por *atividade + regra*. O rótulo distingue "Corrigir aqui (com confirmação)" de "Ver como
  corrigir".

**B‑07 — P2 — Celular e tela estreita: tabelas transbordam e o texto fica cortado**
- A 375 px, **21 de 23 tabelas** passam da largura e nenhuma está em `.table-responsive`. As colunas *Evidência* e
  *Orientação* saem da tela (captura `11`). A página do Curso B chega a cerca de **12.900 px** de altura (7.800 px no
  desktop).
- Isso fere o WCAG 1.4.10 (refluxo a 320 px). Professores consultam o Moodle no celular com frequência; o número exato
  não foi medido.
- **Hipótese:** em telas estreitas, linhas viram blocos empilhados (rótulo + valor). No mínimo imediato,
  `.table-responsive`.

**B‑08 — P3 — Hierarquia de títulos**
- A sequência é h1 (nome do curso) → h4 ("Desde a auditoria anterior") → h3 ("Auditoria técnica: …") → h4 … h6. O axe
  `best-practice` aponta `heading-order` no relatório, na comparação e no painel. Leitores de tela usam os títulos para
  navegar, então a estrutura importa.

**B‑09 — P2 — Depois de corrigir, o relatório não reflete a correção.** Ver A‑01; aqui é registrado como problema de
fluxo (Nielsen: visibilidade do estado do sistema).

### 4.2 Correção assistida (`correction.php`)

Pontos fortes a preservar (captura `05`):
- valor atual e proposto lado a lado;
- simulação ("deixariam de ocorrer…", "nenhuma inconformidade nova prevista");
- impedimentos separados de riscos;
- "Quem pode executar";
- confirmação explícita;
- aviso de que não há desfazer automático.

O fluxo diagnóstico → proposta → prévia → confirmação → execução está bem resolvido.

**B‑10 — P2 — A proposta orientada usa como referência um valor que está fora da política**
- CL‑14 no jogo "Palavras cruzadas": **Valor proposto: 30 junho 2027, 01:00 AM**, que é o término atual do curso. O
  próprio relatório marca esse término como não conforme (CL‑07 exige 31/12/2027). Para questionários, a estratégia
  executável bloqueia quando o CL‑07 não está conforme (`quiz_timeclose.php:84-85`). A orientada não bloqueia, só lista
  a dependência no fim.
- Consequência: o professor segue a orientação, grava uma data errada e terá de refazer depois de corrigir o CL‑07.
- **Hipótese:** quando a referência não está conforme, a proposta orientada diz "Corrija primeiro a data de término do
  curso (CL‑07); depois a data proposta será 31/12/2027", com link.

**B‑11 — P3 — O aviso "o valor anterior fica registrado" aparece também na correção orientada**
- `templates/correction.mustache:93` mostra `fix_noundo` sempre. Na correção orientada o plugin não grava nada, então a
  frase é falsa nesse caso.

**B‑12 — P3 — A tela de resultado mostra códigos internos**
- "Reauditoria: `CL-13: NAO_CONFORME, NAO_CONFORME → CONFORME`" e "Configuração: `attempts: 1 → 2`" (captura `06`). Os
  estados aparecem como constantes, sem tradução, e o campo usa o nome técnico. Na prévia, o professor vê
  `local/mooc_audit:applycorrection, moodle/course:manageactivities`.
- **Hipótese:** "Tentativas permitidas: 1 → 2. Verificado: o problema não aparece mais." As capacidades ficam só quando
  o usuário **não** pode executar (para saber o que pedir).

**B‑13 — P3 — Categoria da proposta repetida**
- "Correção executável mediante confirmação" seguido de um selo "A — executável mediante confirmação". A letra A/B/C é
  vocabulário interno da spec.

### 4.3 Comparação entre ofertas (`compare.php`)

Pontos fortes a preservar:
- a frase "a causa pode ser a importação ou uma alteração posterior: não é atribuída automaticamente";
- o aviso de origem e destino possivelmente trocados, com o link "Comparar invertido";
- a separação entre "Diferenças esperadas" e diferenças relevantes;
- a explicação "O que significa" em cada diferença.

**B‑14 — P2 — Termos sem definição e o mesmo problema em três lugares**
- No Curso B como destino, "Questionário 1" aparece em **Pendência da nova oferta** (CL‑14), **Novo no destino**
  (CL‑13, X‑DEP‑02) e **Atividades com configurações modificadas** (`extra.attempts 2 → 1`, `availability`). A página tem
  cerca de 8.900 px.
- Termos que exigem explicação: "Pendência da nova oferta", "Novo no destino", "Novo no destino (atividade adicional)",
  "Correspondências ambíguas", "Duplicidades no destino", e o estado "— → Não conforme" (o travessão significa "não
  existia na origem").
- **Colisão de vocabulário:** "Persistente" na comparação significa "já estava na oferta anterior". No histórico,
  significa "já estava na execução anterior da mesma sala". O usuário que usa as duas telas tende a confundir.
- **Hipótese:** organizar por pergunta da equipe: *1. O que precisa ser feito na nova sala* (pendências + problemas
  novos, por atividade); *2. O que mudou em relação à oferta anterior* (modificadas, ausentes, adicionais); *3. O que veio
  igual* (preservadas, esperadas, recolhido). Trocar "persistente" na comparação por "já existia na oferta anterior".

**B‑15 — P3 — Rótulos e títulos**
- A primeira linha da tabela de identificação das **salas** tem o rótulo "Atividade" (captura `08`; string
  `cmp_activity`).
- Há cabeçalho de coluna vazio (`empty-table-header`, axe).
- Títulos repetidos: "Comparar ofertas" aparece 2 vezes, "Síntese" 2 vezes, "Diferenças esperadas" 2 vezes.

**B‑16 — P3 — Diferenças com nomes de campo internos**
- `extra.attempts`, `extra.sumgrades`, `grade_items[0].grademax`, `formatoptions.hiddensections`. Textos colados
  ("referênciaCarga horária"; ver A‑11). A coluna "O que significa" ajuda, mas repete o nome técnico.

### 4.4 Histórico (`history.php`)

**B‑17 — P2 — A página de execução destaca o que não mudou**
- Com 30 problemas persistentes e nenhum novo ou resolvido, a página lista os **30 persistentes expandidos**, com
  "Antes" e "Agora" **idênticos** linha a linha, seguidos do relatório completo: cerca de 10.400 px. A informação útil
  ("nada mudou desde 17:38") cabe numa frase.
- **Hipótese:** mostrar expandidas só as classes que pedem atenção (novas, alteradas, resolvidas). Persistentes viram
  contagem com expansão opcional, e a coluna "Antes" só aparece quando difere.

**B‑18 — P3 — "Execução 3" parece ordinal**
- O número é o id global. No Curso B, que tem 2 execuções, "Execução 3" sugere que existe uma terceira. Usar a data como
  identificador principal e o id como detalhe.

### 4.5 Painel (`dashboard.php`) e lote (`batch.php`)

Pontos fortes a preservar:
- o aviso "'Sem inconformidades detectadas' … não é aprovação da sala" (evita introduzir a ideia de "curso aprovado"
  sem decisão institucional);
- a ordenação por situação (bloqueantes primeiro);
- os filtros por categoria, situação e gravidade;
- a seleção de salas para lote direto no painel.

**B‑19 — P2 — "Desatualizada" em todas as salas e com contraste insuficiente**
- **104 de 104** salas aparecem como desatualizadas por causa de uma atualização do plugin que não mudou regras (A‑03).
  O cartão "Desatualizada 104" perde o poder de discriminar.
- O texto "Desatualizada: …" usa `text-warning` em letra pequena. O axe aponta **`color-contrast` (grave) em 50 nós**,
  uma falha de WCAG 1.4.3 (contraste mínimo). O checkbox "Incluir subcategorias" falha `target-size` (WCAG 2.5.8).
  Os testes Behat não pegaram, porque o cenário de teste não tem salas desatualizadas.

**B‑20 — P2 — Indicadores que não levam a uma decisão**
- "Problemas desde a auditoria anterior de cada sala: **Persistentes: 1630**" é a soma de ocorrências de 104 salas e
  não orienta nenhuma ação.
- Respostas às perguntas do gestor:

  | Pergunta | Situação no painel |
  |---|---|
  | Salas que exigem atenção, com bloqueantes, não auditadas | **Sim** |
  | Salas desatualizadas | Sim, mas sem discriminação (B‑19) |
  | O que foi resolvido / quem piorou | Só por sala, na coluna "Desde a auditoria anterior"; não há filtro "pioraram" nem "melhoraram" |
  | Próxima ação | Não é explícita; há 3 links por linha ("Ver · Histórico · Auditoria técnica") |

- **Hipótese:** cartões que são filtros clicáveis ("53 com bloqueantes", "N pioraram", "N desatualizadas porque a sala
  mudou"); trocar o total de persistentes por "salas que pioraram" e "salas com problemas resolvidos"; uma ação principal
  por linha ("Abrir relatório").

**B‑21 — P3 — O andamento do lote não se atualiza sozinho**
- A página do lote mostra "Na fila / Em execução / Concluída" sem atualização automática. É aceitável, mas um aviso
  "atualize a página" ajuda.

## 5. Problemas transversais

**Linguagem.** Os códigos (CL‑13, X‑DEP‑02, cmid=24) aparecem como informação principal. Para a equipe técnica eles são
úteis; para o professor, são ruído. Mesmo na equipe técnica, `cmid=24` repete o link que já está ao lado. Há termos
de domínio sem definição: "não verificável", "não se aplica", "persistente", "pendência da oferta", "política
2027‑decisoes‑2026‑10‑28". Hoje não existe um glossário acessível a partir das telas.

**Hierarquia.** O resultado mais importante (situação + o que fazer primeiro) não está no topo. Detalhes técnicos
(motor, contrato, tempo em segundos, inventário) disputam atenção com o diagnóstico.

**Densidade.** Cada ocorrência ocupa uma linha de tabela com 5 colunas e até 6 blocos de texto. Com 25 ocorrências, a
página tem cerca de 9 telas. Cartões longos piorariam (seção 6).

**Cor e estado.** Os estados vêm sempre com texto ("Não conforme", "Atenção"), então não dependem só de cor, o que é
positivo. As exceções são o contraste do painel (B‑19) e os selos numéricos coloridos da tabela de contagens, que não
têm texto próprio além do cabeçalho da coluna.

**Teclado e leitores de tela.** Todos os controles são links, botões ou formulários nativos, sem JavaScript próprio, e
as tabelas têm `th scope`. Isso é bom para teclado. Não foi feito teste com leitor de tela real; ver o roteiro.

## 6. Alternativas de organização das inconformidades

| Alternativa | Bom para | Ruim para | Escala com 25+ ocorrências | Avaliação |
|---|---|---|---|---|
| Tabela compacta atual (gravidade › categoria) | Triagem técnica | Corrigir; celular | Longa, mas densa | Manter como visão "por prioridade" |
| Cartão por ocorrência | Leitura isolada | Comparar; rolagem | **Piora** (3–4× mais altura) | Não recomendado como padrão |
| **Lista por atividade, com linhas resumidas e expansão** | Corrigir (abre-se a atividade uma vez) | Visão global de gravidade | Boa: 12 atividades no lugar de 30 linhas | **Recomendado** como visão "por atividade" |
| Agrupar por ação ("corrigível aqui" / "na tela de edição" / "decisão humana") | Planejar o trabalho | Entender causa | Boa | Útil como filtro, não como estrutura principal |
| Por categoria institucional | Gestão do checklist | Corrigir | Média | Já existe como subnível; manter |

**Proposta proporcional (maquete conceitual, sem alterar a interface real):**

```
Curso B — inconsistente · Auditoria de 29/10/26 17:39            [Executar novamente]
┌────────────────────────────────────────────────────────────────────────────────┐
│ ⛔ Bloqueada: 5 problemas impedem o certificado ou a conclusão.                 │
│    20 outros problemas em 9 atividades · 5 pontos para conferir                 │
│    3 problemas podem ser corrigidos aqui, com confirmação.                      │
└────────────────────────────────────────────────────────────────────────────────┘
Ver: (•) Por prioridade   ( ) Por atividade        Filtrar: [Corrigível aqui]

▸ Bloqueantes (5)
  Questionário 1 · exige concluir "Aprofundamento", que não tem conclusão ativada
      → Ative a conclusão em "Aprofundamento" ou remova a condição.  [Abrir configuração]
  Certificado digital · não exige nota mínima de 60 no total do curso
      → Adicione a restrição de nota.                                [Abrir configuração]
  …
▸ Alta (9)
  Questionário 1 · permite 1 tentativa; o exigido é pelo menos 2    [Corrigir aqui]
      ▾ detalhes: regra CL-13 · encontrado 1 · exigido ≥ 2 (0 = ilimitadas) · fonte: decisão 1
  …
▸ Para conferir (5)   ▸ Sem problemas (7 verificações)   ▸ Detalhes técnicos da auditoria
```

Visão "por atividade" do mesmo resultado:

```
Questionário 1  [Abrir configuração]                         4 problemas · 1 corrigível aqui
  ⛔ condição de acesso nunca satisfeita ("Aprofundamento" sem conclusão)
  ● 1 tentativa (exigido: pelo menos 2)                          [Corrigir aqui]
  ● enunciado diz 2 tentativas; configuração diz 1                (resolvido pela correção acima)
  ● sem data de encerramento                                      [Ver como corrigir]
```

Princípios da proposta:
1. Uma linha por problema, frase de problema em vez de nome de regra.
2. Detalhes técnicos sob demanda (`<details>` nativo, sem JavaScript, acessível por teclado).
3. Contagem em frase no topo.
4. Ação explícita por linha.
5. Inventário e metadados no fim, recolhidos.

Estimativa de ganho, a validar: a altura da página do Curso B cairia de cerca de 7.800 px para cerca de 2.500 px com os
detalhes recolhidos.

## 7. Problemas de linguagem: exemplos de reescrita (hipóteses)

| Hoje | Proposta (a validar) |
|---|---|
| CL‑08 · `status=1 novas_inscricoes=1 início=(não definida) fim=(não definida)` | A autoinscrição está **desativada** e sem datas. Exigido: ativada, de 01/01/2027 a 31/12/2027. |
| CL‑12 · `aggregateonlygraded=1 (excluir notas vazias)` · Esperado `0` | O livro de notas está com "Excluir notas vazias" marcado. Desmarque: com ela ligada, a nota mínima de 60 do certificado vale só sobre as atividades já avaliadas. |
| CL‑20 · `hiddensections=0` · Esperado `1` | Seções escondidas aparecem para o estudante como "não disponíveis". Exigido: "completamente invisíveis" (Configurações do curso › Formato). |
| X‑CERT‑01 · `cadeia transitiva de conclusão: 6 módulos; tipos de condição: {"completion":2}` | O certificado pode nunca ser liberado: a cadeia de requisitos passa por "Aprofundamento" (sem conclusão) e "Leitura obrigatória" (oculta). |
| X‑DEP‑04 · `ciclo: 32 → 33 → 32` | "Ciclo A" exige "Ciclo B", que exige "Ciclo A": nenhum dos dois pode ser liberado. |
| Resultado da correção · `CL-13: NAO_CONFORME, NAO_CONFORME → CONFORME` | Verificado depois da alteração: o questionário agora atende à regra. |

Cuidados: distinguir **obrigação** (política institucional, com a decisão citada), **risco** (o que acontece com o
estudante) e **observação** (conferir; pode ser intencional). Não esconder o código da regra: ele é a ponte com a
equipe técnica e com o catálogo.

## 8. Roteiro para testes com usuários (validação empírica pendente)

**Nenhum teste com pessoas foi realizado.** As métricas abaixo são o protocolo proposto, não resultados.

**Participantes:** 5 por perfil (professor editor, equipe técnica, gestor), recrutados no Cefor. Pelo menos 2
professores com pouca experiência em configuração do Moodle.

**Preparação:** laboratório com o Curso B (bloqueantes), uma sala com só alertas e não verificáveis, um par
origem/destino com correspondência ambígua (cenários da spec 003), uma sala com 5+ execuções no histórico, um lote com
falha parcial. Gravação de tela e áudio com consentimento; protocolo de pensar em voz alta.

| # | Tarefa | Perfil | Sucesso | Métricas |
|---|---|---|---|---|
| T1 | "Qual é o primeiro problema que impede o certificado nesta sala?" | todos | Aponta um bloqueante | tempo até a resposta; rolagem; acerto |
| T2 | "Explique com suas palavras o problema do Questionário 1 e o que fazer." | professor, técnica | Explicação correta, sem ajuda | erros de interpretação; pedidos de ajuda |
| T3 | "Abra a configuração exata para corrigir a autoinscrição." | professor | Chega à tela certa | cliques; navegação desnecessária |
| T4 | "Corrija as tentativas do Questionário 1 e confirme que ficou certo." | professor, técnica | Aplica e reconhece que resolveu | conclusão; confusão na volta ao relatório (A‑01) |
| T5 | "Este item é 'não verificável'. O que você faria?" | técnica | Entende que não é falha | interpretação correta |
| T6 | "Compare a oferta 2026 com a 2027. O que é preciso fazer na nova sala?" | técnica | Lista as pendências corretas | tempo; itens esquecidos ou trocados |
| T7 | "Qual problema apareceu na nova sala e não existia antes?" | técnica | Identifica um item "novo no destino" | acerto; entendimento do termo |
| T8 | "Veja como estava a sala na auditoria da semana passada." | técnica, gestor | Abre a execução certa | tempo; entendimento de "persistente" |
| T9 | "Quais salas da reoferta precisam de atenção hoje? Por onde começaria?" | gestor | Filtra e justifica a ordem | tempo; uso dos filtros |
| T10 | "Audite todas as salas da categoria e descubra quais falharam." | gestor | Cria o lote e acha as falhas | conclusão; navegação |

**Após cada sessão:** SEQ (dificuldade da tarefa, 1–7) por tarefa; SUS ou UMUX‑Lite ao final; perguntas abertas sobre
termos desconhecidos.

**Critérios para aceitar a reorganização:**
- tempo mediano de T1 abaixo de 30 s;
- T2 correta sem ajuda em pelo menos 4 de 5 professores;
- nenhuma interpretação de "Conforme 28" como "quase tudo certo";
- T4 concluída sem dúvida sobre o resultado.

Comparar a versão atual com a protótipo na mesma sessão, em ordem alternada.

## 9. Recomendações priorizadas (UX)

| Prioridade | Itens | Natureza |
|---|---|---|
| Antes da homologação | B‑09/A‑01 (relatório após correção), B‑19 (contraste + desatualizadas), B‑10 (referência não conforme), B‑07 mínimo (`table-responsive`), B‑11 | Pequenas correções |
| Primeiro ciclo de UX (validar com usuários antes e depois) | B‑01, B‑02, B‑03, B‑04, B‑05, B‑06, B‑12 (relatório e correção) | Spec de experiência |
| Segundo ciclo de UX | B‑14, B‑15, B‑16 (comparação); B‑17, B‑18 (histórico); B‑20, B‑21 (painel) | Spec de experiência |
| Polimento | B‑08, B‑13 | Pequenas correções |

## 10. Limitações

- Só o perfil administrador foi usado no navegador. Professor editor e professor sem edição foram vistos nos cenários
  Behat e por leitura. O professor vê o mesmo relatório, sem o link "Comparar".
- Não houve teste com leitor de tela (NVDA/VoiceOver) nem navegação completa só por teclado. O axe automático cobre
  apenas parte dos critérios (30–50%, segundo a documentação do Moodle).
- O tema institucional do Ifes pode mudar contraste e espaçamento. Esta avaliação usou o tema Boost padrão.
- As capturas foram feitas em emulação de 1366 px reduzida no painel do navegador; as medidas de altura e
  transbordamento vêm do DOM, não das imagens.
