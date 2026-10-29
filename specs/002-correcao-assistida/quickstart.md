# Quickstart: verificar a correção assistida

Ambiente local isolado da Spec 002 (receita em `moodle-env/README.md`, seção "Ambiente da spec 002"): site
`http://localhost:8093`, banco `moodle_s2`, cursos A (id 2) e B (id 3) criados por `cli/make_fixtures.php`.

## Automatizado

```bash
docker exec -w /var/www/html moocaudit-s2-web vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-s2-web php admin/tool/behat/cli/run.php --tags=@local_mooc_audit
docker exec -w /var/www/html moocaudit-s2-web /tmp/cs/vendor/bin/phpcs --standard=moodle local/mooc_audit
```

Esperado: 117 testes OK; 6 cenários Behat OK; PHPCS sem erros nem avisos.

## Manual (≈ 10 min)

1. Entrar como administrador local; abrir *Curso B > Mais > Auditoria técnica*. Conferir o botão "Ver proposta de
   correção" nas inconformidades CL-13, CL-14 e CL-17 com atividade, e a ausência dele nas demais regras.
2. CL-13 / "Questionário 1": a prévia mostra atual 1, proposto 2, simulação com as duas inconformidades CL-13
   resolvidas. Conferir no banco que nada mudou (`quiz.attempts`, `course.cacherev`, `local_mooc_audit_fix` vazio).
   Confirmar: mensagem "Correção aplicada e confirmada"; tela de edição do questionário mostra 2 tentativas.
3. CL-14 / "Questionário 1": prévia bloqueada pelo CL-07 (término do curso fora da política). Ajustar o término do
   curso para 31/12 23:59 do ano da reoferta; reabrir: executável. Antes de confirmar, renomear o questionário em outra
   aba; confirmar → "O curso mudou desde a prévia", nada gravado. "Gerar nova análise" → confirmar → encerramento =
   término do curso e evento "close" no calendário.
4. CL-14 / jogo e CL-17 / "Espaço de interação": propostas orientadas, sem botão de confirmar, com link para a tela
   de edição (seção de conclusão no CL-17).
5. Questionário com tentativa de estudante (não prévia): proposta CL-13 com impedimento "já tem 1 tentativa(s) de
   estudantes".
6. Professor não editor com `preparecorrection` (concedida manualmente): vê a proposta, não vê "Confirmar correção".
   Professor editor com `audit` concedida: confirma a correção.
7. *Administração > Relatórios > Logs*: evento "Correção assistida aplicada" por correção, com regra, campo, valor
   anterior/novo e resultado da reauditoria.
