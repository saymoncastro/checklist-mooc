# Resultados da linha de base (interface atual, Auditor 0.6.1)

> **A preencher depois das sessões.** Nenhum dado foi coletado até 2026-10-30. Não inclua nomes; use os códigos P01…P08.

**Sessões:** _n_ professores editores, _n_ da equipe técnica · **Período:** _datas_ · **Moderação:** _papel, sem nome_

**Ambiente** (ficha do roteiro, seção "Ambiente de referência"): Auditor _versão_ · Moodle _build_ ·
`mod_simplecertificate` _versão_ · `mod_game` _versão_ · `block_completion_progress` _versão_ · contagem do Curso B
impressa pelo script: _situação, não conformes, alertas_

> **Ambiente congelado em 2026-10-30** (`moocaudit-f0-web`, http://localhost:8102), conferido no piloto técnico:
>
> | Item | Valor |
> |---|---|
> | Auditor | 0.6.1 (version 2026103101), ZIP de `main` 0c1035f, árvore do plugin `4b004d56`, SHA-256 do ZIP `f580b51fdc6d20c2fcab2a6f8c049db86f093212fbf52c9e5515d372fdc31c31`, montado somente leitura |
> | Moodle | 4.5.15 (Build: 20261005), PHP 8.3, PostgreSQL, tema Boost, `pt_br` |
> | Plugins de terceiros | `mod_simplecertificate` 4.0.01 (2024051100) · `mod_game` 2026-10-08 (2026100800) · `block_completion_progress` 2026101200 |
> | Política | `2027-decisoes-2026-10-28` (padrão do plugin), ano da reoferta 2027 |
> | Gabarito do Curso B | Bloqueada · 25 não conformes (bloqueantes CL‑18, CL‑23, X‑CERT‑01, X‑DEP‑02, X‑DEP‑04; 9 altas, 9 médias, 2 baixas) · 5 alertas |
> | Gabarito da sala com alertas | Requer correções · 2 não conformes · 3 alertas · 1 não verificável |
>
> Contas fictícias do piloto técnico no site: `lb_p97` (salas 2 e 3). Os participantes recebem códigos a partir de P01.
> Se algum valor mudar no dia das sessões, pare e registre aqui antes de continuar.

## Critérios de sucesso (comparação com a nova interface em FR‑030)

| Critério | Linha de base | Meta da spec 006 |
|---|---|---|
| SC‑001 — mediana do tempo da T1 | _s_ | < 30 s e menor que a linha de base |
| SC‑002 — professores com T2 correta sem ajuda | _x de 5_ | ≥ 4 de 5 |
| SC‑003 — participantes com INT‑CONTAGEM | _n_ | 0 |
| SC‑004 — participantes com T4 concluída sem ajuda | _x de n_ | todos |
| SC‑005 — mediana de SEQ por tarefa (T1, T2, T3, T4) | _· · · ·_ | ≥ 6, sem piora |
| SC‑013 — professores com T6 concluída sem ajuda | _x de 5_ | ≥ 4 de 5 |
| SC‑013 — professores que apontam as duas causas na T7 | _x de 5_ | ≥ 4 de 5 |

UMUX‑Lite (mediana de U1 e U2): _·_

## Hipóteses da spec

| Hipótese | Códigos observados (n) | Confirmada? | Comentário |
|---|---|---|---|
| B‑01 topo / inventário | ROL‑INVENT | | |
| B‑02 fragmentação | FRAG‑ATIV | | |
| B‑03 linguagem técnica | INT‑TECNICO | | Termos citados: |
| B‑04 contagens | INT‑CONTAGEM | | |
| B‑05 alertas | INT‑ALERTA | | |
| B‑06 botões duplicados | BOTAO‑DUP | | |
| B‑12/B‑13 códigos na correção | CODIGO‑CORR | | |
| Visão inicial (por prioridade × por atividade) | — | | |

## Decisões para a spec 006

_Registre aqui o que muda na especificação (FR‑029) antes de `/speckit-plan`._
