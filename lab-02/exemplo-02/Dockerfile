# syntax=docker/dockerfile:1

# Usar a imagem do Composer para instalar dependências em um estágio separado.

## Definir o diretório de trabalho para a instalação das dependências.

## Montar os arquivos de configuração e um cache temporário, depois instalar apenas dependências de produção.

# Criar o estágio final usando PHP com Apache para servir a aplicação web.

## Instalar os drivers PDO necessários para a conexão com MariaDB/MySQL.

## Copiar as bibliotecas instaladas pelo Composer para a raiz servida pelo Apache.

## Copiar os arquivos da aplicação para a raiz servida pelo Apache.

## Executar o Apache com um usuário sem privilégios de root.
