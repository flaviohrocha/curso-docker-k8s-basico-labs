# Exemplo — Atualização e rollback de Deployments no Kubernetes

## Objetivo do exemplo

Este exercício utiliza um cluster Kubernetes local criado com **kind** e a ferramenta **kubectl** para demonstrar como atualizar a imagem de uma aplicação gerenciada por um `Deployment`, acompanhar a atualização gradual (*rolling update*) e reverter a aplicação para uma revisão anterior (*rollback*).

O roteiro começa criando o namespace `exemplo-07` e implantando o `Deployment` definido em `deployment.yaml`. Em seguida, a imagem da aplicação será alterada para `flavioh/k8s-in-action:v1` e depois para `flavioh/k8s-in-action:v2`. Por fim, serão consultados o histórico de revisões e os mecanismos de reversão.


## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com o kind.
2. Abra um terminal Bash, como o Git Bash, ou outro terminal em que o `kubectl` esteja configurado para acessar o cluster.
3. Entre no diretório que contém o arquivo `deployment.yaml`.
4. Confirme que o cluster está disponível e que o contexto atual aponta para o cluster correto.

```bash
kubectl cluster-info
```

Exibe informações sobre o cluster acessado pelo contexto atual. Se houver erro de conexão, confira o contexto selecionado antes de continuar.

```bash
kubectl config current-context
```

Mostra o contexto selecionado pelo `kubectl`. Para o cluster criado com `--name lab`, o contexto normalmente é `kind-lab`.



## 1. Namespace

O namespace `exemplo-07` separa os recursos deste exercício dos recursos de outros exercícios ou aplicações no cluster.

### Criar o namespace

```bash
kubectl create namespace exemplo-07
```

Cria o namespace chamado `exemplo-07`. Se ele já existir, o Kubernetes informará que o recurso já existe; nesse caso, não é necessário criá-lo novamente.

### Verificar o namespace

```bash
kubectl get namespaces
```

Lista os namespaces do cluster. Confirme que `exemplo-07` aparece na saída.

## 2. Criar o Deployment inicial

O arquivo `deployment.yaml` define o Deployment `k8s-in-action-app`. A imagem usada inicialmente será aquela que estiver configurada no campo `spec.template.spec.containers[].image` desse manifesto.

### Criar o Deployment

```bash
kubectl apply -f deployment.yaml
```

Lê o arquivo `deployment.yaml` e cria ou atualiza o recurso nele declarado. Esse comando funciona para o namespace `exemplo-07` declarado no manifesto.

### Acompanhar a conclusão do rollout inicial

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Acompanha o estado da implantação do Deployment `k8s-in-action-app` no namespace `exemplo-07`. Por padrão, o comando aguarda até que o rollout seja concluído. Se os Pods não ficarem prontos, examine o estado dos Pods, os eventos e os logs para investigar a causa.

### Consultar o Deployment

```bash
kubectl -n exemplo-07 get deployment k8s-in-action-app -o wide
```

Exibe o Deployment com colunas adicionais, incluindo a imagem configurada e os contadores de réplicas desejadas, atualizadas e disponíveis. Guarde essa saída como referência para comparar as mudanças de versão.

## 3. Atualizar a imagem para a versão v1

Nesta etapa, você altera o campo `image` no arquivo `deployment.yaml` para:

```yaml
image: flavioh/k8s-in-action:v1
```

A alteração deve ser feita no container declarado em `spec.template.spec.containers`. Mantenha os outros campos do manifesto.

### Aplicar a nova versão

Depois de salvar o arquivo, execute:

```bash
kubectl apply -f deployment.yaml
```

O `kubectl apply` registra a mudança declarada no manifesto. Como a imagem faz parte do template do Pod, sua alteração normalmente cria uma nova revisão do Deployment e inicia um rollout.

### Acompanhar o rollout da v1

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Aguarda a conclusão da atualização. O resultado confirma se o Deployment convergiu para o novo estado desejado, mas não substitui a verificação do funcionamento da aplicação.

### Observar os Pods durante a atualização

Abra um segundo terminal Bash e execute:

```bash
while true; do clear; kubectl get pods -n exemplo-07 -l "app=k8s-in-action-app"; sleep 2; done
```

O laço limpa a tela, lista os Pods que possuem o rótulo `app=k8s-in-action-app`, aguarda dois segundos e repete a consulta. Isso permite observar a criação de Pods novos e o encerramento de Pods antigos durante o rollout.

- `clear` limpa a tela do terminal.
- `kubectl get pods` lista os Pods.
- `-n exemplo-07` limita a consulta ao namespace do exercício.
- `-l "app=k8s-in-action-app"` filtra os Pods pelo rótulo.
- `sleep 2` aguarda dois segundos antes da próxima consulta.
- `Ctrl+C` encerra o laço de observação sem interromper o Deployment nem os Pods.

> [!NOTE]
> Esse comando usa sintaxe do Bash/Git Bash. Se estiver usando PowerShell, será necessário adaptar o laço ao shell utilizado.

## 4. Atualizar a imagem para a versão v2

Edite novamente o arquivo `deployment.yaml`, alterando o campo `image` para:

```yaml
image: flavioh/k8s-in-action:v2
```

Essa mudança representa uma segunda atualização do template do Pod. Será utilizada uma tag que não existe para simular uma falha na atualização da aplicação.

### Aplicar a nova versão

```bash
kubectl apply -f deployment.yaml
```

O Kubernetes inicia outro rollout para levar o Deployment à versão `v2`.

### Acompanhar a atualização

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Aguarda a conclusão do rollout da versão mais recente. O comando acompanha a atualização até que seja concluída ou até que seja reportado um problema.

### Consultar o histórico de revisões

```bash
kubectl -n exemplo-07 rollout history deployment k8s-in-action-app
```

Lista as revisões disponíveis do Deployment. A alteração da imagem modifica o template do Pod e, normalmente, cria uma nova revisão. O número atribuído a cada revisão depende do histórico existente e de outras alterações feitas no Deployment.

Para consultar detalhes de uma revisão específica, você também pode usar:

```bash
kubectl -n exemplo-07 rollout history deployment k8s-in-action-app --revision=2
```

Nesse exemplo, `--revision=2` solicita os detalhes da revisão 2. Substitua o número pelo que estiver disponível no histórico real.

### Conferir a versão configurada

```bash
kubectl -n exemplo-07 get deployment k8s-in-action-app -o wide
```

Mostra a imagem configurada e o estado das réplicas. Verifique se a imagem é `flavioh/k8s-in-action:v2` e se o rollout terminou.

Também execute novamente:

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Essa consulta permite confirmar que a atualização não foi concluída devido à falha para realizar o pull da referida imagem, conforme a listagem dos pods no outro terminal.


## 5. Reverter para a revisão anterior

Já que a nova versão apresentou problemas, o `kubectl rollout undo` permite retornar o template do Deployment a uma revisão anterior. A reversão altera o template do Deployment; o controlador executa então outro rollout para aplicar o estado revertido.

### Reverter para a revisão anterior

```bash
kubectl -n exemplo-07 rollout undo deployment k8s-in-action-app
```

Reverte o Deployment para a revisão anterior disponível. O Kubernetes inicia uma nova atualização para substituir gradualmente os Pods da versão atual pelos Pods correspondentes ao template restaurado.

Depois, verifique o resultado:

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Aguarda a conclusão do rollout de rollback.

### Consultar o histórico após a reversão

```bash
kubectl -n exemplo-07 rollout history deployment k8s-in-action-app
```

Mostra o histórico disponível após a reversão. Uma reversão também altera o template ativo e pode gerar uma nova revisão; por isso, o histórico e sua numeração podem diferir do que havia antes do rollback.

## 6. Reverter para uma revisão específica

Além de voltar à revisão anterior, é possível selecionar explicitamente uma revisão do histórico.

```bash
kubectl -n exemplo-07 rollout undo deployment k8s-in-action-app --to-revision=1
```

Solicita que o Deployment volte ao template registrado na revisão 1. A revisão 1 representa o template da primeira revisão registrada desse Deployment, não necessariamente uma tag específica em qualquer situação. Para saber qual imagem corresponde a uma revisão, consulte o histórico e, se necessário, os detalhes com `--revision` antes de executar o rollback.

### Verificar a reversão

```bash
kubectl -n exemplo-07 rollout status deployment k8s-in-action-app
```

Aguarda que o Deployment conclua a atualização resultante do rollback.

### Acompanhar o estado do Deployment em tempo real

Em outro terminal Bash, execute:

```bash
while true; do clear; kubectl get deployment k8s-in-action-app -n exemplo-07 -o wide; sleep 2; done
```

O laço consulta o estado do Deployment a cada dois segundos. A saída `-o wide` inclui a imagem configurada e permite observar as mudanças de versão e os contadores de réplicas durante o rollout. Pressione `Ctrl+C` para encerrar a observação.


## Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `kubectl create namespace exemplo-07` | Cria o namespace do exercício. |
| `kubectl apply -n exemplo-07 -f deployment.yaml` | Cria ou atualiza o Deployment no namespace `exemplo-07`. |
| `kubectl -n exemplo-07 rollout status deployment k8s-in-action-app` | Acompanha a conclusão do rollout. |
| `kubectl -n exemplo-07 get deployment k8s-in-action-app -o wide` | Mostra a imagem configurada e os contadores de réplicas. |
| `kubectl -n exemplo-07 rollout history deployment k8s-in-action-app` | Lista as revisões do Deployment. |
| `kubectl -n exemplo-07 rollout history deployment k8s-in-action-app --revision=2` | Inspeciona os detalhes da revisão indicada. |
| `kubectl -n exemplo-07 rollout undo deployment k8s-in-action-app` | Reverte para a revisão anterior disponível. |
| `kubectl -n exemplo-07 rollout undo deployment k8s-in-action-app --to-revision=1` | Reverte para a revisão 1. |
| `kubectl get pods -n exemplo-07 -l "app=k8s-in-action-app"` | Lista os Pods da aplicação pelo rótulo. |
| `kubectl get pods -n exemplo-07 -l "app=k8s-in-action-app" --watch` | Acompanha alterações na listagem dos Pods; encerre com `Ctrl+C`. |

## Encerrar e limpar o laboratório

Para remover o Deployment:

```bash
kubectl delete -f deployment.yaml
```

Esse comando remove o Deployment. Os Pods controlados por ele serão encerrados pelo Kubernetes.

Para remover o namespace e os recursos que ainda existirem nele:

```bash
kubectl delete namespace exemplo-07
```

> [!WARNING]
> A exclusão do namespace remove os recursos nele contidos. Use esse comando somente quando tiver certeza de que não precisa manter o Deployment ou outros recursos do exercício.

## Referências

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | Update a Deployment Without Downtime](https://kubernetes.io/docs/tasks/run-application/update-deployment-rolling/)
> - [Kubernetes Documentation | kubectl rollout](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_rollout/)
> - [Kubernetes Documentation | kubectl rollout status](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_rollout/kubectl_rollout_status/)
> - [Kubernetes Documentation | kubectl rollout history](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_rollout/kubectl_rollout_history/)
> - [Kubernetes Documentation | kubectl](https://kubernetes.io/docs/reference/kubectl/)
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
