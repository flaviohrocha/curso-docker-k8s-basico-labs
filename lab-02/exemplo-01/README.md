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

1. Abra o **Git Bash** ou **IDE** na raiz do diretório `exemplo-01`

2. Crie um Dockerfile seguindo a especificação abaixo:

   - Imagem: php:8.2-apache

   - Diretório da aplicação: /var/www/html

   - Instalação dos pacotes do SO:
   ```bash
   apt-get update
   apt-get install -y --no-install-recommends \
           curl \
           unzip \
           git \
           libzip-dev
   ```

   - Instalar extensões do PHP
   ```bash
   docker-php-ext-install pdo pdo_mysql zip 
   ```

   - Instalação do composer:
   ```bash
   curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
   php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
   ```

   - Limpeza de arquivos desnecessários:
   ```bash
   rm -f /tmp/composer-setup.php
   apt-get clean
   rm -rf /var/lib/apt/lists/*
   ```

   - Copiar código da aplicação

   - Instalar dependências com Composer:
   ```bash
   composer install \
       --no-dev \
       --no-interaction \
       --prefer-dist \
       --optimize-autoloader
   ```

   - Executar com usuário não-privilegiado

   - Comando para execução
   ```bash
   apache2-foreground
   ```

3. Construa a imagem da aplicação:

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

3. Acesse [http://localhost:9000/](http://localhost:9000/) para visualizar as mensagens.

### Remoção dos containers

Para parar e remover os containers criados:

```bash
docker rm -f message-server message-mariadb
```
