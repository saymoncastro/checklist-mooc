# Resultados: API Externa (Spec 004) — plugin 0.6.0

Ambiente: `moocaudit-s4-web` (http://localhost:8098, banco `moodle_s4`, rede `moocaudit_s4`; receita em
`moodle-env/README.md`, seção "Ambiente da spec 004"). Moodle 4.5.15 (Build 20261005), PHP 8.3.35, PostgreSQL 17.11,
PHPUnit 9.6.34. Base: `main` em de682b2 (plugin 0.5.3). Evidências brutas em `plugin-results/0.6.0/`.

## Testes automatizados (T002, T012, T032, T039)

| Momento | Resultado |
|---|---|
| Linha de base (0.5.3, antes da 004) | **OK (225 testes, 3457 asserções)** |
| Depois da 004 (0.6.0) | **OK (243 testes, 3631 asserções)** — 18 testes novos ou estendidos (`plugin-results/0.6.0/phpunit.txt`) |
| Recorte da 004 (external, api, latest, run_store, tarefa, somente leitura, saída, privacidade, acesso ao histórico) após o PHPCS | OK (48 testes, 543 asserções) |
| Moodle 5.3+ (Build 20261030, PHPUnit 11.5.56; contêiner temporário `moocaudit-s4-53`, banco `moodle_s4m53`) | **OK (243 testes, 3631 asserções)**; 74 depreciações do PHPUnit, todas de metadados em doc-comment (`@covers`), como as 65 da 0.5.3 (+9 dos arquivos novos) (`plugin-results/0.6.0/phpunit-moodle53.txt`) |
| Behat `@local_mooc_audit` (Chrome, com axe) no 4.5 | **13 cenários / 213 passos verdes** — inclui a página da auditoria (`index.php`) e o histórico (`history.php`), que passaram a usar `latest` e `evolution::base_for/between` (`plugin-results/0.6.0/behat.txt`) |
| PHPCS `moodle` (`moodlehq/moodle-cs`) em `local/mooc_audit` | 0 erros, 0 avisos |
| `php -l` nos arquivos alterados | sem erros |

A suíte inteira roda com o serviço "Auditor MOOC" desabilitado e nenhum papel com `local/mooc_audit:api` (padrão da
instalação de testes, conferido por `tests/external/access_test.php`): SC-008 atendido (T032). As refatorações de
`index.php` (`latest::for_course`) e `history.php` (`evolution::base_for/between`) passam nos testes existentes de
histórico, painel, acesso e Behat-equivalentes de saída (T012).

Cobertura nova (FR-031): `tests/external/{get_latest_audit,request_audit,get_run,access,contract}_test.php`,
`tests/local/api/{service,run_view}_test.php`, `tests/local/history/{latest,run_store}_test.php`, e extensões de
`tests/task/audit_course_test.php` (permissão por origem), `tests/readonly_test.php` (pedido pela API + tarefa não
alteram a sala), `tests/output_test.php` (rótulo "API" no histórico) e `tests/privacy/provider_test.php` (execução
`api` exportada e anonimizada).

## Atualização 0.5.3 → 0.6.0 no site s4

`admin/cli/upgrade.php` criou a configuração `local_mooc_audit/api_max_pending`, a capacidade sem papel e o serviço
`local_mooc_audit` com `enabled = 0`, `restrictedusers = 1`, `requiredcapability = local/mooc_audit:api`.

## Chamadas REST reais (T041, parte automatizável do roteiro)

Conta `integracao` com o papel de sistema do `API.md`, serviço habilitado e conta autorizada
(`plugin-results/0.6.0/s4_api_setup.php`). Respostas observadas:

| Passo | Resposta |
|---|---|
| `get_latest_audit` sala B nunca auditada | `{"api_version":"1.0","found":false}` |
| `request_audit` sala B | `runid 1`, `status queued`, `reused false` |
| `request_audit` sala B de novo | mesmo `runid 1`, `reused true` |
| `get_run` antes do cron | `status queued`, `origin api`, `timestarted 0`, sem `report` |
| `get_run 999999` | `errorcode runnotfound` |
| `request_audit` curso do site | `errorcode invalidcourseid` |
| `get_latest_audit courseid=abc` | `errorcode invalidparameter` |
| token inválido | `errorcode invalidtoken` |
| cron (`adhoc_task.php --execute`), depois `get_run` | `status complete`, `overall BLOQUEADA`, `attempts 1`, `reofferyear 2027` |
| segunda auditoria, `get_run includeevolutionitems=1` | 30 itens de evolução |

## Medições (T040; SC-001, SC-002)

Sala `MEDE151` (152 módulos, `plugin-results/0.4.0/make151.php`), REST por `curl` do host, duas rodadas
(`plugin-results/0.6.0/medicoes-rest.txt`):

| Chamada | Tempo (s) | Resposta |
|---|---|---|
| Linha de base do ambiente: token inválido (nenhum código do plugin roda) | 0,65 | 136 B |
| `request_audit` | 0,71–0,72 | 102 B |
| `get_run` com relatório | 0,62–0,70 | 49 kB (relatório 41 kB) |
| `get_run includereport=0` | 0,68–0,70 | 0,8 kB |
| `get_run includereport=0 includeevolutionitems=1` (42 itens) | 0,66–0,90 | 24 kB |
| `get_latest_audit` com relatório | 0,70–0,79 | 49 kB |
| `get_latest_audit includereport=0` | 0,64–0,71 | 0,9 kB |

Quase todo o tempo é o custo fixo de uma requisição neste ambiente (Docker/Colima, sem cache de opcode ajustado): a
própria auditoria da sala de 152 módulos levou 0,13–0,15 s na tarefa. SC-001 (pedido ≤ 1 s) e SC-002 (consultas com
relatório ≤ 2 s) atendidos com folga; medir de novo na homologação institucional.

## Pendências

- **T038 / D5**: registrar a decisão 13 em `DECISOES-INSTITUCIONAIS-CHECKLIST.md` depois que o responsável aprovar
  D1–D3 (capacidade própria sem arquétipo, serviço desabilitado e restrito, limite de 100 pendentes por conta). A
  implementação segue as recomendações; mudar qualquer uma é simples (capacidade, `db/services.php`, configuração).
- **T041 / SC-007**: o roteiro manual do `quickstart.md` §2 por uma pessoa que não participou (configuração pela
  interface só com o `API.md`, cronometrada) ainda não foi feito; as chamadas acima cobrem o comportamento.
- Metadados de teste em doc-comment (`@covers`): migrar para atributos antes do PHPUnit 12 (pendência herdada da 0.5.3).
