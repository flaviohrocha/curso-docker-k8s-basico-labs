# Construindo imagens + Trabalhando com registries - Guia de comandos

## Gerenciando imagens

### Baixar uma imagem do registry

- **Sintaxe:**
```bash
docker image pull [OPÇÕES] NOME[:TAG|@DIGEST]
```
ou
```bash
docker pull [OPÇÕES] NOME[:TAG|@DIGEST]
```

- **Exemplos:**
```bash
# Baixa a imagem padrão do Docker Hub (tag latest)
docker image pull ubuntu
```
```bash
# Baixa uma tag específica
docker image pull ubuntu:24.04
```
```bash
# Baixa uma imagem identificada por digest
docker image pull ubuntu@sha256:<DIGEST>
```

- Se a tag não for informada, o Docker utiliza `latest` como padrão. O digest permite referenciar um conteúdo específico da imagem.

> [!NOTE]
> **Para saber mais**  
    [Docker Docs | docker image pull](https://docs.docker.com/reference/cli/docker/image/pull/)

### Listar imagens locais

- **Sintaxe:**
```bash
docker image ls [OPÇÕES]
```
ou
```bash
docker images [OPÇÕES]
```

- **Opções relevantes:**
```text
-a, --all       Exibe todas as imagens, incluindo as intermediárias
-q, --quiet     Exibe somente os IDs das imagens
-f, --filter    Filtra os resultados
--no-trunc      Não trunca os valores exibidos
--format        Formata a saída com um template Go
```

- **Exemplos:**
```bash
# Lista as imagens locais
docker image ls
```
```bash
# Lista somente os IDs das imagens
docker image ls -q
```
```bash
# Exibe o ID, o repositório e a tag
docker image ls --format "table {{.ID}}\t{{.Repository}}\t{{.Tag}}"
```

### Exibir o histórico de uma imagem

- **Sintaxe:**
```bash
docker image history NOME[:TAG|ID]
```

- **Exemplo:**
```bash
# Exibe o histórico de construção da imagem
docker image history minha-imagem:1.0
```

- O comando apresenta o histórico associado à imagem, incluindo informações das instruções registradas na construção e das camadas resultantes.

### Inspecionar uma imagem

- **Sintaxe:**
```bash
docker image inspect NOME[:TAG|ID]
```

- **Exemplo:**
```bash
# Exibe informações detalhadas da imagem
docker image inspect minha-imagem:1.0
```

### Consultar o uso de disco pelo Docker

- **Sintaxe:**
```bash
docker system df [OPÇÕES]
```

- **Exemplos:**
```bash
# Exibe um resumo do espaço utilizado
docker system df
```
```bash
# Exibe informações detalhadas sobre o uso de disco
docker system df -v
```

### Remover imagens não utilizadas

- **Sintaxe:**
```bash
docker image prune [OPÇÕES]
```

- **Opções relevantes:**
```text
-a, --all       Remove todas as imagens não utilizadas por containers
-f, --force     Não solicita confirmação
--filter        Restringe a remoção conforme um filtro
```

- **Exemplos:**
```bash
# Remove imagens dangling
docker image prune
```
```bash
# Remove imagens não utilizadas por containers
docker image prune -a
```
```bash
# Remove imagens não utilizadas e dangling criadas há mais de 10 dias,
# sem solicitar confirmação
docker image prune -a --force --filter "until=240h"
```

!!! warning "Atenção"
    A opção `-a` amplia o conjunto de imagens que pode ser removido. Verifique quais imagens não estão sendo utilizadas antes de executar o comando.

> [!NOTE]
> **Para saber mais**  
    [Docker Docs | Understanding the image layers](https://docs.docker.com/get-started/docker-concepts/building-images/understanding-image-layers/)  
    [Docker Docs | Storage drivers](https://docs.docker.com/engine/storage/drivers/)  
    [Docker Docs | OverlayFS storage driver](https://docs.docker.com/engine/storage/drivers/overlayfs-driver/)  
    [Docker Docs | containerd image store with Docker Engine](https://docs.docker.com/engine/storage/containerd)  
    [Docker Docs | Storage drivers - The copy-on-write (CoW) strategy](https://docs.docker.com/engine/storage/drivers/#the-copy-on-write-cow-strategy)  
    [The Linux Kernel | Overlay Filesystem](https://docs.kernel.org/filesystems/overlayfs.html/)  

---

## Construindo imagens

### Construir uma imagem a partir de um Dockerfile

- **Sintaxe:**
```bash
docker build [OPÇÕES] [CONTEXTO]
```
ou
```bash
docker image build [OPÇÕES] [CONTEXTO]
```

- O contexto de construção geralmente é indicado por `.` quando o diretório atual contém o Dockerfile e os arquivos necessários.

- **Exemplos:**
```bash
# Constrói uma imagem com nome e tag
docker build -t minha-imagem:1.0 .
```
```bash
# Utiliza um Dockerfile com outro nome ou caminho
docker build -f docker/Dockerfile -t minha-imagem:1.0 .
```
```bash
# Constrói a imagem com uma tag destinada ao Docker Hub
docker build -t SEU_DOCKER_ID/mensagem:1.0 .
```

### Executar build checks

- **Exemplo:**
```bash
# Executa as verificações estáticas do Dockerfile sem construir a imagem
docker build --check .
```

### Parametrizar o build com `--build-arg`

- Utilize `--build-arg` para fornecer valores às variáveis `ARG` declaradas no Dockerfile.

- **Exemplo de Dockerfile:**
```dockerfile
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-alpine
```

- **Comando:**
```bash
# Substitui o valor padrão do argumento durante o build
docker build --build-arg PHP_VERSION=8.5 -t minha-aplicacao:1.0 .
```

!!! warning "Atenção"
    Não utilize `ARG` nem `ENV` para fornecer senhas, tokens, chaves privadas ou outros secrets necessários durante o build. Utilize os mecanismos de Build Secrets ou SSH mounts.

### Controlar o cache e atualizar a imagem-base

- **Exemplos:**
```bash
# Solicita que o Docker verifique e obtenha versões mais recentes das imagens-base
docker build --pull -t minha-imagem:1.0 .
```
```bash
# Constrói sem reutilizar o cache das instruções
docker build --no-cache -t minha-imagem:1.0 .
```
```bash
# Combina a atualização das imagens-base com a desativação do cache
docker build --pull --no-cache -t minha-imagem:1.0 .
```

### Construir até um estágio específico

- Em um Dockerfile com múltiplos estágios identificados com `AS`, a opção `--target` permite selecionar o estágio que será construído.

- **Exemplo de Dockerfile:**
```dockerfile
FROM php:8.5-cli-alpine AS build
# Instruções de construção

FROM php:8.5-cli-alpine AS runtime
# Instruções da imagem final
```

- **Comando:**
```bash
# Constrói até o estágio identificado como build
docker build --target build -t minha-imagem:build .
```

### Utilizar mounts na instrução `RUN`

- Os mounts do BuildKit disponibilizam arquivos, diretórios, caches ou credenciais durante a execução de uma instrução `RUN`.

#### `RUN --mount=type=bind`

- **Exemplo:**
```dockerfile
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    composer install
```

- Os arquivos do contexto são disponibilizados durante o `RUN`, mas não são adicionados à camada da imagem por meio desses mounts. Por padrão, o bind mount é somente leitura.

#### `RUN --mount=type=cache`

- **Exemplo:**
```dockerfile
RUN --mount=type=cache,target=/tmp/cache \
    composer install
```

- O cache é mantido pelo builder entre builds. A ferramenta executada precisa utilizar o diretório montado para que os arquivos armazenados sejam aproveitados.

### Disponibilizar Build Secrets

- **Exemplo com secret fornecido por arquivo:**

    No Dockerfile:
```dockerfile
RUN --mount=type=secret,id=aws \
    AWS_SHARED_CREDENTIALS_FILE=/run/secrets/aws \
    aws s3 cp ...
```

    No terminal:
```bash
# Disponibiliza o arquivo de credenciais apenas durante o build
docker build --secret id=aws,src="$HOME/.aws/credentials" -t minha-imagem:1.0 .
```

- **Exemplo de secrets disponibilizados como variáveis de ambiente:**

    No Dockerfile:
```dockerfile
RUN --mount=type=secret,id=aws-key-id,env=AWS_ACCESS_KEY_ID \
    --mount=type=secret,id=aws-secret-key,env=AWS_SECRET_ACCESS_KEY \
    --mount=type=secret,id=aws-session-token,env=AWS_SESSION_TOKEN \
    aws s3 cp ...
```

    No terminal, as variáveis correspondentes precisam estar definidas no ambiente local:
```bash
docker build \
  --secret id=aws-key-id,env=AWS_ACCESS_KEY_ID \
  --secret id=aws-secret-key,env=AWS_SECRET_ACCESS_KEY \
  --secret id=aws-session-token,env=AWS_SESSION_TOKEN \
  -t minha-imagem:1.0 .
```

### Utilizar uma chave ou agente SSH durante o build

- Para acessar repositórios privados por SSH, declare um SSH mount no Dockerfile:

```dockerfile
RUN --mount=type=ssh \
    git clone git@github.com:empresa/projeto-privado.git
```

- **Comando de build usando uma chave SSH:**
```bash
docker buildx build \
  --ssh default="$HOME/.ssh/id_ed25519" \
  -t minha-imagem:1.0 .
```

- O identificador `default` associa a origem da credencial informada à instrução `RUN --mount=type=ssh`.

### Utilizar Multi-stage Builds

- **Exemplo:**
```dockerfile
# syntax=docker/dockerfile:1

FROM golang:1.26 AS build
WORKDIR /src
COPY . .
RUN go build -o /bin/app .

FROM scratch
COPY --from=build /bin/app /bin/app
CMD ["/bin/app"]
```

- **Comando:**
```bash
docker build -t minha-aplicacao:1.0 .
```

- Somente o artefato copiado pelo `COPY --from=build` entra no estágio final; ferramentas e arquivos que permaneceram exclusivamente no estágio `build` não são incluídos na imagem final.

> [!NOTE]
> **Para saber mais**  
    [Docker Docs | Docker Build Overview](https://docs.docker.com/build/concepts/overview/)  
    [Docker Docs | Build drivers](https://docs.docker.com/build/builders/drivers/)  
    [BuildKit | Rootless mode](https://github.com/moby/buildkit/blob/master/docs/rootless.md)  
    [Docker Docs | Build context](https://docs.docker.com/build/concepts/context/)  
    [Docker Docs | Dockerfile reference](https://docs.docker.com/reference/dockerfile/)  
    [Docker Docs | Build checks](https://docs.docker.com/reference/build-checks/)  
    [Docker Docs | Dockerfile reference - RUN --mount](https://docs.docker.com/reference/dockerfile/#run---mount/)  
    [Docker Docs | Optimize cache usage in builds](https://docs.docker.com/build/cache/optimize/)  
    [Docker Docs | Build variables](https://docs.docker.com/build/building/variables/)  
    [Docker Docs | Build secrets](https://docs.docker.com/build/building/secrets/)  
    [Docker Docs | Multi-stage builds](https://docs.docker.com/get-started/docker-concepts/building-images/multi-stage-builds/)  
    [Docker Docs | Understand how CMD and ENTRYPOINT interact](https://docs.docker.com/reference/dockerfile/#understand-how-cmd-and-entrypoint-interact/)  

---

## Trabalhando com registries

### Autenticar-se em um registry

- **Sintaxe:**
```bash
docker login [ENDEREÇO_DO_REGISTRY]
```

- **Exemplos:**
```bash
# Login no Docker Hub
docker login
```
```bash
# Login em um registry privado
docker login registry.exemplo.gov.br
```

- Para sair da sessão autenticada:
```bash
docker logout [ENDEREÇO_DO_REGISTRY]
```

- Caso já esteja autenticado pelo Docker Desktop, normalmente não é necessário realizar outro login no terminal. Se não estiver autenticado, siga o fluxo apresentado por `docker login`. Para autenticação com nome de usuário, o Docker pode solicitar um Personal Access Token (PAT) no prompt.

### Identificar uma imagem com o nome e a tag de destino

- **Sintaxe:**
```bash
docker image tag IMAGEM_ORIGEM[:TAG] REGISTRY/NAMESPACE/REPOSITORY[:TAG]
```

- **Exemplos:**
```bash
# Cria uma nova referência para uma imagem local
docker image tag mensagem:latest SEU_DOCKER_ID/mensagem:1.0
```
```bash
# Identifica a imagem para publicação em um registry privado
docker image tag mensagem:latest \
    registry.exemplo.gov.br/projeto/mensagem:1.0
```

- `docker image tag` cria uma nova referência para a imagem existente; não cria uma segunda cópia física da imagem.

### Publicar uma imagem no registry

- **Sintaxe:**
```bash
docker image push NOME[:TAG]
```

- **Exemplo para o Docker Hub:**
```bash
docker image push SEU_DOCKER_ID/mensagem:1.0
```

- **Exemplo para um registry privado:**
```bash
docker image push registry.exemplo.gov.br/projeto/mensagem:1.0
```

- No Docker Hub, não é necessário criar previamente o repositório. Se ainda não existir, o primeiro `push` poderá criá-lo automaticamente, desde que o usuário esteja autenticado, possua permissão para publicar e não haja restrições para o namespace. A visibilidade inicial segue a configuração padrão da conta.

### Baixar uma imagem por tag ou digest

- **Exemplos:**
```bash
# Baixa uma versão identificada por tag
docker image pull SEU_DOCKER_ID/mensagem:1.0
```
```bash
# Baixa uma imagem identificada por digest
docker image pull SEU_DOCKER_ID/mensagem@sha256:<DIGEST>
```

- Uma tag é uma referência nomeada que pode ser atualizada para apontar para outro conteúdo. O digest identifica o conteúdo específico da imagem.

### Referências de imagens

- **Formato geral:**
```text
[HOST[:PORT]/]NAMESPACE/REPOSITORY[:TAG]
```

- **Exemplo com registry privado:**
```text
registry.exemplo.gov.br:5000/equipe/minha-aplicacao:1.0
```

- **Exemplo de imagem no Docker Hub:**
```text
docker.io/SEU_DOCKER_ID/mensagem:1.0
```

> [!NOTE]
> **Para saber mais**  
    [CNCF Distribution | HTTP API V2](https://distribution.github.io/distribution/spec/api/)  
    [Docker Docs | docker image pull](https://docs.docker.com/reference/cli/docker/image/pull/)  
    [Docker Docs | docker image push](https://docs.docker.com/reference/cli/docker/image/push/)  
    [Docker Docs | docker login](https://docs.docker.com/reference/cli/docker/login/)  
    [Docker Docs | Docker Hub quickstart](https://docs.docker.com/docker-hub/quickstart/)  
    [Docker Docs | Settings](https://docs.docker.com/docker-hub/settings/)  
    [CNCF Distribution | Deploy a registry server](https://distribution.github.io/distribution/about/deploying/)  
    [Docker Docs | docker image tag](https://docs.docker.com/reference/cli/docker/image/tag/)  
    [Docker Docs | Push images to a repository](https://docs.docker.com/docker-hub/repos/manage/hub-images/push/)  
