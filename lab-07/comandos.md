# Redes e armazenamento no Kubernetes — Guia de comandos


## 1. Services

Um `Service` fornece um ponto de acesso estável para uma aplicação executada em um ou mais Pods. Normalmente, seu `selector` identifica os Pods que serão utilizados como endpoints. O Kubernetes representa esses endpoints em objetos `EndpointSlice`, que podem existir em mais de um objeto para o mesmo Service.

### Criar um Service `ClusterIP`

Exemplo de manifesto `service.yaml`:

```yaml
apiVersion: v1
kind: Service

metadata:
  name: web

spec:
  type: ClusterIP
  selector:
    app: web
  ports:
    - port: 80
      targetPort: 8080
```

- `port` é a porta disponibilizada pelo Service.
- `targetPort` é a porta utilizada no backend; pode ser numérico ou uma porta nomeada no Pod.
- `ClusterIP` é o tipo padrão quando `type` não é informado.

Aplique e consulte o Service:

```bash
kubectl apply -f service.yaml
kubectl get service web
kubectl get services
kubectl describe service web
kubectl get service web -o yaml
```

### Expor um Service com `NodePort`

O `NodePort` disponibiliza o Service em uma porta de cada Node. A faixa padrão é `30000-32767`, embora possa ser configurada no cluster.

```yaml
apiVersion: v1
kind: Service

metadata:
  name: web

spec:
  type: NodePort
  selector:
    app: web
  ports:
    - port: 80
      targetPort: 8080
      nodePort: 30080
```

Neste exemplo, a porta `30080` é a porta publicada no Node. A acessibilidade depende da rede e da configuração dos Nodes.

### Solicitar um balanceador externo com `LoadBalancer`

```yaml
apiVersion: v1
kind: Service

metadata:
  name: web

spec:
  type: LoadBalancer
  selector:
    app: web
  ports:
    - port: 80
      targetPort: 8080
```

O Kubernetes depende da infraestrutura ou de uma integração compatível para fornecer o balanceador externo; criar esse Service não garante, por si só, que um balanceador esteja disponível.

### Associar um Service a um nome DNS externo com `ExternalName`

```yaml
apiVersion: v1
kind: Service

metadata:
  name: database

spec:
  type: ExternalName
  externalName: db.example.com
```

O DNS do cluster retorna um registro `CNAME` apontando para `db.example.com`. Esse tipo não cria um IP virtual nem realiza proxy ou encaminhamento de tráfego.

### Criar um Headless Service

Um Headless Service não possui IP virtual do Service. O campo `clusterIP: None` permite que o DNS retorne diretamente os endereços dos Pods associados.

```yaml
apiVersion: v1
kind: Service

metadata:
  name: database

spec:
  clusterIP: None
  selector:
    app: database
  ports:
    - port: 5432
      targetPort: 5432
```

Esse padrão é especialmente útil quando os clientes precisam descobrir individualmente os Pods, por exemplo, em conjunto com um `StatefulSet`.

### Descobrir Services por DNS

O nome DNS completo segue o formato `<service-name>.<namespace>.svc.<cluster-domain>`. O domínio padrão normalmente é `cluster.local`, mas pode variar conforme a configuração do cluster.

Exemplo para um Service `web` no namespace `producao`:

```text
web
web.producao
web.producao.svc.cluster.local
```

Um Service normal costuma resolver para o `clusterIP`; um Headless Service pode resolver diretamente para os endereços dos Pods.

Quando a descoberta é feita por variáveis de ambiente, podem existir variáveis como:

```text
REDIS_PRIMARY_SERVICE_HOST
REDIS_PRIMARY_SERVICE_PORT
```

As variáveis dependem de o Service existir antes da criação do Pod cliente. A descoberta por DNS não possui essa dependência de ordem de criação.

### Consultar EndpointSlices e remover um Service

```bash
# Consultar todos os EndpointSlices
kubectl get endpointslices

# Consultar os EndpointSlices associados ao Service web
kubectl get endpointslices -l kubernetes.io/service-name=web

# Remover o Service
kubectl delete service web
```

Um Service com `selector` normalmente tem seus EndpointSlices criados automaticamente. Também é possível criar Services sem `selector` e gerenciar EndpointSlices para representar backends externos.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Service](https://kubernetes.io/docs/concepts/services-networking/service/)
> - [Kubernetes Documentation | DNS for Services and Pods](https://kubernetes.io/docs/concepts/services-networking/dns-pod-service/)
> - [Kubernetes Documentation | EndpointSlices](https://kubernetes.io/docs/concepts/services-networking/endpoint-slices/)

---

## 2. Ingress

O `Ingress` define regras para rotear tráfego HTTP/HTTPS externo para Services internos. O objeto contém a configuração de roteamento; é necessário um **Ingress Controller** em execução para processá-la.

### Criar regras de roteamento por host e caminho

Exemplo de manifesto `ingress.yaml` com um backend padrão e dois caminhos para o host `app.example.com`:

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress

metadata:
  name: app

spec:
  ingressClassName: nginx

  defaultBackend:
    service:
      name: default
      port:
        number: 80

  rules:
    - host: app.example.com
      http:
        paths:
          - path: /api
            pathType: Prefix
            backend:
              service:
                name: api
                port:
                  number: 80
          - path: /web
            pathType: Prefix
            backend:
              service:
                name: web
                port:
                  number: 80
```

Neste exemplo:

- `app.example.com/api` encaminha requisições para o Service `api`.
- `app.example.com/web` encaminha requisições para o Service `web`.
- `defaultBackend` pode receber requisições que não correspondem às regras, conforme o comportamento do controlador.
- Os Services `default`, `api` e `web` precisam existir e disponibilizar as portas indicadas para que os backends funcionem.

### Tipos de caminho (`pathType`)

```yaml
# Correspondência exata
path: /api
pathType: Exact
```

`Exact` corresponde a `/api`, mas não a `/api/users` ou `/api/v2`.

```yaml
# Correspondência por prefixo de caminho
path: /api
pathType: Prefix
```

`Prefix` pode corresponder a `/api`, `/api/` e `/api/users`, mas não a `/apiv2`. `ImplementationSpecific` delega os detalhes de correspondência ao Ingress Controller e pode reduzir a portabilidade entre implementações.

Quando mais de uma regra corresponde à requisição, o caminho correspondente mais longo tem precedência; em caso de empate entre `Exact` e `Prefix`, `Exact` tem precedência.

### Definir uma `IngressClass`

A `IngressClass` identifica o controlador responsável por processar os objetos `Ingress` que a referenciam.

```yaml
apiVersion: networking.k8s.io/v1
kind: IngressClass

metadata:
  name: nginx

spec:
  controller: k8s.io/ingress-nginx
```

O Ingress referencia a classe por meio de:

```yaml
spec:
  ingressClassName: nginx
```

Uma classe pode ser definida como padrão com a seguinte anotação:

```yaml
metadata:
  annotations:
    ingressclass.kubernetes.io/is-default-class: "true"
```

No máximo uma `IngressClass` deve ser definida como padrão para evitar ambiguidades.

### Configurar TLS

O Secret referenciado deve conter o certificado e a chave privada utilizados pelo Ingress Controller.

```yaml
spec:
  tls:
    - hosts:
        - app.example.com
      secretName: app-tls
```

### Aplicar, consultar e remover um Ingress

```bash
# Criar ou atualizar o Ingress
kubectl apply -f ingress.yaml

# Consultar Ingresses e IngressClass
kubectl get ingress app
kubectl get ingresses
kubectl get ingressclass

# Examinar detalhes e YAML
kubectl describe ingress app
kubectl get ingress app -o yaml

# Remover o Ingress
kubectl delete ingress app
```

> [!NOTE]
> O material da aula informa que a API `Ingress` está congelada: é estável e não está planejada para remoção, mas não recebe novos recursos da API. Para novos cenários, a aula recomenda considerar o Gateway API. A implementação e os recursos disponíveis dependem do controlador instalado.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Ingress](https://kubernetes.io/docs/concepts/services-networking/ingress/)
> - [Kubernetes Documentation | Ingress Controllers](https://kubernetes.io/docs/concepts/services-networking/ingress-controllers/)

---

## 3. Gateway API

O `Gateway API` é um conjunto de APIs extensíveis para gerenciamento de infraestrutura e roteamento de tráfego. A aula destaca a separação de responsabilidades entre `GatewayClass`, `Gateway` e recursos de rota. A utilização depende de uma implementação compatível e das CRDs correspondentes instaladas no cluster.

### Recursos principais

| Recurso | Finalidade |
| --- | --- |
| `GatewayClass` | Define a classe de Gateways e o controlador responsável por implementá-la. |
| `Gateway` | Representa uma instância da infraestrutura de tratamento de tráfego. |
| `HTTPRoute` | Define regras de roteamento HTTP para um Gateway. |
| `GRPCRoute` | Define regras de roteamento gRPC para um Gateway. |

O fluxo conceitual é `GatewayClass` → `Gateway` → `HTTPRoute`/`GRPCRoute` → `Service` → Pods.

### Consultar e gerenciar recursos do Gateway API

Os comandos da aula usam os nomes `example-gateway` e `example-route` como exemplos:

```bash
# Consultar classes e gateways
kubectl get gatewayclasses
kubectl get gateways
kubectl get gateway example-gateway

# Consultar rotas
kubectl get httproutes
kubectl get grpcroutes

# Aplicar manifestos
kubectl apply -f gateway.yaml
kubectl apply -f httproute.yaml

# Inspecionar recursos
kubectl describe gateway example-gateway
kubectl describe httproute example-route
kubectl get gateway example-gateway -o yaml
kubectl get httproute example-route -o yaml

# Remover recursos
kubectl delete gateway example-gateway
kubectl delete httproute example-route
```

> [!NOTE]
> A aula fornece os comandos de operação acima, mas não apresenta um manifesto YAML completo de `Gateway` ou `HTTPRoute`. Os arquivos `gateway.yaml` e `httproute.yaml` devem conter recursos compatíveis com a implementação Gateway API instalada no cluster.

### Mapear conceitos de Ingress para Gateway API

A migração não é apenas uma troca de `kind`; é necessário converter as regras e verificar as extensões específicas do controlador atual.

| Ingress | Gateway API |
| --- | --- |
| `IngressClass` | `GatewayClass` |
| `Ingress` | `Gateway` + `HTTPRoute` |
| `host` | `hostnames` |
| `paths` | `matches` |
| `pathType: Prefix` | `PathPrefix` |
| backend Service | `backendRefs` |
| entrada HTTP/HTTPS | `listeners` |
| `defaultBackend` | regra explícita de roteamento |

O projeto `ingress2gateway` pode auxiliar na conversão, mas a conversão automática não substitui a análise de funcionalidades específicas utilizadas pelo Ingress Controller.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Gateway API](https://kubernetes.io/docs/concepts/services-networking/gateway/)
> - [Kubernetes Documentation | Getting started with Gateway API](https://gateway-api.sigs.k8s.io/guides/getting-started/introduction/)
> - [Kubernetes Documentation | Migrating from Ingress](https://gateway-api.sigs.k8s.io/guides/getting-started/migrating-from-ingress/)

---

## 4. ConfigMaps

Um `ConfigMap` armazena dados de configuração não confidenciais em pares chave-valor. Os dados podem ser usados como variáveis de ambiente, arquivos em volume, argumentos do container ou consultados pela própria aplicação por meio da API. O tamanho dos dados de um ConfigMap não pode exceder 1 MiB.

### Criar um ConfigMap por manifesto

Exemplo de manifesto `configmap.yaml`:

```yaml
apiVersion: v1
kind: ConfigMap

metadata:
  name: app-config

data:
  database_host: "db.example.com"
  database_port: "5432"
  app.properties: |
    server.port=8080
    log.level=INFO
```

Aplique e consulte o recurso:

```bash
kubectl apply -f configmap.yaml
kubectl get configmap app-config
kubectl get configmaps
kubectl describe configmap app-config
kubectl get configmap app-config -o yaml
```

### Criar um ConfigMap por comando

```bash
# Usar valores literais
kubectl create configmap app-config \
  --from-literal=database_host=db.example.com \
  --from-literal=database_port=5432

# Criar a partir de um arquivo
kubectl create configmap app-config \
  --from-file=app.properties

# Criar a partir de um diretório
kubectl create configmap app-config \
  --from-file=config/
```

### Disponibilizar os dados como variáveis de ambiente

Para importar todas as chaves de um ConfigMap, use `envFrom`:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  containers:
    - name: app
      image: busybox:latest
      command: ["/bin/sh", "-c", "printenv"]
      envFrom:
        - configMapRef:
            name: app-config
```

Para selecionar apenas uma chave e definir o nome da variável de ambiente:

```yaml
env:
  - name: DATABASE_HOST
    valueFrom:
      configMapKeyRef:
        name: app-config
        key: database_host
```

As variáveis de ambiente já definidas em um container não são atualizadas automaticamente quando o ConfigMap muda; para refletir a alteração, é necessário reiniciar o Pod.

### Marcar o ConfigMap como imutável

```yaml
apiVersion: v1
kind: ConfigMap

metadata:
  name: app-config

immutable: true

data:
  database_host: "db.example.com"
  log_level: "INFO"
```

Depois de marcado como imutável, o ConfigMap não pode voltar a ser mutável e seus dados não podem ser alterados. Para modificar a configuração, exclua e recrie o objeto ou crie outro ConfigMap.

### Remover um ConfigMap

```bash
kubectl delete configmap app-config
```

> [!WARNING]
> Não armazene senhas, tokens ou chaves em ConfigMaps. Eles não foram projetados para fornecer confidencialidade ou criptografia.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | ConfigMaps](https://kubernetes.io/docs/concepts/configuration/configmap/)
> - [Kubernetes Documentation | Configure a Pod to Use a ConfigMap](https://kubernetes.io/docs/tasks/configure-pod-container/configure-pod-configmap/)

---

## 5. Secrets

Um `Secret` armazena informações sensíveis, como senhas, tokens, chaves e credenciais. Pode fornecer valores para variáveis de ambiente, arquivos em volumes ou credenciais para que o `kubelet` obtenha imagens de um registry privado. O tamanho de um Secret é limitado a 1 MiB.

### Criar um Secret declarativamente

Exemplo de manifesto `secret.yaml`:

```yaml
apiVersion: v1
kind: Secret

metadata:
  name: app-credentials

type: Opaque

stringData:
  username: "admin"
  password: "S3nh@F0rte"
```

`stringData` permite informar valores como strings; o Kubernetes os converte para o formato usado em `data`. Em `data`, os valores são codificados em Base64, mas Base64 **não é criptografia**.

```bash
kubectl apply -f secret.yaml
kubectl get secret app-credentials
kubectl get secrets
kubectl describe secret app-credentials
kubectl get secret app-credentials -o yaml
```

### Criar Secrets por comando

```bash
# Valores literais
kubectl create secret generic app-credentials \
  --from-literal=username=admin \
  --from-literal=password='S3nh@F0rte'

# A partir de um arquivo
kubectl create secret generic app-secret \
  --from-file=credentials.txt

# A partir de um arquivo de ambiente
kubectl create secret generic app-credentials \
  --from-env-file=.env.secret

# Credenciais de registry privado
kubectl create secret docker-registry regcred \
  --docker-server=registry.example.com \
  --docker-username=usuario \
  --docker-password='senha'

# Secret TLS
kubectl create secret tls app-tls \
  --cert=tls.crt \
  --key=tls.key
```

> [!NOTE]
> No material original, o exemplo de arquivo de ambiente aparece como `--from-env-file=.env secret`, enquanto a tabela de comandos utiliza `--from-env-file=.env.secret`. Neste guia foi utilizado `.env.secret`, conforme a tabela; substitua pelo nome real do arquivo utilizado no exercício.

### Injetar uma chave do Secret em variável de ambiente

```yaml
env:
  - name: DATABASE_PASSWORD
    valueFrom:
      secretKeyRef:
        name: app-credentials
        key: password
```

Para importar todas as chaves do Secret:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: envfrom-secret

spec:
  containers:
    - name: envars-test-container
      image: nginx
      envFrom:
        - secretRef:
            name: test-secret
```

Secrets usados como variáveis de ambiente não atualizam automaticamente os valores já presentes no ambiente de um container; é necessário reiniciar o Pod.

### Usar um Secret para baixar imagem privada

O Secret de registry deve estar no mesmo namespace do Pod.

```yaml
spec:
  containers:
    - name: app
      image: registry.example.com/equipe/app:1.0
  imagePullSecrets:
    - name: regcred
```

`imagePullSecrets` fornece credenciais ao mecanismo que obtém a imagem; não entrega o conteúdo do Secret à aplicação.

### Tornar um Secret imutável

```yaml
apiVersion: v1
kind: Secret

metadata:
  name: app-credentials

type: Opaque
immutable: true
stringData:
  username: "admin"
  password: "S3nh@F0rte"
```

Depois de definido como imutável, o conteúdo não pode ser alterado e o objeto não pode voltar a ser mutável. Para atualizar os dados, exclua e recrie o Secret ou crie outro.

### Remover um Secret

```bash
kubectl delete secret app-credentials
```

> [!WARNING]
> Evite informar credenciais diretamente na linha de comando quando isso puder expô-las no histórico do shell ou a outros usuários. A saída de `kubectl get secret -o yaml` mostra `data` em Base64, que pode ser facilmente decodificado. Para proteger Secrets, a aula recomenda Encryption at Rest, RBAC com menor privilégio, acesso limitado aos containers que precisam dos dados e consideração de provedores externos de Secrets.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Secrets](https://kubernetes.io/docs/concepts/configuration/secret/)
> - [Kubernetes Documentation | Distribute Credentials Securely Using Secrets](https://kubernetes.io/docs/tasks/inject-data-application/distribute-credentials-secure/)
> - [Kubernetes Documentation | Pull an Image from a Private Registry](https://kubernetes.io/docs/tasks/configure-pod-container/pull-image-private-registry/)

---

## 6. CSI — Container Storage Interface

O `CSI` é uma interface padronizada que permite integrar sistemas de armazenamento ao Kubernetes por meio de drivers independentes do código principal. Conforme o driver, pode oferecer operações de provisionamento, remoção, anexação, montagem, expansão, snapshots e clonagem.

A aula não apresenta comandos de terminal específicos para instalar ou administrar um driver CSI; a utilização concreta depende do driver compatível instalado no cluster e da `StorageClass` que o referencia.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes CSI Documentation](https://kubernetes-csi.github.io/docs/)

---

## 7. Volumes

Um volume é declarado no nível do Pod por `spec.volumes`. Cada container que o utiliza declara sua montagem em `volumeMounts`, indicando o nome do volume e o caminho no sistema de arquivos do container.

### Exemplo básico com `emptyDir`

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: dados
      emptyDir: {}

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: dados
          mountPath: /dados
```

Neste exemplo, o volume `dados` é disponibilizado no container em `/dados`. `emptyDir` é temporário: os dados são removidos quando o Pod é removido do Node, mas permanecem disponíveis se apenas um container falhar e reiniciar enquanto o Pod continua existindo.

### Compartilhar um volume entre containers do mesmo Pod

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: compartilhado
      emptyDir: {}

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: compartilhado
          mountPath: /dados

    - name: sidecar
      image: busybox:1.36
      volumeMounts:
        - name: compartilhado
          mountPath: /dados
```

Os dois containers acessam o mesmo volume no caminho `/dados`.

Também é possível utilizar memória para `emptyDir`:

```yaml
emptyDir:
  medium: Memory
```

Nesse caso, o Kubernetes utiliza `tmpfs`.

### Disponibilizar um ConfigMap como arquivo

```yaml
apiVersion: v1
kind: ConfigMap

metadata:
  name: app-config

data:
  app.conf: |
    server.port=8080
    log.level=INFO

---
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: config
      configMap:
        name: app-config

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: config
          mountPath: /etc/app
```

O conteúdo fica disponível no container como `/etc/app/app.conf`. O ConfigMap deve existir antes de ser consumido. Montagens normais podem refletir atualizações do ConfigMap; quando se utiliza `subPath`, as atualizações posteriores não são propagadas para essa montagem.

### Disponibilizar um Secret como arquivos

```yaml
apiVersion: v1
kind: Secret

metadata:
  name: database-secret

type: Opaque
stringData:
  username: app
  password: senha-secreta

---
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: credentials
      secret:
        secretName: database-secret

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: credentials
          mountPath: /etc/credentials
          readOnly: true
```

Os arquivos são disponibilizados em `/etc/credentials/username` e `/etc/credentials/password`. O volume é somente leitura e seus dados são mantidos em `tmpfs`. Ainda é necessário proteger o próprio Secret por meio de controles de acesso.

### Montar somente subdiretórios usando `subPath`

Exemplo de montagem de duas partes de um PVC:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: dados
      persistentVolumeClaim:
        claimName: dados-app

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: dados
          mountPath: /var/www/html
          subPath: html
        - name: dados
          mountPath: /var/log/app
          subPath: logs
```

O mesmo volume pode disponibilizar `html` em `/var/www/html` e `logs` em `/var/log/app`. ConfigMaps, Secrets e Downward API não propagam suas atualizações posteriores para montagens feitas com `subPath`.

### Montar um diretório do Node com `hostPath`

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: log-reader

spec:
  volumes:
    - name: logs
      hostPath:
        path: /var/log
        type: Directory

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: logs
          mountPath: /host-logs
          readOnly: true
```

O tipo `Directory` exige que o diretório exista no Node. Outros valores descritos na aula incluem `DirectoryOrCreate`, `File`, `FileOrCreate`, `Socket`, `CharDevice` e `BlockDevice`.

> [!WARNING]
> `hostPath` dá acesso direto a um caminho do sistema de arquivos do Node e pode expor arquivos sensíveis ou interfaces privilegiadas. Além disso, dois Pods executados em Nodes diferentes podem ver conteúdos diferentes. Utilize-o somente quando apropriado e com permissões restritas.

### Montar um compartilhamento NFS

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  volumes:
    - name: dados
      nfs:
        server: nfs.example.com
        path: /export/kubernetes
        readOnly: false

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: dados
          mountPath: /dados
```

O servidor NFS e o compartilhamento exportado precisam existir antes de o volume ser utilizado. Múltiplos Pods podem utilizar o compartilhamento, conforme o suporte e a configuração do servidor.

### Montar uma imagem como volume usando `image`

O volume `image` disponibiliza os arquivos de uma imagem ou artefato OCI em modo somente leitura, sem executá-lo como container.

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: image-volume

spec:
  containers:
    - name: app
      image: debian:12
      command:
        - sleep
        - infinity
      volumeMounts:
        - name: app-files
          mountPath: /opt/files

  volumes:
    - name: app-files
      image:
        reference: quay.io/example/app-data:v1
        pullPolicy: IfNotPresent
```

O conteúdo do artefato é disponibilizado em `/opt/files`. Segundo a aula, o tipo de volume `image` tornou-se estável no Kubernetes v1.36; verifique a versão do cluster antes de utilizar esse recurso.

### Combinar fontes com `projected`

Um volume `projected` combina fontes diferentes em um único diretório, como `configMap`, `secret` e `downwardAPI`.

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: projected-volume
  labels:
    app: web

spec:
  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: config
          mountPath: /etc/app
          readOnly: true

  volumes:
    - name: config
      projected:
        sources:
          - configMap:
              name: app-config
          - secret:
              name: app-secret
          - downwardAPI:
              items:
                - path: pod-name
                  fieldRef:
                    fieldPath: metadata.name
```

O diretório `/etc/app` contém arquivos projetados a partir das fontes especificadas, incluindo `pod-name`.

### Disponibilizar metadados do Pod usando `downwardAPI`

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: web
  labels:
    app: web
    ambiente: producao

spec:
  volumes:
    - name: pod-info
      downwardAPI:
        items:
          - path: name
            fieldRef:
              fieldPath: metadata.name
          - path: namespace
            fieldRef:
              fieldPath: metadata.namespace
          - path: labels
            fieldRef:
              fieldPath: metadata.labels

  containers:
    - name: app
      image: nginx:1.27
      volumeMounts:
        - name: pod-info
          mountPath: /etc/pod-info
          readOnly: true
```

Os arquivos ficam em `/etc/pod-info/name`, `/etc/pod-info/namespace` e `/etc/pod-info/labels`. Esse mecanismo permite obter informações do Pod sem consultar diretamente a API do Kubernetes. Alterações não são refletidas em uma montagem feita com `subPath`.

### Volume local definido em um PersistentVolume

Um volume `local` representa armazenamento conectado a um Node. A afinidade do PV informa ao agendador em qual Node o armazenamento está disponível.

```yaml
apiVersion: v1
kind: PersistentVolume

metadata:
  name: local-pv

spec:
  capacity:
    storage: 100Gi
  volumeMode: Filesystem
  accessModes:
    - ReadWriteOnce
  persistentVolumeReclaimPolicy: Retain
  storageClassName: local-storage
  local:
    path: /mnt/disks/ssd1
  nodeAffinity:
    required:
      nodeSelectorTerms:
        - matchExpressions:
            - key: kubernetes.io/hostname
              operator: In
              values:
                - worker-01
```

O exemplo declara um PV de 100 GiB em `/mnt/disks/ssd1`, disponível somente no `worker-01`. A aula destaca que volumes locais não possuem provisionamento dinâmico nativo e recomenda uma StorageClass com `volumeBindingMode: WaitForFirstConsumer`.

> [!WARNING]
> Um volume `local` depende da disponibilidade do Node que contém o armazenamento. Se o Node ficar indisponível, o volume também pode ficar inacessível.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Volumes](https://kubernetes.io/docs/concepts/storage/volumes/)

---

## 8. PersistentVolume (PV)

Um `PersistentVolume` representa um recurso de armazenamento do cluster. Pode ser criado estaticamente por um administrador ou provisionado dinamicamente por uma `StorageClass`. Seu ciclo de vida é independente do ciclo de vida dos Pods que o utilizam.

### Criar um PV NFS

Exemplo de manifesto `pv.yaml`:

```yaml
apiVersion: v1
kind: PersistentVolume

metadata:
  name: pv-nfs

spec:
  capacity:
    storage: 10Gi
  volumeMode: Filesystem
  accessModes:
    - ReadWriteMany
  persistentVolumeReclaimPolicy: Retain
  storageClassName: nfs-manual
  nfs:
    server: nfs.example.com
    path: /export/kubernetes
```

Esse PV representa 10 GiB de armazenamento NFS. Ele pode ser associado a um PVC compatível.

### Modos de acesso

| Modo | Descrição |
| --- | --- |
| `ReadWriteOnce` (`RWO`) | Pode ser montado como leitura e escrita por um único Node. |
| `ReadOnlyMany` (`ROX`) | Pode ser montado como somente leitura por múltiplos Nodes. |
| `ReadWriteMany` (`RWX`) | Pode ser montado como leitura e escrita por múltiplos Nodes. |
| `ReadWriteOncePod` (`RWOP`) | Pode ser montado como leitura e escrita por um único Pod; é suportado por volumes CSI. |

Os modos de acesso devem refletir as capacidades efetivas do volume. `ReadWriteOnce` não significa necessariamente um único Pod: dependendo do armazenamento, vários Pods no mesmo Node podem compartilhar o volume.

### Consultar e remover PVs

```bash
# Criar ou atualizar
kubectl apply -f pv.yaml

# Consultar um PV e todos os PVs
kubectl get pv pv-data
kubectl get persistentvolumes

# Inspecionar detalhes e YAML
kubectl describe pv pv-data
kubectl get pv pv-data -o yaml
kubectl get pv -o wide

# Filtrar PVs associados a PVCs (fase Bound)
kubectl get pv --field-selector status.phase=Bound

# Remover um PV
kubectl delete pv pv-data
```

Os comandos que usam `pv-data` são exemplos de consulta; para operar o manifesto desta seção, substitua pelo nome real do PV (`pv-nfs`).

### Fases do PV

| Fase | Descrição |
| --- | --- |
| `Available` | PV disponível, ainda não associado a um PVC. |
| `Bound` | PV associado a um PVC. |
| `Released` | O PVC associado foi removido, mas o armazenamento ainda não foi recuperado. |
| `Failed` | Ocorreu falha no processo automatizado de recuperação. |

### Política de recuperação

O campo `persistentVolumeReclaimPolicy` define o comportamento do PV quando ele deixa de estar associado ao PVC:

- `Retain`: mantém o PV e o armazenamento para recuperação manual; o PV pode permanecer em `Released` e os dados não são removidos automaticamente.
- `Delete`: remove o PV e, quando suportado pelo driver ou provisionador, o armazenamento associado. É o padrão comum para PVs provisionados dinamicamente quando não há outra política configurada.
- `Recycle`: política depreciada, não recomendada para novos ambientes.

> [!WARNING]
> `Delete` não garante que todo armazenamento físico ou externo será removido em qualquer cenário; isso depende do suporte do driver ou provisionador. Verifique a política antes de excluir recursos que contêm dados necessários.

---

## 9. PersistentVolumeClaim (PVC)

Um `PersistentVolumeClaim` representa uma solicitação de armazenamento feita por uma carga de trabalho. A aplicação solicita capacidade, modo de acesso e, quando necessário, classe de armazenamento; o Kubernetes procura um PV compatível ou solicita o provisionamento por meio de uma `StorageClass`.

### Criar um PVC

Exemplo de manifesto `pvc.yaml`:

```yaml
apiVersion: v1
kind: PersistentVolumeClaim

metadata:
  name: dados-app

spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 10Gi
  storageClassName: fast
```

O PVC solicita 10 GiB, modo de acesso `ReadWriteOnce` e a StorageClass `fast`. O Kubernetes procura um PV compatível ou solicita que o provisionador dessa classe crie um volume.

### Consultar o estado do PVC

Os comandos abaixo são formas operacionais complementares para inspecionar o exemplo de PVC:

```bash
kubectl apply -f pvc.yaml
kubectl get pvc
kubectl get pvc dados-app
kubectl describe pvc dados-app
kubectl get pvc dados-app -o yaml
```

O PVC pode estar em `Pending` (ainda sem PV associado), `Bound` (associado a um PV) ou `Lost` (perdeu o volume subjacente). Um PVC pode permanecer `Pending` se não houver PV compatível, se a StorageClass não provisionar armazenamento ou se os requisitos/topologia não puderem ser atendidos.

---

## 10. StorageClass e provisionamento

Uma `StorageClass` descreve uma classe de armazenamento oferecida pelo cluster. Pode representar diferentes tecnologias, desempenho, topologia, políticas de recuperação e parâmetros do provedor. É um recurso de escopo do cluster e é utilizada principalmente no provisionamento dinâmico.

### Exemplo de StorageClass

```yaml
apiVersion: storage.k8s.io/v1
kind: StorageClass

metadata:
  name: fast

provisioner: csi.example.com
reclaimPolicy: Delete
allowVolumeExpansion: true
volumeBindingMode: WaitForFirstConsumer
```

- `provisioner` identifica o provisionador e é obrigatório.
- `reclaimPolicy` define a política de recuperação dos PVs provisionados; quando não especificada, o padrão é `Delete`.
- `allowVolumeExpansion` indica se a expansão de volumes é permitida quando suportada.
- `volumeBindingMode` determina quando a associação/provisionamento ocorre; o padrão é `Immediate`.
- `WaitForFirstConsumer` aguarda a existência de um Pod consumidor para considerar a topologia antes de associar ou provisionar o volume.

Uma StorageClass pode ser marcada como padrão por meio da anotação:

```yaml
metadata:
  annotations:
    storageclass.kubernetes.io/is-default-class: "true"
```

É recomendável manter apenas uma StorageClass padrão para evitar ambiguidades. Um PVC sem `storageClassName` pode utilizar a classe padrão, se houver.

### Provisionamento estático e dinâmico

- **Estático:** o administrador cria os PVs antes de serem solicitados pelos workloads e configura os detalhes do armazenamento.
- **Dinâmico:** o PV é criado automaticamente por meio do provisionador indicado pela StorageClass, a partir da especificação do PVC. É necessário que o provisionador esteja configurado e disponível.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | StorageClass](https://kubernetes.io/docs/reference/kubernetes-api/storage/storage-class-v1/)

---

## 11. Consumir um PVC em um Pod ou Deployment

Um Pod e o PVC que ele referencia precisam estar no mesmo namespace. O PVC é declarado em `spec.volumes`, e o container monta o volume em `volumeMounts`.

### Consumir um PVC em um Pod

Exemplo de manifesto `pod-pvc.yaml`:

```yaml
apiVersion: v1
kind: Pod

metadata:
  name: app

spec:
  containers:
    - name: app
      image: nginx:latest
      volumeMounts:
        - name: app-storage
          mountPath: /usr/share/nginx/html

  volumes:
    - name: app-storage
      persistentVolumeClaim:
        claimName: app-data
```

Nesse exemplo, o volume do container é montado em `/usr/share/nginx/html` e utiliza o PVC `app-data`.

### Consumir um PVC em um Deployment

```yaml
apiVersion: apps/v1
kind: Deployment

metadata:
  name: app

spec:
  replicas: 1
  selector:
    matchLabels:
      app: app
  template:
    metadata:
      labels:
        app: app
    spec:
      containers:
        - name: app
          image: nginx:latest
          volumeMounts:
            - name: app-storage
              mountPath: /data
      volumes:
        - name: app-storage
          persistentVolumeClaim:
            claimName: app-data
```

Comandos complementares para aplicar e verificar o workload e o PVC:

```bash
kubectl apply -f pod-pvc.yaml
kubectl get pods
kubectl describe pod app
kubectl apply -f deployment-pvc.yaml
kubectl get deployment app
kubectl get pods -l app=app -o wide
kubectl get pvc app-data
```

Um mesmo PVC pode ser utilizado por mais de um Pod quando o modo de acesso e o armazenamento permitem. Para montar o volume como somente leitura, use `readOnly: true` no respectivo `volumeMounts`.

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)

> [!NOTE]
> **Para saber mais**
> - [Kubernetes Documentation | Persistent Volumes](https://kubernetes.io/docs/concepts/storage/persistent-volumes/)
