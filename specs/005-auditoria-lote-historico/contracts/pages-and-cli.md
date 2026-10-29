# Contratos: páginas, tarefas e CLI (Spec 005)

Todas as páginas exigem `require_login`; ações que escrevem exigem `require_sesskey()` e POST.

## `index.php?courseid=N[&format=json]` (alterada)

- Inalterado: acesso (`local/mooc_audit:audit` no curso), relatório HTML/JSON (contrato 1.0).
- Novo: a execução é registrada (`origin = page`). O JSON ganha, em cada resultado, `occurrence` (aditivo) e no topo
  `run` = `{"id", "status", "previousrunid", "evolution"}` (aditivo). O HTML mostra o quadro "Desde a auditoria
  anterior" (contagens por classe, link para a evolução) e o link "Histórico de auditorias".

## `history.php?courseid=N` — histórico da sala

- Acesso: `local/mooc_audit:audit` no contexto do curso. Curso removido: acesso pelo painel com
  `viewdashboard` + o histórico guardado (sem link para auditar).
- Lista paginada (20) das execuções: data/hora, origem, solicitante, estado, resultado geral, contagens de não
  conformidades por gravidade, evolução resumida, política (versão e 8 primeiros caracteres da impressão digital),
  ano de reoferta, versão do plugin.

## `history.php?courseid=N&runid=R[&baseid=B]` — execução e evolução

- O par (`runid`, `courseid`) é conferido; outro curso → `invalidrecord`.
- Mostra cabeçalho histórico ("Registro de DD/MM/AAAA HH:MM — não recalculado"), erros/limitações, a evolução em
  relação a `baseid` (padrão: `previousrunid` da execução) agrupada por classe, as correções da trilha da Spec 002 no
  intervalo (com aviso), e o relatório guardado renderizado pelo mesmo renderer da auditoria.
- `format=json`: `{"run": {...metadados...}, "evolution": {...itens...}, "report": {...}}`.

## `dashboard.php` — painel de acompanhamento

- Acesso: `local/mooc_audit:viewdashboard` (sistema). Entrada em Administração do site > Cursos.
- Parâmetros GET (filtros): `categoryid` (0 = todas), `subcats` (0/1, padrão 1), `q` (texto em nome/sigla),
  `situation` (`not_audited|incomplete|blocking|needs_fix|none_detected|stale|pending`), `severity`
  (`Bloqueante|Alta|Média|Baixa|Informativa`: salas cuja última execução tem não conformidade dessa gravidade),
  `from`/`to` (data da última auditoria, `AAAA-MM-DD`), `page`, `perpage` (padrão 50).
- Escopo: cursos (exceto o do site) em que o usuário tem `local/mooc_audit:audit`.
- Saída: contadores (selecionadas, auditadas, não auditadas, com bloqueantes, requer correções, sem inconformidades
  detectadas, auditoria incompleta, desatualizadas, na fila), evolução somada (resolvidas, novas, persistentes),
  tabela por sala, lotes recentes, aviso de escopo.
- Com `batchaudit`: caixas de seleção e botão "Auditar selecionadas" (POST para `batch.php`) e formulário de novo lote.

## `batch.php` — lotes

- `batch.php` (GET): formulário de novo lote (`batchaudit`): modo `category` (+ subcategorias), `ids` (lista separada
  por vírgula/espaço), `startyear`; botão "Pré-visualizar" mostra salas aceitas e recusadas (motivo); "Confirmar"
  cria o lote.
- `batch.php` (POST `action=create`, `sesskey`): cria o lote a partir de `mode` + parâmetros ou `courseids[]`
  (seleção do painel); redireciona para `batch.php?id=B`.
- `batch.php?id=B` (`viewdashboard`): critérios, solicitante, data, recusadas, andamento (na fila, em execução,
  concluídas, parciais, falhas) e tabela por sala (somente salas em que o usuário tem `audit`; as demais contadas como
  "fora da sua permissão").
- `batch.php` (POST `action=retry`, `id=B`, `sesskey`, `batchaudit`): cria novas execuções (no mesmo lote) para as
  salas cuja execução mais recente no lote falhou.

## Tarefas

- `\local_mooc_audit\task\audit_course` (ad hoc): `customdata = {"runid": int}`, `userid` = solicitante.
  Concorrência padrão = configuração `batch_concurrency`.
- `\local_mooc_audit\task\cleanup_history` (agendada, diária 03:17): retenção, lotes vazios, políticas órfãs,
  execuções presas.

## CLI

- `cli/audit.php --courseid=N [--record]` (existente + opção): `--record` grava a execução (`origin = cli`,
  `userid = 0`).
- `cli/batch.php --courses=1,2,3 | --category=C [--subcats] | --startyear=AAAA [--name=...] [--run]`: cria o lote como
  o administrador (`userid` = admin principal) e, com `--run`, executa as tarefas do lote em série no próprio
  processo, imprimindo tempo e memória por sala (usado na medição de desempenho).

## Retrocompatibilidade

- Contrato do relatório continua `1.0`; `occurrence` e `run` são aditivos (o schema já permite propriedades extras).
- `comparison` 1.0 (Spec 003) inalterado.
- Trilha `local_mooc_audit_fix` inalterada (somente lida pela evolução).
