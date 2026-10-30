# Backlog priorizado das especificações

**Data:** 2026-10-28 · **Convenção:** numeração sequencial do Spec Kit (`specs/NNN-nome/`); a numeração pode ser
ajustada quando cada especificação for criada. Apenas a **001** está especificada; as demais são propostas de escopo
para orientar prioridade e dependências, **não** especificações completas.

| Ordem | Spec | Nome | Depende de | Valor | Risco técnico |
|---|---|---|---|---|---|
| 1 | **001** | Auditoria técnica operacional de cursos MOOC (MVP) | — | alto: torna a PoC usável institucionalmente | médio: reorganizar regras sem mudar comportamento |
| 2 | **002** | Correção assistida | 001 (relatório com localização estável) | alto: fecha o ciclo diagnóstico → correção | alto: escrita em salas; autorização, conflito, registro |
| 3 | **003** | Comparação entre ofertas | 001 (snapshot estável, matching por atributos) | médio: explica problemas herdados × introduzidos | médio: ambiguidade de correspondência |
| 4 | **005** | Auditoria em lote, histórico e acompanhamento | 001; persistência (se 001 não a incluir) | alto para gestores | médio: tarefas, volume, consultas |
| 5 | **006** | Experiência do relatório individual e da correção assistida (especificada em `specs/006-experiencia-relatorio/`) | 001, 002; etapas 1 e 2 do plano de estabilização; linha de base com usuários | alto: reduz a dependência da equipe técnica para interpretar o relatório | médio: textos das 29 regras, contrato 1.1 compatível |
| 6 | **004** | API e integração externa | 001 (contrato JSON versionado); 005 se expuser histórico; 006 (contrato 1.1) | médio: habilita MOOC Admin como consumidor | médio: autenticação, versionamento |

A ordem recomendada troca 004 e 005: o histórico e o lote servem diretamente à equipe do Cefor; a API só agrega valor
quando houver um consumidor pronto (MOOC Admin).

Depois da revisão geral de 2026-10-29 (`docs/revisao-geral/C-PLANO-DE-ESTABILIZACAO.md`), a 006 (experiência do
usuário) entra antes da 004: a API passaria a expor um contrato que a 006 estende.

---

## 001 — Auditoria técnica operacional (especificada em `specs/001-auditoria-tecnica-operacional/`)

Evolução da PoC para MVP: acesso pela administração do curso, controle por capacidade, política institucional,
regras prioritárias consolidadas com teste por regra, relatório por categoria e gravidade com evidência, orientação e
links, reexecução, tratamento de resultados incompletos, contrato JSON versionado. Sem correção, lote, API ou
histórico (persistência mínima somente se a especificação a justificar).

## 002 — Correção assistida (especificada em `specs/002-correcao-assistida/`; incremento experimental 0.3.0 entregue para revisão)

- Orientações contextualizadas por inconformidade (passo a passo na interface do Moodle, com link direto).
- Para um subconjunto de regras determinísticas (datas do curso, autoinscrição, `hiddensections`, conclusão de
  rótulo, `aggregateonlygraded`, tentativas do quiz, data de encerramento): prévia da alteração (valor atual →
  esperado, impactos), confirmação explícita do usuário, verificação de conflito (estado ainda igual ao
  diagnosticado), aplicação pela API oficial do módulo, registro (quem, quando, antes/depois), reauditoria imediata.
- Capacidade própria `local/mooc_audit:fix` (contexto de curso), separada de `:audit`.
- Proteções: nunca em lote; nunca em sala com estudantes ativos sem aviso explícito; lista branca de campos
  corrigíveis definida na política; sem correção de conteúdo (textos, arquivos, restrições complexas).
- Fora: correções que exigem decisão pedagógica (restrições da Avaliação, certificado, glossário/jogos).
- Pré-requisitos: 001 entregue; decisão institucional sobre quem pode corrigir; princípio IV da constituição.

## 003 — Comparação entre ofertas (especificada em `specs/003-comparacao-ofertas/`; incremento experimental 0.4.0 entregue para revisão)

- Seleção de sala de origem e de destino (ids, com busca por nome).
- Correspondência de módulos por atributos estáveis (tipo + seção + nome → tipo + nome → tipo + seção + posição),
  nunca por `cmid`; ambiguidades e duplicatas (fórum de avisos nativo da sala nova) reportadas explicitamente.
- Diferenças esperadas na reoferta (ids, shortname, datas, categoria) separadas das inesperadas (conclusão,
  restrições remapeadas, notas, arquivos, blocos, inscrição, formato).
- Classificação do relatório da sala nova: problema **herdado** (presente na origem), **introduzido** pela
  importação (ausente na origem), **pendência específica da nova oferta** (datas, inscrição, categoria).
- Tratamento de módulos com identificação ambígua e de versões em Libras com mesmo nome.
- Correção do defeito conhecido (`problematic` sempre verdadeiro) e testes do comparador.
- Fora: comparação de conteúdo textual palavra a palavra; comparação com `.mbz`.

## 004 — API e integração externa (especificada em `specs/004-api-externa/`; incremento experimental 0.6.0 entregue para revisão)

- Web Services próprios, somente leitura: solicitar auditoria de um curso e consultar o último resultado (ou um
  resultado por id, se houver histórico).
- Contrato JSON versionado (`contract_version`), compatível com o relatório atual, com nota de migração a cada
  mudança incompatível.
- Autenticação e autorização pelo mecanismo nativo (serviço externo, token, capacidade `local/mooc_audit:api` no
  contexto de sistema ou de categoria).
- Integração opcional com o MOOC Admin como consumidor; nenhum dado do MOOC Admin é pré-requisito.
- Fora: escrita via API; disparo de correções; auditoria em lote via API (usa 005).

## 005 — Auditoria em lote, histórico e acompanhamento (especificada em `specs/005-auditoria-lote-historico/`; incremento experimental 0.5.0 entregue para revisão)

- Execuções persistidas e imutáveis (página, lote, CLI), com a política aplicada guardada por impressão digital.
- Chave estável de ocorrência (`regra|entidade|verificação[|discriminador]`) declarada pelas regras; evolução entre
  execuções: nova, persistente, resolvida, estado/gravidade alterados, não verificável, não comparável (política ou
  regra mudou), recurso removido ou substituído; correções da Spec 002 listadas no período sem atribuição.
- Lotes por categoria, ids, ano de início ou seleção no painel, em tarefas ad hoc com concorrência limitada, trava por
  sala, reconferência de permissão e retomada após interrupção; reexecução de falhas.
- Painel de acompanhamento com situação por sala, desatualização, filtros e evolução somada, sem selo nem percentual.
- Retenção configurável, privacy provider atualizado. Fora: exportação CSV, notificações, agendamento recorrente de
  lotes, correção em lote, API (Spec 004), adoção da chave de ocorrência na comparação entre ofertas (Spec 003).

## Itens de catálogo sem especificação própria (entram como incrementos de regras)

Ver `docs/catalogo-regras.md` §J: regras planejadas de prioridade 2 e 3 serão agrupadas em especificações curtas
("Incremento de regras N") após 001, cada uma com testes por regra, sem alterar o motor.
