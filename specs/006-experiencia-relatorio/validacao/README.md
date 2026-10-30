# Validação com usuários da spec 006

Material da linha de base (FR‑029), a ser reaplicado com a nova interface (FR‑030). Nenhuma sessão foi realizada
ainda: os resultados abaixo são preenchidos pela equipe do Cefor depois das sessões.

| Arquivo | Para quê |
|---|---|
| `roteiro-linha-de-base.md` | Guia do moderador: participantes, preparo, falas, tarefas com critérios de sucesso, dicas padronizadas, instrumentos, códigos de observação e análise ligada a SC‑001 a SC‑005 |
| `cartoes-tarefas.md` | Texto das tarefas mostrado ao participante |
| `preparar_sessao.php` | Script **de laboratório** que cria, para cada participante, uma cópia do Curso B e a sala com alertas, o usuário de teste e uma auditoria registrada em cada sala |
| `registro-tarefas.csv` | Planilha modelo: uma linha por participante e tarefa |
| `registro-questionario.csv` | Planilha modelo: uma linha por participante (experiência, UMUX‑Lite, respostas abertas) |
| `resultados-linha-de-base.md` | Resumo agregado, sem identificação, a preencher depois das sessões |

**Antes da primeira sessão:**
1. Resolver os pontos **[validar]** do roteiro: recrutamento, consentimento ou ética, e guarda das gravações.
2. Fazer uma **sessão piloto** com alguém de fora da amostra, para ajustar tempos e redação dos cartões.

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
