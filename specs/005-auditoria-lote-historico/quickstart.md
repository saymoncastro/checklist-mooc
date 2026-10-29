# Quickstart: validar a Spec 005 (histórico, lote e acompanhamento)

Ambiente isolado: `moocaudit-s5-web` (http://localhost:8095, admin / `Admin123!`), receita em `moodle-env/README.md`.
Cursos: A = 2 (sem inconformidades), B = 3 (com bloqueantes); 100 salas de medição na categoria "Lote de medição".

## 1. Automatizado

```bash
docker exec -w /var/www/html moocaudit-s5-web vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-s5-web \
  vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags=@local_mooc_audit
docker exec -w /var/www/html moocaudit-s5-web /tmp/cs/vendor/bin/phpcs --standard=moodle local/mooc_audit
```

## 2. Roteiro de homologação (manual, ~15 min)

1. **Primeira e segunda auditoria** — entrar como professor editor da sala B, *Mais > Auditoria técnica*: o quadro
   "Desde a auditoria anterior" diz "Primeira auditoria registrada". *Executar novamente*: todas as ocorrências como
   **Persistentes**, nenhuma nova ou resolvida.
2. **Resolução** — corrigir as tentativas de "Questionário 1" (CL-13) pela correção assistida ou pela edição da
   atividade; reabrir a auditoria: **Resolvidas: 1**. *Ver a evolução*: a correção aparece em "Correções assistidas
   registradas entre as duas auditorias", com o aviso de que a relação não é verificada.
3. **Nova inconformidade** — ocultar uma página e reauditar: **Novas: 1** (X-STR-01).
4. **Mudança de política** — em *Plugins locais > Auditoria MOOC*, colocar `{"min_quiz_attempts": 3}` e reauditar:
   ocorrências do CL-13 que surgem ou somem ficam **Não comparáveis** ("os valores da política lidos por esta regra
   mudaram"); nenhuma "nova" por causa da política. Restaurar a política.
5. **Histórico** — *Histórico de auditorias*: lista com data, origem, solicitante, estado, resultado, contagens por
   gravidade, evolução, política (versão + impressão digital) e versão do plugin; abrir uma execução antiga: "resultado
   guardado, não recalculado"; escolher outra base em "Comparar com".
6. **Lote** — como gerente, *Administração do site > Cursos > Acompanhamento das auditorias* > *Nova auditoria em lote*,
   categoria "Lote de medição": *Pré-visualizar* (100 salas) e *Enfileirar*. Rodar o cron
   (`php admin/cli/cron.php` ou `php admin/cli/adhoc_task.php --execute`); a página do lote mostra o andamento.
   Remover uma sala antes do cron: só ela falha ("Sala indisponível"); *Reexecutar auditorias com falha* não a
   reenfileira.
7. **Painel** — contadores (salas, auditadas, com bloqueantes, requer correções, incompleta, não auditada, sem
   inconformidades detectadas, desatualizada, na fila); filtros por categoria, texto, situação, gravidade e período; a
   palavra "aprovado" e percentuais não aparecem; o aviso de escopo das 29 regras está no topo.
8. **Permissões** — o professor editor da sala B não vê o painel nem o histórico de outra sala (trocar `courseid` ou
   `runid` na URL → acesso negado / registro inválido).
9. **Somente leitura** — nenhuma auditoria, lote ou abertura do histórico altera a sala (data de modificação e
   `cacherev` iguais; teste automatizado `readonly_test`).

## 3. Medição

```bash
docker exec -w /var/www/html moocaudit-s5-web php local/mooc_audit/cli/batch.php --category=3 --run
docker exec -w /var/www/html moocaudit-s5-web php local/mooc_audit/cli/batch.php --courses=104 --run
```

Resultados e consultas usadas em [results.md](results.md).
