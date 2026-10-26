# Exemplo — Workloads no Kubernetes

## Objetivo do exemplo

Este exercício utiliza um cluster Kubernetes local criado com **kind** e a ferramenta **kubectl** para explorar cinco recursos que controlam a execução de aplicações: `Deployment`, `DaemonSet`, `StatefulSet`, `Job` e `CronJob`.

Ao longo das etapas, você vai criar e consultar um namespace, implantar workloads por meio de manifestos YAML, observar a criação e a remoção de Pods, escalar um Deployment e um StatefulSet, verificar como um DaemonSet reage à exclusão de um Pod e acompanhar Jobs executados uma vez ou de acordo com um agendamento.


## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com o kind.
2. Abra um terminal Bash, como o Git Bash, para executar os comandos deste roteiro. Alguns comandos de observação usam recursos comuns do Bash, como `while`, `head` e `xargs`.
3. Entre no diretório que contém `kind-config.yaml`, `deployment.yaml`, `daemonset.yaml`, `statefulset.yaml`, `job.yaml` e `cronjob.yaml`.
4. Confirme se o cluster existe e se o `kubectl` está apontando para ele.

### Criar o cluster kind

Se o cluster `lab` já estiver criado é necessário criá-lo novamente. Remova o cluster atual:

```bash
kind delete cluster --name lab
```

Crie o cluster por meio do arquivo `kind-config.yaml`:

```bash
kind create cluster --name lab --config kind-config.yaml
```

- `kind create cluster` cria um cluster local.
- `--name lab` define o nome do cluster.
- `--config kind-config.yaml` informa a configuração dos nós.


### Verificar o cluster

```bash
kind get clusters
```

Lista os clusters gerenciados pelo kind. Confirme que `lab` aparece na saída.

```bash
kubectl config view
```

Exibe a configuração de acesso do `kubectl`, incluindo clusters, usuários e contextos disponíveis. Evite compartilhar essa saída sem revisar dados sensíveis.

```bash
kubectl config current-context
```

Mostra o contexto selecionado pelo `kubectl`. Para o cluster criado com `--name lab`, o contexto normalmente é `kind-lab`.

```bash
kubectl cluster-info
```

Verifica a comunicação com o cluster e exibe os endereços dos serviços principais do Kubernetes.

```bash
kubectl get nodes
```

Lista os nós do cluster e seus estados. Antes de continuar, confirme que os nós estão `Ready`.


## 1. Namespace

O namespace `exemplo-06` agrupa os recursos deste exercício e permite consultar ou remover os objetos do laboratório sem misturá-los com workloads de outros namespaces.

### Criar o namespace

```bash
kubectl create namespace exemplo-06
```

Cria o namespace chamado `exemplo-06`. Se ele já existir, o comando informará que o recurso já existe; nesse caso, não é preciso criá-lo de novo.

### Listar os namespaces

```bash
kubectl get namespaces
```

Lista os namespaces do cluster e permite confirmar a existência de `exemplo-06`.

### Consultar recursos do namespace

```bash
kubectl --namespace exemplo-06 get all
```

Lista os recursos mais comuns que o comando `get all` inclui no namespace. **`all` não significa literalmente todos os tipos de recursos Kubernetes**; por exemplo, nem todos os recursos de configuração ou armazenamento são incluídos.

A opção curta `-n` equivale a `--namespace`:

```bash
kubectl -n exemplo-06 get pods,deployments
```

Lista Pods e Deployments do namespace `exemplo-06` em uma única consulta.

> [!IMPORTANT]
> Os comandos `kubectl apply -f deployment.yaml`, `kubectl apply -f daemonset.yaml`, `kubectl apply -f job.yaml` e `kubectl apply -f cronjob.yaml` dependem do namespace definido nos próprios manifestos. Para que os recursos sejam criados em `exemplo-06`, cada manifesto contém `metadata.namespace: exemplo-06`; caso não existisse essa informação, seria necessário utilizar o comando indicando o namespace `kubectl apply -n exemplo-06 -f <arquivo>.yaml`. Se não houver namespace no manifesto nem opção `-n`, o recurso será criado no namespace `default`.


## 2. Deployment

Um `Deployment` declara o estado desejado de uma aplicação, incluindo a quantidade de réplicas. O controlador cria e gerencia um `ReplicaSet`, que por sua vez mantém os Pods. Se um Pod gerenciado for excluído ou falhar, o controlador procura criar outro para restaurar a quantidade desejada.

### Criar o Deployment

```bash
kubectl apply -f deployment.yaml
```

Aplica a definição contida no arquivo `deployment.yaml`. O arquivo usado no exemplo declara o Deployment `k8s-in-action-app`.


### Consultar o Deployment

```bash
kubectl -n exemplo-06 get deployment k8s-in-action-app
```

Mostra um resumo do Deployment, incluindo réplicas desejadas e disponíveis.

```bash
kubectl -n exemplo-06 get deployment k8s-in-action-app -o wide
```

Exibe colunas adicionais para facilitar a inspeção do recurso.

### Descrever o Deployment

```bash
kubectl -n exemplo-06 describe deployment k8s-in-action-app
```

Mostra a configuração detalhada, os seletores, a imagem, o estado das réplicas, o ReplicaSet associado e eventos recentes. É útil para investigar problemas de criação ou atualização.

### Consultar os Pods

```bash
kubectl get pods -n exemplo-06 --show-labels
```

Lista os Pods do namespace e seus rótulos. Os rótulos são usados pelos controladores e Services para identificar recursos relacionados.

```bash
kubectl get pods -n exemplo-06 -l "app=k8s-in-action-app"
```

Filtra a listagem para mostrar somente Pods com o rótulo `app=k8s-in-action-app`.

### Consultar o ReplicaSet associado

```bash
kubectl -n exemplo-06 get replicaset
```

Lista os ReplicaSets existentes no namespace. O Deployment normalmente cria e administra um ReplicaSet com base no template de Pod.

```bash
kubectl -n exemplo-06 get replicaset -o wide
```

Apresenta informações adicionais sobre os ReplicaSets, facilitando a identificação do recurso associado ao Deployment.

### Observar o escalonamento do Deployment

Em um primeiro terminal Bash, execute:

```bash
while true; do clear; kubectl get pods -n exemplo-06; sleep 2; done
```

O laço limpa o terminal, consulta os Pods e espera dois segundos antes de repetir. Ele permite observar a criação ou remoção de réplicas quase em tempo real. Pressione `Ctrl+C` para encerrar o laço.

Em outro terminal, aumente o número desejado de réplicas para dez:

```bash
kubectl -n exemplo-06 scale deployment k8s-in-action-app --replicas=10
```

O controlador tenta ajustar o Deployment para manter dez Pods. A quantidade efetivamente `Ready` pode levar algum tempo para convergir, dependendo do agendamento, do download da imagem e da inicialização da aplicação.

### Excluir Pods e observar a autorrecuperação

O comando abaixo seleciona até quatro Pods da aplicação e solicita sua exclusão sem esperar individualmente que cada exclusão termine:

```bash
kubectl get pods --no-headers -o custom-columns=":metadata.name" -n exemplo-06 -l "app=k8s-in-action-app" | head -n 4 | xargs -r -I {} kubectl delete pod {} -n exemplo-06 --wait=false
```

- `-l "app=k8s-in-action-app"` limita a seleção aos Pods dessa aplicação, evitando excluir Pods não relacionados no namespace.
- `--no-headers` omite o cabeçalho da listagem.
- `-o custom-columns=":metadata.name"` mostra somente o nome de cada Pod.
- `head -n 4` seleciona até quatro nomes.
- `xargs -r -I {}` executa o comando de exclusão para cada nome recebido; `-r` evita executar o comando se não houver entrada.
- `--wait=false` solicita a exclusão sem aguardar a conclusão de cada operação.

Como os Pods são controlados pelo ReplicaSet do Deployment, o Kubernetes tentará criar substitutos para manter a quantidade desejada de réplicas. Volte ao terminal de observação para acompanhar o comportamento.

> [!NOTE]
> O comando acima é destinado a Bash/Git Bash. Em PowerShell, a sintaxe de pipelines e utilitários é diferente.

## 3. DaemonSet

Um `DaemonSet` busca manter uma cópia de um Pod em cada nó elegível do cluster. Diferentemente do Deployment, não se configura um número fixo de réplicas: a quantidade de Pods depende dos nós elegíveis e das restrições de agendamento, como seletores, afinidades, taints e tolerations.

### Criar o DaemonSet

```bash
kubectl apply -f daemonset.yaml
```

Aplica o manifesto `daemonset.yaml`, que deve definir o DaemonSet `k8s-in-action-ds` e usar o namespace `exemplo-06`.


### Verificar o DaemonSet e seus Pods

```bash
kubectl -n exemplo-06 get daemonset
```

Exibe o estado do DaemonSet, incluindo a quantidade de nós desejados, atuais, prontos e disponíveis, conforme a versão do `kubectl`.

```bash
kubectl -n exemplo-06 get pods -l "app=k8s-in-action-ds" -o wide
```

Lista os Pods do DaemonSet e mostra em qual nó cada Pod foi agendado.

### Excluir um Pod e observar sua recriação

O nome completo de um Pod de DaemonSet é gerado pelo Kubernetes e pode mudar. Em vez de depender de um sufixo fixo, selecione um Pod pelo rótulo:

```bash
POD=$(kubectl get pods -n exemplo-06 -l "app=k8s-in-action-ds" -o jsonpath='{.items[0].metadata.name}')
kubectl -n exemplo-06 delete pod "$POD"
```

A primeira linha obtém o nome de um Pod associado ao DaemonSet. A segunda solicita sua exclusão. O DaemonSet deverá criar outro Pod no mesmo nó elegível para restaurar a presença desejada.

Confira os Pods novamente:

```bash
kubectl -n exemplo-06 get pods -l "app=k8s-in-action-ds" -o wide
```

A coluna `NODE` permite acompanhar se o Pod substituto foi criado no mesmo nó.

### Investigar por que o control-plane não recebeu um Pod

```bash
kubectl describe nodes
```

Exibe detalhes de todos os nós, incluindo taints, capacidade, condições e eventos.

```bash
kubectl get nodes
```

Mostra um resumo dos nós e seus estados, por exemplo, `Ready`.

```bash
kubectl describe node lab-control-plane
```

Inspeciona especificamente o nó `lab-control-plane`. Procure a seção `Taints`. Um taint como `node-role.kubernetes.io/control-plane:NoSchedule` impede o agendamento de Pods que não tenham uma toleration correspondente.

Se o DaemonSet não incluir essa toleration, o Pod poderá ser criado nos workers, mas não no control-plane. Para permitir o agendamento no control-plane, o template do Pod precisa tolerar o taint aplicável. A toleration permite o agendamento, mas não obriga o Pod a ser executado em um nó específico.

## 4. StatefulSet

Um `StatefulSet` gerencia aplicações cujos Pods precisam manter uma identidade estável. Diferentemente dos Pods criados por um Deployment, os Pods de um StatefulSet recebem nomes previsíveis com índices ordinais, como `web-0`, `web-1` e `web-2`. Isso é importante para aplicações que precisam distinguir cada instância, como bancos de dados e sistemas distribuídos. Um StatefulSet também pode associar um volume persistente a cada réplica por meio de `volumeClaimTemplates`, quando essa configuração estiver definida no manifesto.

### Criar o StatefulSet

```bash
kubectl apply -f statefulset.yaml
```

Aplica o manifesto `statefulset.yaml` e usar o namespace `exemplo-06`.

### Consultar o StatefulSet

```bash
kubectl -n exemplo-06 get statefulset
```

Lista os StatefulSets do namespace `exemplo-06`. Observe a coluna `READY`, que mostra quantas réplicas estão prontas em relação à quantidade desejada.

### Consultar os Pods do StatefulSet

```bash
kubectl -n exemplo-06 get pods -l "app=nginx" -o wide
```

Lista os Pods que possuem o rótulo `app=nginx` e exibe informações adicionais, incluindo o nó em que cada Pod está executando. O seletor precisa corresponder aos rótulos definidos em `spec.template.metadata.labels` no manifesto do StatefulSet. Se outros Pods do namespace também usarem esse rótulo, eles também aparecerão na listagem.

### Acompanhar os Pods em tempo real

Execute o comando abaixo em um terminal:

```bash
while true; do clear; kubectl -n exemplo-06 get pods -l "app=nginx" -o wide; sleep 2; done
```

O laço limpa a tela, consulta os Pods, aguarda dois segundos e repete a consulta. Ele permite observar a criação e a remoção de réplicas durante o escalonamento. Pressione `Ctrl+C` para encerrar a observação; isso não interrompe os Pods nem altera o StatefulSet.

### Escalar o StatefulSet para quatro réplicas

Em outro terminal, execute:

```bash
kubectl -n exemplo-06 scale statefulset web --replicas=4
```

Altera a quantidade desejada de réplicas do StatefulSet `web` para quatro. O controlador cria os Pods que faltam para alcançar esse estado. Os nomes seguem a identidade ordinal do StatefulSet; por exemplo, se existirem duas réplicas (`web-0` e `web-1`), serão criadas `web-2` e `web-3`. Com a política padrão `OrderedReady`, os Pods são criados sequencialmente, aguardando que cada réplica anterior esteja pronta antes de avançar.

Acompanhe a coluna `READY` do StatefulSet e a lista de Pods. A convergência pode levar algum tempo, dependendo do agendamento, da disponibilidade da imagem e da inicialização da aplicação.

### Reduzir o StatefulSet para zero réplicas

```bash
kubectl -n exemplo-06 scale statefulset web --replicas=0
```

Define a quantidade desejada de réplicas como zero. O controlador encerra os Pods gerenciados pelo StatefulSet, mas **não exclui o próprio objeto StatefulSet**. Se o manifesto utilizar `volumeClaimTemplates`, os PVCs associados são mantidos por padrão durante o escalonamento para zero, salvo se a política de retenção tiver sido configurada de outra forma.

Para confirmar o resultado, execute novamente:

```bash
kubectl -n exemplo-06 get statefulset
kubectl -n exemplo-06 get pods -l "app=nginx" -o wide
```

O StatefulSet deverá continuar listado, com zero réplicas prontas, e não deverá haver Pods correspondentes ao seletor, desde que não existam outros workloads usando o mesmo rótulo.

> [!NOTE]
> Escalar para zero não é o mesmo que excluir o StatefulSet. Para voltar a executar as réplicas, use `kubectl -n exemplo-06 scale statefulset web --replicas=<QUANTIDADE>`; para remover o recurso, use `kubectl -n exemplo-06 delete statefulset web`.

## 5. Job

Um `Job` gerencia Pods destinados a concluir uma tarefa. O objetivo é que a execução termine com sucesso, conforme os parâmetros definidos no manifesto. Diferentemente de um Deployment, um Job não mantém indefinidamente uma aplicação em execução.

### Criar o Job

```bash
kubectl apply -f job.yaml
```

Aplica o manifesto que define o Job `processamento`.


### Verificar a criação e o estado do Job

```bash
kubectl -n exemplo-06 get jobs
```

Lista os Jobs do namespace e apresenta informações como conclusões e estado geral.

```bash
kubectl -n exemplo-06 get jobs processamento
```

Consulta especificamente o Job chamado `processamento`.

```bash
kubectl -n exemplo-06 describe job processamento
```

Exibe detalhes do Job, seus parâmetros de execução, contadores de sucesso/falha e eventos.

```bash
kubectl -n exemplo-06 get pods
```

Lista os Pods do namespace. Um Job cria um ou mais Pods, dependendo da configuração e do resultado das execuções.

```bash
kubectl -n exemplo-06 get jobs processamento
```

Consulte novamente o Job depois de algum tempo para observar se a tarefa terminou. A coluna `COMPLETIONS` ajuda a identificar a conclusão esperada.

### Consultar os logs do Pod do Job

Os nomes dos Pods de um Job são gerados dinamicamente e, por isso, o nome de exemplo pode não corresponder à execução atual. Use o nome real obtido com `kubectl get pods` ou execute:

```bash
POD=$(kubectl get pods -n exemplo-06 -l job-name=processamento -o jsonpath='{.items[0].metadata.name}')
kubectl -n exemplo-06 logs "$POD"
```

A primeira linha obtém o nome do primeiro Pod associado ao Job `processamento`; a segunda exibe seus logs. Se o Job ainda não tiver criado um Pod, ou se não houver correspondência, será necessário verificar novamente o estado do Job e dos Pods.

## 6. CronJob

Um `CronJob` cria Jobs conforme uma programação definida no manifesto. O arquivo do exercício se chama `cronjob.yaml` e os comandos abaixo usam `every-minute-job` como o nome do CronJob ilustrativo. Confira os nomes reais definidos no YAML antes de executar os comandos, pois eles precisam corresponder a `metadata.name`.

### Criar o CronJob

```bash
kubectl apply -f cronjob.yaml
```

Aplica o manifesto do CronJob.

### Consultar o CronJob e a programação

```bash
kubectl -n exemplo-06 get cronjobs
```

Lista os CronJobs do namespace, mostrando sua programação, a última execução e o estado de suspensão, entre outras informações.

```bash
kubectl -n exemplo-06 describe cronjob
```

Exibe detalhes dos CronJobs do namespace, incluindo a expressão de agendamento, o Job template, as políticas de concorrência e os eventos. Para inspecionar apenas um CronJob, informe seu nome, por exemplo `kubectl -n exemplo-06 describe cronjob every-minute-job`.

### Acompanhar Jobs e Pods criados pelo CronJob

```bash
kubectl -n exemplo-06 get jobs
```

Lista os Jobs que já foram criados pelo CronJob. O Kubernetes cria Jobs conforme a programação definida e o funcionamento do controlador.

```bash
kubectl -n exemplo-06 get pods
```

Lista os Pods criados pelos Jobs. Como os nomes incluem identificadores gerados dinamicamente, não reutilize um nome de Pod copiado de uma execução anterior sem confirmar que ele ainda existe.

Para acompanhar novas criações em tempo real:

```bash
kubectl -n exemplo-06 get jobs --watch
```

O comando permanece aberto atualizando a saída quando ocorrerem alterações. Pressione `Ctrl+C` para encerrar a observação.

```bash
kubectl -n exemplo-06 get pods --watch
```

Permite acompanhar a criação, a conclusão e a remoção dos Pods. Também termina com `Ctrl+C` sem interromper os workloads.

### Descrever e consultar logs de uma execução

Obtenha o nome real de um Job com `kubectl -n exemplo-06 get jobs` e substitua o valor `NOME_REAL_DO_JOB` nos comandos abaixo.

```bash
kubectl -n exemplo-06 get jobs NOME_REAL_DO_JOB -o wide
```

Exibe informações adicionais do Job selecionado. Substitua o nome ilustrativo pelo que aparecer na sua saída.

```bash
kubectl -n exemplo-06 describe job NOME_REAL_DO_JOB
```

Exibe detalhes, estado, eventos e informações sobre os Pods associados ao Job. Use o nome real retornado pelo comando `get jobs`.

```bash
kubectl -n exemplo-06 describe jobs
```

Descreve os Jobs do namespace, útil para observar várias execuções criadas pelo CronJob.

Para localizar um Pod associado a uma execução específica, consulte `kubectl get pods -n exemplo-06` e use o nome que estiver presente na saída:

```bash
kubectl -n exemplo-06 describe pod <NOME_REAL_DO_POD>
kubectl -n exemplo-06 logs <NOME_REAL_DO_POD>
```

O primeiro comando apresenta detalhes e eventos do Pod; o segundo mostra os logs do container. Substitua `<NOME_REAL_DO_POD>` pelo nome real — os nomes de exemplo do roteiro não são fixos.

## Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `kind get clusters` | Lista os clusters locais gerenciados pelo kind. |
| `kubectl config current-context` | Mostra o contexto Kubernetes atual. |
| `kubectl cluster-info` | Verifica a comunicação com o cluster. |
| `kubectl get nodes` | Lista os nós e seus estados. |
| `kubectl create namespace exemplo-06` | Cria o namespace do exercício. |
| `kubectl -n exemplo-06 get all` | Lista os principais tipos de recursos no namespace; não literalmente todos os tipos. |
| `kubectl apply -n exemplo-06 -f deployment.yaml` | Cria ou atualiza o Deployment no namespace do exercício. |
| `kubectl -n exemplo-06 get deployment k8s-in-action-app -o wide` | Consulta o Deployment com informações adicionais. |
| `kubectl -n exemplo-06 describe deployment k8s-in-action-app` | Exibe detalhes e eventos do Deployment. |
| `kubectl get pods -n exemplo-06 --show-labels` | Lista Pods e seus rótulos. |
| `kubectl -n exemplo-06 get replicaset -o wide` | Lista ReplicaSets e informações adicionais. |
| `kubectl -n exemplo-06 scale deployment k8s-in-action-app --replicas=10` | Altera a quantidade desejada de réplicas do Deployment. |
| `kubectl apply -n exemplo-06 -f daemonset.yaml` | Cria ou atualiza o DaemonSet no namespace do exercício. |
| `kubectl -n exemplo-06 get daemonset` | Consulta o estado do DaemonSet. |
| `kubectl -n exemplo-06 get pods -l app=k8s-in-action-ds -o wide` | Lista os Pods do DaemonSet e os nós em que estão executando. |
| `kubectl apply -n exemplo-06 -f statefulset.yaml` | Cria ou atualiza o StatefulSet definido no manifesto. |
| `kubectl -n exemplo-06 get statefulset` | Lista os StatefulSets do namespace e mostra o estado das réplicas. |
| `kubectl -n exemplo-06 get pods -l "app=nginx" -o wide` | Lista os Pods selecionados pelo rótulo `app=nginx` e mostra os nós de execução. |
| `kubectl -n exemplo-06 scale statefulset web --replicas=4` | Define quatro réplicas desejadas para o StatefulSet `web`. |
| `kubectl -n exemplo-06 scale statefulset web --replicas=0` | Encerra os Pods do StatefulSet `web`, mantendo o objeto StatefulSet. |
| `kubectl -n exemplo-06 delete statefulset web` | Exclui o objeto StatefulSet `web`. Verifique separadamente a política de retenção dos PVCs, se houver. |
| `kubectl apply -n exemplo-06 -f job.yaml` | Cria ou atualiza o Job. |
| `kubectl -n exemplo-06 describe job processamento` | Exibe estado, eventos e informações de execução do Job. |
| `kubectl -n exemplo-06 logs <NOME_REAL_DO_POD>` | Consulta os logs de um Pod de Job ou CronJob. |
| `kubectl apply -n exemplo-06 -f cronjob.yaml` | Cria ou atualiza o CronJob. |
| `kubectl -n exemplo-06 get cronjobs` | Lista os CronJobs do namespace. |
| `kubectl -n exemplo-06 get jobs --watch` | Acompanha alterações nos Jobs. |
| `kubectl -n exemplo-06 get pods --watch` | Acompanha alterações nos Pods. |

## Encerrar e limpar o laboratório

Para remover os recursos de um workload específico, use o manifesto correspondente:

```bash
kubectl delete -f deployment.yaml
kubectl delete -f daemonset.yaml
kubectl delete -f statefulset.yaml
kubectl delete -f job.yaml
kubectl delete -f cronjob.yaml
```

Esses comandos removem os recursos declarados nos manifestos. Alguns deles também controlam Pods ou Jobs relacionados; o resultado exato depende dos objetos definidos nos arquivos.

Para excluir todo o namespace e os recursos que ele contém:

```bash
kubectl delete namespace exemplo-06
```

> [!WARNING]
> A exclusão do namespace remove os recursos nele contidos. Use-a apenas quando tiver certeza de que não precisa mais dos workloads e dos dados mantidos nesse namespace.

Para remover o cluster kind inteiro:

```bash
kind delete cluster --name lab
```

Esse comando remove o cluster local, seus nós e os recursos dentro dele. Ele não desinstala o kind nem o kubectl da máquina.

## Referências

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Namespaces](https://kubernetes.io/docs/concepts/overview/working-with-objects/namespaces/)
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | DaemonSets](https://kubernetes.io/docs/concepts/workloads/controllers/daemonset/)
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
> - [Kubernetes Documentation | Jobs](https://kubernetes.io/docs/concepts/workloads/controllers/job/)
> - [Kubernetes Documentation | CronJobs](https://kubernetes.io/docs/concepts/workloads/controllers/cron-jobs/)
> - [Kubernetes Documentation | Labels and Selectors](https://kubernetes.io/docs/concepts/overview/working-with-objects/labels/)
> - [Kubernetes Documentation | kubectl](https://kubernetes.io/docs/reference/kubectl/)
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
