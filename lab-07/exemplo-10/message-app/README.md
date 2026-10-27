# Aplicação Message em Kubernetes

Este diretório contém manifestos Kubernetes derivados do `compose.yaml` da aplicação PHP/Apache e MariaDB.

## Recursos incluídos

- `Namespace`: `message-app` para os recursos namespaced.
- `ConfigMap`: configurações de conexão da aplicação.
- `Secret`: credenciais de demonstração.
- `PersistentVolume` e `PersistentVolumeClaim`: armazenamento do MariaDB.
- `Service` headless e `StatefulSet`: identidade de rede estável e execução do banco.
- `Deployment` e `Service` `ClusterIP`: aplicação PHP/Apache.
- `Ingress`: entrada HTTP pelo host `message.local`.

## Pré-requisitos

- Cluster Kubernetes funcional e `kubectl` configurado para o contexto correto.
- Um Ingress Controller instalado. O manifesto usa a classe `nginx`; altere `spec.ingressClassName` se o cluster utilizar outra classe.
- Imagem `message-server:compose` disponível para os nós que executam a aplicação.

## Construir/disponibilizar a imagem da aplicação

Na raiz do projeto onde se encontra o Dockerfile:

```bash
docker build -t message-server:compose .
```

A imagem precisa estar acessível no cluster. Para clusters locais, importe-a para o runtime do cluster, por exemplo:

```bash
# kind (substitua NOME_DO_CLUSTER)
kind load docker-image message-server:compose --name NOME_DO_CLUSTER

# Minikube
minikube image load message-server:compose
```

Para um cluster remoto, publique a imagem em um registry acessível pelos nós e atualize `image` em `07-message-server-deployment.yaml` (ou no manifesto consolidado) com a referência do registry.

## Aplicar os manifestos

Aplique o diretório de manifestos:

```bash
kubectl apply -f k8s-message-app/
```

Ou aplique o manifesto consolidado, se estiver no diretório pai:

```bash
kubectl apply -f k8s-message-app.yaml
```

## Validar a implantação

```bash
# Recursos do namespace
kubectl get all -n message-app

# ConfigMap, Secret e PVC
kubectl get configmap,secret,pvc -n message-app

# PV é um recurso de escopo do cluster, portanto não usa namespace
kubectl get pv message-mariadb-pv

# Estado do banco e logs
kubectl get pods -n message-app -o wide
kubectl logs -n message-app statefulset/message-mariadb

# Estado da aplicação e logs
kubectl rollout status deployment/message-server -n message-app
kubectl logs -n message-app deployment/message-server

# Service e Ingress
kubectl get svc,ingress -n message-app
kubectl describe ingress message-server -n message-app
```

A aplicação preserva a rota usada no exemplo original, `http://<ENDERECO_DO_INGRESS>/message.php`. O host configurado no Ingress é `message.local`. Configure a resolução desse nome para o endereço do Ingress (por exemplo, no arquivo `hosts` do sistema operacional) ou teste com `curl` usando um cabeçalho `Host` adequado.

```bash
curl -H 'Host: message.local' http://<ENDERECO_DO_INGRESS>/message.php
```

Substitua `<ENDERECO_DO_INGRESS>` pelo endereço acessível do Ingress Controller. Em clusters locais, esse endereço depende da configuração do ambiente.

## Limitações e cuidados

- **PersistentVolume é cluster-scoped:** não pode receber `metadata.namespace`. Os demais recursos namespaced estão no namespace `message-app`.
- O `PersistentVolume` usa `hostPath` no caminho `/var/lib/kubernetes/message-app/mariadb`. Isso serve para laboratório ou cluster local simples; o diretório é criado no nó Kubernetes, não necessariamente na máquina de onde o `kubectl` é executado. Para produção, prefira uma StorageClass e um provisionador de armazenamento apropriado.
- O `persistentVolumeReclaimPolicy` está como `Retain`, para evitar descarte automático dos dados quando o claim for removido.
- O `Secret` contém credenciais `message` apenas para demonstração. `stringData` deixa os valores legíveis no YAML; não versione credenciais reais. Kubernetes Secret não é, por si só, garantia de criptografia em repouso.
- Para manter compatibilidade com o Compose de origem, a aplicação conecta-se ao MariaDB como `root`. Em produção, crie um usuário dedicado com privilégios mínimos.
- A imagem do banco segue `mariadb:latest`, como no Compose de origem. Para implantação reproduzível, fixe uma versão testada antes de usar em ambientes compartilhados.
- A Ingress API requer um Ingress Controller; criar o recurso `Ingress` sozinho não instala o controlador.

## Remover os recursos

Remova os recursos namespaced:

```bash
kubectl delete namespace message-app
```

Como o PV é cluster-scoped e tem política `Retain`, remova seu objeto explicitamente somente quando apropriado:

```bash
kubectl delete pv message-mariadb-pv
```

A exclusão do objeto PV não apaga automaticamente os arquivos em `/var/lib/kubernetes/message-app/mariadb` no nó. Apague esses dados manualmente no nó apenas se tiver certeza de que não são mais necessários.
