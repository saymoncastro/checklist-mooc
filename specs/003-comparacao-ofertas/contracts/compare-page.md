# Contrato da página e da CLI de comparação

## Página `/local/mooc_audit/compare.php`

**Contexto da página**: sistema (layout `admin`), registrada como `admin_externalpage('local_mooc_audit_compare')`
na categoria **Cursos** da administração do site, com a capacidade `local/mooc_audit:compare`.

### Parâmetros (GET)

| Parâmetro | Tipo | Significado |
|---|---|---|
| `source` | int | curso de origem (oferta anterior) |
| `target` | int | curso de destino (nova oferta) |
| `confirm` | 0/1 | confirmação de que `source` é a anterior e `target` a nova |
| `pair[]` | `<cmid>-<cmid>` | correspondências manuais (opcional, repetível) |
| `format` | `html` \| `json` | `json` devolve o contrato `comparison` 1.0 |

Sem `source`, `target` e `confirm=1` válidos a página mostra só o formulário.

### Acesso

1. `require_login()`.
2. Formulário: exige `local/mooc_audit:compare` no sistema **ou** em ao menos um curso (gerente de categoria).
3. Comparação: `require_capability('local/mooc_audit:compare', context_course::instance(id))` para **origem e
   destino**; qualquer falha → exceção padrão `required_capability`, sem dado de nenhuma das salas.
4. Curso inexistente ou `SITEID` → `invalidcourseid`; origem = destino → mensagem de erro no formulário.

### Formulário (`classes/form/compare_form.php`)

- "Sala de origem (oferta anterior)": autocomplete de cursos (nome/sigla; também aceita o id digitado na URL),
  restrito a cursos com a capacidade.
- "Sala de destino (nova oferta)": idem.
- Caixa obrigatória "Confirmo que a origem é a oferta anterior e o destino é a nova oferta".
- Botão **Comparar ofertas** (método GET, para que o resultado tenha URL reproduzível; a página não escreve nada).

### Seções do relatório (ordem fixa, FR-022)

1. Cabeçalho: salas (nome, sigla, id, período, categoria, nº de módulos, estado geral da auditoria), data, motor,
   política e ano de cada lado, nota sobre a política da origem, links JSON e "comparar novamente", aviso de inversão.
2. Síntese: veredito, contadores (preservadas, modificadas com diferença relevante, ausentes, adicionais,
   ambiguidades, duplicatas, pendências por classe) e legenda dos tipos de diferença.
3. **Pendências da sala de destino**: grupos `pendencia_oferta`, `novo_no_destino`, `novo_em_atividade_adicional`,
   `persistente`, `indeterminado`, `nao_verificavel_no_destino`; depois "Da oferta anterior": `ausente_no_destino`,
   `resolvido_no_destino`, `nao_aplicavel_no_destino`. Cada item: regra, título, gravidade, estado origem → destino,
   local (link para a sala de destino), evidências e orientação.
4. Atividades ausentes no destino.
5. Correspondências ambíguas, com o formulário de correspondência manual.
6. Atividades modificadas (relevantes primeiro), cada diferença com tipo, origem → destino e explicação.
7. Atividades adicionais no destino.
8. Diferenças da sala (configurações, seções, inscrição, blocos, livro de notas).
9. Diferenças esperadas (contadas e listadas recolhidas).
10. Atividades preservadas (lista compacta, com estratégia e confiança).
11. Avisos.

Nenhum botão de correção é exibido. Todo texto vindo do curso passa pelo escape do Mustache.

## Link a partir do relatório de auditoria

`index.php` (Spec 001) mostra "Comparar com a oferta anterior" →
`/local/mooc_audit/compare.php?target=<curso>` quando o usuário tem `local/mooc_audit:compare` no curso. O contrato
1.0 do relatório não muda (o link é só da página).

## CLI `cli/compare.php`

```
php local/mooc_audit/cli/compare.php --source=ID --target=ID [--pairs=SRC-TGT,SRC-TGT] [--json] [--out=FILE] [--policy=FILE]
```

- Executa como administrador (como `cli/audit.php`), somente leitura.
- Texto: mesmas seções da página, em linhas; `--json`: contrato `comparison` 1.0 (`comparator::to_json`).
- `--policy`: JSON com sobreposições da política (aplicadas aos dois lados; o ano da origem continua vindo da sua
  data de início).
- Código de saída 0 em sucesso; diferente de 0 (`cli_error`) para curso inválido ou origem = destino.
