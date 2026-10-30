# Resultados — Spec 006

## Fase 1 — textos e links

**Versão:** Auditor 0.7.0 (2026103102). **Ramo:** `claude/spec-006-implementacao` (PR #17, mesclado). **Data:** 2026-10-30.

## O que mudou para quem usa

Cada linha de problema do relatório passou a dizer, no idioma de quem vê e com os nomes atuais das atividades:

- **o problema** em uma frase (o código da regra fica discreto, abaixo);
- **por que importa**;
- **como corrigir**, com as alternativas quando a escolha é pedagógica (X‑DEP‑02, alvo oculto);
- **ações** que abrem a seção certa do formulário do Moodle (`showonly`), para as duas pontas de uma dependência e
  para cada atividade de um ciclo, só quando a atividade existe e quem vê pode usar a tela;
- **quem pode** e **como confirmar**;
- **notas de ordem** (lista fechada): condição de acesso inválida antes de outra correção na mesma atividade; datas
  do curso antes da inscrição e do encerramento; soma das notas antes da nota mínima do certificado;
- **aviso de plugin ausente** no topo, quando o site não tem um plugin de que a política depende;
- **detalhes técnicos** num `<details>` nativo: regra, verificação, evidência, encontrado, exigido, orientação gravada
  e chave da ocorrência.

Linhas sem mensagem (execuções muito antigas) continuam com o texto gravado.

## O que não mudou

Regras, estados, gravidades, ids, chaves de ocorrência, impressão digital do código das regras e contrato JSON 1.0.
Nenhum arquivo de `classes/local/rule/`, `classes/engine.php` ou `classes/snapshot.php` foi alterado.

## Cobertura

- 81 variantes de mensagem (29 regras), cada uma com problema, por que importa e como corrigir em `en` e `pt_br`
  (`presentation_catalog_test`).
- Toda verificação declarada pelas regras está no catálogo; todo resultado não conforme, de atenção ou não verificável
  dos Cursos A e B tem mensagem.

## Verificação

| Verificação | Resultado |
|---|---|
| PHPUnit completo (Moodle 4.5.15, laboratório s2) | **258 testes, 7.652 asserções, todos verdes** — inclui `characterization_test` (Cursos A/B sem regravar) e os 14 testes novos (`presentation_catalog_test`, `presentation_finding_test`) |
| Behat `@local_mooc_audit` | **15 cenários, 244 passos, todos verdes** — inclui `report_fix.feature`: professor editor segue "Condições de conclusão: Aprofundamento", o formulário abre só nessa seção, ativa a conclusão, executa de novo e o problema some; professor sem edição vê o texto e "Professor editor da sala", sem ações; axe sem violações |
| PHPCS `moodle` | limpo nos arquivos novos e alterados |
| Moodle 5.3 (site dist53, só inglês, sem os três plugins de terceiros) | textos em inglês; aviso de plugins ausentes no topo; CL‑23, X‑CERT‑01 e CL‑21 indicam o administrador; CL‑18 deixa de mandar remover o glossário; CL‑20 cita o rótulo do 5.3 ("Hide completely"); rotas `showonly` corretas |
| Relatório real do Curso B (4.5, `pt_br`) | 30 de 30 linhas com mensagem; nenhuma linha principal com `cmid=`, `status=` ou JSON |

**Achado do teste Behat:** a página do Curso B de referência não termina de renderizar em 10 s no Behat (o índice do
curso com 24 atividades renderiza templates por AJAX sem cache); no navegador real não há erro. O cenário da jornada
usa uma sala com só a dependência do caso A, montada por um passo do plugin.

**Ajuste feito durante a fase:** os textos do CL‑13 e do CL‑14 citavam o botão "Ver proposta de correção", que só
aparece para quem pode preparar correções; passaram a dizer "se esta linha oferecer a correção assistida".

## Captura

![Relatório 0.7.0: o que fazer em cada problema](../../docs/capturas/relatorio-o-que-fazer.jpg)

## O que fica para as próximas fases

- Fase 2: resumo no topo, "Comece por aqui", visões por atividade e por prioridade, causas agrupadas e contadas
  (FR‑038), verificações manuais (FR‑040), detalhes da auditoria recolhidos, celular.
- Fase 3: `params` no contrato para dizer "encontrado × exigido" em frase; prévia e resultado da correção sem códigos.
- Fase 4 (decisão do Cefor): X‑INT‑03 com plugins exigidos, CL‑17 em rótulos e fórum institucional, CL‑18 sem afirmar
  colaboração, alvo oculto igual no X‑DEP‑02 e no X‑CERT‑01.
- Linha de base com pessoas no site congelado em 0.6.1 e avaliação final com a nova interface (FR‑029, FR‑030).

## Fase 2 — estrutura do relatório

**Versão:** Auditor 0.8.0 (2026103103). **Ramo:** `claude/spec-006-fase-2`. **Data:** 2026-10-30.

**O que mudou:** topo com a situação, os números da sala (problemas, bloqueantes, causas distintas, atividades e
configurações envolvidas, pontos para conferir, propostas de correção) e "Comece por aqui" (até três problemas; primeiro
os de que outras correções dependem); troca entre as visões "por prioridade" e "por atividade", lembrada em cookie do
navegador; ocorrências da mesma causa ligadas entre si (relações R12); impacto prático ao lado da gravidade; selo
"conferência de texto"/"busca por nome"; seção "Verificações manuais" com o procedimento de teste do certificado;
metadados, inventário e contagens por estado em "Detalhes técnicos da auditoria", recolhido, no fim; "Local" dentro da
coluna do problema; "Quem pode" e "Como confirmar" só quando dizem algo próprio; linhas empilhadas abaixo de 768 px;
títulos h2 → h3 → h4 → h5 sem saltos.

| Verificação | Resultado |
|---|---|
| PHPUnit completo (4.5.15, s2) | **261 testes, 7.674 asserções, todos verdes**; caracterização intacta; `presentation_summary_test` novo (contagens e causas do Curso B, "Comece por aqui" com a dependência primeiro, as duas visões com as mesmas ocorrências, Questionário 1 uma vez, sala sem problemas) |
| Behat `@local_mooc_audit` | **16 cenários, 262 passos, todos verdes**; cenário novo: troca para "por atividade", visão lembrada ao voltar, retorno a "por prioridade", axe |
| PHPCS `moodle` | limpo |
| FR‑001 (1366 × 900, Curso B) | situação, números e "Comece por aqui" terminam a 829 px: **sem rolagem** |
| FR‑015 | visões com as mesmas 30 ocorrências (25 não conformes, 5 alertas) |
| FR‑021 (375 px) | 0 linhas de problema com rolagem horizontal; só a tabela de contagens, dentro dos detalhes técnicos recolhidos, rola |
| FR‑022/FR‑023 (axe, 4.5 e 5.3) | sem violações do plugin; restam `landmark-unique` (navegação principal do Moodle) e, no 5.3, `color-contrast`/`region` do índice do curso do Moodle |
| SC‑014 | Curso B gerado: 25 problemas → 22 causas; cópia do Curso B no 5.3 sem plugins: 26 → 22 |

**Não atendido:** SC‑006 (página do Curso B com até um terço da altura antiga). A 1366 px a página tem cerca de
9.800 px, contra cerca de 7.800 px antes: cada linha agora explica o problema, a consequência e a correção, o que a
spec também exige (FR‑031). Para reduzir, a próxima opção é recolher "Por que importa" e as alternativas por padrão;
a decisão fica para depois da linha de base com usuários, que dirá se o texto visível ajuda ou atrapalha.

**Ainda em aberto:** visão inicial "por prioridade" **[validar]** pela linha de base; fase 3 (contrato 1.1, prévia e
resultado da correção em linguagem do Moodle); fase 4 (regras, decisão do Cefor).

## Fase 3 — contrato 1.1 e correção em linguagem do Moodle

**Versão:** Auditor 0.9.0 (2026103104). **Ramo:** `claude/spec-006-fase-3`. **Data:** 2026-10-30.

**O que mudou:**

- **Contrato 1.1** (`schema/report-v1.1.schema.json`, aditivo; o 1.0 continua publicado): cada resultado mantém
  `check` e `subject` e ganha `evidence_level`; uma lista fechada de verificações ganha `params` (encontrado e exigido)
  e `subjects` (atividades relacionadas). Tudo calculado por `classes/local/details.php` a partir do retrato e da
  política: **nenhuma regra mudou**, a impressão digital do código das regras é a mesma, nenhuma execução anterior
  passa a "desatualizada". A API continua na versão 1.0 e devolve o relatório gravado com seu `contract_version`.
- **Encontrado × Exigido** em frase nas linhas (datas no formato do Moodle, tentativas, estado da autoinscrição, soma
  das notas, condição de nota do certificado); X‑CERT‑01 lista e liga os itens que travam a cadeia do certificado.
- **Correção assistida** (US4): problema em frase e atividade pelo nome na prévia; sem a letra da categoria;
  permissões técnicas só para quem não pode executar; resultado com "Tentativas permitidas: 1 → 2", "não conforme →
  conforme" e os problemas resolvidos em frase (descrição injetada no `verifier` pelo planejador e pelo executor).

| Verificação | Resultado |
|---|---|
| PHPUnit completo (4.5.15, s2) | **265 testes, 7.877 asserções, todos verdes**; caracterização intacta; `details_test` novo; testes que fixavam o contrato 1.0, o texto de evidência na prévia e a ausência de `check` atualizados para o comportamento novo |
| Behat `@local_mooc_audit` | **16 cenários, 262 passos, todos verdes** (o cenário da correção confere "Attempts allowed: 1 → 2") |
| PHPCS `moodle` | limpo |
| Navegador (4.5, pt_br) | 8 linhas com "Encontrado × Esperado"; prévia e resultado da correção do CL‑13 sem códigos |
| Moodle 5.3 (inglês, sem plugins de terceiros) | contrato 1.1, 8 linhas com valores, datas por extenso |

**Ainda em aberto:** fase 4 (regras, decisão do Cefor); linha de base e avaliação final com pessoas (FR‑029, FR‑030);
SC‑006 (altura).
