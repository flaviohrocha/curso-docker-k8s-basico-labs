# Executando e atualizando cargas de trabalho no Kubernetes — Guia de comandos


## 1. Pods

Um Pod é a menor unidade de computação que o Kubernetes cria e gerencia. Os containers de um mesmo Pod compartilham a rede e o espaço de portas, podendo comunicar-se por `localhost`; eles também podem compartilhar volumes declarados no Pod.

### Criar um Pod

Exemplo de manifesto `pod.yaml`:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: nginx

spec:
  containers:
    - name: nginx
      image: nginx:1.27
```

Aplique o manifesto e consulte o estado do Pod:

```bash
kubectl apply -f pod.yaml
kubectl get pod nginx
kubectl describe pod nginx
kubectl get pod nginx -o yaml
```

### Especificar uma imagem e a política de download

```yaml
spec:
  containers:
    - name: nginx
      image: nginx:1.27
      imagePullPolicy: IfNotPresent
```

Os principais valores de `imagePullPolicy` são `IfNotPresent` (usa uma imagem local quando disponível), `Always` (solicita a resolução da imagem quando o container é iniciado, podendo aproveitar camadas em cache) e `Never` (não tenta baixar a imagem).

Uma imagem também pode ser referenciada pelo digest, por exemplo `registry.exemplo.com/app@sha256:<DIGEST>`, para identificar um conteúdo específico em vez de depender de uma tag.

### Utilizar credenciais de um registry privado

O Secret referenciado por `imagePullSecrets` deve existir no mesmo namespace do Pod.

```yaml
spec:
  imagePullSecrets:
    - name: registry-credentials
```

### Configurar o contexto de segurança

O `securityContext` pode ser definido no nível do Pod:

```yaml
spec:
  securityContext:
    runAsUser: 1000
    runAsGroup: 3000
    fsGroup: 2000
```

Ou no nível do container:

```yaml
containers:
  - name: app
    image: minha-aplicacao:1.0
    securityContext:
      allowPrivilegeEscalation: false
      runAsNonRoot: true
```

Exemplo completo de segurança apresentado na aula:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: security-demo

spec:
  securityContext:
    runAsUser: 1000
    runAsGroup: 3000
    runAsNonRoot: true

  containers:
    - name: app
      image: nginx:1.27
      securityContext:
        allowPrivilegeEscalation: false
        capabilities:
          drop:
            - ALL
        seccompProfile:
          type: RuntimeDefault
```

O exemplo demonstra `runAsUser`, `runAsGroup`, `runAsNonRoot`, `allowPrivilegeEscalation`, remoção de capabilities e o perfil `seccomp` padrão do runtime.

### Diagnosticar o ciclo de vida do Pod

```bash
kubectl get pods
kubectl describe pod nginx
kubectl get pod nginx -o yaml
kubectl logs nginx
```

Use `describe` para verificar eventos e motivos de espera ou falha, como `ErrImagePull`, `ImagePullBackOff`, `CrashLoopBackOff` e `OOMKilled`. A fase geral do Pod (`Pending`, `Running`, `Succeeded`, `Failed` ou `Unknown`) não é a mesma coisa que o estado individual de cada container (`Waiting`, `Running` ou `Terminated`).

> [!NOTE]
> `kubectl logs nginx` pressupõe que existe um Pod chamado `nginx` e que ele possui um container cujos logs possam ser consultados. Quando houver mais de um container, indique-o com `-c <NOME-DO-CONTAINER>`.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Pods](https://kubernetes.io/docs/concepts/workloads/pods/)
> - [Kubernetes Documentation | Init Containers](https://kubernetes.io/docs/concepts/workloads/pods/init-containers/)
> - [Kubernetes Documentation | Sidecar Containers](https://kubernetes.io/docs/concepts/workloads/pods/sidecar-containers/)

---

## 2. Deployment

O `Deployment` gerencia normalmente aplicações stateless. Ele cria e administra um `ReplicaSet`, que mantém a quantidade desejada de Pods. Ao alterar o template dos Pods, o Deployment pode criar uma nova revisão e atualizar gradualmente as réplicas.

### Exemplo básico

Manifesto `deployment.yaml`:

```yaml
apiVersion: apps/v1
kind: Deployment

metadata:
  name: nginx

spec:
  replicas: 3

  selector:
    matchLabels:
      app: nginx

  template:
    metadata:
      labels:
        app: nginx

    spec:
      containers:
        - name: nginx
          image: nginx:1.27
```

Aplique e consulte os recursos:

```bash
kubectl apply -f deployment.yaml
kubectl get deployment nginx
kubectl get replicasets
kubectl get pods -l app=nginx
kubectl get pods -l app=nginx -o wide
kubectl get pods -l app=nginx --watch
kubectl describe deployment nginx
```

### Exemplo com parâmetros de atualização

Este é outro exemplo da aula, com estratégia e parâmetros explícitos para o rollout:

```yaml
apiVersion: apps/v1
kind: Deployment

metadata:
  name: nginx

spec:
  replicas: 3

  selector:
    matchLabels:
      app: nginx

  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 1
      maxSurge: 1

  minReadySeconds: 10
  progressDeadlineSeconds: 600
  revisionHistoryLimit: 10

  template:
    metadata:
      labels:
        app: nginx

    spec:
      containers:
        - name: nginx
          image: nginx:1.27
          ports:
            - containerPort: 80
```

- `maxUnavailable` define quantos Pods podem ficar indisponíveis durante o `RollingUpdate`.
- `maxSurge` limita quantos Pods adicionais podem existir acima da quantidade desejada durante o rollout.
- `minReadySeconds` define por quanto tempo o Pod novo precisa permanecer `Ready` para ser considerado disponível.
- `progressDeadlineSeconds` determina o prazo para identificar uma atualização sem progresso; excedê-lo não provoca rollback automático.
- `revisionHistoryLimit` define quantos ReplicaSets antigos devem ser mantidos para possibilitar rollback.

### Comandos do Deployment

```bash
# Criar ou atualizar a partir do manifesto
kubectl apply -f deployment.yaml

# Consultar e detalhar
kubectl get deployment nginx
kubectl describe deployment nginx
kubectl get replicasets
kubectl get pods -l app=nginx -o wide

# Escalar para cinco réplicas
kubectl scale deployment/nginx --replicas=5

# Alterar a imagem
kubectl set image deployment/nginx nginx=nginx:1.28

# Acompanhar a atualização
kubectl rollout status deployment/nginx

# Consultar o histórico de revisões
kubectl rollout history deployment/nginx

# Consultar detalhes de uma revisão específica
kubectl rollout history deployment/nginx --revision=2

# Pausar ou retomar o rollout
kubectl rollout pause deployment/nginx
kubectl rollout resume deployment/nginx

# Reiniciar os Pods gradualmente
kubectl rollout restart deployment/nginx

# Reverter para a revisão anterior ou para uma revisão específica
kubectl rollout undo deployment/nginx
kubectl rollout undo deployment/nginx --to-revision=2

# Remover o Deployment
kubectl delete deployment nginx
```

> [!NOTE]
> `kubectl rollout pause` e `kubectl rollout resume` são operações compatíveis com Deployment, não com DaemonSet ou StatefulSet.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | ReplicaSet](https://kubernetes.io/docs/concepts/workloads/controllers/replicaset/)

---

## 3. DaemonSet

Um `DaemonSet` busca manter uma cópia de um Pod em cada Node elegível. Não se define a quantidade de réplicas com `spec.replicas`: a quantidade depende dos nós elegíveis e das regras de agendamento.

### Exemplo da aula 06.2

Manifesto `daemonset.yaml`:

```yaml
apiVersion: apps/v1
kind: DaemonSet

metadata:
  name: node-agent
  namespace: kube-system

spec:
  selector:
    matchLabels:
      app: node-agent

  template:
    metadata:
      labels:
        app: node-agent

    spec:
      containers:
        - name: node-agent
          image: exemplo/node-agent:1.0
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              memory: 256Mi
```

> [!NOTE]
> O manifesto da aula 06.2 declara `namespace: kube-system`. Ao executar consultas ou atualizações desse exemplo a partir de outro namespace padrão, informe `-n kube-system`, por exemplo `kubectl -n kube-system get daemonset node-agent`. Os comandos apresentados na tabela original pressupõem que o namespace de execução esteja corretamente selecionado.

### Exemplo com estratégia de atualização

A aula 06.3 apresenta este exemplo independente para demonstrar o controle do rollout:

```yaml
apiVersion: apps/v1
kind: DaemonSet

metadata:
  name: node-agent

spec:
  selector:
    matchLabels:
      app: node-agent

  updateStrategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 1
      maxSurge: 0

  minReadySeconds: 5
  revisionHistoryLimit: 10

  template:
    metadata:
      labels:
        app: node-agent

    spec:
      containers:
        - name: agent
          image: registry.example.com/infra/node-agent:1.0
```

### Comandos do DaemonSet

```bash
# Criar ou atualizar
kubectl apply -f daemonset.yaml

# Consultar e detalhar o DaemonSet
kubectl get daemonset node-agent
kubectl describe daemonset node-agent

# Consultar Pods e os Nodes em que estão executando
kubectl get pods -l app=node-agent
kubectl get pods -l app=node-agent -o wide
kubectl get pods -l app=node-agent --watch

# Atualizar a imagem (exemplo da aula 06.2)
kubectl set image daemonset/node-agent node-agent=exemplo/node-agent:1.1

# Atualizar a imagem (exemplo da aula 06.3)
kubectl set image daemonset/node-agent agent=registry.example.com/infra/node-agent:2.0

# Acompanhar a atualização e consultar o histórico
kubectl rollout status daemonset/node-agent
kubectl rollout history daemonset/node-agent

# Reverter para a revisão anterior ou para uma revisão específica
kubectl rollout undo daemonset/node-agent
kubectl rollout undo daemonset/node-agent --to-revision=2

# Remover
kubectl delete daemonset node-agent
```

A estratégia padrão `RollingUpdate` substitui os Pods de forma controlada. Com `OnDelete`, alterar o template não substitui automaticamente os Pods existentes: é necessário excluir os Pods antigos para que sejam recriados com o template atualizado.

```bash
# Exemplo de exclusão manual de um Pod (substitua pelo nome real)
kubectl delete pod <NOME-DO-POD>
```

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | DaemonSet](https://kubernetes.io/docs/concepts/workloads/controllers/daemonset/)
> - [Kubernetes Documentation | Perform a Rolling Update on a DaemonSet](https://kubernetes.io/docs/tasks/manage-daemon/update-daemon-set/)
> - [Kubernetes Documentation | Perform a Rollback on a DaemonSet](https://kubernetes.io/docs/tasks/manage-daemon/rollback-daemon-set/)

---

## 4. StatefulSet

O `StatefulSet` gerencia Pods com identidade estável, como `web-0`, `web-1` e `web-2`. Pode associar um PVC próprio a cada réplica por meio de `volumeClaimTemplates`. É comum utilizar um Headless Service para oferecer identidade de rede estável aos Pods.

### Exemplo da aula 06.2: Headless Service e armazenamento persistente

Manifesto `statefulset.yaml`:

```yaml
apiVersion: v1
kind: Service

metadata:
  name: nginx

spec:
  clusterIP: None
  selector:
    app: nginx
  ports:
    - name: web
      port: 80
      targetPort: 80
---
apiVersion: apps/v1
kind: StatefulSet

metadata:
  name: web

spec:
  serviceName: nginx
  replicas: 3

  selector:
    matchLabels:
      app: nginx

  template:
    metadata:
      labels:
        app: nginx

    spec:
      containers:
        - name: nginx
          image: nginx:1.27
          ports:
            - name: web
              containerPort: 80
          volumeMounts:
            - name: www
              mountPath: /usr/share/nginx/html

  volumeClaimTemplates:
    - metadata:
        name: www
      spec:
        accessModes:
          - ReadWriteOnce
        resources:
          requests:
            storage: 1Gi
```

Neste exemplo, são criados três Pods com identidade estável e um PVC por Pod, por exemplo, `www-web-0`, `www-web-1` e `www-web-2`. O provisionamento depende das StorageClasses e da infraestrutura de armazenamento disponíveis no cluster.

### Exemplo da aula 06.3: estratégia de atualização

Este manifesto é um exemplo independente, com estratégia de atualização explícita:

```yaml
apiVersion: v1
kind: Service

metadata:
  name: app-headless

spec:
  clusterIP: None
  selector:
    app: app
  ports:
    - name: web
      port: 80
      targetPort: 8080
---
apiVersion: apps/v1
kind: StatefulSet

metadata:
  name: app

spec:
  serviceName: app-headless
  replicas: 3

  selector:
    matchLabels:
      app: app

  updateStrategy:
    type: RollingUpdate
    rollingUpdate:
      partition: 0

  minReadySeconds: 10
  revisionHistoryLimit: 10

  template:
    metadata:
      labels:
        app: app
    spec:
      containers:
        - name: app
          image: registry.example.com/equipe/app:1.0
          ports:
            - name: web
              containerPort: 8080
```

Com `RollingUpdate`, o StatefulSet normalmente atualiza os Pods em ordem decrescente de ordinal, como `app-2`, `app-1` e `app-0`, aguardando que cada Pod atualizado fique disponível antes de prosseguir. `partition` permite restringir quais ordinais receberão a revisão nova.

### Comandos do StatefulSet

```bash
# Criar ou atualizar
kubectl apply -f statefulset.yaml

# Consultar o StatefulSet, Pods e PVCs
kubectl get statefulset web
kubectl get pods -l app=nginx
kubectl get pods -l app=nginx --watch
kubectl get pvc
kubectl describe statefulset web

# Escalar para cinco réplicas
kubectl scale sts web --replicas=5

# Atualizar a imagem (exemplo da aula 06.2)
kubectl set image statefulset/web nginx=nginx:1.28

# Acompanhar atualização e consultar histórico
kubectl rollout status statefulset/web
kubectl rollout history statefulset/web

# Comandos do exemplo de atualização da aula 06.3
kubectl get statefulsets
kubectl describe statefulset app
kubectl set image statefulset/app app=registry.example.com/equipe/app:2.0
kubectl rollout status statefulset/app
kubectl rollout history statefulset/app
kubectl rollout history statefulset/app --revision=2
kubectl rollout undo statefulset/app
kubectl rollout undo statefulset/app --to-revision=2
kubectl get pods -w -l app=app

# Excluir um Pod (nome real) para recriação sob OnDelete ou após rollback bloqueado
kubectl delete pod <NOME-DO-POD>

# Remover o StatefulSet do exemplo da aula 06.2
kubectl delete statefulset web
```

> [!WARNING]
> Excluir ou reverter um StatefulSet não desfaz alterações nos dados persistentes. Um rollout pode ficar bloqueado quando um Pod não consegue atingir `Running` e `Ready`; em determinadas situações, depois de restaurar o template, também será necessário excluir o Pod problemático para que ele seja recriado com a configuração corrigida. A retenção dos PVCs depende da política configurada.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)

---

## 5. Job

Um `Job` gerencia uma tarefa que deve terminar com sucesso, podendo criar um ou mais Pods até alcançar a quantidade desejada de conclusões.

### Exemplo de manifesto

Manifesto `job.yaml`:

```yaml
apiVersion: batch/v1
kind: Job

metadata:
  name: processamento

spec:
  completions: 10
  parallelism: 3
  backoffLimit: 4
  ttlSecondsAfterFinished: 300

  template:
    spec:
      restartPolicy: Never
      containers:
        - name: processamento
          image: busybox:1.36
          command:
            - sh
            - -c
            - |
              echo "Processando..."
              sleep 10
              echo "Processamento concluído"
```

O manifesto solicita dez conclusões bem-sucedidas, com até três Pods executando em paralelo, e permite a remoção automática do Job concluído após 300 segundos.

### Comandos do Job

```bash
# Criar ou atualizar
kubectl apply -f job.yaml

# Consultar e detalhar
kubectl get job processamento
kubectl get jobs
kubectl describe job processamento
kubectl get job processamento -o yaml

# Consultar e acompanhar os Pods do Job
kubectl get pods -l job-name=processamento
kubectl get pods -l job-name=processamento --watch

# Consultar os logs dos Pods associados ao Job
kubectl logs -l job-name=processamento

# Suspender ou retomar a criação de Pods do Job
kubectl patch job processamento -p '{"spec":{"suspend":true}}'
kubectl patch job processamento -p '{"spec":{"suspend":false}}'

# Remover
kubectl delete job processamento
```

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Jobs](https://kubernetes.io/docs/concepts/workloads/controllers/job/)

---

## 6. CronJob

O `CronJob` cria Jobs de forma recorrente conforme uma expressão Cron. Cada Job gerencia os Pods de uma execução específica.

### Exemplo de manifesto

Manifesto `cronjob.yaml`:

```yaml
apiVersion: batch/v1
kind: CronJob

metadata:
  name: backup

spec:
  schedule: "0 2 * * *"

  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: backup
              image: exemplo/backup:1.0
          restartPolicy: OnFailure
```

A expressão `0 2 * * *` programa a execução diária às 02:00, conforme o fuso horário aplicado pelo cluster/CronJob.

### Comandos do CronJob

```bash
# Criar ou atualizar
kubectl apply -f cronjob.yaml

# Consultar e detalhar
kubectl get cronjob backup
kubectl get cronjobs
kubectl describe cronjob backup
kubectl get cronjob backup -o yaml

# Criar uma execução manual a partir do CronJob
kubectl create job --from=cronjob/backup backup-manual

# Acompanhar Jobs e Pods
kubectl get jobs --watch
kubectl get job <JOB-GERADO>
kubectl get pods -l job-name=<JOB-GERADO>
kubectl get pods -l job-name=<JOB-GERADO> --watch

# Consultar logs da execução (use o nome/label do Job correspondente)
kubectl logs -l job-name=<JOB-GERADO>

# Suspender ou retomar futuras execuções programadas
kubectl patch cronjob backup -p '{"spec":{"suspend":true}}'
kubectl patch cronjob backup -p '{"spec":{"suspend":false}}'

# Remover o CronJob
kubectl delete cronjob backup
```

`<JOB-GERADO>` é um marcador: substitua-o pelo nome real de um Job criado pelo CronJob. Suspender um CronJob impede novas execuções programadas; não significa, por si só, interromper Jobs que já estejam em execução.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | CronJob](https://kubernetes.io/docs/concepts/workloads/controllers/cron-jobs/)

---

## 7. Rolling update e rollback

O processo operacional descrito na aula 06.3 é: **Update**, **Verify** e, se a nova versão não for validada e houver revisão apropriada, **Rollback**. O controlador aplica a atualização de forma assíncrona; o comando que altera o template não significa que o rollout já terminou.

### Atualizar a imagem de um controlador

Sintaxe geral apresentada na aula:

```bash
kubectl set image <TIPO>/<NOME> <CONTAINER>=<NOVA-IMAGEM>
```

Exemplo de Deployment:

```bash
kubectl set image deployment/app app=registry.example.com/app:2.0
```

Também é possível alterar o manifesto YAML e aplicá-lo novamente:

```bash
kubectl apply -f deployment.yaml
```

Uma alteração no `spec.template`, como imagem, variáveis, recursos ou probes, normalmente inicia uma nova revisão. Alterar somente a quantidade de réplicas não cria, por si só, uma nova revisão do Deployment.

### Acompanhar a atualização

```bash
# Sintaxe geral
kubectl rollout status <TIPO>/<NOME>

# Exemplos por controlador
kubectl rollout status deployment/app
kubectl rollout status daemonset/node-agent
kubectl rollout status statefulset/app
```

Complementarmente, inspecione os Pods, eventos e logs:

```bash
kubectl get pods -o wide
kubectl describe pod <NOME-DO-POD>
kubectl logs <NOME-DO-POD>
```

A conclusão do rollout não substitui testes funcionais da aplicação. Verifique a versão executada, a prontidão (`Ready`), os eventos, os logs e as requisições esperadas.

### Consultar histórico e reverter

```bash
# Consultar histórico
kubectl rollout history <TIPO>/<NOME>

# Inspecionar uma revisão específica
kubectl rollout history <TIPO>/<NOME> --revision=<REVISAO>

# Reverter para a revisão anterior
kubectl rollout undo <TIPO>/<NOME>

# Reverter para uma revisão específica
kubectl rollout undo <TIPO>/<NOME> --to-revision=<REVISAO>
```

Esses comandos são usados com Deployment, DaemonSet e StatefulSet, respeitando as revisões disponíveis no histórico do recurso.

> [!WARNING]
> Rollback restaura o template dos Pods; ele não desfaz automaticamente alterações externas realizadas pela aplicação, como migrações de banco de dados, alterações em filas ou dados persistentes. Mantenha procedimentos próprios de recuperação e backup.

---

## 8. Referência rápida

| Operação | Comando |
| --- | --- |
| Consultar os Pods | `kubectl get pods` |
| Consultar Pods por label | `kubectl get pods -l app=nginx` |
| Exibir detalhes e eventos do Pod | `kubectl describe pod nginx` |
| Consultar logs | `kubectl logs <POD>` |
| Aplicar manifesto | `kubectl apply -f <ARQUIVO>.yaml` |
| Consultar Deployment | `kubectl get deployment nginx` |
| Escalar Deployment | `kubectl scale deployment/nginx --replicas=5` |
| Atualizar imagem de Deployment | `kubectl set image deployment/nginx nginx=nginx:1.28` |
| Consultar ReplicaSets | `kubectl get replicasets` |
| Consultar DaemonSet | `kubectl get daemonset node-agent` |
| Atualizar imagem de DaemonSet | `kubectl set image daemonset/node-agent node-agent=exemplo/node-agent:1.1` |
| Consultar StatefulSet | `kubectl get statefulset web` |
| Consultar PVCs | `kubectl get pvc` |
| Escalar StatefulSet | `kubectl scale sts web --replicas=5` |
| Consultar Job | `kubectl get job processamento` |
| Consultar Pods do Job | `kubectl get pods -l job-name=processamento` |
| Consultar CronJob | `kubectl get cronjob backup` |
| Criar Job manual a partir do CronJob | `kubectl create job --from=cronjob/backup backup-manual` |
| Acompanhar rollout | `kubectl rollout status <TIPO>/<NOME>` |
| Consultar histórico | `kubectl rollout history <TIPO>/<NOME>` |
| Rollback | `kubectl rollout undo <TIPO>/<NOME>` |
| Rollback para revisão específica | `kubectl rollout undo <TIPO>/<NOME> --to-revision=<REVISAO>` |
| Remover um recurso pelo manifesto | `kubectl delete -f <ARQUIVO>.yaml` |

---

## Referências gerais

> [!NOTE]
> **Para saber mais**
>
> **Pods e gerenciamento de cargas de trabalho**
> - [Kubernetes Documentation | Pods](https://kubernetes.io/docs/concepts/workloads/pods/)
> - [Kubernetes Documentation | Init Containers](https://kubernetes.io/docs/concepts/workloads/pods/init-containers/)
> - [Kubernetes Documentation | Sidecar Containers](https://kubernetes.io/docs/concepts/workloads/pods/sidecar-containers/)
> - [Kubernetes Documentation | Workload Management](https://kubernetes.io/docs/concepts/workloads/controllers/)
>
> **Controladores**
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | ReplicaSet](https://kubernetes.io/docs/concepts/workloads/controllers/replicaset/)
> - [Kubernetes Documentation | DaemonSet](https://kubernetes.io/docs/concepts/workloads/controllers/daemonset/)
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
> - [Kubernetes Documentation | Jobs](https://kubernetes.io/docs/concepts/workloads/controllers/job/)
> - [Kubernetes Documentation | CronJob](https://kubernetes.io/docs/concepts/workloads/controllers/cron-jobs/)
>
> **Atualizações e rollback**
> - [Kubernetes Documentation | Performing a Rolling Update](https://kubernetes.io/docs/tutorials/kubernetes-basics/update/update-intro/)
> - [Kubernetes Documentation | Perform a Rolling Update on a DaemonSet](https://kubernetes.io/docs/tasks/manage-daemon/update-daemon-set/)
> - [Kubernetes Documentation | Perform a Rollback on a DaemonSet](https://kubernetes.io/docs/tasks/manage-daemon/rollback-daemon-set/)
