# Laboratório 09 | Implantando a aplicação Spring PetClinic no Kubernetes

## Objetivo

Implantar a aplicação Spring PetClinic em um cluster Kubernetes local criado com `kind`. A solução utiliza frontend, backend e PostgreSQL, com configuração e credenciais em recursos próprios, armazenamento persistente e exposição HTTP por meio de um `Ingress`.

Ao final do laboratório, você deverá ser capaz de:

- criar um cluster `kind` com múltiplos nós e encaminhamento de portas para o host;
- instalar e preparar um Ingress Controller;
- aplicar e inspecionar manifestos Kubernetes;
- configurar `ConfigMap`, `Secret`, `PersistentVolume` e `PersistentVolumeClaim`;
- executar o PostgreSQL em um `StatefulSet` e a aplicação em `Deployments`;
- verificar `startupProbe`, `livenessProbe` e `readinessProbe`;
- instalar o `metrics-server` e consultar o consumo de CPU e memória dos Pods;
- remover os recursos criados pelo laboratório.

## Orientações

- Execute os comandos em um terminal Bash, como Git Bash ou WSL. Alguns comandos de shell podem exigir adaptação no PowerShell.
- O Docker Engine ou Docker Desktop, `kind` e `kubectl` devem estar instalados e disponíveis no `PATH`.
- Execute os comandos a partir da raiz do diretório `lab-09`. O arquivo `kind-config.yaml` deve estar nessa pasta, e os manifestos devem estar no subdiretório `petclinic/`.
- Organize os arquivos com estes nomes, sem sufixos entre parênteses:

```text
lab-09/
├── kind-config.yaml
└── petclinic/
    ├── 00-namespace.yaml
    ├── 01-configmap.yaml
    ├── 02-secret.yaml
    ├── 03-persistent-volume.yaml
    ├── 04-persistent-volume-claim.yaml
    ├── 05-database-service.yaml
    ├── 06-database-statefulset.yaml
    ├── 07-frontend-deployment.yaml
    ├── 08-frontend-service.yaml
    ├── 09-backend-deployment.yaml
    ├── 10-backend-service.yaml
    └── 11-ingress.yaml
```

- Os manifestos utilizam as imagens `flavioh/spring-petclinic-ui:1.0.1`, `flavioh/spring-petclinic-api:1.0.0` e `postgres:16.3`.
- Verifique se as portas **80 e 443** estão disponíveis no host. A configuração do `kind` encaminha essas portas ao nó `control-plane`.
- As credenciais do banco presentes no `Secret` são somente para demonstração. Não as reutilize em ambientes reais.

> [!WARNING]
> O `PersistentVolume` usa `hostPath`, que armazena os dados no sistema de arquivos de um nó Kubernetes. Em um cluster `kind`, os nós são containers; portanto, esse caminho não representa diretamente um diretório do host. Essa configuração serve para o laboratório, não para armazenamento de produção.

> [!IMPORTANT]
> Este roteiro usa o Ingress NGINX para reproduzir o exemplo. O projeto foi aposentado em março de 2026 e seu repositório está arquivado. Utilize-o apenas para fins didáticos ou para reproduzir ambientes existentes; para novas implantações, avalie um controlador mantido ou a Gateway API. Consulte as referências ao final.

## Cenário

```text
Navegador
   |
   | http://petclinic.localhost
   v
Ingress Controller (NGINX)
   |                         \
   | /petclinic/api           \ /
   v                            v
backend:9966                 frontend:8080
   |
   | database:5432
   v
PostgreSQL (StatefulSet)
   |
   v
PersistentVolumeClaim -> PersistentVolume
```

## Tarefas

### 1. Criar o cluster `kind`

A partir da raiz de `lab-09`, examine o arquivo `kind-config.yaml`. Ele define um nó `control-plane`, dois nós `worker`, o rótulo `ingress-ready=true` no `control-plane` e o encaminhamento das portas 80 e 443 do host para o cluster.

1. Confirme que o Docker está em execução e verifique as versões de `kind` e `kubectl`.
2. Crie um cluster chamado `lab-09`, utilizando o arquivo de configuração fornecido.
3. Verifique o cluster atual, os nós e seus rótulos. Confirme que os três nós estão `Ready` e que o nó `control-plane` possui o rótulo `ingress-ready=true`.

### 2. Instalar e preparar o Ingress Controller

O recurso `Ingress` não implementa o roteamento por conta própria. É necessário instalar um Ingress Controller que interprete as regras do manifesto `petclinic/11-ingress.yaml`.

1. Instale o Ingress NGINX usando o manifesto oficial para `kind` indicado no exemplo.
2. Confirme ou aplique o rótulo `ingress-ready=true` ao nó `lab-09-control-plane`.
3. Configure o Deployment `ingress-nginx-controller` para selecionar esse nó.
4. Aguarde o rollout do controlador e verifique os Pods e a classe `nginx`.

### 3. Validar e aplicar os manifestos da aplicação

Todos os comandos desta etapa devem ser executados na raiz de `lab-09`, usando `petclinic/` como caminho dos manifestos.

1. Valide os manifestos com `kubectl apply --dry-run=client`.
2. Aplique os manifestos do diretório `petclinic/`.
3. Confira o namespace, os recursos da aplicação, o `PersistentVolume`, o `PersistentVolumeClaim` e o `Ingress`.
4. Aguarde os rollouts do `StatefulSet` `database` e dos Deployments `backend` e `frontend`.
5. Caso algum Pod não fique pronto, investigue os eventos e os logs correspondentes.

### 4. Verificar o acesso à aplicação

1. Acesse [http://petclinic.localhost/](http://petclinic.localhost/) no navegador.
2. Verifique se a interface da Spring PetClinic é exibida.
3. Consulte o recurso `Ingress` e confirme as regras que encaminham `/petclinic/api` e `/petclinic/actuator` ao backend e as demais rotas ao frontend.
4. Caso o acesso falhe, verifique os Pods, os Services, os EndpointSlices, os eventos e os logs do Ingress Controller.

### 5. Inspecionar as probes

Examine os manifestos de `backend` e `database` e identifique o tipo, o caminho ou comando de cada probe e os parâmetros de execução.

1. Inspecione o Deployment `backend` e o StatefulSet `database` com `kubectl`.
2. No navegador, acesse os seguintes endpoints do backend:
   - [http://petclinic.localhost/petclinic/actuator/health](http://petclinic.localhost/petclinic/actuator/health)
   - [http://petclinic.localhost/petclinic/actuator/health/liveness](http://petclinic.localhost/petclinic/actuator/health/liveness)
   - [http://petclinic.localhost/petclinic/actuator/health/readiness](http://petclinic.localhost/petclinic/actuator/health/readiness)
3. Relacione os endpoints com as probes configuradas no manifesto do backend.
4. Confira o estado dos Pods e investigue eventos caso as verificações de saúde não apresentem o resultado esperado.

### 6. Instalar o `metrics-server` e consultar métricas

1. Instale o `metrics-server` usando o manifesto oficial publicado no repositório `kubernetes-sigs/metrics-server`.
2. Confira o Deployment e os Pods do `metrics-server` no namespace `kube-system`.
3. Para este cluster de laboratório, ajuste os argumentos do Deployment adicionando `--kubelet-insecure-tls`.
4. Aguarde o rollout e verifique novamente o estado do Deployment.
5. Consulte os Pods do namespace `petclinic` e os recursos configurados no Deployment `backend`.
6. Utilize `kubectl top` para consultar o consumo de CPU e memória dos Pods e dos nós.
7. Se as métricas ainda não estiverem disponíveis, aguarde um pouco e confira os logs e os eventos do `metrics-server`.

> [!WARNING]
> A opção `--kubelet-insecure-tls` desativa a validação do certificado TLS apresentado pelo kubelet. Ela é usada aqui como ajuste de laboratório para o `kind`; não a adote como configuração de segurança para um cluster de produção.

### 7. Limpar o ambiente

1. Remova os recursos declarados nos manifestos de `petclinic/`.
2. Confirme que os recursos da aplicação foram removidos.
3. Remova o Ingress Controller instalado para o laboratório.
4. Exclua o cluster `kind` `lab-09` quando não precisar mais dele.

> [!WARNING]
> A exclusão do cluster remove os nós containerizados do `kind` e pode apagar os dados armazenados nos diretórios `hostPath`. Confirme que não precisa preservar os dados antes de remover o cluster.

## Resultado esperado

Ao concluir o laboratório, você terá criado um cluster `kind`, publicado a Spring PetClinic por meio de um Ingress, inspecionado as probes do backend e do banco de dados e consultado métricas de consumo dos Pods com `kubectl top`.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
> - [kind | Ingress](https://kind.sigs.k8s.io/docs/user/ingress/)
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [Kubernetes Documentation | Services](https://kubernetes.io/docs/concepts/services-networking/service/)
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | Startup, Liveness and Readiness Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/)
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
> - [Kubernetes Documentation | Resource Metrics Pipeline](https://kubernetes.io/docs/tasks/debug/debug-cluster/resource-metrics-pipeline/)
> - [Metrics Server | GitHub repository](https://github.com/kubernetes-sigs/metrics-server)
> - [Kubernetes Documentation | Ingress NGINX Retirement: What You Need to Know](https://kubernetes.io/blog/2025/11/11/ingress-nginx-retirement/)
> - [Ingress NGINX Controller | Repositório arquivado](https://github.com/kubernetes/ingress-nginx)
