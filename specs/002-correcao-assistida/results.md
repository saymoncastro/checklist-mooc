# Resultados — Spec 002, Correção Assistida (plugin 0.3.0)

**Data**: 2026-10-29 · **Branch**: `claude/spec-002-correcao-assistida-9d1925`, criada a partir da branch da Spec 001
(`claude/install-spec-kit-01eeb1`, `73f2c78`) porque o PR #1 continua **aberto** (não mesclado). O PR desta spec tem
como base a branch da Spec 001. **Ambiente**: site local isolado (`moodle_s2`, porta 8093); laboratório principal,
contêineres da Spec 001 e produção intocados.

## 1. Entregáveis

| # | Entregável | Onde |
|---|---|---|
| 1 | Spec 002 no Spec Kit | `specs/002-correcao-assistida/spec.md`, `checklists/requirements.md` |
| 2 | Plano técnico, pesquisa, modelo, contrato, tarefas | `plan.md`, `research.md`, `data-model.md`, `contracts/correction-page.md`, `tasks.md` |
| 3 | Matriz de regras elegíveis | `correction-matrix.md` (29 regras: A implementada 2, A futuro 6, B 12, C 9) |
| 4 | Implementação experimental | `plugin/local_mooc_audit/classes/local/correction/`, `correction.php`, `db/` |
| 5 | Interface de prévia e confirmação | `correction.php`, `templates/correction*.mustache`, ação no relatório |
| 6 | Registro mínimo | tabela `local_mooc_audit_fix` + evento `correction_applied` |
| 7 | Testes automatizados | 36 PHPUnit novos (111 no total), 3 cenários Behat novos (5 no total) |
| 8–9 | Evidências e antes/depois | `plugin-results/0.3.0/` |
| 10 | Revisão de segurança | §4 |
| 11 | Constituição e Spec 001 | `analysis.md` |
| 12 | PR | ver descrição do PR |

## 2. Execução dos testes

| Suíte | Resultado |
|---|---|
| PHPUnit `local_mooc_audit_testsuite` | **111 testes, 2001 asserções, OK** (75 da Spec 001, incluindo caracterização A/B e contrato 1.0, + 36 novos) |
| Behat `@local_mooc_audit` | **5 cenários, 69 passos, OK** (2 da Spec 001 + 3 novos; acessibilidade axe na prévia) |
| PHPCS `moodle` | 0 erros, 0 avisos |
| `php -l` | sem erros |
| Upgrade 0.2.0 → 0.3.0 | `admin/cli/upgrade.php` cria a tabela e as capacidades |

## 3. Cenários experimentais pedidos

| # | Cenário | Prova automatizada | Prova no Moodle local |
|---|---|---|---|
| 1 | Autorizado visualiza a proposta | `planner_test`, Behat 1 | passo 2 |
| 2 | Não autorizado não executa | `executor_test::test_capabilities_are_checked_on_the_server`, Behat 3 | — |
| 3 | Abrir proposta não modifica | `readonly_test::test_correction_preview_does_not_modify_the_course` | passo 2 (banco conferido) |
| 4 | Cancelar não modifica | Behat 2 | — |
| 5 | Confirmar altera só o previsto | `test_cl13_applies_only_the_planned_field`, `test_cl14_sets_close_date_and_calendar` | passos 3 e 8 |
| 6 | Alteração concorrente interrompe | `test_conflicts_never_write`, `test_value_changed_inside_the_transaction` | passo 7 |
| 7 | Curso com dados de estudantes | `test_cl13_students`, `test_refused_with_student_data_…`, `test_student_attempt_appearing_before_the_write` | passo 10 |
| 8 | Correção registrada | trilha + evento em `test_cl13_applies_only_the_planned_field` | `trilha.txt`, log padrão |
| 9 | Reauditoria correta | `statuses_of` após execução; `verifier_test` | passos 3 e 8 |
| 10 | Repetição sem alteração indevida | `test_repetition_is_harmless` | (atualizar a página após confirmar → "nada a corrigir") |
| 11 | Falha parcial sem falso sucesso | `test_failure_rolls_back_and_is_recorded`, `test_value_not_read_back_is_a_failure`, `test_reaudit_failure_is_reported`, `output_test::test_result_messages` | — |
| 12 | Auditoria segue somente leitura | `readonly_test` (original e novo), caracterização A/B | curso A idêntico antes/depois |

Critério de sucesso: além do banco, os testes leem o valor por `\mod_quiz\quiz_settings` (e o evento de calendário no
CL-14) e reauditam; no Moodle local a tela oficial de edição exibiu o novo valor.

## 4. Revisão de segurança e permissões

- **Escrita só por POST confirmado**: `correction.php` executa apenas com `confirm=1`, `data_submitted()` e
  `require_sesskey()`; GET sempre mostra a prévia.
- **Autorização no servidor, em camadas**: página exige `preparecorrection`; confirmação exige `applycorrection` na
  página **e** no executor, que também exige `moodle/course:manageactivities` no contexto da atividade. A atividade
  precisa pertencer ao curso (`get_fast_modinfo($courseid)->cms[$cmid]`). O botão oculto não é a proteção.
- **Nenhum valor do navegador**: o POST carrega só identificadores e a impressão digital; valores atual/proposto são
  recalculados do banco; impressão digital diferente = conflito.
- **Concorrência**: trava por atividade; releitura do valor dentro da transação; `cacherev` no fingerprint.
- **Escrita mínima**: lista branca de 2 colunas; comparação do registro inteiro nos testes.
- **Saída**: templates usam apenas `{{ }}` (escapado); nenhum `{{{ }}}`; conteúdo de curso (nomes) escapado.
- **Privacidade**: só `userid` de quem confirmou; exportação; exclusão por anonimização.
- **Capacidades**: `applycorrection` com `captype=write` e `RISK_CONFIG`; padrão apenas gerente.
- Revisão independente (subagente, leitura do diff): ver §4.1.

### 4.1 Revisão independente

Um subagente revisou o diff somente leitura (sem acesso às conclusões deste relatório). Nenhum achado alto; nenhum
problema em: escrita sem POST/sesskey/capacidades, escrita no caminho de auditoria, falsificação ou repetição da
impressão digital, escape de saída, privacidade e definição das capacidades. Achados e tratamento:

| # | Achado | Gravidade | Tratamento |
|---|---|---|---|
| 1 | Falha na releitura pela API ocorria depois do commit: a página dizia "nada foi mantido" com o valor já gravado | média | Corrigido: `persisted()` roda dentro da transação; divergência desfaz tudo. Teste passa a conferir o valor no banco |
| 2 | Tentativas/notas de estudantes não eram reconferidas dentro da transação (janela entre a reconstrução da proposta e a escrita) | baixa/média | Corrigido: `assert_no_student_data()` em `apply()` → conflito. Novo teste `test_student_attempt_appearing_before_the_write` |
| 3 | Atualização do cache do curso e evento dentro da transação: um rollback poderia deixar cache em revisão nova | baixa | Corrigido: `after_commit()` chamado pelo executor depois do commit |
| 4 | "Quem pode executar" listava papéis só com `applycorrection` | baixa | Corrigido: papéis com as duas capacidades |
| 5 | Evento com `crud = u` / `LEVEL_OTHER` | baixa | Ajustado para `crud = c` (objeto = linha da trilha) e `LEVEL_TEACHING` |

As evidências da interface (§3, `plugin-results/0.3.0/`) foram coletadas antes dessas correções; uma reexecução real
com o código final (trilha #3 e #4, `Questionário 4`) aplicou e resolveu CL-13 e CL-14 novamente.

## 5. Perguntas finais

**O plugin consegue propor correções de forma confiável?** Sim, para as três regras do incremento: a proposta é
recalculada do estado atual, cita a política e a fonte, mostra impedimentos e riscos e traz o impacto **simulado**
pelas próprias regras (não uma afirmação). Nos cursos A/B e nos 35 testes, toda simulação coincidiu com a reauditoria
real. Para as demais 26 regras o plugin continua apenas com a orientação da Spec 001.

**Quais alterações foram efetivamente executadas?** Duas, no curso B do ambiente local: `quiz.attempts` 1 → 2
(CL-13, Questionário 1) e `quiz.timeclose` não definido → 31/12/2027 23:59 (CL-14, mesmo questionário), ambas
confirmadas pela reauditoria e registradas (trilha #1 e #2). O ajuste do término do curso (CL-07) foi feito
manualmente, como dependência, fora do plugin.

**Quais permanecem apenas como orientação?** CL-14 em tarefa, fórum, escolha, pesquisa, base de dados e jogo;
CL-17 em todos os tipos; CL-13 quando o problema é só o enunciado ou quando a correção reduziria tentativas; qualquer
proposta A com impedimento (dados de estudantes, término do curso fora da política, abertura posterior, simulação
desfavorável).

**A confirmação está protegida por permissões adequadas?** Sim: capacidade própria de escrita, separada de ver a
auditoria e de ver propostas, somada à capacidade nativa de gerenciar atividades, verificadas no servidor, com chave
de sessão e POST. Quem recebe `applycorrection` em produção ainda é decisão institucional (**[validar]**).

**É possível garantir que uma correção não provoque outra inconformidade?** Não em termos absolutos, mas o risco é
controlado em duas camadas: (1) a simulação executa as 29 regras com o valor proposto e bloqueia a execução se a
inconformidade alvo não sumir ou se surgir um `NAO_CONFORME` (ex.: enunciado que declara 1 tentativa); (2) a
reauditoria após a escrita compara antes × depois e relata inconformidades introduzidas, sem declarar sucesso
nesse caso. O que as regras não verificam (ex.: efeito pedagógico) continua fora do alcance.

**Como são identificados conflitos e alterações concorrentes?** Impressão digital sha256 do estado da prévia
(`course.cacherev`, `timemodified` da instância, valores atual/proposto, versão da política, impedimentos), comparada
com a proposta reconstruída na confirmação; trava `fix_<cmid>`; releitura do valor dentro da transação antes do
`update_record`. Comprovado por teste e no Moodle local (renomear a atividade entre prévia e confirmação).

**O registro é suficiente para auditoria institucional?** Para o MVP, sim: curso, atividade, regra, estratégia, campo,
valor anterior, valor novo, usuário, data/hora, resultado da execução (inclusive falhas), resultado da reauditoria,
versão da política e do motor — na tabela própria e no log padrão. Não há tela de consulta da trilha além do histórico
por atividade na prévia e do relatório de logs do Moodle; consolidação e histórico de auditorias são da Spec 005.

**Qual é o esforço para ampliar a correção assistida às demais regras?** A arquitetura não muda: cada nova correção é
uma estratégia (descrever, aplicar, reler) + strings + testes. Estimativa por regra da categoria "A futuro":
CL-19/CL-20 (`hiddensections`) ≈ 0,5–1 dia; CL-05/06/07/08 ≈ 1–2 dias cada, mais a decisão institucional sobre o
momento de publicar a sala; CL-14 para tarefa/fórum ≈ 1–2 dias por tipo (prorrogações, datas derivadas); CL-17
executável exige estudo de bloqueio/recálculo de conclusão com registros (≈ 3–5 dias com testes).

**Quais correções não devem ser automatizadas?** Notas e pesos (CL-12), restrições da Avaliação e do certificado
(CL-16, CL-23, X-CERT-01, X-DEP-*), conteúdo (CL-04, X-CONT-*), arquivos (X-INT-02, X-CONT-04), componentes
institucionais ausentes (CL-09, CL-11, CL-21, CL-22) e glossário dos jogos (CL-18); também a redução de tentativas e
qualquer alteração em atividade com tentativas/notas de estudantes.

**A Spec 002 está pronta para merge e posterior homologação?** Pronta para **revisão** técnica, não para merge
imediato: (1) depende do merge do PR #1 (Spec 001) — o PR desta spec tem a branch da 001 como base; (2) faltam as
validações institucionais marcadas **[validar]** (quem corrige; marco do encerramento); (3) validação em uma cópia da
instalação institucional com plugins e dados reais antes de produção. Tecnicamente, a suíte completa, o Behat e o
PHPCS passam.

## 6. Limitações

- Executável apenas para questionários; sem desfazer automático; sem lote.
- Janela residual de concorrência com edição manual simultânea (milissegundos) entre releitura e gravação.
- Evidências da interface por texto da página (`get_page_text`) e consultas ao banco; sem capturas de tela versionadas.
- Fuso do laboratório: Europe/London. O valor gravado é o mesmo instante do término do curso, portanto independente
  de fuso, mas a exibição das datas deve ser conferida na homologação com o fuso da instalação institucional.
