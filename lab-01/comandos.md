# Introdução ao Docker - Guia de comandos

## Ciclo de vida de containers

### Comandos para manipulação de Imagens

#### Baixar imagem do registry

- **Sintaxe:**
```bash
docker image pull [OPÇÕES] [REGISTRO PRIVADO/] NOME [:TAG]
```
ou
```bash
docker pull [OPÇÕES] [REGISTRO PRIVADO/] NOME [:TAG]
```

- **Exemplo:**
```bash
# Baixa imagem padrão (tag latest)

docker image pull php
```
```bash
# Baixa tag específica

docker pull php:8
```


#### Subir imagem para o registry

- **Sintaxe:**
```bash
docker image push [OPÇÕES] [REGISTRO PRIVADO/] NOME [:TAG]
```
ou
```bash
docker push [OPÇÕES] [REGISTRO PRIVADO/] NOME [:TAG]
```

- **Exemplo:**
```bash
docker image push minhaimagem
```


#### Construir uma imagem a partir do Dockerfile

- **Sintaxe:**
```bash
docker image build [-t NOME] .
```
ou
```bash
docker build [-t NOME] .
```

- **Exemplo:**
```bash
docker image build -t minhaimagem .
```


#### Nomear ou aplicar uma tag em uma imagem

- **Sintaxe:**
```bash
docker image tag IMAGEM_INICIAL[:TAG] IMAGEM_FINAL[:TAG]
```
ou
```bash
docker tag IMAGEM_INICIAL[:TAG] IMAGEM_FINAL[:TAG]
```

- **Exemplo:**
```bash
docker image tag imagemdeteste:1.0.0 registry.exemplo.br/projeto/imagemdeteste:1.0.0
```


> Esse comando é comumente utilizado para modificar a nomenclatura da imagem para realizar o push para registries privados


#### Listar as imagens presentes no host

- **Sintaxe:**
```bash
docker image ls
```
```bash
Opções:

  -a, --all             Exibe todas as imagens (padrão não exibe as imagens intermediárias)
  -f, --filter          Filtra a saída de acordo com condições definidas
  --format string       Formata saída usando um template Go
  --no-trunc            Não trunca a saída
  -q, --quiet           Mostra apenas IDs númericos

```
ou
```bash
docker images
```

- **Exemplo:**
```bash
# Lista as imagens do host

docker image ls
```
```bash
# Lista somente os IDs das imagens

docker image ls -q
```
```bash
# Formata a saída

docker image ls --format "{{.ID}}: {{.Repository}}"

docker image ls --format "table {{.ID}}\t{{.Repository}}\t{{.Tag}}"
```


#### Remover imagem do host

- **Sintaxe:**
```bash
docker image rm [OPÇÕES] NOME/ID
```
```bash
Opções:

  -f      Força a remoção da imagem
```
ou 
```bash
docker rmi [OPÇÕES] NOME/ID
```

- **Exemplo:**
```bash
docker image rm php:8
```


#### Exibir histórico de criação de imagem (camadas)

- **Sintaxe:**
```bash
docker image history NOME/ID
```

- **Exemplo:**
```bash
docker image history php
```


#### Exibir informações detalhadas sobre as imagens

- **Sintaxe:**
```bash
docker image inspect NOME/ID
```

- **Exemplo:**
```bash
docker image inspect php
```


#### Remover imagens não utilizadas (não referenciadas e "dangling")

- **Sintaxe:**
```bash
docker image prune [OPÇÕES]
```
```bash
Opções:

  -a, --all             Remove todas as imagens não utilizadas, não somente as imagens dangling
      --filter          Permite que seja informado um filtro (p.ex. 'until=<timestamp>')
  -f, --force           Não solicita confirmação
```

- Imagens *dangling* são imagens sem tag que não estão sendo referenciadas por outra referência de imagem, como por exemplo, imagens que foram substituídas por uma versão mais recente com o mesmo nome e tag

- Imagens *dangling* não tem uso e com o tempo podem lotar o seu espaço de armazenamento

- Imagens *dangling* aparecem com nome e tag igual a `<none>:<none>` quando executado o comando `docker image ls`. Essas imagens não devem ser confundidas com as imagens `<none>:<none>` que são mostradas na saída do comando `docker image ls -a`, nesse caso são imagens intermediárias das imagens útéis

- Imagens não referenciadas são imagens presentes no host, mas que não estão vinculadas a nenhum container em execução ou parado

- **Exemplo:**
```bash
# Remove somente as imagens "dangling"

docker image prune
```
```bash
# Remove todas as imagens não referenciadas por nenhum container e "dangling"

docker image prune -a
```
```bash
# Remove todas as imagens não referenciadas e "dangling" criadas a mais de 10 dias
# sem solicitar confirmação 

 docker image prune -a --force --filter "until=240h"
```


### Comandos para utilização de registries Docker

#### Realizar login no registry privado

- **Sintaxe:**
```bash
docker login [REGISTRY PRIVADO]
```

- **Exemplo:**
```bash
docker login registry.trf1.jus.br
```


#### Realizar logout no registry privado

- **Sintaxe:**
```bash
docker logout [REGISTRY PRIVADO]
```

- **Exemplo:**
```bash
docker logout registry.trf1.jus.br
```


#### Pesquisar imagens no Docker Hub

- **Sintaxe:**
```bash
docker search [OPÇÕES] TERMO
```
```bash
Opções:
  -f, --filter      Filtra a saída de acordo com condições definidas
                    Filtros:  stars (int) - número de estrelas da imagem
                              is-automated (boolean - true ou false) - se possui build automatizado
                              is-official (boolean - true ou false) - se a imagem é oficial
  --format string   Formata saída usando um template Go
  --limit int       Número máximo de resultados (default 25)
  --no-trunc        Não trunca a saída
```

- **Exemplo:**
```bash
# Pesquisa imagem no Docker Hub com o termo java (nome da imagem ou descrição)

docker search java
```
```bash
# Pesquisa imagem no Docker Hub com o termo java e se é oficial

docker search -f is-official=true java
```
```bash
# Formata a saída

docker search --format "{{.Name}}: {{.StarCount}}" java
```


### Comandos para execução de containers Docker

#### Executar o container a partir da imagem

- **Sintaxe:**
```bash
docker container run [OPÇÕES] [REGISTRO PRIVADO/] IMAGEM[:TAG] [COMANDO] [ARG...]
```
ou
```bash
docker run [OPÇÕES] [REGISTRO PRIVADO/] IMAGEM[:TAG] [COMANDO] [ARG...]
```
```bash
Opções:

  -d          Execução do container em background
  -i          Modo interativo. Mantém o STDIN aberto.
  -t          Aloca uma pseudo TTY
  –-rm        Remove o container após finalização
              (Não funciona com -d)
  –-name      Atribui um nome ao container
```

- **Exemplo:**
```bash
# Executa o container

docker container run php:8.5
```
```bash
# Executa o container em background

docker container run -d php:8.5
```
```bash
# Executa o container em background e atribui um nome

docker container run -d --name php-app php:8.5
```
```bash
# Executa o container e o remove automaticamente na finalização

docker container run --rm php:8.5
```
```bash
# Executa o container e roda um comando

docker container run ubuntu cat /etc/os-release
```

##### Publicar portas dos containers (`-p`)

- A opção **`-p`** ou **`--publish`** publica uma porta do container no host, permitindo que um serviço executado dentro do container seja acessado por meio do host

- A porta do container não é automaticamente disponibilizada no host apenas porque um processo está escutando nela. Para acessá-la por meio do host, é necessário publicar a porta

- **Sintaxe:**
```bash
docker container run -p <PORTA_HOST>:<PORTA_CONTAINER> IMAGEM
```

- **Exemplo:**
```bash
docker container run -it --rm -p 8888:8080 tomcat:8.0
```

- A interface do host na qual a porta será publicada também pode ser especificada
```bash
docker container run -p 127.0.0.1:80:8080 IMAGEM
```
Nesse caso, a porta `8080` do container será publicada na porta `80` do host, somente na interface de loopback `127.0.0.1`

- Quando a porta do host é omitida, o Docker atribui uma porta efêmera ao host. O mapeamento pode ser consultado com `docker container port <ID|NOME>`

- Não é necessário que a porta publicada no host seja igual à porta utilizada pelo serviço dentro do container. Por exemplo, `8888:8080` publica a porta `8080` do container como porta `8888` no host

- Não é possível publicar simultaneamente a mesma combinação de endereço IP do host, porta e protocolo para containers diferentes

- É possível utilizar a opção **`-p`** várias vezes no mesmo comando para publicar várias portas:

- **Exemplo:**
```bash
docker container run \
  -p 8080:80 \
  -p 8443:443 \
  nginx
```

- A opção **`-P`** ou **`--publish-all`** publica todas as portas explicitamente expostas pelo container no host, atribuindo portas efêmeras. Essas portas podem ter sido declaradas pela instrução `EXPOSE` na imagem ou especificadas por meio da opção `--expose`


##### Definir variáveis de ambiente (`-e`, `--env`)

- A opção **`-e`** ou **`--env`** é utilizada para definir variáveis de ambiente dentro do container

- **Sintaxe:**
```bash
docker container run -e VAR1=valor IMAGEM
```

- **Exemplo:**
```bash
docker container run \
  -e VAR1=val \
  -e VAR2="val 2" \
  debian env
```

- É possível definir variáveis de ambiente que não existiam anteriormente na imagem

- Também é possível substituir o valor de uma variável de ambiente definida originalmente na imagem

- É possível utilizar várias vezes as opções **`-e`** e **`--env`** no mesmo comando:

- **Exemplo:**
```bash
docker container run \
  -e VAR1=valor1 \
  -e VAR2=valor2 \
  -e VAR3=valor3 \
  IMAGEM
```

- A opção **`--env-file`** permite definir variáveis de ambiente a partir de um arquivo. O arquivo pode utilizar as seguintes formas:

- **Exemplo:**
```bash
# arquivo .env.test
VAR1=valor
VAR2
```
```bash
docker container run \
  --env-file=.env.test \
  IMAGEM
```

- Quando uma variável é especificada como `VAR=valor`, o valor informado no arquivo é utilizado. Quando especificada apenas como `VAR`, o Docker procura seu valor no ambiente local

- O Docker define automaticamente algumas variáveis de ambiente para containers Linux, como `HOME`, `HOSTNAME`, `PATH` e `TERM`. A variável `TERM` é definida quando o container é executado com um pseudo-TTY. O Docker não define automaticamente essas variáveis para containers Windows da mesma forma


##### Mapeamento de diretórios do host com `-v`

- A opção **`-v`** ou **`--volume`** permite montar um diretório ou arquivo do host diretamente em um caminho dentro do container

- **Sintaxe:**
```bash
docker container run -v <CAMINHO_HOST>:<CAMINHO_CONTAINER> IMAGEM
```

- **Exemplo:**
```bash
docker container run \
  -v /dados:/app/dados \
  minha-aplicacao
```
    - Nesse exemplo:
        - O diretório `/dados` do host será disponibilizado dentro do container no caminho `/app/dados`
        - Qualquer arquivo criado pelo processo do container em `/app/dados` será armazenado no diretório `/dados` do host. Da mesma forma, arquivos existentes em `/dados` ficarão disponíveis para o container por meio de `/app/dados`


> A opção `-v` possui outras formas de utilização, inclusive para volumes gerenciados pelo Docker. Esses conceitos e a sintaxe detalhada das montagens serão apresentados posteriormente, na aula **Armazenando dados de containers**.


##### Políticas de reinicialização (`--restart`)

- A opção **`--restart`** permite definir a política de reinicialização do container, determinando em quais condições o Docker deve tentar iniciá-lo novamente automaticamente quando seu processo termina

- **Sintaxe:**
```bash
docker container run --restart=<POLÍTICA> IMAGEM
```

- **Exemplo:**
```bash
docker container run --restart=always redis
```

- O Docker suporta as seguintes políticas:

    | POLÍTICA                   | RESULTADO                                                                                                                                                              |
    | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
    | `no`                       | Não reinicia automaticamente o container. É o padrão.                                                                                                                  |
    | `on-failure[:max-retries]` | Reinicia o container somente quando o processo termina com um código de saída diferente de zero. `max-retries` especifica opcionalmente o número máximo de tentativas. |
    | `always`                   | Reinicia o container independentemente do código de saída. Se o container estiver parado quando o daemon do Docker for reiniciado, ele também será iniciado novamente. |
    | `unless-stopped`           | Semelhante a `always`, mas, se o container tiver sido parado manualmente, ele não será reiniciado automaticamente quando o daemon do Docker for reiniciado.            |

- **Exemplo:**
```bash
docker container run \
  --restart=on-failure:5 \
  minha-aplicacao
```
Nesse caso, o Docker tentará reiniciar o container quando o processo terminar com código de saída diferente de zero, limitando as tentativas conforme a política configurada


#### Exibir os containers em execução

- **Sintaxe:**
```bash
docker container ls [OPÇÕES]
```
ou
```bash
docker ps [OPÇÕES]
```
```bash
Opções:

  -a, --all             Exibe todos os containers (padrão exibe somente em execução)
  -f, --filter          Filtra a saída de acordo com condições definidas
  --format string       Formata saída usando um template Go
  -n, --last int        Mostra n últimos containers criados (inclui todos os estados) (default -1)
  -l, --latest          Mostra o último container criado
  --no-trunc            Não trunca a saída
  -q, --quiet           Mostra apenas IDs númericos

```

- **Exemplo:**
```bash
# Lista os containers em execução no host

docker container ls
```
```bash
# Lista somente os IDs dos containers

docker container ls -q
```
```bash
# Lista todos os containers do host (inclusive parados)

docker container ls -a
```
```bash
# Lista containers filtrando pelo nome

docker container ls --filter "name=php-app"
```
```bash
# Lista containers filtrando somente os finalizados sem erro

docker container ls -a --filter 'exited=0'
```
```bash
# Lista containers com status de pausado

docker container ls --filter status=paused
```
```bash
# Lista containers que foram criado a partir de uma imagem específica ou outras imagens que tiveram essa como base

docker container ls -a --filter ancestor=php:8.5
```
```bash
# Formata a saída

docker container ls --format "{{.ID}}: {{.Command}}"
```
```bash
docker container ls --format "table {{.ID}}\t{{.Labels}}"
```


#### Inicializar e finalizar um container

- **Sintaxe:**
```bash
docker container start NOME/ID
```
ou
```bash
docker start NOME/ID
```
```bash
docker container stop NOME/ID
```
ou
```bash
docker stop NOME/ID
```

- **Exemplo:**
```bash
# Finaliza o container de nome php-app

docker container stop php-app
```
```bash
# Inicializa o container pelo ID

docker container start 36cc6767b041

```

#### Exibir informações detalhadas sobre os containers

- **Sintaxe:**
```bash
docker container inspect NOME/ID
```
ou
```bash
docker inspect NOME/ID
```

- **Exemplo:**
```bash
docker container inspect php-app
```

#### Remover containers do host

- **Sintaxe:**
```bash
docker container rm [OPÇÕES] NOME/ID
```
ou
```bash
docker rm [OPÇÕES] NOME/ID
```
```bash
Opções:

  -f, --force     Força a remoção de containers em execução (usa SIGKILL)
  -v, --volumes   Remove volumes associados ao container
```

- **Exemplo:**
```bash
docker container rm php-app

```


#### Executar comandos em um container

- **Sintaxe:**
```bash
docker container exec [OPÇÕES] NOME/ID COMANDO [ARG...]
```
ou
```bash
docker exec [OPÇÕES] NOME/ID COMANDO [ARG...]
```
```bash
Opções:

  -d          Execução o comando em background
  -i          Modo interativo. Mantém o STDIN aberto
  -t          Aloca uma pseudo TTY
  -u          Define um usuário ou UID para execução
  -e          Define váriaveis de ambiente
```

- **Exemplo:**
```bash
# Executa um comando touch em um container em execução

docker container exec php-app touch /tmp/teste.txt
```
```bash
# Inicializa uma sessão no container em execução

docker container exec -i -t php-app /bin/bash

```


#### Remover containers parados

- **Sintaxe:**
```bash
docker container prune
```
ou
```bash
docker prune
```
```bash
Opções:

      --filter          Permite que seja informado um filtro (p.ex. 'until=<timestamp>')
  -f, --force           Não solicita confirmação
```

- **Exemplo:**
```bash
# Remove todos os containers parados

docker container prune
```
```bash
# Remove todos os containers criados a mais de 5 minutos sem solicitar confirmação

docker container prune --force --filter "until=5m"
```


### Comandos de sistema para o Docker

#### Exibir informações de sistema do Docker

- **Sintaxe:**
```bash
docker system info [OPÇÕES]
```
```bash
Opções:

  --format string   Formata saída usando um template Go
  
```

- **Exemplo:**
```bash
# Exibe informações de sistema do Docker
docker system info
```
```bash
# Exibe informações de sistema do Docker em formato JSON
docker system info --format '{{json .}}'
```

#### Mostrar o uso de disco pelo Docker

- **Sintaxe:**
```bash
docker system df [OPÇÕES]
```
```bash
Opções:

  --format string   Formata saída usando um template Go
  -v, --verbose     Mostra informações detalhadas de uso de espaço

```

- **Exemplo:**
```bash
# Mostra o uso de disco pelo Docker, por padrão a informação é sintética

docker system df
```
```bash
# Mostra o uso de disco pelo Docker de forma detalhada

docker system df -v
```

#### Remover todos os dados não utilizados

- **Sintaxe:**
```bash
docker system prune [OPÇÕES]
```
```bash
Opções:

--all , -a      Remove todas as imagens não utilizadas, não somente as imagens dangling
--filter        Permite que seja informado um filtro (p.ex. 'label==')
  -f, --force   Não solicita confirmação (e.g. ‘')
--volumes       Elimina volumes não utilizados por nenhum container
```

- A opção `docker system prune` remove:  
    - todos os containers parados  
    - volumes (com a opção `--volumes`)  
    - redes (não referenciadas por nenhum container)  
    - imagens não utilizadas - dangling e não referenciadas (com a opção `-a`)  
    - e dados do cache.

- **Exemplo:**
```bash
# Remove todos os containers parados, redes (não referenciadas por nenhum container) e imagens dangling

docker system prune
```
```bash
# Remove todos os dados

docker system prune -a --volumes
```


> [!NOTE]
> **Para saber mais**  
>    [Docker Docs | Docker CLI Reference](https://docs.docker.com/reference/cli/docker/)


### Logging


#### Modificar o logging driver padrão

- Para modificar o logging driver padrão utilizado pelo daemon do Docker, é necessário defini-lo no arquivo `daemon.json`

- Em hosts Linux, esse arquivo normalmente está localizado em `/etc/docker/daemon.json`. Em hosts Windows, o caminho é `C:\ProgramData\docker\config\daemon.json`

- **Exemplo:**
```json
{
  "log-driver": "local"
}
```

- Se o logging driver possuir opções configuráveis, elas poderão ser especificadas por meio da chave `log-opts`

- **Exemplo:**
```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
```

- Após modificar o arquivo `daemon.json`, é necessário reiniciar o daemon do Docker para que a alteração seja aplicada aos novos containers. Containers já existentes não passam automaticamente a utilizar a nova configuração

- Para verificar o logging driver padrão utilizado pelo daemon, execute:

- **Sintaxe:**
```bash
docker info --format '{{.LoggingDriver}}'
```

- Quando um container é criado, ele pode ser configurado para utilizar um logging driver diferente do padrão do daemon por meio da opção `--log-driver`

> O logging driver configurado para um container é definido no momento de sua criação. Alterar o logging driver padrão do daemon não modifica automaticamente containers já existentes.


#### Comando para visualização dos logs

- O comando `docker container logs` permite recuperar os logs de um container

```bash
docker container logs [OPÇÕES] CONTAINER
```
ou
```bash
docker logs [OPÇÕES] CONTAINER
```
```bash
Opções:

--details              Exibe informações adicionais fornecidas pelo logging driver.
--follow, -f           Acompanha continuamente a saída de logs, exibindo novas mensagens
                       à medida que são produzidas.
--since                Exibe os logs gerados desde um determinado instante. Pode receber
                       um timestamp, um timestamp Unix ou uma duração relativa, como 42m.
--tail, -n             Define a quantidade de linhas a serem exibidas a partir do final
                       dos logs. O padrão é all.
--timestamps, -t       Exibe timestamps em cada entrada de log.
--until                Exibe os logs gerados antes de um determinado instante. Pode
                       receber um timestamp, um timestamp Unix ou uma duração relativa.
```

- A saída corresponde aos dados registrados nas saídas padrão **`STDOUT`** e **`STDERR`** pelo processo do container, conforme o mecanismo de logging utilizado

- **Exemplos:**
```bash
# Exibe os logs do container
docker container logs e64f8a7b2dd2
```
```bash
# Exibe os logs e acompanha continuamente novas mensagens
docker container logs -f jerry
```
```bash
# Exibe os logs gerados nos últimos 2 minutos
docker container logs --since 2m e64f8a7b2dd2
```
```bash
# Exibe as últimas 10 linhas dos logs
docker container logs --tail 10 jerry
```
```bash
# Exibe os logs gerados antes de determinado instante
docker container logs --until 2m jerry
```
```bash
# Exibe os logs com timestamps
docker container logs --timestamps jerry
```
O valor de `--since` e `--until` pode ser informado utilizando, entre outros formatos, timestamps no padrão RFC 3339, timestamps Unix ou durações relativas, como `1m30s` e `3h`

#### Logging drivers suportados

O Docker inclui os seguintes logging drivers:

|    Driver   | Descrição                                                                                                                                                                                            |
| :---------: | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|    `none`   | Desabilita o armazenamento de logs do container.                                                                                                                                                     |
| `json-file` | Armazena os logs em arquivos no formato JSON. É o driver padrão do Docker.                                                                                                                           |
|   `local`   | Armazena os logs localmente utilizando um formato otimizado e realiza rotação automática por padrão. É o driver recomendado pela documentação para muitos cenários que utilizam armazenamento local. |
|   `syslog`  | Envia mensagens de log para um servidor `syslog`.                                                                                                                                                    |
|  `journald` | Envia mensagens de log para o `journald`.                                                                                                                                                            |
|    `gelf`   | Envia mensagens de log utilizando o formato Graylog Extended Log Format (GELF), para endpoints como Graylog ou Logstash.                                                                             |
|  `fluentd`  | Envia mensagens de log para `fluentd`, normalmente por meio de sua entrada *forward*.                                                                                                                |
|  `awslogs`  | Envia mensagens de log para o Amazon CloudWatch Logs.                                                                                                                                                |
|   `splunk`  | Envia mensagens de log para o Splunk por meio do coletor de eventos HTTP.                                                                                                                            |
|  `etwlogs`  | Registra mensagens de log como eventos Event Tracing for Windows (ETW). Disponível em plataformas Windows.                                                                                           |
|  `gcplogs`  | Envia mensagens de log para o Google Cloud Logging.                                                                                                                                                  |

- Para alguns logging drivers que não permitem a leitura local dos logs, o Docker pode utilizar o mecanismo de **dual logging**, mantendo uma cópia local dos logs para permitir o uso do comando `docker container logs`


>**Para saber mais**    
>    [Docker Docs | View container logs](https://docs.docker.com/engine/logging/)  
>    [Docker Docs | Configure logging drivers](https://docs.docker.com/engine/logging/configure/)  


### Monitoramento

- O Docker fornece o comando `docker container stats`, que apresenta um fluxo contínuo de estatísticas sobre o uso de recursos dos containers

- **Sintaxe:**
```bash
docker container stats [OPÇÕES] [CONTAINER...]
```
ou
```bash
docker stats [OPÇÕES] [CONTAINER...]
```
```bash
Opções:
--all, -a     Exibe as estatísticas de todos os containers. Por padrão, apenas os
              containers em execução são exibidos.
--format      Formata a saída utilizando um template personalizado.
--no-stream   Desabilita o fluxo contínuo de estatísticas e exibe apenas
              o primeiro resultado.
--no-trunc   Impede que determinados valores da saída sejam truncados.
```

- As estatísticas incluem informações como:

    - utilização de CPU
    - utilização de memória
    - entrada e saída de rede
    - entrada e saída de operações de bloco
    - quantidade de processos e threads associados ao container

- O comando pode receber o nome ou o ID de um ou mais containers separados por espaço

- Por padrão, `docker container stats` exibe as estatísticas dos containers em execução. Também é possível utilizar a opção `--all` para incluir containers parados. Containers parados não retornam dados de uso de recursos

- Para obter informações mais detalhadas de forma programática, é possível utilizar o endpoint da API do Docker:

- **Sintaxe:**
```text
/containers/(id)/stats
```

- **Exemplos:**
```bash
# Exibe as estatísticas dos containers em execução
docker container stats
```
```bash
# Exibe as estatísticas de um container específico
docker container stats jerry
```
```bash
# Exibe as estatísticas sem atualização contínua
docker container stats --no-stream jerry
```
```bash
# Exibe as estatísticas de todos os containers
docker container stats --all
```
- A opção `--format` permite selecionar e organizar as informações exibidas utilizando templates Go

- **Exemplos:**
```bash
# Exibe o nome do container e o percentual de utilização de CPU
docker container stats \
  --format '{{.Name}}: {{.CPUPerc}}'
```
```bash
# Utilizando uma tabela:
docker container stats \
  --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}'
```

- A opção `--format` também permite utilizar o formato JSON:

- **Sintaxe:**
```bash
docker container stats \
  --format '{{json .}}'
```

- As informações disponíveis no template incluem, entre outras:

    |     Campo    | Descrição                                                  |
    | :----------: | ---------------------------------------------------------- |
    | `.Container` | Nome ou ID informado para o container.                     |
    |    `.Name`   | Nome do container.                                         |
    |     `.ID`    | ID do container.                                           |
    |  `.CPUPerc`  | Percentual de utilização de CPU.                           |
    |  `.MemUsage` | Utilização de memória.                                     |
    |  `.MemPerc`  | Percentual de utilização de memória.                       |
    |   `.NetIO`   | Entrada e saída de rede.                                   |
    |  `.BlockIO`  | Entrada e saída de operações de bloco.                     |
    |    `.PIDs`   | Quantidade de processos e threads associados ao container. |


> [!NOTE]
> **Para saber mais**  
>    [Docker Docs | Docker CLI Reference - docker container stats](https://docs.docker.com/reference/cli/docker/container/stats/)

