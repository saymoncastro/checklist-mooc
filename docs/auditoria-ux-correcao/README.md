# Auditoria de UX e da capacidade de correção do relatório de não conformidades

**Auditor MOOC (`local_mooc_audit` 0.6.1) · 2026-10-30 · ramo `claude/moodle-noncompliance-ux-audit-6a15e0`**

> **Natureza desta avaliação.** É uma inspeção do código e um percurso cognitivo feito por um agente de IA num Moodle
> real, com uma conta de professora editora de teste. **Nenhuma pessoa participou.** As jornadas da seção 10 são
> simulações do agente e não substituem o teste moderado previsto na Spec 006 (FR‑029/FR‑030). Onde há número, ele foi
> medido; onde há hipótese, ela está marcada.

Relatórios anteriores que este documento **não repete**, apenas atualiza: Relatório B da revisão geral
(`docs/revisao-geral/B-AVALIACAO-UX-COGNITIVA.md`, achados B‑01 a B‑21) e a Spec 006
(`specs/006-experiencia-relatorio/spec.md`). A Spec 006 tratava principalmente de **leitura** (hierarquia, linguagem,
visões). Esta auditoria trata de **resolução**: se a pessoa consegue, a partir de cada achado, chegar à tela certa,
corrigir sem quebrar outra coisa e confirmar o resultado.

## Sumário

1. [Método, ambiente e material](#1-método-ambiente-e-material)
2. [Diagnóstico executivo (Entregável 1)](#2-diagnóstico-executivo-entregável-1)
3. [Como o relatório é produzido hoje](#3-como-o-relatório-é-produzido-hoje)
4. [Casos obrigatórios A–D](#4-casos-obrigatórios)
5. [Achados de resolução (U‑01 a U‑22)](#5-achados-de-resolução)
6. [Matriz por regra: os 12 critérios](#6-matriz-por-regra-os-12-critérios)
7. [Matriz de capacidade de correção (Entregável 2)](#7-matriz-de-capacidade-de-correção-entregável-2)
8. [Ocorrência, causa e ação corretiva](#8-ocorrência-causa-e-ação-corretiva)
9. [Navegação contextual para a correção](#9-navegação-contextual-para-a-correção)
10. [Jornadas com o perfil de professor](#10-jornadas-com-o-perfil-de-professor)
11. [Severidade, prioridade e organização](#11-severidade-prioridade-e-organização)
12. [Acessibilidade e integração visual](#12-acessibilidade-e-integração-visual)
13. [Proposta de experiência (Entregável 3)](#13-proposta-de-experiência-entregável-3)
14. [Recomendações arquiteturais (Entregável 4)](#14-recomendações-arquiteturais-entregável-4)
15. [Plano SDD (Entregável 5)](#15-plano-sdd-entregável-5)
16. [Respostas finais](#16-respostas-finais)

---

## 1. Método, ambiente e material

| Item | Detalhe |
|---|---|
| Código inspecionado | 29 regras (`classes/local/rule/*.php`), `engine.php`, `locator.php`, `helpers.php`, `output/report.php`, `templates/report*.mustache`, estratégias de correção, `lang/{en,pt_br}` |
| Ambiente da captura | `moocaudit-dist53-web` (Moodle 5.3, porta 8101, instalado do ZIP 0.6.1, só inglês, **sem** `mod_simplecertificate`, `mod_game` e `block_completion_progress`). O Curso B desse site produz exatamente as **29 não conformidades** da captura |
| Sala de teste | Cópia do Curso B por backup/restauração no mesmo site: curso 4, "Sala de teste UX". Script em [`evidencias/preparar_jornada_professor.php`](evidencias/preparar_jornada_professor.php) |
| Perfil usado | Conta `prof.ux`, papel **Professor** (editingteacher) só nesse curso, sem papel no sistema; acesso pelo "Acessar como" do administrador. Capacidades conferidas por `has_capability` (seção 10) |
| Comparação | Curso B do laboratório principal (Moodle 4.5, `pt_br`, **com** os plugins de terceiros): 24 não conformidades e 6 alertas. Salas reais 55 e 670 do laboratório para a recorrência dos padrões |
| Medições | Altura da página e posição da primeira inconformidade (DOM), transbordamento de tabelas a 375 px, axe‑core do próprio Moodle (`/lib/behat/axe/axe.min.js`, WCAG 2.2 A/AA + boas práticas) |
| Evidências | JSON das auditorias antes e depois das correções em [`evidencias/`](evidencias/) |

O que **não** foi feito: teste com participantes; leitor de tela real; tema institucional do Ifes; sala com
`NAO_VERIFICAVEL` (nenhuma das salas usadas produz esse estado com o site configurado normalmente).

---

## 2. Diagnóstico executivo (Entregável 1)

**Em uma frase:** o motor encontra problemas reais e o relatório os descreve de forma tecnicamente correta, mas só em
4 das 29 regras a orientação é suficiente para corrigir sem ajuda, em nenhuma o relatório diz como confirmar a
correção, e em 6 regras a orientação está errada, contradiz outra regra ou não pode ser executada pela pessoa que a lê.

| Dimensão | Avaliação | Evidência principal |
|---|---|---|
| **Clareza do diagnóstico** | **Parcial** | Títulos são critérios ("Glossário: proibido se colaborativo; vazio se fonte de jogos"); evidências com valores de banco (`status=1`, `completion=1`, `hiddensections=0`, `aggregateonlygraded=1`), JSON (`condições de nota=[]`, `{"completion":2}`) e ids (`ciclo: 36 → 37 → 36`, `cmids [43]`). No site em inglês, evidência em português e orientação em inglês na mesma linha |
| **Compreensão das consequências** | **Insuficiente** | Só 6 regras mencionam alguma consequência, e só o X‑DEP‑02 ("nunca será satisfeita") de forma direta; nas demais é parcial (CL‑11, CL‑12 "excluir vazias", CL‑13 só na prévia, CL‑18, X‑INT‑03). "Bloqueante" é usado tanto para "o certificado nunca sai" (X‑DEP‑02) quanto para "o certificado sai sem nota mínima" (CL‑23 `min_grade`), que é o efeito oposto |
| **Capacidade de orientar correções** | **Insuficiente** | 4 regras acionáveis, 19 parciais, 6 não acionáveis (seção 7). Orientações erradas por herança do texto padrão da regra (CL‑11, CL‑16, CL‑21), contraditórias entre regras (CL‑17 × CL‑09/CL‑19) ou destrutivas sem aviso (CL‑18 "remova o glossário") |
| **Navegação até os ajustes** | **Parcial** | 16 telas distintas para 29 ocorrências; 2 sem link (X‑DEP‑04, X‑CERT‑01). Os links levam ao formulário inteiro, com as seções fechadas; nenhum usa o parâmetro nativo `showonly` que o próprio Moodle usa para abrir só "Restringir acesso" ou "Conclusão de atividade". A atividade que **precisa** ser alterada no X‑DEP‑02 não tem link |
| **Facilidade de validação** | **Parcial** | A correção assistida (CL‑13, CL‑14 em questionários) reaudita e grava (A‑01 resolvido no 0.5.1): comprovado, "Voltar ao relatório" mostra 27 e "Resolvidos: 2". Para correções feitas na tela do Moodle, o relatório não diz como confirmar; o Moodle devolve a pessoa à página do curso, não ao relatório |
| **Carga cognitiva** | **Alta** | Primeira inconformidade a 1.395 px (1,8 tela a 1024 × 768), página com 9.566 px; 34 linhas em 22 tabelas; 29 ocorrências apresentadas como 29 tarefas quando são 24 causas (seção 8); a ordem em que as correções precisam ser feitas não aparece, e uma delas **impede** outras (caso A) |
| **Acessibilidade** | **Parcial** | Controles nativos, estados com texto. axe: `scrollable-region-focusable` (grave) a 1024 px e `heading-order`; a 375 px, 20 de 22 tabelas exigem rolagem horizontal interna |

**O que já funciona e deve ser preservado:** a separação dos seis estados (não verificável ≠ falha); a correção
assistida com prévia, simulação, riscos e confirmação; a reauditoria gravada depois da correção; a chave estável de
ocorrência; o registro "a sala foi alterada depois desta auditoria"; o fato de o relatório nunca alterar nada sozinho.

---

## 3. Como o relatório é produzido hoje

```
regra::evaluate()  ──►  f(status, local, evidencia, {encontrado, esperado, orientacao, cmid, locus, check, subject})
        │                       │            │
        │              texto fixo em PT   get_string() no idioma de quem executa
        ▼
engine::run_rules()  ── preenche 'orientacao' de toda NAO_CONFORME sem orientação com a orientação geral da regra
        │             ── calcula a chave de ocorrência  regra|entidade|check[|subject]   e APAGA check e subject
        ▼
engine::audit_with_policy()  ── locator::url_for() → 'link' ABSOLUTO, calculado no momento da auditoria
        ▼
run_store (local_mooc_audit_run)  ── o JSON inteiro é gravado; index.php exibe SEMPRE o JSON gravado
        ▼
output\report + report_group.mustache  ── tabela: Regra | Estado | Local | Evidência | Orientação
```

Quatro consequências diretas para a experiência, todas verificadas no código:

1. **O texto é congelado no momento da auditoria.** Títulos e orientações ficam no idioma de quem clicou em
   "Executar"; local e evidência são português fixo no código da regra (`helpers::label()`, `"Seção {$n}"`,
   `"ciclo: ..."`). É a causa da mistura de idiomas da captura. Qualquer melhoria de redação só aparece em execuções
   novas (`index.php:117-118` lê `run_store::json($run, 'report')`).
2. **O texto de orientação padrão é aplicado sem olhar a verificação** (`engine.php:170-171`). Por isso "Nenhum
   certificado" (CL‑11) recebe "Adicione ao certificado a restrição da Pesquisa de Perfil".
3. **O motor já sabe mais do que mostra.** O código da verificação (`check`) e o objeto relacionado (`subject`, por
   exemplo `cm:38`, a atividade exigida no X‑DEP‑02, ou `36>37`, o ciclo) estão na chave de ocorrência gravada
   (`X-DEP-02|cm:43|target_completion|cm:38`). A apresentação pode usá‑los sem mudar o motor.
4. **Os links são absolutos e não são reavaliados.** Não se verifica, ao exibir, se a atividade ainda existe, se a
   pessoa tem permissão para editar, nem se o endereço do site mudou.

---

## 4. Casos obrigatórios

### Caso A — X‑DEP‑02: atividade exige conclusão de outra sem conclusão

**Hoje** (dist53, inglês): `quiz "Questionário 1" (cmid=43)` · `exige conclusão de page "Aprofundamento" (cmid=38) que
NÃO tem acompanhamento de conclusão` · *Enable completion on the target module or remove the condition; otherwise it
will never be satisfied.* Link: `modedit.php?update=43` (formulário do questionário, todas as seções fechadas).

**Como o motor identifica.** Percorre as condições de acesso de cada atividade (`snapshot::walk_conditions`); para cada
condição `completion` cujo alvo existe e tem `completion == 0`, emite NC na atividade **dependente** (`cmid` = 43) com
`subject = cm:<alvo>`. Os dois nomes estão no retrato; o do alvo só aparece dentro da frase de evidência.

**O que acontece de fato (verificado no laboratório):**

- *Para o estudante:* "Questionário 1" nunca é liberado. Como a "Avaliação do curso" exige "Questionário 1" e o
  certificado exige a "Avaliação do curso", **o certificado também nunca é liberado** (o X‑CERT‑01 mostra a mesma causa
  em outra linha, sem ligação visível entre as duas).
- *Para quem corrige no formulário:* com a seção "Restringir acesso" aberta, a condição aparece como **"Choose…"**
  com o selo **"Please set"**. O Moodle só lista no seletor atividades com conclusão ativada; a palavra "Aprofundamento"
  não aparece em lugar nenhum da tela.
- **Bloqueio de outras correções (comprovado):** seguindo a orientação do CL‑13 ("Nota › Tentativas permitidas"),
  alterei o valor para 2 e salvei. O Moodle **recusou o formulário** por causa da condição inválida; o banco continuou
  com `attempts = 1`. Enquanto o X‑DEP‑02 não for resolvido, nenhuma correção manual do Questionário 1 (CL‑13, CL‑14) é
  salva. A correção assistida do plugin funciona porque grava sem passar pelo formulário.

**Alternativas válidas, com a decisão que cada uma exige:**

| Alternativa | Onde | Quando faz sentido | Riscos |
|---|---|---|---|
| **1. Ativar a conclusão em "Aprofundamento"** | `modedit.php?update=38&showonly=activitycompletionheader` | "Aprofundamento" é pré‑requisito pedagógico do questionário | Visualizações anteriores à ativação **não contam**: o Moodle só registra a visualização quando a conclusão por visualização já está ativa (`lib/completionlib.php:788-790`); quem já leu precisará abrir de novo. O critério escolhido precisa seguir o CL‑17 (para página: visualizar) |
| **2. Remover a condição do questionário** | `modedit.php?update=43&showonly=availabilityconditionsheader`, condição "Choose…" | "Aprofundamento" é leitura complementar | O questionário fica liberado sem essa leitura; muda a sequência pedagógica |
| 3. Trocar a condição por outra atividade | mesma tela | A sequência pretendida era outra | Decisão pedagógica; o relatório não pode sugeri‑la |

**Resultado no laboratório:** ativei a conclusão por visualização em "Aprofundamento" pela tela do Moodle. A nova
auditoria resolveu `X-DEP-02|cm:43|target_completion|cm:38` sem criar ocorrência nova; o X‑CERT‑01 ficaria só com a
leitura oculta como impedimento (no dist53 o certificado não existe, ver caso C).

**Curso já utilizado:** ativar conclusão numa atividade sem dados de conclusão não aciona o bloqueio "opções de
conclusão travadas" do Moodle; alterar o critério de uma atividade que **já** tem dados aciona, e destravar apaga esses
dados. A orientação precisa distinguir os dois casos (o retrato não sabe se há dados de conclusão: **limitação**).

**O que o relatório deveria dizer** (texto proposto, seção 13): problema com os dois nomes; consequência em cadeia;
as duas alternativas com a pergunta pedagógica explícita; um link para cada alternativa já na seção certa; o aviso de
que o formulário do questionário não salva enquanto isso não for resolvido; e como confirmar.

### Caso B — X‑DEP‑04: ciclo

**Hoje:** local `Grafo de restrições`, evidência `ciclo: 36 → 37 → 36`, orientação "Remove one of the conditions to
break the cycle", **sem link** (`LOCUS = none`).

- **Nomes:** os ids do ciclo estão na chave de ocorrência (`X-DEP-04|course|cycle|36>37`) e no texto. Ao exibir, com o
  `get_fast_modinfo()` da sala, eles viram "Ciclo A" e "Ciclo B". Para execuções antigas cuja atividade foi apagada, o
  texto deve dizer "atividade removida depois da auditoria (id 36)".
- **Representação acessível:** uma lista ordenada basta e funciona em leitor de tela, sem diagrama:
  *"Ciclo A" só é liberada depois de concluir "Ciclo B" → "Ciclo B" só é liberada depois de concluir "Ciclo A" →
  volta ao início.* Um diagrama SVG só acrescentaria valor em ciclos longos, que não ocorreram em nenhuma sala.
- **Consequência:** nenhuma das atividades do ciclo pode ser liberada para estudantes. No Curso B, o ciclo **não**
  está na cadeia do certificado (cadeia: certificado → Avaliação → Questionário 1 → 3 páginas; e Pesquisa de Perfil),
  então ele bloqueia duas páginas, não a certificação. Hoje a gravidade "Bloqueante" não permite essa distinção; a
  apresentação pode calculá‑la a partir do grafo do retrato.
- **Orientação sem arbitrariedade:** o relatório não sabe qual sequência é a correta. Deve apresentar cada aresta como
  uma decisão — "Se 'Ciclo A' deve vir primeiro, remova de 'Ciclo A' a condição sobre 'Ciclo B'" e o inverso — com o
  link de "Restringir acesso" de cada atividade, e pedir que se confirme a sequência com quem planejou o curso.

### Caso C — CL‑23: certificado

**Requisito aplicado** (`cl23.php`, política `2027-decisoes-2026-10-28`): existe uma atividade `simplecertificate` que
não seja a declaração da licença; ela exige (a) a conclusão da "Avaliação do curso", (b) nota ≥ 60 **no item Total do
curso**, (c) operador raiz "todas". As quatro verificações aparecem como linhas independentes com `check` distinto.

**O que a captura mostra não é "falta o certificado"; é "falta o plugin do certificado".** No dist53,
`mod_simplecertificate` não está instalado. A restauração do Curso B descartou o certificado sem aviso, e quatro
ocorrências nascem da mesma causa: CL‑23 (`certificate_presence`, bloqueante), X‑CERT‑01 (`certificate_presence`,
bloqueante), CL‑11 (`certificate_presence`) e CL‑22 (declaração). A orientação "Importe o certificado da Sala Padrão"
**não pode ser cumprida** nesse site: a importação ignora atividades de plugins ausentes. Ao mesmo tempo, X‑INT‑03
("plugins usados pelo curso instalados") aparece como **conforme**, porque só examina plugins que a sala ainda usa.

| Pergunta | Resposta verificada |
|---|---|
| A ausência compromete a emissão ou só a conformidade? | Sem certificado, ninguém recebe certificado: compromete a emissão. Já `min_grade` ausente (Curso B do laboratório, `condições de nota=[]`) faz o oposto: o certificado é liberado **sem** nota mínima. Mesmo "Bloqueante", consequências opostas |
| "Importar da Sala Padrão" é sempre aplicável? | Não. Exige (1) o plugin instalado; (2) acesso à Sala Padrão com `moodle/backup:backuptargetimport` **na sala de origem**, que o professor da oferta normalmente não tem; (3) refazer as restrições depois: na importação de uma atividade isolada, condições sobre atividades e itens de nota não importados viram `cm = 0` e `gradeitemid = 0` (`availability/condition/completion/classes/condition.php:440-451` e `grade/classes/condition.php:293-304`; leitura do código, não testado neste ciclo). A regra X‑DEP‑01/X‑DEP‑03 passaria a apontar essas condições |
| Quem tem as permissões? | Instalar plugin: administrador do site. Importar: equipe da reoferta (com acesso à Sala Padrão). Ajustar restrições do certificado: professor editor (`moodle/course:manageactivities`, confirmado) |
| Depende de outros plugins? | Sim, de `mod_simplecertificate` (nome configurável na política). A condição de nota depende de o Total do curso somar 100 (CL‑12): a condição do Moodle é percentual do máximo; com soma 90 (dist53), "60%" vale 54 pontos |
| Como verificar depois? | Estático: executar a auditoria; CL‑23, X‑CERT‑01 e X‑DEP‑01/03 sem ocorrências. Funcional: um estudante de teste que conclua a cadeia e atinja 60 deve ver o certificado; um com 59 não deve (seção 4, caso D) |

### Caso D — X‑CERT‑01: estática × funcional

`xcert01.php` percorre a cadeia transitiva de **condições de conclusão** a partir do certificado e marca NC quando um
elo não existe, não tem conclusão ou está oculto. A orientação é sempre a mesma: *"Verificação estática; a emissão real
exige usuário de teste."* — um aviso, não uma ação.

| Situação | Natureza real | Como aparece hoje | Como deveria aparecer |
|---|---|---|---|
| Elo sem conclusão ou inexistente | **Falha comprovada**: uma condição de conclusão sobre atividade sem conclusão nunca é satisfeita | NC bloqueante, nomes só no texto "Encontrado", sem link | Problema comprovado, com os elos nomeados e ligados à mesma causa do X‑DEP‑02 |
| Elo oculto | **Falha comprovada para estudantes** (atividade oculta não pode ser concluída por eles); o X‑DEP‑02 trata o mesmo fato como **Atenção** | NC aqui, Atenção lá | Um único tratamento; divergência registrada como questão de regra (seção 14) |
| Cadeia sem impedimento estático | **Não comprovado**: a regra não avalia condições de nota, datas, grupos, tempo mínimo do `simplecertificate` (`requiredtime` está no retrato e não é lido), nem ciclos na cadeia | "Regras conformes · Alcançabilidade estática do certificado", ao lado de verificações realmente conclusivas | "Não encontramos impedimento na configuração. A emissão só fica comprovada com um teste" + procedimento em **Verificações manuais** |
| Sem certificado | Mesma causa do CL‑23 | Segunda NC bloqueante | Citada como consequência do CL‑23, não contada de novo |

O "usuário de teste" citado na orientação só existe na linha de comando (`cli/audit.php --userid`); a página não
oferece essa opção. Procedimento manual que a interface pode descrever sem implementar nada novo: criar ou usar uma
conta de estudante de teste, inscrever, concluir a cadeia (ou usar "Substituir conclusão" do relatório de progresso
para marcar as atividades), lançar a nota no livro de notas e abrir o certificado; repetir com nota abaixo de 60.

---

## 5. Achados de resolução

Natureza: **A** = só apresentação e textos (renderer, templates, `lang/`, sem mudar resultados); **C** = contrato aditivo
(campo novo opcional no JSON, sem mudar estados); **R** = regra ou motor (muda resultados; exige caracterização
regravada e aprovação). Os achados B‑xx citados vêm do Relatório B e continuam abertos no 0.6.1.

| ID | Prioridade | Natureza | Achado | Evidência |
|---|---|---|---|---|
| **U‑01** | P0 | R + A | **Plugin exigido pela política ausente não é detectado.** Sem `mod_simplecertificate`, 4 ocorrências mandam "importar" algo que não pode ser importado; sem `block_completion_progress`, o CL‑21 manda "adicionar o bloco HTML"; sem `mod_game`, o CL‑18 muda de diagnóstico e manda **remover** o glossário que, no site de produção, alimenta os jogos. X‑INT‑03 aparece conforme | dist53 × laboratório (curso 9: CL‑18 Atenção "fonte de 1 jogos"; dist53: NC "remova") |
| **U‑02** | P0 | A + C | **Ordem obrigatória entre correções não é informada.** Condição inválida no X‑DEP‑01/02 impede salvar o formulário da atividade (comprovado). Também: datas do curso (CL‑06/07) antes das datas de inscrição (CL‑08) e de encerramento (CL‑14); soma 100 (CL‑12) antes de conferir a nota mínima do certificado (CL‑23); conclusão da atividade (CL‑17) antes de exigi‑la na Avaliação (CL‑16), porque o seletor do Moodle só lista atividades com conclusão | Caso A; `strategy/quiz_timeclose.php` já bloqueia pelo CL‑07 |
| **U‑03** | P0 | A | **O formulário do Moodle não mostra o que o relatório descreve.** A condição sobre atividade sem conclusão aparece como "Choose…"/"Please set"; o relatório fala em "Aprofundamento" | Caso A |
| **U‑04** | P1 | A | **Orientação padrão contradiz a verificação** (`engine.php:170`): CL‑11 sem certificado → "adicione ao certificado…"; CL‑16 sem Avaliação → "adicione as restrições faltantes"; CL‑21 bloco de progresso → "adicione o bloco HTML" | dist53 |
| **U‑05** | P1 | R + A | **Orientações contraditórias para a mesma configuração.** Fórum institucional: CL‑09 "desative a conclusão" × CL‑17 "troque para automática". Rótulo: CL‑19 "desative" × CL‑17 "troque para automática". A própria prévia do CL‑17 para o rótulo propõe "sem acompanhamento" e diz no impacto "será marcada automaticamente" | dist53, `correction.php?rule=CL-17&cmid=34` |
| **U‑06** | P1 | A | **Ids internos como referência principal** (B‑03 aberto): `cmid=`, `ciclo: 36 → 37`, `cmids [43]`, `item 7`, `Autoinscrição (id 9)`, `quiz:43=50` | todas as salas |
| **U‑07** | P1 | C | **Idioma misto e congelado** (seção 3) | dist53: evidência PT + orientação EN |
| **U‑08** | P1 | A | **Links genéricos e não reavaliados.** Sem `showonly`; X‑DEP‑02 sem link para o alvo; ciclo e X‑CERT‑01 sem link; seção → página de visualização (não `editsection.php`); inscrição → lista (não `editinstance.php`); questionário sem questões → `view.php` (não `edit.php`); capítulo de livro → formulário do livro. O link da prévia do CL‑17 usa `#id_activitycompletionheader`, que abre o formulário **sem** expandir a seção | medido no navegador |
| **U‑09** | P1 | A | **Responsável não indicado.** A professora tem `moodle/course:changecategory`, mas **0 categorias** onde possa criar curso: o seletor de categoria (CL‑05) não lhe oferece "Inscrições Abertas". Importar da Sala Padrão exige acesso à sala de origem. Plugins exigem administrador | `has_capability` + `make_categories_list` |
| **U‑10** | P1 | A | **Consequência raramente explicada**: 6 de 29 regras mencionam alguma, só 1 de forma direta | seção 6, coluna C5 |
| **U‑11** | P1 | A + R | **Severidade não comunica o impacto.** "Bloqueante" para CL‑23 `min_grade` (certificado mais fácil, não impedido) e para X‑DEP‑04 fora da cadeia do certificado; o mesmo fato (alvo oculto) é Atenção no X‑DEP‑02 e NC no X‑CERT‑01 | casos B, C, D |
| **U‑12** | P1 | A + C | **Estático, heurístico e funcional não se distinguem.** X‑CERT‑01 conforme aparece como regra conforme; heurísticas de texto (CL‑13 enunciado, X‑AV‑01, CL‑20 nome genérico) e correspondência por nome (CL‑09, CL‑11, CL‑22, decisão 10 pendente) aparecem como fato | caso D |
| **U‑13** | P1 | R | **CL‑18 afirma "colaborativo" sem verificar.** Qualquer glossário sem jogo associado vira "proibido (colaborativo)" com a orientação "remova", sem aviso de perda de entradas | `cl18.php` |
| **U‑14** | P1 | A | **Alertas sem orientação** (B‑05 aberto): X‑DEP‑02 oculto, CL‑14 ≠ fim do curso, CL‑17 resumo, CL‑12 total ≠ 100, CL‑20 seção oculta com conclusão, CL‑13 enunciado sem número | dist53, laboratório |
| **U‑15** | P1 | A | **Nenhuma regra diz como confirmar a correção** além de "Executar novamente" | seção 6, coluna C10 |
| **U‑16** | P1 | A | **Hierarquia e contagens** (B‑01, B‑04 abertos): a primeira inconformidade a 1,8 tela; "Conforme 21 · Regras 29" numa sala bloqueada | 1024 × 768 |
| **U‑17** | P2 | A | **Textos com histórico interno**: "Na restauração do curso 670 isto ocorreu…", "Relatório de investigação §6.4", "unable to restore alias", `@@PLUGINFILE@@`, "stealth", `campo chapter[5]` | `lang/` e regras |
| **U‑18** | P2 | A | **Rótulo de campo desatualizado.** CL‑20 cita "completamente invisíveis" (4.5); no 5.3 a opção chama "Hide completely"; a string inglesa do plugin está truncada ("Tick 'Hidden sections are shown as not available' = completely invisible") | `lang/en/moodle.php` 4.5 × 5.3 |
| **U‑19** | P2 | A | **Ruído e duplicidade**: CL‑17 resumo ("nenhuma=8 manual=2 automática=8") como alerta; X‑CERT‑01 "sem certificado" repete o CL‑23; dois botões CL‑13 para a mesma proposta (B‑06) | dist53 |
| **U‑20** | P2 | A | **Retorno ao relatório.** Depois de salvar no `modedit.php`, o Moodle volta à página do curso; é preciso reabrir *Mais › Auditoria técnica* e executar de novo. `course/edit.php` e `enrol/editinstance.php` aceitam `returnurl`; `modedit.php` não | navegador |
| **U‑21** | P2 | A | **Resultado da correção com códigos** (B‑12/B‑13 abertos): `attempts: 1 → 2`, `CL-13: NAO_CONFORME, NAO_CONFORME → CONFORME`, selo "A — executable…", nomes de capacidade | dist53 |
| **U‑22** | P2 | A | **Celular e teclado**: 20 de 22 tabelas com rolagem interna a 375 px (página de 11.079 px); `scrollable-region-focusable` grave a 1024 px | axe, DOM |

**Contagem por natureza:** 15 achados só de apresentação e texto (A), 3 de contrato aditivo com apresentação (A + C ou
C), 4 que exigem mudança de regra (R, dos quais 3 também têm parte de apresentação).

---

## 6. Matriz por regra: os 12 critérios

**C1** título compreensível sem o código · **C2** descreve a condição encontrada · **C3** evidência compreensível ao
professor · **C4** identifica a atividade/configuração · **C5** consequência · **C6** ação concreta · **C7**
alternativas · **C8** onde ajustar no Moodle · **C9** chega à configuração sem navegação excessiva · **C10** como
confirmar · **C11** riscos · **C12** distingue comprovado de hipótese/verificação manual.

● atende · ◐ parcial · ○ não · — não se aplica. A avaliação considera **todas** as verificações da regra, não só a
orientação geral; "◐" em C6 significa que existe texto, mas falta objeto, valor ou passo.

| Regra | C1 | C2 | C3 | C4 | C5 | C6 | C7 | C8 | C9 | C10 | C11 | C12 | Evidência que decide |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CL‑04 sumário | ● | ◐ | ◐ | ● | ○ | ◐ | — | ● | ● | ○ | ○ | ● | "link https://… ausente"; não diz qual botão nem o código |
| CL‑05 categoria | ◐ | ● | ● | ● | ○ | ◐ | — | ● | ○ | ○ | ○ | ● | professor sem categoria disponível no seletor |
| CL‑06 início | ● | ◐ | ◐ | ● | ○ | ◐ | — | ● | ● | ○ | ○ | ● | "ano=2026 ≠ 2027"; não avisa que CL‑08/CL‑14 dependem dela |
| CL‑07 término | ● | ◐ | ◐ | ● | ○ | ◐ | — | ● | ● | ○ | ○ | ● | sem "Encontrado"; idem |
| CL‑08 autoinscrição | ● | ◐ | ○ | ◐ | ○ | ● | — | ● | ◐ | ○ | ○ | ● | `status=1` = desativada; link para a lista |
| CL‑09 fórum | ◐ | ◐ | ○ | ● | ○ | ● | — | ◐ | ◐ | ○ | ○ | ◐ | `completion=1`; contradiz CL‑17; nome canônico pendente |
| CL‑11 pesquisa de perfil | ◐ | ◐ | ○ | ◐ | ◐ | ○ | — | ○ | ◐ | ○ | ○ | ◐ | orientação errada sem certificado; `cmids [..]` |
| CL‑12 livro de notas | ◐ | ◐ | ○ | ◐ | ◐ | ◐ | ○ | ● | ◐ | ○ | ○ | ● | `quiz:43=50`, `aggregation=13`; só "excluir vazias" explica |
| CL‑13 tentativas | ● | ● | ◐ | ● | ◐ | ● | ◐ | ● | ◐ | ◐ | ◐ | ◐ | correção assistida completa; enunciado é heurística |
| CL‑14 encerramento | ● | ● | ● | ● | ○ | ◐ | ○ | ◐ | ◐ | ◐ | ◐ | ◐ | sem valor sugerido no relatório; alerta sem orientação |
| CL‑16 Avaliação do curso | ◐ | ◐ | ◐ | ● | ○ | ◐ | ○ | ○ | ◐ | ○ | ○ | ● | faltantes por nome (bom); não diz que precisam ter conclusão |
| CL‑17 critério de conclusão | ◐ | ◐ | ◐ | ● | ○ | ○ | ○ | ◐ | ◐ | ○ | ◐ | ◐ | "troque para automática" errado para rótulo/fórum |
| CL‑18 glossário | ○ | ◐ | ◐ | ● | ◐ | ○ | ◐ | ◐ | ◐ | ○ | ○ | ○ | afirma "colaborativo" sem verificar; "remova" |
| CL‑19 rótulos | ● | ◐ | ○ | ◐ | ○ | ● | — | ◐ | ◐ | ○ | ○ | ● | `completion=1`; mesma causa do CL‑17 |
| CL‑20 seções | ○ | ◐ | ○ | ◐ | ○ | ◐ | ◐ | ◐ | ◐ | ○ | ○ | ◐ | `hiddensections=0`; rótulo de campo do 4.5 |
| CL‑21 blocos | ● | ◐ | ○ | ◐ | ○ | ○ | ○ | ◐ | ◐ | ○ | ○ | ◐ | `blocos=["html","html"]`; bloco de progresso ≠ HTML |
| CL‑22 licença | ● | ● | ● | ◐ | ○ | ◐ | ○ | ● | ◐ | ○ | ○ | ◐ | importar exige acesso à Sala Padrão |
| CL‑23 certificado | ◐ | ◐ | ○ | ● | ○ | ◐ | ○ | ◐ | ◐ | ○ | ○ | ● | `condições de nota=[]`, `operador raiz=\|`; plugin ausente |
| X‑AV‑01 pontos | ◐ | ● | ● | ● | ○ | ◐ | ◐ | ○ | ◐ | ○ | ○ | ○ | heurística de enunciado apresentada como fato; afeta CL‑12 |
| X‑CERT‑01 alcançabilidade | ○ | ◐ | ○ | ◐ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ◐ | orientação é um aviso; sem links para os elos |
| X‑CONT‑01 vazios | ◐ | ● | ● | ● | ○ | ◐ | ◐ | ○ | ◐ | ○ | ○ | ● | "Preencha ou remova o recurso" também para questionário |
| X‑CONT‑04 arquivo perdido | ○ | ◐ | ○ | ◐ | ○ | ◐ | — | ◐ | ◐ | ○ | ○ | ● | `@@PLUGINFILE@@/banner.png`, `campo content` |
| X‑DEP‑01 alvo inexistente | ◐ | ◐ | ○ | ● | ○ | ◐ | ○ | ○ | ◐ | ○ | ○ | ● | `cm=…`; não diz como aparece no formulário |
| X‑DEP‑02 alvo sem conclusão | ◐ | ● | ◐ | ◐ | ● | ◐ | ● | ○ | ◐ | ○ | ○ | ◐ | caso A |
| X‑DEP‑03 item de nota | ◐ | ◐ | ○ | ● | ○ | ◐ | ○ | ○ | ◐ | ○ | ○ | ● | `id=…` |
| X‑DEP‑04 ciclo | ◐ | ◐ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ● | caso B |
| X‑INT‑02 armazenamento | ◐ | ● | ◐ | ● | ○ | ◐ | ○ | ○ | ◐ | ○ | ○ | ● | `mod_resource/content, 19 bytes` |
| X‑INT‑03 plugins | ● | ● | ◐ | ◐ | ◐ | ◐ | ◐ | ○ | ○ | ○ | ○ | ◐ | só plugins usados; verde com o certificado faltando |
| X‑STR‑01 ocultos | ◐ | ◐ | ◐ | ● | ○ | ◐ | — | ○ | ◐ | ○ | ○ | ● | "(stealth)"; não liga ao X‑DEP‑02/X‑CERT‑01 |

**Totais por critério (● / ◐ / ○, de 29):** C5 consequência 1 / 5 / 23 · C6 ação 4 / 19 / 6 · C8 onde 8 / 9 / 12 ·
C10 confirmar 0 / 2 / 27 · C11 riscos 0 / 3 / 26. Os critérios de leitura (C1–C4) estão razoáveis; os de resolução
(C5, C10, C11) são o ponto fraco.

---

## 7. Matriz de capacidade de correção (Entregável 2)

Responsável: **Prof** = professor editor; **Equipe** = equipe da reoferta (acesso à Sala Padrão, categorias);
**Adm** = administrador do site. "Orientação acionável" considera o perfil indicado como responsável.

| Regra | Problema como aparece hoje | Consequência explicada | Orientação acionável | Caminho de correção | Responsável | Lacuna | Prior. |
|---|---|---|---|---|---|---|---|
| **X‑DEP‑02** Condição exige conclusão de módulo sem conclusão ou oculto | `exige conclusão de page "Aprofundamento" (cmid=38) que NÃO tem…` | Parcial ("nunca será satisfeita"; sem a cadeia) | Parcial | Existente só para a dependente; ausente para o alvo; sem seção | Prof | nomes como sujeito; link para os dois lados com `showonly`; aviso de bloqueio do formulário; alternativas com decisão; alerta "oculto" sem orientação | **P0** |
| **X‑DEP‑04** Ciclos no grafo | `Grafo de restrições · ciclo: 36 → 37 → 36` | Não | Não (sem nomes) | Ausente | Prof (decisão pedagógica) | nomes, relação textual, link por aresta, se está na cadeia do certificado | **P0** |
| **CL‑23** Certificado condicionado… | `nenhum certificado de conclusão (simplecertificate)` / `condições de nota=[]` / `operador raiz=\|` | Não | Parcial | Importação (genérica) ou formulário | Adm (plugin) → Equipe (importar) → Prof (restrições) | detectar plugin ausente; consequência por verificação; refazer restrições após importar; depende do CL‑12 | **P0** |
| **X‑CERT‑01** Alcançabilidade estática | `sem certificado` ou `cadeia… {"completion":2}` | Não | Não | Ausente | Prof / Equipe | elos nomeados com link; conforme ≠ comprovado; procedimento de teste funcional; não contar de novo a falta do certificado | **P0** |
| **CL‑17** Critério de conclusão | `conclusão MANUAL` · esperado "automática (ou nenhuma…)" | Não | Não para rótulo/fórum institucional | Formulário; prévia com âncora que não abre a seção | Prof | orientação por tipo; resolver a contradição com CL‑09/CL‑19; resumo informativo fora dos alertas | P1 |
| **CL‑21** Blocos padrão | `blocos=["html","html"] (fonte: block_manager)` | Não | Não (bloco de progresso) | Página do curso (sem modo de edição) | Prof (HTML) / Adm (plugin de progresso) | orientação por bloco; conteúdo do bloco Acessibilidade; plugin ausente | P1 |
| **CL‑18** Glossário | `módulo glossary presente (0 entradas) sem uso por jogos` | Parcial (repovoar) | Não ("remova") | Formulário ou visualização | Prof / Equipe | verificar colaboração; risco de perda; depende de `mod_game` | P1 |
| **CL‑11** Pesquisa de Perfil | `nenhum certificado de conclusão` + orientação sobre o certificado | Parcial | Não (sem certificado) | Importação | Equipe / Prof | orientação por verificação | P1 |
| **CL‑16** Avaliação do curso | `exige conclusão de cmids [43]` · faltam: Questionário 2 | Não | Parcial | Formulário (sem seção) | Prof | dizer "Restringir acesso"; depende do CL‑17 | P1 |
| **CL‑12** Livro de notas | `soma dos máximos=90 itens=quiz:43=50…`; `aggregateonlygraded=1` | Parcial (só "excluir vazias") | Parcial | Configuração do livro de notas | Prof | quais atividades e quanto (decisão); efeito no CL‑23 e no X‑AV‑01 | P1 |
| **CL‑08** Autoinscrição | `status=1 novas_inscricoes=1 início=(não definida)` | Não (ninguém se inscreve sozinho) | Sim | Lista de métodos | Prof | valores em palavras; `editinstance.php`; datas depois do CL‑06/07 | P1 |
| **CL‑05** Categoria | `categoria="Category 1"` | Não | Parcial (não para o professor) | Configurações do curso | Equipe | dizer quem move; não sugerir ação indisponível | P1 |
| **CL‑09** Fórum institucional | `completion=1` | Não | Sim (contradita) | Formulário (sem seção) | Prof | uma ação com o CL‑17; `showonly` | P1 |
| **X‑INT‑03** Plugins | `módulo X NÃO instalado` | Parcial | Parcial | Ausente | Adm | incluir plugins exigidos pela política | P1 |
| **X‑CONT‑01** Recursos vazios | `questionário sem questões`; texto com "curso 670" | Não | Parcial | View do questionário | Prof | `mod/quiz/edit.php`; orientação por tipo | P1 |
| **X‑CONT‑04** Arquivo perdido | `@@PLUGINFILE@@/banner.png … campo content` | Não | Parcial | Formulário (capítulo de livro: tela errada) | Prof | linguagem; capítulo; origem comum com X‑CONT‑01 em salas restauradas | P1 |
| **CL‑13** Tentativas | `tentativas permitidas=1` · esperado `≥ 2 (0 = ilimitadas)` | Parcial (na prévia) | Sim (correção assistida) | Correção assistida ou formulário | Prof | um botão; aviso de que o formulário não salva com X‑DEP‑02 | P2 |
| **CL‑14** Encerramento | `data de encerramento não definida` | Não | Parcial | Correção (questionário) ou formulário | Prof | valor sugerido; `showonly=timing`; alerta sem orientação | P2 |
| **CL‑06 / CL‑07** Datas | `ano=2026 ≠ 2027` / `término=30/06/2027` | Não | Parcial | Configurações do curso | Prof | valor exigido na ação; ordem antes do CL‑08/CL‑14; `returnurl` | P2 |
| **CL‑19** Rótulos | `completion=1` · esperado `0` | Não | Sim | Formulário | Prof | uma ação com o CL‑17 | P2 |
| **CL‑20** Seções | `hiddensections=0` · esperado `1 (completamente invisíveis)` | Não | Parcial | Configurações do curso; seção → visualização | Prof | rótulo do campo pelo núcleo; `editsection.php` | P2 |
| **CL‑22** Licença | `declaração "…": ausente` | Não | Parcial | Importação | Equipe | acesso à Sala Padrão; plugin do certificado | P2 |
| **X‑AV‑01** Enunciado × pontos | `enunciado declara 50 pontos; nota máxima=40` | Não | Parcial | Formulário | Prof | marcar como conferência de texto; efeito no CL‑12 | P2 |
| **X‑DEP‑01 / X‑DEP‑03** Alvo ou item inexistente | `cm=…` / `id=…` | Não | Parcial | Formulário | Prof | como aparece no Moodle; bloqueia o formulário | P2 |
| **X‑INT‑02** Arquivo sem conteúdo | `arquivo "apostila.txt" (mod_resource/content, 19 bytes)…` | Não | Parcial | Formulário | Prof / Adm | linguagem; quando é problema do armazenamento | P2 |
| **X‑STR‑01** Ocultos | `módulo oculto (stealth)` | Não | Parcial | Formulário | Prof | ligar ao X‑DEP‑02/X‑CERT‑01 | P2 |
| **CL‑04** Sumário | `link https://… ausente` | Não | Parcial | Configurações do curso | Prof | qual botão e o código a colar | P3 |

---

## 8. Ocorrência, causa e ação corretiva

Os três conceitos são úteis e **já são calculáveis em parte** com o que o JSON grava, sem similaridade de texto.

| Relação | Critério técnico (demonstrável) | Exemplo real | Disponível hoje? |
|---|---|---|---|
| Mesma correção resolve várias ocorrências | A simulação do planejador (`planner`) já lista "deixariam de ocorrer" | CL‑13 tentativas + enunciado | Sim, para regras com estratégia |
| Mesma configuração, mesma ação | mesma entidade (`cm:32`) e mesmo campo do Moodle (conclusão) | CL‑09 + CL‑17 no fórum; CL‑19 + CL‑17 no rótulo | Sim, com uma tabela "verificação → campo" na apresentação |
| Mesma causa ausente | mesmo `check` em regras diferentes | `certificate_presence` em CL‑11, CL‑23 e X‑CERT‑01 | Sim, pela chave de ocorrência |
| Mesmo objeto como causa | `subject` de uma ocorrência = entidade de outra | X‑DEP‑02 `subject cm:41` = X‑STR‑01 `cm:41` (oculto) | Sim, pela chave de ocorrência |
| Causa dentro de outra regra | X‑CERT‑01 lista elos só em texto ("Aprofundamento… sem conclusão") | X‑CERT‑01 × X‑DEP‑02 `cm:38` | **Não**: exige `subjects` estruturados no X‑CERT‑01 (contrato) |
| Ordem obrigatória | condição inválida na entidade X bloqueia o formulário de X; dependências de valor entre regras | X‑DEP‑02 em `cm:43` antes de CL‑13/CL‑14 em `cm:43`; CL‑06/07 antes de CL‑08/CL‑14 | Sim, com regras de precedência declaradas (lista fechada, testada) |
| Mesmo arquivo perdido | nome de arquivo igual em X‑CONT‑01 e X‑CONT‑04 | sala 670: "Manual de Acessibilidade…" em 1 recurso e 4 referências | **Não seguro** (coincidência de nome não prova a causa): apresentar como "possivelmente relacionadas" ou não agrupar |

**Cálculo no Curso B do dist53 (29 não conformidades):** juntando apenas as relações demonstráveis das quatro primeiras
linhas, ficam **24 causas**, em **16 telas** do Moodle. Dessas 24, ao menos 4 não podem ser resolvidas pela professora:
instalar `mod_simplecertificate` (resolve 3 ocorrências e permite a quarta, a declaração), instalar
`block_completion_progress`, mover a categoria, importar componentes da Sala Padrão. O número de ações **só** deve
aparecer quando calculado por essas regras fechadas; quando houver relação não demonstrável, a tela conta ocorrências.

**Evolução necessária para agrupar com segurança:** gravar `check`, `subject` e uma lista `subjects` (entidades
relacionadas, como os elos do X‑CERT‑01) como campos opcionais do contrato 1.1 — hoje são apagados depois de compor a
chave (`engine.php:177`). Parsear a chave funciona, mas acopla a apresentação ao formato da chave; é aceitável como
ponte para execuções antigas.

---

## 9. Navegação contextual para a correção

Todas as rotas abaixo existem no Moodle 4.5 e no 5.3 (conferido nas duas árvores). `showonly` é o mecanismo que o
próprio núcleo usa nos selos de conclusão e de restrição da página do curso
(`course/format/classes/output/local/content/cm/{completion,availability}.php`); quando a seção não existe no
formulário, `moodleform::filter_shown_headers()` a ignora e o formulário abre inteiro — falha segura.

| Necessidade | Rota nativa | Usada hoje? |
|---|---|---|
| Restringir acesso da atividade | `/course/modedit.php?update=<cmid>&showonly=availabilityconditionsheader` | Não (formulário inteiro) |
| Conclusão de atividade | `/course/modedit.php?update=<cmid>&showonly=activitycompletionheader` | Não; a prévia usa âncora `#id_activitycompletionheader`, que **não** expande a seção |
| Nota / tentativas (questionário) | `/course/modedit.php?update=<cmid>&showonly=modstandardgrade` | Não |
| Datas do questionário | `...&showonly=timing` | Não |
| Restrições de uma seção | `/course/editsection.php?id=<sectionid>&showonly=availabilityconditions` | Não (`section.php`, visualização) |
| Editar a autoinscrição | `/enrol/editinstance.php?courseid=<c>&id=<id>&type=self&returnurl=<relatório>` | Não (lista) |
| Configurações do curso, voltando ao relatório | `/course/edit.php?id=<c>&returnto=url&returnurl=<relatório>` | Não |
| Questões do questionário | `/mod/quiz/edit.php?cmid=<cmid>` | Não (`view.php`) |
| Capítulo de livro | `/mod/book/edit.php?cmid=<cmid>&id=<chapterid>` | Não (o retrato precisa guardar o id do capítulo) |
| Conferir conclusão dos estudantes | `/report/progress/index.php?course=<c>` | Não |
| Livro de notas | `/grade/edit/tree/index.php?id=<c>` | Sim |
| Importar | `/backup/import.php?id=<c>` | Sim |

**Cuidados obrigatórios para não oferecer uma ação que não existe:**

- **Calcular os links ao exibir**, a partir de `locus`, `cmid`, `check` e `subject` gravados, com o `modinfo` atual. Se
  a atividade não existe mais: "removida depois desta auditoria", sem link.
- **Verificar a permissão de quem vê** (`moodle/course:manageactivities` no contexto do módulo, `moodle/course:update`,
  `enrol/self:config`, `moodle/grade:manage`); sem permissão, mostrar quem pode, não o botão.
- **Endereço relativo** (`moodle_url` ao exibir), para não depender do `wwwroot` gravado.
- Nada de links com `sesskey` (ativar modo de edição): eles não podem ser gravados nem pré‑calculados; o texto diz
  "ative o Modo de edição".
- Execuções antigas: o JSON gravado continua com `link`; a tela o substitui pelo calculado quando possível e mostra o
  gravado só se o cálculo não for possível.

---

## 10. Jornadas com o perfil de professor

Conta `prof.ux`, papel Professor no curso 4. Capacidades verificadas: `local/mooc_audit:audit`, `preparecorrection`,
`applycorrection`, `moodle/course:update`, `moodle/course:manageactivities`, `moodle/course:changecategory`,
`enrol/self:config`, `moodle/grade:manage`, `moodle/backup:backuptargetimport`, `moodle/site:manageblocks` — **todas
sim**; categorias em que pode criar curso: **0**. Respostas dadas pelo agente simulando o perfil, a partir **apenas** do
que a tela mostra (sem ler o código).

| Cenário | 1. Entende o problema? | 2. Explica a consequência? | 3. Encontra a configuração? | 4. Corrige? | 5. Reaudita? | 6. Sabe se resolveu? |
|---|---|---|---|---|---|---|
| **X‑DEP‑02** (dependência) | Parcial: "Aprofundamento" está no texto, entre `page` e `(cmid=38)` | Parcial: "nunca será satisfeita"; não sabe que o certificado também trava | **Não** pelo link: abre o questionário, a condição aparece "Choose…". "Aprofundamento" só pelo índice do curso | Sim, depois de achar a página e a seção (ativei a conclusão) | Sim: "Executar novamente" | Sim: a linha some; nenhuma orientação diz o que esperar |
| **CL‑13 manual** (efeito colateral do X‑DEP‑02) | Sim | Não (só na prévia) | Sim: "Nota › Tentativas permitidas" | **Não**: o Moodle recusa salvar ("Please set" na restrição, longe do campo editado) | — | — |
| **CL‑13 assistida** | Sim | Sim (prévia: "Students will have up to 2 attempts") | Sim | Sim | Automática | Sim, mas lido em código: `attempts: 1 → 2`, `NAO_CONFORME → CONFORME` |
| **X‑DEP‑04** (ciclo) | **Não**: `ciclo: 36 → 37 → 36` | Não | **Não**: sem link e sem nomes; "Ciclo A/B" só se adivinhar pelo índice | Não sem ajuda | — | — |
| **CL‑23 + X‑CERT‑01** (certificação, dist53) | Parcial: "nenhum certificado" | Não | Link de importação | **Não**: o plugin não está instalado; a importação não traz o certificado; a professora não tem acesso à Sala Padrão | — | Não: duas linhas bloqueantes sem saída; nada indica que é com o administrador |
| **CL‑17 rótulo** (média) | Parcial: "conclusão MANUAL" | Não | Sim | Risco de erro: seguir "troque para automática" mantém o CL‑19 e contraria a prévia | Sim | Parcial |
| **CL‑20 seções ocultas** (baixa) | **Não**: `hiddensections=0` | Não | Parcial: "Configurações do curso › Formato", mas o rótulo citado não existe no 5.3 | Provável, por tentativa | Sim | Sim |
| **CL‑05 categoria** (outro responsável) | Sim | Não | Sim (o campo existe) | **Não**: "Inscrições Abertas" não aparece no seletor; o relatório não diz quem move | — | — |

**Resultado:** dos 8 cenários, a professora completa sem ajuda 3 (CL‑13 assistida, CL‑20 por tentativa, X‑DEP‑02
depois de procurar). Em 3 ela não consegue (ciclo, certificado, categoria) e nada no relatório diz a quem recorrer; em
2 ela erra ou é bloqueada por outra inconformidade sem saber (CL‑13 manual, CL‑17).

**Teste moderado com pessoas:** não realizado. O material está pronto em `specs/006-experiencia-relatorio/validacao/`
(roteiro T1–T5). Este estudo acrescentou ao roteiro e aos cartões duas tarefas: **T6** "Libere o Questionário 1 e
confirme" (caso A) e **T7** "O que impede ou prejudica o certificado, e quem resolveria?" (casos C e D), com o código
de observação **INT‑ORDEM** para quem tentar corrigir as tentativas pela tela de edição antes da condição.

---

## 11. Severidade, prioridade e organização

- **O professor entende bloqueante/alta/média/baixa?** Os rótulos não dizem o critério, e o mesmo rótulo cobre
  impactos opostos (caso C). Hipótese a validar: o professor entende melhor **impactos** do que níveis: "impede a
  certificação", "impede o acesso a atividades", "impede inscrições", "contraria a política institucional", "conferir".
- **A severidade corresponde a um impacto?** Parcialmente. Sem mudar as gravidades (FR‑025 da Spec 006), a apresentação
  pode **acrescentar** a frase de impacto por verificação e calcular dois fatos a partir do retrato: a ocorrência está
  na cadeia do certificado? Afeta o acesso de estudantes?
- **Há bloqueantes que impedem disponibilizar ou certificar?** Sim: CL‑08 (sem autoinscrição ativa ninguém se inscreve sozinho —
  hoje é "Alta", não bloqueante), X‑DEP‑02 e X‑CERT‑01 (certificação), CL‑23 presença. A classificação institucional é
  decisão do Cefor; o relatório deve, no mínimo, dizer o efeito.
- **A categoria técnica ajuda?** Ajuda a equipe técnica a navegar o checklist; atrapalha a correção, que é por
  atividade (B‑02). Proposta: manter como filtro/subtítulo, não como estrutura principal.
- **Dependências entre correções:** sim, com segurança, para a lista fechada da seção 8 (precedências declaradas e
  testadas). Fora dela, não inferir.

**Organização recomendada (sem painel decorativo):**

1. **Resumo operacional** (uma caixa): situação; *"29 problemas em 9 atividades e nas configurações da sala; 24 ações;
   4 dependem de outra pessoa; 1 pode ser corrigida aqui com confirmação"*; aviso de desatualizado/incompleto.
2. **Comece por aqui** (no máximo 3 itens): bloqueantes ordenados pela precedência (o X‑DEP‑02 vem antes do CL‑13 da
   mesma atividade).
3. **Pendências de correção**, em duas visões do mesmo conteúdo: *por atividade* (para corrigir) e *por prioridade*
   (para triagem). Cada ação em uma linha resumida com `<details>` nativo.
4. **Depende de outra pessoa**: administrador (plugins), equipe da reoferta (categoria, Sala Padrão).
5. **Verificações manuais**: emissão do certificado, heurísticas de texto, nomes canônicos pendentes.
6. **Detalhamento técnico** recolhido: códigos, evidência original, chaves, inventário, motor, política, JSON.

A tabela atual continua útil como **visão técnica** (equipe e suporte), acessível por um controle "Visão técnica" ou
como conteúdo dos detalhes de cada ação. Alternativas comparadas: cartão por ocorrência (aumenta a altura 3–4×, Relatório
B §6), agrupamento por causa como estrutura principal (útil, mas metade das relações não é demonstrável hoje) e
checklist com marcação manual (cria estado que o plugin não verifica; descartado — quem marca "feito" é a reauditoria).

---

## 12. Acessibilidade e integração visual

| Item | Situação | Ação |
|---|---|---|
| Teclado | Controles nativos; tabela larga não focável (`scrollable-region-focusable`, grave) | Linhas empilhadas em tela estreita; `<details>` nativo |
| Leitor de tela | `th scope` presente; títulos h4→h3→h4…h6 (`heading-order`) | Hierarquia h2/h3/h4 a partir do título da página |
| Foco | Padrão do tema Boost | Manter; nenhum JS próprio necessário |
| Rótulos | Botões duplicados com o mesmo texto ("See correction proposal" ×2 para a mesma proposta) | Um por atividade+regra; rótulo com o nome da atividade para leitores de tela |
| Contraste | Sem violação no relatório (o painel tinha, B‑19, corrigido) | Manter `bg-* text-white/dark` |
| Responsividade | 20/22 tabelas com rolagem a 375 px | Lista com rótulo/valor em tela estreita |
| Ícones | Não há ícones sem texto | Se usar `pix_icon`, sempre com texto visível |
| Tema | Componentes Bootstrap do Boost (`alert`, `badge`, `table`) | Usar `notification`, `single_button`, `<details>`/collapse do Boost; nada de CSS próprio além de espaçamento |
| Strings | Plugin em `lang/en` e `lang/pt_br`; **evidências fora do sistema de strings**; rótulos de campos copiados à mão | Mensagens por verificação em `lang/`; rótulos de campo pelas strings do núcleo (`get_string('attemptsallowed', 'quiz')`, `get_string('hiddensectionsinvisible')`, `get_string('activitycompletion', 'completion')`), que acompanham a versão e o idioma do site |

---

## 13. Proposta de experiência (Entregável 3)

Convenção: **[existe]** já está no 0.6.1; **[novo‑A]** apresentação; **[novo‑C]** contrato aditivo; **[novo‑R]**
mudança de regra. As frases são propostas de redação a validar com a equipe do Cefor; nenhuma foi implementada.

### 13.1 Visão geral

```
Auditoria técnica · Sala de teste UX                         [Executar novamente] [existe]
Resultado de 30/10/2026 19:31 · a sala foi alterada depois desta auditoria [existe]
┌──────────────────────────────────────────────────────────────────────────────────────┐
│ Bloqueada: o certificado não pode ser emitido nesta sala.                [novo‑A]     │
│ 29 problemas em 9 atividades e nas configurações da sala → 24 ações.     [novo‑A]     │
│ 20 você pode fazer (1 com correção assistida) · 4 dependem de outra pessoa [novo‑A]    │
└──────────────────────────────────────────────────────────────────────────────────────┘
Comece por aqui                                                            [novo‑A]
 1. Questionário 1 exige "Aprofundamento", que não registra conclusão.  [Ver como corrigir]
    Faça isto antes de outras alterações no Questionário 1.
 2. O site não tem o plugin de certificado (Simple Certificate).        Com o administrador
 3. A autoinscrição está desativada: ninguém consegue se inscrever sozinho. [Editar autoinscrição]
Ver: (•) Por atividade ( ) Por prioridade ( ) Visão técnica                [novo‑A]
▸ Depende de outra pessoa (4)   ▸ Verificações manuais (2)   ▸ Detalhes técnicos da auditoria
```

### 13.2 Antes e depois — X‑DEP‑02

**Antes [existe]:**

> **X-DEP-02** Condition requires completion of a module without completion or hidden · Non conformant · Blocking ·
> [quiz "Questionário 1" (cmid=43)] · exige conclusão de page "Aprofundamento" (cmid=38) que NÃO tem acompanhamento de
> conclusão · Enable completion on the target module or remove the condition; otherwise it will never be satisfied.

**Depois [novo‑A, com `subject` lido da chave]:**

> **Questionário 1 nunca será liberado** · Impede a certificação
>
> **O que encontramos:** o Questionário 1 só é liberado depois que o estudante concluir "Aprofundamento", mas
> "Aprofundamento" não registra conclusão.
>
> **Por que importa:** a condição nunca é cumprida. Sem o Questionário 1, a Avaliação do curso e o certificado também
> ficam bloqueados.
>
> **Antes de tudo:** enquanto isso não for resolvido, o Moodle não salva o formulário do Questionário 1 (a condição
> aparece como "Escolher…" em Restringir acesso).
>
> **Como corrigir — escolha conforme o planejamento do curso:**
> - "Aprofundamento" deve ser lido antes do questionário → [Ativar a conclusão em "Aprofundamento"] (abre só a seção
>   Conclusão de atividade; para página, use "Visualizar"). Quem já leu precisará abrir a página de novo.
> - A leitura é opcional → [Remover a condição do Questionário 1] (abre só Restringir acesso; remova a condição
>   "Escolher…").
>
> **Quem pode fazer:** você (professor editor).
>
> **Como confirmar:** clique em Executar novamente; este item deve sumir, e o item da cadeia do certificado também.
>
> ▸ Detalhes técnicos: X‑DEP‑02 · `target_completion` · Questionário 1 (cmid 43) → Aprofundamento (cmid 38) ·
> evidência original · fonte: relatório de investigação §6.4

### 13.3 Antes e depois — X‑DEP‑04

**Antes [existe]:** `X-DEP-04 · Grafo de restrições · ciclo: 36 → 37 → 36 · Remove one of the conditions to break the
cycle.` (sem link)

**Depois [novo‑A]:**

> **"Ciclo A" e "Ciclo B" esperam uma pela outra** · Impede o acesso a 2 atividades (fora da cadeia do certificado)
>
> **O que encontramos:** 1. "Ciclo A" só é liberada depois de concluir "Ciclo B". 2. "Ciclo B" só é liberada depois de
> concluir "Ciclo A".
>
> **Por que importa:** nenhuma das duas pode ser aberta por estudantes.
>
> **Como corrigir:** decida qual vem primeiro e remova a condição da que deve abrir primeiro.
> - "Ciclo A" vem primeiro → [Restringir acesso de "Ciclo A"] e remova a condição sobre "Ciclo B".
> - "Ciclo B" vem primeiro → [Restringir acesso de "Ciclo B"] e remova a condição sobre "Ciclo A".
>
> Se não souber a sequência pretendida, confirme com quem planejou o curso antes de alterar.
>
> **Como confirmar:** Executar novamente; o ciclo deve sumir e nenhuma das duas deve aparecer como "nunca liberada".

### 13.4 Antes e depois — CL‑23 (sala sem o plugin)

**Antes [existe]:** duas linhas bloqueantes (`CL-23 · nenhum certificado de conclusão (simplecertificate) · Import the
certificate from the Standard Room` e `X-CERT-01 · sem certificado de conclusão · Static check; actual issuing requires
a test user`) e, nas regras conformes, `X-INT-03 Plugins used by the course installed on the site`.

**Depois [novo‑A com aviso calculado ao exibir; novo‑R para a verificação no X‑INT‑03]:**

> **Esta sala não tem certificado, e o site não tem o plugin de certificado** · Impede a certificação · Depende do
> administrador
>
> **O que encontramos:** a política exige um certificado do tipo Simple Certificate (`mod_simplecertificate`). O plugin
> não está instalado neste site, por isso a sala não tem certificado (a importação descarta atividades de plugins
> ausentes).
>
> **Como corrigir, em ordem:**
> 1. **Administrador do site:** instalar o plugin Simple Certificate.
> 2. **Equipe da reoferta:** importar o certificado da Sala Padrão (exige acesso à Sala Padrão).
> 3. **Professor editor:** em Restringir acesso do certificado, refazer as condições — concluir "Avaliação do curso" e
>    nota mínima 60 no Total do curso, com "todas" as condições. Na importação, as condições antigas apontam para
>    atividades da Sala Padrão e são perdidas.
>
> **Também resolve:** "Certificado sem Pesquisa de Perfil" (CL‑11) e "Certificado inalcançável" (X‑CERT‑01), que têm a
> mesma causa.
>
> **Como confirmar:** Executar novamente (estático). Para confirmar a emissão: ver *Verificações manuais*.

### 13.5 Validação depois da correção e achados relacionados

- **[existe]** Correção assistida com reauditoria gravada; "Resolvidos: N" no topo.
- **[novo‑A]** O resultado da correção em palavras: "Tentativas permitidas: 1 → 2. Verificado: o problema não aparece
  mais. Também resolvido: o enunciado agora confere com a configuração."
- **[novo‑A]** Depois de qualquer link para o Moodle, a linha mostra "Depois de salvar, volte aqui e clique em
  Executar novamente"; onde a rota aceita (`course/edit.php`, `enrol/editinstance.php`), o `returnurl` traz de volta.
- **[novo‑A]** Achados relacionados aparecem dentro da ação principal ("também resolve"), não como linhas soltas; a
  contagem do topo usa ações apenas quando as relações forem demonstráveis (seção 8).

---

## 14. Recomendações arquiteturais (Entregável 4)

### 14.1 Resolve só a apresentação (sem mudar estados, gravidades, ids ou chaves)

1. **Catálogo de mensagens por verificação**, não por regra: strings `rule_<id>_<check>_{problem,why,fix,verify}` em
   `lang/en` e `lang/pt_br`, com parâmetros nomeados. Uma classe de apresentação (por exemplo
   `output\finding_view`) monta a ocorrência apresentável a partir de `rule`, `check`, `subject`, `cmid`, `section`,
   `encontrado/esperado` e do `modinfo` atual. A regra continua igual; o teste de caracterização não muda.
2. **Renderizar ao exibir, não ao auditar**: a tela usa o JSON gravado só como fonte de fatos; texto e links saem do
   idioma e do site de quem vê. Para execuções antigas sem informação suficiente, mostrar o texto gravado (FR‑027).
3. **Rótulos de campo pelas strings do núcleo**, nunca copiados: acompanham a versão (CL‑20 no 5.3) e o idioma.
4. **Links nativos com `showonly` e `returnurl`**, calculados no `locator` a partir de `locus` + `check`, com verificação
   de existência e permissão.
5. **Precedências e relações declaradas**: uma tabela pequena e testada ("condição inválida na entidade X precede
   qualquer correção manual em X"; "CL‑06/07 precede CL‑08/CL‑14"; "certificate_presence agrupa CL‑11/CL‑23/X‑CERT‑01").
6. **Responsável calculado**: capacidade de quem vê para a tela de destino; categoria (`make_categories_list`); plugin
   exigido pela política ausente (`core_plugin_manager` ao exibir).

### 14.2 Exige evolução aditiva do contrato (1.0 → 1.1, campos opcionais)

- `check` e `subject` como campos (hoje calculados e apagados);
- `subjects`: entidades relacionadas (elos do X‑CERT‑01, nós do ciclo, faltantes do CL‑16);
- `params`: valores estruturados que hoje vão só no texto (`attempts`, `min`, `status`, `aggregation`);
- `evidence_level`: `proven` · `heuristic` · `static` · `name_match` — para separar comprovado de hipótese sem mudar o
  estado.

Nenhuma mudança de banco: o JSON já é gravado inteiro em `local_mooc_audit_run`.

### 14.3 Exige mudança de regra (muda resultados; caracterização regravada; decisão do Cefor)

| Mudança | Motivo | Efeito nos resultados |
|---|---|---|
| X‑INT‑03 verifica os plugins exigidos pela política (certificado, jogo, bloco de progresso) | U‑01 | Nova ocorrência quando o plugin falta |
| CL‑17 não diz "automática" para rótulos e fóruns institucionais; ou deixa esses casos para CL‑09/CL‑19 | U‑05 | Remove ocorrências duplicadas |
| CL‑18 verifica se o glossário é colaborativo antes de afirmar; não orienta remoção destrutiva | U‑13 | Pode mudar NC para Atenção |
| X‑DEP‑02 e X‑CERT‑01 tratam "alvo oculto" do mesmo modo | U‑11 | Muda um estado num dos dois |
| X‑CERT‑01 não emite "sem certificado" quando o CL‑23 já o faz; avalia ciclos e condições de nota na cadeia | caso D | Remove uma NC duplicada; pode criar outras |

**Não recomendado:** reescrever o motor, persistir ações ou causas em tabelas novas, IA generativa para explicar, ou
correção automática de configurações pedagógicas. Nada disso é necessário para os objetivos desta auditoria.

---

## 15. Plano SDD (Entregável 5)

**Decisão: ampliar a Spec 006**, que já trata do mesmo fluxo (relatório individual e correção) e está em rascunho sem
plano, em vez de criar uma spec nova. A revisão foi registrada em `specs/006-experiencia-relatorio/spec.md` (seção
"Revisão 2026-10-30"), com duas histórias novas (US7 e US8), FR‑031 a FR‑044, SC‑011 a SC‑015 e casos‑limite.

| Fase | Conteúdo | Muda resultados? | Testes | Risco de regressão |
|---|---|---|---|---|
| **1. Textos e links** (menor complexidade, maior impacto) | Mensagens por verificação (problema, por que importa, como corrigir, quem, como confirmar) para as 29 regras; nomes no lugar de ids; links nativos com `showonly`/`returnurl`, calculados ao exibir; avisos de precedência para a lista fechada; responsável e plugin ausente ao exibir; correção das orientações erradas (U‑04) | Não | Teste por regra e verificação: nenhuma string sem mensagem; snapshot de texto; caracterização A/B intacta; Behat da jornada X‑DEP‑02 | Baixo (só apresentação); cuidado com execuções antigas (FR‑027) |
| **2. Estrutura do relatório** | Resumo operacional, "Comece por aqui", visões por atividade/prioridade/técnica, verificações manuais, detalhes recolhidos, celular | Não | Behat + axe; medição de altura; 320 px | Médio (template); a visão técnica preserva a tabela atual |
| **3. Contrato 1.1** | `check`, `subject`, `subjects`, `params`, `evidence_level` opcionais; contagem de ações demonstráveis | Não (aditivo) | `contract_test` com 1.0 e 1.1; API (Spec 004) | Baixo; consumidores ignoram campos novos |
| **4. Correções de regra** | Lista da seção 14.3, uma a uma, cada uma com decisão registrada | **Sim** | Caracterização regravada e revisada; teste por regra | Médio; exige aprovação do Cefor |

Ordem de implementação: 1 → 2 → 3 → 4. A fase 1 sozinha atende ao critério de sucesso para 23 das 29 regras (seção 16,
pergunta 3). A linha de base com pessoas (FR‑029) deve ocorrer **antes** da fase 2, com as tarefas T6 e T7 acrescentadas.

---

## 16. Respostas finais

**1. O relatório atual é suficiente para orientar correções sem assistência?**
Não. É suficiente para **localizar** a maioria dos problemas de quem já conhece o Moodle e o plugin. Para o professor,
falta, em quase todas as regras, a consequência, o caminho exato, o responsável e a confirmação; em 6 regras a
orientação induz a erro ou não pode ser cumprida por quem a lê; e uma inconformidade (X‑DEP‑02) bloqueia a correção de
outras sem que o relatório avise.

**2. Quais regras são as mais difíceis de compreender?**
X‑DEP‑04 (só ids, sem link), X‑CERT‑01 (aviso no lugar de ação, causa em outra regra), X‑DEP‑02 (o alvo aparece no
Moodle como "Escolher…"), CL‑23 quando o plugin falta (orientação impossível), CL‑17 (contradiz CL‑09/CL‑19), CL‑21
(bloco de progresso tratado como HTML) e CL‑18 (afirma "colaborativo" e manda remover).

**3. Qual proporção das regras possui orientações efetivamente acionáveis?**
**4 de 29 (14%)** — CL‑08, CL‑09, CL‑13 e CL‑19 — e mesmo duas delas (CL‑09, CL‑19) entram em contradição com o CL‑17.
19 (66%) são parciais (há texto, falta objeto, valor, caminho ou responsável); 6 (21%) não são acionáveis. Com a fase 1
(só textos e links), a estimativa é chegar a 23 acionáveis; as 6 restantes dependem de outra pessoa (CL‑05, CL‑22,
CL‑23 sem plugin, X‑INT‑03) ou de mudança de regra (CL‑17, CL‑18).

**4. Quantos problemas são de texto e apresentação versus limitações do motor?**
Dos 22 achados desta auditoria: **15 só de apresentação e texto**, **3 de contrato aditivo** (o motor já calcula a
informação e a descarta ou a guarda só em texto) e **4 de regra** (U‑01, U‑05, U‑11, U‑13). Nenhum exige reescrever o
motor ou criar persistência nova.

**5. Quais são as cinco melhorias de maior impacto e menor complexidade?**
1. **Nomes no lugar de ids** e a atividade relacionada como sujeito (X‑DEP‑02/04, CL‑16, CL‑23, X‑CERT‑01, CL‑12),
   resolvidos ao exibir com `get_fast_modinfo()`.
2. **Links nativos com `showonly`** para as duas pontas de cada dependência e rotas diretas (inscrição, seção, questões),
   recalculados com existência e permissão.
3. **Mensagens por verificação** com "por que importa", "como corrigir" e "como confirmar", corrigindo as orientações
   erradas e contraditórias (CL‑11, CL‑16, CL‑17, CL‑21, CL‑18) e usando rótulos de campo do núcleo.
4. **Aviso de ordem** para a lista fechada de precedências, começando pelo comprovado: "resolva a restrição de acesso
   desta atividade antes de editar outras configurações dela".
5. **"Quem pode corrigir"** calculado (capacidade, categorias, plugin ausente), com a seção "Depende de outra pessoa".

**6. Qual seria a experiência mínima ideal para uma primeira versão pública?**
Um resumo de uma caixa (situação, problemas → ações, quantas dependem de outra pessoa); "Comece por aqui" com até três
bloqueantes na ordem certa; uma lista por atividade em que cada ação responde, em linguagem do Moodle e no idioma de
quem lê, *o que encontramos, por que importa, como corrigir (com alternativas quando a decisão é pedagógica), quem
pode, como confirmar*, com um link que abre exatamente a seção do formulário; uma seção "Verificações manuais" para o
que o plugin não comprova (emissão do certificado); e os detalhes técnicos atuais preservados, recolhidos, para a
equipe e o suporte. Sem cartões longos, sem painel decorativo, sem JavaScript próprio e sem alterar nenhuma regra na
primeira entrega.

---

## Ambientes e alterações deste estudo

- **dist53** (`moocaudit-dist53-web`, porta 8101, site descartável): criado o curso 4 (cópia do Curso B) e a conta de
  teste `prof.ux` (senha aleatória; acesso pelo "Acessar como"). No curso 4 foram aplicadas, como professora: a correção
  assistida do CL‑13 (tentativas 1 → 2) e a ativação da conclusão por visualização em "Aprofundamento". O Curso B
  original (curso 3) não foi alterado. Foram gravadas 3 execuções no histórico do curso 4.
- **Laboratório principal** (porta 8091): só auditorias por linha de comando sem `--record`; nada gravado.
- Código do plugin: **nenhuma alteração**.
