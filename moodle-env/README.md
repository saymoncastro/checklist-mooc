# Ambiente Moodle 4.5 local para a prova de conceito (reproduzível)

Usado em 2026-10-28. Versões exatas: **Moodle 4.5.15 (Build 20261005), branch `MOODLE_405_STABLE`, commit
215f4438**; PHP 8.3.35 (imagem `moodlehq/moodle-php-apache:8.3`); PostgreSQL 17.11 (`postgres:17`);
`moodlehq/moodle-docker` commit f4c2324 (2026-07-30); Docker 29.9 via Colima (4 CPU, 6 GB, macOS arm64).

Os diretórios de código ficam em `moodle-local/` (ignorado pelo git). Os arquivos desta pasta são os únicos
específicos do ambiente.

## Passos

```bash
PROJECT_DIR=$(pwd)                                   # raiz do repositório checklist-mooc
mkdir -p moodle-local && cd moodle-local
git clone --depth 1 --branch MOODLE_405_STABLE https://github.com/moodle/moodle.git moodle
git clone https://github.com/moodlehq/moodle-docker.git moodle-docker
envsubst < ../moodle-env/env.sh > env.sh                # ou edite os caminhos à mão
envsubst < ../moodle-env/local.yml > moodle-docker/local.yml
cp ../moodle-env/mphp.sh . && chmod +x mphp.sh
cp moodle-docker/config.docker-template.php moodle/config.php
source env.sh
moodle-docker/bin/moodle-docker-compose up -d db webserver
moodle-docker/bin/moodle-docker-wait-for-db
./mphp.sh admin/cli/install_database.php --agree-license --fullname="MOOC Audit Lab (local)" \
  --shortname=moocaudit --adminuser=admin --adminpass='Admin!2026local' --adminemail=admin@example.com --lang=pt_br
./mphp.sh admin/cli/cfg.php --name=timezone --set=America/Sao_Paulo
./mphp.sh admin/cli/cfg.php --name=forcetimezone --set=America/Sao_Paulo
```

`local.yml` monta `plugin/local_mooc_audit` em `/var/www/html/local/mooc_audit` e a raiz do projeto em `/backups`
(somente leitura) para restaurar os `.mbz`.

## Plugins de terceiros instalados (copiados para a árvore do Moodle)

| Plugin | Fonte | Versão instalada | Observação |
|---|---|---|---|
| mod_simplecertificate | github.com/davidherney/moodle-mod_simplecertificate (master) | 2024051100 | produção usa 2024051105; moodle.org exige login para baixar a 4.0.7 |
| mod_game | github.com/bdaloukas/moodle-mod_game (master) | 2026100800 | igual à geração dos backups (2026051500 → mais nova) |
| mod_hvp | github.com/h5p/moodle-mod_hvp (master) + submódulos `library` (branch moodle), `editor` e `reporting` (stable) | 2026090101 | |
| block_completion_progress | github.com/deraadt/moodle-block_completion_progress (master) | 2026101200 | suporta 4.5 |
| availability_days | github.com/willianmano/moodle-availability_days (fork de 2017 do plugin de Valery Fremaux) | 2016060100 | única cópia pública; instalou e avalia condições `days`; a versão institucional é desconhecida |

```bash
./mphp.sh admin/cli/upgrade.php --non-interactive
```

## Restauração dos backups institucionais e cursos de teste

```bash
./mphp.sh admin/cli/restore_backup.php --file=/backups/backup-moodle2-course-55-empp-20261028-1455-nu.mbz --categoryid=1   # -> curso 2
./mphp.sh admin/cli/restore_backup.php --file=/backups/backup-moodle2-course-670-acessibilidetecnologias_2026-20261028-1501-nu.mbz --categoryid=1  # -> curso 3
./mphp.sh admin/cli/restore_backup.php --file=/backups/backup-moodle2-course-668-cc_mooc_2026-20261014-1555-nu.mbz --categoryid=1  # FALHA (pacote corrompido)
./mphp.sh local/mooc_audit/cli/make_fixtures.php --year=2027      # cursos A (referência) e B (inconsistente)
./mphp.sh local/mooc_audit/cli/make_import.php --source=<A> --year=2027   # reoferta sem MOOC Admin (importação nativa)
```

Simulação de arquivo perdido no armazenamento (curso B): remover
`/var/www/moodledata/filedir/<xx>/<yy>/<contenthash>` do arquivo `apostila.txt` (hash impresso pelo script).

## Testes

```bash
docker exec -i -w /var/www/html moocaudit-webserver-1 sh -c 'composer install && php admin/tool/phpunit/cli/init.php'
docker exec -i -w /var/www/html moocaudit-webserver-1 vendor/bin/phpunit local/mooc_audit/tests/engine_test.php
```

Site: http://localhost:8091 (admin / `Admin!2026local`, apenas local). Nenhuma credencial ou dado de produção.

## Testar código de um worktree sem tocar o laboratório

O contêiner `moocaudit-webserver-1` monta o plugin da cópia principal. Para rodar o PHPUnit sobre outra cópia (ex.: um
worktree do git) sem recriar o laboratório, use um segundo contêiner com a mesma árvore do Moodle, o plugin do worktree
montado por cima e um banco próprio:

```bash
docker exec moocaudit-db-1 createdb -U moodle moodle_wt
docker run -d --name moocaudit-wt --network moocaudit_default \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_wt -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8091 -e MOODLE_DOCKER_RUNNING=1 \
  -v "$PROJECT_DIR/moodle-local/moodle:/var/www/html" \
  -v "<worktree>/plugin/local_mooc_audit:/var/www/html/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3 tail -f /dev/null
docker exec -w /var/www/html moocaudit-wt php admin/tool/phpunit/cli/init.php
docker exec -w /var/www/html moocaudit-wt vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
```

Para **ver a página** do plugin do worktree no navegador, um segundo contêiner com Apache, porta própria (8092) e o
mesmo banco `moodle_wt` (as tabelas do site regular, prefixo `m_`, convivem com as do PHPUnit, prefixo `t_`); o
`moodledata` fica dentro do contêiner e se perde ao removê-lo:

```bash
docker run -d --name moocaudit-wt-web --network moocaudit_default -p 127.0.0.1:8092:80 \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_wt -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8092 -e MOODLE_DOCKER_RUNNING=1 \
  -v "$PROJECT_DIR/moodle-local/moodle:/var/www/html" \
  -v "<worktree>/plugin/local_mooc_audit:/var/www/html/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3
docker exec moocaudit-wt-web bash -c 'mkdir -p /var/www/moodledata && chown www-data:www-data /var/www/moodledata'
docker exec -w /var/www/html moocaudit-wt-web php admin/cli/install_database.php --agree-license \
  --fullname="Auditor MOOC (worktree)" --shortname=wt --adminpass='Admin123!' --adminemail=admin@example.com --lang=pt_br
docker exec -w /var/www/html moocaudit-wt-web php local/mooc_audit/cli/make_fixtures.php   # cursos A e B
docker exec moocaudit-wt-web chown -R www-data:www-data /var/www/moodledata
```

Site: http://localhost:8092 (admin / `Admin123!`, apenas local). Remover: `docker rm -f moocaudit-wt-web`.

Para **rodar o Behat** do plugin sem tocar o laboratório nem contêineres de outros projetos: um Selenium próprio na
rede `moocaudit_default` com o apelido `selenium`, que resolve `webserver` para o contêiner web do worktree, e o nome
`webserver` apontado para o próprio contêiner web (o `behat_wwwroot` do `config.php` é `http://webserver`):

```bash
IP=$(docker inspect moocaudit-wt-web --format '{{(index .NetworkSettings.Networks "moocaudit_default").IPAddress}}')
docker run -d --name moocaudit-wt-selenium --network moocaudit_default --network-alias selenium \
  --add-host "webserver:$IP" --shm-size=2g selenium/standalone-chrome:4
docker exec moocaudit-wt-web bash -c 'echo "127.0.0.1 webserver" >> /etc/hosts; mkdir -p /var/www/behatdata /var/www/behatfaildumps; chown www-data /var/www/behatdata /var/www/behatfaildumps'
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-wt-web php admin/tool/behat/cli/init.php
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-wt-web php admin/tool/behat/cli/run.php --tags=@local_mooc_audit
```

PHPCS (`moodlehq/moodle-cs`) fora da árvore do Moodle, no contêiner de testes:

```bash
docker exec moocaudit-wt bash -c 'mkdir -p /tmp/cs && cd /tmp/cs && php -r "copy(\"https://getcomposer.org/download/latest-stable/composer.phar\", \"composer.phar\");" \
  && php composer.phar config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true \
  && php composer.phar require --no-interaction moodlehq/moodle-cs'
docker exec -w /var/www/html moocaudit-wt /tmp/cs/vendor/bin/phpcs --standard=moodle local/mooc_audit
```

Para regravar as expectativas de caracterização após uma mudança de comportamento documentada:
`docker exec -e LOCAL_MOOC_AUDIT_WRITE_EXPECTED=1 -w /var/www/html moocaudit-wt vendor/bin/phpunit local/mooc_audit/tests/characterization_test.php`.
Remover depois: `docker rm -f moocaudit-wt moocaudit-wt-web moocaudit-wt-selenium && docker exec moocaudit-db-1 dropdb -U moodle moodle_wt`.

## Ambiente da spec 002 (correção assistida)

Os contêineres `moocaudit-wt*` montam o worktree da spec 001. Para a spec 002 há um ambiente próprio, com banco
`moodle_s2`, porta 8093 e uma rede `moocaudit_s2` (o banco do laboratório é ligado a ela com o apelido `db`), para que
o Selenium com apelido `selenium` não colida com o da spec 001:

```bash
docker exec moocaudit-db-1 createdb -U moodle moodle_s2
docker network create moocaudit_s2
docker network connect --alias db moocaudit_s2 moocaudit-db-1
docker run -d --name moocaudit-s2-web --network moocaudit_s2 -p 127.0.0.1:8093:80 \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_s2 -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8093 -e MOODLE_DOCKER_RUNNING=1 -e MOODLE_DOCKER_BROWSER=chrome \
  -v "$PROJECT_DIR/moodle-local/moodle:/var/www/html" -v "<worktree>/plugin/local_mooc_audit:/var/www/html/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3
docker exec moocaudit-s2-web bash -c 'mkdir -p /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && chown www-data /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && echo "127.0.0.1 webserver" >> /etc/hosts'
docker exec -w /var/www/html moocaudit-s2-web php admin/cli/install_database.php --agree-license \
  --fullname="Auditor MOOC (spec 002)" --shortname=s2 --adminpass='Admin123!' --adminemail=admin@example.com --lang=pt_br
docker exec -w /var/www/html moocaudit-s2-web php local/mooc_audit/cli/make_fixtures.php     # A = 2, B = 3
docker exec moocaudit-s2-web chown -R www-data /var/www/moodledata
docker exec -w /var/www/html moocaudit-s2-web php admin/tool/phpunit/cli/init.php
IP=$(docker inspect moocaudit-s2-web --format '{{(index .NetworkSettings.Networks "moocaudit_s2").IPAddress}}')
docker run -d --name moocaudit-s2-selenium --network moocaudit_s2 --network-alias selenium --add-host "webserver:$IP" \
  --shm-size=2g selenium/standalone-chrome:4
docker exec -w /var/www/html moocaudit-s2-web php admin/tool/behat/cli/init.php
```

O PHPCS usa uma cópia de `/tmp/cs` do contêiner `moocaudit-wt` (`docker cp`). Remover tudo:
`docker rm -f moocaudit-s2-web moocaudit-s2-selenium && docker network disconnect moocaudit_s2 moocaudit-db-1 &&
docker network rm moocaudit_s2 && docker exec moocaudit-db-1 dropdb -U moodle moodle_s2`.

## Ambiente da spec 003 (comparação entre ofertas)

Mesmo desenho do ambiente da spec 002, com banco `moodle_s3`, porta 8094 e rede `moocaudit_s3`, montando o plugin do
worktree da spec 003:

```bash
docker exec moocaudit-db-1 createdb -U moodle moodle_s3
docker network create moocaudit_s3
docker network connect --alias db moocaudit_s3 moocaudit-db-1
docker run -d --name moocaudit-s3-web --network moocaudit_s3 -p 127.0.0.1:8094:80 \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_s3 -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8094 -e MOODLE_DOCKER_RUNNING=1 -e MOODLE_DOCKER_BROWSER=chrome \
  -v "$PROJECT_DIR/moodle-local/moodle:/var/www/html" -v "<worktree>/plugin/local_mooc_audit:/var/www/html/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3
docker exec moocaudit-s3-web bash -c 'mkdir -p /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && chown www-data /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && echo "127.0.0.1 webserver" >> /etc/hosts'
docker exec -w /var/www/html moocaudit-s3-web php admin/cli/install_database.php --agree-license \
  --fullname="Auditor MOOC (spec 003)" --shortname=s3 --adminpass='Admin123!' --adminemail=admin@example.com --lang=pt_br
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/make_fixtures.php      # A = 2, B = 3
docker exec -w /var/www/html moocaudit-s3-web php local/mooc_audit/cli/make_scenarios.php --year=2027   # cenários A–G
docker exec moocaudit-s3-web chown -R www-data /var/www/moodledata
docker exec -w /var/www/html moocaudit-s3-web php admin/tool/phpunit/cli/init.php
IP=$(docker inspect moocaudit-s3-web --format '{{(index .NetworkSettings.Networks "moocaudit_s3").IPAddress}}')
docker run -d --name moocaudit-s3-selenium --network moocaudit_s3 --network-alias selenium --add-host "webserver:$IP" \
  --shm-size=2g selenium/standalone-chrome:4
docker exec -w /var/www/html moocaudit-s3-web php admin/tool/behat/cli/init.php
```

Site: http://localhost:8094 (admin / `Admin123!`, apenas local). Medição de desempenho:
`docker cp plugin-results/0.4.0/make151.php moocaudit-s3-web:/tmp/ && docker exec -w /var/www/html moocaudit-s3-web php /tmp/make151.php`.
Remover tudo: `docker rm -f moocaudit-s3-web moocaudit-s3-selenium && docker network disconnect moocaudit_s3 moocaudit-db-1 &&
docker network rm moocaudit_s3 && docker exec moocaudit-db-1 dropdb -U moodle moodle_s3`.

## Ambiente da spec 005 (histórico, lote e acompanhamento)

Mesmo desenho das specs 002/003, com banco `moodle_s5`, porta 8095 e rede `moocaudit_s5`, montando o plugin do
worktree da spec 005:

```bash
docker exec moocaudit-db-1 createdb -U moodle moodle_s5
docker network create moocaudit_s5
docker network connect --alias db moocaudit_s5 moocaudit-db-1
docker run -d --name moocaudit-s5-web --network moocaudit_s5 -p 127.0.0.1:8095:80 \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_s5 -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8095 -e MOODLE_DOCKER_RUNNING=1 -e MOODLE_DOCKER_BROWSER=chrome \
  -v "$PROJECT_DIR/moodle-local/moodle:/var/www/html" -v "<worktree>/plugin/local_mooc_audit:/var/www/html/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3
docker exec moocaudit-s5-web bash -c 'mkdir -p /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && chown www-data /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && echo "127.0.0.1 webserver" >> /etc/hosts'
docker exec -w /var/www/html moocaudit-s5-web php admin/cli/install_database.php --agree-license \
  --fullname="Auditor MOOC (spec 005)" --shortname=s5 --adminpass='Admin123!' --adminemail=admin@example.com --lang=pt_br
docker exec -w /var/www/html moocaudit-s5-web php local/mooc_audit/cli/make_fixtures.php      # A = 2, B = 3
docker exec moocaudit-s5-web chown -R www-data /var/www/moodledata
docker exec -w /var/www/html moocaudit-s5-web php admin/tool/phpunit/cli/init.php
IP=$(docker inspect moocaudit-s5-web --format '{{(index .NetworkSettings.Networks "moocaudit_s5").IPAddress}}')
docker run -d --name moocaudit-s5-selenium --network moocaudit_s5 --network-alias selenium --add-host "webserver:$IP" \
  --shm-size=2g selenium/standalone-chrome:4
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-s5-web php admin/tool/behat/cli/init.php
```

Site: http://localhost:8095 (admin / `Admin123!`, apenas local). Medição do lote:
`docker cp plugin-results/0.5.0/make_lote.php moocaudit-s5-web:/tmp/ && docker exec -w /var/www/html moocaudit-s5-web php /tmp/make_lote.php --n=100`
(100 salas A/B na categoria "Lote de medição") e `php local/mooc_audit/cli/batch.php --category=<id> --run`; detalhes
em `specs/005-auditoria-lote-historico/results.md`. Remover tudo: `docker rm -f moocaudit-s5-web moocaudit-s5-selenium &&
docker network disconnect moocaudit_s5 moocaudit-db-1 && docker network rm moocaudit_s5 && docker exec moocaudit-db-1 dropdb -U moodle moodle_s5`.


## Ambiente da revisão geral e da estabilização (rv)

Mesmo desenho das specs 002/003/005, com banco `moodle_rv`, porta 8096 e rede `moocaudit_rv`, montando o plugin do
worktree da revisão geral (`docs/revisao-geral/`) e das etapas do plano de estabilização. Comandos idênticos aos da
spec 005, trocando `s5` por `rv`, `moodle_s5` por `moodle_rv` e `8095` por `8096`. O PHPCS usa uma cópia de `/tmp/cs`
de outro contêiner (`docker cp` para o scratchpad e de lá para `moocaudit-rv-web:/tmp/cs`).

Site: http://localhost:8096 (admin / `Admin123!`, apenas local; A = 2, B = 3). Behat:
`docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-rv-web vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags=@local_mooc_audit`.
Remover tudo: `docker rm -f moocaudit-rv-web moocaudit-rv-selenium && docker network disconnect moocaudit_rv moocaudit-db-1 &&
docker network rm moocaudit_rv && docker exec moocaudit-db-1 dropdb -U moodle moodle_rv`.

## Ambiente Moodle 5.3 LTS (compatibilidade, 53)

Árvore própria `moodle-local/moodle53` (branch `MOODLE_503_STABLE`; testado no commit 236a9d70, 5.3+ Build 20261030),
banco `moodle_53`, porta 8097, rede `moocaudit_53`. Desde o 5.1 o código web fica em `public/`: a raiz do Apache é
`/var/www/html/public`, o plugin é montado em `public/local/mooc_audit` e o `vendor/` do Composer fica na raiz. Os
plugins de terceiros são copiados da árvore 4.5 (mesmas versões da tabela acima).

```bash
cd moodle-local
git clone --depth 1 --branch MOODLE_503_STABLE https://github.com/moodle/moodle.git moodle53
for p in mod/simplecertificate mod/game mod/hvp blocks/completion_progress availability/condition/days; do cp -R moodle/$p moodle53/public/$p; done
cp moodle-docker/config.docker-template.php moodle53/config.php
docker exec moocaudit-db-1 createdb -U moodle moodle_53
docker network create moocaudit_53
docker network connect --alias db moocaudit_53 moocaudit-db-1
docker run -d --name moocaudit-53-web --network moocaudit_53 -p 127.0.0.1:8097:80 \
  -e MOODLE_DOCKER_DBTYPE=pgsql -e MOODLE_DOCKER_DBNAME=moodle_53 -e MOODLE_DOCKER_DBUSER=moodle -e 'MOODLE_DOCKER_DBPASS=m@0dl3ing' \
  -e MOODLE_DOCKER_WEB_HOST=localhost -e MOODLE_DOCKER_WEB_PORT=127.0.0.1:8097 -e MOODLE_DOCKER_RUNNING=1 -e MOODLE_DOCKER_BROWSER=chrome \
  -e APACHE_DOCUMENT_ROOT=/var/www/html/public \
  -v "$PROJECT_DIR/moodle-local/moodle53:/var/www/html" -v "<worktree>/plugin/local_mooc_audit:/var/www/html/public/local/mooc_audit" \
  -w /var/www/html moodlehq/moodle-php-apache:8.3
docker exec moocaudit-53-web bash -c 'mkdir -p /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && chown www-data /var/www/moodledata /var/www/behatdata /var/www/behatfaildumps && echo "127.0.0.1 webserver" >> /etc/hosts'
docker exec -w /var/www/html moocaudit-53-web php admin/cli/install_database.php --agree-license \
  --fullname="Auditor MOOC (Moodle 5.3)" --shortname=m53 --adminpass='Admin123!' --adminemail=admin@example.com --lang=en
docker exec -w /var/www/html moocaudit-53-web php public/local/mooc_audit/cli/make_fixtures.php   # A = 4, B = 5
docker exec moocaudit-53-web chown -R www-data /var/www/moodledata
docker exec -w /var/www/html moocaudit-53-web bash -c 'php -r "copy(\"https://getcomposer.org/download/latest-stable/composer.phar\", \"/usr/local/bin/composer\");" && chmod +x /usr/local/bin/composer && composer install --no-interaction'
docker exec -w /var/www/html moocaudit-53-web php public/admin/tool/phpunit/cli/init.php
docker exec -w /var/www/html moocaudit-53-web vendor/bin/phpunit --testsuite local_mooc_audit_testsuite
IP=$(docker inspect moocaudit-53-web --format '{{(index .NetworkSettings.Networks "moocaudit_53").IPAddress}}')
docker run -d --name moocaudit-53-selenium --network moocaudit_53 --network-alias selenium --add-host "webserver:$IP" \
  --shm-size=2g selenium/standalone-chrome:4
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-53-web php public/admin/tool/behat/cli/init.php
docker exec -e MOODLE_DOCKER_BROWSER=chrome -w /var/www/html moocaudit-53-web vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags=@local_mooc_audit
```

Site: http://localhost:8097 (admin / `Admin123!`, apenas local). No Behat do 5.x, classes depreciadas do Bootstrap 4
recebem fundo vermelho-claro de alerta (só em sites Behat ou no modo de designer de tema), o que derruba o teste de
contraste do axe. Remover tudo: `docker rm -f moocaudit-53-web moocaudit-53-selenium && docker network disconnect
moocaudit_53 moocaudit-db-1 && docker network rm moocaudit_53 && docker exec moocaudit-db-1 dropdb -U moodle moodle_53`.
