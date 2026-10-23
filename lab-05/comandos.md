# Introdução ao Kubernetes — Guia de comandos

## Comandos do kubectl

### Sintaxe geral

O `kubectl` é a ferramenta de linha de comando utilizada para se comunicar com um cluster Kubernetes por meio da API do Kubernetes.

- **Sintaxe:**

  ```bash
  kubectl [COMANDO] [TIPO] [NOME] [OPÇÕES]
  ```

- Os elementos da sintaxe representam:

  | Elemento | Finalidade |
  | --- | --- |
  | `COMANDO` | Operação realizada sobre um ou mais recursos. |
  | `TIPO` | Tipo do recurso Kubernetes manipulado. |
  | `NOME` | Nome do recurso específico. |
  | `OPÇÕES` | Parâmetros adicionais que modificam o comportamento do comando. |

- **Exemplo:**

  ```bash
  kubectl get pod nginx
  ```

  Nesse exemplo, `get` é a operação, `pod` é o tipo do recurso e `nginx` é o nome do recurso.

### Especificar o tipo e o nome do recurso

O tipo do recurso não diferencia maiúsculas de minúsculas e pode ser informado no singular, no plural ou por uma abreviação (*short name*), quando disponível.

```bash
# Formas equivalentes de consultar o Pod nginx
kubectl get pod nginx
kubectl get pods nginx
kubectl get po nginx
```

Quando o nome é omitido, o comando normalmente opera sobre todos os recursos daquele tipo no escopo selecionado:

```bash
# Lista os Pods do namespace atual
kubectl get pods
```

É possível informar vários recursos do mesmo tipo:

```bash
kubectl get pods pod1 pod2 pod3
```

Também é possível combinar tipos diferentes de recursos:

```bash
kubectl get pod/pod1 service/service1
```

### Utilizar manifestos

A opção `-f` permite informar um arquivo YAML ou JSON que descreve recursos Kubernetes. Ela pode ser utilizada por diferentes comandos, incluindo `get`, `apply` e `delete`.

```bash
# Consulta o recurso descrito em um manifesto
kubectl get -f pod.yaml
```

Para fornecer vários arquivos:

```bash
kubectl get -f pod.yaml -f service.yaml
```

Para aplicar a configuração declarada em um arquivo:

```bash
kubectl apply -f pod.yaml
```

O `apply` cria os recursos que ainda não existem e atualiza os recursos gerenciados a partir do manifesto.

## Operações mais utilizadas

| Comando | Finalidade |
| --- | --- |
| `get` | Consulta e lista recursos. |
| `describe` | Exibe informações detalhadas sobre um recurso. |
| `apply` | Cria ou atualiza recursos a partir de arquivos de configuração. |
| `create` | Cria recursos por meio de comandos imperativos ou arquivos. |
| `delete` | Remove recursos. |
| `edit` | Abre a configuração de um recurso para edição direta. |
| `patch` | Atualiza parcialmente um recurso. |
| `scale` | Altera a quantidade de réplicas de uma carga de trabalho. |
| `set` | Altera propriedades específicas, como imagem, recursos e variáveis de ambiente. |
| `rollout` | Gerencia e acompanha atualizações de recursos como Deployments, DaemonSets e StatefulSets. |
| `logs` | Exibe logs dos containers de um Pod. |
| `exec` | Executa um comando dentro de um container de um Pod. |
| `port-forward` | Encaminha uma porta local para um Pod, Service ou outro recurso compatível. |
| `cp` | Copia arquivos entre a máquina local e containers. |
| `events` | Exibe eventos relacionados ao cluster e aos recursos. |
| `api-resources` | Lista os tipos de recursos disponíveis na API do cluster. |
| `api-versions` | Lista as versões de API disponíveis no cluster. |

## Consultar informações do cluster

### Informações básicas

```bash
# Exibe informações básicas sobre o cluster acessado
kubectl cluster-info
```

### Consultar os nós

```bash
# Lista os nós do cluster
kubectl get nodes
```

O recurso `Node` representa uma máquina que participa do cluster. Em um cluster kind com um `control-plane` e dois `workers`, esse comando permite consultar os três nós.

### Consultar namespaces

```bash
# Lista os namespaces do cluster
kubectl get namespaces
```

Namespaces organizam logicamente os recursos Kubernetes. Muitos comandos atuam por padrão no namespace selecionado no contexto atual, normalmente `default`, quando não é indicado outro namespace.

### Consultar os tipos de recursos e as versões de API

```bash
# Lista os tipos de recursos disponíveis na API do cluster
kubectl api-resources
```

```bash
# Lista as versões de API disponibilizadas pelo cluster
kubectl api-versions
```

### Tipos de recursos mais utilizados

| Recurso | Short name | Escopo | Finalidade |
| --- | --- | --- | --- |
| `pods` | `po` | Namespace | Unidade básica de execução de cargas de trabalho. |
| `deployments` | `deploy` | Namespace | Gerencia Pods de aplicações sem estado e seus rollouts. |
| `replicasets` | `rs` | Namespace | Mantém a quantidade desejada de réplicas de Pods; normalmente é gerenciado por um Deployment. |
| `statefulsets` | `sts` | Namespace | Gerencia aplicações que precisam de identidade e armazenamento persistentes. |
| `daemonsets` | `ds` | Namespace | Garante a execução de um Pod em determinados nós do cluster. |
| `jobs` | — | Namespace | Executa cargas de trabalho que devem ser concluídas. |
| `cronjobs` | `cj` | Namespace | Cria Jobs de acordo com um agendamento. |
| `services` | `svc` | Namespace | Fornece um ponto de acesso estável para um conjunto de Pods. |
| `ingresses` | `ing` | Namespace | Define regras de entrada HTTP/HTTPS para aplicações, quando há um controlador correspondente. |
| `configmaps` | `cm` | Namespace | Armazena dados de configuração não sensíveis. |
| `secrets` | — | Namespace | Armazena informações sensíveis utilizadas pelas aplicações. |
| `namespaces` | `ns` | Cluster | Isola logicamente grupos de recursos. |
| `nodes` | `no` | Cluster | Representa uma máquina que participa do cluster. |
| `persistentvolumeclaims` | `pvc` | Namespace | Solicita armazenamento persistente. |
| `persistentvolumes` | `pv` | Cluster | Representa recursos de armazenamento persistente disponibilizados ao cluster. |
| `storageclasses` | `sc` | Cluster | Define classes de armazenamento e parâmetros de provisionamento. |
| `serviceaccounts` | `sa` | Namespace | Identidade utilizada por processos executados dentro do cluster. |
| `roles` | — | Namespace | Define permissões de acesso a recursos dentro de um namespace. |
| `rolebindings` | — | Namespace | Associa Roles a usuários, grupos ou ServiceAccounts. |
| `clusterroles` | — | Cluster | Define permissões utilizáveis em nível de cluster ou namespace. |
| `clusterrolebindings` | — | Cluster | Associa ClusterRoles a usuários, grupos ou ServiceAccounts. |
| `customresourcedefinitions` | `crd` | Cluster | Estende a API do Kubernetes com novos tipos de recursos. |

### Consultar Pods

```bash
# Lista os Pods do namespace atual
kubectl get pods
```

```bash
# Lista os Pods de todos os namespaces
kubectl get pods --all-namespaces
```

```bash
# Lista os Pods do namespace kube-system
kubectl get pods -n kube-system
```

A opção `-n` especifica o namespace a ser consultado. O namespace `kube-system` contém diversos Pods associados aos componentes do sistema Kubernetes.

## Inspecionar e diagnosticar recursos

### Exibir detalhes de um recurso

```bash
# Exibe informações detalhadas de um Pod
kubectl describe pod <NOME>
```

O comando `describe` apresenta detalhes do recurso e eventos relacionados, ajudando a investigar problemas de configuração, agendamento ou inicialização.

### Consultar logs

```bash
# Exibe os logs de um Pod
kubectl logs <POD>
```

Esse comando é utilizado para consultar a saída de log do container de um Pod. Se o Pod possuir mais de um container, pode ser necessário indicar qual deles deve ser consultado.

### Executar comandos em um container

```bash
# Abre um shell interativo dentro do container
kubectl exec -it <POD> -- /bin/sh
```

- `exec` executa um processo dentro de um container do Pod.
- `-i` mantém a entrada padrão aberta.
- `-t` aloca um terminal.
- `--` separa as opções do `kubectl` do comando executado no container.
- `/bin/sh` é o shell solicitado; ele precisa existir na imagem do container.

### Encaminhar portas

```bash
kubectl port-forward <POD> <PORTA_LOCAL>:<PORTA_CONTAINER>
```

O `port-forward` encaminha conexões de uma porta local para a porta de um Pod. A sessão permanece ativa enquanto o comando estiver em execução. Para interromper o encaminhamento, pressione `Ctrl+C`; isso não remove o Pod.

### Copiar arquivos

A operação `cp` permite copiar arquivos entre a máquina local e um container. A forma exata do comando depende do sentido da cópia e do Pod/container de destino.

```bash
kubectl cp <ORIGEM_LOCAL> <POD>:<CAMINHO_NO_CONTAINER>
```

## Contextos e arquivo kubeconfig

### Para que serve o kubeconfig

O arquivo `kubeconfig` contém as informações necessárias para que o `kubectl` selecione um cluster e se autentique para acessar sua API. `kubeconfig` é um termo genérico: o arquivo não precisa se chamar literalmente `kubeconfig`.

Por padrão, o `kubectl` procura o arquivo:

```text
$HOME/.kube/config
```

### Consultar a configuração

```bash
# Exibe a ajuda dos subcomandos de configuração
kubectl config
```

```bash
# Mostra o conteúdo do kubeconfig no Bash
cat ~/.kube/config
```

```bash
# Exibe a configuração interpretada pelo kubectl
kubectl config view
```

O arquivo pode conter endereços de servidores, certificados, tokens e outros dados de autenticação. Evite compartilhar seu conteúdo sem revisar e remover informações sensíveis.

### Listar e selecionar contextos

Um contexto associa um cluster a um usuário e, opcionalmente, a um namespace.

```bash
# Lista os contextos configurados
kubectl config get-contexts
```

```bash
# Mostra o contexto atualmente selecionado
kubectl config current-context
```

```bash
# Seleciona o contexto kind-lab
kubectl config use-context kind-lab
```

O contexto `kind-lab` é o nome normalmente criado pelo kind quando o cluster é criado com o nome `lab`. Confira a saída de `kubectl config get-contexts` antes de selecioná-lo, caso o nome seja diferente.

### Utilizar outro arquivo kubeconfig

É possível indicar um arquivo de configuração diretamente em um comando:

```bash
kubectl --kubeconfig <ARQUIVO> config get-contexts
```

Também é possível definir o arquivo por meio da variável de ambiente `KUBECONFIG`.

- **Linux e macOS:**

  ```bash
  export KUBECONFIG=.kube/producao
  ```

- **Windows PowerShell:**

  ```powershell
  $env:KUBECONFIG="$HOME\.kube\homologacao"
  ```

A variável de ambiente define o arquivo que será utilizado pelos comandos `kubectl` executados na sessão ou no ambiente em que ela estiver definida.

### Definir o namespace padrão do contexto atual

```bash
kubectl config set-context --current --namespace=<NAMESPACE>
```

Esse comando define o namespace padrão do contexto atual. Os comandos executados com esse contexto passarão a usar o namespace especificado quando outro namespace não for informado explicitamente com uma opção como `-n`.

## Estrutura de um arquivo kubeconfig

Um kubeconfig normalmente organiza as informações em três grupos:

| Grupo | Conteúdo |
| --- | --- |
| `clusters` | Endereço do servidor da API e informações associadas à conexão TLS e à autoridade certificadora. |
| `users` | Credenciais utilizadas para autenticação, como certificados, chaves ou tokens. |
| `contexts` | Associação entre cluster, usuário e, opcionalmente, namespace. |
| `current-context` | Contexto selecionado por padrão pelo `kubectl`. |

Exemplo ilustrativo:

```yaml
apiVersion: v1
kind: Config

clusters:
  - name: producao
    cluster:
      server: https://kubernetes.exemplo.com:6443
      certificate-authority-data: <CERTIFICADO-CA>

users:
  - name: administrador
    user:
      client-certificate-data: <CERTIFICADO>
      client-key-data: <CHAVE-PRIVADA>

contexts:
  - name: producao-admin
    context:
      cluster: producao
      user: administrador
      namespace: default

current-context: producao-admin
```

Nesse exemplo, `clusters` contém as informações para localizar o cluster; `users` contém os dados de autenticação; `contexts` associa cluster e usuário; e `current-context` define o contexto selecionado por padrão.

> [!WARNING]
> O YAML acima é ilustrativo. Os valores entre `<...>` são marcadores que precisam ser substituídos por dados apropriados; não são valores válidos para conectar-se a um cluster real.

## Comandos de referência rápida

| Comando | Finalidade |
| --- | --- |
| `kubectl cluster-info` | Exibe informações básicas do cluster. |
| `kubectl get nodes` | Lista os nós do cluster. |
| `kubectl get namespaces` | Lista os namespaces. |
| `kubectl api-resources` | Lista os tipos de recursos da API. |
| `kubectl api-versions` | Lista as versões de API disponíveis. |
| `kubectl get pods` | Lista os Pods do namespace atual. |
| `kubectl get pods --all-namespaces` | Lista os Pods de todos os namespaces. |
| `kubectl get pods -n kube-system` | Lista os Pods de `kube-system`. |
| `kubectl describe pod <NOME>` | Exibe detalhes e eventos de um Pod. |
| `kubectl logs <POD>` | Exibe os logs de um Pod. |
| `kubectl exec -it <POD> -- /bin/sh` | Abre um shell no container, se disponível. |
| `kubectl port-forward <POD> <PORTA_LOCAL>:<PORTA_CONTAINER>` | Encaminha uma porta local para o Pod. |
| `kubectl cp <ORIGEM_LOCAL> <POD>:<CAMINHO_NO_CONTAINER>` | Copia um arquivo local para um container. |
| `kubectl config view` | Exibe a configuração do cliente. |
| `kubectl config get-contexts` | Lista os contextos. |
| `kubectl config current-context` | Mostra o contexto atual. |
| `kubectl config use-context <CONTEXTO>` | Seleciona um contexto. |
| `kubectl config set-context --current --namespace=<NAMESPACE>` | Define o namespace padrão do contexto atual. |
| `kubectl apply -f <ARQUIVO>` | Cria ou atualiza recursos definidos em um manifesto. |
| `kubectl delete -f <ARQUIVO>` | Remove recursos definidos em um manifesto. |

## Referências

### Orquestração de containers

> [!NOTE]
> **Para saber mais**  
> [What is container orchestration?](https://www.redhat.com/en/topics/containers/what-is-container-orchestration/)  
> [O que é orquestração de contêiner?](https://www.ibm.com/br-pt/think/topics/container-orchestration/)

### Introdução, arquitetura e componentes do Kubernetes

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Overview](https://kubernetes.io/docs/concepts/overview/)  
> [Kubernetes Documentation | Cluster Architecture](https://kubernetes.io/docs/concepts/architecture/)  
> [Kubernetes Documentation | Components](https://kubernetes.io/docs/concepts/overview/components/)

### Extensões e plugins do Kubernetes

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Extending Kubernetes](https://kubernetes.io/docs/concepts/extend-kubernetes/)  
> [Kubernetes Documentation | Device Plugins](https://kubernetes.io/docs/concepts/extend-kubernetes/compute-storage-net/device-plugins/)  
> [Kubernetes Documentation | Network Plugins](https://kubernetes.io/docs/concepts/extend-kubernetes/compute-storage-net/network-plugins/)  
> [Kubernetes Documentation | CSI](https://kubernetes.io/docs/concepts/storage/volumes/#csi)

### Instalação e plataformas de gerenciamento

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Bootstrapping clusters with kubeadm](https://kubernetes.io/docs/setup/production-environment/tools/kubeadm/)  
> [SUSE Rancher Prime (Rancher Manager)](https://documentation.suse.com/pt-br/cloudnative/rancher-manager/)  
> [Red Hat OpenShift Container Platform — Architecture](https://docs.redhat.com/en/documentation/openshift_container_platform/4.22/html/architecture/)  
> [Broadcom — VMware vSphere Kubernetes Service (VKS)](https://developer.broadcom.com/xapis/vmware-vsphere-kubernetes-service/latest/)

### kubectl

> [!NOTE]
> **Para saber mais**  
> [Kubernetes Documentation | Command line tool (kubectl)](https://kubernetes.io/docs/reference/kubectl/)  
> [Kubernetes Documentation | Download kubectl](https://kubernetes.io/releases/download/#kubectl)
