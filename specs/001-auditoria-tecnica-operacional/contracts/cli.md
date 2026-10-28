# Contrato — linha de comando `cli/audit.php`

Inalterado em relação à PoC (FR-025). Executa como administrador do site.

```bash
php local/mooc_audit/cli/audit.php --courseid=N [--userid=N] [--policy=FILE] [--json] [--out=FILE] [--snapshot]
```

| Opção | Efeito |
|---|---|
| `--courseid=N` (`-c`) | curso a auditar (obrigatório) |
| `--userid=N` | visão usada em `uservisible` (0 = administrativa) |
| `--policy=FILE` | JSON que sobrescreve parâmetros da política (mescla rasa); arquivo inválido → erro e saída 1 |
| `--json` | imprime o relatório JSON (contrato 1.0) em vez do texto |
| `--out=FILE` | grava o JSON em arquivo |
| `--snapshot` | inclui o retrato completo no JSON |
| `--help` (`-h`) | ajuda |

Saída texto: cabeçalho, inventário, síntese (inclui situação geral), não conformes e alertas ordenados por estado e
gravidade, conformes resumidos, avisos (coleta e política). Código de saída 0 em execução concluída, mesmo com
inconformidades; 1 em erro de uso.

`cli/compare.php`, `cli/make_fixtures.php` e `cli/make_import.php` ficam fora desta entrega (inalterados).
