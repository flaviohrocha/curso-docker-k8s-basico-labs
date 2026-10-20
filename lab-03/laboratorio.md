# Laboratório 03 | Armazenando dados e trabalhando com redes

## Objetivo

Usar a aplicação **Spring PetClinic REST** e o **PostgreSQL** para praticar volumes e redes Docker.

Ao final do laboratório, você deverá ser capaz de:

- persistir os dados do PostgreSQL em um volume;
- conectar containers por uma rede definida pelo usuário;
- acessar o PostgreSQL pelo nome do container na rede Docker;
- diferenciar a publicação de portas da comunicação entre containers.

## Orientações

- Execute os comandos no Git Bash ou outro shell Bash, ou PowerShell no Windows.
- O Docker Engine deve estar em execução.
- Construa a imagem `spring-petclinic-api:1.0.0` conforme as instruções do Laboratório 02.
- Este laboratório usa a imagem local `spring-petclinic-api:1.0.0` e a imagem `postgres:16.3`.
- Os comandos usam os nomes `petclinic-rest`, `petclinic-db`, `petclinic-data` e `petclinic-net`. Confira se já existem recursos com esses nomes antes de começar.
- Os comandos usam credenciais de demonstração. Não as reutilize em ambientes reais.

> [!WARNING]
> Remover um container não remove o volume. Remover `petclinic-data`, por outro lado, apaga os dados do PostgreSQL armazenados nele.

## Cenário

A aplicação Spring PetClinic REST será configurada para usar um PostgreSQL em outro container. Primeiro, a aplicação alcançará o banco pela porta publicada no host. Depois, os containers serão conectados por uma rede definida pelo usuário e a aplicação usará o nome do container do banco.

```text
Execução inicial:
Aplicação → host.docker.internal → porta publicada do PostgreSQL

Execução com rede definida pelo usuário:
Aplicação → petclinic-db → PostgreSQL
```

## Imagens utilizadas

| Imagem | Finalidade |
| --- | --- |
| `spring-petclinic-api:1.0.0` | Aplicação REST Spring PetClinic construída no Laboratório 02 |
| `postgres:16.3` | Banco de dados PostgreSQL |

## Tarefas

### 1. Iniciar o PostgreSQL e a aplicação

Crie um volume para os dados do PostgreSQL com o nome `petclinic-data`.

Crie o arquivo `env.txt` com as variáveis necessárias para inicializar o banco:

```text
POSTGRES_USER=petclinic
POSTGRES_PASSWORD=petclinic
POSTGRES_DB=petclinic
```

Inicie o PostgreSQL em background com o nome `petclinic-db`, publique sua porta no host com o mesmo número (`5432`) e monte o volume no diretório de dados `/var/lib/postgresql/data`.

Aguarde o PostgreSQL estar pronto

Inicie a aplicação usando o PostgreSQL por meio do host conforme foi feito no **Laboratório 02**:

```bash
docker run -d \
  --name petclinic-rest \
  --env SPRING_PROFILES_ACTIVE=postgres,spring-data-jpa \
  --env POSTGRES_URL=jdbc:postgresql://host.docker.internal:5432/petclinic \
  --add-host=host.docker.internal:host-gateway \
  -p 9966:9966 \
  spring-petclinic-api:1.0.0
```

Verifique os containers e os logs.

Acesse a aplicação em [http://localhost:9966/petclinic/](http://localhost:9966/petclinic/). Pelo Swagger, crie um proprietário usando `POST /api/owners` e o Json abaixo:

```json 
{
  "firstName": "Paulo",
  "lastName": "Silva",
  "address": "Q.1 casa 10",
  "city": "Brasilia",
  "telephone": "6085551023"
}
``` 

Consulte `GET /api/owners` para verificar a criação e o id do novo registro.


---


### 2. Verificar a persistência do banco

Remova os containers.

Consulte que o volume permanece criado.

Recrie o PostgreSQL usando o mesmo volume e o mesmo arquivo de variáveis.

Aguarde o banco estar pronto.

Recrie a aplicação com as mesmas configurações da tarefa anterior.

Acesse novamente o Swagger e consulte `GET /api/owners`. Verifique se o proprietário criado na tarefa anterior continua disponível.


---


### 3. Conectar os containers por uma rede definida pelo usuário

Crie uma rede Docker chamada `petclinic-net`.

Remova os containers criados anteriormente.

Inicie o PostgreSQL na rede e monte o mesmo volume. **Nesta etapa, não publique a porta do banco no host**.

Aguarde o banco estar pronto.

Inicie a aplicação na mesma rede. Use o nome do container do banco de dados na variável de ambiente `POSTGRES_URL` (`jdbc:postgresql://petclinic-db:5432/petclinic`).

Acesse [http://localhost:9966/petclinic/](http://localhost:9966/petclinic/) e confirme que os dados do usuário criado continuam disponíveis.

Inspecione a rede.


---


### 5. Limpar o ambiente

Remova os containers e a rede.

O volume e o arquivo de variáveis não são removidos junto com os containers. Se não precisar mais dos dados, remova o volume.


> [!WARNING]
> A remoção do volume apaga os dados do PostgreSQL.


---


## Resultado esperado

Ao concluir o laboratório, você terá:

- confirmado que os dados do PostgreSQL persistem em um volume após a remoção dos containers;
- conectado a aplicação e o PostgreSQL pela mesma rede definida pelo usuário;
- acessado o banco pelo nome `petclinic-db`, sem publicar sua porta no host.