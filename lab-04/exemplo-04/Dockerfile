# syntax=docker/dockerfile:1

# Define a versão do PHP utilizada no estágio final.
ARG PHP_VERSION=8.2

# Usa a imagem do Composer para instalar dependências em um estágio separado.
FROM composer:lts as deps

# Define o diretório de trabalho para a instalação das dependências.
WORKDIR /app

# Monta os arquivos de configuração e um cache temporário, depois instala apenas dependências de produção.
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction

# Cria o estágio final usando PHP com Apache para servir a aplicação web.
FROM php:${PHP_VERSION}-apache as final

# Instala os drivers PDO necessários para a conexão com MariaDB/MySQL.
RUN docker-php-ext-install pdo pdo_mysql

# Ativa as configurações recomendadas para produção do PHP.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copia as bibliotecas instaladas pelo Composer para a raiz servida pelo Apache.
COPY --from=deps app/vendor/ /var/www/html/vendor

# Copia os arquivos da aplicação para a raiz servida pelo Apache.
COPY ./src /var/www/html

# Executa o Apache com um usuário sem privilégios de root.
USER www-data