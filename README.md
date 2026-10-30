# checklist-mooc — Auditor MOOC: auditoria técnica de cursos MOOC no Moodle (4.5 e 5.3)

Projeto do **Auditor MOOC**: plugin local Moodle (`local_mooc_audit`) que audita salas de cursos MOOC do Cefor/Ifes,
identifica inconformidades técnicas e gera relatório com orientações de correção, para apoiar o processo anual de
reoferta. Começou como investigação de viabilidade sobre backups `.mbz`, evoluiu para uma prova de conceito executável
dentro do Moodle 4.5 e agora é conduzido por especificações (Spec-Driven Development).

## Conteúdo

| Caminho | Descrição |
|---|---|
| `RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md` | Relatório completo: engenharia reversa do formato MBZ, matriz dos 29 itens do checklist, catálogo de regras adicionais, achados nos backups reais, comparação arquitetural, MVP, arquitetura e roadmap |
| `DECISOES-INSTITUCIONAIS-CHECKLIST.md` | As 10 ambiguidades do checklist como decisões institucionais, com as respostas registradas |
| `RELATORIO-AUDITORIA-VIA-API-MOODLE.md` | Investigação complementar: auditar a sala restaurada via Web Services do Moodle 4.5 — inventário de funções, matriz de cobertura regra a regra, lacunas, verificações só possíveis na sala ativa, decisão arquitetural (motor único com adaptadores MBZ e API) |
| `RELATORIO-POC-PLUGIN-MOODLE.md` | **Prova de conceito executada**: plugin `local_mooc_audit` em Moodle 4.5.15 local, cursos de teste A/B, importação nativa, comparação com o auditor MBZ e decisão arquitetural (plugin autônomo recomendado) |
| `plugin/local_mooc_audit/` | Plugin local Moodle (0.5.3, Moodle 4.5 e 5.3: 29 regras, relatório, correção assistida, comparação entre ofertas, histórico, lote e painel; PHPUnit e Behat) — ver seu `README.md` |
| `plugin-results/` | Saídas das auditorias (cursos restaurados 55/670, cursos A/B, importações, comparações, PHPUnit, captura de tela) |
| `moodle-env/` | Instruções e scripts reproduzíveis do ambiente Moodle local (moodle-docker, plugins de terceiros, restauração) |
| `poc/moodle_api_client.py` | Cliente REST isolado e somente leitura (allowlist de funções) para a prova comparativa via Web Services, não executado |
| `poc/plano-prova-comparativa-api.md` | Plano da prova MBZ × API em homologação (14 regras, capacidades, critérios) |
| `poc/mbz_inspect.py` | Protótipo somente-leitura (Python, só biblioteca padrão) que extrai o `.mbz` com segurança, normaliza os XML e executa 51 regras |
| `poc/resultados/` | Saídas do protótipo (texto e JSON) para os três backups examinados |
| `poc/README.md` | Uso e limitações do protótipo |
| `.specify/memory/constitution.md` | **Constituição do projeto** (Spec-Driven Development com GitHub Spec Kit): 10 princípios, restrições, portões de qualidade |
| `docs/baseline-poc.md` | Baseline técnico e funcional da PoC: capacidades com estado objetivo, cobertura de testes real, divergências documentação × código |
| `docs/catalogo-regras.md` | Catálogo consolidado de regras (CL-*, X-*, ENV-*) com status de implementação, testes, risco de falso positivo e prioridade |
| `docs/visao-produto.md` | Visão de produto do Auditor MOOC: usuários, 8 capacidades, experiência mínima, decisões pendentes |
| `docs/backlog.md` | Backlog priorizado das especificações 001–005 |
| `docs/relatorio-adocao-sdd.md` | Relatório executivo da adoção do SDD: riscos, decisões, recomendações |
| `specs/001-auditoria-tecnica-operacional/` | **Especificação 001** (MVP): spec, plano, pesquisa, modelo de dados, contratos (JSON 1.0, CLI, página), quickstart, tarefas (105) e análise de consistência |
| `specs/002-correcao-assistida/`, `specs/003-comparacao-ofertas/`, `specs/005-auditoria-lote-historico/` | Especificações implementadas da correção assistida, da comparação entre ofertas e do histórico/lote/painel |
| `specs/004-api-externa/` | Especificação implementada da API externa (Web Services, plugin 0.6.0; guia em `plugin/local_mooc_audit/API.md`) |
| `docs/revisao-geral/` | Revisão geral independente (2026-10-29): conformidade Moodle, UX e carga cognitiva, plano de estabilização e parecer |

Os backups `.mbz` e a planilha do checklist **não** estão versionados (ver `.gitignore`): contêm conteúdo
institucional e arquivos grandes. Coloque-os na raiz do projeto para reproduzir a análise.

## Reproduzir

```bash
python3 -I poc/mbz_inspect.py caminho/do/backup.mbz --out resultado.json
```

## Estado

- **2026-10-28 (manhã):** investigação de viabilidade concluída; recomendação inicial de motor PHP/Laravel no MOOC
  Admin (`RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md`) e investigação via Web Services
  (`RELATORIO-AUDITORIA-VIA-API-MOODLE.md`). **Superadas** pela prova de conceito.
- **2026-10-28 (PoC):** plugin autônomo `local_mooc_audit` executado em Moodle 4.5.15 local; **decisão arquitetural
  vigente: plugin local Moodle com motor interno**, independente do MOOC Admin e de `.mbz`
  (`RELATORIO-POC-PLUGIN-MOODLE.md`). O protótipo Python permanece como oráculo das regras.
- **2026-10-28 (SDD):** adoção do GitHub Spec Kit; constituição v1.0.0; baseline, catálogo, visão de produto, backlog;
  especificação 001 com plano, tarefas e análise (`docs/relatorio-adocao-sdd.md`).
- **2026-10-29 (spec 001 implementada):** plugin 0.2.0 com as 29 regras em classes próprias e teste por regra, página
  do relatório no menu do curso com links de correção e reexecução, contrato JSON 1.0 com schema, política ajustável
  na administração. 75 testes PHPUnit, 2 cenários Behat com axe, PHPCS sem avisos; estados idênticos à PoC em A, B, 55
  e 670 (`plugin-results/0.2.0/`). Pendências institucionais: decisão 10 (nomes canônicos), permissão de proponentes,
  validação com bloco de site real.
- **2026-10-29 (specs 002, 003 e 005):** correção assistida (0.3.0), comparação entre ofertas (0.4.0), histórico, lote
  e painel (0.5.0); especificação 004 (API externa) aprovada como documento, sem implementação.
- **2026-10-30 (revisão geral e estabilização):** revisão independente em `docs/revisao-geral/` (parecer: pronto para
  homologação; nenhum P0). Etapas 1 e 2 do plano de estabilização (0.5.1 e 0.5.2): reauditoria pós-correção registrada,
  desatualização só por mudança de regra, privacidade da trilha de salas removidas, guarda estrita dos scripts de
  dados de teste, tabelas responsivas e ajustes de acessibilidade (`plugin-results/0.5.1/`, `plugin-results/0.5.2/`).
- **2026-10-30 (Moodle 5.3):** plugin 0.5.3 testado no Moodle 5.3 LTS (PHPUnit, Behat com axe, auditorias A/B
  idênticas às do 4.5); selos migrados para classes válidas nos Bootstraps 4 e 5 (`plugin-results/0.5.3/`).
- **2026-10-30 (spec 004 implementada):** plugin 0.6.0 com a API externa: três funções de Web Services
  (pedir auditoria, consultar execução, última auditoria), capacidade `local/mooc_audit:api` sem papel padrão, serviço
  desabilitado e restrito; guia `plugin/local_mooc_audit/API.md` (`specs/004-api-externa/results.md`).

## Fluxo de desenvolvimento (Spec Kit)

Toda funcionalidade segue `/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` →
`/speckit-analyze` → implementação, sob a constituição em `.specify/memory/constitution.md`. Especificações vivem em
`specs/NNN-nome/`. Testes PHPUnit do plugin rodam no ambiente de `moodle-env/`:

```bash
docker exec -i -w /var/www/html moocaudit-webserver-1 vendor/bin/phpunit local/mooc_audit/tests
```
