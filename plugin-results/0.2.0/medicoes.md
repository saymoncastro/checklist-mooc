# Medições da entrega 001 (plugin 0.2.0)

Executado em 2026-10-29 no site do worktree (`moocaudit-wt-web`, Moodle 4.5.15, PHP 8.3, PostgreSQL 17), política
padrão `2027-decisoes-2026-10-28`, ano de reoferta 2027. Cursos 55 e 670 restaurados dos `.mbz` originais
(`backup-moodle2-course-55-empp-20261028-1455-nu.mbz`, `backup-moodle2-course-670-acessibilidetecnologias_2026-20261028-1501-nu.mbz`).

## SC-007 — mesmos estados por regra que a PoC

Comparação de `estado|gravidade` por regra entre `plugin-results/<curso>.json` (PoC, 2026-10-28) e
`plugin-results/0.2.0/<curso>.json`:

| Curso | Situação geral | Por estado | Regras com diferença |
|---|---|---|---|
| A (sintético) | Sem inconformidades | 48 C | 0 de 29 |
| B (sintético) | Bloqueada | 28 C, 25 NC, 5 A | 0 de 29 |
| 55 (restaurado) | Bloqueada | 46 C, 16 NC | 0 de 29 |
| 670 (restaurado) | Requer correções | 48 C, 16 NC, 6 A | 0 de 29 |

## SC-003 — inconformidades e alertas com link de correção

| Curso | Com link / total (NC + ATENCAO) |
|---|---|
| A | 0 / 0 |
| B | 28 / 30 |
| 55 | 16 / 16 |
| 670 | 22 / 22 |
| **Total** | **66 / 68 = 97,1 %** (meta ≥ 90 %) |

Os dois sem link não têm destino no Moodle: a síntese de conclusão de CL-17 (ATENCAO) e o ciclo de X-DEP-04 (NC),
que envolve dois módulos.

## SC-002 — tempo para sala com até 150 módulos

Sala sintética com 151 módulos (páginas, rótulos, questionários, fóruns, restrições encadeadas), criada por
`make150.php` (script de medição, fora do plugin): 230 NC, 63 ATENCAO, 47 C.

| Medida | Valor |
|---|---|
| `timing.total_s` do motor (3 execuções CLI) | 0,123 s · 0,092 s · 0,091 s |
| Página completa no navegador (`loadEventEnd`), 293 linhas e 291 links | 1,31 s (primeiro byte em 0,64 s) |

Meta: < 5 s. O site de teste roda com `debugdeveloper` e `perfdebug` ligados.

## SC-006, SC-008 e qualidade

- PHPUnit: 71 testes, 1635 asserções, todos verdes (`plugin-results/phpunit-0.2.0.txt`); 29 arquivos de teste por regra.
- `readonly_test`: 17 tabelas do curso B idênticas antes e depois de três auditorias; `timemodified`/`cacherev` iguais.
- Behat: 2 cenários, 29 passos, Chrome via Selenium, inclusive verificação de acessibilidade axe (`plugin-results/behat-0.2.0.txt`).
- PHPCS `moodlehq/moodle-cs`, padrão `moodle`: 0 erros, 0 avisos; `php -l` sem erros em todos os arquivos.
