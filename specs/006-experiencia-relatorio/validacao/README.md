# Validação com usuários da spec 006

Material da linha de base (FR‑029), a ser reaplicado com a nova interface (FR‑030). Nenhuma sessão foi realizada
ainda: os resultados abaixo são preenchidos pela equipe do Cefor depois das sessões.

| Arquivo | Para quê |
|---|---|
| `roteiro-linha-de-base.md` | Guia do moderador: participantes, preparo, falas, tarefas com critérios de sucesso, dicas padronizadas, instrumentos, códigos de observação e análise ligada a SC‑001 a SC‑005 |
| `cartoes-tarefas.md` | Texto das tarefas mostrado ao participante |
| `preparar_sessao.php` | Script **de laboratório** que cria, para cada participante, uma cópia do Curso B e a sala com alertas, o usuário de teste e uma auditoria registrada em cada sala; não altera salas existentes e recusa rodar fora do ambiente de referência |
| `registro-tarefas.csv` | Planilha modelo: uma linha por participante e tarefa |
| `registro-questionario.csv` | Planilha modelo: uma linha por participante (experiência, UMUX‑Lite, respostas abertas) |
| `resultados-linha-de-base.md` | Resumo agregado, sem identificação, a preencher depois das sessões |

## Fase 0 — o que fazer, em ordem

A fase 0 da Spec 006 é esta linha de base. Nenhuma mudança perceptível da interface pode chegar ao site de sessão antes
dela.

| # | Quem | O quê | Evidência a registrar |
|---|---|---|---|
| 1 | Cefor | Decidir os pontos **[validar]**: quem recruta e quando; termo de consentimento ou comitê de ética; onde ficam as gravações; sessão presencial no computador do laboratório ou remota com controle de tela | Decisões por escrito (fora do repositório se tiverem nomes) |
| 2 | Cefor | Recrutar **5 professores editores** (2 com pouca experiência em configuração) e **3 da equipe técnica**, e mais **1 pessoa de fora da amostra** para o piloto | Lista de códigos P01–P08 e do piloto; correspondência com nomes só com o moderador |
| 3 | Técnico | Subir ou conferir o site `moocaudit-f0-web` (receita em `moodle-env/README.md`) e preencher a **ficha do ambiente** do roteiro | Ficha em `resultados-linha-de-base.md` |
| 4 | Técnico + moderador | **Sessão piloto** com a pessoa de fora da amostra: preparar as salas, aquecer o site, aplicar T1–T7 e os questionários | Tempos por tarefa, cartões ou dicas que confundiram; ajustes feitos no roteiro (commit próprio, antes da 1ª sessão) |
| 5 | Técnico | Para cada sessão: `preparar_sessao.php` com o código e o perfil, conferir a contagem impressa, aquecer o site | Linha impressa pelo script na folha do moderador |
| 6 | Moderador | Conduzir a sessão pelo roteiro (35–45 min; T1–T7, SEQ, UMUX‑Lite, perguntas abertas) | Gravação; uma linha por tarefa em `registro-tarefas.csv`; uma linha em `registro-questionario.csv` |
| 7 | Cefor | Consolidar em `resultados-linha-de-base.md` e decidir se alguma hipótese da Spec 006 precisa ser revista (FR‑029) | Resultados agregados, sem nomes |

Depois do passo 7: `/speckit.plan` e `/speckit.tasks` da Spec 006, incorporando os resultados.

**Depende de pessoas, não do desenvolvedor:** recrutamento, consentimento, moderação, as sessões, a análise e a revisão
da redação das mensagens. **Depende de um técnico:** o Docker do computador que hospeda o site (passos 3 e 5); o site só
responde em `localhost`, por isso a sessão acontece nesse computador.

## Piloto técnico (2026-10-30, sem participantes)

Ensaio feito pelo agente com contas fictícias P97 (professor) e P98 (equipe técnica), **sem pessoas**: valida o
ambiente e os instrumentos, não a interface. Site `moocaudit-f0-web`, Moodle 4.5.15 (Build: 20261005), Auditor 0.6.1,
`mod_simplecertificate` 4.0.01, `mod_game` 2026-10-08, `block_completion_progress` 2026101200.

| Verificação | Resultado |
|---|---|
| Execução integral do `preparar_sessao.php` (dois participantes, ~1 s cada) | Curso B: Bloqueada, 25 não conformes (5 bloqueantes, 9 altas, 9 médias, 2 baixas), 5 alertas; sala com alertas: 2 não conformes, 3 alertas, 1 não verificável — **igual ao gabarito**, nas duas execuções |
| Gabarito por ocorrência | Os 5 bloqueantes da T1, as 5 linhas do "Questionário 1" (T2), a autoinscrição desativada e sem datas (T3), as duas causas da T7 (cadeia por "Aprofundamento" sem conclusão e "Leitura obrigatória" oculta; certificado sem nota mínima) e a sala com alertas (T5) conferem |
| Cursos preexistentes | Impressão digital (curso, módulos, instâncias, seções, inscrições, papéis, notas, blocos) dos cursos 1, 2 e 3 **idêntica** antes e depois |
| Permissões | Professor: auditar, ver e aplicar correções nas duas salas dele, sem comparar. Técnico: o mesmo e comparar. Nenhum dos dois acessa a sala do outro (vai para a página de inscrição) |
| Login e Painel | As contas entram com a senha definida; o Painel mostra só as duas salas do participante; sem rodapé de depuração |
| T3 | O link do CL‑08 leva à lista de métodos; a edição da autoinscrição está a um clique (critério "Parcial" × "Sucesso" observável) |
| T4 | Correção do CL‑13 aplicada e confirmada; "Voltar ao relatório" mostra o problema resolvido |
| T6 | Com o editor de restrições carregado, o formulário do "Questionário 1" é **recusado** ("Por favor, acione" na condição "Escolher…"): INT‑ORDEM observável no 4.5 como no 5.3. Ativar a conclusão em "Aprofundamento" e "Executar novamente" resolve o X‑DEP‑02 ("Resolvidas: 1") |

**Ajustes feitos no roteiro a partir do piloto:** começar no **Painel**, porque a "Página inicial" lista as salas de
todos os participantes com o mesmo nome e a primeira era de outra pessoa; **aquecer o site** antes da sessão, porque no
primeiro acesso o editor de restrições demora a aparecer e um salvamento rápido passou sem validação; site de sessão com
**depuração escondida** nas páginas (no laboratório comum o participante veria tempo e memória no rodapé).

**Antes da primeira sessão** continua faltando o que depende de pessoas: passos 1, 2 e 4 da tabela acima.

**Teste do script (2026-10-30, laboratório rv, plugin 0.5.2):** participantes fictícios P98 e P99.
- Cópia do Curso B: Bloqueada, 25 não conformidades e 5 alertas, igual ao cenário de referência.
- Sala com alertas: Requer correções, 2 não conformidades, 3 alertas e 1 não verificável.
- Permissões conferidas: professor audita e corrige; perfil técnico (gerente da sala) também compara.
- A primeira versão do script movia as salas para uma categoria própria. Isso acrescentava uma não conformidade do CL‑05
  e mudava o cenário, então as salas ficam na categoria de referência.

**Revisão de 2026-10-30 (auditoria de capacidade de correção):** o roteiro passou a fixar um ambiente de referência
(Auditor 0.6.1, Moodle 4.5, `mod_simplecertificate`, `mod_game` e `block_completion_progress`), com ficha do ambiente
antes da sessão piloto; `preparar_sessao.php` recusa rodar sem esses plugins, porque o gerador os omite em silêncio e o
gabarito mudaria; foram acrescentadas as tarefas T6 e T7 e os códigos INT‑ORDEM e INT‑RESP.
