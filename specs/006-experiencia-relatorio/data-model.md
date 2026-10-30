# Data model — Spec 006 (fase 1)

Nada é persistido. As entidades abaixo existem só na apresentação, montadas ao exibir a partir de um resultado gravado
(contrato 1.0).

## Fato da ocorrência (entrada)

| Campo | Origem |
|---|---|
| `rule`, `status`, `gravidade`, `cmid`, `locus`, `section`, `local`, `evidencia`, `encontrado`, `esperado`, `orientacao`, `link` | resultado gravado |
| `check`, `entity`, `subject` | `occurrence` = `regra|entidade|check[|subject][#n]` |
| `modname`, `name`, `exists` | `get_fast_modinfo()` para `cmid` e para `subject` `cm:N` |
| ids do ciclo | `subject` `a>b>c` do X‑DEP‑04 |

## Variante (catálogo)

`regra + check + variante` → chaves de string `msg_<regra>_<check>_<variante>_{problem,why,fix}` e, opcional,
`…_verify` (sem ela, vale `msg_verify_default`). Também declara:

- `who`: `teacher` | `team` | `admin`;
- `actions`: lista de tipos de ação (R4) com o alvo (`cmid`, `subject`, curso, seção, inscrição);
- `needs_plugin`: componente que, ausente, troca a variante por `…_noplugin`.

## Ocorrência apresentável (saída)

| Campo | Conteúdo |
|---|---|
| `problem` | uma frase, com nomes atuais |
| `why` | consequência |
| `fix` | o que fazer (pode conter alternativas) |
| `who` | "Você pode fazer isto" ou o responsável |
| `verify` | como confirmar |
| `actions[]` | `{label, url}` só para destinos existentes e permitidos |
| `before` | nota de precedência (R7), opcional |
| `place` | onde está o problema: nome da atividade, seção ou tela do curso, no idioma de quem vê |
| `alternatives[]` | alternativas quando a correção depende de uma decisão pedagógica |
| `fallback` | verdadeiro quando não há mensagem (usa o texto gravado) |
| detalhes técnicos | código e título da regra, `check`, evidência, encontrado, esperado, orientação gravada, chave |

## Aviso de plugin exigido ausente (saída, topo)

`{plugin, label}` para cada componente exigido não instalado; responsável: administrador do site.
