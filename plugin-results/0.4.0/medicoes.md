# Medições da entrega 003 (plugin 0.4.0)

Executado em 2026-10-29 no site isolado da Spec 003 (`moocaudit-s3-web`, Moodle 4.5.15, PHP 8.3, PostgreSQL 17,
Docker via Colima 4 CPU / 6 GB, macOS arm64), política padrão `2027-decisoes-2026-10-28`, reoferta 2027. O site roda
com `debugdeveloper` ligado.

## SC-005 — duas salas de 151 módulos

Sala sintética criada por [`make151.php`](make151.php) (script de medição, fora do plugin): 151 módulos (páginas com
restrição de conclusão encadeada, rótulos, questionários com 3 questões, URLs, fóruns, recursos com arquivo) + o fórum
"Avisos" nativo = 152 módulos (curso 18, oferta 2026). Importada pela importação nativa (backup/restore em modo
importação, 3,69 s) para uma sala nova ajustada para 2027 (curso 19, 153 módulos: o "Avisos" duplicado).

| Medida | Execução 1 | Execução 2 | Execução 3 |
|---|---|---|---|
| Retrato da origem (`snapshot_source_s`) | 0,109 s | 0,099 s | 0,086 s |
| Retrato do destino (`snapshot_target_s`) | 0,056 s | 0,047 s | 0,033 s |
| Regras nas duas salas (`rules_s`) | 0,023 s | 0,021 s | 0,022 s |
| Correspondência + diferenças + relação (`compare_s`) | 0,104 s | 0,104 s | 0,101 s |
| **Total (`total_s`)** | **0,292 s** | **0,272 s** | **0,241 s** |
| Pico de memória do PHP (`memory_peak_mb`) | 30 MB | 30 MB | 30 MB |

Resultado: 151 preservadas (confiança alta), 1 ambiguidade (fórum "Avisos" duplicado), 0 ausentes, 0 modificadas.

Página completa no navegador (`compare.php?source=18&target=19&confirm=1`): primeiro byte 0,96 s, `loadEventEnd`
0,99 s, 25 KB transferidos, 151 itens preservados listados.

**Comparação com a auditoria individual** (mesmas salas, CLI): 0,102–0,107 s por sala (`total_s`). A comparação custa
≈ 2,5× uma auditoria: dois retratos e duas execuções do catálogo (≈ 2×) mais ≈ 0,10 s de correspondência, impressões
de conteúdo, diferenças e relação entre auditorias. Meta SC-005 (< 10 s): atendida com folga de duas ordens de
grandeza. Não se presumiu custo igual ao da auditoria individual: o custo adicional foi medido separadamente
(`compare_s`).

## Cenários A–G (salas sintéticas, laboratório)

Montados por `cli/make_scenarios.php --year=2027`; origem 2026, destino 2027. Saídas completas em
`cenario_<X>.txt` (texto da CLI) e `cenario_<X>.json` (contrato `comparison` 1.0).

| Cenário | Origem → destino | Veredito | Preserv. | Modif. (relev.) | Ausentes | Ambíguas | Pendências (classes) | Tempo |
|---|---|---|---|---|---|---|---|---|
| A importação correta | 6 → 7 | Revisar (só CL-14) | 12 | 0 | 0 | 1 (Avisos) | 2 pendência da oferta (CL-14) | 0,14 s |
| B sala com problemas | 8 → 9 | Revisar | 20 | 0 | 0 | 1 (Avisos) | 21 persistentes, 3 pendências, 1 resolvido; `hiddensections` relevante | 0,15 s |
| C glossário de jogo | 10 → 11 | Revisar | 1 | 2 (2) | 0 | 1 (Avisos) | CL-18 novo no destino (ATENÇÃO → NÃO CONFORME) | 0,08 s |
| D avaliação alterada | 6 → 12 | Revisar | 11 | 1 (1) | 0 | 1 (Avisos) | 5 novos (CL-12, CL-13, CL-17 ×2, X-AV-01), 2 pendências | 0,12 s |
| E arquivos | 13 → 14 | Revisar | 0 | 3 (3) | 0 | 1 (Avisos) | 5 novos (X-CONT-01, X-INT-02, X-STR-01, CL-12 ×2) | 0,08 s |
| F nomes repetidos | 15 → 16 | Revisar | 2 | 0 | 0 | 2 (Avisos, Leitura complementar) | 2 novos (CL-12 ×2, artefato da sala sintética) | 0,08 s |
| G oferta não ajustada | 6 → 17 | Revisar | 12 | 0 | 0 | 1 (Avisos) | 3 pendências (CL-06, CL-07, CL-08) | 0,13 s |

Observações:
- A e D: a importação **não desloca as datas de encerramento** dos questionários (continuam em 31/12/2026); o CL-14
  aparece como pendência da nova oferta, não como problema herdado.
- E e F: as salas sintéticas desses cenários não têm itens de nota; na origem o livro de notas nunca foi
  materializado (o Moodle cria a categoria raiz sob demanda) e o CL-12 não produz resultado no mesmo local; no
  destino a restauração cria a categoria e o CL-12 a avalia. Isso aparece como "novo no destino" e é um artefato do
  cenário, registrado aqui para não ser confundido com um efeito da importação em salas reais (que sempre têm livro
  de notas).
