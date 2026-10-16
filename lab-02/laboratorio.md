# Laboratório 02 | Criando uma imagem Docker multi-stage

## Objetivo

Criar, validar e publicar uma imagem Docker para a aplicação Spring PetClinic REST. A atividade explora builds multi-stage, otimização da imagem final e execução do processo sem privilégios de root. Ao final, um desafio opcional introduz um estágio independente para executar testes.

Ao final do laboratório, você deverá ser capaz de:

- identificar as informações de build e execução de uma aplicação Spring Boot;
- criar um Dockerfile multi-stage para compilar e executar uma aplicação Java;
- usar cache de dependências Maven durante o build;
- construir uma imagem e executar um container a partir dela;
- marcar e publicar uma imagem no Docker Hub pessoal;
- explicar como o BuildKit seleciona os estágios que serão executados;
- criar, como desafio final, um estágio de testes que não seja obrigatório para a imagem runtime.

> [!IMPORTANT]
> A imagem será construída localmente e publicada no repositório pessoal do Docker Hub de cada participante. Nunca inclua credenciais ou tokens em arquivos do projeto ou nos comandos registrados no histórico do shell.

## Orientações

- Execute as tarefas na ordem apresentada;
- Utilize o Docker CLI e, quando necessário, o Docker Buildx;
- O Docker Engine deve estar em execução; no Windows, o Docker Desktop fornece o Engine e o Buildx;
- Você precisará de uma conta pessoal no Docker Hub e de permissão para publicar imagens nela;
- Trabalhe em uma branch ou cópia do repositório; 
- Observe os logs dos comandos Maven e Docker. Quando um comando falhar, identifique a etapa que falhou antes de prosseguir.

## Cenário

O código-fonte utilizado neste laboratório está no repositório [flaviohrocha/spring-petclinic-rest](https://github.com/flaviohrocha/spring-petclinic-rest). Você precisa criar uma imagem executável para a aplicação. O build Maven gera um JAR Spring Boot, mas o Maven e o JDK não são necessários para executar esse JAR em produção.

A imagem terá estágios com responsabilidades distintas:

```text
BuildKit
├── build   -> compila e empacota a aplicação
└── runtime -> contém o JRE e o JAR necessário para iniciar a aplicação
```

No desafio final, você adicionará um estágio `test` independente. Ele não deverá ser uma dependência do estágio `runtime`.


### Imagens utilizadas

| IMAGEM | FINALIDADE |
| --- | --- |
| `maven:3.9.9-eclipse-temurin-21` | Maven |
| `eclipse-temurin:21-jre-alpine` | Java 21 runtime |


### Pré-requisitos

- **Docker Engine** em execução;
- acesso ao **Docker Hub** para baixar as imagens-base;
- Git Bash ou outra shell Bash, ou PowerShell no Windows.

## Tarefas

### 1. Investigar os requisitos de build e execução

Antes de escrever o Dockerfile, consulte o `pom.xml` e `src/main/resources/application.properties`.

Identifique:

- versão Java usada pelo projeto;
- comando Maven que empacota a aplicação;
- porta e contexto HTTP;
- qual perfil de banco de dados é usado por padrão.

Se quiser confirmar a versão Java efetiva herdada pelo POM, execute:

```bash
./mvnw -q help:evaluate -Dexpression=java.version -DforceStdout
```

No PowerShell, use `./mvnw.cmd` no lugar de `./mvnw`.

> [!IMPORTANT]
> **Pergunta**  
> Quais ferramentas são necessárias para compilar a aplicação? Quais são necessárias apenas para executar o JAR?

---

### 2. Criar o estágio de build

Crie `Dockerfile` na raiz do projeto e implemente um estágio chamado `build`.

Esse estágio deverá:

- usar uma imagem Maven compatível com Java 21;
- definir um diretório de trabalho `workspace`;
- copiar o `pom.xml` e o diretório `src` para a imagem;
- usar um RUN com mount para cache montado em `/root/.m2` para as dependências Maven;
- executar `mvn package` sem compilar e executar os testes da aplicação nesse estágio

<details>
<summary>💡<strong>Dica (tente sozinho primeiro!)</strong></summary>
- Comando Maven:<br>
    <code>
    mvn -B -ntp -Dmaven.test.skip=true package
    </code>
</details>
<br>

> [!IMPORTANT]
> **Pergunta**  
> O que fica armazenado no cache montado em `/root/.m2`? Esse cache é a mesma coisa que o cache de camadas controlado pelas opções `--no-cache` e `--no-cache-filter`?

---

### 3. Criar o estágio de execução

Adicione um estágio final chamado `runtime` baseado na imagem JRE 21. O estágio final deverá:

- definir um diretório de trabalho `app`;
- criar um usuário e grupo com o nome `petclinic` dedicados para a aplicação;
- copiar para a imagem somente o JAR empacotado no estágio `build`;
- executar como o usuário dedicado, sem privilégios de root;
- documentar a porta `9966` com `EXPOSE`;
- iniciar o JAR com `java -jar` usando `ENTRYPOINT`.

Não copie o código-fonte, o Maven, o JDK ou os arquivos locais de `target` para o estágio final. O JAR deve vir do estágio `build`.


<details>
<summary>💡<strong>Dica (tente sozinho primeiro!)</strong></summary>
- Comando para criação de usuário:<br>
    <code>
    addgroup -S petclinic && adduser -S -G petclinic petclinic
    </code>
</details>
<br>



> [!IMPORTANT]
> **Pergunta**  
> Quais são as vantagens e os limites de usar uma imagem JRE no estágio final em vez de reutilizar a imagem Maven/JDK?

---

### 4. Construir a imagem final

Construa a imagem local e atribua a tag `spring-petclinic-rest:local`.

Verifique se ela foi criada corretamente.

O build padrão deve produzir a imagem a partir do estágio final `runtime` e de seus estágios dependentes.

> [!IMPORTANT]
> **Pergunta**  
> Que relação entre estágios faz o Docker executar um estágio? Qual estágio fornece o JAR ao runtime?

---

### 5. Executar e validar a aplicação

Inicie a aplicação em background, publicando a porta `9966` e usando o nome `petclinic-rest`.

Verifique o estado e os logs da aplicação.


> [!IMPORTANT]
> **Pergunta**  
> Qual é a diferença entre a porta declarada por `EXPOSE` e a publicação da porta feita com `-p` em `docker run`?

---

### 6. Publicar a imagem no Docker Hub pessoal

Identifique a imagem local gerada com o nome do seu usuário do Docker Hub e a tag `lab-02`

Em seguida realize o push da sua imagem.

Acesse o site do [Docker Hub](https://hub.docker.com/) e confira se o upload foi realizado corretamente.


> [!IMPORTANT]
> **Pergunta**  
> Por que o nome da imagem publicada inclui o nome de usuário do Docker Hub? Por que não devemos informar um token como argumento do comando `docker login`?

---

## Desafio final | Adicionar um estágio opcional de testes

Amplie o Dockerfile para que a suíte Maven possa ser executada em um estágio chamado `test`, baseado no estágio `build`. O estágio deve usar o cache Maven já configurado, mas não pode ser uma dependência de `runtime`. O build padrão da imagem final deve continuar sem executar os testes.

Execute o alvo `test` sem exportar uma imagem nomeada e mostrando o output do Maven no console.

Confirme no output o total de testes, falhas, erros, testes ignorados e o resultado final do build. Em seguida, reconstrua a imagem padrão e observe que o BuildKit não executa o estágio `test`.


<details>
<summary>💡<strong>Dica (tente sozinho primeiro!)</strong></summary>
- Comando Maven:<br>
    <code>
    mvn -B -ntp test
    </code>
</details>
<br>


> [!IMPORTANT]
> **Pergunta**  
> Por que nomear um estágio `test` não faz com que ele seja executado automaticamente? O que fazem `--no-cache`, `--progress=plain` e `--output=type=cacheonly` nesse comando? Se o estágio `runtime` passasse a depender de `test`, qual seria o efeito?

Ao concluir o desafio, pare e remova o container criado na tarefa anterior, se ainda estiver ativo.

---

## Resultado esperado

Ao concluir a atividade, você terá uma imagem `spring-petclinic-rest:local` para execução, terá publicado uma tag no seu Docker Hub pessoal e, no desafio final, terá adicionado um alvo `test` independente que executa a suíte sem exportar uma imagem de testes.
