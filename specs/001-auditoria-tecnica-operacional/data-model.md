# Data Model — 001 Auditoria Técnica Operacional

Nenhuma tabela é criada. Todas as entidades são estruturas em memória (arrays PHP) produzidas por execução e
serializadas em JSON pelo contrato `contracts/report-v1.0.schema.json`.

## Relatório (`report`)

| Campo | Tipo | Regra |
|---|---|---|
| `contract_version` | string | `"1.0"` nesta entrega; muda só em alteração incompatível |
| `engine` | string | `"local_mooc_audit <release>"` |
| `policy_version` | string | versão da política; sufixo `+admin` quando personalizada |
| `policy_warnings` | string[] | vazio quando a política é válida |
| `reoffer_year` | int | ano considerado (política ou ano corrente + 1) |
| `executed_at` | string ISO 8601 | data/hora da execução |
| `course` | objeto `{id, shortname, fullname}` | obrigatório |
| `viewer_userid` | int | 0 = visão administrativa |
| `timing` | `{snapshot_s, total_s}` | segundos |
| `inventory` | objeto | período, categoria, visibilidade, seções, módulos por tipo, itens de nota, inscrições, blocos, plugins usados, arquivos, arquivos sem conteúdo, site |
| `summary` | objeto | `por_status` (contagem por estado), `nao_conformes_por_gravidade`, `regras` (total do catálogo), `overall_status` |
| `results` | Resultado[] | ordem de avaliação do catálogo (a ordenação para exibição é do renderer) |
| `warnings` | string[] | avisos da coleta (snapshot) |
| `snapshot` | objeto (opcional) | só com `--snapshot` no CLI |

**Situação geral (`summary.overall_status`)**: `BLOQUEADA` se existe resultado `NAO_CONFORME` com gravidade
`Bloqueante`; senão `REQUER_CORRECOES` se existe `NAO_CONFORME`; senão `SEM_INCONFORMIDADES`. Estados `ATENCAO`,
`NAO_APLICAVEL`, `NAO_VERIFICAVEL` e `ERRO` **não** influenciam a situação geral.

## Resultado (`result`)

| Campo | Tipo | Regra |
|---|---|---|
| `rule` | string | id estável (`CL-nn`, `X-ÁREA-nn`) |
| `titulo` | string | título da regra (string de idioma) |
| `origem` | string | `checklist:n` ou `extra:§` |
| `classe` | string | `A` (herdado da PoC; viabilidade) |
| `category` | string | um dos 8 valores de `category` |
| `tipo` | string | `institucional` ou `tecnica` |
| `status` | enum | `CONFORME`, `NAO_CONFORME`, `ATENCAO`, `NAO_APLICAVEL`, `NAO_VERIFICAVEL`, `ERRO` |
| `gravidade` | enum | `Bloqueante`, `Alta`, `Média`, `Baixa`, `Informativa` (padrão da regra ou sobrescrita do finding) |
| `local` | string | texto legível; obrigatório quando `status` ∈ {NAO_CONFORME, ATENCAO} (também no contrato JSON) |
| `cmid` | int ou null | módulo do curso, quando aplicável |
| `locus` | enum | `module_edit`, `module_view`, `course_settings`, `enrol`, `gradebook`, `blocks`, `section`, `none` |
| `section` | int ou null | número da seção quando `locus = section` |
| `link` | string (URL) ou null | derivado por `locator`; null quando `locus = none` ou sem destino |
| `evidencia` | string | obrigatória, sem truncamento |
| `encontrado` | string ou null | — |
| `esperado` | string ou null | obrigatório quando `status = NAO_CONFORME` e a regra tem valor esperado determinável |
| `orientacao` | string ou null | obrigatória quando `status = NAO_CONFORME` (herda a padrão da regra se o finding não a define) |

Validações aplicadas pelo `engine` (teste em `engine_test`): todo resultado tem `rule`, `status` válido, `gravidade`
válida, `category` válida; `NAO_CONFORME` tem `local`, `evidencia` e `orientacao` não vazios.

## Metadados de regra (`rule::meta()`)

| Campo | Tipo | Regra |
|---|---|---|
| `id` | string | igual a `id()` |
| `title` | string (lang key) | `rule_<id>_title` |
| `category` | enum | ver research R12 |
| `origin` | string | `checklist:n` / `extra:§` |
| `type` | enum | `institucional` / `tecnica` |
| `severity` | enum | gravidade padrão |
| `policy_keys` | string[] | chaves de `policy` lidas (vazio para técnicas) |
| `locus` | enum | tipo de local padrão dos findings |
| `guidance` | string (lang key) | orientação padrão |
| `source` | string | fonte normativa (item do checklist, decisão institucional, doc Moodle) |

## Política (`policy`)

Array associativo; chaves e padrões idênticos a `policy::defaults()` da PoC. Carregamento: padrões → setting
`local_mooc_audit/policyjson` (JSON objeto, mescla rasa) → sobrescrita por arquivo (CLI). `warnings[]` recebe:
`policyjson inválido: <motivo>`; `chave <k>: tipo inesperado, padrão mantido`. `reoffer_year` nulo → ano de
`reference_timestamp` + 1.

## Categoria (`category`)

Enum com 8 valores e string de idioma cada: `offer_enrol`, `institutional_resources`, `assessment_grades`,
`completion_restrictions`, `certification`, `content_files`, `structure_blocks`, `plugins_environment`.

## Estado e gravidade (`status`)

Constantes movidas de `rules` para `local\status`: 6 estados, 5 gravidades, ordem de exibição
(NAO_CONFORME < ATENCAO < ERRO < NAO_VERIFICAVEL < NAO_APLICAVEL < CONFORME; Bloqueante < Alta < Média < Baixa <
Informativa).

## Transições

Não há estado persistido; cada execução produz um relatório novo. A "reexecução" é uma nova execução.
