# Prontidão para distribuição — produto, instalação, repositório e Marketplace

**Data:** 2026-10-30 · **Ramo:** `claude/product-distribution-audit-4b6c94` (a partir de `main` 2ac2f1a, plugin 0.6.0)
· **Escopo:** o plugin `local_mooc_audit` como produto que um terceiro baixa, instala e usa — não a qualidade
interna do motor, já revisada em [../revisao-geral/](../revisao-geral/README.md).

Documento único da auditoria. As capturas estão em [capturas/](capturas/); as mudanças feitas estão listadas no fim.

## A. Relatório executivo

### Veredito

**Estágio: plugin instalável para homologação.** Ao começar esta auditoria o projeto **não tinha pacote
instalável**: o único ZIP que um administrador conseguia obter (o *Download ZIP* do GitHub) é recusado pelo instalador
do Moodle. O código em si estava pronto para ser instalado: empacotado corretamente, instalou pela interface em Moodle
4.5 e 5.3 limpos, sem nenhum aviso com depuração de desenvolvedor, e funcionou de ponta a ponta. O bloqueio era de
distribuição, não de engenharia, e foi resolvido nesta entrega com um empacotamento reprodutível testado pela CI.

Não é ainda **candidato à distribuição pública** porque: (1) não existe release publicada que um administrador possa
baixar sem ferramentas de desenvolvimento; (2) a política padrão é a do Cefor/Ifes, e num site qualquer até o curso de
referência sai *Bloqueado*; (3) só PostgreSQL foi testado; (4) textos de evidência fixos em português e a pasta
`lang/pt_br` dentro do pacote contrariam as regras de idioma do diretório de plugins; (5) ninguém de fora do projeto
instalou ou usou o plugin.

### O que já funciona (comprovado nesta auditoria)

| Verificação | Resultado | Evidência |
|---|---|---|
| Instalação pelo ZIP na interface, Moodle 4.5.15 limpo, PostgreSQL 17, `DEBUG_DEVELOPER` | validação OK (único aviso: maturidade ALPHA), banco criado, nenhum aviso | capturas 03–06 |
| Instalação manual (descompactar em `public/local/` + `admin/cli/upgrade.php`), Moodle 5.3+ limpo | OK, 6 configurações com padrão, relatório idêntico em estrutura | captura 17 |
| Objetos criados | 4 tabelas, 7 capacidades com os arquétipos esperados, tarefa agendada, serviço externo desabilitado | consulta ao banco |
| Descoberta | entrada *Technical audit* no menu *More* do curso para professor editor; *Compare offers* e *Audit follow-up* em *Site administration > Courses* | capturas 07, 14 |
| Funcionalidade principal como professor editor (sem ser administrador) | 1ª auditoria do curso B: 29 inconformidades em 4 gravidades, links de correção | capturas 08–10 |
| Correção assistida | prévia, simulação, confirmação, reauditoria registrada (execução 2, origem "reauditoria após correção") | capturas 11–13 |
| Lote pela fila de tarefas e linha de comando | `cli/batch.php` + `admin/cli/adhoc_task.php`: execução registrada; `cli/audit.php` OK | saída da CLI |
| Atualização 0.3.0 → 0.6.0 pela interface (ZIP sobre plugin existente) | passos 2026103000/03001/03100 OK; trilha de correção, política personalizada e permissões preservadas | captura 16 |
| Desinstalação pela interface | remove tabelas, configurações, capacidades, tarefas, serviço e a pasta; dados do curso intactos | captura 15 |
| Logs do servidor nos dois sites | nenhum *PHP Warning/Notice/Deprecated* | `docker logs` |
| CI existente (`moodle-plugin-ci`, 4.5/PHP 8.3/PostgreSQL) | verde nas últimas 8 execuções | GitHub Actions |

### Principais lacunas

1. **Distribuição (bloqueio na entrada, resolvido):** sem ZIP, sem processo de empacotamento, plugin aninhado em
   `plugin/local_mooc_audit` num repositório de projeto (`checklist-mooc`).
2. **Publicação (aberto):** sem tag, sem release, repositório sem `LICENSE` (o GitHub não detectava licença; os
   arquivos PHP já declaravam GPL v3+), sem descrição.
3. **Produto para terceiros (aberto):** política, nomes canônicos e orientações escritos para o Cefor/Ifes ("Sala
   Padrão", "calendário do Cefor", "curso 670", URLs do `mooc.cefor.ifes.edu.br` na política padrão).
4. **Apresentação (resolvido):** README principal e README do plugin eram crônica de desenvolvimento (34 KB, estados
   por versão, estrutura de classes) e a raiz tinha quatro relatórios longos, três deles superados.
5. **Internacionalização (aberto):** interface em inglês com evidências em português; códigos internos visíveis
   (`NAO_CONFORME → CONFORME` no resultado da correção); CLI em português.
6. **Metadado inconsistente (resolvido):** relatório, histórico e API mostravam "0.5.0" com o plugin em 0.6.0.

### Riscos para adoção por terceiros

- Um administrador de outra instituição instala sem problema, mas verá todas as salas *Bloqueadas* (certificado
  `simplecertificate`, bloco Progresso de Conclusão, categoria "Inscrições Abertas") até reescrever a política em JSON.
  Sem um perfil neutro, o primeiro contato sugere que o plugin está errado.
- MySQL/MariaDB sem nenhum teste: falhas de SQL só aparecerão no site de quem adotar.
- A desinstalação apaga a trilha de correções (quem mudou o quê); só o aviso genérico do Moodle alerta.
- Maturidade ALPHA e ausência de uso em produção.

### Grau de preparação para o Moodle Marketplace

**Não pronto para submissão.** Requisitos que hoje reprovariam ou atrasariam a revisão: idioma (só inglês no pacote;
strings em `get_string`), MySQL não testado, descrição e documentação em inglês incompletas (`API.md` em português).
Detalhe na seção D. As diretrizes de submissão do Marketplace exigem login (Atlassian) e não puderam ser lidas; a
avaliação usa o FAQ oficial (editado em 2026-08-13) e o checklist legado do diretório de plugins, que o próprio Moodle
marca como referência histórica.

### Recomendação

1. Commitar estas mudanças, criar a tag `v0.6.1` e uma release **privada ou de pré-lançamento** com o ZIP gerado pela
   CI — decisão do responsável (nenhuma publicação foi feita aqui).
2. Homologação externa: entregar o ZIP e [../instalacao.md](../instalacao.md) a um administrador e a um professor que
   não participaram do projeto, com os cursos de teste A/B (backups já gerados nesta auditoria), e observar.
3. Só depois decidir entre a spec 006 (experiência do relatório) e uma spec de "política portátil" (ver plano, seção E).

## B. Matriz de achados

P0 impede instalação ou funcionamento essencial · P1 impede adoção segura por terceiros · P2 dificulta compreensão,
operação ou manutenção · P3 aperfeiçoamento. Esforço: P (horas), M (dias), G (spec).

| ID | Pri. | Achado | Evidência | Impacto | Correção | Esforço | Situação |
|---|---|---|---|---|---|---|---|
| D-01 | **P0** | Nenhum pacote instalável; o ZIP do GitHub é recusado | capturas 01–02: *Unable to detect the plugin type*; forçando *local*, componente `local_checklist-mooc-main` e volta ao formulário sem instalar | administrador não consegue instalar sem copiar pastas à mão | `tools/package-plugin.sh` (ZIP `mooc_audit/`, reprodutível, conferido) e CI que testa e guarda o ZIP | P | **Resolvido** |
| D-02 | P1 | Sem release/tag; ZIP só obtido com Git 2.42 ou pela CI | `gh release list` vazio, nenhuma tag | o administrador depende de quem desenvolve | tag `v0.6.1` + release com o ZIP da CI | P | Aberto (decisão do responsável) |
| D-03 | P1 | Repositório sem `LICENSE` | `gh repo view`: `licenseInfo: null` | repositório público sem licença explícita | `LICENSE` (texto da GPL v3, a mesma declarada nos arquivos) | P | **Resolvido**; confirmar autorização institucional |
| D-04 | P1 | Política padrão do Cefor/Ifes faz qualquer sala de outro site sair *Bloqueada* | curso A (referência) num Moodle sem `simplecertificate` nem bloco Progresso: *Blocked*, CL-23 e X-CERT-01 bloqueantes, CL-21 | terceiros interpretam como defeito | documentado (README, instalação); perfil de política neutro ou assistente de política | G | Aberto → nova spec |
| D-05 | P1 | Só PostgreSQL testado | CI e laboratórios só PostgreSQL | risco de falha em MySQL/MariaDB (checklist legado: obrigatório) | entrada MariaDB na matriz da CI; corrigir o que aparecer | M | Aberto |
| D-06 | P1 (Marketplace) | `lang/pt_br` dentro do pacote | `lang/pt_br/local_mooc_audit.php` (638 strings) | checklist legado: "just the English strings should ship"; traduções via AMOS | manter para distribuição institucional; remover do pacote do Marketplace e enviar ao AMOS após aprovação | P | Aberto (decisão) |
| D-07 | P1 (Marketplace) | Evidências e locais das regras fixos em português no código | relatório em inglês com "exige conclusão de…", "Configurações do curso > Data de início" | interface mista; contraria "use `get_string()`" | mover para `lang/` mantendo o contrato JSON (aditivo) | G | Aberto → spec (006 ou própria) |
| D-08 | P2 | Versão exibida "0.5.0" com o plugin em 0.6.0 | relatório, histórico e CLI mostravam `local_mooc_audit 0.5.0` | confunde suporte e histórico | `engine::VERSION` = release, com teste que impede nova divergência; versão 0.6.1 | P | **Resolvido** |
| D-09 | P2 | README orientado ao desenvolvimento; raiz com 4 relatórios longos | README antigo: linha do tempo de specs; README do plugin: 34 KB | o visitante não descobre o que é, como instalar, onde fica | README de produto, README do plugin em inglês, `CHANGES.md`, guias de instalação e uso, relatórios superados em `docs/historico/` | P | **Resolvido** |
| D-10 | P2 | Jargão interno e códigos na interface | "decision 12.5" nas configurações; "Standard Room", "Cefor calendar", "course 670" nas orientações; `NAO_CONFORME → CONFORME` na correção; CLI em português ("lote 1: 1 enfileiradas") | sobrecarga para quem não participou do projeto | revisão de redação (escopo da spec 006) | M | Aberto |
| D-11 | P2 | Moodle 5.3 declarado e testado só em laboratório; fora da CI | matriz da CI só `MOODLE_405_STABLE` | regressões no 5.x passam despercebidas | entrada `MOODLE_503_STABLE` na CI | P | Aberto |
| D-12 | P2 | `API.md` só em português | arquivo no pacote | integradores externos e Marketplace | versão em inglês quando a API sair de experimental | M | Aberto |
| D-13 | P2 | Ambiente de desenvolvimento pesado para um colaborador externo | `moodle-env/README.md` com 7 ambientes por spec | contribuição exige conhecimento informal | um *quickstart* único (moodle-docker padrão + plugin montado) | P | Aberto |
| D-14 | P3 | Nome do repositório fora da convenção `moodle-local_mooc_audit` e plugin em subpasta | `github.com/saymoncastro/checklist-mooc` | só recomendação; o ZIP resolve a instalação | manter o monorrepositório (SDD + plugin) ou espelhar `plugin/local_mooc_audit` num repositório de distribuição | M | Decisão futura |
| D-15 | P3 | Regras de data dependem do fuso do site | mesmo curso B: 6 alertas no site em America/Sao_Paulo, 5 no site sem fuso configurado | resultados diferentes entre sites | documentado em [../instalacao.md](../instalacao.md) | P | **Resolvido (documentação)** |
| D-16 | P3 | Tabela *Resultados por estado* soma resultados ao lado do total de regras ("29 … 21 … 29") | captura 10 | leitura ambígua | escopo da spec 006 | P | Aberto |
| D-17 | P3 | Repositório sem descrição, tópicos e modelos de issue | `gh repo view` | descoberta e triagem | preencher no GitHub | P | Aberto |

Não foram achados: erros de PHP, falha de instalação, atualização ou desinstalação, dependência de Composer, MOOC Admin,
`.mbz` ou MUC em tempo de execução, nem arquivos de desenvolvimento no pacote além dos testes (que a convenção Moodle
inclui).

## C. Pacote demonstrável

| Item | Valor |
|---|---|
| Artefato | `local_mooc_audit-0.6.1-2026103101.zip` (raiz única `mooc_audit/`) |
| Origem | commit deste ramo com as mudanças desta auditoria; o SHA-256 vai em `<zip>.sha256` e é idêntico em builds do mesmo commit |
| Gerar | `tools/package-plugin.sh [ref] [pasta]` (Git ≥ 2.42) ou artefato `local_mooc_audit-zip` da CI |
| Inclui | tudo o que está versionado em `plugin/local_mooc_audit/` naquele commit: código, `db/`, `lang/en` e `lang/pt_br`, `templates/`, `schema/`, `tests/` (PHPUnit, Behat, gerador), `README.md`, `CHANGES.md`, `API.md` |
| Exclui | `cli/make_fixtures.php`, `cli/make_import.php`, `cli/make_scenarios.php` (criam cursos de teste; já só rodavam em site local com depuração de desenvolvedor) e tudo fora da pasta do plugin |
| Conferências do script | raiz única; `version.php`, `lib.php`, `db/access.php`, `lang/en`, `classes/privacy/provider.php` presentes; sem `.git`, `.github`, `vendor`, `node_modules`, `.mbz`, `.xlsx`, `.zip`; componente `local_mooc_audit` |
| Testes de instalação | 0.6.0 (antes das mudanças desta entrega): ZIP pela interface no 4.5, manual no 5.3, atualização 0.3.0→0.6.0, desinstalação. 0.6.1: ver *Verificação final* |

## D. Requisitos de publicação

Fontes: FAQ de plugins do Moodle (docs.moodle.org/501, editado em 2026-08-13: envio do ZIP com nome Frankenstyle
válido, detalhes, documentação, instruções de instalação, capturas, verificações automáticas de arquivo e
`moodle-plugin-ci`, revisão por ticket); checklist legado do diretório de plugins (moodledev.io, marcado como legado).
Obrigatório = exigido pelo FAQ ou "must"/bloqueio no checklist; recomendado = "should".

| Requisito | Tipo | Situação | Evidência / falta |
|---|---|---|---|
| ZIP com nome Frankenstyle válido e pasta na raiz | obrigatório | **Atendido e comprovado** | validação do instalador nos dois sites |
| Passa nas verificações do `moodle-plugin-ci` | obrigatório | **Atendido e comprovado** (4.5/PostgreSQL) | CI verde; agora sobre o ZIP |
| Instala pela interface sem passos externos (sem Composer) | obrigatório | **Atendido e comprovado** | capturas 03–06 |
| Sem warnings/notices com depuração de desenvolvedor | obrigatório | **Atendido e comprovado** | páginas e logs dos dois sites |
| Suporta ao menos uma versão mantida do Moodle | obrigatório | **Atendido e comprovado** | 4.5 LTS e 5.3 LTS |
| Funciona em MySQL e PostgreSQL | obrigatório (exceções justificadas) | **Parcial** | só PostgreSQL (D-05) |
| GPL v3 ou posterior | obrigatório | **Atendido** | cabeçalhos em todos os arquivos; `LICENSE` na raiz do repositório |
| Dependências declaradas em `version.php` e documentadas | obrigatório | **Atendido** | nenhuma obrigatória; opcionais documentadas |
| Só strings em inglês no pacote | obrigatório (checklist) | **Não atendido** | `lang/pt_br` (D-06) |
| Todo texto por `get_string()` | obrigatório (checklist) | **Parcial** | evidências fixas em português (D-07) |
| Privacy API e descrição dos dados pessoais | obrigatório | **Atendido** | `classes/privacy/provider.php`, testes; seção *Privacy* do README |
| Segurança (`require_login`, capacidades, `sesskey`, parâmetros tipados, SQL com marcadores) | obrigatório | **Atendido** (revisão de 2026-10-29) | [../revisao-geral/A-CONFORMIDADE-TECNICA-MOODLE.md](../revisao-geral/A-CONFORMIDADE-TECNICA-MOODLE.md); não reauditado aqui |
| Prefixo Frankenstyle, sem colisões | obrigatório | **Atendido** | classes com namespace; `local_mooc_audit_*` em funções globais |
| Rastreador de issues público | bloqueio se ausente | **Atendido** | issues habilitadas no GitHub |
| Repositório público | recomendado | **Atendido** | `github.com/saymoncastro/checklist-mooc` |
| Nome `moodle-local_mooc_audit` | recomendado | **Não atendido** | D-14 |
| Descrição curta e longa em inglês | recomendado (FAQ: "detalhes") | **Parcial** | base no README do plugin; texto do Marketplace não redigido |
| Documentação e instruções de instalação | obrigatório (FAQ) | **Atendido** | README do plugin (inglês), [../instalacao.md](../instalacao.md) |
| Capturas de tela reais | obrigatório (FAQ) | **Atendido** | [capturas/](capturas/) (inglês, Moodle 4.5) |
| Release identificável por versão e tag | recomendado | **Não atendido** | D-02 |
| Estilo de código Moodle | recomendado | **Atendido** | PHPCS `moodle` sem avisos (CI) |
| Diretrizes atuais do Marketplace | — | **Não verificável** | página no Confluence exige login |

## E. Cenários de aceitação

Executados por este agente seguindo só o pacote e a documentação pública. **Não substituem** o teste com pessoas de fora
do projeto, que continua pendente.

| Cenário | Passos | Resultado | Dificuldades | Correções |
|---|---|---|---|---|
| 1. Administrador externo | baixar ZIP do GitHub → falha; gerar pacote → instalar pela interface → configurações → localizar menus | instalou no 4.5 e no 5.3 sem consultar SDD | obter o ZIP; entender que a política padrão é de outra instituição | empacotamento, README, guia de instalação (feito); release (D-02); política (D-04) |
| 2. Operador externo (professor editor) | curso B → *More > Technical audit* → *Run audit* → ler → abrir proposta → confirmar → histórico | encontrou, executou e corrigiu; reauditoria registrada | menu *More* (não está à vista); evidências em português; contagens da tabela por estado; códigos crus na correção | guia do usuário (feito); D-07, D-10, D-16 → spec 006 |
| 3. Desenvolvedor Moodle | ler README → arquitetura → gerar ZIP → CI → rodar PHPUnit no laboratório | código identificável; 244 testes verdes; pacote reprodutível | montar o ambiente exige ler receitas por spec | guia de desenvolvimento (feito); *quickstart* (D-13) |

## F. Plano de evolução

| Ordem | Ação | Resolve | Precisa de spec? |
|---|---|---|---|
| 1 | Commit, tag `v0.6.1`, release de pré-lançamento com o ZIP da CI; descrição e tópicos do repositório | D-02, D-17 | não |
| 2 | CI com `MOODLE_503_STABLE` e MariaDB; corrigir o que falhar | D-05, D-11 | não |
| 3 | Homologação externa com ZIP + guias + backups dos cursos A/B; registrar dificuldades | cenários 1–2 por pessoas reais | não (roteiro da spec 006 pode ser reaproveitado) |
| 4 | Política portátil: perfil neutro ou assistente, padrões do Cefor como perfil opcional | D-04 | **sim** |
| 5 | Spec 006 (experiência do relatório) incluindo redação sem jargão, evidências em `lang/`, códigos traduzidos, tabela por estado | D-07, D-10, D-16 | **sim** (já especificada; incluir D-07) |
| 6 | Preparação do pacote do Marketplace: só `lang/en`, `API.md` em inglês, descrição, maturidade BETA após homologação, decisão sobre repositório de distribuição | D-06, D-12, D-14 | não |

## G. Respostas finais

1. **Um desenvolvedor Moodle externo entenderia hoje o que o plugin faz pelo GitHub?** Antes desta entrega, com
   esforço: o README listava relatórios e specs. Com o novo README, sim: a primeira tela diz o que é, para quem, as
   funcionalidades e onde ficam.
2. **Conseguiria instalar sem falar com quem desenvolveu?** Antes, não pelo caminho padrão (ZIP do GitHub recusado). Agora
   sim, se tiver Git 2.42 para gerar o pacote ou acesso ao artefato da CI; sem release publicada ainda é uma barreira.
3. **Após instalar, encontraria e experimentaria as funções principais?** Sim: a entrada no menu do curso, o relatório,
   a correção e o histórico funcionaram para um professor editor num site limpo. Mas, fora do Cefor/Ifes, verá quase
   tudo *Bloqueado* até ajustar a política.
4. **O repositório está organizado como produto ou como ambiente SDD?** Estava essencialmente como ambiente SDD.
   Agora há separação explícita: pacote gerado só da pasta do plugin, documentação de produto na frente, SDD e histórico
   indicados como tal. Continua sendo um monorrepositório de projeto.
5. **O que falta para uma primeira versão pública confiável?** Release com tag e ZIP; teste em MariaDB e CI no 5.3;
   homologação com pessoas externas; política utilizável fora do Cefor/Ifes; e, para o Marketplace, pacote só em inglês
   com evidências traduzíveis.

## Verificação final (0.6.1)

| Verificação | Resultado |
|---|---|
| Pacote | `local_mooc_audit-0.6.1-2026103101.zip`, 212 arquivos, gerado por `tools/package-plugin.sh` de um commit de pré-visualização não referenciado (454f5f7) com o conteúdo desta entrega; o ZIP definitivo sai do commit que for criado — mesmo conteúdo, SHA-256 diferente só pela data do commit |
| Instalação nova pela interface, Moodle 5.3+ limpo (depois de desinstalar a 0.6.0) | validação OK, banco criado, configurações salvas; relatório mostra `local_mooc_audit 0.6.1 (contract 1.0)` (captura 18) |
| Atualização manual 0.6.0 → 0.6.1, Moodle 4.5 com histórico e trilha | versão 2026103101; política personalizada, execução e correção preservadas; nova auditoria compara com a anterior e anota a troca de versão |
| Logs do servidor nos dois sites | zero *PHP Warning/Notice/Deprecated/Fatal* |
| PHPUnit (laboratório 4.5, PostgreSQL) | 244 testes, 3632 asserções, OK (inclui o teste novo de versão) |
| PHPCS `moodle` nos arquivos PHP alterados | sem avisos |
| Behat (13 cenários, com axe) | 13 cenários, 213 passos, OK |
| CI no GitHub com o ZIP | não executada: depende de commit e push |

## Mudanças feitas nesta entrega

- `tools/package-plugin.sh`: gera e confere o ZIP reprodutível; CI passa a testar o ZIP e guardá-lo como artefato.
- Plugin 0.6.1 (2026103101): `engine::VERSION` acompanha a release, com teste; sem mudança de regras, banco ou
  contrato. README do plugin em inglês para administradores; `CHANGES.md`.
- `LICENSE` (GPL v3); `.gitignore` com `dist/`.
- Documentação: README de produto; [../instalacao.md](../instalacao.md); [../guia-do-usuario.md](../guia-do-usuario.md)
  (catálogo funcional); [../desenvolvimento/README.md](../desenvolvimento/README.md); o antigo README do plugin virou
  [../desenvolvimento/arquitetura-plugin.md](../desenvolvimento/arquitetura-plugin.md) (`git mv`, histórico preservado);
  os três relatórios superados foram para [../historico/](../historico/) (`git mv`). Nenhuma especificação ou auditoria
  foi apagada; `DECISOES-INSTITUCIONAIS-CHECKLIST.md` continua na raiz porque a constituição o referencia ali.

## Ambientes usados

Contêineres descartáveis criados para esta auditoria, sem dados reais: `moocaudit-dist-web` (Moodle 4.5.15, porta 8099,
banco `moodle_dist`) e `moocaudit-dist53-web` (Moodle 5.3+, porta 8101, banco `moodle_dist53`), ambos a partir do
núcleo do Moodle sem plugins adicionais (`git archive` das árvores de `moodle-local/`). Cursos A e B levados por
backup/restauração do laboratório da spec 005. Remover:

```bash
docker rm -f moocaudit-dist-web moocaudit-dist53-web
docker network disconnect moocaudit_dist moocaudit-db-1 && docker network rm moocaudit_dist
docker network disconnect moocaudit_dist53 moocaudit-db-1 && docker network rm moocaudit_dist53
docker exec moocaudit-db-1 dropdb -U moodle moodle_dist && docker exec moocaudit-db-1 dropdb -U moodle moodle_dist53
```
