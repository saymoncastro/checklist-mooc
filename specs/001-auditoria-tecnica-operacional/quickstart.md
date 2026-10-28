# Quickstart — validar a entrega 001

Pré-requisitos: ambiente `moodle-env/` em execução (`moocaudit-webserver-1`), plugin montado em
`/var/www/html/local/mooc_audit`, PHPUnit inicializado (`admin/tool/phpunit/cli/init.php`).

## 1. Instalar/atualizar o plugin

```bash
./moodle-local/mphp.sh admin/cli/upgrade.php --non-interactive
```

Esperado: versão `2026102900` (release 0.2.0) instalada; nenhuma tabela criada.

## 2. Suíte automatizada (portão)

```bash
docker exec -i -w /var/www/html moocaudit-webserver-1 vendor/bin/phpunit local/mooc_audit/tests
```

Esperado: todos os testes verdes, incluindo `characterization_test` (A: 0 não conformes; B: estados por regra iguais
a `tests/fixtures/expected_B.json`), `readonly_test`, `access_test`, `policy_test`, `locator_test` e os 29
`tests/rule/*_test.php`. Testes que dependem de `mod_game`/`mod_simplecertificate` são pulados se o módulo não
estiver instalado (no laboratório estão).

## 3. Lint e padrão de código

```bash
for f in $(find plugin/local_mooc_audit -name '*.php'); do docker exec -i moocaudit-webserver-1 php -l < "$f"; done
```

PHPCS padrão `moodle` quando disponível no contêiner (`vendor/bin/phpcs --standard=moodle local/mooc_audit`).

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
# Behat (opcional)
docker exec -i -w /var/www/html moocaudit-webserver-1 vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags @local_mooc_audit
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
