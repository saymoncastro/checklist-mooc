# Revisão geral do Auditor MOOC — síntese executiva e parecer

**Data:** 2026-10-29 · **Base:** `main` em `8c1fe03`, plugin `local_mooc_audit` 0.5.0 (2026103001), Moodle 4.5.15 de
laboratório · **Natureza:** auditoria independente, sem alterações no plugin.

| Documento | Conteúdo |
|---|---|
| [A — Conformidade técnica Moodle](A-CONFORMIDADE-TECNICA-MOODLE.md) | Arquitetura, ferramentas, segurança, privacidade, operação, 16 achados técnicos |
| [B — UX e carga cognitiva](B-AVALIACAO-UX-COGNITIVA.md) | Perfis, jornadas, 21 achados por tela, alternativas, roteiro de testes com usuários |
| [C — Plano de estabilização](C-PLANO-DE-ESTABILIZACAO.md) | O que corrigir antes da homologação e da produção, dependências, sequência |
| `capturas/` | 11 capturas das telas reais (laboratório s5) |
| `evidencias/` | Saídas de PHPUnit, Behat, PHPCS e complexidade |

## Respostas às dez perguntas

1. **O plugin está tecnicamente adequado às práticas oficiais do Moodle?**
   Sim, em grau alto.
   - PHPCS `moodle` limpo; os 414 avisos do `moodle-extra` são todos cosméticos (visibilidade de constantes).
   - 214 PHPUnit e 13 Behat (5 com axe) passando; CI `moodle-plugin-ci` verde.
   - Usa as APIs oficiais: capacidades, renderers/Mustache, Forms, Task API, Privacy API, XMLDB com savepoints.
   - Divergências menores: sem FKs no XMLDB, poucos eventos de log, textos das regras fora de `lang/`.

2. **Existem problemas que impedem a homologação?**
   Não. *Errata de 2026-10-30:* a primeira versão apontava o A‑01 como P1. Ele era limitação já documentada no
   README e mitigada pelo aviso "a sala foi alterada"; foi reclassificado como P2 e tratado na etapa 1.

3. **Há riscos de segurança ou integridade não cobertos pelas specs anteriores?**
   Nenhum P0. Não foram encontrados SQL injection, XSS, CSRF ou acesso indevido entre salas. Riscos novos:
   - **A‑02:** trilha de correções de salas apagadas fica fora da Privacy API e sem retenção.
   - **A‑04:** scripts que criam cursos de teste vão no pacote de produção, com uma guarda permissiva.
   - **A‑05:** o painel carrega todas as salas antes de filtrar; não foi medido no volume institucional.

4. **A arquitetura está sustentável ou excessivamente complexa?**
   Sustentável. A complexidade está concentrada em quatro funções testadas: evolução, relação entre auditorias, painel
   e snapshot. Há dois vocabulários de comparação, um por spec, com justificativa técnica. Recomendo alinhar só os
   termos exibidos, não o código. Nenhuma refatoração estrutural é recomendada.

5. **A experiência atual é compreensível para um professor editor?**
   Só em parte; hipótese a validar com usuários.
   - As ações existem (link para a tela certa, proposta de correção com prévia).
   - Porém o relatório fala a língua do banco (`status=1`, `aggregateonlygraded=1`, `cmid=24`), põe o inventário
     antes do diagnóstico e espalha o mesmo problema em várias seções.
   - No celular, as tabelas são cortadas.

6. **Quais elementos mais aumentam o esforço cognitivo?**
   - Evidências com nomes e valores internos.
   - A mesma atividade repetida em grupos de gravidade diferentes.
   - Contagens com unidades misturadas: "Conforme 28 · Regras 29" numa sala "Bloqueada".
   - O resultado da correção não refletido no relatório.
   - Termos sem definição ("persistente", com sentidos diferentes no histórico e na comparação; "pendência da oferta").
   - O sinal "Desatualizada" aceso em 100% das salas.

7. **Quais mudanças provavelmente oferecem maior ganho operacional?**
   1. Gravar a reauditoria pós-correção (A‑01).
   2. Restringir "desatualizada" às mudanças que afetam o resultado (A‑03).
   3. Topo do relatório com situação e próxima ação, e visão "por atividade" (B‑01/B‑02).
   4. Evidências em linguagem do Moodle, com textos em `lang/` (B‑03/A‑09).

8. **Quais recomendações exigem validação com usuários?**
   Toda a reorganização das telas (B‑01 a B‑06, B‑14, B‑17, B‑20) e a reescrita dos textos. O roteiro está no
   Relatório B, §8. **Nenhum teste com usuários foi feito.**

9. **O que deve ser corrigido antes da instalação institucional?**
   - Defeitos e lacunas: A‑01, A‑02, A‑03, A‑04 e B‑10.
   - Acessibilidade: contraste do painel e refluxo mínimo das tabelas (B‑19, B‑07).
   - Ensaio de instalação numa cópia anonimizada do Moodle institucional.
   - Medição do painel com o volume real.
   - Testes no PHP e no banco da produção.

10. **Qual deve ser o próximo ciclo de desenvolvimento?**
    1. Um lote de **pequenas correções**, sem spec nova (cerca de 1 semana).
    2. Homologação com linha de base de usabilidade.
    3. Uma **spec de experiência do usuário** (relatório e correção primeiro; comparação, histórico e painel depois).
    4. Só então a Spec 004 (API), que hoje existe apenas como especificação.

## Parecer sobre a prontidão para homologação institucional

**Pronto para homologação.** A base técnica é sólida e segura para ser instalada num ambiente de homologação
institucional (não produção). *Errata de 2026-10-30:* a condição "corrigir A‑01 antes" foi retirada, porque o
achado era limitação documentada e mitigada (agora P2, tratado na etapa 1).

Antes de **produção**, é preciso resolver os itens da seção 3 do Relatório C e obter as decisões institucionais
pendentes:
- nomes canônicos (decisão 10);
- prazo de guarda da trilha de correções;
- processo de empacotamento;
- volume, PHP e banco da produção;
- calendário de migração do Moodle 4.5, cujo suporte de segurança termina em 04/10/2027.

A melhoria da experiência deve ser tratada como o próximo investimento principal, guiada por testes com professores,
equipe técnica e gestores.

**Aguardando autorização para implementar qualquer recomendação.**
