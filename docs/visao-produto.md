# Visão de produto — Auditor MOOC

**Data:** 2026-10-28 · **Status:** proposta para validação institucional (Cefor/CGTE) · **Base:** decisão
arquitetural da PoC (`RELATORIO-POC-PLUGIN-MOODLE.md`), decisões institucionais registradas, constituição do projeto.

## 1. Problema

Todo ano o Cefor reoferta cursos MOOC: cria salas novas, importa conteúdo das ofertas anteriores e confere, item a
item, um checklist técnico de 29 pontos. A conferência é manual, demorada, desigual entre revisores e chega tarde:
problemas herdados (glossários vazios, arquivos perdidos, restrições impossíveis, certificado inalcançável) só
aparecem quando um estudante reclama. Não há registro do que foi verificado nem de quando.

## 2. Objetivo

Reduzir o esforço manual e aumentar a confiabilidade da preparação, auditoria e acompanhamento das salas MOOC,
tornando instantâneas e uniformes as verificações de configuração e deixando para as pessoas apenas o julgamento
pedagógico, visual e os testes funcionais.

## 3. Produto

**Auditor MOOC** é um plugin local do Moodle (`local_mooc_audit`), instalado uma única vez na plataforma, que:

- audita qualquer sala autorizada a partir do seu identificador, lendo o estado real da sala de dentro do Moodle;
- aplica um catálogo de regras rastreáveis (checklist institucional + verificações técnicas) sob uma política
  institucional versionada;
- entrega um relatório legível e estruturado: situação geral, inconformidades com local, evidência e orientação,
  alertas, itens conformes e itens não verificáveis;
- não altera nada durante o diagnóstico; correções futuras serão propostas separadamente, com autorização e
  registro;
- funciona sem o MOOC Admin; integrações são consumidoras opcionais do seu resultado.

Não é: um analisador de backups `.mbz`, um substituto do julgamento pedagógico, um sistema de bloqueio automático de
publicação, nem uma ferramenta de IA.

## 4. Usuários

| Usuário | O que faz com o produto | O que precisa ver |
|---|---|---|
| **Equipe técnica do Cefor (CGTE)** | executa auditorias, investiga inconformidades, acompanha a reoferta, confere configurações institucionais, reexecuta após correções | relatório completo com evidências e `cmid`, filtros por gravidade/categoria, acesso direto ao recurso/configuração, histórico por sala |
| **Proponentes e responsáveis pelos cursos** | consultam os problemas da própria sala, entendem o que corrigir e como; futuramente executam correções autorizadas | linguagem não técnica, orientação passo a passo, link para onde corrigir, sem jargão de banco de dados |
| **Gestores** | acompanham nível de conformidade, volume de pendências e evolução do ciclo de reoferta | síntese por sala e por ciclo, tendência, pendências bloqueantes |

Permissões seguem o Moodle: capacidade de auditar no contexto do curso (equipe técnica e, por decisão
institucional, responsáveis); comparação, lote e API em contexto de sistema.

## 5. Capacidades pretendidas e ordem de entrega

| # | Capacidade | Entrega | Situação na PoC |
|---|---|---|---|
| 1 | Auditoria técnica de uma sala | **001** | implementada (CLI + página mínima) |
| 2 | Relatório de conformidade organizado por categoria e gravidade, com evidências | **001** | parcial (tabela única) |
| 3 | Orientação para correções (o que, onde, como; links de navegação) | **001** (orientação textual + links) e **002** (contextualização) | parcial (texto por regra) |
| 4 | Correções assistidas | **002** | inexistente |
| 5 | Comparação entre ofertas (origem × destino) | **003** | experimental (CLI) |
| 6 | Histórico de auditorias | **005** (persistência mínima pode entrar em 001 se justificada) | inexistente |
| 7 | Auditoria em lote e acompanhamento | **005** | inexistente |
| 8 | API para integração externa (MOOC Admin e outros) | **004** | experimental (0.6.0): pedir auditoria, consultar execução e última auditoria por Web Services |

## 6. Experiência mínima a entregar (MVP, spec 001)

Um servidor com permissão acessa a sala no Moodle, encontra "Auditoria técnica" na administração do curso, executa a
auditoria e vê, na própria página: situação geral; inconformidades agrupadas por gravidade e categoria, cada uma com
onde está, qual a evidência, o que era esperado e o que fazer, com link para o recurso ou configuração quando
existir; alertas; regras conformes; itens não verificáveis e o motivo. Corrige pelo Moodle, volta à página e
reexecuta. O resultado é o mesmo em JSON para quem precisar.

## 7. Princípios de produto (resumo da constituição)

Independência operacional · plugin padrão sem tocar o núcleo · auditoria somente leitura · correção só assistida e
autorizada · regras rastreáveis com política fora do código · seis estados explícitos (não verificado ≠ falha) ·
testes por regra · segurança pelo modelo de capacidades do Moodle · simplicidade (nada além do Moodle) · evolução
em incrementos especificados.

## 8. Medidas de sucesso (produto)

- Tempo de conferência técnica de uma sala: de horas para minutos (auditoria < 5 s; leitura do relatório < 15 min).
- Zero inconformidades bloqueantes conhecidas no lançamento de uma reoferta auditada.
- Toda inconformidade apontada é compreendida e localizada pelo responsável sem ajuda da equipe técnica (meta: ≥ 90 %
  das inconformidades do relatório têm orientação e local acionáveis).
- Falsos positivos reportados pela equipe por ciclo: ≤ 1 por regra, com correção na política ou na regra.

## 9. Decisões que ainda exigem validação institucional

1. Nomes canônicos dos recursos institucionais (decisão 10, pendente) e tratamento de cursos antigos.
2. Quem recebe a capacidade de auditar além da equipe técnica (proponentes? gestores?).
3. Lista do que o MOOC Admin altera automaticamente na reoferta (para suprimir alarmes redundantes).
4. Imagens e texto oficiais do certificado (CL-24/25) e política de liberação condicionada (se algum dia houver).
5. Versões institucionais de `mod_simplecertificate` e `availability_days`, e referência de contagem do `days`.
6. Se o histórico de auditorias deve existir já no MVP (persistência mínima) ou apenas em 005.
