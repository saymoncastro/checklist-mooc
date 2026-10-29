# Quickstart: validar a Spec 004 (API externa)

Ambiente isolado novo: `moocaudit-s4-web` (http://localhost:8096, admin / `Admin123!`), mesma receita do ambiente s5
em `moodle-env/README.md` (rede `moocaudit_s4`, banco próprio, `make_fixtures.php`: A = 2 sem inconformidades,
B = 3 com bloqueantes). A receita entra no README na fase de implementação (tarefa T002).

## 1. Automatizado

```bash
docker exec -w /var/www/html moocaudit-s4-web vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
docker exec -w /var/www/html moocaudit-s4-web vendor/bin/phpunit --filter 'local_mooc_audit\\external'
docker exec -w /var/www/html moocaudit-s4-web /tmp/cs/vendor/bin/phpcs --standard=moodle local/mooc_audit
```

Esperado: suíte completa verde (Specs 001–005 + 004); testes de `tests/external/` cobrindo a matriz de FR-031.

## 2. Roteiro de homologação (manual, ~20 min)

1. **Instalação** — *Administração > Servidor > Web services > Serviços externos*: "Auditor MOOC" existe,
   **desabilitado**, "somente usuários autorizados", capacidade exigida `local/mooc_audit:api`. *Definir permissões*:
   nenhum papel tem `local/mooc_audit:api`.
2. **Configuração (seguindo só o `API.md`)** — habilitar Web Services e REST; criar o usuário `integracao`; criar o
   papel de sistema "Integração Auditor MOOC" com as capacidades do guia e atribuí-lo; habilitar o serviço e autorizar
   `integracao`; gerar o token. Cronometrar (SC-007: ≤ 30 min).
3. **Última auditoria (US1)** — sala B nunca auditada:
   ```bash
   curl -s "$WWW/webservice/rest/server.php" -d wstoken=$TOKEN -d moodlewsrestformat=json \
     -d wsfunction=local_mooc_audit_get_latest_audit -d courseid=3
   ```
   `found: false`. Auditar B pela página (*Executar auditoria*) e repetir: `found: true`, `stale_reasons: []`, `report`
   igual ao de `index.php?courseid=3&format=json` (sem o bloco `run`). Editar uma atividade de B e repetir:
   `stale_reasons: ["course_changed"]`, mesma data.
4. **Pedir e acompanhar (US2)** — `wsfunction=local_mooc_audit_request_audit&courseid=3`: resposta imediata,
   `status: queued`, `reused: false`. Repetir na hora: mesmo `runid`, `reused: true`. Rodar a fila
   (`php admin/cli/adhoc_task.php --execute`); `get_run&runid=<id>&includereport=0`: `status: complete`, `origin: api`,
   `evolution.counts` presente. No histórico da sala B, a execução aparece com origem "API".
5. **Execução por id (US3)** — `get_run&runid=<id>&includeevolutionitems=1`: itens iguais aos da página
   *Histórico > execução*. `get_run&runid=999999` e o id de uma execução de uma sala fora do escopo: mesma resposta
   `runnotfound`.
6. **Acesso (US4)** — restringir `integracao` à categoria da sala A (papel de sistema só com `api`; `audit`,
   `course:view` e `viewhiddencourses` na categoria): pedir/consultar B → `nopermissions`/`requireloginerror`;
   `get_run` de execução de B → `runnotfound`. Com um professor editor de B com token de um serviço personalizado que
   inclua as funções: `nopermissions` em todas.
7. **Limite (US2.6)** — configurar `api_max_pending = 1`; pedir A (fila), pedir B → `queuefull`; pedir A de novo →
   `reused: true`.
8. **Independência** — desabilitar o serviço: página, histórico, painel, lote e CLI funcionam como antes.

## 3. Medição

Registrar em `specs/004-api-externa/results.md`: tempo de `request_audit` (sala de 151 módulos), de `get_run` e
`get_latest_audit` com relatório (média de 20 chamadas), tamanho da resposta, e custo de `includeevolutionitems=1`.
