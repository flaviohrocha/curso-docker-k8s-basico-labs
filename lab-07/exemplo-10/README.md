# Exemplo — Publicando uma aplicação PHP com MariaDB e Ingress no Kubernetes

## Objetivo do exemplo

Este exercício implanta a aplicação PHP `message-server` e seu banco de dados MariaDB em um cluster Kubernetes local criado com **kind**. A aplicação será publicada por um `Ingress`, enquanto o banco será executado em um `StatefulSet` com armazenamento persistente.

Ao longo das etapas, você vai:

- criar um cluster `kind` com encaminhamento das portas HTTP e HTTPS para o host;
- instalar e configurar o Ingress Controller;
- separar os recursos da aplicação no namespace `message-app`;
- definir configurações não sensíveis em um `ConfigMap` e credenciais em um `Secret`;
- disponibilizar armazenamento para o MariaDB por meio de `PersistentVolume` e `PersistentVolumeClaim`;
- implantar o MariaDB com `StatefulSet` e um Service headless;
- implantar a aplicação com `Deployment` e `Service`;
- criar um `Ingress` para acessar a aplicação por `http://message.localhost/`;
- verificar os objetos Kubernetes, os logs, a conectividade e a persistência dos dados.

> [!IMPORTANT]
> Este roteiro segue os manifestos fornecidos e utiliza o Ingress NGINX (`kubernetes/ingress-nginx`) para reproduzir o laboratório. O projeto foi aposentado em março de 2026 e seu repositório está arquivado. Os comandos podem ser úteis para compreender ou reproduzir um ambiente existente, mas não escolha esse controlador para novas implantações de produção. Para novos ambientes, avalie uma implementação mantida de Ingress ou a Gateway API. Consulte [Referências](#referências).

## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com `kind`.
2. Confirme que `kind` e `kubectl` estão instalados e disponíveis no `PATH`.
3. Abra um terminal Bash, como Git Bash ou uma distribuição Linux/WSL. Os comandos deste roteiro usam sintaxe Bash; no PowerShell, quebras de linha e comandos de shell podem exigir adaptação.
4. Acesse a pasta localizada em `lab-07\exemplo-10` para execução do laboratório.
5. Confirme que a imagem `flavioh/message-server:1.0.0` está publicada no registry acessível pelo cluster ou disponível para carregamento no `kind`.
6. Verifique se as portas 80 e 443 do host estão disponíveis.

### Sobre a imagem da aplicação

O Deployment usa a imagem `flavioh/message-server:1.0.0` com `imagePullPolicy: IfNotPresent`. Se ela estiver publicada em um registry acessível, o Kubernetes poderá baixá-la.

Execute esses comandos somente no diretório correto da aplicação. Se a imagem local tiver outro nome ou tag, ajuste o campo `image` de `07-message-server-deployment.yaml` para corresponder a ela.

## 1. Criar o cluster kind

O arquivo `kind-config.yaml` define um nó `control-plane`, dois nós `worker`, o rótulo `ingress-ready=true` no `control-plane` e o encaminhamento das portas 80 e 443 do host para esse nó.

Crie o cluster:

```bash
kind create cluster --name lab --config kind-config.yaml
```

- `--name lab` define o nome do cluster.
- `--config kind-config.yaml` aplica a configuração dos nós e das portas.
- `extraPortMappings` encaminha as portas do host para o nó do cluster; isso não publica diretamente a porta do container da aplicação.

Se o cluster `lab` já existir, examine sua configuração antes de prosseguir. A configuração de encaminhamento é aplicada na criação do cluster. Só o exclua e recrie se puder perder os recursos e os dados que estiverem nele:

```bash
kind delete cluster --name lab
```
```bash
kind create cluster --name lab --config kind-config.yaml
```

### Verificar o cluster

```bash
kind get clusters
```
```bash
kubectl config current-context
```
```bash
kubectl cluster-info
```
```bash
kubectl get nodes -o wide
```

Confirme que o contexto atual é normalmente `kind-lab` e que os três nós estão `Ready`.

## 2. Instalar e preparar o Ingress Controller

Um recurso `Ingress` declara regras de roteamento HTTP(S), mas não encaminha tráfego sozinho. Para aplicar essas regras, é necessário um Ingress Controller.

Instale o controlador usado neste exemplo:

```bash
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

O arquivo `kind-config.yaml` já define o rótulo do nó, mas o comando abaixo permite conferir ou atualizar esse rótulo:

```bash
kubectl label node lab-control-plane ingress-ready=true --overwrite
```
```bash
kubectl get node lab-control-plane --show-labels
```

Direcione o Deployment do controlador para o nó que recebe as portas encaminhadas. O comando abaixo usa a sintaxe JSON corrigida:

```bash
kubectl patch deployment ingress-nginx-controller \
  --namespace ingress-nginx \
  --type=merge \
  -p '{"spec":{"template":{"spec":{"nodeSelector":{"ingress-ready":"true"}}}}}'
```

Aguarde o controlador e confirme a classe de Ingress:

```bash
kubectl rollout status deployment/ingress-nginx-controller -n ingress-nginx --timeout=180s
```
```bash
kubectl get pods -n ingress-nginx -o wide
```
```bash
kubectl get ingressclass
```
```bash
kubectl describe ingressclass nginx
```

O manifesto `09-ingress.yaml` usa `ingressClassName: nginx`. Essa classe precisa existir e ser atendida pelo controlador instalado antes que o roteamento funcione.

## 3. Aplicar os manifestos da aplicação

Entre na pasta `message-app`, que contém os arquivos.  
Aplique os manifestos na ordem abaixo. O namespace vem primeiro; a configuração e o Secret são criados antes dos workloads; o volume e o PVC são preparados antes do StatefulSet do MariaDB.

### 3.1 Namespace, ConfigMap e Secret

```bash
kubectl apply -f 00-namespace.yaml
```
```bash
kubectl apply -f 01-configmap.yaml
```
```bash
kubectl apply -f 02-secret.yaml
```

Os recursos namespaced ficam no namespace `message-app`.

- `message-app-config` contém `DB_HOST`, `DB_NAME` e `DB_USER`.
- `message-app-secret` contém `DB_PASSWORD` e `MARIADB_ROOT_PASSWORD`.

Confira os objetos:

```bash
kubectl get namespace message-app
```
```bash
kubectl get configmap message-app-config -n message-app
```
```bash
kubectl describe configmap message-app-config -n message-app
```
```bash
kubectl get secret message-app-secret -n message-app
```
```bash
kubectl describe secret message-app-secret -n message-app
```

O comando `describe secret` mostra as chaves e o tamanho dos valores, não os valores em texto puro.

> [!WARNING]
> As credenciais `message` e o uso do usuário `root` são somente para demonstração. Não reutilize essas credenciais em ambientes reais. Dados armazenados em um Kubernetes Secret não devem ser considerados protegidos apenas por estarem em um Secret; configure os controles de acesso e a proteção de dados do cluster adequadamente.

### 3.2 PersistentVolume e PersistentVolumeClaim

Crie o volume persistente e solicite armazenamento para ele:

```bash
kubectl apply -f 03-persistent-volume.yaml
```
```bash
kubectl get pv message-mariadb-pv
```
```bash
kubectl describe pv message-mariadb-pv
```
```bash
kubectl apply -f 04-persistent-volume-claim.yaml
```
```bash
kubectl get pvc -n message-app
```
```bash
kubectl describe pvc message-mariadb-pvc -n message-app
```

O PV define capacidade de `5Gi`, modo de acesso `ReadWriteOnce`, política de retenção `Retain` e `storageClassName: manual`. O PVC solicita `5Gi` e referencia explicitamente `message-mariadb-pv`. Verifique se o PVC chega ao estado `Bound` antes de iniciar o banco.

> [!WARNING]
> O PV fornecido usa `hostPath` em `/var/lib/kubernetes/message-app/mariadb`. Em um cluster `kind`, esse caminho fica no sistema de arquivos do nó (que é um container), e não diretamente no sistema de arquivos do computador host. Os dados podem permanecer durante a recriação do Pod enquanto o nó existir, mas a exclusão do cluster remove os nós e pode eliminar esses dados. Essa configuração é apropriada para um laboratório local, não para armazenamento de produção.

### 3.3 Service headless e StatefulSet do MariaDB

Crie primeiro o Service headless, que fornece identidade DNS estável ao StatefulSet, e depois o StatefulSet:

```bash
kubectl apply -f 05-mariadb-service.yaml
```
```bash
kubectl apply -f 06-mariadb-statefulset.yaml
```

Aguarde o Pod ser criado e examine seu estado:

```bash
kubectl rollout status statefulset/message-mariadb -n message-app --timeout=180s
```
```bash
kubectl get statefulset message-mariadb -n message-app
```
```bash
kubectl get pods -n message-app -o wide
```
```bash
kubectl logs statefulset/message-mariadb -n message-app
```

O nome estável esperado do Pod do StatefulSet com uma réplica é `message-mariadb-0`. Para verificar se o MariaDB concluiu a inicialização, execute o utilitário de health check da imagem:

```bash
kubectl exec -n message-app message-mariadb-0 -- healthcheck.sh --connect --innodb_initialized
```

Se o Pod não estiver pronto ou o comando falhar, examine os eventos e os logs antes de prosseguir:

```bash
kubectl describe pod message-mariadb-0 -n message-app
```
```bash
kubectl get events -n message-app --sort-by=.lastTimestamp
```
```bash
kubectl logs -n message-app message-mariadb-0
```

### 3.4 Deployment e Service da aplicação

Crie o Deployment que executa o servidor PHP/Apache:

```bash
kubectl apply -f 07-message-server-deployment.yaml
```
```bash
kubectl rollout status deployment/message-server -n message-app
```

O Deployment obtém `DB_HOST`, `DB_NAME` e `DB_USER` do ConfigMap e `DB_PASSWORD` do Secret. O valor de `DB_HOST` é `message-mariadb`, nome do Service interno do banco.

Crie o Service da aplicação:

```bash
kubectl apply -f 08-message-server-service.yaml
```

Verifique o Deployment, os Pods e o Service:

```bash
kubectl get deployment message-server -n message-app -o wide
```
```bash
kubectl get pods -n message-app -o wide --show-labels
```
```bash
kubectl describe deployment message-server -n message-app
```
```bash
kubectl get service message-server -n message-app
```
```bash
kubectl describe service message-server -n message-app
```
```bash
kubectl get endpointslices -n message-app -l kubernetes.io/service-name=message-server -o wide
```
```bash
kubectl logs deployment/message-server -n message-app
```

O Service seleciona os Pods pela label `app.kubernetes.io/name: message-server`. Os EndpointSlices devem mostrar os endpoints correspondentes aos Pods prontos.

> [!NOTE]
> Ao contrário de `depends_on` com `condition: service_healthy` no Docker Compose, a ordem de criação dos recursos Kubernetes não garante que o banco esteja pronto antes da aplicação. O manifesto fornecido não define uma `readinessProbe` para o MariaDB nem um mecanismo de espera no container da aplicação. Por isso, verifique a saúde do banco e consulte os logs da aplicação se houver falha de conexão na inicialização.

### 3.5 Ingress

Depois de confirmar que o Service da aplicação possui endpoints, aplique o Ingress:

```bash
kubectl apply -f 09-ingress.yaml
```

O recurso encaminha solicitações para o host `message.localhost`, caminho `/`, ao Service `message-server` na porta `80`.

Consulte as regras e os eventos:

```bash
kubectl get ingress -n message-app
```
```bash
kubectl get ingress message-server -n message-app -o wide
```
```bash
kubectl describe ingress message-server -n message-app
```

## 4. Verificar todos os objetos criados

Use os comandos abaixo para conferir os recursos depois de aplicar todos os manifestos:

### Pods, Services, Deployments, StatefulSets e outros recursos de workload

```bash
kubectl get all -n message-app
```

### Configurações, credenciais, armazenamento solicitado e Ingress

```bash
kubectl get configmap,secret,pvc,ingress -n message-app
```

### Volumes persistentes são recursos de escopo do cluster, sem namespace

```bash
kubectl get pv
```
```bash
kubectl get pv message-mariadb-pv -o wide
```

### Classes de armazenamento disponíveis

```bash
kubectl get storageclass
```

Para uma visão conjunta do estado dos workloads e de seus rótulos:

```bash
kubectl get pods,services,deployments,statefulsets -n message-app -o wide --show-labels
```

Resultados esperados no fluxo principal:

- `message-mariadb-0` e o Pod do `message-server` em execução;
- Deployment `message-server` com a réplica disponível;
- StatefulSet `message-mariadb` com uma réplica pronta;
- Service `message-mariadb` e Service `message-server` criados;
- Ingress `message-server` com o host `message.localhost`;
- PVC `message-mariadb-pvc` em estado `Bound`;
- PV `message-mariadb-pv` associado ao PVC.

`kubectl get all` não exibe todos os tipos de recursos, por isso ConfigMap, Secret, PVC, Ingress e PV são consultados separadamente.

## 5. Testar o acesso à aplicação

Abra no navegador:

[http://message.localhost/message.php](http://message.localhost/message.php)


O teste depende de as portas 80/443 estarem encaminhadas pelo `kind`, o Ingress Controller estar disponível e o Ingress apontar para um Service com endpoints prontos. Uma resposta `404` pode indicar que host ou caminho não corresponde à regra, ou pode vir da própria aplicação. Respostas `502` ou `503` justificam verificar o Service, os EndpointSlices, o estado dos Pods e os logs.

### Verificações para diagnóstico

```bash
kubectl get pods -n ingress-nginx -o wide
kubectl get ingressclass
kubectl get pods -n message-app -o wide
kubectl get svc -n message-app
kubectl get endpointslices -n message-app -o wide
kubectl describe ingress message-server -n message-app
kubectl logs deployment/ingress-nginx-controller -n ingress-nginx
kubectl logs deployment/message-server -n message-app
kubectl logs statefulset/message-mariadb -n message-app
```

Problemas frequentes:

| Sintoma | O que verificar |
| --- | --- |
| O cluster não inicia | Docker está em execução; portas 80/443 estão disponíveis; configuração do `kind` é válida. |
| O controlador fica `Pending` | Rótulo `ingress-ready=true`, `nodeSelector` e estado dos nós. |
| PVC fica `Pending` | PV criado, capacidade, `storageClassName`, `accessModes` e `volumeName`. |
| MariaDB não inicia | Logs do StatefulSet, permissões/estado do diretório de dados e Secret. |
| Aplicação não conecta ao banco | `DB_HOST=message-mariadb`, Service e endpoints do banco, credenciais e inicialização do MariaDB. |
| Ingress retorna `404` | `ingressClassName`, host `message.localhost`, caminho `/` e controlador ativo. |
| Ingress retorna `502` ou `503` | Endpoints do Service, Pods prontos, porta do Service e logs da aplicação. |

## 6. Atividades complementares

### Usar uma StorageClass com provisionamento dinâmico

O caminho principal deste laboratório usa PV e PVC estáticos com `storageClassName: manual`. Como atividade complementar, consulte as classes disponíveis:

```bash
kubectl get storageclass
```

Só prossiga se o cluster tiver uma `StorageClass` funcional, como `standard` respaldada por um provisionador dinâmico. Para usar o provisionamento dinâmico, adapte `04-persistent-volume-claim.yaml` para utilizar a classe disponível (ou a classe padrão), removendo `volumeName: message-mariadb-pv`. Não aplique o PV estático do exemplo nessa variante. Faça essa alteração em um ambiente de laboratório descartável e pare o StatefulSet antes de substituir o PVC. A nova reivindicação recebe armazenamento novo; os dados do volume anterior não são migrados automaticamente.

> [!WARNING]
> Alterar ou remover PVCs pode interromper o banco e tornar seus dados indisponíveis. Não execute a migração em um ambiente que contenha dados que precisam ser preservados sem antes planejar e validar um backup e a migração.

### Importar todas as variáveis do ConfigMap com `envFrom`

O Deployment atual importa as variáveis do ConfigMap uma a uma usando `configMapKeyRef`. Como exercício, altere `07-message-server-deployment.yaml` para utilizar `envFrom` com `configMapRef` para importar `DB_HOST`, `DB_NAME` e `DB_USER` em conjunto. Mantenha `DB_PASSWORD` associado ao Secret, pois se trata de uma informação sensível.

Depois da alteração, aplique novamente e verifique o rollout e os logs:

```bash
kubectl apply -f 07-message-server-deployment.yaml
```
```bash
kubectl rollout status deployment/message-server -n message-app --timeout=180s
```
```bash
kubectl logs deployment/message-server -n message-app
```

## 7. Encerrar e limpar o laboratório

Para remover os recursos namespaced da aplicação, execute os comandos a partir do diretório dos manifestos. O PV é removido separadamente porque não pertence ao namespace:

```bash
kubectl delete -f 09-ingress.yaml
kubectl delete -f 08-message-server-service.yaml
kubectl delete -f 07-message-server-deployment.yaml
kubectl delete -f 06-mariadb-statefulset.yaml
kubectl delete -f 05-mariadb-service.yaml
kubectl delete -f 04-persistent-volume-claim.yaml
kubectl delete -f 03-persistent-volume.yaml
kubectl delete -f 02-secret.yaml
kubectl delete -f 01-configmap.yaml
kubectl delete -f 00-namespace.yaml
```

Confirme que o PV foi removido:

```bash
kubectl get pv
```

Como alternativa, remova o namespace para excluir todos os recursos namespaced de uma vez e depois remova o PV separadamente:

```bash
kubectl delete namespace message-app
kubectl delete -f 03-persistent-volume.yaml
```

> [!WARNING]
> A política de recuperação do PV é `Retain`, portanto a exclusão do PVC não apaga automaticamente os dados do volume subjacente. Contudo, neste exemplo o diretório fica dentro do nó containerizado do `kind`; excluir o cluster também exclui os nós e pode eliminar os dados armazenados neles.

Para remover o Ingress Controller instalado para o laboratório:

```bash
kubectl delete -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

Por fim, para excluir o cluster inteiro:

```bash
kind delete cluster --name lab
```

Esse comando remove o cluster local e os recursos que estão dentro dele. Não é necessário executá-lo para simplesmente interromper a aplicação.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [Kubernetes Documentation | Services](https://kubernetes.io/docs/concepts/services-networking/service/)
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | StatefulSets](https://kubernetes.io/docs/concepts/workloads/controllers/statefulset/)
> - [Kubernetes Documentation | ConfigMaps](https://kubernetes.io/docs/concepts/configuration/configmap/)
> - [Kubernetes Documentation | Secrets](https://kubernetes.io/docs/concepts/configuration/secret/)
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
> - [kind | Ingress](https://kind.sigs.k8s.io/docs/user/ingress/)
> - [kind | Configuration — Extra Port Mappings](https://kind.sigs.k8s.io/docs/user/configuration/#extra-port-mappings)
> - [Kubernetes Documentation | Ingress NGINX Retirement: What You Need to Know](https://kubernetes.io/blog/2025/11/11/ingress-nginx-retirement/)
> - [Ingress NGINX Controller | Repository arquivado](https://github.com/kubernetes/ingress-nginx)
