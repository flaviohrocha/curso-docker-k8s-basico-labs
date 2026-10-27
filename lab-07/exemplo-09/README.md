# Exemplo — Publicando uma aplicação com Ingress no Kubernetes

## Objetivo do exemplo

Este exercício utiliza um cluster Kubernetes local criado com **kind**, a ferramenta **kubectl** e um `Ingress Controller` para publicar uma aplicação HTTP fora do cluster.

Ao longo das etapas, você vai:

- criar um cluster `kind` com encaminhamento das portas HTTP e HTTPS para o host;
- instalar o controlador de Ingress usado no laboratório;
- preparar o nó `control-plane` para receber o controlador;
- criar o namespace `exemplo-09`;
- implantar a aplicação por meio de um `Deployment` e de um `Service`;
- criar um recurso `Ingress` que encaminha solicitações para o Service;
- testar o acesso à aplicação pelo endereço `http://k8s-in-action.localhost/`;
- consultar os recursos e investigar falhas comuns.

> [!IMPORTANT]
> Este roteiro reproduz o laboratório descrito nos comandos fornecidos. O projeto **Ingress NGINX (`kubernetes/ingress-nginx`) foi aposentado em março de 2026** e seu repositório está arquivado. Os comandos permanecem úteis para compreender ou reproduzir um ambiente já existente, mas não se deve escolher esse controlador para novas implantações de produção. Para novos ambientes, avalie uma implementação mantida de Ingress ou a Gateway API. Consulte a seção [Referências](#referências).

## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com o `kind`.
2. Confirme que `kind` e `kubectl` estão instalados e disponíveis no `PATH`.
3. Abra um terminal Bash, como o Git Bash. Os comandos deste roteiro usam sintaxe compatível com Bash; no PowerShell, a quebra de linha e alguns comandos de shell podem exigir adaptação.
4. Entre no diretório que contém `kind-config.yaml`, `deployment.yaml`, `service.yaml` e `ingress.yaml`.
5. Verifique se as portas 80 e 443 do host estão disponíveis, pois o exemplo as utiliza para HTTP e HTTPS.

### Configurar o encaminhamento de portas do kind

Para receber solicitações em `http://k8s-in-action.localhost/`, a porta 80 do host deve ser encaminhada para a porta 80 do nó `control-plane`. O mesmo exemplo encaminha a porta 443 do host para a porta 443 do nó, para permitir testes HTTPS caso o Ingress também seja configurado com TLS.

Um exemplo de `kind-config.yaml` é:

```yaml
kind: Cluster
apiVersion: kind.x-k8s.io/v1alpha4
nodes:
  - role: control-plane
    extraPortMappings:
      - containerPort: 80
        hostPort: 80
        listenAddress: "127.0.0.1"
        protocol: TCP
      - containerPort: 443
        hostPort: 443
        listenAddress: "127.0.0.1"
        protocol: TCP
  - role: worker
  - role: worker
```

- `extraPortMappings` configura o encaminhamento de portas do host para o container que representa o nó do `kind`.
- `containerPort: 80` e `containerPort: 443` são as portas no nó do cluster; não são as portas do container da aplicação.
- `hostPort: 80` e `hostPort: 443` são as portas publicadas na máquina que executa o `kind`.
- `listenAddress: "127.0.0.1"` limita o acesso às interfaces locais do host.
- A configuração de encaminhamento é aplicada na criação do cluster. Alterar esse arquivo depois não modifica automaticamente um cluster que já existe.

> [!WARNING]
> Se o cluster `lab` já existir e não tiver os encaminhamentos necessários, será preciso recriá-lo para aplicar essa configuração. A exclusão do cluster remove os recursos e os dados locais que estiverem armazenados nele. Não execute o comando de exclusão se precisar preservar o ambiente existente.

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
- `--name lab` atribui o nome `lab` ao cluster.
- `--config kind-config.yaml` informa a configuração dos nós e dos encaminhamentos de portas.


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

Mostra o contexto atualmente selecionado pelo `kubectl`. Para o cluster criado com `--name lab`, o contexto normalmente é `kind-lab`.

```bash
kubectl cluster-info
```

Verifica a comunicação com o cluster e mostra os endereços dos serviços principais do Kubernetes.

```bash
kubectl get nodes -o wide
```

Lista os nós do cluster, seus estados e informações adicionais. Antes de continuar, confirme que os nós relevantes estão `Ready` e que existe um nó chamado `lab-control-plane`.

## 1. Instalar o Ingress Controller

Um recurso `Ingress` é uma declaração de regras de roteamento HTTP(S); ele não encaminha tráfego por conta própria. Para que as regras sejam aplicadas, o cluster precisa ter um **Ingress Controller** que observe os recursos `Ingress` e configure o proxy ou balanceador correspondente.

Este laboratório usa o manifesto do `ingress-nginx` disponibilizado pelo projeto para ambientes `kind`.

### Aplicar o manifesto do controlador

```bash
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

O comando solicita ao Kubernetes que crie ou atualize os recursos definidos pelo manifesto remoto. O pacote instala o controlador e os recursos de suporte no namespace `ingress-nginx`.

A instalação pode levar algum tempo. Aguarde até que os Pods do controlador estejam prontos antes de testar a aplicação.

> [!NOTE]
> O endereço está fixado na versão `controller-v1.15.1` para reproduzir o exercício. O repositório `kubernetes/ingress-nginx` foi arquivado em março de 2026; consulte o aviso de aposentadoria nas referências antes de usar essa instalação em qualquer ambiente novo.

### Marcar o nó que receberá o controlador

```bash
kubectl label node lab-control-plane ingress-ready=true --overwrite
```

Adiciona o rótulo `ingress-ready=true` ao nó `lab-control-plane`.

- `kubectl label node` altera os rótulos de um nó.
- `ingress-ready=true` identifica o nó que deverá receber o controlador.
- `--overwrite` permite substituir o valor do rótulo caso ele já exista.

O nó deve corresponder ao nome exibido por `kubectl get nodes`. Se o nome for diferente, ajuste o comando. A mensagem `not labeled` pode indicar que o rótulo já tinha o valor solicitado; confira o estado atual antes de considerar isso uma falha.

Confira o rótulo:

```bash
kubectl get node lab-control-plane --show-labels
```

### Direcionar o Deployment do controlador para esse nó

```bash
kubectl patch deployment ingress-nginx-controller \
  --namespace ingress-nginx \
  --type=merge \
  -p '{"spec":{"template":{"spec":{"nodeSelector":{"ingress-ready":"true"}}}}}'
```

O comando altera o template de Pod do Deployment `ingress-nginx-controller`, no namespace `ingress-nginx`, adicionando um `nodeSelector` que requer o rótulo `ingress-ready=true`.

- `--namespace ingress-nginx` seleciona o namespace do controlador.
- `--type=merge` aplica uma alteração do tipo *JSON merge patch*.
- `nodeSelector` restringe o agendamento aos nós com o rótulo indicado.

Essa etapa é importante porque o manifesto de `kind` usa encaminhamentos de portas no nó e o controlador precisa executar no nó que as recebe. O `nodeSelector` restringe a localização do Pod; não substitui a configuração `extraPortMappings` do cluster.

### Verificar os Pods do controlador

```bash
kubectl get pods -n ingress-nginx -o wide
```

Lista os Pods do namespace `ingress-nginx`, incluindo o estado e o nó em que cada Pod está executando.

```bash
kubectl get deployment ingress-nginx-controller -n ingress-nginx
```

Mostra o estado do Deployment. Verifique as colunas de réplicas desejadas, atualizadas e disponíveis.

```bash
kubectl rollout status deployment/ingress-nginx-controller -n ingress-nginx
```

Acompanha a atualização do Deployment até que o rollout termine ou o comando informe que não foi possível concluir.

Se o Pod não ficar `Running` e `Ready`, investigue antes de continuar:

```bash
kubectl describe pods -n ingress-nginx
```

Exibe os detalhes dos Pods e os eventos associados, que podem indicar problemas de agendamento, imagem ou inicialização.

### Verificar a IngressClass

```bash
kubectl get ingressclass
```

Lista as classes de Ingress registradas no cluster. O manifesto deste laboratório deve usar a classe `nginx`, que precisa corresponder a uma classe existente e atendida pelo controlador instalado.

Para inspecionar especificamente essa classe:

```bash
kubectl describe ingressclass nginx
```

Exibe os detalhes da classe `nginx`, incluindo a identificação do controlador associado.

## 2. Namespace

O namespace `exemplo-09` agrupa o Deployment, o Service e o recurso Ingress da aplicação, mantendo-os separados de recursos de outros exercícios.

### Criar o namespace

```bash
kubectl create namespace exemplo-09
```

Cria o namespace `exemplo-09`. Se ele já existir, o comando informará que o recurso existe; nesse caso, confirme sua presença com `kubectl get namespaces` e prossiga.

```bash
kubectl get namespaces
```

Lista os namespaces do cluster.


## 3. Deployment da aplicação

O `Deployment` declara o estado desejado da aplicação e gerencia suas réplicas por meio de um `ReplicaSet`. Neste exercício, o recurso é chamado `k8s-in-action-app` e a aplicação atende na porta `3000`, conforme os nomes e portas usados pelos manifestos do laboratório.

### Criar ou atualizar o Deployment

```bash
kubectl apply -f deployment.yaml
```

Aplica o conteúdo de `deployment.yaml`. O arquivo deve definir o Deployment `k8s-in-action-app`, no namespace `exemplo-09`, com os rótulos e a porta de container esperados pelo Service.

### Consultar o Deployment

```bash
kubectl -n exemplo-09 get deployment k8s-in-action-app
```

Mostra um resumo do Deployment, incluindo a quantidade de réplicas desejadas e disponíveis.

```bash
kubectl -n exemplo-09 get deployment k8s-in-action-app -o wide
```

Exibe colunas adicionais que ajudam a conferir a imagem e outras informações do recurso.

```bash
kubectl -n exemplo-09 describe deployment k8s-in-action-app
```

Mostra a configuração detalhada do Deployment, os seletores, o template do Pod, o estado das réplicas e os eventos recentes.

### Consultar os Pods da aplicação

```bash
kubectl get pods -n exemplo-09 -o wide
```

Lista os Pods do namespace e mostra em qual nó cada um está executando.

```bash
kubectl get pods -n exemplo-09 --show-labels
```

Exibe também os rótulos dos Pods. Confirme que os rótulos correspondem ao seletor configurado no Service. Um seletor incorreto pode deixar o Service sem endpoints, mesmo quando os Pods estão em execução.

```bash
kubectl get pods -n exemplo-09
```

Lista os Pods no namespace `exemplo-09`. Verifique se estão no estado `Running` e se a coluna `READY` indica que os containers estão prontos.

### Inspecionar eventos e logs

```bash
kubectl -n exemplo-09 describe pods
```

Mostra detalhes dos Pods e eventos, úteis para investigar falhas de agendamento, erros de pull da imagem ou problemas de inicialização.

```bash
kubectl logs -n exemplo-09 deployment/k8s-in-action-app
```

Consulta os logs dos Pods selecionados pelo Deployment, conforme o suporte do `kubectl` à versão instalada e a configuração de containers do recurso. Se houver mais de um container ou for necessário examinar uma réplica específica, consulte os nomes com `kubectl get pods -n exemplo-09` e use `kubectl logs <NOME_DO_POD> -n exemplo-09`.

## 4. Service da aplicação

O `Service` fornece um endereço estável dentro do cluster para alcançar os Pods da aplicação. Neste laboratório, o Ingress encaminha as solicitações para o Service `k8s-in-action-app`, na porta `3000`.

O Service deve selecionar os Pods por meio de rótulos correspondentes ao template do Deployment. Sua porta `port` deve corresponder à porta referenciada no Ingress; `targetPort` deve encaminhar o tráfego para a porta em que a aplicação realmente escuta no container.

### Criar ou atualizar o Service

```bash
kubectl apply -f service.yaml
```

Aplica o manifesto `service.yaml`, que deve definir o Service `k8s-in-action-app` no namespace `exemplo-09`.

### Consultar o Service

```bash
kubectl -n exemplo-09 get services
```

Lista os Services do namespace. Confira o nome e a porta `3000`.

```bash
kubectl -n exemplo-09 get service k8s-in-action-app -o wide
```

Exibe os detalhes resumidos do Service, incluindo o tipo, o ClusterIP, as portas e o seletor.

```bash
kubectl -n exemplo-09 describe service k8s-in-action-app
```

Mostra a configuração detalhada e os endpoints associados ao Service.

### Verificar os EndpointSlices

```bash
kubectl -n exemplo-09 get endpointslices -l kubernetes.io/service-name=k8s-in-action-app -o wide
```

Lista os `EndpointSlices` associados ao Service. Eles representam os endpoints de rede que o Kubernetes disponibiliza para encaminhar tráfego aos Pods selecionados.

Se não houver endpoints prontos, verifique:

- se os Pods estão `Ready`;
- se o `selector` do Service corresponde aos rótulos dos Pods;
- se o Service e os Pods estão no mesmo namespace;
- se `targetPort` corresponde à porta de escuta da aplicação.

> [!NOTE]
> Neste roteiro, o acesso externo é feito pelo Ingress, e não por um `NodePort`. A aplicação continua recebendo o tráfego por meio do Service interno do cluster.

## 5. Recurso Ingress

O recurso `Ingress` define as regras que associam requisições HTTP(S) a Services. A regra usada no exercício espera solicitações para o host `k8s-in-action.localhost` e encaminha o caminho `/` para o Service `k8s-in-action-app`, na porta `3000`.

Para o manifesto corresponder ao laboratório, confira estes campos em `ingress.yaml`:

| Campo | Valor esperado | Finalidade |
| --- | --- | --- |
| `metadata.name` | `k8s-in-action-ing` | Nome do recurso Ingress. |
| `metadata.namespace` | `exemplo-09` | Namespace em que o Ingress está criado. |
| `spec.ingressClassName` | `nginx` | Classe de Ingress que o controlador deve atender. |
| `spec.rules[].host` | `k8s-in-action.localhost` | Host HTTP que será comparado com a requisição. |
| `spec.rules[].http.paths[].path` | `/` | Caminho da requisição que será encaminhada. |
| `backend.service.name` | `k8s-in-action-app` | Service de destino. |
| `backend.service.port.number` | `3000` | Porta do Service de destino. |

### Criar ou atualizar o Ingress

```bash
kubectl apply -f ingress.yaml
```

Aplica o manifesto `ingress.yaml`. O recurso só produzirá o roteamento esperado se a classe, o host, o Service e a porta de destino estiverem corretos e o controlador estiver funcional.

### Consultar o Ingress

```bash
kubectl -n exemplo-09 get ingress
```

Lista os recursos Ingress do namespace. Confira o host exibido e, quando disponível, o endereço publicado pelo controlador.

```bash
kubectl -n exemplo-09 get ingress k8s-in-action-ing -o wide
```

Consulta o recurso específico com informações adicionais.

```bash
kubectl -n exemplo-09 describe ingress k8s-in-action-ing
```

Mostra a classe, as regras, os backends associados e os eventos. É um dos primeiros comandos a executar se a rota não funcionar.

### Entender o caminho da requisição

O caminho esperado para uma solicitação HTTP é:

1. O navegador resolve `k8s-in-action.localhost` para o próprio computador.
2. A solicitação chega à porta 80 do host.
3. O `extraPortMappings` do `kind` encaminha a porta 80 do host para a porta 80 do nó `lab-control-plane`.
4. O Ingress Controller recebe a solicitação e avalia o host e o caminho.
5. A regra do Ingress encaminha a solicitação para o Service `k8s-in-action-app` na porta `3000`.
6. O Service encaminha o tráfego para um Pod pronto selecionado pelos seus rótulos.

O Ingress não substitui o Service: a regra de Ingress aponta para um Service, que por sua vez seleciona os Pods.

## 6. Testar o acesso à aplicação

### Acessar pelo navegador

Abra o endereço:

[http://k8s-in-action.localhost/](http://k8s-in-action.localhost/)

O host deve corresponder exatamente ao definido na regra do Ingress. O protocolo é HTTP porque este exemplo não configura uma regra TLS no recurso Ingress.

### Testar com curl

Em Windows, use `curl.exe` para evitar possíveis diferenças com aliases do PowerShell:

```bash
curl.exe -i http://k8s-in-action.localhost/
```

A opção `-i` exibe os cabeçalhos HTTP junto com o corpo da resposta.

Se houver dúvida sobre a resolução do nome, teste diretamente o loopback e envie o cabeçalho `Host` esperado pela regra do Ingress:

```bash
curl.exe -i -H "Host: k8s-in-action.localhost" http://127.0.0.1/
```

Esse teste contorna a resolução do nome no cliente, mas ainda depende de o encaminhamento da porta 80 e o Ingress Controller estarem funcionando.

Uma resposta HTTP de sucesso indica que a requisição chegou a um endpoint que a processou. Uma resposta `404` pode indicar host/caminho sem correspondência ou resposta do próprio aplicativo. Uma resposta `502` ou `503` normalmente justifica conferir o Service, os EndpointSlices e o estado dos Pods; a interpretação exata depende da origem da resposta.

## 7. Diagnóstico de problemas

### Conferir o estado dos recursos

```bash
kubectl get nodes -o wide
kubectl get pods -n ingress-nginx -o wide
kubectl get deployment ingress-nginx-controller -n ingress-nginx
kubectl get ingressclass
kubectl get pods -n exemplo-09 -o wide --show-labels
kubectl get services -n exemplo-09
kubectl get ingress -n exemplo-09
```

Esses comandos verificam a disponibilidade dos nós, do controlador, dos Pods da aplicação e dos recursos de roteamento.

### Examinar o Ingress e seu backend

```bash
kubectl describe ingress k8s-in-action-ing -n exemplo-09
kubectl describe service k8s-in-action-app -n exemplo-09
kubectl get endpointslices -n exemplo-09 -l kubernetes.io/service-name=k8s-in-action-app -o wide
```

Confirme se o Ingress referencia o Service e a porta corretos e se o Service possui endpoints aptos a receber tráfego.

### Consultar logs do controlador

```bash
kubectl logs deployment/ingress-nginx-controller -n ingress-nginx
```

Mostra os logs do controlador para investigar falhas ao interpretar regras ou encaminhar requisições.

### Problemas frequentes

| Sintoma | Verificações recomendadas |
| --- | --- |
| O navegador não consegue conectar a `localhost` | Confirme os `extraPortMappings` do cluster, a disponibilidade da porta 80 no host e o estado do Pod do controlador. |
| O cluster foi criado antes de editar `kind-config.yaml` | Recrie o cluster somente se puder perder os recursos locais; o encaminhamento de portas deve ser definido na criação. |
| O controlador está `Pending` | Confira se o nó `lab-control-plane` existe, se possui o rótulo `ingress-ready=true` e se o `nodeSelector` foi aplicado. |
| `kubectl get ingressclass` não mostra `nginx` | Verifique a conclusão da instalação e o estado do controlador antes de recriar o recurso Ingress. |
| O Ingress existe, mas retorna `404` | Confirme `ingressClassName`, o host `k8s-in-action.localhost`, o caminho `/` e a configuração observada nos logs do controlador. |
| O Service não tem endpoints | Confirme os rótulos dos Pods, o `selector` do Service, o namespace e se os Pods estão prontos. |
| O backend retorna erro ou não responde | Verifique `targetPort`, a porta de escuta da aplicação e os logs do Pod. |
| A aplicação funciona por outro caminho, mas não pelo Ingress | Teste o cabeçalho `Host`, revise a regra Ingress e confira se as portas de host estão encaminhadas corretamente. |

## Comandos úteis

| Comando | Finalidade |
| --- | --- |
| `kind get clusters` | Lista os clusters locais gerenciados pelo `kind`. |
| `kubectl config current-context` | Mostra o contexto atual do `kubectl`. |
| `kubectl cluster-info` | Verifica a comunicação com o cluster. |
| `kubectl get nodes -o wide` | Lista nós e informações de estado. |
| `kubectl label node lab-control-plane ingress-ready=true --overwrite` | Adiciona ou atualiza o rótulo do nó que receberá o controlador. |
| `kubectl get pods -n ingress-nginx -o wide` | Consulta os Pods do Ingress Controller. |
| `kubectl get ingressclass` | Lista as classes de Ingress disponíveis. |
| `kubectl create namespace exemplo-09` | Cria o namespace da aplicação. |
| `kubectl apply -f deployment.yaml` | Cria ou atualiza o Deployment da aplicação. |
| `kubectl -n exemplo-09 get deployment k8s-in-action-app` | Consulta o Deployment da aplicação. |
| `kubectl get pods -n exemplo-09 -o wide` | Lista os Pods e os nós em que estão executando. |
| `kubectl apply -f service.yaml` | Cria ou atualiza o Service. |
| `kubectl -n exemplo-09 get services` | Lista os Services do namespace. |
| `kubectl -n exemplo-09 get endpointslices -l kubernetes.io/service-name=k8s-in-action-app -o wide` | Verifica os endpoints associados ao Service. |
| `kubectl apply -f ingress.yaml` | Cria ou atualiza o recurso Ingress. |
| `kubectl -n exemplo-09 get ingress` | Lista os recursos Ingress do namespace. |
| `kubectl -n exemplo-09 describe ingress k8s-in-action-ing` | Exibe regras e eventos do Ingress. |
| `kubectl logs deployment/ingress-nginx-controller -n ingress-nginx` | Consulta os logs do controlador. |
| `curl.exe -i http://k8s-in-action.localhost/` | Testa o acesso HTTP à aplicação. |

## Encerrar e limpar o laboratório

Para remover somente os recursos da aplicação, execute os comandos a partir do diretório dos manifestos:

```bash
kubectl delete -f ingress.yaml
kubectl delete -f service.yaml
kubectl delete -f deployment.yaml
```

Esses comandos removem os recursos declarados nos respectivos arquivos. O namespace `exemplo-09` permanece criado.

Para excluir todo o namespace da aplicação e os recursos nele contidos:

```bash
kubectl delete namespace exemplo-09
```

> [!WARNING]
> A exclusão de um namespace remove os recursos que estão nele. Use esse comando apenas se tiver certeza de que não precisa manter a aplicação nem outros recursos desse namespace.

Para remover também o controlador instalado no namespace `ingress-nginx`, use o mesmo manifesto remoto empregado na instalação, substituindo `apply` por `delete`:

```bash
kubectl delete -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.15.1/deploy/static/provider/kind/deploy.yaml
```

Por fim, para excluir o cluster `kind` inteiro:

```bash
kind delete cluster --name lab
```

Esse comando remove o cluster local e os recursos que estiverem dentro dele. Ele não desinstala o `kind`, o `kubectl` nem o Docker Desktop da máquina.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [Kubernetes Documentation | Services](https://kubernetes.io/docs/concepts/services-networking/service/)
> - [Kubernetes Documentation | Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)
> - [Kubernetes Documentation | Namespaces](https://kubernetes.io/docs/concepts/overview/working-with-objects/namespaces/)
> - [Kubernetes Documentation | Ingress NGINX Retirement: What You Need to Know](https://kubernetes.io/blog/2025/11/11/ingress-nginx-retirement/)
> - [Ingress NGINX Controller | Repository arquivado](https://github.com/kubernetes/ingress-nginx)
> - [kind | Ingress](https://kind.sigs.k8s.io/docs/user/ingress/)
> - [kind | Configuration — Extra Port Mappings](https://kind.sigs.k8s.io/docs/user/configuration/#extra-port-mappings)
