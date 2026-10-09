# Laboratório 01 | Executando aplicações em containers Docker

## Objetivo

Praticar a execução e o gerenciamento de containers Docker de uma aplicação e banco de dados, observando como diferentes opções de execução alteram o comportamento dos containers.

Ao final do laboratório, você deverá ser capaz de:

- executar aplicações a partir de imagens Docker;
- executar containers em background e atribuir nomes;
- publicar portas de containers no host;
- acompanhar os logs de uma aplicação;
- executar comandos dentro de um container;
- configurar aplicações por meio de variáveis de ambiente;
- utilizar um arquivo de variáveis de ambiente;
- executar uma aplicação utilizando um banco de dados PostgreSQL em outro container;
- inspecionar a configuração de containers;
- monitorar o consumo de recursos computacionais.

> [!WARNING]
>  O foco deste laboratório é a **execução e o gerenciamento de containers**. Não é necessário construir imagens Docker.

## Orientações

- Utilize preferencialmente o **Docker CLI** para executar as atividades.
- Consulte o [Guia de comandos](comandos.md) desse laboratório, sempre que necessário para identificar a sintaxe e as opções dos comandos.
- Execute as atividades na ordem apresentada, pois algumas tarefas dependem do estado produzido pelas anteriores.
- Observe atentamente o comportamento dos containers após cada operação.
- Utilize os comandos de inspeção e monitoramento para confirmar o resultado das operações realizadas.
- Ao encontrar um comportamento diferente do esperado, utilize os logs e a inspeção do container para investigar a causa antes de recriá-lo.

## Cenário

Você faz parte de uma equipe responsável por preparar um ambiente local para desenvolvimento e testes de uma aplicação de uma clínica veterinária.

A aplicação utilizada no laboratório é a **Spring PetClinic REST**, uma API REST baseada no ecossistema Spring. Ela disponibiliza operações para gerenciamento de proprietários, animais de estimação, veterinários, tipos de animais, especialidades e visitas.

O ambiente inicial utiliza a aplicação com seu banco de dados padrão em memória (**H2**). Em uma segunda etapa, a aplicação deverá ser executada utilizando um banco de dados **PostgreSQL** em um container separado.

O laboratório representa, portanto, uma situação comum no desenvolvimento de aplicações:

```text
Máquina de desenvolvimento (Docker host)
│
├── Container da aplicação
│   └── Spring PetClinic REST
│
└── Container do banco de dados
    └── PostgreSQL
```

> [!NOTE]
> **O que vamos fazer?**  
> Durante a atividade, você deverá controlar o ciclo de vida dos containers, configurar a execução da aplicação, acessar a API, analisar logs, inspecionar configurações e observar a utilização dos recursos computacionais.


### Imagens utilizadas

| IMAGEM | FINALIDADE |
| --- | --- |
| `springcommunity/spring-petclinic-rest` | Aplicação REST Spring PetClinic |
| `postgres:16.3` | Banco de dados PostgreSQL |

#### Pré-requisitos

- **Docker Engine** em execução;
- acesso ao **Docker Hub** para baixar imagens;
- Git Bash ou outra shell Bash, ou PowerShell no Windows.

## Tarefas

### 1. Executar a aplicação em background

Execute a aplicação **Spring Petclinic REST** em background.

O container deverá:

- possuir o nome `petclinic-rest`;
- publicar a porta `9966` do container na porta `9966` do host.

Depois da execução, verifique se o container está em execução.

> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre executar um container em primeiro plano e executá-lo em background?

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

> [!IMPORTANT]
> **Pergunta**  
> Qual é a relação entre as requisições realizadas na API e as mensagens observadas nos logs do container?

---

### 3. Executar comandos dentro do container

Utilize o container `petclinic-rest` para verificar a versão do Java utilizada pela aplicação.

Realize a verificação de duas formas:

- executando o comando diretamente no container;
- abrindo uma sessão interativa no container e executando o comando a partir do terminal.


> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre executar diretamente um comando com `docker exec` e abrir uma sessão interativa no container?

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

> [!IMPORTANT]
> **Pergunta**  
> Qual é a vantagem de utilizar um arquivo de variáveis de ambiente em vez de informar todas as variáveis diretamente na linha de comando?

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

> [!IMPORTANT]
> **Pergunta**  
> Por que um container existente precisa ser recriado quando é necessário alterar determinadas opções utilizadas em sua criação, como variáveis de ambiente?

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

> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre a visão resumida apresentada pelo comando que lista containers e as informações detalhadas obtidas por meio da inspeção?

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

> [!IMPORTANT]
> **Pergunta**  
> Por que containers que executam aplicações diferentes podem apresentar padrões diferentes de utilização de CPU, memória, rede e operações de bloco?

