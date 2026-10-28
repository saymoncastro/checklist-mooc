# checklist-mooc — Auditoria automatizada de cursos MOOC a partir de backups Moodle

Investigação de viabilidade e desenho de uma solução que recebe o backup `.mbz` de um curso Moodle,
analisa componentes e configurações, identifica inconsistências e gera um relatório técnico com
orientações de correção, para apoiar o processo anual de reoferta dos cursos MOOC do Cefor/Ifes.

## Conteúdo

| Caminho | Descrição |
|---|---|
| `RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md` | Relatório completo: engenharia reversa do formato MBZ, matriz dos 29 itens do checklist, catálogo de regras adicionais, achados nos backups reais, comparação arquitetural, MVP, arquitetura e roadmap |
| `DECISOES-INSTITUCIONAIS-CHECKLIST.md` | As 10 ambiguidades do checklist como decisões institucionais, com as respostas registradas |
| `RELATORIO-AUDITORIA-VIA-API-MOODLE.md` | Investigação complementar: auditar a sala restaurada via Web Services do Moodle 4.5 — inventário de funções, matriz de cobertura regra a regra, lacunas, verificações só possíveis na sala ativa, decisão arquitetural (motor único com adaptadores MBZ e API) |
| `poc/moodle_api_client.py` | Cliente REST isolado e somente leitura (allowlist de funções) para a prova comparativa, ainda não executado |
| `poc/plano-prova-comparativa-api.md` | Plano da prova MBZ × API em homologação (14 regras, capacidades, critérios) |
| `poc/mbz_inspect.py` | Protótipo somente-leitura (Python, só biblioteca padrão) que extrai o `.mbz` com segurança, normaliza os XML e executa 51 regras |
| `poc/resultados/` | Saídas do protótipo (texto e JSON) para os três backups examinados |
| `poc/README.md` | Uso e limitações do protótipo |

Os backups `.mbz` e a planilha do checklist **não** estão versionados (ver `.gitignore`): contêm conteúdo
institucional e arquivos grandes. Coloque-os na raiz do projeto para reproduzir a análise.

## Reproduzir

```bash
python3 -I poc/mbz_inspect.py caminho/do/backup.mbz --out resultado.json
```

## Estado

Investigação concluída em 2026-10-28. Recomendação: motor de auditoria em PHP/Laravel como pacote interno do
MOOC Admin, com política institucional em JSON versionada por ciclo; o protótipo Python serve de especificação
executável e oráculo de testes. Próximos passos e pendências estão no final do relatório e do documento de decisões.
