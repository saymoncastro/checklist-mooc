# Protótipo de inspeção de backups Moodle (.mbz) — somente leitura

Prova de conceito usada na investigação de viabilidade da auditoria automatizada de cursos MOOC.
**Não é a implementação definitiva** (a tecnologia final recomendada é PHP/Laravel; ver o relatório principal).

## O que faz

1. **Leitor MBZ**: detecta o formato (gzip+tar ou zip), extrai em diretório isolado com checagens
   (caminhos absolutos, `..`, links simbólicos, limites de tamanho) e registra erros de stream.
2. **Normalizador**: lê `moodle_backup.xml`, `course/*.xml`, `sections/*/section.xml`,
   `activities/*/{module,inforef,grades,<mod>}.xml`, `gradebook.xml`, `files.xml`, `badges.xml`,
   `questions.xml` e os blocos (`configdata` em base64 + serialização PHP) para um modelo interno.
3. **Motor de regras**: 51 regras (29 derivadas do checklist, `CL-xx`, e descobertas adicionais, `X-xx`),
   cada uma produzindo resultados com estado (`CONFORME`, `NAO_CONFORME`, `ATENCAO`, `NAO_APLICAVEL`,
   `NAO_VERIFICAVEL`, `ERRO`), local, evidência, valor encontrado/esperado e orientação.
4. **Saída**: relatório em texto no terminal e JSON estruturado (`--out`).

A política institucional (ano da reoferta, datas de término, nota mínima, tentativas, nomes de recursos
obrigatórios etc.) está em `DEFAULT_POLICY` e pode ser sobrescrita com `--policy politica.json`.

## Uso

```bash
python3 -I poc/mbz_inspect.py caminho/do/backup.mbz --out resultado.json
```

Opcionalmente `--workdir DIR` mantém a extração para inspeção manual. Nenhum acesso à rede é feito;
nenhum conteúdo do backup é executado; o `.mbz` original não é modificado.

## Resultados da execução nos backups do projeto

`resultados/curso-{55,670,668}.{txt,json}` — gerados em 2026-10-28 com a política de exemplo
(ano de reoferta 2026). Consulte o relatório principal para a interpretação (o que é achado comprovado,
o que é suspeita e o que é falso positivo conhecido).

## Limitações conhecidas do protótipo

- Regras por nome (fórum "Espaço de interação", "Pesquisa de Perfil") dependem de nomes canônicos.
- A soma de 100 pontos assume agregação Natural (soma) na categoria raiz.
- Não faz verificação de URLs externas (fase 2) nem emissão de certificado de teste (pós-restauração).
- O desserializador PHP cobre apenas os tipos usados em `configdata` de blocos.
