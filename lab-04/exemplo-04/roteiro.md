# Integrando containers com Docker Compose

## Exemplo: PHP, MariaDB e Docker

- criação do compose.yaml a partir da solução do Laboratório 03
  - aplicação
  - banco de dados


- comandos
  - config
  - build
  - up (-d) (--build) (--force-recreate)
  - down (-v)
  - logs
  - ps
  - start/stop/restart
  - exec

https://mariadb.com/docs/server/server-management/automated-mariadb-deployment-and-administration/docker-and-mariadb/using-healthcheck-sh

cd ../../lab-04/exemplo-04
docker compose config
docker compose build 
docker compose up
docker compose up -d
docker compose down
docker compose up -d
docker compose down -v
docker compose ps
docker compose up -d
docker compose up -d --build
docker compose stop
docker compose restart
docker compose up -d --force-recreate
docker compose logs -f
docker compose top
docker compose exec message-server bash