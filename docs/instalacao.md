# Instalação, atualização e configuração do Auditor MOOC

Para administradores Moodle. Os procedimentos abaixo foram executados em 2026-10-30 em sites **limpos** (núcleo do
Moodle sem nenhum plugin adicional, banco novo, depuração de desenvolvedor ligada); as evidências estão em
[prontidao-distribuicao/](prontidao-distribuicao/README.md).

## Requisitos

| Item | Requisito | Testado |
|---|---|---|
| Moodle | 4.5 ou posterior (`requires` 2024100700); faixa declarada 4.5–5.3 | 4.5.15 e 5.3+ (build 20261030) |
| PHP | o do Moodle | 8.3 |
| Banco | PostgreSQL; MySQL/MariaDB **não testados** | PostgreSQL 17 |
| Outros plugins | nenhum obrigatório | — |
| Cron | necessário para auditorias em lote e pedidos pela API | — |
| Composer, Node, scripts do repositório | **não** são necessários para instalar ou usar | — |

Quando instalados, `mod_simplecertificate`, `mod_game`, `mod_hvp` e `block_completion_progress` também são lidos. A
política padrão (do Cefor/Ifes) **exige** o certificado `mod_simplecertificate` e o bloco Progresso de Conclusão; num
site sem eles, toda sala aparece como *Bloqueada* até a política ser ajustada (ver *Configuração*).

## Obter o pacote

O pacote é um ZIP com uma única pasta, `mooc_audit/`, e o nome `local_mooc_audit-<versão>-<build>.zip`.

- **Gerar a partir do repositório** (Git 2.42 ou posterior):

  ```bash
  tools/package-plugin.sh            # commit atual → dist/
  tools/package-plugin.sh v0.6.1     # uma tag ou um commit específico
  ```

  O script empacota só arquivos versionados daquele commit (o mesmo commit gera sempre o mesmo ZIP), deixa de fora os
  scripts de laboratório `cli/make_*.php`, confere a estrutura e grava o SHA-256 em `<zip>.sha256`.
- **Artefato da CI:** cada execução do workflow *Moodle Plugin CI* guarda o ZIP testado como artefato
  `local_mooc_audit-zip`.
- **Não use** o botão *Code > Download ZIP* do GitHub. Ele traz o projeto inteiro (`checklist-mooc-main/` com
  documentação, especificações e o plugin em `plugin/local_mooc_audit`); o instalador do Moodle responde *Unable to detect
  the plugin type* e, forçando o tipo *local*, volta ao formulário sem instalar (teste em inglês).

## Instalar pela interface (ZIP)

1. *Administração do site > Plugins > Instalar plugins*.
2. Envie o ZIP em *Pacote ZIP* e clique em *Instalar plugin do arquivo ZIP*.
3. A validação deve terminar em *Validation successful, installation can continue*. O único aviso esperado é o
   nível de maturidade (ALPHA).
   Clique em *Continuar*.
4. Na verificação de plugins, *Auditoria MOOC* (`/local/mooc_audit`) aparece como "A ser instalado". Clique em
   *Atualizar banco de dados do Moodle agora* e depois em *Continuar*.
5. A tela *Novas configurações* mostra a política institucional e os parâmetros de histórico, lote e API; salve
   (pode ajustar depois).

A instalação cria as tabelas `local_mooc_audit_run`, `_policy`, `_batch` e `_fix`, sete capacidades, a tarefa agendada
de retenção do histórico e o serviço externo "Auditor MOOC" (desabilitado).

O servidor web precisa poder escrever em `local/`. Se não puder (ou se `$CFG->disableupdateautodeploy` estiver ligado),
use a instalação manual.

## Instalar manualmente

```bash
# Moodle 4.5
unzip local_mooc_audit-0.6.1-2026103101.zip -d <moodle>/local/
# Moodle 5.1 ou posterior (código web em public/)
unzip local_mooc_audit-0.6.1-2026103101.zip -d <moodle>/public/local/

php <moodle>/admin/cli/upgrade.php --non-interactive   # ou Administração do site > Notificações
```

O resultado deve ser a pasta `local/mooc_audit` (ou `public/local/mooc_audit`) com `version.php` na raiz.

## Atualizar

Instale o ZIP novo da mesma forma: o instalador avisa *Target location already exists and will be removed* e substitui a pasta;
depois rode a atualização do banco. Manualmente: troque a pasta e rode `admin/cli/upgrade.php`. Testado de 0.3.0 para
0.6.0: os passos de banco 2026103000, 2026103001 e 2026103100 rodaram, e a trilha de correções, a política
personalizada e as permissões foram preservadas. Faça backup do banco antes, como em qualquer atualização.

## Desinstalar

*Administração do site > Plugins > Visão geral dos plugins > Auditoria MOOC > Desinstalar*. Apaga histórico, lotes,
políticas guardadas, trilha de correções, configurações, capacidades, tarefas e o serviço externo; o Moodle oferece
remover a pasta em seguida. **As alterações já aplicadas às salas pela correção assistida permanecem.** Exporte o que
precisar antes (relatórios em JSON, Privacy API).

## Configuração

### Política institucional

*Administração do site > Plugins > Plugins locais > Auditoria MOOC > Política institucional (JSON)*. A página lista
todas as chaves aceitas, com tipo e valor padrão. Só as chaves informadas sobrescrevem o padrão; JSON inválido é
recusado ao salvar; uma chave com tipo errado é avisada no relatório e o padrão é usado. Exemplo para outro site:

```json
{"target_category_name": "Cursos abertos", "min_quiz_attempts": 3}
```

O ano da reoferta, quando não informado, é o ano seguinte ao da execução. As regras de data usam o **fuso horário do
site**: confira-o em *Administração do site > Localização*.

### Demais parâmetros

| Configuração | Padrão | Faixa |
|---|---|---|
| Retenção do histórico (dias) | 365 | 30–1825 |
| Desatualizada após (dias) | 7 | 1–365 |
| Auditorias simultâneas em segundo plano | 2 | 1–10 (`$CFG->task_concurrency_limit` tem precedência) |
| Tamanho máximo do lote | 100 | 1–2000 |
| Auditorias pendentes pela API por conta | 100 | 1–2000 |

### Permissões

| Capacidade | Contexto | Papéis padrão | Para |
|---|---|---|---|
| `local/mooc_audit:audit` | curso | gerente, professor editor | executar a auditoria, ver relatório e histórico |
| `local/mooc_audit:preparecorrection` | curso | gerente, professor editor | ver propostas de correção |
| `local/mooc_audit:applycorrection` | curso | gerente, professor editor | confirmar correções (exige também `moodle/course:manageactivities`; `RISK_CONFIG`) |
| `local/mooc_audit:compare` | sistema | gerente | comparar ofertas (exige `audit` nas duas salas) |
| `local/mooc_audit:viewdashboard` | sistema | gerente | painel e lotes (leitura) |
| `local/mooc_audit:batchaudit` | sistema | gerente | solicitar lotes |
| `local/mooc_audit:api` | sistema | nenhum | usar a API |

Professores não editores e estudantes não recebem nenhuma capacidade (decisão institucional 11). Um gerente só de
categoria não vê o painel, porque as capacidades do painel são de sistema.

### Cron e API

Lotes e pedidos pela API viram tarefas ad hoc `\local_mooc_audit\task\audit_course`; sem cron ficam "Na fila ou em
execução". Para processar à mão: `php admin/cli/adhoc_task.php --execute`. A API exige habilitar Web Services, o
protocolo REST, o serviço "Auditor MOOC" e uma conta de integração: [API.md](../plugin/local_mooc_audit/API.md).

## Conferir a instalação

1. *Administração do site > Plugins > Visão geral dos plugins*: *Auditoria MOOC*, versão 2026103101.
2. Numa sala, como gerente ou professor editor: *Mais > Auditoria técnica* → "Esta sala ainda não foi auditada…" →
   *Executar auditoria* mostra o relatório.
3. *Administração do site > Cursos*: entradas *Comparar ofertas* e *Acompanhamento das auditorias*.
4. Opcional, pela linha de comando: `php local/mooc_audit/cli/audit.php --courseid=<id>`.

Uso e interpretação do relatório: [guia-do-usuario.md](guia-do-usuario.md).
