# Data Model: Comparação entre Ofertas

Nenhuma tabela nova. Todas as entidades existem só em memória durante a requisição e são serializadas pelo contrato
[`comparison` 1.0](contracts/comparison-v1.0.schema.json). Entrada: dois `CourseSnapshot` (Spec 001,
`classes/snapshot.php`) e os resultados do motor (`engine::run_rules`).

## Comparison (raiz)

| Campo | Tipo | Origem / regra |
|---|---|---|
| `contract` | `"comparison"` | constante |
| `contract_version` | `"1.0"` | `comparator::CONTRACT_VERSION` |
| `engine` | string | `local_mooc_audit <engine::VERSION>` |
| `executed_at` | ISO 8601 | relógio do servidor |
| `timing` | objeto | `snapshot_source_s`, `snapshot_target_s`, `rules_s`, `compare_s`, `total_s`, `memory_peak_mb` |
| `source`, `target` | CourseSide | ver abaixo |
| `policy` | PolicyContext | ver abaixo |
| `summary` | Summary | ver abaixo |
| `matches` | Match[] | preservadas e modificadas |
| `missing_in_target` | ModuleRef[] | origem sem par |
| `extra_in_target` | ExtraModule[] | destino sem par |
| `ambiguities` | Ambiguity[] | — |
| `course_diffs` | Difference[] | escopos `course`, `section`, `enrol`, `block`, `grade` |
| `audit` | AuditRelation | — |
| `manual_pairs` | {source_cmid, target_cmid, accepted, reason}[] | parâmetros `pair[]` |
| `warnings` | string[] | avisos de coleta das duas salas, pares manuais inválidos, possível inversão |

## CourseSide

`id`, `shortname`, `fullname`, `startdate` (Y-m-d H:i ou null), `enddate`, `category` (nome), `sections` (n),
`modules` (n), `modules_by_type` (objeto), `overall_status` (da auditoria daquela sala).

## PolicyContext

`version` (da política vigente), `target_reoffer_year`, `source_reoffer_year` (ano da data de início da origem ou
null), `source_basis` (`startdate` | `sem_contexto`), `cycle_rules` (lista de ids), `note` (frase que declara que a
origem é avaliada com a política vigente no ano da própria origem).

## Match

| Campo | Regra |
|---|---|
| `source_cmid`, `target_cmid` | ids **apenas informativos** (nunca usados para casar) |
| `modname`, `source_name`, `target_name`, `source_section`, `target_section` | — |
| `strategy` | `manual` \| `type_section_name` \| `type_section_name_content` \| `type_name` \| `type_content` \| `type_section_position` |
| `confidence` | `alta` (passes 1–3) \| `media` (4–5) \| `manual` |
| `state` | `preservada` (sem diferença relevante/informativa) \| `modificada` |
| `relevance` | `relevante` \| `informativa` \| `esperada` \| `nenhuma` (pior tipo entre as diferenças) |
| `problematic` | `relevance === 'relevante'` (correção do defeito) |
| `diffs` | Difference[] |
| `expected_count` | nº de diferenças esperadas omitidas da lista principal (ids remapeados) |

Transição: um par com diferenças só `esperada` tem `state = preservada`.

## Difference

`scope` (`module`, `course`, `section`, `enrol`, `block`, `grade`), `field`, `source`, `target`, `kind`
(`esperada` \| `relevante` \| `informativa`), `code` (tabela do research R3), `explanation` (texto da língua da
interface), `dependents` (lista opcional de `{cmid, modname, name}` — ex.: jogos que usam o glossário).
Valores textuais são truncados a 160 caracteres; nunca contêm senhas (inscrição só com `has_password`).

## Ambiguity

`reason` (`duplicado_no_destino` \| `duplicado_na_origem` \| `conteudo_equivalente` \| `mesmo_nome`), `modname`,
`name`, `source` ({cmid, section, position}[]), `target` (idem), `settings_identical` (bool: multiconjunto das
impressões de configuração idêntico nos dois lados), `explanation`.

## ModuleRef / ExtraModule

`cmid`, `modname`, `name`, `section`; ExtraModule acrescenta `kind`/`code` (`extra_graded` relevante,
`extra_resource` informativa).

## AuditRelation

`items`: AuditItem[]; `counts`: objeto por classe; `source_policy_version`, `target_policy_version`.

### AuditItem

| Campo | Regra |
|---|---|
| `classification` | destino: `persistente`, `novo_no_destino`, `novo_em_atividade_adicional`, `indeterminado`, `pendencia_oferta`, `nao_verificavel_no_destino`; origem: `resolvido_no_destino`, `ausente_no_destino`, `nao_aplicavel_no_destino` |
| `rule`, `title`, `category`, `severity` | do resultado do destino (ou da origem quando só lá) |
| `status_source`, `status_target` | pior estado do grupo em cada lado, ou null |
| `local`, `cmid_source`, `cmid_target`, `link` | link para a sala de destino (locator da Spec 001) |
| `evidence_source`, `evidence_target`, `guidance` | — |
| `key` | chave de local (research R7) |

Regras de classificação (determinísticas, nesta ordem):
1. regra do ciclo e problema no destino → `pendencia_oferta`;
2. regra do ciclo sem problema no destino → não listada (contexto da origem não é herdado);
3. problema no destino: local em ambiguidade → `indeterminado`; atividade adicional → `novo_em_atividade_adicional`;
   problema também na origem (mesma chave) → `persistente`; senão → `novo_no_destino`;
4. destino `NAO_VERIFICAVEL`/`ERRO` → `nao_verificavel_no_destino`;
5. problema só na origem: atividade sem par → `ausente_no_destino`; destino `NAO_APLICAVEL` →
   `nao_aplicavel_no_destino`; destino `CONFORME` ou sem resultado na chave → `resolvido_no_destino`; módulo em
   ambiguidade → `indeterminado`.

## Summary

`source_modules`, `target_modules`, `preserved`, `modified`, `modified_relevant`, `missing_in_target`,
`extra_in_target`, `ambiguous_groups`, `ambiguous_source_modules`, `duplicates_in_target`, `matches_by_confidence`,
`course_diffs_relevant`, `audit_counts` (por classe), `verdict`:
- `REVISAR` se houver ausente, diferença relevante, item novo/persistente `NAO_CONFORME` ou pendência da oferta;
- `CONFERIR` se só houver ambiguidades, alertas (`ATENCAO`) ou diferenças informativas;
- `SEM_DIFERENCAS_RELEVANTES` caso contrário.
O veredito descreve a comparação, não substitui o estado geral da auditoria do destino (exibido ao lado).
