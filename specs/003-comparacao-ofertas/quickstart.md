# Quickstart: validar a comparação entre ofertas

## Pré-requisitos

Ambiente isolado da Spec 003 (receita em `moodle-env/README.md`, seção "Ambiente da spec 003"): contêineres
`moocaudit-s3-web` (porta 8094, banco `moodle_s3`, admin / `Admin123!`, apenas local) e `moocaudit-s3-selenium`,
montando `plugin/local_mooc_audit` deste worktree. Cursos A = 2 e B = 3 criados por `cli/make_fixtures.php`.

```bash
docker exec -w /var/www/html moocaudit-s3-web php admin/cli/upgrade.php --non-interactive
```

## 1. Testes automatizados

```bash
docker exec -w /var/www/html moocaudit-s3-web vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
docker exec -w /var/www/html moocaudit-s3-web php admin/tool/behat/cli/run.php --tags=@local_mooc_audit
```

Esperado: todos verdes (suítes das Specs 001, 002 e 003).

## 2. Cenários experimentais A–G no laboratório

```bash
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/make_scenarios.php --year=2027
```

O script (somente laboratório) cria as salas de cada cenário e imprime os pares `origem → destino`. Para cada par:

```bash
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/compare.php --source=<O> --target=<D>
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/compare.php --source=<O> --target=<D> --json
```

| Cenário | Conferir |
|---|---|
| A importação correta | 0 ausentes; fórum "Avisos" em ambiguidade `duplicado_no_destino`; ids/sigla/datas só em esperadas; nenhuma pendência de oferta |
| B avisos duplicado | ambiguidade com 2 candidatos; nenhum par para "Avisos"; `duplicates_in_target = 1` |
| C glossário | glossário modificado `content_lost` entradas 3 → 0 com o jogo em `dependents`; jogo com `glossary_entries_available` 3 → 0; CL-18 `novo_no_destino` |
| D avaliação alterada | questionário modificado: tentativas, nota, conclusão como relevantes; CL-13 `novo_no_destino` |
| E arquivos | `file_record_missing`, `file_content_missing`, `file_unavailable` em três recursos distintos |
| F nomes repetidos | "Aula 1"/"Aula 1" (uma com Libras) casadas por conteúdo; par idêntico em ambiguidade `conteudo_equivalente` |
| G oferta temporal | ajustada: sem pendência; não ajustada: CL-06/CL-07/CL-08 `pendencia_oferta`, nenhum `persistente` de ciclo |

## 3. Interface

1. Entrar como admin em http://localhost:8094 → Administração do site → Cursos → **Comparar ofertas**.
2. Escolher origem e destino pela pesquisa, marcar a confirmação, **Comparar ofertas**.
3. Conferir as seções do [contrato da página](contracts/compare-page.md), o link JSON e, numa ambiguidade, escolher
   um candidato e "Comparar novamente com estas correspondências" (par com confiança "manual").
4. Abrir a auditoria de uma sala (Mais → Auditoria técnica) e seguir "Comparar com a oferta anterior".
5. Como professor editor das duas salas: a página nega o acesso.

## 4. Desempenho

```bash
docker exec -w /var/www/html moocaudit-s3-web php /tmp/make151.php        # duas salas de 151 módulos (script de medição)
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/compare.php --source=<X> --target=<Y> --json | grep -A7 '"timing"'
```

Registrar `timing` e `memory_peak_mb` de três execuções em `plugin-results/0.4.0/medicoes.md` (meta < 10 s).
