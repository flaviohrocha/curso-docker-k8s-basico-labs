# Construindo imagens

## Exemplo: PHP, MariaDB e Docker

O propósito deste exemplo é construir uma imagem para uma aplicação desenvolvida em **PHP**, servida pelo Apache e que utiliza o Twig para gerar a página HTML. As mensagens são armazenadas em um banco **MariaDB**. 

### Funcionamento da aplicação

Quando uma requisição é feita para `message.php`, a aplicação:

1. Lê as variáveis `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` e conecta ao banco MariaDB.
2. Cria a tabela `messages`, caso ela ainda não exista.
3. Conta os registros existentes e monta uma mensagem inicial concatenando essa quantidade ao prefixo de `MESSAGE_PREFIX`. Se a variável estiver ausente ou vazia, usa `message-` como prefixo. Por exemplo, com prefixo `HelloDocker-` e nenhum registro, a primeira mensagem será `HelloDocker-0`.
4. Insere essa mensagem somente se ela ainda não estiver na tabela.
5. Busca todas as mensagens e entrega os dados ao Twig, que gera a tabela HTML exibida no navegador.

### Preparação do ambiente

1. Abra o **Git Bash** na raiz do diretório `exemplo`, onde estão o `Dockerfile`, `composer.json` e a pasta `src`.

2. Construa a imagem da aplicação:

```bash
docker build -t message-server .
```

### Execução dos containers

1. Inicie o MariaDB:

```bash
docker run -d \
    -p 3306:3306 \
    --name message-mariadb \
    --user root \
    --env MARIADB_DATABASE=message \
    --env MARIADB_ROOT_PASSWORD=message \
    mariadb
```
Espere o banco ficar saudável antes de iniciar a aplicação.

2. Inicie a aplicação PHP/Apache, configurando as credenciais do banco:

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
ou

Passando a variável `MESSAGE_PREFIX`:

```bash
docker run -d \
  -p 9000:80 \
  --name message-server \
  --env DB_HOST=host.docker.internal \
  --env DB_NAME=message \
  --env DB_USER=root \
  --env DB_PASSWORD=message \
  --add-host=host.docker.internal:host-gateway \
  --env MESSAGE_PREFIX=HelloDocker- \
  message-server
```

3. Acesse [http://localhost:9000/message.php](http://localhost:9000/message.php) para visualizar as mensagens.

### Remoção dos containers

Para parar e remover os containers criados:

```bash
docker rm -f message-server message-mariadb
```

### Outras opções de construção

#### Escolher a versão do PHP

O `Dockerfile` define `PHP_VERSION` como `8.2` por padrão. Para escolher outra versão disponível da imagem oficial `php:<versão>-apache`, informe o argumento `PHP_VERSION` durante o build. Por exemplo, para construir com PHP 8.5:

```bash
docker build -t message-server-8.5 --build-arg PHP_VERSION=8.5 .
```

O nome `message-server-8.5` é a tag local da imagem; a versão usada na imagem base é definida por `--build-arg PHP_VERSION=8.5`.

#### Validar o Dockerfile com build checks

O Docker BuildKit pode verificar o `Dockerfile` sem construir a imagem. Para que avisos encontrados pelos checks interrompam a validação, adicione `# check=error=true` logo após a diretiva de sintaxe no início do `Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1
# check=error=true
```

Em seguida, execute na raiz do projeto:

```bash
docker build --check .
```
Com `check=error=true`, avisos também fazem a validação falhar. 