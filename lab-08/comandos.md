# Recursos e disponibilidade de cargas de trabalho no Kubernetes — Guia de comandos

## 1. Requests e limits

Os campos `resources.requests` e `resources.limits` definem, respectivamente, os recursos solicitados para fins de agendamento e os limites de uso aplicados ao container. Os recursos mais comuns são CPU e memória.

### Definir requests e limits em um container

Exemplo de manifesto `pod.yaml`:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  containers:
    - name: app
      image: nginx:1.27
      resources:
        requests:
          cpu: "250m"
          memory: "128Mi"
        limits:
          cpu: "500m"
          memory: "256Mi"
```

Nesse exemplo, o container solicita `250m` de CPU e `128Mi` de memória. O agendador considera os `requests` para decidir em qual Node o Pod pode ser colocado; o container pode utilizar mais do que o `request` quando houver capacidade disponível, respeitando os `limits`.

| Campo | Finalidade |
| --- | --- |
| `resources.requests.cpu` | CPU solicitada para fins de agendamento. |
| `resources.requests.memory` | Memória solicitada para fins de agendamento. |
| `resources.limits.cpu` | Limite de CPU que o container pode utilizar. |
| `resources.limits.memory` | Limite de memória do container. |
| `resources.requests.ephemeral-storage` | Armazenamento local efêmero solicitado. |
| `resources.limits.ephemeral-storage` | Limite de armazenamento local efêmero. |

### Unidades comuns

| Recurso | Valor | Significado |
| --- | --- | --- |
| CPU | `1` | Aproximadamente uma CPU. |
| CPU | `500m` | 0,5 CPU. |
| CPU | `250m` | 0,25 CPU. |
| CPU | `100m` | 0,1 CPU. |
| Memória | `M`, `G` | Megabytes e gigabytes, em unidades decimais. |
| Memória | `Ki`, `Mi`, `Gi` | Kibibytes, mebibytes e gibibytes. |

### Recursos totais de um Pod com múltiplos containers

Os requests e limits do Pod são calculados pela soma dos valores definidos em seus containers. Por exemplo:

```yaml
spec:
  containers:
    - name: app
      image: nginx:1.27
      resources:
        requests:
          cpu: "250m"
          memory: "64Mi"
        limits:
          cpu: "500m"
          memory: "128Mi"

    - name: log-aggregator
      image: busybox:1.36
      command: ["sh", "-c", "sleep 3600"]
      resources:
        requests:
          cpu: "250m"
          memory: "64Mi"
        limits:
          cpu: "500m"
          memory: "128Mi"
```

Nesse exemplo, o Pod solicita `500m` de CPU e `128Mi` de memória; seus limites somados são `1` CPU e `256Mi` de memória.

### Consultar e diagnosticar recursos de um Pod

```bash
# Aplicar o manifesto
kubectl apply -f pod.yaml

# Consultar o Pod
kubectl get pod app
kubectl get pod app -o wide

# Ver configuração, estado e eventos do Pod
kubectl describe pod app

# Consultar a definição aplicada pelo Kubernetes
kubectl get pod app -o yaml
```

O agendador considera principalmente os `requests`, e não o consumo atual do Pod. Um Pod pode permanecer `Pending` mesmo que o consumo observado de CPU ou memória do Node esteja baixo, caso não exista capacidade alocável suficiente para atender aos requests.

### Recursos no nível do Pod

Versões atuais do Kubernetes também oferecem `PodLevelResources`, que permite declarar um orçamento de CPU e memória no nível do Pod. Na aula, essa funcionalidade é apresentada como Beta desde o Kubernetes v1.34 e habilitada por padrão.

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: pod-resources-demo
  namespace: pod-resources-example
spec:
  resources:
    limits:
      cpu: "1"
      memory: "200Mi"
    requests:
      cpu: "1"
      memory: "100Mi"
  containers:
    - name: pod-resources-demo-ctr-1
      image: nginx
      resources:
        limits:
          cpu: "0.5"
          memory: "100Mi"
        requests:
          cpu: "0.5"
          memory: "50Mi"
    - name: pod-resources-demo-ctr-2
      image: fedora
      command: ["sleep", "inf"]
```

O segundo container não define requests e limits individuais e, nesse exemplo, compartilha o orçamento de recursos disponível no nível do Pod.

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Resource Management for Pods and Containers](https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/)

---

## 2. ResourceQuota

Um `ResourceQuota` limita o consumo agregado de recursos que podem ser solicitados ou limitados no Namespace. Não substitui a configuração de `requests` e `limits` de cada container.

### Exemplo de manifesto `resourcequota.yaml`

```yaml
apiVersion: v1
kind: ResourceQuota

metadata:
  name: mem-cpu-demo
  namespace: mynamespace

spec:
  hard:
    requests.cpu: "2"
    requests.memory: 1Gi
    limits.cpu: "3"
    limits.memory: 2Gi
```

A cota desse exemplo limita a soma dos requests a 2 CPUs e 1 GiB de memória e a soma dos limits a 3 CPUs e 2 GiB de memória no Namespace.

### Aplicar, consultar e remover um ResourceQuota

```bash
# Criar ou atualizar a cota
kubectl apply -f resourcequota.yaml -n mynamespace

# Consultar as cotas do Namespace
kubectl get resourcequota -n mynamespace

# Consultar detalhes
kubectl describe resourcequota mem-cpu-demo -n mynamespace

# Consultar a definição YAML
kubectl get resourcequota mem-cpu-demo -o yaml -n mynamespace

# Remover a cota
kubectl delete resourcequota mem-cpu-demo -n mynamespace
```

Quando um recurso controlado pela cota não tem os valores exigidos definidos, ou a soma ultrapassa o limite, a criação ou atualização de uma carga de trabalho pode ser rejeitada. Um `LimitRange` pode fornecer valores padrão para preencher campos omitidos.

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Resource Quotas](https://kubernetes.io/docs/concepts/policy/resource-quotas/)

---

## 3. LimitRange

Um `LimitRange` define valores padrão e restrições de recursos para objetos individuais em um Namespace. Diferentemente do `ResourceQuota`, que controla a soma dos recursos do Namespace, ele pode definir valores padrão, mínimos e máximos para containers, Pods ou solicitações de armazenamento.

### Exemplo de manifesto `limitrange.yaml`

```yaml
apiVersion: v1
kind: LimitRange

metadata:
  name: cpu-resource-constraint
  namespace: mynamespace

spec:
  limits:
    - type: Container
      default:
        cpu: 500m
      defaultRequest:
        cpu: 500m
      min:
        cpu: 100m
      max:
        cpu: "1"
```

Nesse exemplo, cada container recebe um limit e um request padrão de `500m` de CPU; a configuração restringe os valores de CPU ao intervalo de `100m` a `1` CPU conforme os campos definidos.

### Aplicar e consultar um LimitRange

```bash
# Criar ou atualizar a configuração
kubectl apply -f limitrange.yaml -n mynamespace

# Listar LimitRanges
kubectl get limitrange -n mynamespace

# Exibir os detalhes
kubectl describe limitrange cpu-resource-constraint -n mynamespace

# Consultar a definição YAML
kubectl get limitrange cpu-resource-constraint -o yaml -n mynamespace
```

Para remover o recurso:

```bash
kubectl delete limitrange cpu-resource-constraint -n mynamespace
```

Os valores padrão são aplicados durante a admissão de novos objetos. Criar ou modificar um `LimitRange` não altera retroativamente os Pods que já existem no Namespace.

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Limit Ranges](https://kubernetes.io/docs/concepts/policy/limit-range/)

---

## 4. Probes de inicialização, atividade e prontidão

Probes são verificações periódicas executadas pelo `kubelet` para avaliar o estado dos containers. Cada probe deve utilizar exatamente um mecanismo de verificação: `exec`, `httpGet`, `tcpSocket` ou `grpc`.

| Probe | Campo | Finalidade | Efeito quando falha repetidamente |
| --- | --- | --- | --- |
| Inicialização | `startupProbe` | Verifica se a aplicação terminou de iniciar. | O container é encerrado e reiniciado conforme a política do Pod. Os outros probes não são executados até o startup passar. |
| Atividade | `livenessProbe` | Verifica se a aplicação continua funcionando. | O kubelet encerra o container; a política de reinicialização determina o comportamento posterior. |
| Prontidão | `readinessProbe` | Verifica se a aplicação pode receber tráfego. | O Pod fica não pronto e deixa de receber tráfego pelos Services correspondentes; a falha, por si só, não reinicia o container. |

### Exemplo com probes HTTP

O manifesto a seguir demonstra os três tipos de probe. Os endpoints são ilustrativos e precisam existir na aplicação.

Crie o namespace usado no exemplo antes de aplicar o manifesto:

```bash
kubectl create namespace pod-resources-example
```

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: probe-example

spec:
  containers:
    - name: app
      image: registry.example.com/app:1.0
      ports:
        - containerPort: 8080

      startupProbe:
        httpGet:
          path: /healthz
          port: 8080
        failureThreshold: 30
        periodSeconds: 10

      livenessProbe:
        httpGet:
          path: /healthz
          port: 8080
        periodSeconds: 10
        timeoutSeconds: 2
        failureThreshold: 3

      readinessProbe:
        httpGet:
          path: /ready
          port: 8080
        periodSeconds: 5
        timeoutSeconds: 2
        failureThreshold: 3
```

- Enquanto o `startupProbe` não passar, os probes de liveness e readiness não são executados.
- O `livenessProbe` acima é executado a cada 10 segundos; três falhas consecutivas acionam o encerramento do container.
- O `readinessProbe` é executado a cada 5 segundos. Enquanto falhar, o Pod não é considerado pronto para receber tráfego.

### Mecanismos de verificação

#### HTTP (`httpGet`)

```yaml
readinessProbe:
  httpGet:
    path: /ready
    port: 8080
    scheme: HTTP
  periodSeconds: 5
  timeoutSeconds: 2
```

Respostas HTTP com códigos entre `200` e `399` são consideradas bem-sucedidas. Os campos mais comuns são `path`, `port`, `scheme`, `host` e `httpHeaders`.

#### TCP (`tcpSocket`)

```yaml
livenessProbe:
  tcpSocket:
    port: 8080
  periodSeconds: 10
  timeoutSeconds: 2
```

A verificação passa quando é possível estabelecer uma conexão TCP com a porta especificada. Isso não comprova, por si só, que a aplicação esteja processando corretamente as requisições.

#### Execução de comando (`exec`)

```yaml
readinessProbe:
  exec:
    command: ["sh", "-c", "test -f /tmp/ready"]
  periodSeconds: 5
```

O comando é considerado bem-sucedido quando termina com código de saída `0`. Use um comando que realmente verifique a condição desejada na aplicação.

#### gRPC (`grpc`)

```yaml
livenessProbe:
  grpc:
    port: 50051
  periodSeconds: 10
  timeoutSeconds: 2
```

Esse mecanismo utiliza o protocolo de verificação de saúde gRPC; a porta deve ser numérica e o serviço deve implementar o protocolo de health checking compatível.

### Campos relevantes dos probes

| Campo | Finalidade |
| --- | --- |
| `initialDelaySeconds` | Tempo inicial de espera antes de executar o probe. |
| `periodSeconds` | Intervalo entre execuções. |
| `timeoutSeconds` | Tempo máximo de espera por uma resposta. |
| `successThreshold` | Resultados consecutivos bem-sucedidos necessários após uma falha. Deve ser `1` para startup e liveness. |
| `failureThreshold` | Quantidade de falhas consecutivas necessária para considerar a verificação malsucedida. |
| `terminationGracePeriodSeconds` | Período de tolerância para encerramento após falha de startup ou liveness; não se aplica ao readiness. |

Valores padrão importantes: `initialDelaySeconds: 0`, `periodSeconds: 10`, `timeoutSeconds: 1`, `successThreshold: 1` e `failureThreshold: 3`. Ajuste-os de acordo com o tempo real de inicialização e o comportamento da aplicação.

### Aplicar e investigar os probes

```bash
# Aplicar o manifesto de exemplo
kubectl apply -f probe-example.yaml

# Consultar o Pod e seu estado
kubectl get pod probe-example
kubectl get pod probe-example -o wide

# Inspecionar probes, condições e eventos do Pod
kubectl describe pod probe-example

# Consultar a configuração aplicada
kubectl get pod probe-example -o yaml

# Consultar logs da aplicação
kubectl logs probe-example
```

Em workloads gerenciados por Deployment, também é possível consultar o recurso controlador e os Pods correspondentes:

```bash
kubectl describe deployment NOME_DO_DEPLOYMENT -n NAMESPACE
kubectl get pods -n NAMESPACE -o wide
kubectl describe pod NOME_DO_POD -n NAMESPACE
kubectl logs NOME_DO_POD -n NAMESPACE
```

> [!WARNING]
> Uma falha de readiness normalmente não gera reinicialização do container. Uma falha de liveness ou startup que atinge `failureThreshold` pode encerrar o container. Uma probe excessivamente rigorosa pode provocar reinicializações desnecessárias.

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Liveness, Readiness, and Startup Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/)  
> [Kubernetes Documentation | Configure Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/tasks/configure-pod-container/configure-liveness-readiness-probes/)

---

## 5. Consultar e acompanhar eventos com `kubectl events`

`kubectl events` lista eventos recentes do cluster. É possível restringir a consulta a um Namespace, consultar todos os Namespaces, filtrar por um recurso específico e acompanhar eventos novos com `--watch`.

### Listar eventos recentes

```bash
# Eventos do Namespace atual
kubectl events

# Eventos de um Namespace específico
kubectl events -n NAMESPACE

# Eventos de todos os Namespaces
kubectl events --all-namespaces

# Somente eventos do tipo Warning
kubectl events -n NAMESPACE --types=Warning

# Somente eventos Normal e Warning
kubectl events -n NAMESPACE --types=Normal,Warning
```

### Acompanhar eventos em tempo real

```bash
# Acompanhar novos eventos do Namespace
kubectl events -n NAMESPACE --watch

# Acompanhar eventos Warning no Namespace
kubectl events -n NAMESPACE --types=Warning --watch

# Acompanhar eventos Warning em todos os Namespaces
kubectl events --all-namespaces --types=Warning --watch
```

### Filtrar eventos de um recurso específico

A opção `--for` filtra eventos relacionados ao recurso indicado. Informe um único recurso no formato `TIPO/NOME`.

```bash
# Consultar eventos de um Pod específico
kubectl events -n NAMESPACE --for pod/NOME_DO_POD

# Acompanhar eventos de um Pod específico
kubectl events -n NAMESPACE --for pod/NOME_DO_POD --watch

# Acompanhar eventos associados ao Deployment
kubectl events -n NAMESPACE --for deployment/NOME_DO_DEPLOYMENT --watch

# Acompanhar eventos do StatefulSet
kubectl events -n NAMESPACE --for statefulset/NOME_DO_STATEFULSET --watch
```

**Importante:** filtrar por `deployment/NOME` não garante que sejam exibidos todos os eventos dos Pods criados pelo Deployment. Eventos de probes, como `Unhealthy` e `Readiness probe failed`, geralmente estão associados ao Pod. Para observar eventos de falha de todos os Pods de uma aplicação, acompanhe os eventos `Warning` do Namespace e identifique os Pods pelo nome.

### Exibir eventos em outros formatos

```bash
# Saída em YAML
kubectl events -n NAMESPACE -o yaml

# Saída em JSON
kubectl events -n NAMESPACE -o json
```

### Alternativa com `kubectl get events`

```bash
# Listar eventos do Namespace, ordenados pelo último timestamp
kubectl get events -n NAMESPACE --sort-by=.lastTimestamp

# Listar eventos de todos os Namespaces
kubectl get events --all-namespaces --sort-by=.lastTimestamp

# Consultar os eventos Warning recentes usando field selector
kubectl get events -n NAMESPACE --field-selector=type=Warning --sort-by=.lastTimestamp
```

### Diagnosticar falhas de probes

```bash
# Acompanhar eventos Warning do Namespace
kubectl events -n NAMESPACE --types=Warning --watch

# Em outro terminal, acompanhar os Pods
kubectl get pods -n NAMESPACE -w

# Examinar as probes e eventos de um Pod
kubectl describe pod NOME_DO_POD -n NAMESPACE

# Consultar logs da aplicação
kubectl logs NOME_DO_POD -n NAMESPACE
```

Eventos como `Unhealthy`, `Liveness probe failed` ou `Readiness probe failed` podem ajudar a identificar falhas. O Kubernetes não gera um evento para cada execução bem-sucedida de um probe; portanto, a ausência de eventos não comprova que as verificações não estão sendo executadas. Acompanhe também a coluna `READY` dos Pods e as condições do recurso.

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | kubectl events](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_events/)  
> [Kubernetes Documentation | kubectl get](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_get/)

---

## Referências consolidadas

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Resource Management for Pods and Containers](https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/)  
> [Kubernetes Documentation | Resource Quotas](https://kubernetes.io/docs/concepts/policy/resource-quotas/)  
> [Kubernetes Documentation | Limit Ranges](https://kubernetes.io/docs/concepts/policy/limit-range/)  
> [Kubernetes Documentation | Liveness, Readiness, and Startup Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/)  
> [Kubernetes Documentation | Configure Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/tasks/configure-pod-container/configure-liveness-readiness-probes/)  
> [Kubernetes Documentation | kubectl events](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_events/)
