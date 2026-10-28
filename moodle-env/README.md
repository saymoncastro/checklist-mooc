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

Para regravar as expectativas de caracterização após uma mudança de comportamento documentada:
`docker exec -e LOCAL_MOOC_AUDIT_WRITE_EXPECTED=1 -w /var/www/html moocaudit-wt vendor/bin/phpunit local/mooc_audit/tests/characterization_test.php`.
Remover depois: `docker rm -f moocaudit-wt && docker exec moocaudit-db-1 dropdb -U moodle moodle_wt`.
