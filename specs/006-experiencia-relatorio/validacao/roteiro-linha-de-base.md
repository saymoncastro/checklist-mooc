# Roteiro do teste de linha de base (spec 006, FR‑029)

**Objetivo:** medir como professores editores e a equipe técnica do Cefor usam **hoje** o relatório de auditoria e a
correção assistida, antes de qualquer mudança da spec 006. A mesma sessão, com o mesmo roteiro, será repetida com a nova
interface (FR‑030), e os resultados serão comparados nos critérios SC‑001 a SC‑005.

**Versão avaliada:** plugin 0.5.2 (etapas 1 e 2 do plano de estabilização), a versão que vai para a homologação. Isso
inclui a reauditoria gravada depois da correção (A‑01), então a tarefa T4 mede a interface, não esse defeito já
corrigido.

**Não é avaliação de pessoas.** Os resultados descrevem a ferramenta; nenhum dado identifica participantes.

---

## 1. Participantes

| Perfil | Quantidade | Critério de inclusão | Papel na sessão |
|---|---|---|---|
| Professor editor / proponente | 5 | Edita salas MOOC no Moodle do Cefor; pelo menos 2 com pouca experiência em configuração do Moodle | Professor editor nas duas salas |
| Equipe técnica do Cefor | 3 | Prepara ou confere salas para reoferta | Gerente nas duas salas |

- **Recrutamento:** **[validar]** quem convida e em que período.
- **Ética e consentimento:** **[validar]** com o Cefor/Ifes se a atividade exige parecer de comitê de ética ou só
  termo de consentimento interno. No mínimo, use um termo que informe:
  - a gravação de tela e de áudio;
  - o uso só para avaliar a ferramenta;
  - a guarda sem nomes;
  - o direito de parar a qualquer momento.
- **Identificação:** cada pessoa recebe um código (`P01`…`P08`). A planilha e as gravações usam só o código. A
  correspondência entre código e nome fica com o moderador, fora do repositório.

## 2. Preparação (antes de cada sessão)

1. **Laboratório.** Use um site Moodle **local** de laboratório (por exemplo, o `moocaudit-rv-web` da receita em
   `moodle-env/README.md`) com o plugin 0.5.2. **Nunca** a produção.
2. **Salas novas para o participante:**
   ```bash
   docker cp specs/006-experiencia-relatorio/validacao/preparar_sessao.php moocaudit-rv-web:/tmp/
   docker exec -w /var/www/html moocaudit-rv-web php /tmp/preparar_sessao.php --participante=P01 --perfil=professor --senha='<senha escolhida>'
   ```
   O script imprime as duas URLs e o `cmid` do "Questionário 1". Anote na **folha do moderador**, nunca na planilha.
   Cada sessão recebe salas novas, porque a tarefa T4 altera o Curso B.
3. **Navegador:** janela anônima, 1366 × 768 ou maior, zoom 100%. Deixe o participante entrar com o usuário `lb_p01`
   e a senha. Comece na **página inicial do Moodle**, não no relatório.
4. **Gravação:** tela e áudio (com consentimento). Cronômetro ou registro de tempo da gravação.
5. **Material:** cartões de tarefa (`cartoes-tarefas.md`) impressos ou numa segunda tela; planilhas
   `registro-tarefas.csv` e `registro-questionario.csv`.

### O que as salas contêm (gabarito do moderador)

**Curso B (cópia):** situação **Bloqueada**; 25 não conformidades (5 bloqueantes, 9 altas, 9 médias, 2 baixas) e 5
alertas.

| Bloqueante | Atividade |
|---|---|
| X‑DEP‑02 — exige concluir "Aprofundamento", que não tem conclusão ativada | Questionário 1 |
| X‑DEP‑04 — ciclo de dependências | páginas "Ciclo A" e "Ciclo B" |
| CL‑23 — certificado sem a nota mínima de 60 no total | Certificado digital de conclusão |
| X‑CERT‑01 — certificado possivelmente inalcançável | Certificado digital de conclusão |
| CL‑18 — glossário vazio usado como fonte do jogo | Glossário de termos |

O "Questionário 1" aparece em cinco linhas:
- X‑DEP‑02 bloqueante;
- CL‑13 × 2: 1 tentativa, quando o exigido é pelo menos 2; e o enunciado diz 2 tentativas;
- CL‑14: sem data de encerramento;
- X‑DEP‑02 alerta: exige uma página oculta.

A autoinscrição (CL‑08) está desativada e sem datas.

**Curso A — com alertas:** situação **Requer correções**.

| Situação | Item |
|---|---|
| Não conforme | CL‑12: a soma das notas é 110 |
| Não conforme | CL‑16: a Avaliação do curso não exige a "Lição complementar" |
| Alerta | CL‑12: total do curso |
| Alerta | CL‑20: seção "Topic 3" |
| Alerta | X‑STR‑01: página "Aprofundamento" oculta |
| **Não verificável** | CL‑14: "Lição complementar", tipo sem campo de encerramento conhecido |

## 3. Roteiro da sessão (35–45 min)

### Abertura (5 min) — fala sugerida

> "Obrigado por participar. Estamos avaliando uma ferramenta do Moodle que confere a configuração das salas MOOC.
> **Quem está sendo testado é a ferramenta, não você**: não há resposta errada, e qualquer dificuldade é uma
> informação útil para nós. Vou pedir algumas tarefas. Enquanto faz, tente **pensar em voz alta**, dizendo o que
> procura, o que espera encontrar e o que acha confuso. Eu não vou ajudar durante as tarefas, para não influenciar, mas
> pode perguntar no fim. Podemos parar a qualquer momento. Posso começar a gravar?"

Confirme o consentimento e inicie a gravação. Pergunte, e anote na folha do moderador, sem nome:
- há quanto tempo usa o Moodle como editor;
- se já preparou uma sala para reoferta;
- se já usou a auditoria técnica.

### Regras do moderador durante as tarefas

- Leia o cartão em voz alta, entregue-o e diga **"pode começar"**. O tempo começa aí.
- O tempo termina quando a pessoa **diz a resposta** ou **declara que terminou** (ou desistiu).
- Se a pessoa ficar em silêncio mais de 20 s, diga só "o que você está pensando agora?".
- **Ajuda:** só se a pessoa pedir ou ficar parada 2 minutos. Use **somente** a dica padronizada do cartão. Cada dica
  conta 1 em `ajudas`.
- **Limite:** 5 min por tarefa (T4: 8 min). Esgotado o tempo, registre `conclusao = N` e passe para a próxima.
- Depois de cada tarefa, faça a pergunta SEQ (seção 4) e anote.

### Tarefas

| # | Sala | Início | Sucesso (S) | Parcial (P) | Dica padronizada |
|---|---|---|---|---|---|
| **T1** Primeiro bloqueante | Curso B | Página inicial do Moodle | Aponta **qualquer um** dos 5 bloqueantes e a atividade | Aponta um problema não bloqueante como "o primeiro a resolver" | "A ferramenta fica no menu da sala." |
| **T1b** Situação geral (pergunta) | Curso B | Fim da T1 | Descreve a sala como impedida ou bloqueada, com problemas sérios | — | Sem dica. Registre a resposta literal. Se a pessoa disser "quase tudo certo" ou "28 de 29", marque o código **INT‑CONTAGEM** |
| **T2** Explicar um problema do Questionário 1 | Curso B | Relatório aberto | Escolhe um dos problemas do Questionário 1 e explica corretamente, com as próprias palavras, **o que está errado** e **o que fazer** (ex.: "permite 1 tentativa, precisa de pelo menos 2; mudar em Tentativas permitidas"). Anote qual problema escolheu e se percebeu que há outros | Explica o que está errado, mas não o que fazer, ou o contrário | "Procure pelo nome da atividade." |
| **T3** Localizar a configuração da autoinscrição | Curso B | Relatório aberto | Chega à tela de edição do método "Autoinscrição" | Chega à lista de métodos de inscrição, mas não abre a autoinscrição | "O relatório tem links para as configurações." |
| **T4** Corrigir as tentativas e confirmar | Curso B | Relatório aberto | Aplica a correção do CL‑13 **e** afirma, olhando o relatório, que o problema foi resolvido | Aplica, mas não consegue confirmar, ou corrige pela tela de edição sem confirmar | "Procure uma forma de corrigir pela própria ferramenta." |
| **T5** Interpretar o "não verificável" | Curso A — com alertas | Relatório aberto | Entende que a ferramenta **não conseguiu verificar** (não é falha) e que é preciso **conferir à mão** a data de encerramento da lição | Entende que não é falha, mas não sabe o que fazer | "Leia a seção de itens não verificados." |

Na T5, o moderador pode indicar a sala: "Agora abra a sala 'Curso A — com alertas'."

### Encerramento (5 min)

Aplique o questionário final (seção 4) e então as perguntas abertas:
1. "O que foi mais difícil de entender?"
2. "Teve algum termo que você não conhecia?" Anote os termos literais.
3. "Se pudesse mudar uma coisa nessa tela, o que seria?"

Agradeça e encerre a gravação.

## 4. Instrumentos

**SEQ (após cada tarefa):** "No geral, essa tarefa foi…" — 1 = muito difícil … 7 = muito fácil.

**UMUX‑Lite (fim da sessão):** concordância de 1 a 7 (1 = discordo totalmente, 7 = concordo totalmente):
- **U1:** "As funcionalidades desta ferramenta atendem ao que eu preciso."
- **U2:** "Esta ferramenta é fácil de usar."

**Códigos de observação:** registre na coluna `codigos` da planilha, separados por `;`.

| Código | Quando usar |
|---|---|
| INT‑CONTAGEM | Interpreta as contagens como sala quase conforme |
| INT‑TECNICO | Não entende uma evidência por causa de termo ou valor técnico; anote qual em `observacoes` |
| INT‑NV | Trata "não verificável" como falha da sala |
| INT‑ALERTA | Não sabe se um alerta exige ação |
| ROL‑INVENT | Lê o inventário ou se detém nele procurando o problema |
| FRAG‑ATIV | Não percebe que o mesmo problema ou atividade aparece em outro ponto do relatório |
| BOTAO‑DUP | Hesita entre os dois botões "Ver proposta" do CL‑13 |
| PERDIDO | Sai do relatório sem querer ou não sabe voltar |
| CODIGO‑CORR | Fica confuso com códigos na prévia ou no resultado da correção (`NAO_CONFORME`, `attempts`, capacidades) |

## 5. Registro

- `registro-tarefas.csv`: **uma linha por participante e tarefa** (T1, T1b, T2, T3, T4, T5).
  - `interface`: `atual` (linha de base) ou `nova` (FR‑030).
  - `tempo_s`: segundos inteiros.
  - `conclusao`: `S`, `P` ou `N`.
  - `ajudas`: número de dicas dadas.
  - `navegacao_desnecessaria`: páginas abertas fora do caminho esperado, contadas na gravação.
  - `seq`: 1–7.
  - `codigos`: códigos da seção 4.
  - `observacoes`: texto livre, **sem nomes**.
- `registro-questionario.csv`: uma linha por participante, com os dados de experiência, U1, U2 e as respostas abertas
  resumidas.
- Planilha e gravações ficam **fora** do repositório público (**[validar]** onde guardar). Só o resumo agregado entra em
  `specs/006-experiencia-relatorio/validacao/resultados-linha-de-base.md`.

## 6. Análise (o que levar para a spec)

| Critério | Cálculo na linha de base | Uso |
|---|---|---|
| SC‑001 | Mediana de `tempo_s` da T1 (todos os participantes) | Referência a superar; meta < 30 s |
| SC‑002 | Professores com T2 = `S` e `ajudas` = 0 | Meta ≥ 4 de 5 na nova interface |
| SC‑003 | Participantes com INT‑CONTAGEM | Meta 0 na nova interface |
| SC‑004 | Participantes com T4 = `S` e `ajudas` = 0 | Meta: todos |
| SC‑005 | Mediana de `seq` por tarefa | Meta ≥ 6 e sem piora por tarefa |

Verifique também as hipóteses da spec. Se a linha de base contrariar alguma delas, revise a spec antes do plano
(FR‑029).

| Hipótese | Evidência na linha de base |
|---|---|
| B‑01 (topo) | ROL‑INVENT e tempo da T1 |
| B‑02 (fragmentação) | FRAG‑ATIV, T2 parcial |
| B‑03 (linguagem) | INT‑TECNICO e termos citados nas perguntas abertas |
| B‑04 (contagens) | INT‑CONTAGEM |
| B‑05 (alertas) | INT‑ALERTA |
| B‑06 (botões) | BOTAO‑DUP |
| B‑12/B‑13 (correção) | CODIGO‑CORR |
| **Visão inicial ([validar])** | Se professores procuram os problemas pela atividade (relatam ou fazem isso na T2/T3), considere a visão "por atividade" como inicial para esse perfil |

Com 8 participantes, os números orientam decisões de projeto, mas não têm valor estatístico. Registre também os
comportamentos observados e as falas literais relevantes.

## 7. Fora deste roteiro

As tarefas T6–T10 do Relatório B (comparação, histórico, painel e lote) pertencem ao segundo incremento de UX. Se a
equipe quiser aproveitar as mesmas sessões para registrar a linha de base delas, será preciso preparar também:
- um par origem/destino (`cli/make_scenarios.php`);
- uma sala com várias execuções no histórico;
- um lote com falha parcial.

Esse preparo não está coberto por este material.
