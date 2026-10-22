# Laboratório 04 | Solução

## Objetivo

Criar e executar uma aplicação Spring PetClinic composta por frontend, backend e PostgreSQL utilizando Docker Compose. A solução apresenta cada etapa incrementalmente e, ao final, o arquivo `compose.yaml` completo.

## Tarefas

### 1. Preparar o ambiente e iniciar o arquivo Compose

Na raiz do projeto, crie o arquivo `.env` com as variáveis usadas para inicializar o PostgreSQL:

```dotenv
POSTGRES_DB=petclinic
POSTGRES_USER=petclinic
POSTGRES_PASSWORD=petclinic
```

Essas credenciais são apenas para demonstração.

Crie o arquivo `compose.yaml` e defina o nome do projeto e o serviço `frontend`. O frontend será construído a partir do diretório atual, que deve conter o `Dockerfile` e os arquivos necessários à construção da aplicação. A imagem resultante será identificada como `spring-petclinic-ui`, e a porta `8080` do container será publicada na porta `8080` do host.

#### Solução

```yaml
name: petclinic

services:
  frontend:
    image: spring-petclinic-ui
    build:
      context: .
    ports:
      - "8080:8080"
```

Valide a configuração e confira o serviço:

```bash
docker compose config --quiet
docker compose config --services
```

A saída de `docker compose config --services` deverá conter:

```text
frontend
```

---

### 2. Adicionar o banco de dados e a persistência

Adicione o serviço `database`, utilizando a imagem `postgres:16.3`. Carregue as variáveis de inicialização do arquivo `.env`, monte o volume `petclinic-data` no diretório de dados do PostgreSQL e configure um `healthcheck` baseado em `pg_isready`.

#### Solução

Acrescente ao `compose.yaml` a definição do serviço `database` e a declaração de nível superior do volume:

```yaml
  database:
    image: postgres:16.3
    env_file:
      - .env
    volumes:
      - petclinic-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready"]
      interval: 1s
      timeout: 5s
      retries: 10

volumes:
  petclinic-data:
```

A seção `volumes` deve ficar no nível superior, alinhada com `name` e `services`, e não dentro de um serviço.

Valide a configuração:

```bash
docker compose config --quiet
docker compose config --services
docker compose config --volumes
```

A lista de serviços deve conter `frontend` e `database`; a lista de volumes deve conter `petclinic-data`.

---

### 3. Adicionar o backend e sua dependência do banco

Adicione o serviço `backend` com a imagem `SEU_DOCKER_ID/spring-petclinic-api:1.0.0`. Configure o perfil Spring, a URL JDBC com o hostname `database` e a publicação da porta `9966`. O backend deve depender de o serviço `database` atingir o estado saudável.

#### Solução

Acrescente a seguinte definição à seção `services`:

```yaml
  backend:
    image: SEU_DOCKER_ID/spring-petclinic-api:1.0.0
    environment:
      - SPRING_PROFILES_ACTIVE=postgres,spring-data-jpa
      - POSTGRES_URL=jdbc:postgresql://database:5432/petclinic
    ports:
      - "9966:9966"
    depends_on:
      database:
        condition: service_healthy
```

Valide a configuração e confira o modelo resultante:

```bash
docker compose config --quiet
docker compose config --services
docker compose config
```

O atributo `depends_on` com `condition: service_healthy` instrui o Compose a aguardar o `healthcheck` do PostgreSQL antes de iniciar o backend. Isso não substitui as políticas de reconexão que a própria aplicação pode precisar durante sua execução.

---

### 4. Conectar os serviços pela rede do projeto

Declare a rede nomeada `petclinic-net` e associe os três serviços a ela. Como backend e banco se comunicam pela rede Docker, não é necessário publicar a porta `5432` do PostgreSQL no host.

#### Solução

Acrescente a configuração `networks` em cada um dos três serviços:

```yaml
    networks:
      - petclinic-net
```

No serviço `frontend`, o bloco deve ficar no mesmo nível de `ports`. No `backend`, deve ficar no mesmo nível de `ports` e `depends_on`. No `database`, deve ficar no mesmo nível de `volumes` e `healthcheck`.

Declare a rede no nível superior:

```yaml
networks:
  petclinic-net:
```

Valide:

```bash
docker compose config --quiet
docker compose config --networks
docker compose config --services
docker compose config
```

O modelo final deve associar `frontend`, `backend` e `database` à rede `petclinic-net`. O banco não possui uma seção `ports`, portanto sua porta `5432` não é publicada no host.

---

### 5. Construir e baixar as imagens

Construa a imagem do frontend e baixe as imagens do backend e do PostgreSQL:

```bash
docker compose config --quiet
docker compose build frontend
docker compose pull backend database
docker image ls
```

A construção do frontend depende da existência de um `Dockerfile` válido e dos arquivos necessários no contexto de construção. O comando `pull` obtém as imagens nomeadas no registry, desde que estejam acessíveis ao usuário.

---

### 6. Iniciar e validar a aplicação

Inicie os serviços em segundo plano. A opção `--build` solicita a construção das imagens que possuem configuração de build antes de iniciar os containers; `--wait` aguarda os serviços estarem em execução ou saudáveis.

```bash
docker compose up -d --build --wait
```

Confira os serviços e os logs:

```bash
docker compose ps
docker compose logs --tail=100
docker compose exec database pg_isready
```

Para investigar um problema, consulte os logs por serviço:

```bash
docker compose logs backend
docker compose logs database
```

Acesse:

- Frontend: [http://localhost:8080](http://localhost:8080)
- Backend: [http://localhost:9966/petclinic/](http://localhost:9966/petclinic/)

#### Detalhes

- O atributo `build` especifica como construir uma imagem a partir do código-fonte e do contexto informado. O atributo `image` identifica a imagem a utilizar ou o nome/tag da imagem produzida pelo build quando ambos são definidos.
- Dentro da rede Compose, o DNS integrado permite que `database` seja resolvido para o container do PostgreSQL. `localhost`, dentro do container backend, apontaria para o próprio backend.
- O `healthcheck` verifica se o serviço está pronto segundo o teste configurado. A condição `service_healthy` faz o Compose esperar que o banco seja considerado saudável antes de iniciar o backend.
- A porta `5432` é acessível entre serviços conectados à mesma rede Docker. A publicação no host é necessária somente quando o acesso precisa ser feito a partir do host ou de outro destino externo à rede Docker.

---

### 7. Verificar a persistência dos dados

Crie um registro de teste usando a aplicação e confirme que está disponível. Em seguida, remova os containers e a rede do projeto sem remover o volume:

```bash
docker compose down
```

Confira os volumes definidos na configuração e recrie os serviços:

```bash
docker compose config --volumes
docker compose up -d --wait
```

Consulte novamente o registro criado.

#### Detalhes

O registro permanece disponível porque os arquivos de dados do PostgreSQL são armazenados no volume nomeado `petclinic-data`, fora da camada gravável do container. O comando `docker compose down` não remove volumes nomeados por padrão.

---

### 8. Limpar o ambiente

Remova os containers e a rede do projeto:

```bash
docker compose down
```

Se os dados não precisarem ser preservados, remova também os volumes:

```bash
docker compose down --volumes
```

> [!WARNING]
> A opção `--volumes` remove o volume nomeado do projeto e os dados persistidos do PostgreSQL. Não a utilize enquanto os dados precisarem ser mantidos.

---

## Arquivo completo `compose.yaml`

O arquivo final deve ficar assim:

```yaml
name: petclinic

services:
  frontend:
    image: spring-petclinic-ui
    build:
      context: .
    ports:
      - "8080:8080"
    networks:
      - petclinic-net

  backend:
    image: SEU_DOCKER_ID/spring-petclinic-api:1.0.0
    environment:
      - SPRING_PROFILES_ACTIVE=postgres,spring-data-jpa
      - POSTGRES_URL=jdbc:postgresql://database:5432/petclinic
    ports:
      - "9966:9966"
    networks:
      - petclinic-net
    depends_on:
      database:
        condition: service_healthy

  database:
    image: postgres:16.3
    env_file:
      - .env
    volumes:
      - petclinic-data:/var/lib/postgresql/data
    networks:
      - petclinic-net
    healthcheck:
      test: ["CMD-SHELL", "pg_isready"]
      interval: 1s
      timeout: 5s
      retries: 10

volumes:
  petclinic-data:

networks:
  petclinic-net:
```

Valide o arquivo final antes de iniciar a aplicação:

```bash
docker compose config --quiet
docker compose config
```

## Resultado esperado

Ao concluir o laboratório, você terá configurado e executado a aplicação Spring PetClinic com Docker Compose, utilizando três serviços, uma rede compartilhada, um volume nomeado para os dados do PostgreSQL e uma verificação de saúde que controla a ordem de inicialização do backend.
