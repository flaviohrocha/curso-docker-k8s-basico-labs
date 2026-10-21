# Laboratório 04 | Escrevendo e executando uma aplicação com Docker Compose

## Objetivo

Criar progressivamente um arquivo `compose.yaml` para definir e executar a aplicação Spring PetClinic, composta por frontend, backend e banco de dados PostgreSQL.

Ao final do laboratório, você deverá ser capaz de:

- definir serviços em um arquivo Compose;
- construir uma imagem a partir de um Dockerfile e utilizar imagens existentes;
- configurar variáveis de ambiente, publicação de portas e dependências entre serviços;
- declarar redes e volumes nomeados;
- configurar um `healthcheck` e aguardar que o banco esteja pronto;
- validar a configuração antes de iniciar a aplicação;
- iniciar, inspecionar e remover os recursos do projeto com Docker Compose;
- verificar a persistência dos dados após recriar os containers.

## Orientações

- Execute os comandos no terminal a partir da raiz do projeto, onde ficarão o `compose.yaml`, o `Dockerfile` do frontend e os arquivos da aplicação.
- O Docker Engine e o Docker Compose v2 devem estar disponíveis.
- O frontend será construído a partir do contexto local e do `Dockerfile` existente no diretório do projeto.
- O backend utilizará a imagem `SEU_DOCKER_ID/spring-petclinic-api:1.0.0`, **que foi criada no Laboratório 02**. 
- O banco de dados utilizará a imagem `postgres:16.3`.
- Confira se as portas `8080` e `9966` estão disponíveis no host.
- Crie o arquivo `.env` requerido pela configuração do banco. Ele deverá definir `POSTGRES_DB`, `POSTGRES_USER` e `POSTGRES_PASSWORD`, usando os valores de demonstração indicados pelo instrutor.
- Os valores de usuário e senha deste laboratório são apenas para demonstração. Não os reutilize em ambientes reais.
- Acrescente as configurações ao `compose.yaml` na ordem proposta. **O enunciado descreve o que escrever, mas não apresenta os trechos YAML correspondentes.** Consulte a solução somente depois de tentar cada etapa.

> [!WARNING]
> O volume nomeado mantém os dados do PostgreSQL mesmo depois que os containers são removidos. Não utilize `docker compose down --volumes` antes de verificar se deseja apagar os dados persistidos.

## Cenário

A aplicação será organizada em três serviços: `frontend`, `backend` e `database`. Todos participarão de uma rede definida pelo projeto; o banco utilizará um volume nomeado para persistir seus dados. O backend dependerá da condição de saúde do PostgreSQL antes de iniciar.

```text
Navegador
   |
   | localhost:8080
   v
frontend
   |
   | rede petclinic-net
   v
backend  ----->  database
localhost:9966   PostgreSQL 16.3
                     |
                     v
                petclinic-data
                (volume persistente)
```

## Imagens e recursos utilizados

| Recurso | Finalidade |
| --- | --- |
| `spring-petclinic-ui` | Imagem do frontend construída localmente a partir do Dockerfile |
| `SEU_DOCKER_ID/spring-petclinic-api:1.0.0` | Imagem do backend Spring PetClinic REST |
| `postgres:16.3` | Banco de dados PostgreSQL |
| `petclinic-net` | Rede utilizada para a comunicação entre os serviços |
| `petclinic-data` | Volume nomeado para persistir os dados do PostgreSQL |

## Tarefas

### 1. Preparar o ambiente e iniciar o arquivo Compose

O código-fonte utilizado neste laboratório está no repositório [flaviohrocha/spring-petclinic-angular](https://github.com/flaviohrocha/spring-petclinic-angular) na branch **docker**. Faça o `git clone` e mude para a branch indicada.

Execute os comandos a seguir no Git Bash ou em outro terminal com Git instalado:

```
# Clona o repositório
git clone https://github.com/flaviohrocha/spring-petclinic-angular.git

# Acessa o diretório do projeto
cd spring-petclinic-angular

# Muda para a branch docker
git checkout docker
```

Na raiz do projeto:

1. Confirme que o Docker Compose está disponível e identifique os arquivos necessários para construir o frontend.
2. Crie o arquivo `.env` com as variáveis `POSTGRES_DB`, `POSTGRES_USER` e `POSTGRES_PASSWORD`. Utilize os valores de demonstração indicados pelo instrutor.
3. Crie o arquivo `compose.yaml`.
4. Defina o nome do projeto como `petclinic`.
5. Declare a seção de serviços e adicione o serviço `frontend`. 
6. Configure-o para construir uma imagem a partir do diretório atual, usando o `Dockerfile` desse diretório. Identifique a imagem resultante como `spring-petclinic-ui`.
7. Publique a porta `8080` do container na porta `8080` do host.

Valide a configuração e confira se o Compose reconhece o serviço `frontend`.

---

### 2. Adicionar o banco de dados e a persistência

Acrescente ao arquivo `compose.yaml` o serviço `database`:

1. Utilize a imagem `postgres:16.3`.
2. Configure o serviço para carregar as variáveis do arquivo `.env`.
3. Monte um volume nomeado chamado `petclinic-data` no diretório de dados do PostgreSQL, `/var/lib/postgresql/data`.
4. Declare o volume na seção de nível superior apropriada.
5. Configure um `healthcheck` que execute `pg_isready` dentro do container.
6. Defina o intervalo entre verificações como 1 segundo, o tempo limite de cada verificação como 5 segundos e o número máximo de tentativas como 10.

Valide a configuração e verifique se o serviço e o volume foram reconhecidos.

A lista de serviços deverá incluir `frontend` e `database`; a lista de volumes deverá incluir `petclinic-data`.

---

### 3. Adicionar o backend e sua dependência do banco

Acrescente o serviço `backend`:

1. Utilize a imagem `SEU_DOCKER_ID/spring-petclinic-api:1.0.0`.
2. Configure o perfil Spring com `postgres,spring-data-jpa`.
3. Configure a URL JDBC para acessar o banco pelo nome do serviço `database`, na porta `5432`, usando o banco `petclinic`.
4. Publique a porta `9966` do container na porta `9966` do host.
5. Configure a dependência do `backend` em relação a `database`, exigindo que o banco esteja saudável antes da inicialização do backend.

Valide a configuração e confira a lista de serviços.

Verifique especialmente os valores de `image`, `environment`, `ports`, `depends_on` e `healthcheck`.

---

### 4. Conectar os serviços pela rede do projeto

Configure uma rede nomeada `petclinic-net`:

1. Declare a rede na seção de nível superior apropriada.
2. Conecte `frontend`, `backend` e `database` a essa rede.
3. Não adicione a publicação da porta `5432` do banco no host. A comunicação entre backend e banco deve ocorrer pela rede Docker, usando o nome do serviço `database`.

Valide a configuração.

A lista de redes deverá incluir `petclinic-net`. Em seguida, execute novamente `docker compose config` e confira se os três serviços estão associados à rede e se o backend depende de `database` com a condição `service_healthy`.

---

### 5. Construir e baixar as imagens

Depois de completar o arquivo, valide a configuração final antes de executar a aplicação.

Construa a imagem do frontend.

Baixe as imagens do backend e do banco.

Confira as imagens disponíveis localmente.

Caso a construção do frontend falhe, confira se o contexto de construção contém o `Dockerfile` e os arquivos necessários ao build.

---

### 6. Iniciar e validar a aplicação

Inicie a aplicação em segundo plano, construindo a imagem do frontend se necessário e aguardando os serviços atingirem o estado de execução ou saúde esperado.

Confira o estado dos serviços.

Verifique os logs.


Acesse o frontend em [http://localhost:8080](http://localhost:8080). Para verificar o backend, acesse [http://localhost:9966/petclinic/](http://localhost:9966/petclinic/).

Se algum serviço não iniciar, consulte os logs do serviço correspondente.

---

### 7. Verificar a persistência dos dados

1. Pela interface da aplicação ou pelo endpoint disponível no backend, crie um registro de teste e confirme que ele aparece na consulta correspondente.
2. Remova os containers e a rede gerenciados pelo projeto, sem remover o volume.

3. Verifique que o volume continua declarado no projeto.

4. Inicie novamente os serviços.

5. Consulte novamente o registro criado. Confirme se os dados continuam disponíveis.

---

### 8. Limpar o ambiente

Interrompa e remova os containers e a rede do projeto.

Se o laboratório terminou e não houver necessidade de preservar o banco, remova também os volumes.

> [!WARNING]
> A opção `--volumes` remove os volumes nomeados gerenciados pelo projeto e apaga os dados persistidos neles. Execute-a somente se essa perda de dados for intencional.

## Resultado esperado

Ao concluir o laboratório, você terá:

- escrito incrementalmente um `compose.yaml` com os serviços `frontend`, `backend` e `database`;
- validado a configuração em cada etapa;
- construído a imagem do frontend e baixado as imagens do backend e do PostgreSQL;
- configurado rede, volume, variáveis de ambiente e verificação de saúde;
- executado e inspecionado a aplicação com Docker Compose;
- confirmado que os dados do PostgreSQL persistem após a remoção e a recriação dos containers.
