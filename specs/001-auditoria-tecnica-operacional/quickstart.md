# Quickstart — validar a entrega 001

Pré-requisitos: ambiente `moodle-env/` em execução (`moocaudit-webserver-1`), plugin montado em
`/var/www/html/local/mooc_audit`, PHPUnit inicializado (`admin/tool/phpunit/cli/init.php`).

## 1. Instalar/atualizar o plugin

```bash
./moodle-local/mphp.sh admin/cli/upgrade.php --non-interactive
```

Esperado: versão `2026102901` (release 0.2.0) instalada; nenhuma tabela criada.

## 2. Suíte automatizada (portão)

```bash
docker exec -i -w /var/www/html moocaudit-webserver-1 vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
```

Esperado: todos os testes verdes, incluindo `characterization_test` (A: 0 não conformes; B: estados por regra iguais
a `tests/fixtures/expected_B.json`), `readonly_test`, `access_test`, `policy_test`, `locator_test` e os 29
`tests/local/rule/*_test.php`. Testes que dependem de `mod_game`/`mod_simplecertificate` são pulados se o módulo não
estiver instalado (no laboratório estão).

## 3. Lint e padrão de código

```bash
for f in $(find plugin/local_mooc_audit -name '*.php'); do docker exec -i moocaudit-webserver-1 php -l < "$f"; done
```

PHPCS padrão `moodle` (`moodlehq/moodle-cs`, instalado à parte, por exemplo em `/tmp/cs` com Composer):
`/tmp/cs/vendor/bin/phpcs --standard=moodle local/mooc_audit` → nenhum erro nem aviso.

## 4. Cursos sintéticos e CLI

```bash
./moodle-local/mphp.sh local/mooc_audit/cli/make_fixtures.php --year=2027      # recria A e B (ambiente local)
./moodle-local/mphp.sh local/mooc_audit/cli/audit.php --courseid=<A>           # esperado: SEM_INCONFORMIDADES
./moodle-local/mphp.sh local/mooc_audit/cli/audit.php --courseid=<B> --json | head -40
```

Esperado no JSON: `contract_version: "1.0"`, `summary.overall_status: "BLOQUEADA"` para B, cada resultado com
`category`, `locus`, `link`.

## 5. Página e navegação (manual ou Behat)

1. Entrar em http://localhost:8091 como `admin`.
2. Abrir o curso B → menu "Mais" → **Auditoria técnica**. Esperado: relatório com situação "Bloqueada", 5 bloqueantes
   primeiro, agrupados por categoria; cada inconformidade com local (link), evidência completa, esperado e
   orientação; seções Alertas, Não foi possível verificar, Erro na verificação (vazia), Conformes, Avisos.
3. Clicar no local de "CL-13 Questionário 1" → abre a edição do questionário. Corrigir tentativas para 2, voltar,
   **Executar novamente** → CL-13 deixa de aparecer; contagens atualizadas.
4. Criar um usuário estudante inscrito em B, entrar como ele: menu "Mais" **não** mostra Auditoria técnica; acessar
   a URL direta → "Você não tem permissão".
5. Administração do site → Plugins → Plugins locais → Auditoria MOOC: salvar `{` (inválido) → erro de validação;
   salvar `{"required_forum_name": "Fórum geral"}` → auditar B: CL-09 passa a "ausente" e o relatório mostra
   política `…+admin`.

```bash
# Behat (exige um contêiner Selenium alcançável como "selenium" e o site como "webserver"; ver moodle-env/README.md)
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html <contêiner web> php admin/tool/behat/cli/init.php
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html <contêiner web> php admin/tool/behat/cli/run.php --tags=@local_mooc_audit
```

## 6. Cursos institucionais restaurados (validação complementar, não versionada)

```bash
./moodle-local/mphp.sh local/mooc_audit/cli/audit.php --courseid=2 > /tmp/55.txt   # comparar com plugin-results/curso55_restaurado.txt
./moodle-local/mphp.sh local/mooc_audit/cli/audit.php --courseid=3 > /tmp/670.txt
```

Esperado: mesmos estados por regra que os registrados em `plugin-results/` (apenas a data de execução muda).

## 7. Critérios de aceite cobertos

US1 (passos 5.2), US2 (5.3), US3 (5.4, 5.5, testes `policy_test`/`engine_test`), US4 (4), US5 (5.5), SC-006/008 (2),
SC-007 (2 e 6), SC-009 (grep por strings fixas: `grep -rn "'[A-ZÁ-Ú][a-záéíóú]* " plugin/local_mooc_audit/classes/output plugin/local_mooc_audit/index.php` deve retornar vazio).

## Execução registrada — 2026-10-29, plugin 0.2.0 (versão 2026102901)

Ambiente: contêineres do worktree (`moocaudit-wt` para PHPUnit, `moocaudit-wt-web` na porta 8092 com banco
`moodle_wt`, `moocaudit-wt-selenium` para Behat), e não o laboratório `moocaudit-webserver-1`, que monta a cópia
principal e continua na versão da PoC. Receitas em `moodle-env/README.md`.

| Passo | Resultado |
|---|---|
| 1. Instalação | `install_database.php` com o plugin 0.2.0; nenhuma tabela própria |
| 2. Suíte | OK (75 testes, 1720 asserções, após a revisão de código) — `plugin-results/phpunit-0.2.0.txt` |
| 3. Lint e PHPCS | `php -l` sem erros; PHPCS `moodle` 0 erros, 0 avisos |
| 4. Sintéticos e CLI | A: `SEM_INCONFORMIDADES`; B: `BLOQUEADA`; JSON com `contract_version` 1.0, `category`, `locus`, `link` |
| 5.1–5.3 Página, links, reexecução | Verificados no navegador (curso B) e no Behat; links abrem a edição do módulo |
| 5.4 Estudante e professor | Sem a entrada no menu (Behat, `access_test`) |
| 5.5 Política | JSON inválido recusado ao salvar; nome canônico alterado aplicado a CL-09 (`policy_test`) |
| Behat | 2 cenários, 29 passos, verificação de acessibilidade axe — `plugin-results/behat-0.2.0.txt` |
| 6. Restaurados | 55 e 670 restaurados dos `.mbz` no site do worktree: estados por regra idênticos à PoC |
| SC-002 / SC-003 | 151 módulos: motor 0,09–0,12 s, página 1,3 s; links em 97,1 % das NC/ATENCAO — `plugin-results/0.2.0/medicoes.md` |
| SC-009 | `grep` por strings fixas em `classes/output` e `index.php`: vazio |

Desvios: o passo 5.3 não corrigiu o questionário no curso B (a correção manual foi exercitada apenas abrindo o link);
o laboratório principal não foi atualizado para 0.2.0 para não alterar o ambiente de referência.

### Revisão de código — 2026-10-29

Oito achados corrigidos antes do PR: X-CERT-01 deixou de depender de quem abre a página (disponibilidade por usuário
só com `--userid`); a política passou a validar o tipo de cada chave, nulos inclusive, e a exigir objeto JSON no
setting, no carregamento e no `--policy`; nomes de curso e categoria escapados uma única vez; conclusão e restrições
desabilitadas no site afetam só as regras que as leem (`REQUIRES_COMPLETION`/`REQUIRES_AVAILABILITY`); o texto do CLI
mantém valores "0"; os testes por regra reutilizam os construtores do gerador; o schema da spec é um link simbólico
para o do plugin. Estados por regra de A, B, 55 e 670 continuam idênticos aos registrados.
