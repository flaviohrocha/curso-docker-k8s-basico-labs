# Integrando containers com Docker Compose - Guia de comandos

## Comandos do Docker Compose

### Sintaxe geral

- **Sintaxe:**
```bash
docker compose [OPÇÕES] [COMANDO]
```

- **Opções globais relevantes:**
```text
--dry-run                 Simula a execução sem realizar alterações.
--env-file ARQUIVO        Utiliza um arquivo de ambiente alternativo.
-f, --file ARQUIVO        Especifica um arquivo Compose; pode ser repetido.
--progress TIPO           Define a saída do progresso: auto, tty, plain, json ou quiet.
--project-directory DIR  Define um diretório de trabalho alternativo.
-p, --project-name NOME   Define o nome do projeto.
```

- **Subcomandos frequentes:**

| Comando | Finalidade |
| --- | --- |
| `build` | Constrói ou reconstrói as imagens dos serviços. |
| `config` | Valida e exibe a configuração Compose resultante. |
| `down` | Interrompe e remove containers e redes do projeto. |
| `exec` | Executa um comando em um serviço em execução. |
| `images` | Lista as imagens utilizadas pelos serviços. |
| `logs` | Exibe os logs dos serviços. |
| `pull` / `push` | Baixa / publica as imagens dos serviços. |
| `ps` | Lista os containers e seus estados. |
| `restart` / `stop` / `start` | Reinicia / interrompe / inicia serviços. |
| `rm` | Remove containers interrompidos. |
| `top` | Exibe os processos em execução nos containers. |
| `up` | Cria, recria e inicia os serviços. |
| `version` | Exibe a versão do Docker Compose. |

### Iniciar os serviços com `docker compose up`

- **Sintaxe:**
```bash
docker compose up [OPÇÕES] [SERVIÇOS...]
```

- **Opções relevantes:**
```text
-d, --detach          Executa os containers em segundo plano.
--build               Constrói as imagens antes de iniciar os serviços.
--no-recreate         Não recria containers já existentes.
--force-recreate      Força a recriação dos containers.
--wait                Aguarda os serviços ficarem em execução ou saudáveis.
```

- **Exemplos:**
```bash
# Cria e inicia todos os serviços definidos no arquivo Compose
docker compose up
```
```bash
# Inicia os serviços em segundo plano
docker compose up -d
```
```bash
# Constrói as imagens e inicia os serviços
docker compose up --build -d
```
```bash
# Aguarda os serviços atingirem o estado running ou healthy
docker compose up --wait
```
```bash
# Simula o comando sem efetuar as alterações
docker compose --dry-run up -d
```

- O Compose pode recriar containers quando a configuração ou imagem de um serviço muda. Os volumes montados são preservados durante a recriação.

### Listar containers com `docker compose ps`

- **Sintaxe:**
```bash
docker compose ps [SERVIÇOS...]
```

- **Exemplo:**
```bash
# Lista os containers do projeto e seus estados
docker compose ps
```

- A saída ajuda a identificar os serviços, containers, imagens, estados e portas publicadas.

### Consultar logs com `docker compose logs`

- **Sintaxe:**
```bash
docker compose logs [OPÇÕES] [SERVIÇOS...]
```

- **Exemplos:**
```bash
# Exibe os logs de todos os serviços
docker compose logs
```
```bash
# Acompanha continuamente os logs
docker compose logs -f
```
```bash
# Acompanha os logs de um serviço específico
docker compose logs -f backend
```

### Executar comandos com `docker compose exec`

- Executa um comando em um container de serviço que já está em execução.

- **Sintaxe:**
```bash
docker compose exec [OPÇÕES] SERVIÇO COMANDO [ARG...]
```

- **Exemplo:**
```bash
# Abre um shell no serviço backend
docker compose exec backend sh
```

### Validar a configuração com `docker compose config`

- O comando valida e exibe o modelo Compose resultante após a interpolação e a combinação dos arquivos de configuração.

- **Exemplos:**
```bash
# Valida e exibe a configuração resultante
docker compose config
```
```bash
# Exibe as variáveis usadas na interpolação
docker compose config --environment
```

### Interromper e remover recursos com `docker compose down`

- **Sintaxe:**
```bash
docker compose down [OPÇÕES]
```

- **Exemplos:**
```bash
# Interrompe e remove containers e redes do projeto
docker compose down
```
```bash
# Remove também os volumes
docker compose down --volumes
```
```bash
# Remove as imagens locais criadas pelo projeto
docker compose down --rmi local
```
```bash
# Remove todas as imagens utilizadas pelos serviços
docker compose down --rmi all
```

> [!WARNING]
> A opção `--volumes` remove os volumes nomeados associados ao projeto e pode apagar dados persistentes. Utilize-a somente quando essa remoção for desejada.

### Utilizar arquivos Compose e variáveis alternativas

- **Exemplos:**
```bash
# Usa um arquivo Compose específico
docker compose -f compose.yaml up -d
```
```bash
# Combina arquivo base e arquivo de produção
docker compose -f compose.yaml -f compose.prod.yaml up -d
```
```bash
# Define um arquivo de variáveis para interpolação
docker compose --env-file .env.prod up -d
```
```bash
# Define um nome específico para o projeto
docker compose -p minha-aplicacao up -d
```

- Ao especificar vários arquivos com `-f`, eles são processados na ordem informada. O arquivo posterior pode complementar ou alterar a configuração anterior conforme as regras de mesclagem do Compose.
- Em combinações com `-f`, os caminhos relativos são resolvidos em relação ao primeiro arquivo Compose especificado.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | How Compose works - CLI](https://docs.docker.com/compose/intro/compose-application-model/#cli)  
> [Docker Docs | Docker Compose CLI Reference](https://docs.docker.com/reference/cli/docker/compose/)

---

## Configuração do arquivo Compose

### Estrutura básica

- O arquivo Compose utiliza YAML. Os nomes convencionais atuais são `compose.yaml` ou `compose.yml`; `docker-compose.yaml` e `docker-compose.yml` continuam sendo aceitos.
- O atributo `version` é obsoleto e não deve ser incluído em novos arquivos.

- **Exemplo:**
```yaml
name: minha-aplicacao

services:
  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    networks:
      - frontend

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    environment:
      APP_ENV: development
    env_file:
      - ./backend.env
    networks:
      - frontend
      - backend
    depends_on:
      database:
        condition: service_healthy

  database:
    image: postgres:18
    environment:
      POSTGRES_USER: app
      POSTGRES_PASSWORD: exemplo
      POSTGRES_DB: app
    volumes:
      - database-data:/var/lib/postgresql/data
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

volumes:
  database-data:

networks:
  frontend:
  backend:
```

- `services` define os componentes da aplicação; `volumes` declara volumes nomeados; `networks` declara redes; `name` define o nome do projeto.
- Um serviço pode usar `image` para indicar uma imagem existente ou `build` para construir uma imagem a partir do código-fonte.

### Configurar `build` e `image`

- **Exemplos:**
```yaml
services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
```
```yaml
services:
  web:
    image: nginx:alpine
```

- `build.context` define o contexto de construção; `build.dockerfile` indica o Dockerfile utilizado. `image` identifica a imagem usada para iniciar o serviço.

### Publicar portas com `ports`

- A sintaxe curta é `[IP_DO_HOST:]PORTA_CONTAINER[/PROTOCOLO]` ou `[IP_DO_HOST:]PORTA_HOST:PORTA_CONTAINER[/PROTOCOLO]`.
- Especifique mapeamentos como strings entre aspas para evitar ambiguidades no YAML.

- **Exemplos:**
```yaml
services:
  web:
    image: nginx:alpine
    ports:
      - "8080:80"
      - "127.0.0.1:8443:443"
      - "8081:80/udp"
```

- O protocolo padrão é TCP. Sem um endereço IP explícito, a porta pode ser publicada em todas as interfaces do host. Usar `127.0.0.1` restringe a publicação à interface de loopback.

### Definir variáveis com `environment` e `env_file`

- `environment` declara variáveis diretamente no arquivo Compose; `env_file` carrega variáveis a partir de um arquivo.

- **Exemplos:**
```yaml
services:
  backend:
    environment:
      APP_ENV: development
      LOG_LEVEL: debug
```
```yaml
services:
  backend:
    env_file:
      - ./backend.env
```

- Exemplo de conteúdo de `backend.env`:
```text
APP_ENV=development
LOG_LEVEL=debug
```

### Verificar a saúde e ordenar serviços

- `healthcheck` executa uma verificação para determinar se o serviço está `starting`, `healthy` ou `unhealthy`. `depends_on` controla a ordem de inicialização; com a condição `service_healthy`, o Compose aguarda o serviço dependente ficar saudável.

- **Exemplo:**
```yaml
services:
  database:
    image: postgres:18
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  backend:
    build: .
    depends_on:
      database:
        condition: service_healthy
```

- Em `healthcheck.test`, `CMD` executa o comando diretamente; `CMD-SHELL` executa o comando por meio do shell. `$$` impede que o Compose faça a interpolação da variável e permite que ela seja expandida dentro do container.
- A sintaxe curta de `depends_on` garante a ordem de inicialização, mas não espera, por si só, que o serviço esteja saudável.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Define services in Docker Compose](https://docs.docker.com/reference/compose-file/services/)

---

## Redes e volumes

### Definir redes

- Os serviços que compartilham uma rede podem se comunicar entre si. Por padrão, o Compose cria uma rede para a aplicação; redes nomeadas permitem separar os componentes.

- **Exemplo:**
```yaml
services:
  proxy:
    image: nginx:alpine
    networks:
      - frontend

  app:
    image: minha-aplicacao:1.0
    networks:
      - frontend
      - backend

  database:
    image: postgres:18
    networks:
      - backend

networks:
  frontend:
  backend:
```

- Nesse exemplo, `proxy` alcança `app`, e `app` alcança `database`; `proxy` e `database` não compartilham uma rede diretamente.
- Para utilizar uma rede já existente, declare-a como externa:
```yaml
networks:
  outside:
    external: true
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Define and manage networks in Docker Compose](https://docs.docker.com/reference/compose-file/networks/)

### Definir volumes e bind mounts

- Um volume nomeado é gerenciado pelo Docker. O Compose cria-o quando necessário e reutiliza um volume existente com o mesmo nome do projeto.

- **Volume nomeado:**
```yaml
services:
  database:
    image: postgres:18
    volumes:
      - database-data:/var/lib/postgresql/data

volumes:
  database-data:
```

- **Bind mount:**
```yaml
services:
  frontend:
    image: node:lts
    volumes:
      - type: bind
        source: ./static
        target: /opt/app/static
```

- Para usar um volume já existente, declare-o como externo:
```yaml
volumes:
  database-data:
    external: true
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Define and manage volumes in Docker Compose](https://docs.docker.com/reference/compose-file/volumes/)

---

## Configs e secrets

### Disponibilizar arquivos de configuração com `configs`

- `configs` disponibiliza dados de configuração aos serviços autorizados, sem tratar esses dados como secrets.

- **Exemplo com arquivo:**
```yaml
services:
  web:
    image: nginx:alpine
    configs:
      - source: nginx-config
        target: /etc/nginx/nginx.conf

configs:
  nginx-config:
    file: ./nginx.conf
```

- A origem também pode ser definida como `external: true` quando a configuração já existir na plataforma.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Configs top-level element](https://docs.docker.com/reference/compose-file/configs/)

### Disponibilizar dados sensíveis com `secrets`

- Utilize `secrets` para conceder acesso explícito a dados sensíveis, como senhas, tokens, certificados e chaves privadas. Por padrão, o secret é disponibilizado como arquivo em `/run/secrets/<nome>`.

- **Exemplo com arquivo:**
```yaml
services:
  backend:
    image: minha-aplicacao/backend
    secrets:
      - database-password

secrets:
  database-password:
    file: ./secrets/database-password.txt
```

- Também é possível obter o valor de um secret de uma variável de ambiente do host:
```yaml
services:
  backend:
    image: minha-aplicacao/backend
    secrets:
      - source: api-token
        target: api-token
        mode: 0o440

secrets:
  api-token:
    environment: API_TOKEN
```

> [!WARNING]
> Conceda secrets somente aos serviços que precisam deles. Evite colocar senhas, tokens e chaves em imagens, no código-fonte ou em variáveis de ambiente quando o recurso `secrets` for apropriado.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Secrets](https://docs.docker.com/reference/compose-file/secrets/)  
> [Docker Docs | Manage secrets securely in Docker Compose](https://docs.docker.com/compose/how-tos/use-secrets/)  
> [Docker Docs | Define services in Docker Compose - Secrets](https://docs.docker.com/reference/compose-file/services/#secrets)

---

## Variáveis de ambiente e interpolação

- O Compose pode substituir variáveis no arquivo YAML usando valores do ambiente do shell ou de um arquivo `.env`.

- **Exemplo de `compose.yaml`:**
```yaml
services:
  web:
    image: "nginx:${NGINX_TAG}"
    ports:
      - "${APP_PORT}:80"
```

- **Exemplo de `.env`:**
```text
APP_PORT=8080
NGINX_TAG=1.27
```

- Para utilizar outro arquivo de ambiente:
```bash
docker compose --env-file .env.prod up -d
```

- Para exibir os valores usados na interpolação:
```bash
docker compose config --environment
```

> [!WARNING]
> Variáveis usadas para interpolação no arquivo Compose não se tornam automaticamente variáveis de ambiente dentro dos containers. Para isso, use `environment` ou `env_file` no serviço.

> [!NOTE]
> **Para saber mais**  
> [Compose Specification](https://github.com/compose-spec/compose-spec/)  
> [Docker Docs | Interpolation](https://docs.docker.com/reference/compose-file/interpolation/)

---

## Reutilizar e combinar arquivos Compose

### Combinar arquivos com `-f`

- **Exemplo:**
```bash
docker compose \
  -f compose.yaml \
  -f compose.prod.yaml \
  config
```

- A ordem dos arquivos importa. O Compose combina os arquivos para gerar o modelo final; os caminhos relativos são resolvidos em relação ao primeiro arquivo especificado.

### Usar `compose.override.yaml`

- Por convenção, `compose.yaml` mantém a configuração comum, enquanto `compose.override.yaml` pode adicionar ou sobrescrever configurações locais, como portas de desenvolvimento, bind mounts e variáveis. O Compose carrega esse arquivo de substituição automaticamente quando utilizado com o nome convencional.

### Incluir outro arquivo com `include`

- `include` incorpora recursos definidos em outro arquivo Compose, que pode declarar seus próprios serviços, redes e volumes.

- **Exemplo:**
```yaml
include:
  - ./infra.yaml

services:
  web:
    image: minha-aplicacao/web
    depends_on:
      database:
        condition: service_started
```

- Nesse exemplo, `infra.yaml` pode definir o serviço `database` e seus volumes. Os caminhos relativos de cada arquivo incluído são resolvidos no contexto desse arquivo.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Use include to modularize Compose files](https://docs.docker.com/reference/compose-file/include/)

### Reutilizar a configuração de um serviço com `extends`

- `extends` reutiliza a definição de um serviço declarado no mesmo arquivo ou em outro arquivo Compose.

- **Exemplo:**
```yaml
services:
  web:
    extends:
      file: common.yaml
      service: webapp
    ports:
      - "8080:8000"
```

- `extends` não incorpora automaticamente todos os recursos relacionados ao serviço reutilizado. Declare no modelo final as redes, volumes, configs, secrets e demais recursos necessários.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Extend your Compose file](https://docs.docker.com/compose/how-tos/multiple-compose-files/extends/)

---

## Referências gerais

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Docker Compose CLI Reference](https://docs.docker.com/reference/cli/docker/compose/)  
> [Compose Specification](https://github.com/compose-spec/compose-spec/)
