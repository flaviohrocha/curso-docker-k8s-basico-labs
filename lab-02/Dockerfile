# syntax=docker/dockerfile:1

# Estágio de build: usa Maven e JDK para gerar o JAR executável do Spring Boot.
FROM maven:3.9.9-eclipse-temurin-21 AS build

WORKDIR /workspace

COPY pom.xml ./
COPY src ./src

# Mantém as dependências Maven em cache e não compila/executa testes neste estágio.
RUN --mount=type=cache,target=/root/.m2 \
    mvn -B -ntp -Dmaven.test.skip=true package

# Estágio opcional de testes; execute com `docker build --target test`.
# Não é dependência do estágio runtime, portanto não roda no build padrão da imagem.
FROM build AS test

RUN --mount=type=cache,target=/root/.m2 \
    mvn -B -ntp test

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
