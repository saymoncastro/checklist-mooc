# Desenvolvimento do Auditor MOOC

Para quem vai ler, testar ou modificar o plugin. Instalação e uso estão em [../instalacao.md](../instalacao.md) e
[../guia-do-usuario.md](../guia-do-usuario.md).

## Onde está o quê

| Caminho | Conteúdo | Vai para o ZIP? |
|---|---|---|
| `plugin/local_mooc_audit/` | código do plugin (tipo `local`, componente `local_mooc_audit`, pasta de instalação `local/mooc_audit`) | sim, como `mooc_audit/` (menos `cli/make_*.php`) |
| `plugin/local_mooc_audit/tests/` | PHPUnit (244 testes), Behat (13 cenários), gerador dos cursos A/B, expectativas de caracterização | sim (convenção Moodle) |
| `tools/package-plugin.sh` | gera e confere o ZIP a partir de um commit | não |
| `.github/workflows/moodle-plugin-ci.yml` | CI com `moodle-plugin-ci` sobre o ZIP gerado (Moodle 4.5, PHP 8.3, PostgreSQL) | não |
| `moodle-env/` | receitas Docker dos laboratórios (4.5, 5.3, ambientes por spec) | não |
| `specs/`, `.specify/`, `.claude/skills/` | Spec-Driven Development: especificações 001–006, constituição, comandos do Spec Kit | não |
| `docs/` | instalação, guia, este documento, [arquitetura do plugin](arquitetura-plugin.md), catálogo de regras, revisões, prontidão, histórico | não |
| `plugin-results/` | evidências por versão (saídas de auditoria, medições, capturas) | não |
| `poc/` | protótipo Python que lê `.mbz` e originou as regras (oráculo histórico) | não |

A arquitetura (motor, uma classe por regra, catálogo, política, retrato da sala, correção, comparação, histórico, API),
as fontes de dados e a justificativa de cada acesso ao banco estão em [arquitetura-plugin.md](arquitetura-plugin.md).

## Gerar o pacote

```bash
tools/package-plugin.sh [ref] [pasta]     # padrão: HEAD, dist/
```

Usa `git archive` sobre `plugin/local_mooc_audit` do commit indicado com prefixo `mooc_audit/` e data dos arquivos =
data do commit, então o mesmo commit gera o mesmo SHA-256. Exclui os scripts de laboratório (`cli/make_fixtures.php`,
`make_import.php`, `make_scenarios.php`) e confere: raiz única, arquivos obrigatórios, ausência de `.git`, `vendor`,
`.mbz`, `.xlsx` e `version.php` com o componente certo. Arquivos não commitados nunca entram.

Para publicar uma versão: atualizar `version.php` (`version`, `release`, `maturity`), `engine::VERSION` (um teste exige
que seja igual a `release`) e `CHANGES.md`; commitar; criar a tag `vX.Y.Z`; gerar o ZIP a partir da tag. Ainda não há
releases nem tags publicadas.

## Testar

Os testes rodam num Moodle de laboratório (ver [moodle-env/README.md](../../moodle-env/README.md) para montar um com
Docker, com o plugin do seu checkout montado em `local/mooc_audit`):

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
php admin/tool/behat/cli/init.php && vendor/bin/behat --config <behatdata>/behatrun/behat/behat.yml --tags=@local_mooc_audit
vendor/bin/phpcs --standard=moodle local/mooc_audit          # moodlehq/moodle-cs
```

Os testes dos cursos A/B usam `mod_simplecertificate`, `mod_game` e `block_completion_progress` quando instalados (a
CI os instala); sem eles, os cenários correspondentes são pulados pelo gerador. Os scripts `cli/make_fixtures.php`,
`make_import.php` e `make_scenarios.php` criam cursos de teste num site de desenvolvimento (só rodam com host local e
depuração de desenvolvedor; `make_fixtures` exige o autoloader do Composer do Moodle).

A CI (`moodle-plugin-ci`) roda phplint, phpcs, phpdoc, validate, savepoints, mustache, PHPUnit e Behat em Moodle 4.5 /
PHP 8.3 / PostgreSQL sobre o conteúdo do ZIP e guarda o ZIP como artefato. O Moodle 5.3 e o MySQL/MariaDB ainda não estão
na matriz.

## Processo

Funcionalidades seguem o Spec Kit: `/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` →
`/speckit-analyze` → implementação, sob a [constituição](../../.specify/memory/constitution.md) (10 princípios:
independência, plugin padrão, auditoria somente leitura, correção só assistida, regras rastreáveis, seis estados, teste
por regra, segurança, simplicidade, entrega incremental). Cada spec vive em `specs/NNN-nome/` com `results.md` e as
evidências em `plugin-results/<versão>/`. Decisões institucionais: [DECISOES-INSTITUCIONAIS-CHECKLIST.md](../../DECISOES-INSTITUCIONAIS-CHECKLIST.md).
Backlog: [backlog.md](../backlog.md).

## Linha do tempo do projeto

- **2026-10-28:** investigação de viabilidade sobre backups `.mbz` e sobre Web Services ([historico/](../historico/));
  prova de conceito do plugin no Moodle 4.5 e **decisão arquitetural vigente: plugin local com motor interno**,
  independente do MOOC Admin e de `.mbz`; adoção do Spec Kit e constituição v1.0.0.
- **2026-10-29:** spec 001 (0.2.0: 29 regras em classes próprias, relatório no curso, contrato JSON 1.0), spec 002
  (0.3.0: correção assistida), spec 003 (0.4.0: comparação entre ofertas), spec 005 (0.5.0: histórico, lote, painel);
  revisão geral independente ([revisao-geral/](../revisao-geral/)).
- **2026-10-30:** estabilização (0.5.1, 0.5.2), compatibilidade com Moodle 5.3 (0.5.3), spec 004 (0.6.0: API),
  especificação 006 (experiência do relatório, ainda sem plano) e auditoria de prontidão para distribuição
  ([prontidao-distribuicao/](../prontidao-distribuicao/README.md)).
