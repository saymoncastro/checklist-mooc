# Contrato — página e navegação

## Entrada na navegação do curso

- Callback `local_mooc_audit_extend_navigation_course()` em `lib.php`.
- Nó "Auditoria técnica" (string `auditmenu`) adicionado à navegação do curso quando o usuário tem
  `local/mooc_audit:audit` no contexto do curso; ausente caso contrário.
- Destino: `/local/mooc_audit/index.php?courseid=<id>`.

## Página `/local/mooc_audit/index.php`

| Parâmetro | Tipo | Comportamento |
|---|---|---|
| `courseid` (ou `id`) | int, obrigatório | curso a auditar; ausente → formulário simples pedindo o id (compatibilidade); inexistente → erro padrão `invalidcourseid` |
| `format` | `html` (padrão) ou `json` | `json` devolve `application/json; charset=utf-8` com o contrato 1.0 |

Comportamento:

1. `require_login($course)`; `require_capability('local/mooc_audit:audit', context_course)`; sem permissão → exceção
   padrão `nopermissions` (nenhuma auditoria executada).
2. Contexto e layout do curso (`incourse`), título "Auditoria técnica — <curso>".
3. Executa a auditoria na visão administrativa (`viewer_userid = 0`).
4. Renderiza, nesta ordem: cabeçalho (curso, execução, ano de reoferta, política, versão do auditor, botão
   **Executar novamente**, link **JSON**); inventário resumido; situação geral com contagens por estado e por
   gravidade; **Inconformidades** agrupadas por gravidade (Bloqueante → Informativa) e, dentro, por categoria, cada
   item com: regra (id + título), local (com link quando houver), evidência completa, encontrado, esperado,
   orientação; **Alertas** (ATENCAO) no mesmo formato; **Não verificados ou não aplicáveis** (subtítulo "Não foi possível verificar" para
   NAO_VERIFICAVEL e "Não se aplica a esta sala" para NAO_APLICAVEL, ambos com motivo); **Erro na verificação** (ERRO, com mensagem); **Conformes** (lista compacta de
   regras); **Avisos** (coleta e política).
5. "Executar novamente" recarrega a mesma URL (nova execução; operação de leitura).

Acessibilidade: cabeçalhos de seção (`h3`/`h4`), tabelas com `<th>`, links com texto do local, estados com texto (não
só cor), tudo pelo tema padrão do Moodle.

Todas as strings vêm de `lang/{en,pt_br}/local_mooc_audit.php`.
