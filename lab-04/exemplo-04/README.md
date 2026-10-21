# Integrando containers com Docker Compose

## Exemplo: PHP, MariaDB e Docker

O objetivo deste exemplo é definir e executar uma aplicação PHP servida pelo Apache, que utiliza o MariaDB para armazenar mensagens. O Docker Compose reúne a configuração da aplicação e do banco de dados em um único arquivo, incluindo a rede de comunicação, o volume persistente e a condição de saúde necessária para iniciar a aplicação.

### Preparação do ambiente

1. Certifique-se de que o Docker Engine esteja em execução. No Windows, você pode utilizar o Docker Desktop com o Git Bash.

2. Abra o terminal na raiz do projeto, onde estão o `Dockerfile`, o `composer.json` e a pasta `src`.

3. Confirme que o arquivo `Dockerfile` da aplicação está presente. O `compose.yaml` usa o diretório atual (`.`) como contexto de construção.

### 1. Criar o arquivo `compose.yaml`

Crie um arquivo chamado `compose.yaml` na raiz do projeto e adicione a configuração abaixo:

```yaml
name: message-app
services:
  message-server:
    build:
      context: .
      dockerfile: Dockerfile
    image: message-server:compose
    environment:
      - DB_HOST=message-mariadb
      - DB_NAME=message
      - DB_USER=root
      - DB_PASSWORD=message
    ports:
      - "9000:80"
    networks:
      - message-network
    depends_on:
      message-mariadb:
        condition: service_healthy

  message-mariadb:
    image: mariadb
    environment:
      - MARIADB_DATABASE=message
      - MARIADB_ROOT_PASSWORD=message
    volumes:
      - message-mariadb-vol:/var/lib/mysql
    networks:
      - message-network
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      start_period: 10s
      interval: 10s
      timeout: 5s
      retries: 3

volumes:
  message-mariadb-vol:

networks:
  message-network:
```

### 2. Entender a configuração

- **`name`** define o nome do projeto Compose: `message-app`.
- **`services`** define os dois serviços da aplicação:
    - `message-server` constrói uma imagem a partir do `Dockerfile`, publica a porta `80` do container na porta `9000` do host e recebe as configurações de conexão com o banco.
    - `message-mariadb` utiliza a imagem `mariadb` e define o banco `message` e a senha de demonstração do usuário `root`.
- **`depends_on` com `condition: service_healthy`** faz o Compose aguardar o healthcheck do MariaDB ser aprovado antes de iniciar a aplicação.
- **`healthcheck`** verifica se o MariaDB está pronto para aceitar conexões e inicializou o InnoDB.
- **`volumes`** declara o volume nomeado `message-mariadb-vol`, montado em `/var/lib/mysql`, para manter os dados do banco quando os containers forem removidos.
- **`networks`** declara a rede `message-network`, compartilhada pelos dois serviços. Por isso, a aplicação acessa o banco usando `message-mariadb` como nome do host, sem precisar publicar a porta do banco no host.

> [!WARNING]
> As credenciais `message` deste exemplo são apenas para demonstração. Não as utilize em ambientes reais. O arquivo usa a imagem `mariadb` sem fixar uma tag; para ambientes controlados, avalie especificar uma versão de imagem.

### 3. Validar o arquivo Compose

Antes de iniciar os serviços, verifique a configuração e o modelo resultante:

```bash
docker compose config
```

Se o arquivo for válido, o Compose exibirá a configuração consolidada. Corrija eventuais erros de YAML ou de configuração antes de prosseguir.

### 4. Construir a imagem e iniciar os serviços

Execute o comando a seguir na mesma pasta do `compose.yaml`:

```bash
docker compose up --build -d --wait
```

O comando constrói a imagem da aplicação quando necessário, cria a rede e o volume declarados, inicia o MariaDB e aguarda os serviços atingirem o estado esperado. A aplicação somente será iniciada depois que o healthcheck do banco passar.

Para listar os containers do projeto e seus estados:

```bash
docker compose ps
```

Para acompanhar os logs dos serviços:

```bash
docker compose logs -f
```

Para acompanhar somente os logs da aplicação:

```bash
docker compose logs -f message-server
```

Para sair da visualização contínua dos logs, pressione `Ctrl+C`. Isso encerra a visualização, mas não interrompe os serviços em execução.

### 5. Acessar e testar a aplicação

Abra [http://localhost:9000/message.php](http://localhost:9000/message.php) no navegador. Atualize a página algumas vezes e verifique o comportamento das mensagens.

Para executar um comando no container da aplicação, utilize `docker compose exec`. Por exemplo, para abrir um shell quando a imagem possuir `sh`:

```bash
docker compose exec message-server sh
```

### 6. Verificar a persistência dos dados

1. Interrompa e remova os containers e a rede criados pelo projeto:

```bash
docker compose down
```

2. Inicie os serviços novamente:

```bash
docker compose up -d --wait
```

3. Acesse novamente [http://localhost:9000/message.php](http://localhost:9000/message.php) e verifique os dados.

O comando `docker compose down` não remove o volume nomeado por padrão. Por isso, os dados do MariaDB permanecem disponíveis quando os serviços são recriados, desde que o volume não seja removido.

### 7. Encerrar o ambiente

Para interromper e remover os containers e a rede do projeto:

```bash
docker compose down
```

Para remover também o volume persistente do banco de dados:

```bash
docker compose down --volumes
```

> [!WARNING]
> Use `docker compose down --volumes` somente quando desejar apagar os dados persistidos do MariaDB. A remoção do volume é diferente de remover os containers e não deve ser realizada se os dados precisarem ser preservados.

### Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `docker compose config` | Valida e exibe a configuração consolidada. |
| `docker compose build` | Constrói ou reconstrói as imagens definidas por `build`. |
| `docker compose up --build -d` | Constrói as imagens quando necessário e inicia os serviços em segundo plano. |
| `docker compose ps` | Lista os containers do projeto e seus estados. |
| `docker compose logs -f` | Acompanha os logs dos serviços. |
| `docker compose exec message-server sh` | Abre um shell no container da aplicação, se disponível. |
| `docker compose down` | Remove os containers e a rede do projeto, preservando os volumes nomeados. |
| `docker compose down --volumes` | Remove também os volumes do projeto; os dados persistidos serão apagados. |

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Docker Compose CLI Reference](https://docs.docker.com/reference/cli/docker/compose/)  
> [Docker Docs | Define services in Docker Compose](https://docs.docker.com/reference/compose-file/services/)  
> [Docker Docs | Define and manage volumes in Docker Compose](https://docs.docker.com/reference/compose-file/volumes/)  
> [Docker Docs | Define and manage networks in Docker Compose](https://docs.docker.com/reference/compose-file/networks/)  
> [Docker Docs | Control startup and shutdown order in Compose](https://docs.docker.com/compose/how-tos/startup-order/)
