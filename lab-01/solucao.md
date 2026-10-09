# Laboratório 01 | Solução

## Tarefas

### 1. Executar a aplicação em background

Execute a aplicação **Spring Petclinic REST** em background.

O container deverá:

- possuir o nome `petclinic-rest`;
- publicar a porta `9966` do container na porta `9966` do host.

Depois da execução, verifique se o container está em execução.


#### Solução

```bash
docker run \
  --name petclinic-rest \
  -p 9966:9966 \
  -d \
  springcommunity/spring-petclinic-rest
```

Verificar o container:

```bash
docker ps
```

A opção `-d` executa o container em background, enquanto `--name` define seu nome e `-p 9966:9966` publica a porta da aplicação no host.


> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre executar um container em primeiro plano e executá-lo em background?

> Ao executar um container em **primeiro plano**, o terminal permanece associado ao processo principal do container e exibe diretamente suas saídas, como mensagens enviadas para `STDOUT` e `STDERR`. O comando permanece vinculado à execução do container.  
> Ao executar um container em **background**, utilizando a opção `-d`, o Docker inicia o container e devolve imediatamente o controle do terminal ao usuário. O container continua em execução de forma independente, e seus logs podem ser acompanhados posteriormente com comandos como `docker logs`.

---

### 2. Acessar a aplicação e acompanhar os logs

Acesse a interface da aplicação disponível no endereço:

[http://localhost:9966/petclinic/](http://localhost:9966/petclinic/)

Utilize a interface Swagger para executar os seguintes endpoints:

- **owners**: `GET` */api/owners*
- **pets**: `GET` */api/pets*
- **users**: `POST` */api/users*
- **vets**: `DELETE` */api/vets/{vetId}* (id:1)


Enquanto as requisições são executadas, acompanhe os logs da aplicação em outro terminal.

Observe as mensagens geradas antes, durante e depois das requisições.


#### Solução

Acesse a interface da aplicação disponível no endereço:

[http://localhost:9966/petclinic/](http://localhost:9966/petclinic/)

Na interface Swagger:
- identifique o endpoint solicitado;
- Clique na seta à direita para abrir as opções;
- Clique em **Try it out**; 
- inclua os parâmetros, se necessário e clique em **Execute**

Acompanhar os logs:

```bash
docker logs -f petclinic-rest
```

Para encerrar o acompanhamento dos logs:

```text
Ctrl+C
```


> [!IMPORTANT]
> **Pergunta**  
> Qual é a relação entre as requisições realizadas na API e as mensagens observadas nos logs do container?

> As requisições realizadas na API podem provocar a execução de diferentes operações na aplicação e, quando o mecanismo de logging da aplicação está configurado para registrá-las, essas operações geram mensagens nos fluxos `STDOUT` e `STDERR`.  
> O Docker disponibiliza essas saídas por meio do mecanismo de logs do container, permitindo acompanhar, com `docker logs`, eventos relacionados às requisições, inicialização da aplicação, erros e outras informações registradas pelo processo principal.

---

### 3. Executar comandos dentro do container

Utilize o container `petclinic-rest` para verificar a versão do Java utilizada pela aplicação.

Realize a verificação de duas formas:

- executando o comando diretamente no container;
- abrindo uma sessão interativa no container e executando o comando a partir do terminal.

#### Solução

##### Executar diretamente o comando

```bash
docker exec -t petclinic-rest java -version
```

##### Abrir uma sessão interativa

```bash
docker exec -it petclinic-rest bash
```

Dentro do container:

```bash
java -version
```

Para sair da sessão:

```bash
exit
```


> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre executar diretamente um comando com `docker exec` e abrir uma sessão interativa no container?

> Quando um comando é executado diretamente com `docker exec`, apenas o processo especificado é iniciado dentro do container. Depois que esse processo termina, a execução do `docker exec` também termina.  
> Ao abrir uma sessão interativa, normalmente utilizando `docker exec -it`, é iniciado um processo interativo, como um shell `bash`. O usuário passa a ter um terminal dentro do ambiente do container e pode executar vários comandos sucessivamente antes de encerrar a sessão.

---

### 4. Executar o banco de dados PostgreSQL

Crie um arquivo chamado `env.txt`. O arquivo deverá conter as variáveis necessárias para configurar:

- usuário do banco: **POSTGRES_USER**
- senha do banco: **POSTGRES_PASSWORD**
- nome do banco: **POSTGRES_DB**

Todas as variáveis devem ter o valor: **petclinic** 

Execute o PostgreSQL em background utilizando esse arquivo de variáveis de ambiente.

O container deverá possuir o nome `petclinic-db`

A porta `5432` do container deverá ser publicada na porta `5432` do host.

Depois da execução, verifique se o PostgreSQL está em funcionamento.


#### Solução

Criar o arquivo `env.txt`:

```text
POSTGRES_USER=petclinic
POSTGRES_PASSWORD=petclinic
POSTGRES_DB=petclinic
```

Executar o PostgreSQL:

```bash
docker run \
  --name petclinic-db \
  --env-file=env.txt \
  -p 5432:5432 \
  -d \
  postgres:16.3
```

Verificar:

```bash
docker ps
```

Também é possível verificar os logs do banco:

```bash
docker logs petclinic-db
```

Para acompanhar a inicialização:

```bash
docker logs -f petclinic-db
```

Para sair dos logs:

```bash
exit
```

> [!IMPORTANT]
> **Pergunta**  
> Qual é a vantagem de utilizar um arquivo de variáveis de ambiente em vez de informar todas as variáveis diretamente na linha de comando?

> O arquivo de variáveis de ambiente permite **centralizar a configuração**, tornando o comando `docker run` mais simples, legível e fácil de reutilizar. Também facilita a manutenção das variáveis utilizadas por uma aplicação, pois seus valores podem ser alterados no arquivo sem modificar toda a linha de comando.  

---

### 5. Executar novamente a aplicação utilizando o PostgreSQL

A aplicação deverá ser recriada para utilizar o PostgreSQL executado no container `petclinic-db`.

Antes da recriação, observe o estado atual do container `petclinic-rest`.

Na nova execução, a aplicação deverá:

- utilizar os profiles `postgres` e `spring-data-jpa` por meio da variável **SPRING_PROFILES_ACTIVE**;
- receber por variável de ambiente (**POSTGRES_URL**) a URL de conexão com o PostgreSQL (`jdbc:postgresql://host.docker.internal/petclinic`);
- continuar disponível pela porta `9966` do host;
- utilizar o nome `petclinic-rest`.

Após a execução, verifique se a aplicação foi inicializada corretamente e teste novamente a API.


> [!TIP]
> Para este cenário, a aplicação Spring PetClinic é executada em um container, enquanto o PostgreSQL também é executado em outro container. 
> Porém por padrão, o container da  aplicação Spring tenta se conectar ao banco usando localhost ou 127.0.0.1 dentro do próprio ambiente isolado dele, onde o Postgres não existe.  
> Para resolver isso de forma simples e rápida sem precisar criar redes customizadas, que abordaremos mais à frente, você usará a opção `--add-host=host.docker.internal:host-gateway` do comando `docker run` na execução do container da aplicação Spring.  
> Ela mapeia o IP da sua máquina hospedeira para um nome que o container entende. Criando, dentro do container da aplicação, uma entrada de resolução para esse nome apontando para o gateway do host Docker, permitindo que a aplicação alcance o serviço PostgreSQL disponibilizado pelo host na porta `5432`.  

> [!NOTE]
> `host.docker.internal` permite que o container acesse o host Docker  
> `host-gateway` faz com que esse nome seja associado ao gateway utilizado para alcançar o host.


#### Solução

O container existente deverá ser removido para que um novo container seja criado com as novas opções de execução:

```bash
docker rm -f petclinic-rest
```

Executar novamente a aplicação:

```bash
docker run \
  -d \
  --name petclinic-rest \
  -e SPRING_PROFILES_ACTIVE="postgres,spring-data-jpa" \
  -e POSTGRES_URL="jdbc:postgresql://host.docker.internal/petclinic" \
  -p 9966:9966 \
  --add-host=host.docker.internal:host-gateway \
  springcommunity/spring-petclinic-rest
```

Verificar:

```bash
docker ps
```

Acompanhar a inicialização:

```bash
docker logs -f petclinic-rest
```

Para sair dos logs:

```bash
exit
```

Depois, acessar [http://localhost:9966/petclinic/](http://localhost:9966/petclinic/)


> [!IMPORTANT]
> **Pergunta**  
> Por que um container existente precisa ser recriado quando é necessário alterar determinadas opções utilizadas em sua criação, como variáveis de ambiente?

> As opções utilizadas na criação do container fazem parte de sua configuração inicial. Depois que o container é criado, determinadas propriedades, como as variáveis de ambiente definidas com `-e` ou `--env-file`, não podem ser alteradas diretamente para produzir um novo ambiente de execução.  
> Por isso, quando essas configurações precisam ser modificadas, normalmente é necessário remover o container existente e criar outro utilizando as novas opções.

---

### 6. Inspecionar os containers

Utilize os mecanismos de inspeção do Docker para analisar os containers:

- `petclinic-rest`;
- `petclinic-db`.

Localize e registre:

- endereço IP do container;
- portas publicadas;
- variáveis de ambiente;
- comando utilizado para iniciar o container.

Compare as informações obtidas para os dois containers.


#### Solução

Listar os containers:

```bash
docker ps
```

Inspecionar a aplicação:

```bash
docker inspect petclinic-rest
```

Inspecionar o banco:

```bash
docker inspect petclinic-db
```

##### Identificar o endereço IP

Uma forma de localizar o endereço IP é consultar a seção de rede do resultado do `inspect`.

Também é possível obter diretamente o endereço IP:

```bash
docker inspect \
  --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' \
  petclinic-rest
```

##### Identificar as portas publicadas

```bash
docker port petclinic-rest
```

##### Identificar as variáveis de ambiente

```bash
docker inspect \
  --format='{{range .Config.Env}}{{println .}}{{end}}' \
  petclinic-rest
```

##### Identificar o comando configurado para o container

```bash
docker inspect --format='{{json .Config.Cmd}}' petclinic-rest
```


> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre a visão resumida apresentada pelo comando que lista containers e as informações detalhadas obtidas por meio da inspeção?

> O comando `docker ps` apresenta uma **visão resumida** dos containers, permitindo verificar rapidamente informações operacionais, como nome, imagem, estado e portas publicadas.  
> Já `docker inspect` apresenta uma **visão detalhada da configuração e do estado do container**, incluindo informações de rede, endereço IP, variáveis de ambiente, comando configurado, volumes, configurações de execução e outras propriedades internas.  
> Assim, `docker ps` é mais adequado para uma consulta rápida, enquanto `docker inspect` é utilizado quando é necessário analisar detalhadamente a configuração ou investigar o comportamento de um container.


---

### 7. Monitorar o consumo de recursos

Monitore o consumo de recursos dos containers:

- `petclinic-rest`;
- `petclinic-db`.

Observe:

- utilização de CPU;
- utilização de memória;
- tráfego de rede;
- operações de bloco.

Compare o comportamento dos dois containers durante a execução da aplicação.


#### Solução

Monitorar a aplicação:

```bash
docker stats petclinic-rest
```

Monitorar o banco:

```bash
docker stats petclinic-db
```

Monitorar ambos simultaneamente:

```bash
docker stats petclinic-rest petclinic-db
```

Para obter apenas uma amostra:

```bash
docker stats --no-stream petclinic-rest
```

As informações principais incluem:

- CPU%;
- utilização e limite de memória;
- tráfego de rede;
- operações de bloco;
- quantidade de processos associados ao container.


> [!IMPORTANT]
> **Pergunta**  
> Por que containers que executam aplicações diferentes podem apresentar padrões diferentes de utilização de CPU, memória, rede e operações de bloco?

> Cada aplicação possui características próprias de processamento e de acesso aos recursos do sistema. Uma aplicação pode realizar mais cálculos e, consequentemente, apresentar maior utilização de CPU; outra pode manter uma quantidade maior de dados em memória; outra pode realizar muitas requisições de rede; e outra pode executar operações intensivas de leitura e escrita em armazenamento.  
> Portanto, o consumo observado em `docker stats` depende do **perfil de carga da aplicação**, do comportamento do processo executado e das atividades realizadas durante o período de monitoramento.
