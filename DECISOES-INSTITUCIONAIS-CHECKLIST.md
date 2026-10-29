# Decisões institucionais para automatizar o checklist MOOC

**Para:** equipe Cefor/CGTE responsável pela reoferta de cursos MOOC
**Contexto:** a investigação de viabilidade (`RELATORIO-INVESTIGACAO-AUDITORIA-MOOC.md`) mostrou que 17 dos 29 itens do checklist podem ser verificados automaticamente a partir do backup `.mbz`. Para transformá-los em regras sem ambiguidade foram levantadas 10 decisões.

**Situação em 2026-10-29:** decisão 11 (quem audita e aplica correções assistidas) registrada. **Em 2026-10-28:** 9 decisões registradas (abaixo, com a resposta dada); 1 pendente (nomes canônicos). As decisões já foram refletidas na política de exemplo do protótipo (`poc/mbz_inspect.py`, `DEFAULT_POLICY`, versão `2027-decisoes-2026-10-28`).

---

## Calendário da reoferta (premissa que afeta as decisões 3 e 6)

O processo de reoferta começa no ano anterior ao da oferta: em outubro/novembro de 2026 audita-se as salas que iniciarão em **01/01/2027** e terminarão em **31/12/2027**. Consequências para a automação:

- o **ano da reoferta** é, por padrão, o ano seguinte ao da auditoria (parâmetro `reoffer_year`, calculado automaticamente quando não informado);
- a data de início esperada é **01/01** e a de término **31/12** do ano da reoferta (a opção 30/06 do checklist deixa de existir);
- um backup de sala de 2026 auditado em out/2026 terá datas de 2026: isso é informação, não falha, enquanto a sala nova não for criada (ver decisão 3).

---

## 1. Tentativas dos questionários (item 13) — **DECIDIDO: mínimo de 2**

Regra: `attempts_number ≥ 2` (0 = ilimitadas também aceito); 4 tentativas é conforme. O enunciado deve declarar o mesmo número configurado.

## 2. "Soma de 100 pontos" (item 12) — **DECIDIDO: opção A**

Regra: soma dos valores máximos dos itens que valem nota = 100; agregação Natural na categoria raiz; "excluir notas vazias" desligado (o curso de 2020 tem essa opção ligada e será apontado).

## 3. Datas, categoria e inscrição (itens 5 a 8) — **DECIDIDO: informar no backup, exigir após a restauração**

Regra: no backup, as regras de datas/categoria/inscrição produzem resultado rotulado "reavaliar após a restauração"; na sala nova, são obrigatórias: início 01/01/ano, término 31/12/ano, autoinscrição ativa com as mesmas datas, categoria "Inscrições Abertas".

Pendência menor: lista do que o MOOC Admin altera automaticamente na reoferta (para suprimir alarmes redundantes).

## 4. Glossário (item 18) — **DECIDIDO: opção A, com aviso sobre glossário vazio**

Regra: glossário colaborativo é proibido; glossário usado apenas como fonte de jogos é tolerado, **mas o backup sem usuários não traz as entradas** (no Moodle, `glossary_entries` só é exportada com dados de usuário). Confirmado no backup do curso 55: o glossário tem 3 categorias e **0 entradas**, e três jogos (palavras cruzadas e duas forcas) usam essas categorias como fonte. Restaurado assim, os jogos ficam sem palavras e apresentam erro.

O protótipo agora classifica esse caso como **bloqueante** e orienta: repovoar as entradas após a restauração (importação XML do glossário) ou trocar a fonte dos jogos para o banco de questões (como já faz o quarto jogo, "Sopa de Letras").

## 5. Critério de conclusão automática por tipo (item 17) — **DECIDIDO: opção A**

Tabela adotada: página/livro/URL/arquivo = visualização; questionário/jogo/H5P = receber nota; pesquisa/escolha = envio; fórum de entrega = postagem; fóruns institucionais e rótulos = sem conclusão. Conclusão manual é sempre não conformidade; automática sem critério é alerta.

## 6. "Escolha a sua situação junto ao Ifes" (item 15) — **DECIDIDO: retirar do checklist**

A escolha foi substituída pela Pesquisa de Perfil. Ações decorrentes: remover o item 15 (parte da "Escolha") do checklist e atualizar a página "Organizando o curso" do curso "Como criar um MOOC?", que ainda instrui a mantê-la. A parte "restrições das avaliações configuradas corretamente" do item 15 permanece como verificação humana apoiada pelo inventário de restrições.

## 7. Texto do certificado (item 25) — **DECIDIDO: oficializar o texto dos cursos de 2026**

Padrão: "O Instituto Federal do Espírito Santo, por meio do {CAMPUS}, certifica que {USERNAME}, com número de documento {PROFILE_CPF}, concluiu e foi aprovado(a) no curso {COURSENAME}, realizado no período de {TIMESTART} a {TIMEEND}, com carga horária de {CARGA_HORARIA} horas." O checklist deve ser atualizado. O curso de 2020 (sem CPF) será apontado.

Pendência: fornecer as imagens oficiais (frente e verso) do certificado vigente para comparação por hash (item 24).

## 8. Bloco "Acessibilidade" (item 21) — **DECIDIDO: deve estar presente na sala**

Observação técnica: o bloco não aparece em **nenhum** dos três backups (que contêm Progresso de Conclusão, Atenção ao Prazo, Dúvidas técnicas e, no 668, Usuários online). Como o backup do curso inclui todos os blocos adicionados no contexto do curso, isso indica que o bloco "Acessibilidade" é adicionado em outro nível (site ou tema) ou que os três cursos estão sem ele. Em ambos os casos a verificação só é confiável **após a restauração**, olhando a sala no Moodle (classe C). No backup a regra reporta apenas "ausente no backup" como atenção.

Como confirmar: em uma sala, com edição ativada, verificar se o bloco "Acessibilidade" oferece a opção de exclusão no contexto do curso (bloco de curso) ou apenas de configuração herdada (bloco de site).

## 9. Jogos H5P e "atividades que valem nota" (item 16) — **DECIDIDO: opção A**

"Valem nota" = todo item com nota máxima > 0, de qualquer tipo. Consequência: o curso "Como criar um MOOC?" precisa incluir os quatro jogos H5P na restrição da Avaliação do curso e trocar sua conclusão de manual para automática por nota.

## 10. Nomes canônicos dos recursos institucionais (itens 9, 11, 16, 22, 23) — **PENDENTE**

Proposta para validação: adotar os nomes como estão na Sala Padrão Oficial e nos cursos de 2026, aceitando apenas variações de maiúsculas, acentos e espaços:

| Recurso | Nome canônico observado |
|---|---|
| Fórum de interação | Espaço de interação |
| Fórum de avisos | Avisos |
| Página de informações | Informações sobre o curso |
| Página de orientações | Como realizar este curso e obter o certificado? |
| Pesquisa inicial | Pesquisa de Perfil |
| Avaliação final | Avaliação do curso |
| Licença (rótulo e feedback) | Licença para capacitação |
| Declaração | Declaração licença para capacitação |
| Certificado | Certificado digital de conclusão |

Enquanto não houver confirmação, o protótipo usa esses nomes; cursos antigos com nomes diferentes (como o de 2020) gerarão não conformidade no item 9.

---

## 11. Quem pode auditar e aplicar correções assistidas (Auditor MOOC, specs 001 e 002) — **DECIDIDO em 2026-10-29: gerentes e professores editores**

Regra: as capacidades `local/mooc_audit:preparecorrection` (ver propostas) e `local/mooc_audit:applycorrection`
(confirmar correções) são concedidas por padrão aos arquétipos gerente e professor editor; aplicar exige também a
capacidade nativa `moodle/course:manageactivities` na atividade. Professores não editores e estudantes não as recebem.
A capacidade de auditar (`local/mooc_audit:audit`, menu *Mais > Auditoria técnica*) segue o mesmo padrão: gerente e
professor editor. A comparação entre cursos (`local/mooc_audit:compare`, contexto de sistema) continua só com gerentes.
Em sites que já tinham o plugin, o upgrade (versão 2026102905) aplica essa decisão aos papéis de professor editor que
não tenham uma permissão escolhida pelo administrador.

---

## 12. Histórico, lotes e acompanhamento (Auditor MOOC, spec 005) — **DECIDIDO em 2026-10-30 (recomendação aprovada pelo responsável do projeto)**

1. **Registro das auditorias**: só execuções solicitadas explicitamente ("Executar auditoria", "Executar novamente",
   lote, CLI com `--record`) entram no histórico. Abrir ou recarregar a página de auditoria **consulta** o resultado da
   última auditoria guardada, sem executar nem gravar nada.
2. **Retenção**: 365 dias, configurável (30–1825), mantendo sempre a última execução válida de cada sala existente. O
   histórico de uma sala removida segue o mesmo prazo.
3. **Desatualização**: aviso após 7 dias sem nova auditoria **e** sempre que houver alteração conhecida desde a
   execução — na sala (configurações ou atividades), na política ou na versão do plugin (regras). O painel e a página
   mostram sempre a data da execução e falam em "resultado da última auditoria", nunca em conformidade atual.
4. **Gerentes de categoria**: no primeiro MVP o painel e os lotes ficam com gerentes de sistema; ampliar por categoria
   será avaliado depois da homologação.
5. **Lotes**: 2 auditorias simultâneas e no máximo **100 salas por lote** até medir na homologação.
6. **Ciclo**: o ano de início da sala é o critério padrão de seleção; o lote pode informar explicitamente o ciclo (ano
   da reoferta) a aplicar na auditoria das salas.

---

## Informações adicionais necessárias

1. Novo backup íntegro do curso 668 "Como criar um MOOC?" (o arquivo atual está corrompido).
2. Lista de plugins e versões do Moodle de destino, inclusive o `availability_days` instalado e sua referência de contagem (início do curso ou data de inscrição).
3. Mais dois ou três backups de cursos recentes para calibrar falsos positivos.
4. Se existir, o projeto do curso em formato estruturado (nome, carga horária, conteúdos, professores) para automatizar o item 2.
5. Usuário de teste e credenciais de serviço do Moodle para a fase pós-restauração (itens 3, 21 e 25).
6. Imagens oficiais do certificado (frente e verso) para o item 24.
7. Lista do que o MOOC Admin altera automaticamente ao criar a sala da reoferta.
