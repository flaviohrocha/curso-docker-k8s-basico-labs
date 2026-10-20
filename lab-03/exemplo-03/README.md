# Armazenando dados de containers | Trabalhando com Redes

## Exemplo: PHP, MariaDB e Docker

O objetivo deste exemplo é construir uma imagem para uma aplicação em **PHP**, servida pelo Apache, que usa o Twig para gerar a página HTML. As mensagens são armazenadas em um banco **MariaDB**, persistidas em um volume Docker e acessadas por containers da mesma rede.


### Preparação do ambiente

1. Abra o **Git Bash** na raiz do diretório `exemplo-03`, onde estão o `Dockerfile`, `composer.json` e a pasta `src`.

2. Construa a imagem da aplicação:

```bash
docker build -t message-server .
```

### 1. Persistência com volume Docker

1. Crie um volume para manter os dados do MariaDB:

```bash
docker volume create message-mariadb-vol
```

2. Inicie o MariaDB montando o volume criado:

```bash
docker run -d \
    -p 3306:3306 \
    --name message-mariadb \
    --user root \
    --env MARIADB_DATABASE=message \
    --env MARIADB_ROOT_PASSWORD=message \
    -v message-mariadb-vol:/var/lib/mysql \
    mariadb
```

Espere o banco ficar saudável antes de iniciar a aplicação.

3. Inicie a aplicação PHP/Apache configurando as credenciais do banco:

```bash
docker run -d \
  -p 9000:80 \
  --name message-server \
  --env DB_HOST=host.docker.internal \
  --env DB_NAME=message \
  --env DB_USER=root \
  --env DB_PASSWORD=message \
  --add-host=host.docker.internal:host-gateway \
  message-server
```

4. Acesse [http://localhost:9000/message.php](http://localhost:9000/message.php) diversas vezes para visualizar as mensagens.

5. Remova os containers:

```bash
docker rm -f message-server message-mariadb
```

6. Recrie os containers e verifique que os dados não foram perdidos.


### 2. Atualização em tempo real com bind mount

1. Remova o container `message-server` para recriar a aplicação com um bind mount:

```bash
docker rm -f message-server
```

2. Entre na pasta `lab-03/exemplo-03` e execute o container montando o diretório de templates da máquina local:

```bash
MSYS_NO_PATHCONV=1 docker run -d \
  -p 9000:80 \
  --name message-server \
  --env DB_HOST=host.docker.internal \
  --env DB_NAME=message \
  --env DB_USER=root \
  --env DB_PASSWORD=message \
  --add-host=host.docker.internal:host-gateway \
  -v "$(pwd)/src/templates:/var/www/html/templates" \
  message-server
```

3. Acesse [http://localhost:9000/message.php](http://localhost:9000/message.php).

4. Altere alguma propriedade do arquivo `src/templates/messages.html.twig` e atualize o navegador. Verifique se as alterações feitas na sua IDE são refletidas na aplicação em execução.

> Esse passo mostra como um bind mount permite visualizar mudanças no código sem precisar reconstruir a imagem a cada ajuste simples no template.


### 3. Conectando containers pela mesma rede Docker

1. Remova os containers:

```bash
docker rm -f message-server message-mariadb
```

2. Crie uma rede definida pelo usuário:

```bash
docker network create message-network
```

3. Inicie o MariaDB conectando-o à rede criada:

```bash
docker run -d \
    --name message-mariadb \
    --user root \
    --env MARIADB_DATABASE=message \
    --env MARIADB_ROOT_PASSWORD=message \
    -v message-mariadb-vol:/var/lib/mysql \
    --network=message-network \
    mariadb
```

Espere o banco ficar saudável antes de iniciar a aplicação.

4. Inicie a aplicação usando o nome do serviço do banco em vez de `host.docker.internal`:

```bash
docker run -d \
  -p 9000:80 \
  --name message-server \
  --env DB_HOST=message-mariadb \
  --env DB_NAME=message \
  --env DB_USER=root \
  --env DB_PASSWORD=message \
  --network=message-network \
  message-server
```

> Quando os containers estão na mesma rede, o `DB_HOST` deve ser o nome do container do banco, neste caso `message-mariadb`.

5. Acesse [http://localhost:9000/message.php](http://localhost:9000/message.php).


