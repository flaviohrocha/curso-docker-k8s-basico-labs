# Armazenando dados de containers + Trabalhando com redes - Guia de comandos

## Armazenando dados de containers

### Criar um volume

- **Sintaxe:**
```bash
docker volume create [OPÇÕES] NOME-DO-VOLUME
```

- **Opções relevantes:**
```text
--driver    Define o driver utilizado para gerenciar o volume. O driver padrão é `local`.
```

- **Exemplos:**
```bash
# Cria um volume gerenciado pelo Docker
docker volume create dados-app
```
```bash
# Cria um volume utilizando o driver local
docker volume create --driver local dados-app
```

### Listar volumes

- **Sintaxe:**
```bash
docker volume ls [OPÇÕES]
```

- **Exemplo:**
```bash
# Lista os volumes conhecidos pelo Docker
docker volume ls
```

### Inspecionar um volume

- **Sintaxe:**
```bash
docker volume inspect NOME-DO-VOLUME
```

- **Exemplo:**
```bash
# Exibe informações detalhadas sobre o volume
docker volume inspect dados-app
```

### Remover volumes

- **Sintaxe:**
```bash
docker volume rm NOME-DO-VOLUME [NOME-DO-VOLUME...]
```

- **Exemplo:**
```bash
# Remove um volume que não esteja em uso
docker volume rm dados-app
```

- Para remover volumes não utilizados:
```bash
docker volume prune
```

!!! warning "Atenção"
    A remoção de um volume exclui os dados armazenados nele. Verifique se o volume não contém dados necessários antes de removê-lo.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Volumes](https://docs.docker.com/engine/storage/volumes/)  
> [Docker Docs | docker volume](https://docs.docker.com/reference/cli/docker/volume/)

---

### Montar volumes em containers

#### Utilizando `-v` ou `--volume`

- **Sintaxe:**
```bash
docker run [OPÇÕES] -v [NOME-DO-VOLUME:]CAMINHO-NO-CONTAINER[:OPÇÕES] IMAGEM
```

- **Opções da montagem:**
```text
rw                Permite leitura e escrita (padrão)
ro                Monta o volume como somente leitura
volume-nocopy     Impede a cópia automática do conteúdo existente no destino
                  para um volume vazio
```

- Se o nome do volume não for informado, o Docker cria um volume anônimo.

- **Exemplo:**
```bash
# Monta o volume dados-app no diretório /app/dados
docker run -d \
  --name app-com-volume \
  -v dados-app:/app/dados \
  nginx:latest
```

#### Utilizando `--mount`

- **Sintaxe:**
```bash
docker run [OPÇÕES] --mount type=volume,source=NOME-DO-VOLUME,target=CAMINHO-NO-CONTAINER[,OPÇÕES] IMAGEM
```

- **Opções da montagem:**
```text
source, src                  Nome do volume
target, dst, destination      Caminho dentro do container onde o volume será montado
readonly, ro                 Monta o volume como somente leitura
volume-nocopy                Impede a cópia automática do conteúdo existente no
                             destino para um volume vazio
volume-subpath               Monta um subdiretório específico do volume; ele deve
                             existir previamente
volume-opt                   Define opções adicionais para o driver do volume
```

- **Exemplo:**
```bash
# Monta explicitamente o volume no container
docker run -d \
  --name app-com-volume \
  --mount type=volume,source=dados-app,target=/app/dados \
  nginx:latest
```

- Para evitar que o Docker copie para um volume vazio os arquivos existentes no diretório de destino da imagem, utilize `volume-nocopy`:
```bash
docker run -d \
  --name app-sem-copia \
  --mount type=volume,source=dados-app,target=/app,volume-nocopy \
  nginx:latest
```

- Para montar somente um subdiretório já existente dentro de um volume, utilize `volume-subpath`:
```bash
docker run -d \
  --name app-subdiretorio \
  --mount type=volume,source=dados-app,target=/dados,volume-subpath=app1 \
  nginx:latest
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Volumes](https://docs.docker.com/engine/storage/volumes/)  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)

---

### Utilizar bind mounts

- Um bind mount conecta um arquivo ou diretório do host a um caminho dentro do container.

#### Utilizando `-v` ou `--volume`

- **Sintaxe:**
```bash
docker run [OPÇÕES] -v CAMINHO-NO-HOST:CAMINHO-NO-CONTAINER[:OPÇÕES] IMAGEM
```

- **Opções da montagem:**
```text
rw                Permite leitura e escrita (padrão)
ro                Monta o recurso como somente leitura
z                 Permite o compartilhamento do conteúdo entre containers com SELinux
Z                 Marca o conteúdo como privado para um container com SELinux
rprivate          Propagação recursiva privada (padrão)
private           Propagação privada
rshared           Propagação recursiva compartilhada
shared            Propagação compartilhada
rslave            Propagação recursiva slave
slave             Propagação slave
```

- **Exemplo:**
```bash
# Compartilha o diretório target do diretório atual com /app no container
docker run -d \
  --name app-com-bind \
  -v "$(pwd)/target:/app" \
  nginx:latest
```

- Com `-v` ou `--volume`, se o caminho de origem do bind mount não existir no host, o Docker cria automaticamente esse caminho como diretório.

#### Utilizando `--mount`

- **Sintaxe:**
```bash
docker run [OPÇÕES] --mount type=bind,source=CAMINHO-NO-HOST,target=CAMINHO-NO-CONTAINER[,OPÇÕES] IMAGEM
```

- **Opções da montagem:**
```text
source, src                  Caminho do arquivo ou diretório no host; pode ser absoluto ou relativo
target, dst, destination     Caminho absoluto dentro do container onde o recurso será montado
readonly, ro                 Monta o recurso como somente leitura
bind-propagation              Define a propagação: rprivate, private, rshared, shared, rslave ou slave
bind-create-src               Cria automaticamente o diretório de origem no host, se ele não existir
```

- **Exemplos:**
```bash
# Monta um diretório do host como somente leitura
docker run -d \
  --name app-bind-readonly \
  --mount type=bind,source="$(pwd)/target",target=/app,readonly \
  nginx:latest
```
```bash
# Cria o diretório de origem se ele não existir
docker run -d \
  --name app-bind-create-src \
  --mount type=bind,source="$(pwd)/dados",target=/app/dados,bind-create-src \
  nginx:latest
```

- `source` indica o caminho do arquivo ou diretório no host; `target` indica onde será montado dentro do container.
- Por padrão, `--mount type=bind` retorna um erro se o caminho de origem não existir. A opção `bind-create-src` permite criar o diretório de origem automaticamente.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Bind mounts](https://docs.docker.com/engine/storage/bind-mounts/)  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)

---

### Utilizar `tmpfs` mounts

- `tmpfs` disponibiliza armazenamento temporário em memória, sem persistir os dados na camada gravável do container.

#### Utilizando `--tmpfs`

- **Sintaxe:**
```bash
docker run [OPÇÕES] --tmpfs CAMINHO-NO-CONTAINER[:OPÇÕES] IMAGEM
```

- **Opções de `tmpfs`:**
```text
ro          Somente leitura
rw          Leitura e escrita
nosuid      Não considera os bits setuid e setgid
nodev       Impede o uso funcional de arquivos de dispositivo
noexec      Impede a execução de binários
size        Define o tamanho da montagem
mode        Define as permissões
uid         Define o ID do usuário proprietário
gid         Define o ID do grupo proprietário
nr_inodes   Define o número máximo de inodes
nr_blocks   Define o número máximo de blocos
```

- **Exemplo:**
```bash
docker run -d \
  --name app-tmpfs \
  --tmpfs /app/tmp \
  nginx:latest
```

#### Utilizando `--mount`

- **Sintaxe:**
```bash
docker run [OPÇÕES] --mount type=tmpfs,target=CAMINHO-NO-CONTAINER[,OPÇÕES] IMAGEM
```

- **Opções da montagem:**
```text
target, dst, destination     Caminho dentro do container onde o tmpfs será montado
tmpfs-size                   Define o tamanho máximo da montagem em bytes
tmpfs-mode                   Define as permissões da montagem em formato octal
```

- **Exemplo:**
```bash
docker run -d \
  --name app-tmpfs \
  --mount type=tmpfs,target=/app/tmp,tmpfs-size=67108864,tmpfs-mode=1770 \
  nginx:latest
```

- `tmpfs-size` define o tamanho máximo do mount em bytes; `tmpfs-mode` define as permissões em formato octal.
- Os dados de `tmpfs` são temporários e se perdem quando o mount deixa de existir. O uso de memória é contabilizado no limite de memória do container.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | tmpfs mounts](https://docs.docker.com/engine/storage/tmpfs/)  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)

---

### Utilizar image mounts

- Image mounts permitem disponibilizar o conteúdo de uma imagem existente dentro de outro container, como um sistema de arquivos somente leitura. A imagem de origem precisa estar disponível localmente, e esse recurso requer o containerd image store.
- A opção `image-subpath` permite montar apenas um subdiretório da imagem de origem. Image mounts utilizam exclusivamente `--mount` e são sempre somente leitura.

- **Sintaxe:**
```bash
docker run [OPÇÕES] --mount type=image,source=IMAGEM,target=CAMINHO-NO-CONTAINER[,image-subpath=SUBDIRETÓRIO] IMAGEM-DESTINO
```

- **Exemplo:**
```bash
# Baixa a imagem que será utilizada como origem
docker image pull busybox:musl
```
```bash
# Monta a imagem busybox em /dbg e executa um comando disponibilizado por ela
docker run --rm \
  --mount type=image,source=busybox:musl,target=/dbg \
  alpine:latest \
  /dbg/bin/echo "hello from busybox"
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Storage](https://docs.docker.com/engine/storage/)  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)

---

## Trabalhando com redes

### Identificar o modo de rede de um container

- Ao executar um container sem a opção `--network`, o Docker o conecta à rede padrão apropriada ao ambiente. No Docker Engine para Linux, a rede padrão é `bridge`.

- **Exemplo:**
```bash
# Executa um container e testa a conectividade externa
docker run --rm -it busybox ping -c 1 docker.com
```

### Executar um container usando a rede `host`

- O modo `host` compartilha a pilha de rede do host com o container. Nesse modo, o mapeamento de portas com `-p` não é necessário.

- **Exemplo:**
```bash
docker run --network host nginx:latest
```

- No Docker Desktop, o suporte a `host` depende da versão e da habilitação da funcionalidade correspondente.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Networking overview](https://docs.docker.com/engine/network/)  
> [Docker Docs | Network drivers](https://docs.docker.com/engine/network/drivers/)  
> [Docker Docs | Bridge network driver](https://docs.docker.com/engine/network/drivers/bridge/)  
> [Docker Docs | Host network driver](https://docs.docker.com/engine/network/drivers/host/)

---

### Criar uma rede definida pelo usuário

- **Sintaxe:**
```bash
docker network create [OPÇÕES] NOME-DA-REDE
```

- **Opções relevantes:**
```text
-d, --driver       Define o driver utilizado para gerenciar a rede. Se omitido, o padrão é `bridge`.
--subnet            Define explicitamente a sub-rede da rede.
--ipv6              Habilita o suporte a IPv6.
--ipv4=false        Desabilita a atribuição de endereços IPv4.
```

- **Exemplos:**
```bash
# Cria uma rede bridge definida pelo usuário (forma longa)
docker network create --driver bridge minha-rede
```
```bash
# Cria uma rede bridge definida pelo usuário (forma curta)
docker network create -d bridge br0
```
```bash
# Cria uma rede com uma sub-rede IPv4 definida
docker network create \
  --subnet 192.0.2.0/24 \
  minha-rede-ipv4
```
```bash
# Cria uma rede com suporte a IPv6
docker network create --ipv6 minha-rede-ipv6
```
```bash
# Cria uma rede sem atribuição de endereços IPv4
docker network create --ipv4=false minha-rede-sem-ipv4
```

- Para criar uma rede e iniciar um container conectado a ela:
```bash
docker run -it --rm \
  --network=minha-rede \
  --name teste-rede \
  busybox
```

- Containers conectados à mesma rede definida pelo usuário podem se comunicar usando seus nomes ou aliases, sem depender de endereços IP fixos.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Networking overview](https://docs.docker.com/engine/network/)  
> [Docker Docs | Network drivers](https://docs.docker.com/engine/network/drivers/)  
> [Docker Docs | docker network create](https://docs.docker.com/reference/cli/docker/network/create/)

---

### Conectar um container a uma rede

- **Sintaxe:**
```bash
docker network connect [OPÇÕES] NOME-DA-REDE NOME-DO-CONTAINER
```

- **Exemplo:**
```bash
# Conecta um container existente à rede minha-rede
docker network connect minha-rede app
```

- **Opção relevante:** `--alias` define um nome alternativo pelo qual o container pode ser localizado nessa rede.

- Para definir um alias de rede:
```bash
docker network connect \
  --alias frontend \
  minha-rede \
  app
```

- Para desconectar um container:
```bash
docker network disconnect minha-rede app
```

- **Opção relevante para desconexão:** `-f` ou `--force` força a desconexão do container da rede.
```bash
docker network disconnect --force minha-rede app
```

- Um container pode estar conectado simultaneamente a várias redes. Durante a criação, informe a opção `--network` mais de uma vez para conectar o container a redes distintas.

#### Conectar um container a várias redes e definir o gateway padrão

- Quando um container está conectado a várias redes, `gw-priority` define a prioridade para a seleção do gateway padrão. O valor padrão é `0`; a rede com a maior prioridade será selecionada como gateway padrão.

- **Exemplo:**
```bash
docker run \
  --network name=gwnet,gw-priority=1 \
  --network anet1 \
  --name myctr \
  myimage
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker network connect](https://docs.docker.com/reference/cli/docker/network/connect/)  
> [Docker Docs | docker network disconnect](https://docs.docker.com/reference/cli/docker/network/disconnect/)

---

### Publicar portas de um container

- **Sintaxe:**
```bash
docker run [OPÇÕES] -p [IP-DO-HOST:]PORTA-DO-HOST:PORTA-DO-CONTAINER[/PROTOCOLO] IMAGEM
```

- **Exemplos:**
```bash
# Publica a porta 80 do container na porta 8080 do host
docker run -d -p 8080:80 nginx:latest
```
```bash
# Publica a porta do container somente na interface de loopback do host
docker run -d -p 127.0.0.1:8080:80 nginx:latest
```
```bash
# Publica a porta 8080 do host para a porta 80/UDP do container
docker run -d -p 8080:80/udp nginx:latest
```
```bash
# Publica portas TCP e UDP
docker run -d \
  -p 8080:80/tcp \
  -p 8080:80/udp \
  nginx:latest
```

- Para consultar o mapeamento de portas de um container:
```bash
docker port NOME-DO-CONTAINER
```

!!! warning "Atenção"
    Se o IP do host não for especificado em `-p`, o Docker normalmente publica a porta em todos os endereços do host. Para limitar o acesso ao próprio host, utilize `127.0.0.1` como no segundo exemplo.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Port publishing and mapping](https://docs.docker.com/engine/network/port-publishing/)  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)

---

### Configurar o endereço IP de uma rede e de um container

- Para atribuir um endereço IP explicitamente, crie primeiro uma rede com sub-rede definida e informe um endereço não utilizado dentro dessa sub-rede.

- **Exemplo:**
```bash
# Cria uma rede com sub-rede IPv4
docker network create \
  --subnet 192.0.2.0/24 \
  rede-estatica
```
```bash
# Inicia um container com um endereço IPv4 estático nessa rede
docker run -d \
  --name app-ip-estatico \
  --network rede-estatica \
  --ip 192.0.2.10 \
  nginx:latest
```

- Para definir endereços manualmente, `docker run` disponibiliza as opções `--ip` (IPv4) e `--ip6` (IPv6). O endereço precisa pertencer à sub-rede configurada e não pode estar em uso por outro endpoint.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker network create](https://docs.docker.com/reference/cli/docker/network/create/)  
> [Docker Docs | Network drivers](https://docs.docker.com/engine/network/drivers/)

---

### Configurar DNS e nome de host

- É possível definir um servidor DNS, um domínio de pesquisa DNS e um nome de host ao criar um container.

- **Exemplo:**
```bash
docker run -d \
  --name app-dns \
  --hostname app-interno \
  --dns 8.8.8.8 \
  --dns-search exemplo.local \
  nginx:latest
```

- **Opções de DNS e nome de host:**
```text
--dns          Define o endereço de um servidor DNS
--dns-search   Define domínios de pesquisa DNS
--dns-opt      Define opções adicionais do resolvedor DNS
--hostname     Define o nome de host do container
```

- Em redes definidas pelo usuário, o DNS integrado do Docker permite que os containers sejam localizados por nome ou alias.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | Networking overview](https://docs.docker.com/engine/network/)  
> [Docker Docs | Network drivers](https://docs.docker.com/engine/network/drivers/)

---

### Compartilhar a pilha de rede de outro container

- A opção `--network container:<NOME-OU-ID>` faz com que o container utilize a mesma pilha de rede de outro container.

- **Exemplo:**
```bash
# Inicia o Redis vinculado ao loopback do próprio namespace de rede
docker run -d \
  --name redis \
  redis:7-alpine \
  redis-server --bind 127.0.0.1
```
```bash
# Executa o cliente Redis utilizando a pilha de rede do container redis
docker run --rm -it \
  --network container:redis \
  redis:7-alpine \
  redis-cli -h 127.0.0.1
```

- Nesse modo, o segundo container compartilha o namespace de rede do primeiro. Não é possível configurar de forma independente para essa pilha compartilhada as opções `--hostname`, `--dns`, `--dns-search`, `--dns-option`, `--mac-address`, `--publish`, `--publish-all` e `--expose`.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker container run](https://docs.docker.com/reference/cli/docker/container/run/)  
> [Docker Docs | Networking overview](https://docs.docker.com/engine/network/)

---

### Listar redes

- **Sintaxe:**
```bash
docker network ls [OPÇÕES]
```

- **Opções relevantes:**
```text
-f, --filter    Filtra as redes por critérios, como driver, name, scope, type, id ou label
--format        Define o formato de saída usando um template Go ou JSON
--no-trunc      Exibe os IDs completos, sem truncá-los
-q, --quiet     Exibe somente os IDs
```

- Os principais valores para `--filter` são `driver`, `id`, `label`, `name`, `scope` e `type`. O filtro `type` aceita `builtin` (redes predefinidas pelo Docker) ou `custom` (redes definidas pelo usuário).

- Os campos mais usados com `--format` incluem `.ID`, `.Name`, `.Driver`, `.Scope`, `.IPv6`, `.Internal`, `.Labels` e `.CreatedAt`.

- **Exemplos:**
```bash
# Lista todas as redes
docker network ls
```
```bash
# Lista somente os IDs das redes
docker network ls -q
```
```bash
# Lista somente redes bridge
docker network ls --filter driver=bridge
```
```bash
# Lista redes definidas pelo usuário
docker network ls --filter type=custom
```
```bash
# Exibe o ID e o driver de cada rede
docker network ls --format "{{.ID}}: {{.Driver}}"
```
```bash
# Exibe os campos em formato JSON
docker network ls --format json
```
```bash
# Lista redes com escopo swarm
docker network ls --filter scope=swarm
```
```bash
# Exibe os IDs completos
docker network ls --no-trunc
```

- O comando também aceita o alias `docker network list [OPÇÕES]`.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker network ls](https://docs.docker.com/reference/cli/docker/network/ls/)

---

### Inspecionar uma rede

- **Sintaxe:**
```bash
docker network inspect [OPÇÕES] NOME-DA-REDE [NOME-DA-REDE...]
```

- **Opções relevantes:**
```text
-f, --format     Define o formato da saída usando um template Go ou JSON
-v, --verbose    Exibe informações detalhadas para diagnóstico
```

- **Exemplo:**
```bash
# Exibe detalhes da rede e dos containers conectados
docker network inspect minha-rede
```

- Para consultar o endereço IP atribuído a um container:
```bash
docker inspect \
  --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' \
  NOME-DO-CONTAINER
```

- Para obter saída detalhada para diagnóstico:
```bash
docker network inspect --verbose minha-rede
```

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker network inspect](https://docs.docker.com/reference/cli/docker/network/inspect/)  
> [Docker Docs | docker container inspect](https://docs.docker.com/reference/cli/docker/container/inspect/)

---

### Remover redes

- **Sintaxe:**
```bash
docker network rm NOME-DA-REDE [NOME-DA-REDE...]
```

- **Exemplo:**
```bash
docker network rm minha-rede
```

- **Opções relevantes:**
```text
--filter        Aplica filtros, por exemplo, `until=<timestamp>`
-f, --force     Não solicita confirmação da operação
```

- Para remover redes não utilizadas:
```bash
docker network prune
```

- Para não solicitar confirmação:
```bash
docker network prune --force
```

!!! warning "Atenção"
    Uma rede que ainda esteja conectada a containers não pode ser removida até que esses containers sejam desconectados ou removidos. `docker network prune` remove todas as redes não utilizadas que se enquadrem nos critérios da operação; confira o resultado antes de utilizar opções que dispensam confirmação.

> [!NOTE]
> **Para saber mais**  
> [Docker Docs | docker network rm](https://docs.docker.com/reference/cli/docker/network/rm/)  
> [Docker Docs | docker network prune](https://docs.docker.com/reference/cli/docker/network/prune/)  
> [Docker Docs | docker network](https://docs.docker.com/reference/cli/docker/network/)
