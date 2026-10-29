# Laboratório 09 | Solução

## Objetivo

Criar um cluster Kubernetes local com `kind`, implantar a Spring PetClinic com frontend, backend e PostgreSQL, publicar a aplicação por meio de um Ingress, inspecionar probes e consultar métricas de recursos.

> [!NOTE]
> Execute os comandos a partir da raiz do diretório `lab-09`. O arquivo `kind-config.yaml` fica nessa pasta e os manifestos ficam no subdiretório `petclinic/`.

## Tarefas

### 1. Criar o cluster `kind`

Confirme que o Docker está em execução e que as ferramentas estão disponíveis:

```bash
kind version
kubectl version --client
```

Crie o cluster com a configuração fornecida:

```bash
kind create cluster --name lab --config kind-config.yaml
```

O arquivo `kind-config.yaml` configura três nós, define `ingress-ready=true` no `control-plane` e encaminha as portas 80 e 443 do host para esse nó. Verifique o cluster:

```bash
kind get clusters
kubectl config current-context
kubectl cluster-info
kubectl get nodes -o wide
kubectl get node lab-control-plane --show-labels
```

O contexto esperado é `kind-lab`; os três nós devem estar `Ready`. Se o contexto atual não for o do laboratório, selecione-o explicitamente:

```bash
kubectl config use-context kind-lab
```

Confirme que o nó do `control-plane` possui o rótulo `ingress-ready=true`. A configuração do cluster já define esse rótulo; o comando abaixo o reaplica se necessário:

```bash
kubectl label node lab-control-plane ingress-ready=true --overwrite
```

### 2. Instalar e preparar o Ingress Controller

Instale o Ingress NGINX usado neste exemplo:

```bash
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

O controlador deve ser encaminhado ao nó que recebe as portas do host. Aplique o `nodeSelector` ao Deployment:

```bash
kubectl patch deployment ingress-nginx-controller \
  --namespace ingress-nginx \
  --type=merge \
  -p '{"spec":{"template":{"spec":{"nodeSelector":{"ingress-ready":"true"}}}}}'
```

Aguarde o rollout e verifique o controlador e a classe de Ingress:

```bash
kubectl rollout status deployment/ingress-nginx-controller -n ingress-nginx --timeout=180s
kubectl get pods -n ingress-nginx -o wide
kubectl get ingressclass
kubectl describe ingressclass nginx
```

A classe `nginx` precisa existir porque `petclinic/11-ingress.yaml` define `ingressClassName: nginx`. O `Ingress` só aplica o roteamento quando um controlador compatível está em execução.

> [!IMPORTANT]
> O Ingress NGINX foi aposentado em março de 2026 e o repositório está arquivado. O manifesto acima é utilizado para reproduzir o laboratório e não é uma recomendação para novas implantações de produção.

### 3. Validar e aplicar os manifestos da aplicação

Valide os arquivos e, depois, aplique o diretório que contém os manifestos:
```bash
kubectl apply --dry-run=client -f petclinic/
```
```bash
kubectl apply -f petclinic/
```

Confira os recursos namespaced e os recursos de armazenamento:
```bash
kubectl get namespace petclinic
```
```bash
kubectl get all -n petclinic
```
```bash
kubectl get configmap,secret,pvc,ingress -n petclinic
```
```bash
kubectl get pv petclinic-database-pv -o wide
```
```bash
kubectl describe pvc petclinic-database-pvc -n petclinic
```

O PVC `petclinic-database-pvc` deve chegar ao estado `Bound`. O `PersistentVolume` é um recurso de escopo do cluster, enquanto o PVC pertence ao namespace `petclinic`.

Aguarde os três workloads terminarem o rollout:
```bash
kubectl rollout status statefulset/database -n petclinic --timeout=180s
```
```bash
kubectl rollout status deployment/backend -n petclinic --timeout=180s
```
```bash
kubectl rollout status deployment/frontend -n petclinic --timeout=180s
```

Inspecione os Pods, Services, EndpointSlices e eventos:
```bash
kubectl get pods -n petclinic -o wide
```
```bash
kubectl get services,endpointslices -n petclinic -o wide
```
```bash
kubectl get ingress -n petclinic
```
```bash
kubectl events -n petclinic --types=Warning
```

Para diagnosticar um Pod que não ficou pronto, use `kubectl describe` e consulte os logs do workload correspondente:
```bash
kubectl describe pod -n petclinic <nome-do-pod>
```
```bash
kubectl logs -n petclinic deployment/backend
```
```bash
kubectl logs -n petclinic deployment/frontend
```
```bash
kubectl logs -n petclinic statefulset/database
```

Substitua `<nome-do-pod>` pelo nome real exibido por `kubectl get pods`. Os logs do backend também podem incluir mensagens do init container `wait-for-database` se ele ainda estiver esperando o PostgreSQL.

### 4. Verificar o acesso à aplicação

Abra a aplicação no navegador:

[http://petclinic.localhost/](http://petclinic.localhost/)

A regra do Ingress envia `/petclinic/api` e `/petclinic/actuator` ao Service `backend`, na porta 9966, e encaminha as demais rotas ao Service `frontend`, na porta 8080. Para conferir a configuração:
```bash
kubectl describe ingress petclinic -n petclinic
```
```bash
kubectl get services -n petclinic
```
```bash
kubectl get endpointslices -n petclinic -o wide
```
```bash
kubectl get pods -n ingress-nginx -o wide
```

Se a página não abrir, confirme que as portas 80 e 443 estavam disponíveis quando o cluster foi criado, que o controlador está pronto e que os EndpointSlices dos Services possuem endpoints prontos.

### 5. Inspecionar as probes

O Deployment `backend` define três probes HTTP:

- `startupProbe`: `/petclinic/actuator/health/liveness`, na porta nomeada `http`;
- `livenessProbe`: `/petclinic/actuator/health/liveness`, na porta nomeada `http`;
- `readinessProbe`: `/petclinic/actuator/health/readiness`, na porta nomeada `http`.

O StatefulSet `database` define probes do tipo `exec`, executando `pg_isready` com o usuário e o banco configurados no Secret. Inspecione os manifestos aplicados:
```bash
kubectl get deployment backend -n petclinic -o yaml
```
```bash
kubectl describe deployment backend -n petclinic
```
```bash
kubectl get statefulset database -n petclinic -o yaml
```
```bash
kubectl describe statefulset database -n petclinic
```

Teste os endpoints do Actuator no navegador:

- [http://petclinic.localhost/petclinic/actuator/health](http://petclinic.localhost/petclinic/actuator/health)
- [http://petclinic.localhost/petclinic/actuator/health/liveness](http://petclinic.localhost/petclinic/actuator/health/liveness)
- [http://petclinic.localhost/petclinic/actuator/health/readiness](http://petclinic.localhost/petclinic/actuator/health/readiness)

Os endpoints devem responder quando o backend estiver disponível. Se não responderem, verifique o estado e os eventos dos Pods, a regra do Ingress e os logs do backend:
```bash
kubectl get pods -n petclinic
```
```bash
kubectl events -n petclinic --types=Warning
```
```bash
kubectl logs -n petclinic deployment/backend
```

### 6. Instalar o `metrics-server` e consultar métricas

Aplique o manifesto oficial do `metrics-server`:
```bash
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

Confira o Deployment e os Pods no namespace `kube-system`:
```bash
kubectl get deployment metrics-server -n kube-system
```
```bash
kubectl get pods -n kube-system
```

No cluster `kind` deste laboratório, adicione `--kubelet-insecure-tls` aos argumentos do container do `metrics-server`:
```bash
kubectl patch deployment metrics-server \
  --namespace kube-system \
  --type=json \
  -p='[{"op":"add","path":"/spec/template/spec/containers/0/args/-","value":"--kubelet-insecure-tls"}]'
```

Aguarde o rollout e confira o Deployment:
```bash
kubectl rollout status deployment/metrics-server -n kube-system --timeout=120s
```
```bash
kubectl get deployment metrics-server -n kube-system -o wide
```
```bash
kubectl get pods -n kube-system -o wide
```
```bash
kubectl describe deployment metrics-server -n kube-system
```

O pipeline de métricas pode levar algum tempo para começar a fornecer valores. Depois que o `metrics-server` estiver pronto, consulte consumo por nó e por Pod:
```bash
kubectl top nodes
```
```bash
kubectl top pods -n petclinic
```

O comando `kubectl top pods -n petclinic` evita fixar no roteiro um nome de Pod gerado pelo Deployment, pois esse nome pode mudar em novos rollouts.

Confira também os recursos `requests` e `limits` declarados pelo backend:
```bash
kubectl describe deployment backend -n petclinic
```
```bash
kubectl get deployment backend -n petclinic -o yaml
```
```bash
kubectl get pods -n petclinic -o wide
```

No manifesto, o backend solicita `500m` de CPU e `500Mi` de memória e define esses mesmos valores como limites. A saída de `kubectl top` representa o consumo observado no momento da consulta; ela não substitui `requests` e `limits`, que definem os recursos solicitados e limitados pelo manifesto.

> [!WARNING]
> `--kubelet-insecure-tls` desativa a validação do certificado TLS apresentado pelo kubelet. O ajuste é utilizado aqui apenas para viabilizar métricas no cluster de laboratório; não o use como padrão em ambientes de produção.

Se `kubectl top` informar que a API de métricas não está disponível, aguarde alguns instantes e investigue o estado e os logs do `metrics-server`:
```bash
kubectl get apiservices | grep metrics
```
```bash
kubectl logs -n kube-system deployment/metrics-server
```
```bash
kubectl events -n kube-system --types=Warning
```

### 7. Limpar o ambiente

Remova os recursos definidos nos manifestos da aplicação:

```bash
kubectl delete -f petclinic/
```

Confira se os recursos namespaced e o PersistentVolume foram removidos:
```bash
kubectl get all -n petclinic
```
```bash
kubectl get pv
```

Se a exclusão do diretório não remover algum recurso devido ao encerramento do namespace, confirme que não há dados que precisem ser preservados e remova o namespace e o PV separadamente, se ainda existirem:
```bash
kubectl delete namespace petclinic --ignore-not-found
```
```bash
kubectl delete pv petclinic-database-pv --ignore-not-found
```

Remova o Ingress Controller instalado para o laboratório:
```bash
kubectl delete -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

Por fim, exclua o cluster:
```bash
kind delete cluster --name lab
```

A exclusão do cluster remove os nós containerizados de `kind` e pode eliminar os dados existentes nos diretórios `hostPath`.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
> - [kind | Ingress](https://kind.sigs.k8s.io/docs/user/ingress/)
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [Kubernetes Documentation | Startup, Liveness and Readiness Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/)
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
> - [Kubernetes Documentation | Resource Metrics Pipeline](https://kubernetes.io/docs/tasks/debug/debug-cluster/resource-metrics-pipeline/)
> - [Metrics Server | GitHub repository](https://github.com/kubernetes-sigs/metrics-server)
> - [Kubernetes Documentation | Ingress NGINX Retirement: What You Need to Know](https://kubernetes.io/blog/2025/11/11/ingress-nginx-retirement/)
> - [Ingress NGINX Controller | Repositório arquivado](https://github.com/kubernetes/ingress-nginx)
