# Matriz de regras elegíveis para correção

**Spec**: 002 · **Data**: 2026-10-29 · **Política**: `2027-decisoes-2026-10-28`

Categorias:

- **A — executável mediante confirmação**: alteração objetiva, parametrizada pela política, em um campo isolado, com
  rotina do núcleo conhecida para aplicá-la e condição de segurança verificável.
- **B — orientada**: exige interpretação pedagógica ou depende de outras configurações; o plugin explica, propõe
  alternativas e encaminha à tela oficial.
- **C — depende de recurso externo ou decisão institucional**: falta conteúdo, arquivo, componente institucional ou
  decisão; o plugin indica o que recuperar, criar ou decidir, sem inventar conteúdo.

"Nesta entrega" indica o que a Spec 002 implementa: **executável** (estratégia A com confirmação), **orientada**
(estratégia B com prévia, sem execução) ou **—** (sem proposta: mantém orientação e link da Spec 001).

| Regra | Título resumido | Categoria | Nesta entrega | Por quê / condição |
|---|---|---|---|---|
| CL-04 | Botões institucionais no sumário | B | — | Edição de texto do sumário; o modelo de HTML é institucional |
| CL-05 | Categoria "Inscrições Abertas" | A (futuro) | — | Campo único, mas publica a sala: momento é decisão institucional; exige `moodle/course:changecategory` |
| CL-06 | Data de início 01/01 | A (futuro) | — | Campo único; o Moodle não desloca datas de atividades e inscrição juntas — exige plano conjunto com CL-08/CL-14 |
| CL-07 | Data de término 31/12 | A (futuro) | — | Idem; é dependência do CL-14 |
| CL-08 | Autoinscrição e período | A (futuro) | — | Abre inscrições: efeito sobre público; depende de CL-06/07 |
| CL-09 | Fórum "Espaço de interação" | C (ausente) / B (conclusão, nome) | — | Ausência = importar da Sala Padrão; nome canônico pendente (decisão 10) |
| CL-11 | Pesquisa de Perfil | C (ausente) / B (restrição) | — | Recurso institucional; restrição do certificado é cadeia de certificação |
| CL-12 | Livro de notas (soma 100, agregação) | B | — | Altera lógica da avaliação; pesos são decisão pedagógica |
| **CL-13** | Tentativas de questionário | **A** (tentativas) / B (enunciado) | **executável** | `quiz.attempts` < mínimo e > 0; sem tentativas/notas de estudantes; nunca reduz; nunca altera método de nota |
| **CL-14** | Data de encerramento | **A** (questionário) / B (outros tipos) | **executável** (questionário) / **orientada** (demais) | `quiz.timeclose` ← término do curso; término conforme CL-07 e futuro; abertura anterior; sem tentativas |
| CL-16 | Avaliação do curso restrita às atividades com nota | B | — | Restrição de acesso composta; afeta fluxo do estudante |
| **CL-17** | Critério de conclusão por tipo | B (A futuro) | **orientada** | Conclusão interage com restrições, Avaliação, certificado e registros de estudantes |
| CL-18 | Glossário | C | — | Entradas precisam ser reimportadas de fonte autorizada ou jogos reconfigurados |
| CL-19 | Rótulos sem conclusão | A (futuro) | — | Campo `completion` do módulo; pode afetar dependências (checar X-DEP-02) |
| CL-20 | Seções | A (futuro: `hiddensections`) / B (vazias, nomes) | — | Opção de formato é objetiva; nomes e organização são pedagógicos |
| CL-21 | Blocos padrão | C | — | Bloco de site/tema ou conteúdo institucional (decisão 8) |
| CL-22 | Licença para capacitação | C | — | Componentes institucionais importados |
| CL-23 | Certificado condicionado | B | — | Cadeia de certificação: nunca automática nesta fase |
| X-AV-01 | Enunciado × pontos | B | — | Texto ou nota: qual está certo é decisão do autor |
| X-CERT-01 | Alcançabilidade do certificado | B | — | Diagnóstico composto de várias regras |
| X-CONT-01 | Recursos vazios | C | — | Conteúdo pedagógico |
| X-CONT-04 | `@@PLUGINFILE@@` sem arquivo | C | — | Arquivo deve vir de fonte autorizada |
| X-DEP-01 | Condição para módulo inexistente | B | — | Restrição de acesso a reconstruir |
| X-DEP-02 | Condição sobre módulo sem conclusão/oculto | B | — | Duas correções possíveis (condição ou conclusão) |
| X-DEP-03 | Condição de nota para item inexistente | B | — | Restrição a reconstruir |
| X-DEP-04 | Ciclos de dependência | B | — | Escolha de qual aresta remover é pedagógica |
| X-INT-02 | Arquivos sem conteúdo no armazenamento | C | — | Recuperação de arquivo por backup/fonte autorizada (fora do plugin) |
| X-INT-03 | Plugins usados não instalados | C | — | Administração do site |
| X-STR-01 | Módulos ocultos / nome vazio | B | — | Ocultação pode ser intencional |

Totais (29 regras): A implementada 2 · A futuro 6 · B 12 (1 implementada como orientada) · C 9. Regras com duas
categorias contam pela principal.

## Correções que não devem ser automatizadas

Notas e pesos (CL-12), restrições da Avaliação e do certificado (CL-16, CL-23, X-CERT-01, X-DEP-*), conteúdo
(CL-04, X-CONT-*), arquivos (X-INT-02, X-CONT-04), componentes institucionais ausentes (CL-09, CL-11, CL-21, CL-22) e
glossário dos jogos (CL-18). Em todos esses casos o plugin orienta; a decisão e a ação são humanas.

## Critério para promover uma regra à categoria A

1. Campo isolado, valor esperado derivado da política sem interpretação.
2. Rotina do núcleo identificada para atualizar esse campo (com efeitos colaterais conhecidos e reproduzidos).
3. Condições de dados de estudantes verificáveis por consulta.
4. Simulação sobre o snapshot capaz de prever o resultado.
5. Testes PHPUnit de execução, conflito, permissão, dados de estudantes e reauditoria; cenário Behat.
