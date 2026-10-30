# Relatório C — Plano de estabilização

**Auditor MOOC (`local_mooc_audit` 0.5.0) · revisão independente · 2026-10-29**

Este plano organiza os achados dos Relatórios A (técnica) e B (UX). **Nada aqui está autorizado para implementação**;
a sequência é uma proposta para decisão. Esforço: **P** ≤ 1 dia · **M** 1–3 dias · **G** > 3 dias (estimativas de uma
pessoa com o contexto do projeto, incluindo testes).

## 1. Síntese executiva

O plugin está **tecnicamente bem construído** e alinhado às práticas oficiais do Moodle: PHPCS `moodle` limpo, 214
testes PHPUnit e 13 Behat passando, CI com `moodle-plugin-ci`, controle de acesso por capacidades em todas as páginas,
escritas protegidas por `sesskey`, e um caminho de correção com detecção de conflito acima da média. Não foi encontrado
nenhum risco crítico (P0).

Não há defeito P1. O achado mais visível (A‑01, P2 após errata) é uma limitação já documentada: depois de uma
correção assistida bem-sucedida, o relatório continua mostrando o problema (com aviso de que a sala mudou) até alguém
executar a auditoria de novo, porque a reauditoria não é gravada no histórico.

O maior ganho operacional não está na técnica, e sim na **apresentação**. O relatório foi organizado para inspeção, não
para resolução: códigos e valores de banco no lugar de frases, o mesmo problema espalhado em várias seções, inventário
antes do diagnóstico, tabelas que não cabem no celular. Isso pede uma spec de experiência do usuário, validada com
pessoas reais.

## 2. Riscos impeditivos de homologação

Nenhum achado impede a **homologação** (testes em ambiente institucional). Errata de 2026-10-30: o A‑01 constava aqui como P1, mas era limitação documentada e mitigada pelo aviso de sala alterada; passou a P2 e foi tratado na etapa 1. Os da seção 3 devem ser resolvidos
antes da **instalação em produção**.

## 3. Melhorias necessárias (antes da produção)

| ID | Achado | Dimensão | Esforço | Decisão institucional? |
|---|---|---|---|---|
| A‑02 | Trilha de correções de salas removidas fora da Privacy API; sem retenção | Privacidade | P | Sim: prazo de guarda da trilha |
| A‑03 / B‑19 | "Desatualizada" dispara em qualquer atualização do plugin (104/104 salas) | Operação | P | Não |
| B‑19 | Contraste insuficiente (WCAG 1.4.3) no painel; `target-size` | Acessibilidade | P | Não |
| A‑04 | Scripts de fixtures no pacote de produção com guarda "OU" | Operação | P | Sim: empacotamento/deploy |
| B‑10 | Proposta orientada usa referência não conforme (CL‑14 × CL‑07) | Produto | P | Não |
| B‑07 (mínimo) | Tabelas sem `.table-responsive`; refluxo a 320 px (WCAG 1.4.10) | Acessibilidade | P | Não |
| A‑05 | ~~Medir o painel com 2–5 mil cursos~~ **Medido em 2026-10-30:** 0,13 s com 3.000 salas e 0,88 s (página) com 10.000; sem otimização necessária; falta só confirmar o volume real | Desempenho | — | Sim: informar o volume |
| A‑06 | Testar no PHP e no banco da produção (CI + laboratório) | Compatibilidade | P–M | Sim: plataforma de produção |
| — | Ensaio de instalação em cópia anonimizada do Moodle institucional (permissões e histórico reais) | Operação | M | Sim: autorização de cópia |

## 4. Melhorias recomendadas

| ID | Achado | Esforço | Ganho esperado |
|---|---|---|---|
| B‑01, B‑04 | Topo do relatório com situação e "o que fazer primeiro"; inventário e metadados recolhidos; contagens em frase | M | Menos tempo para achar a primeira ação; elimina a leitura errada de "Conforme 28/29" |
| B‑02, B‑06 | Visão "por atividade" e um botão por atividade + regra | M | Corrigir de uma vez o que hoje está em 5 linhas |
| B‑03, A‑09 | Evidência estruturada + textos em `lang/` + reescrita em linguagem simples | G | Professor entende sem a equipe técnica; textos ajustáveis sem mexer nas regras |
| B‑05 | Orientação em todo alerta | P | Distingue "conferir" de "corrigir" |
| B‑12, B‑13, B‑11 | Correção sem códigos crus; aviso de desfazer só quando há escrita | P | Confiança no resultado |
| B‑14, B‑15, B‑16 | Comparação organizada por pergunta; vocabulário alinhado com o histórico | M | Menos ambiguidade na reoferta |
| B‑17, B‑18 | Histórico destacando só o que mudou | P–M | Página de 10 mil px vira uma frase quando nada mudou |
| B‑20 | Painel com filtros "pioraram", "melhoraram" e "sala alterada" | M | Indicadores que levam a ação |
| A‑08 | Eventos para auditoria e lote | P | Rastreabilidade nos logs padrão |
| A‑11 | Separar blocos ao extrair texto | P | Textos legíveis na comparação; heurísticas mais robustas |

## 5. Melhorias opcionais

A‑07 (ajustar constituição ou riscos), A‑10 (decidir X‑AV‑01), A‑12 (mensagens de erro amigáveis), A‑13 (FKs no
XMLDB), A‑14 (`moodle-extra`), A‑15 (documentação), A‑16 (trava na página), B‑08 (títulos), B‑21 (aviso de atualização
no lote). Considerar o Report Builder (`system_report`) para o painel **somente** se a medição do A‑05 mostrar
necessidade de paginação e filtros em SQL. Não é exigência oficial e acrescentaria complexidade.

## 6. Dependências

```
A-01 ─────────────────────────────► (independente; primeiro)
A-03 ──► B-19 (o cartão "Desatualizada" só volta a ter sentido depois do A-03)
A-09 ──► B-03 ──► B-01/B-02/B-05 (as frases novas dependem de evidência estruturada e strings em lang/)
Roteiro de testes (Relatório B §8) ──► antes e depois da spec de UX
Decisão: volume real de cursos ──► A-05 ──► B-20
Decisão: PHP/banco da produção ──► A-06
Decisão: prazo da trilha ──► A-02
Decisão 10 (nomes canônicos, pendente) ──► textos das regras institucionais em B-03
```

## 7. Como organizar o trabalho

| Forma | Conteúdo | Justificativa |
|---|---|---|
| **Pequenas correções independentes** (sem spec nova; PRs curtos com teste) | A‑01, A‑02, A‑03, A‑04, B‑07 mínimo, B‑10, B‑11, B‑19 (contraste), A‑11, A‑15 | São defeitos com escopo claro; uma spec seria cerimônia sem ganho. Cada um ganha teste de regressão (Behat para A‑01) |
| **Spec de experiência do usuário** (próxima spec, por exemplo `006-experiencia-relatorio`) | B‑01–B‑06, B‑12, B‑13, com A‑09 como base técnica; depois comparação, histórico e painel (B‑14–B‑20), em um segundo incremento | Muda contrato de apresentação e textos de 29 regras; precisa de critérios de aceite testáveis e de validação com usuários |
| **Spec de estabilização técnica** | **Não recomendada agora.** O que sobra (A‑06 CI, A‑08 eventos, A‑13 FKs) cabe em correções pequenas | Evitar uma spec "guarda-chuva" sem resultado verificável |
| **Pendências de validação institucional** | Decisão 10; prazo da trilha (A‑02); empacotamento (A‑04); volume e plataforma da produção (A‑05, A‑06); X‑AV‑01 (A‑10); calendário de migração do Moodle 4.5 (segurança até 04/10/2027) | Sem elas, a produção fica com premissas não confirmadas |

Spec 004 (API externa): sem código. Recomendo **adiar** até depois da spec de UX. A API exporta o mesmo contrato que
a reorganização de evidências (A‑09) pode estender. Fazer a API antes obrigaria a manter compatibilidade com campos que
estão para mudar.

## 8. Sequência sugerida

| Etapa | Conteúdo | Esforço total | Saída verificável |
|---|---|---|---|
| 1 | A‑01 (+ Behat "corrige → volta → não vê"), A‑03, B‑19 contraste, B‑10, B‑11 | 2–3 dias | Suíte verde; painel do laboratório sem "desatualizada" falsa; axe sem `color-contrast` |
| 2 | A‑02, A‑04, B‑07 mínimo, A‑11, A‑15; decisões institucionais pendentes encaminhadas | 2–3 dias | Teste de privacidade com sala removida; pacote de produção definido |
| 3 | Homologação: ensaio em cópia institucional, medição do painel (A‑05), PHP/banco da produção (A‑06); **primeira rodada do roteiro de usuários na interface atual** (linha de base) | 1 semana (calendário) | Relatório de homologação; métricas de base |
| 4 | Spec 006 (UX do relatório e da correção): specify → clarify → plan → tasks → analyze; protótipo validado com 3–5 usuários antes de implementar | 2–3 semanas | Critérios do Relatório B §8 atingidos |
| 5 | Segundo incremento de UX (comparação, histórico, painel) | 2 semanas | Idem |
| 6 | Spec 004 (API), sobre o contrato estabilizado | conforme a spec | — |

## 9. Ganhos esperados

- **Etapas 1–2:** o fluxo de correção passa a fechar o ciclo. O painel volta a sinalizar só o que mudou de fato. Ficam
  resolvidas as não conformidades de acessibilidade detectáveis e a lacuna de privacidade. Risco de produção reduzido sem
  mexer na arquitetura.
- **Etapas 4–5 (hipótese a confirmar com o roteiro):** menos tempo até a primeira ação no relatório; menos dependência
  da equipe técnica para interpretar evidências; página do relatório de 3 a 4 vezes mais curta; leitura viável no
  celular.

## 10. O que não fazer

- Não reescrever o motor, o snapshot nem unificar `evolution` e `audit_relation`: a complexidade é localizada e
  coberta por testes.
- Não transformar cada ocorrência em cartão longo.
- Não introduzir "curso aprovado" nem selo de conformidade sem decisão institucional sobre o que isso significa.
- Não simplificar a prévia de correção a ponto de esconder impedimentos, riscos ou a ausência de desfazer.
