# Laboratório 02 | Solução

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

---

### 2. Criar o estágio de build

Crie um arquivo com o nome `Dockerfile` na raiz do projeto e implemente um estágio chamado `build`.

Esse estágio deverá:

- usar uma imagem Maven compatível com Java 21;
- definir um diretório de trabalho `workspace`;
- copiar o `pom.xml` e o diretório `src` para a imagem;
- usar um RUN com mount para cache montado em `/root/.m2` para as dependências Maven;
- executar `mvn package` sem compilar e executar os testes da aplicação nesse estágio


#### Solução

```dockerfile
# syntax=docker/dockerfile:1

# Estágio de build: usa Maven e JDK para gerar o JAR executável do Spring Boot.
FROM maven:3.9.9-eclipse-temurin-21 AS build

WORKDIR /workspace

COPY pom.xml ./
COPY src ./src

# Mantém as dependências Maven em cache e não compila/executa testes neste estágio.
RUN --mount=type=cache,target=/root/.m2 \
    mvn -B -ntp -Dmaven.test.skip=true package

```


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


#### Solução

```dockerfile
# Imagem final: contém apenas o JRE, sem Maven nem código-fonte.
FROM eclipse-temurin:21-jre-alpine AS runtime

WORKDIR /app

# Executa a aplicação sem privilégios de root.
RUN addgroup -S petclinic && adduser -S -G petclinic petclinic

COPY --from=build --chown=petclinic:petclinic /workspace/target/*.jar ./app.jar

USER petclinic

# Porta configurada pela aplicação Spring Boot.
EXPOSE 9966

# Inicia a aplicação empacotada no JAR.
ENTRYPOINT ["java", "-jar", "/app/app.jar"]

```


---

### 4. Construir a imagem final


#### Solução

Construa a imagem local e atribua a tag `spring-petclinic-api:local`.

```bash
docker build -t spring-petclinic-api:local .
```

Verifique se ela foi criada corretamente.

```bash
docker image ls spring-petclinic-rest
```


---

### 5. Executar e validar a aplicação


#### Solução

Inicie a aplicação em background, publicando a porta `9966` e usando o nome `petclinic-rest`.

```bash
docker run -d --name petclinic-rest -p 9966:9966 spring-petclinic-api:local
```

Verifique o estado e os logs da aplicação.

```bash
docker ps --filter name=petclinic-rest
docker logs -f petclinic-rest
```



---

### 6. Publicar a imagem no Docker Hub pessoal

Identifique a imagem local gerada com o nome do seu Docker ID (usuário do Docker Hub) e a tag `1.0.0`

Em seguida realize o push da sua imagem.

Acesse o site do [Docker Hub](https://hub.docker.com/) e confira se o upload foi realizado corretamente.

#### Solução

No Docker Hub, não é necessário criar previamente o repositório `spring-petclinic-api`, pois ele poderá ser criado automaticamente no primeiro `docker push`, desde que a imagem esteja identificada corretamente com seu nome de usuário e que você tenha permissão para publicar imagens.

Antes de enviar a imagem, verifique se você está autenticado no Docker Hub. Caso já esteja conectado pelo Docker Desktop, poderá utilizar diretamente o Docker CLI. Caso contrário, execute:

```bash
docker login
```

Por padrão, o Docker CLI utiliza um fluxo de autenticação pelo navegador. Siga as instruções exibidas no terminal para concluir o login.

Após a autenticação, identifique a imagem utilizando seu Docker ID (usuário do Docker Hub) e o nome do repositório `spring-petclinic-api` e execute o comando `docker push` para publicá-la no Docker Hub:

```bash
docker tag spring-petclinic-api:local SEU_DOCKER_ID/spring-petclinic-api:1.0.0
```
```bash
docker push SEU_DOCKER_ID/spring-petclinic-api:1.0.0
```

---

## Desafio final | Adicionar um estágio opcional de testes

Amplie o Dockerfile para que a suíte Maven possa ser executada em um estágio chamado `test`, baseado no estágio `build`. O estágio deve usar o cache Maven já configurado, mas não pode ser uma dependência de `runtime`. O build padrão da imagem final deve continuar sem executar os testes.


#### Solução

```dockerfile
# Estágio opcional de testes; execute com `docker build --target test`.
# Não é dependência do estágio runtime, portanto não roda no build padrão da imagem.
FROM build AS test

RUN --mount=type=cache,target=/root/.m2 \
    mvn -B -ntp test

```

Execute o alvo `test` sem exportar uma imagem nomeada e mostrando o output do Maven no console.

```bash
docker buildx build --no-cache --progress=plain --target test --output=type=cacheonly .
```

Confirme no output o total de testes, falhas, erros, testes ignorados e o resultado final do build. Em seguida, reconstrua a imagem padrão e observe que o BuildKit não executa o estágio `test`.




Ao concluir o desafio, pare e remova o container criado na tarefa anterior, se ainda estiver ativo:

```bash
docker rm -f petclinic-rest
```

