# Exemplo — Verificando a saúde de uma aplicação Node.js no Kubernetes

## Objetivo do exemplo

Este laboratório implanta a API `health-api` em um cluster Kubernetes local criado com **kind**, junto com PostgreSQL e Redis. A aplicação é executada em um `Deployment` com duas réplicas; os bancos são executados em `StatefulSets` e utilizam volumes persistentes. O acesso HTTP é definido por um `Service` e um `Ingress`.

O exercício demonstra como observar a saúde dos Pods e o comportamento das probes durante falhas controladas nas dependências:

- validar os manifestos antes de aplicá-los;
- implantar Namespace, ConfigMap, Secret, PersistentVolumes, PersistentVolumeClaims, Services, StatefulSets, Deployment e Ingress;
- acompanhar eventos Kubernetes em tempo real com `kubectl events`;
- interromper e restaurar Redis e PostgreSQL para observar a `readinessProbe` da aplicação;
- alterar temporariamente o endpoint da `livenessProbe`, observar a reinicialização do container e restaurar a configuração.

A API fornece os endpoints `GET /health/live`, `GET /health/ready` e `GET /health`. A configuração da `livenessProbe` usa `/health/live`, enquanto a `readinessProbe` usa `/health/ready`.

> [!IMPORTANT]
> O exemplo utiliza os manifestos fornecidos e o Ingress NGINX (`kubernetes/ingress-nginx`) para o laboratório local. O projeto ingress-nginx foi aposentado em março de 2026 e o repositório está arquivado. Use esse controlador apenas para reproduzir o exercício; para novos ambientes, avalie uma implementação mantida de Ingress ou a Gateway API. Consulte [Referências](#referências).

## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com `kind`.
2. Confirme que `kind` e `kubectl` estão instalados e disponíveis no `PATH`.
3. Execute os comandos em um terminal Bash, como Git Bash ou Linux/WSL. Comandos com variáveis de shell e quebras de linha podem precisar de adaptação no PowerShell.
4. Acesse a pasta localizada em `lab-08\exemplo-11` para execução do laboratório.
5. O Deployment usa a imagem `flavioh/nodejs-kubernetes-health:1.0.0`, com `imagePullPolicy: IfNotPresent`. Confirme que a imagem está publicada em um registry acessível pelo cluster. Se for usar uma imagem construída localmente, carregue-a nos nós do `kind` e ajuste o campo `image` do Deployment conforme necessário.
6. Verifique se as portas 80 e 443 do host estão disponíveis.

### Armazenamento e nomes dos nós

Os PersistentVolumes usam `hostPath` e afinidade de nó:

| Volume | Capacidade | Nó esperado | Diretório no nó |
| --- | ---: | --- | --- |
| `nodejs-health-postgres-pv` | `2Gi` | `lab-worker` | `/var/lib/kubernetes/nodejs-health/postgres` |
| `nodejs-health-redis-pv` | `1Gi` | `lab-worker2` | `/var/lib/kubernetes/nodejs-health/redis` |

A configuração `kind-config.yaml` cria um nó `control-plane` e dois `worker`. Com o nome de cluster `lab`, os nomes esperados são `lab-control-plane`, `lab-worker` e `lab-worker2`. Se você usar outro cluster ou outros nomes de nós, ajuste a `nodeAffinity` nos dois arquivos de PersistentVolume antes de aplicar os manifestos.

> [!WARNING]
> Os caminhos `hostPath` ficam no sistema de arquivos dos nós do `kind` (containers), não diretamente no sistema de arquivos do host. Esse armazenamento é adequado para o laboratório, não para produção. A exclusão do cluster remove os nós e pode eliminar os dados armazenados neles.

## 1. Criar e verificar o cluster kind

Se você ainda não tiver um cluster compatível para o laboratório, execute a partir do diretório que contém `kind-config.yaml`:

```bash
kind create cluster --name lab --config kind-config.yaml
```

O arquivo de configuração define três nós, encaminha as portas 80 e 443 do host para o `control-plane` e define nesse nó o rótulo `ingress-ready=true`.

Verifique o contexto e os nós:

```bash
kind get clusters
kubectl config current-context
kubectl cluster-info
kubectl get nodes -o wide
```

O contexto esperado é `kind-lab`. Confirme que os três nós estão `Ready` e que os nomes dos workers correspondem aos usados nos PersistentVolumes. Se já existir um cluster `lab`, não o exclua sem confirmar que pode perder os recursos e os dados existentes.

## 2. Instalar e verificar o Ingress Controller

O recurso `Ingress` define regras de roteamento, mas precisa de um Ingress Controller para atendê-las. O manifesto `13-ingress.yaml` usa a classe `nginx` e o host `health.localhost`.

Instale o controlador usado neste laboratório:

```bash
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

Confirme ou aplique o rótulo esperado no nó que recebe o encaminhamento de portas:

```bash
kubectl label node lab-control-plane ingress-ready=true --overwrite
kubectl get node lab-control-plane --show-labels
```

Direcione o Deployment do controlador para esse nó:

```bash
kubectl patch deployment ingress-nginx-controller \
  --namespace ingress-nginx \
  --type=merge \
  -p '{"spec":{"template":{"spec":{"nodeSelector":{"ingress-ready":"true"}}}}}'
```

Aguarde o controlador e confira a classe de Ingress:

```bash
kubectl rollout status deployment/ingress-nginx-controller -n ingress-nginx
kubectl get pods -n ingress-nginx -o wide
kubectl get ingressclass
kubectl describe ingressclass nginx
```

## 3. Validar e aplicar os manifestos

Execute os comandos a partir do diretório raiz do projeto, onde está a pasta `nodejs-health-api/`. Primeiro, faça a validação no lado do cliente:

```bash
kubectl apply --dry-run=client -f nodejs-health-api/
```

Se não houver erros, aplique os manifestos:

```bash
kubectl apply -f nodejs-health-api/
```

O comando aplica os arquivos YAML da pasta. Não coloque `kind-config.yaml` dentro dessa pasta: ele é lido pelo `kind create cluster`, não pelo `kubectl apply`.

### Verificar os recursos

```bash
kubectl get all -n nodejs-health
```
```bash
kubectl get configmap,secret,pvc,ingress -n nodejs-health
```
```bash
kubectl get pv
```
```bash
kubectl get pods -n nodejs-health -o wide
```

`kubectl get all` não exibe todos os tipos de recursos; consulte ConfigMaps, Secrets, PVCs, Ingress e PVs separadamente.

Acompanhe a inicialização dos workloads:

```bash
kubectl rollout status statefulset/postgres -n nodejs-health
```
```bash
kubectl rollout status statefulset/redis -n nodejs-health
```
```bash
kubectl rollout status deployment/health-api -n nodejs-health
```

Se um Pod não ficar pronto, consulte o `describe`, os eventos e os logs antes de continuar:

```bash
kubectl describe pods -n nodejs-health
```
```bash
kubectl events -n nodejs-health
```
```bash
kubectl logs statefulset/postgres -n nodejs-health
```
```bash
kubectl logs statefulset/redis -n nodejs-health
```
```bash
kubectl logs deployment/health-api -n nodejs-health
```

Verifique também se os PVCs estão `Bound` e se cada PV pode ser associado ao nó correto:

```bash
kubectl get pvc -n nodejs-health
```
```bash
kubectl get pv -o wide
```

### Testar os endpoints da API

Com o Ingress Controller pronto e o host `health.localhost` apontando para o loopback do host, verifique os endereços abaixo:

http://health.localhost/health/live  
http://health.localhost/health/ready  
http://health.localhost/health

Ou execute:

```bash
curl -i http://health.localhost/health/live
curl -i http://health.localhost/health/ready
curl -i http://health.localhost/health
```

Se `health.localhost` não resolver para o computador local, teste usando explicitamente o cabeçalho `Host`:

```bash
curl -i -H 'Host: health.localhost' http://127.0.0.1/health/live
```

O endpoint de liveness deve retornar HTTP `200` quando o processo está funcionando. O endpoint de readiness retorna HTTP `200` quando as verificações críticas passam e HTTP `503` quando uma dependência crítica, como PostgreSQL ou Redis, falha.

## 4. Observar eventos Kubernetes em tempo real

Abra um **segundo terminal** no mesmo contexto Kubernetes e execute:

```bash
kubectl events -n nodejs-health --watch
```

Deixe esse comando em execução durante os testes. Ele mostrará os eventos emitidos no namespace enquanto você escala os StatefulSets e altera a configuração da probe.

Para consultar os eventos de um Pod específico em outro momento, obtenha primeiro o nome atual do Pod da API:

```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

Em Bash, você pode selecionar automaticamente uma das réplicas e consultar os eventos associados a ela:

```bash
POD="$(kubectl get pods -n nodejs-health -l app.kubernetes.io/name=health-api -o jsonpath='{.items[0].metadata.name}')"
kubectl events -n nodejs-health --for "pod/${POD}"
```

O nome do Pod muda quando um Pod é recriado; por isso, não reutilize um nome de exemplo sem confirmá-lo com `kubectl get pods`.

## 5. Simular falhas nas dependências

Mantenha o terminal de eventos aberto. Execute os comandos abaixo no **primeiro terminal**. Depois de cada alteração, dê tempo para as probes executarem as verificações; a readiness não muda necessariamente de forma instantânea.

### 5.1 Observar as réplicas da API

```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

O Deployment deve manter duas réplicas da API. A coluna `READY` indica quantos containers estão prontos em cada Pod.

### 5.2 Interromper e restaurar o Redis

Reduza para zero as réplicas do Redis:

```bash
kubectl -n nodejs-health scale sts/redis --replicas=0
```

Observe os Pods da API enquanto a verificação crítica do Redis falha:

```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

Aguarde as tentativas da `readinessProbe`. As réplicas da API podem continuar em execução, mas deixarão de estar prontas enquanto a aplicação não conseguir se conectar ao Redis.

Restaure o Redis:

```bash
kubectl -n nodejs-health scale sts/redis --replicas=1
```
```bash
kubectl rollout status statefulset/redis -n nodejs-health
```
```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

Quando o Redis voltar a responder, a readiness da API deverá se recuperar após as próximas verificações.

### 5.3 Interromper e restaurar o PostgreSQL

Reduza para zero as réplicas do PostgreSQL:

```bash
kubectl -n nodejs-health scale sts/postgres --replicas=0
```

Verifique novamente o estado de prontidão da API:

```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

Consulte os eventos de uma réplica atual da API. O comando abaixo busca o nome do Pod em vez de presumir um sufixo fixo:

```bash
POD="$(kubectl get pods -n nodejs-health -l app.kubernetes.io/name=health-api -o jsonpath='{.items[0].metadata.name}')"
kubectl events -n nodejs-health --for "pod/${POD}"
```

Restaure o PostgreSQL e espere o StatefulSet ficar pronto:

```bash
kubectl -n nodejs-health scale sts/postgres --replicas=1
```
```bash
kubectl rollout status statefulset/postgres -n nodejs-health
```
```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```

Quando PostgreSQL estiver disponível novamente, a readiness da API deverá se recuperar após as próximas verificações.

> [!NOTE]
> Os `StatefulSets` são escalados a zero apenas para simular falhas controladas em laboratório. Não execute esses comandos em ambientes compartilhados ou de produção. A readiness da aplicação determina se os Pods podem receber tráfego; a falha de readiness, por si só, não reinicia o container.

## 6. Simular falha da livenessProbe

O manifesto `11-app-deployment.yaml` define a `livenessProbe` para `GET /health/live`. Nesta atividade, substitua temporariamente esse caminho por um endpoint inexistente e observe os eventos e a contagem de reinicializações.

> [!WARNING]
> Faça este teste somente no laboratório. Um caminho incorreto faz a probe falhar e pode reiniciar os containers da API. Restaure `/health/live` assim que concluir a observação.

### 6.1 Configurar temporariamente o endpoint incorreto

O roteiro original contém um erro de sintaxe no JSON Patch. Use a versão corrigida abaixo:

```bash
kubectl patch deployment health-api -n nodejs-health --type=json \
  -p='[{"op":"replace","path":"/spec/template/spec/containers/0/livenessProbe/httpGet/path","value":"/health/inexistente"}]'
```

Acompanhe o rollout e os Pods:

```bash
kubectl rollout status deployment/health-api -n nodejs-health
```
```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api" --watch
```

Mantenha também o terminal de `kubectl events --watch` aberto. O endpoint inexistente deve produzir respostas HTTP não bem-sucedidas; após atingir o limite de falhas consecutivas configurado na livenessProbe, o kubelet reinicia o container. Pode ser necessário aguardar algumas verificações para observar a alteração em `RESTARTS` e nos eventos.

### 6.2 Restaurar a livenessProbe

Restaure o caminho correto imediatamente após a demonstração:

```bash
kubectl patch deployment health-api -n nodejs-health --type=json \
  -p='[{"op":"replace","path":"/spec/template/spec/containers/0/livenessProbe/httpGet/path","value":"/health/live"}]'
```

Confirme que o rollout foi concluído e que as réplicas voltaram ao estado esperado:

```bash
kubectl rollout status deployment/health-api -n nodejs-health
```
```bash
kubectl get pods -n nodejs-health -l "app.kubernetes.io/name=health-api"
```
```bash
kubectl get events -n nodejs-health --sort-by=.lastTimestamp
```

## 7. Diagnóstico rápido

| Sintoma | O que verificar |
| --- | --- |
| PVC permanece `Pending` | Confira se os PVs existem, a capacidade, `storageClassName`, `volumeName` e os nomes dos nós definidos na `nodeAffinity`. |
| Pod do PostgreSQL ou Redis não inicia | Consulte `kubectl describe pod`, eventos e logs; confira os PVCs e os caminhos de armazenamento. |
| API fica `NotReady` quando Redis ou PostgreSQL está indisponível | É o comportamento esperado: restaure a dependência e aguarde as próximas verificações da `readinessProbe`. |
| Container da API reinicia durante o teste de liveness | Confira os eventos e `RESTARTS`; confirme que o caminho da probe foi restaurado para `/health/live`. |
| Ingress não encaminha requisições | Confira se o controlador está `Ready`, se existe a classe `nginx`, se as portas 80/443 foram encaminhadas e se o Service tem endpoints. |
| Imagem da API não é obtida | Confira o nome/tag `flavioh/nodejs-kubernetes-health:1.0.0`, o acesso ao registry e os eventos do Pod. |

Comandos úteis durante o diagnóstico:

```bash
kubectl get pods -n nodejs-health -o wide
```
```bash
kubectl describe pod -n nodejs-health <nome-do-pod>
```
```bash
kubectl events -n nodejs-health --types=Warning
```
```bash
kubectl get endpointslices -n nodejs-health
```
```bash
kubectl describe ingress health-api -n nodejs-health
```
```bash
kubectl logs deployment/health-api -n nodejs-health
```

Substitua `<nome-do-pod>` pelo nome exibido por `kubectl get pods`; não copie os sinais `<` e `>` literalmente para o terminal.

## 8. Encerrar e limpar o laboratório

Remova os recursos namespaced excluindo o namespace:

```bash
kubectl delete namespace nodejs-health
```

Os PersistentVolumes têm escopo de cluster e precisam ser removidos separadamente:

```bash
kubectl delete pv nodejs-health-postgres-pv nodejs-health-redis-pv
```

Se o Ingress Controller tiver sido instalado exclusivamente para este laboratório, remova-o usando o mesmo manifesto de instalação:

```bash
kubectl delete -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

Para excluir também o cluster local inteiro:

```bash
kind delete cluster --name lab
```

> [!WARNING]
> Os PVs usam `persistentVolumeReclaimPolicy: Retain`. A remoção dos PVCs ou dos objetos PV não apaga necessariamente o conteúdo dos diretórios `hostPath`. Neste exemplo, esses diretórios ficam dentro dos nós containerizados; excluir o cluster remove os nós e pode eliminar os dados. Não exclua um cluster compartilhado ou que contenha dados a preservar.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [Kubernetes Documentation | Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/)
> - [Kubernetes Documentation | Configure Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/tasks/configure-pod-container/configure-liveness-readiness-probes/)
> - [Kubernetes Documentation | kubectl events](https://kubernetes.io/docs/reference/kubectl/generated/kubectl_events/)
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
> - [Kubernetes Documentation | ConfigMaps](https://kubernetes.io/docs/concepts/configuration/configmap/)
> - [Kubernetes Documentation | Secrets](https://kubernetes.io/docs/concepts/configuration/secret/)
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [kind | Ingress](https://kind.sigs.k8s.io/docs/user/ingress/)
> - [kind | Configuration — Extra Port Mappings](https://kind.sigs.k8s.io/docs/user/configuration/#extra-port-mappings)
> - [Kubernetes Blog | Ingress NGINX Retirement: What You Need to Know](https://kubernetes.io/blog/2025/11/11/ingress-nginx-retirement/)
> - [Ingress NGINX Controller | Repository arquivado](https://github.com/kubernetes/ingress-nginx)
