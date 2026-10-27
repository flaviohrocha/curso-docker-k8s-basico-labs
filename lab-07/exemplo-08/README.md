# Exemplo — Deployment, Service e descoberta DNS no Kubernetes

## Objetivo do exemplo

Este exercício utiliza um cluster Kubernetes local criado com **kind** e a ferramenta **kubectl** para implantar uma aplicação, disponibilizá-la por meio de um `Service` do tipo `NodePort` e verificar como o Kubernetes identifica os Pods que atendem ao Service.

Ao longo das etapas, você criará um namespace, aplicará os manifestos de Deployment e Service, consultará os Pods e seus endpoints, acessará a aplicação pelo navegador, inspecionará as variáveis de ambiente do container e executará uma consulta DNS a partir de um Pod temporário.


## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com o kind.
2. Abra um terminal Bash, como o Git Bash, para executar os comandos deste roteiro.
3. Entre no diretório que contém `kind-config.yaml`, `deployment.yaml` e `service.yaml`.
4. Confira se a configuração do cluster publica a porta `30080` do host para a porta `30080` do nó do kind. Essa configuração é necessária para acessar `http://localhost:30080/` a partir do navegador. O manifesto do Service também deve declarar `nodePort: 30080`.

### Criar o cluster kind

Se o cluster `lab` já estiver criado é necessário criá-lo novamente. Remova o cluster atual:

```bash
kind delete cluster --name lab
```

Crie o cluster por meio do arquivo `kind-config.yaml`:

```bash
kind create cluster --name lab --config kind-config.yaml
```

- `kind create cluster` cria um cluster Kubernetes local.
- `--name lab` define o nome do cluster.
- `--config kind-config.yaml` informa a topologia do cluster e os mapeamentos de portas definidos para seus nós.


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

O namespace `exemplo-08` agrupa os recursos utilizados neste exercício e facilita consultas e operações de limpeza sem afetar recursos de outros namespaces.

### Criar o namespace

```bash
kubectl create namespace exemplo-08
```

Cria o namespace `exemplo-08`. Se ele já existir, o comando informará que o recurso existe; nesse caso, não é necessário criá-lo novamente.

### Listar os namespaces

```bash
kubectl get namespaces
```

Lista os namespaces do cluster e permite confirmar a existência de `exemplo-08`.

### Consultar recursos do namespace

```bash
kubectl -n exemplo-08 get all
```

Lista os tipos de recursos mais comuns incluídos por `get all` no namespace. **A palavra `all` não significa todos os tipos de recursos existentes na API do Kubernetes.** Como o namespace acabou de ser criado, é normal que a listagem ainda não apresente a aplicação.

## 2. Deployment

Um `Deployment` descreve o estado desejado da aplicação, como a imagem do container e o número de réplicas. O controlador cria e gerencia um `ReplicaSet`, responsável por manter os Pods de acordo com a quantidade desejada.

### Criar o Deployment

```bash
kubectl apply -f deployment.yaml
```

Lê o arquivo `deployment.yaml` e cria ou atualiza o recurso declarado nele.

### Consultar o Deployment

```bash
kubectl -n exemplo-08 get deployment k8s-in-action-app
```

Exibe o estado resumido do Deployment `k8s-in-action-app`, incluindo a quantidade de réplicas desejadas, atualizadas e disponíveis.

```bash
kubectl -n exemplo-08 get deployment k8s-in-action-app -o wide
```

Exibe colunas adicionais sobre o Deployment, facilitando a inspeção do recurso.

### Consultar os Pods

```bash
kubectl get pods -n exemplo-08
```

Lista os Pods no namespace `exemplo-08`. Verifique se estão no estado `Running` e se a coluna `READY` indica que os containers estão prontos.

Para ver também os rótulos e os nós em que os Pods estão executando:

```bash
kubectl get pods -n exemplo-08 --show-labels -o wide
```

Os rótulos são importantes porque o Service seleciona os Pods por meio deles. O rótulo usado pelo Service deve corresponder ao definido em `spec.template.metadata.labels` no Deployment.

## 3. Service

Um `Service` oferece um ponto de acesso estável para um conjunto de Pods. Ele encontra os Pods de destino por meio do campo `spec.selector` e encaminha o tráfego para a porta definida em `targetPort`.

Neste exercício, o Service deve se chamar `k8s-in-action-app`, estar no namespace `exemplo-08` e utilizar `type: NodePort`, com `nodePort: 30080`. O `selector` precisa corresponder ao rótulo dos Pods criados pelo Deployment.

### Aplicar o manifesto do Service

```bash
kubectl apply -f service.yaml
```

Cria ou atualiza o Service descrito em `service.yaml`.

### Consultar o Service

```bash
kubectl -n exemplo-08 get services
```

Lista os Services do namespace. Verifique o nome, o tipo, o `ClusterIP` e as portas. Para um Service do tipo `NodePort`, a saída deve mostrar a porta interna do Service e a porta de nó `30080`.

Para ver a configuração completa:

```bash
kubectl -n exemplo-08 get service k8s-in-action-app -o yaml
```

O YAML permite conferir `spec.type`, `spec.selector`, `spec.ports`, o `port`, o `targetPort` e o `nodePort` efetivamente armazenados no cluster.

### Como as portas se relacionam

No exemplo, as portas têm responsabilidades diferentes:

| Configuração | Propósito |
| --- | --- |
| `hostPort: 30080` no `kind-config.yaml` | Porta publicada na máquina host. |
| `containerPort: 30080` no `kind-config.yaml` | Porta de destino no container Docker que representa o nó Kubernetes. |
| `nodePort: 30080` no Service | Porta exposta nos nós pelo Service. Deve corresponder à porta encaminhada pelo kind. |
| `port: 3000` no Service | Porta lógica do Service dentro do cluster. |
| `targetPort: 3000` no Service | Porta do container da aplicação para a qual o tráfego é encaminhado. |
| `containerPort: 3000` no Pod | Documenta a porta que a aplicação utiliza no container; essa declaração isolada não publica a porta no host. |

O fluxo esperado é `localhost:30080` no host → porta `30080` do nó do kind → Service `NodePort` → Pods da aplicação na porta `3000`.

## 4. Acessar a aplicação

Abra o endereço a seguir no navegador, desde que o Deployment esteja pronto, o Service tenha endpoints disponíveis e o mapeamento de portas esteja definido corretamente no `kind-config.yaml`:

[http://localhost:30080/](http://localhost:30080/)

Esse acesso depende de duas configurações que precisam concordar: a porta `30080` deve estar mapeada do host para o nó no kind, e o Service deve utilizar `nodePort: 30080`. Além disso, a aplicação precisa estar escutando na porta `3000` e em uma interface acessível pela rede do Pod, normalmente `0.0.0.0`, e não somente em `127.0.0.1` dentro do container.

Se a página não abrir, confirme o estado dos Pods, do Service e dos endpoints antes de investigar outras camadas:

```bash
kubectl -n exemplo-08 get deployment k8s-in-action-app
kubectl -n exemplo-08 get pods -o wide
kubectl -n exemplo-08 get service k8s-in-action-app
```

## 5. Consultar os EndpointSlices

Os `EndpointSlices` registram os endereços de rede associados aos endpoints de um Service. Eles permitem verificar quais Pods foram identificados como destinos para receber tráfego.

```bash
kubectl -n exemplo-08 get endpointslices
```

Lista os EndpointSlices no namespace `exemplo-08`. Se houver mais de um Service, a saída pode incluir EndpointSlices de todos eles.

Para consultar somente os EndpointSlices associados ao Service `k8s-in-action-app`:

```bash
kubectl -n exemplo-08 get endpointslices \
  -l kubernetes.io/service-name=k8s-in-action-app -o wide
```

A opção `-l` filtra os recursos pelo rótulo `kubernetes.io/service-name`. Verifique se os endereços dos Pods esperados aparecem. Se não houver endpoints, confira se o selector do Service corresponde aos rótulos dos Pods e se eles estão prontos.

## 6. Consultar as variáveis de ambiente do container

O comando original de exemplo usa um nome de Pod gerado dinamicamente:

```bash
kubectl -n exemplo-08 exec -t k8s-in-action-app-84b848456f-74db2 -- env
```

Esse comando executa `env` dentro do container e mostra suas variáveis de ambiente. O nome `k8s-in-action-app-84b848456f-74db2` é apenas um exemplo: nomes de Pods gerenciados por Deployment podem mudar após atualizações, recriações ou escalonamento. A opção `-t` solicita um terminal, mas não é necessária para executar `env`.

Uma alternativa que evita depender do nome de um Pod específico é executar o comando usando o Deployment como recurso-alvo:

```bash
kubectl -n exemplo-08 exec deployment/k8s-in-action-app -- env
```

O `kubectl` seleciona um dos Pods gerenciados pelo Deployment e executa `env` no container correspondente. O comando lista as variáveis de ambiente disponíveis no processo do container, incluindo as definidas no manifesto e outras fornecidas pelo ambiente de execução.

> [!NOTE]
> Se o Pod tiver mais de um container, pode ser necessário indicar o container com `-c <NOME_DO_CONTAINER>`.


## 7. Testar a resolução DNS do Service

O Kubernetes disponibiliza nomes DNS para Services. Para um Service chamado `k8s-in-action-app` no namespace `exemplo-08`, o nome DNS completo normalmente é:

```text
k8s-in-action-app.exemplo-08.svc.cluster.local
```

O sufixo `cluster.local` é o padrão comum, mas pode variar conforme a configuração do cluster.

### Executar nslookup em um Pod temporário

```bash
kubectl run dns-test \
  -n exemplo-08 \
  --image=busybox:1.36 \
  --restart=Never \
  --rm -it \
  -- nslookup k8s-in-action-app.exemplo-08.svc.cluster.local
```

O comando cria um Pod temporário usando a imagem BusyBox, executa `nslookup` e remove o Pod ao terminar:

- `kubectl run dns-test` define o nome do Pod de diagnóstico.
- `-n exemplo-08` cria o Pod de teste no namespace da aplicação.
- `--image=busybox:1.36` escolhe uma imagem que inclui o utilitário `nslookup`.
- `--restart=Never` cria um Pod diretamente, sem um controlador que tente reiniciá-lo.
- `--rm` solicita a remoção do Pod ao encerrar a sessão.
- `-it` mantém uma sessão interativa e aloca um terminal para acompanhar a saída.
- `--` separa as opções do `kubectl` do comando executado dentro do container.
- `nslookup ...` consulta o DNS do cluster para o nome completo do Service.

Se a resolução funcionar, o `nslookup` deverá exibir o endereço IP associado ao Service. Para um Service do tipo `NodePort`, a consulta DNS normalmente retorna o `ClusterIP` do Service, e não o `nodePort` `30080` nem os endereços IP individuais dos Pods.

Se o comando retornar `NXDOMAIN`, verifique primeiro se o Service existe no namespace correto e se o nome consultado corresponde exatamente ao nome do Service:

```bash
kubectl -n exemplo-08 get services
kubectl -n exemplo-08 get service k8s-in-action-app -o wide
```

Também é possível listar os componentes DNS do cluster:

```bash
kubectl get pods -n kube-system -l k8s-app=kube-dns
kubectl get service kube-dns -n kube-system
```

Esses comandos ajudam a verificar o estado do CoreDNS e do Service DNS do cluster. A existência de endpoints vazios, por si só, normalmente não causa `NXDOMAIN`: o nome do Service pode resolver mesmo quando não há Pods de destino prontos.

> [!NOTE]
> O aviso `couldn't attach to pod` pode aparecer se o comando dentro do Pod terminar antes de o `kubectl` conseguir anexar a sessão. Se isso dificultar a leitura dos resultados, crie um Pod de diagnóstico persistente com `sleep`, execute `kubectl exec ... -- nslookup ...` e, ao concluir, remova-o com `kubectl delete pod`.

## 8. Remover os recursos

### Remover o Deployment

```bash
kubectl delete -f deployment.yaml
```

Solicita a exclusão do recurso definido no arquivo `deployment.yaml`. O Deployment e os recursos gerenciados por ele, como ReplicaSets e Pods, serão removidos de acordo com o processo de exclusão do Kubernetes.

### Remover o Service

```bash
kubectl delete -f service.yaml
```

Remove o Service descrito no arquivo `service.yaml`. Depois dessa exclusão, o ponto de acesso estável fornecido pelo Service deixa de existir.


### Confirmar a limpeza

```bash
kubectl -n exemplo-08 get deployment,service,pods
```

Consulta os Deployments, Services e Pods restantes no namespace. É normal receber uma mensagem indicando que não há recursos desses tipos, caso todos tenham sido removidos.

Se desejar excluir o namespace inteiro e todos os recursos nele contidos, execute:

```bash
kubectl delete namespace exemplo-08
```

> [!WARNING]
> A exclusão do namespace remove todos os recursos namespaced que existam nele, não apenas os recursos deste roteiro. Utilize esse comando somente se tiver certeza de que não há outros recursos que devam ser preservados.

## Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `kind create cluster --name lab --config kind-config.yaml` | Cria o cluster kind a partir do arquivo de configuração. |
| `kubectl create namespace exemplo-08` | Cria o namespace do exercício. |
| `kubectl apply -f deployment.yaml` | Cria ou atualiza o Deployment descrito no manifesto, respeitando o namespace declarado nele. |
| `kubectl -n exemplo-08 get deployment k8s-in-action-app -o wide` | Consulta o estado do Deployment e informações adicionais. |
| `kubectl get pods -n exemplo-08` | Lista os Pods do namespace. |
| `kubectl apply -f service.yaml` | Cria ou atualiza o Service a partir do manifesto. |
| `kubectl -n exemplo-08 get services` | Lista os Services do namespace. |
| `kubectl -n exemplo-08 get endpointslices` | Lista os EndpointSlices do namespace. |
| `kubectl -n exemplo-08 exec deployment/k8s-in-action-app -- env` | Executa `env` em um Pod selecionado pelo Deployment. |
| `kubectl -n exemplo-08 get endpointslices -l kubernetes.io/service-name=k8s-in-action-app -o wide` | Mostra os endpoints associados ao Service especificado. |
| `kubectl delete -f deployment.yaml` | Remove o Deployment definido no manifesto. |
| `kubectl delete -f service.yaml` | Remove o Service definido no manifesto. |

## Referências

> [!NOTE]
> **Para saber mais**
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | Service](https://kubernetes.io/docs/concepts/services-networking/service/)
> - [Kubernetes Documentation | EndpointSlices](https://kubernetes.io/docs/concepts/services-networking/endpoint-slices/)
> - [Kubernetes Documentation | DNS for Services and Pods](https://kubernetes.io/docs/concepts/services-networking/dns-pod-service/)
> - [Kubernetes Documentation | kubectl](https://kubernetes.io/docs/reference/kubectl/)
