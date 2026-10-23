# Introdução ao Kubernetes com kind e kubectl

## Objetivo do exemplo

Este exemplo cria um cluster Kubernetes local com o **kind**, explora comandos básicos do **kubectl** e executa um Pod com a aplicação `flavioh/k8s-in-action:latest`.

O exemplo permite praticar a criação e a remoção de um cluster, consultar os recursos disponíveis, inspecionar a configuração do Kubernetes, aplicar um manifesto YAML, acompanhar logs, abrir um shell no container e acessar a aplicação por meio de encaminhamento de porta.

## Ferramentas

### kind

Site: https://kind.sigs.k8s.io/

O **kind** (*Kubernetes IN Docker*) cria clusters Kubernetes locais usando containers como nós do cluster. Neste exemplo, o cluster será composto por um nó `control-plane` e dois nós `worker`.

Instale pelo WinGet:

```bash
winget install Kubernetes.kind
```

### kubectl

Documentação: https://kubernetes.io/docs/reference/kubectl/

O **kubectl** é a ferramenta de linha de comando usada para consultar e administrar recursos de um cluster Kubernetes. Ele envia solicitações à API do Kubernetes usando o contexto e as credenciais definidos no arquivo de configuração do cliente, normalmente `~/.kube/config`.

A instalação do **Docker Desktop** já traz o kubectl embutido, verifique no seu terminal de preferência:

```bash
kubectl version --client
```

Caso não esteja instalado, a instalação pode ser feita pelo WinGet, se preferir:

```bash
winget install -e --id Kubernetes.kubectl
```

> Para instalação em outros sistemas operacionais, consulte a documentação: https://kubernetes.io/docs/tasks/tools/#kubectl


### Runtime de containers

O kind precisa de um runtime de containers compatível para executar os nós do cluster. No Windows, uma opção comum é o Docker Desktop. Inicie o runtime antes de criar o cluster.

## Preparação do ambiente

1. Instale o kind e o kubectl.
2. Inicie o Docker Desktop ou outro runtime compatível com o kind.
3. Abra o Git Bash ou outro terminal Bash para executar os comandos deste documento.
4. Confirme que as ferramentas estão disponíveis:

   ```bash
   kind version
   kubectl version --client
   ```


> [!NOTE]
> Os comandos `cat` e os caminhos `~/.kube/config` usados abaixo seguem a sintaxe do Bash/Git Bash. No PowerShell, você pode consultar o arquivo com `Get-Content "$HOME\.kube\config"`.


## 1. Configurar o cluster kind

Crie um arquivo chamado `kind-config.yaml` com o seguinte conteúdo:

```yaml
kind: Cluster
apiVersion: kind.x-k8s.io/v1alpha4

nodes:
  - role: control-plane
  - role: worker
  - role: worker
```

## 2. Criar e administrar o cluster

Execute os comandos a seguir no diretório em que salvou `kind-config.yaml`.

### Criar o cluster

```bash
kind create cluster --name lab --config kind-config.yaml
```

- `kind create cluster` cria um cluster Kubernetes local.
- `--name lab` define `lab` como o nome do cluster.
- `--config kind-config.yaml` informa o arquivo de configuração a usar.

O kind configura o cluster e, normalmente, atualiza o kubeconfig do usuário para permitir que o `kubectl` acesse o novo cluster.

### Listar os clusters kind

```bash
kind get clusters
```

Lista os clusters kind existentes. O cluster criado acima deverá aparecer como `lab`.

### Excluir o cluster

```bash
kind delete cluster --name lab
```

Remove o cluster chamado `lab`, incluindo seus nós e os recursos armazenados nele. Esse comando é destrutivo: os workloads e os dados que existam apenas dentro do cluster serão perdidos.

> [!WARNING]
> Não execute `kind delete cluster --name lab` antes de terminar o exercício, pois será necessário recriar o cluster para continuar.

## 3. Explorar o cluster com kubectl

### Consultar informações e a configuração de acesso

Exibe os endereços dos serviços principais do cluster Kubernetes acessado pelo contexto atual:

```bash
kubectl cluster-info
```

Exibe a ajuda dos subcomandos disponíveis para administrar a configuração do cliente:

```bash
kubectl config
```

Mostra o conteúdo do kubeconfig no Bash. Esse arquivo contém contextos, clusters e informações de autenticação:

```bash
cat ~/.kube/config
```

Exibe a configuração do kubeconfig interpretada pelo kubectl:

```bash
kubectl config view
```

Lista os contextos configurados e indica o contexto atual:

```bash
kubectl config get-contexts
```

Informa qual contexto está selecionado atualmente:

```bash
kubectl config current-context
```

Seleciona o contexto `kind-lab`, que normalmente corresponde ao cluster `lab` criado pelo kind:

```bash
kubectl config use-context kind-lab
```


> [!NOTE]
> O nome do contexto normalmente é `kind-lab` porque o cluster foi criado com `--name lab`. Confira a saída de `kubectl config get-contexts` antes de executar `kubectl config use-context`, caso o contexto tenha outro nome.

### Listar nós, namespaces e recursos da API

Lista os nós do cluster e mostra seu estado. Neste exemplo, devem existir um `control-plane` e dois `workers`:

```bash
kubectl get nodes
```

Lista os namespaces, que organizam recursos dentro do cluster:

```bash
kubectl get namespaces
```

Lista os tipos de recursos que a API do Kubernetes disponibiliza ao cliente:

```bash
kubectl api-resources
```

Lista os Pods de todos os namespaces:

```bash
kubectl get pods --all-namespaces
```

Lista os Pods do namespace `kube-system`, onde ficam vários componentes essenciais do cluster:

```bash
kubectl get pods -n kube-system
```


## 4. Definir o Pod da aplicação

Crie um arquivo chamado `k8s-in-action.yaml` com o seguinte conteúdo:

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: k8s-in-action
  labels:
    app: k8s-in-action
spec:
  containers:
    - name: app
      image: flavioh/k8s-in-action:latest
      imagePullPolicy: IfNotPresent
      ports:
        - name: http
          containerPort: 3000
          protocol: TCP
      env:
        - name: NODE_ENV
          value: production
        - name: MENSAGEM_DESTAQUE
          value: "Olá Kubernetes"
```

## 5. Criar e inspecionar o Pod

### Aplicar o manifesto

```bash
kubectl apply -f k8s-in-action.yaml
```

O `kubectl apply -f` lê o manifesto e cria o recurso declarado, ou atualiza o recurso gerenciado pelo manifesto quando aplicável.

### Consultar o Pod

Lista os Pods no namespace atual, que por padrão é `default`:

```bash
kubectl get pods
```

Exibe o estado resumido do Pod especificado:

```bash
kubectl get pod k8s-in-action
```

Exibe colunas adicionais, como o nó em que o Pod está executando e seu endereço IP:

```bash
kubectl get pod k8s-in-action -o wide
```

Exibe a representação YAML do recurso armazenado no cluster, incluindo valores adicionados pelo Kubernetes:

```bash
kubectl get pod k8s-in-action -o yaml
```

O Pod pode levar algum tempo para passar para `Running`, especialmente se o nó precisar baixar a imagem. Para acompanhar os eventos e diagnosticar problemas, use `kubectl describe pod`.


### Inspecionar eventos e configuração


```bash
kubectl describe pod k8s-in-action
```

Apresenta detalhes do Pod, os containers, condições, volumes, eventos recentes e possíveis mensagens de erro. É um dos primeiros comandos úteis quando um Pod não inicia.

### Consultar os logs

```bash
kubectl logs k8s-in-action -f
```

Exibe os logs do container e continua acompanhando novas mensagens. Para sair da visualização contínua, pressione `Ctrl+C`; isso encerra o acompanhamento, mas não remove nem interrompe o Pod.


### Abrir um shell no container

```bash
kubectl exec -it k8s-in-action -- sh
```

Abre um shell interativo dentro do container, caso a imagem contenha `/bin/sh`.

- `exec` executa um processo dentro do container.
- `-i` mantém a entrada padrão aberta.
- `-t` aloca um terminal.
- `--` separa as opções do `kubectl` do comando a executar dentro do container.

Para sair do shell, execute `exit` ou pressione `Ctrl+D`.


## 6. Acessar a aplicação com port-forward

A declaração `containerPort: 3000` do manifesto não publica automaticamente a porta do Pod no host. Use o encaminhamento de portas:

```bash
kubectl port-forward k8s-in-action 9999:3000
```

O comando encaminha conexões da porta `9999` da máquina local para a porta `3000` do Pod.

- `9999` é a porta local em que o `kubectl` escuta.
- `3000` é a porta de destino no Pod.
- O comando fica em execução enquanto o encaminhamento estiver ativo. Não feche esse terminal durante o teste.

Com o comando ativo, abra `http://localhost:9999` no navegador. Para encerrar o encaminhamento, pressione `Ctrl+C`. A interrupção do `port-forward` não remove o Pod.


## 7. Remover o Pod

Há duas formas de remover o recurso criado no exercício.

### Remover pelo nome

```bash
kubectl delete pod k8s-in-action
```

Solicita a exclusão do Pod identificado pelo nome. Como o manifesto define um Pod diretamente, e não um Deployment que gerencie réplicas, o Kubernetes não o recriará automaticamente após a remoção.


### Remover pelo manifesto

```bash
kubectl delete -f k8s-in-action.yaml
```

Remove os recursos descritos no arquivo informado. Essa abordagem é útil quando se deseja excluir os recursos associados a um manifesto sem digitar individualmente cada nome.


## 8. Encerrar o ambiente

Para remover o Pod, caso ainda exista:

```bash
kubectl delete -f k8s-in-action.yaml
```

Para excluir o cluster local inteiro:

```bash
kind delete cluster --name lab
```

A exclusão do cluster remove os nós e os recursos Kubernetes mantidos nele. Ela não desinstala as ferramentas kind e kubectl do Windows.


## Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `kind create cluster --name lab --config kind-config.yaml` | Cria o cluster local a partir do arquivo de configuração. |
| `kind get clusters` | Lista os clusters kind existentes. |
| `kind delete cluster --name lab` | Exclui o cluster chamado `lab`. |
| `kubectl cluster-info` | Exibe informações de acesso ao cluster. |
| `kubectl config get-contexts` | Lista os contextos configurados no kubeconfig. |
| `kubectl config current-context` | Exibe o contexto atual. |
| `kubectl get nodes` | Lista os nós Kubernetes. |
| `kubectl get namespaces` | Lista os namespaces. |
| `kubectl api-resources` | Lista os tipos de recursos da API. |
| `kubectl get pods --all-namespaces` | Lista Pods em todos os namespaces. |
| `kubectl apply -f k8s-in-action.yaml` | Cria ou atualiza o Pod com base no manifesto. |
| `kubectl get pod k8s-in-action -o wide` | Exibe o estado do Pod e dados adicionais, incluindo seu nó. |
| `kubectl describe pod k8s-in-action` | Exibe detalhes, eventos e informações de diagnóstico do Pod. |
| `kubectl logs k8s-in-action -f` | Acompanha os logs do container. |
| `kubectl exec -it k8s-in-action -- /bin/sh` | Abre um shell interativo no container, se disponível. |
| `kubectl port-forward k8s-in-action 9999:3000` | Encaminha a porta local 9999 para a porta 3000 do Pod. |
| `kubectl delete pod k8s-in-action` | Solicita a remoção do Pod pelo nome. |
| `kubectl delete -f k8s-in-action.yaml` | Remove o recurso descrito no manifesto. |

---

## Para saber mais

- [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
- [Kubernetes Documentation | kubectl](https://kubernetes.io/docs/reference/kubectl/)
- [Kubernetes Documentation | Pods](https://kubernetes.io/docs/concepts/workloads/pods/)
- [Kubernetes Documentation | Configure a Pod to Use a ConfigMap for Environment Variables](https://kubernetes.io/docs/tasks/configure-pod-container/configure-pod-configmap/)
- [Kubernetes Documentation | Port Forwarding Access to Applications in a Cluster](https://kubernetes.io/docs/tasks/access-application-cluster/port-forward-access-application-cluster/)
