# Contrato: página `/local/mooc_audit/correction.php`

## GET — prévia (nunca grava)

Parâmetros: `courseid` (int, obrigatório), `rule` (texto, id da regra), `cmid` (int).

Acesso: `require_login($course)`; `require_capability('local/mooc_audit:preparecorrection', contexto do curso)`.
`rule` sem estratégia → erro "regra sem correção assistida". `cmid` que não pertence ao curso → erro.

Saída: página no contexto do curso (`incourse`) com: título da atividade, regra (id, título, fonte), problema,
local (link para a edição), valor atual, valor proposto, justificativa, impacto previsto (resolvidas/introduzidas),
riscos, dependências, impedimentos, papéis/capacidades autorizados e:

- se `executable` e o usuário pode executar: formulário `POST` com `sesskey`, `confirm=1`, `fingerprint`, botão
  "Confirmar correção" e link "Cancelar" (volta ao relatório);
- caso contrário: explicação do motivo e link "Abrir a configuração da atividade" (tela oficial) + "Voltar ao
  relatório".

Sem inconformidade atual para (regra, cmid): mensagem "Não há inconformidade a corrigir" + link para o relatório.

## POST — confirmação

Parâmetros adicionais: `sesskey`, `confirm=1`, `fingerprint` (40–64 caracteres hexadecimais).

Verificações, em ordem (qualquer falha termina sem escrita):

1. `require_sesskey()`; método POST (`data_submitted()`); `confirm = 1`.
2. `require_capability('local/mooc_audit:applycorrection', curso)`.
3. Atividade existe e pertence ao curso; `moodle/course:manageactivities` no contexto da atividade.
4. Proposta reconstruída do banco; inexistente → "nada a corrigir".
5. Impressão digital igual à enviada; diferente → "o curso mudou desde a prévia".
6. `executable` verdadeiro; senão → "correção não pode ser executada" + impedimentos.
7. Trava `fix_<cmid>` obtida em até 5 s; senão → "outra correção em andamento".

Resultado (template `correction_result`): situação (`applied` com reauditoria `resolved` / `not_resolved` /
`introduced` / `error`, `failed`, `conflict`, `refused`, `nothing`), valor anterior e novo, resultado da regra alvo
antes/depois, inconformidades resolvidas e introduzidas, link para o relatório. Mensagem de sucesso apenas para
`applied` + `resolved`.

## Ação no relatório

Para cada resultado `NAO_CONFORME`/`ATENCAO` com `cmid` das regras CL-13, CL-14, CL-17, quando o usuário tem
`preparecorrection`: link "Ver proposta de correção" (GET). O JSON do relatório (contrato 1.0) não muda.
