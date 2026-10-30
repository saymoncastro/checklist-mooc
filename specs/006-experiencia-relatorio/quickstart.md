# Quickstart — validação da fase 1

**Pré‑requisitos:** um laboratório Moodle 4.5 montando o plugin deste ramo (receitas em `moodle-env/README.md`; **não**
o site de sessão `moocaudit-f0-web`, que fica congelado em 0.6.1) com os plugins de referência; e um site 5.3 para a
conferência de compatibilidade.

1. **Testes:** `vendor/bin/phpunit --testsuite local_mooc_audit_testsuite` — todos verdes; os testes de caracterização
   não são regravados (nenhuma regra muda).
2. **Cobertura de mensagens:** `presentation_catalog_test` — toda verificação dos Cursos A, B, da sala com alertas e dos
   cenários de regra tem `problem`, `why` e `fix` em `en` e `pt_br`.
3. **Jornada X‑DEP‑02** (Curso B, professor editor): a linha diz que o Questionário 1 nunca será liberado porque
   "Aprofundamento" não registra conclusão; tem duas ações ("Ativar a conclusão em Aprofundamento", "Abrir Restringir
   acesso do Questionário 1"), cada uma abrindo só a seção do formulário; o CL‑13 da mesma atividade avisa que a
   condição precisa ser resolvida antes. Depois de ativar a conclusão e "Executar novamente", a linha some.
4. **Ciclo:** X‑DEP‑04 mostra "Ciclo A" e "Ciclo B" e uma ação por atividade.
5. **Permissões:** um professor sem edição (ou um usuário só com `local/mooc_audit:audit`) vê os textos e o
   responsável, sem ações.
6. **Plugin ausente:** no site sem `mod_simplecertificate` (dist53), o topo avisa que falta o plugin do certificado e
   CL‑23, X‑CERT‑01 e CL‑11 indicam o administrador em vez de "importar".
7. **Idioma:** com a interface em inglês, as linhas aparecem em inglês; os detalhes técnicos mantêm a evidência gravada.
8. **Execução antiga:** uma execução gravada sem `occurrence` abre sem erro, com o texto gravado.
9. **Acessibilidade:** axe no relatório do Curso B sem violações novas.
