# Exemplo — Coletando logs de Pods e eventos Kubernetes com Grafana Loki no kind

## Objetivo do exemplo

Este laboratório instala **Grafana Loki**, **Grafana** e o chart **Kubernetes Monitoring** em um cluster Kubernetes local criado com **kind**. O Grafana Alloy coleta logs dos Pods e eventos Kubernetes nas namespaces `meta` e `prod`, encaminha os registros para o Loki e permite consultá-los pelo Grafana.

Ao concluir o exemplo, você terá praticado:

- criação de um cluster kind com três nós;
- instalação de charts Helm do Grafana;
- armazenamento e consulta de logs com Loki;
- coleta de logs de Pods e eventos do cluster com Grafana Alloy;
- acesso ao Grafana e à interface web do Alloy por `kubectl port-forward`;
- diagnóstico de workloads e consulta de logs coletados.

## Arquitetura

```text
Pods e eventos Kubernetes
          |
          v
Kubernetes Monitoring Helm chart
  +---------------------------+
  | Alloy                     |
  | - alloy-logs              | ---> logs de Pods
  | - alloy-singleton         | ---> eventos Kubernetes
  +---------------------------+
          |
          | HTTP interno do cluster
          v
   Loki monolítico
   armazenamento filesystem
          ^
          | consulta
          v
       Grafana
          |
          +-- kubectl port-forward --> http://localhost:3000

Interface Alloy:
kubectl port-forward ----------------> http://localhost:12345
```

> [!IMPORTANT]
> A configuração é voltada a um **laboratório local**. Loki é instalado em modo monolítico e utiliza o armazenamento `filesystem`, sem depender do subchart ou das imagens do MinIO. O armazenamento em filesystem é adequado para desenvolvimento, prova de conceito e baixo volume; não é recomendado para produção. Para ambientes reais, configure um backend de armazenamento de objetos suportado e avalie a arquitetura recomendada para o volume de logs esperado.

## Preparação do ambiente

1. Inicie o Docker Desktop ou outro runtime de containers compatível com kind.
2. Instale e verifique `kind`, `kubectl`, `Helm` v3 ou superior e `git`.
3. Execute os comandos em um terminal Bash, como Git Bash ou Linux/WSL. No PowerShell, a sintaxe de variáveis e os comandos com heredoc podem exigir adaptação.
4. Coloque o arquivo de configuração anexado na pasta do laboratório e renomeie `kind-config(4).yaml` para `kind-config.yaml`.
5. Confirme que as portas `80` e `443` estão disponíveis no host, pois o arquivo `kind-config.yaml` faz o encaminhamento dessas portas para o nó `control-plane`. Este tutorial acessa Grafana e Alloy por `kubectl port-forward`, então os encaminhamentos `80` e `443` não são usados pelo próprio exemplo.
6. Reserve recursos suficientes para os containers do kind. Como referência para este laboratório, aloque aproximadamente **4 CPUs e 8 GB de RAM** ao runtime, especialmente se também instalar o workload opcional Tempo.
7. As portas `3000` (Grafana) e `12345` (Alloy UI) precisam estar livres quando você iniciar os comandos `kubectl port-forward`.

> [!NOTE]
> Se as portas `80` ou `443` já estiverem ocupadas e você não precisar delas para outro exercício, edite `kind-config.yaml` e remova a seção `extraPortMappings`. O tutorial não usa Ingress. O rótulo `ingress-ready=true` também não é necessário para este exemplo, mas pode ser mantido sem causar impacto.

## 1. Criar e verificar o cluster kind

Execute o comando a partir do diretório que contém `kind-config.yaml`:

```bash
kind create cluster --name lab --config kind-config.yaml
```

O arquivo cria um nó `control-plane` e dois nós `worker`, aplica o rótulo `ingress-ready=true` ao `control-plane` e configura o encaminhamento das portas 80 e 443 para o host.

Verifique o cluster e o contexto atual:

```bash
kind get clusters
kubectl config current-context
kubectl cluster-info
kubectl get nodes -o wide
```

O contexto esperado é `kind-lab`; os três nós devem estar no estado `Ready`.

> [!WARNING]
> Se o cluster `lab` já existir, examine os recursos antes de excluí-lo ou recriá-lo. `kind delete cluster --name lab` remove os workloads e os dados que estejam armazenados nos nós desse cluster.

### 1.1 Verificar o armazenamento persistente

Os values do tutorial habilitam volumes persistentes para Grafana e Loki. Verifique se o cluster tem uma `StorageClass` padrão:

```bash
kubectl get storageclass
```

Se não houver uma StorageClass marcada como `(default)`, instale o provisionador local usado com frequência em laboratórios kind:

```bash
kubectl apply -f https://raw.githubusercontent.com/rancher/local-path-provisioner/v0.0.37/deploy/local-path-storage.yaml
kubectl rollout status deployment/local-path-provisioner -n local-path-storage --timeout=180s
kubectl get storageclass
```

Se a StorageClass `local-path` existir, mas não estiver marcada como padrão e o cluster não tiver outra StorageClass padrão, marque-a como padrão:

```bash
kubectl patch storageclass local-path --type=merge \
  -p '{"metadata":{"annotations":{"storageclass.kubernetes.io/is-default-class":"true"}}}'
```

O provisionador cria volumes nos nós do cluster. O armazenamento é adequado ao laboratório e não oferece as garantias de durabilidade de uma solução de storage de produção.

## 2. Obter os arquivos de configuração do tutorial

A partir do diretório em que está o `kind-config.yaml`, clone o repositório de cenários do Grafana e entre na pasta do exemplo:

```bash
git clone https://github.com/grafana/alloy-scenarios.git
cd alloy-scenarios/k8s/logs
```

O diretório contém `grafana-values.yml`, `k8s-monitoring-values.yml` e `loki-values.yml`. O `k8s-monitoring-values.yml` atual usa o schema do chart **Kubernetes Monitoring v4 ou superior**: destinations e collectors são mapas, e os recursos Alloy são definidos em `collectors`. Por isso, mantenha esse arquivo com o chart v4; não o combine com um chart v3 nem substitua por um values antigo da página do tutorial.

## 3. Adicionar os repositórios Helm e criar as namespaces

```bash
helm repo add grafana https://grafana.github.io/helm-charts --force-update
helm repo add grafana-community https://grafana-community.github.io/helm-charts --force-update
helm repo update
```

Crie as namespaces utilizadas pelo tutorial:

```bash
kubectl create namespace meta
kubectl create namespace prod
```

- `meta`: Loki, Grafana e coletores Alloy.
- `prod`: workload de demonstração que gera logs.

Se uma namespace já existir, confira se ela pertence a este laboratório antes de continuar.

## 4. Preparar os values do Loki para o kind

O chart atual do Loki usa `deploymentMode: Monolithic` (o nome antigo `SingleBinary` está depreciado). Para evitar falhas de pull das imagens MinIO e remover essa dependência do laboratório, configure o backend de armazenamento como `filesystem` e desabilite o MinIO integrado.

Crie o arquivo `loki-values-kind.yml` no diretório `alloy-scenarios/k8s/logs` com o conteúdo abaixo. Os recursos do processo Loki foram reduzidos para o ambiente local; se houver pouca memória disponível, evite também instalar o workload Tempo opcional descrito adiante.

```yaml
loki:
  auth_enabled: false
  commonConfig:
    replication_factor: 1
  schemaConfig:
    configs:
      - from: "2024-04-01"
        store: tsdb
        object_store: filesystem
        schema: v13
        index:
          prefix: loki_index_
          period: 24h
  storage:
    type: filesystem
    filesystem:
      chunks_directory: /var/loki/chunks
      rules_directory: /var/loki/rules
  pattern_ingester:
    enabled: true
  limits_config:
    allow_structured_metadata: true
    volume_enabled: true
  rulerConfig:
    enable_api: true

# Não instala o MinIO integrado ao chart: Loki usa armazenamento local filesystem.
minio:
  enabled: false

deploymentMode: Monolithic

singleBinary:
  replicas: 1
  resources:
    requests:
      cpu: 500m
      memory: 1Gi
    limits:
      cpu: "2"
      memory: 2Gi
  extraEnv:
    - name: GOMEMLIMIT
      value: 1800MiB

chunksCache:
  writebackSizeLimit: 10MB

# Desabilita os demais modos de implantação para manter Loki monolítico.
backend:
  replicas: 0
read:
  replicas: 0
write:
  replicas: 0
ingester:
  replicas: 0
querier:
  replicas: 0
queryFrontend:
  replicas: 0
queryScheduler:
  replicas: 0
distributor:
  replicas: 0
compactor:
  replicas: 0
indexGateway:
  replicas: 0
bloomPlanner:
  replicas: 0
bloomBuilder:
  replicas: 0
bloomGateway:
  replicas: 0
```

**Por que usar um arquivo separado?** Isso evita alterar os values originais do repositório e registra as alterações específicas do cluster local. O arquivo desabilita o MinIO, usa armazenamento local `filesystem`, uma réplica e limites reduzidos de CPU e memória para Loki. Os dados armazenados em filesystem não são migrados automaticamente de uma instalação anterior que utilizava MinIO.

## 5. Instalar Loki

Instale o chart comunitário de Loki no namespace `meta`:

```bash
helm install loki grafana-community/loki \
  --namespace meta \
  --values loki-values-kind.yml
```

A instalação não habilita MinIO: Loki usa o volume persistente do chart para armazenar os dados no filesystem do Pod. Esse modo é voltado ao laboratório e não substitui um armazenamento de objetos para produção.

> [!NOTE]
> Se você já instalou o Loki seguindo uma versão anterior deste README, **não execute `helm install` novamente**. Edite o arquivo `loki-values-kind.yml` para desabilitar MinIO e usar filesystem, depois aplique com `helm upgrade`. Para evitar uma atualização de versão principal involuntária, especifique a versão do chart que já está instalada (consulte `helm list -n meta`):
>
> ```bash
> helm upgrade loki grafana-community/loki \
>   --version VERSAO_DO_CHART_INSTALADO \
>   --namespace meta \
>   --values loki-values-kind.yml
> ```
>
> Substitua `VERSAO_DO_CHART_INSTALADO` pela versão numérica exibida na coluna `CHART`, sem o prefixo `loki-`. Os logs anteriormente armazenados no MinIO não passam automaticamente para filesystem; para um laboratório descartável, considere a instalação como uma nova instância.

Verifique o release, os Pods e os PVCs:

```bash
helm status loki -n meta
kubectl get pods,pvc -n meta -o wide
kubectl get events -n meta --sort-by=.lastTimestamp
```

## 6. Instalar Grafana

O arquivo `grafana-values.yml` do cenário configura a fonte de dados Loki apontando para `http://loki-gateway.meta.svc.cluster.local:80`. Ele também habilita persistência e define credenciais de demonstração.

Instale Grafana:

```bash
helm install grafana grafana/grafana \
  --namespace meta \
  --values grafana-values.yml
```

Verifique os Pods e o PVC:

```bash
helm status grafana -n meta
kubectl get pods,pvc -n meta -o wide
```

Aguarde até os componentes do Loki e Grafana estarem prontos antes de instalar o coletor. Não deve ser criado um Pod `loki-minio-0`, pois MinIO está desabilitado:

```bash
kubectl get pods -n meta --watch
```

Pressione `Ctrl+C` depois que os Pods relevantes estiverem `Running` e prontos. Se algum Pod permanecer `Pending`, consulte `kubectl describe pod` e verifique se a StorageClass foi criada corretamente.

## 7. Instalar Kubernetes Monitoring e Alloy

Instale o chart `grafana/k8s-monitoring` v4 ou superior com os values fornecidos pelo repositório:

```bash
helm install k8s grafana/k8s-monitoring \
  --version "^4.0.0" \
  --namespace meta \
  --values k8s-monitoring-values.yml
```

Essa configuração habilita dois fluxos principais:

- **Logs de Pods**, coletados pela API do Kubernetes usando o collector `alloy-logs`.
- **Eventos Kubernetes**, coletados pelo collector `alloy-singleton`.

O release `k8s` e os nomes dos collectors formam os nomes/labels dos recursos gerados. Para o collector de logs, o seletor documentado é `app.kubernetes.io/name=alloy-logs,app.kubernetes.io/instance=k8s-alloy-logs`; observe que o label `instance` não é simplesmente `k8s`.

A coleta está limitada às namespaces `meta` e `prod`. A coleta de logs do sistema de arquivos dos nós está desabilitada, o que evita exigir acesso a `/var/log/journal` nesta demonstração.

Verifique o release e aguarde os Pods:

```bash
helm status k8s -n meta
kubectl get pods -n meta -o wide --watch
```

Pressione `Ctrl+C` quando os componentes estiverem prontos. Confira também os releases Helm:

```bash
helm list -n meta
kubectl get pods -n meta
```

## 8. Acessar o Grafana

Em um terminal Bash, selecione o Pod do Grafana e inicie o encaminhamento de porta:

```bash
POD_NAME="$(kubectl get pods -n meta \
  -l 'app.kubernetes.io/name=grafana,app.kubernetes.io/instance=grafana' \
  -o jsonpath='{.items[0].metadata.name}')"
kubectl -n meta port-forward "$POD_NAME" 3000:3000
```

Mantenha esse terminal aberto e navegue para:

- Grafana: [http://localhost:3000](http://localhost:3000)
- Logs Drilldown: [http://localhost:3000/a/grafana-lokiexplore-app](http://localhost:3000/a/grafana-lokiexplore-app)

Credenciais configuradas pelo arquivo fornecido: usuário `admin` e senha `adminadminadmin`. São credenciais exclusivamente para demonstração e não devem ser utilizadas em ambientes reais.

No Grafana, abra **Explore** ou **Logs Drilldown** e selecione a fonte de dados `Loki`.

## 9. Gerar logs de demonstração

### Opção recomendada para kind: workload leve

O chart `tempo-distributed` utilizado na página do tutorial pode criar diversos componentes e consumir recursos consideráveis. Para validar a coleta de logs em um cluster local com menos recursos, crie um Pod simples que escreve uma mensagem a cada cinco segundos:

```bash
kubectl run log-generator -n prod \
  --image=busybox:1.36 \
  --restart=Never \
  --command -- sh -c 'i=0; while true; do echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) demo log number=$i"; i=$((i+1)); sleep 5; done'
```

Confirme que o Pod está em execução e produzindo logs:

```bash
kubectl get pods -n prod -o wide
kubectl logs -n prod -f pod/log-generator
```

Deixe o segundo comando rodando por alguns segundos e pressione `Ctrl+C`. Ele interrompe apenas a visualização dos logs, não o Pod.

### Opção equivalente ao tutorial: Tempo

Para seguir a demonstração original da página e instalar Tempo como workload de exemplo, use em vez do gerador leve:

```bash
helm install tempo grafana/tempo-distributed -n prod
kubectl get pods -n prod --watch
```

Esse release é apenas uma fonte de logs de exemplo para o laboratório; não é necessário para validar Loki, Grafana e Alloy. Em kind, prefira a opção leve acima caso os Pods fiquem sem recursos disponíveis.

## 10. Consultar os logs no Grafana

Depois que Alloy estiver pronto e o workload de demonstração tiver produzido mensagens, abra [Logs Drilldown](http://localhost:3000/a/grafana-lokiexplore-app) ou use **Explore** com a fonte de dados `Loki`.

Uma consulta LogQL para filtrar logs dos workloads da namespace `prod` é:

```logql
{namespace="prod"}
```

Para verificar a coleta de eventos Kubernetes, provoque uma alteração simples na namespace `prod` em outro terminal, por exemplo:

```bash
kubectl create configmap evento-demo -n prod --from-literal=origem=laboratorio
kubectl describe configmap evento-demo -n prod
```

A coleta de eventos não é instantânea; aguarde alguns instantes e atualize a consulta no Grafana. Os rótulos disponíveis podem variar conforme o tipo de registro; use o explorador de labels do Grafana para inspecioná-los.

## 11. (Opcional) Acessar a interface do Alloy

A interface web do Alloy permite visualizar o grafo de componentes e depurar o pipeline de coleta. Em um segundo terminal, primeiro liste os Pods e seus labels para confirmar que os coletores foram criados:

```bash
kubectl get pods -n meta -o wide --show-labels
```

Selecione o Pod `alloy-logs` usando o label `instance` completo (`k8s-alloy-logs`, não `k8s`):

```bash
POD_NAME="$(kubectl get pods -n meta \
  -l 'app.kubernetes.io/name=alloy-logs,app.kubernetes.io/instance=k8s-alloy-logs' \
  -o jsonpath='{.items[0].metadata.name}')"

if [ -z "$POD_NAME" ]; then
  echo "Pod alloy-logs não encontrado. Confira os Pods, labels e eventos no namespace meta."
  kubectl get pods -n meta -o wide --show-labels
  kubectl get events -n meta --sort-by=.lastTimestamp
else
  kubectl -n meta port-forward "$POD_NAME" 12345:12345
fi
```

Mantenha o comando em execução e acesse [http://localhost:12345](http://localhost:12345). Esse encaminhamento exibe a interface do collector de logs de Pods. Para depurar eventos, interrompa o port-forward com `Ctrl+C` e execute em outro terminal:

```bash
POD_NAME="$(kubectl get pods -n meta \
  -l 'app.kubernetes.io/name=alloy-singleton,app.kubernetes.io/instance=k8s-alloy-singleton' \
  -o jsonpath='{.items[0].metadata.name}')"

if [ -z "$POD_NAME" ]; then
  echo "Pod alloy-singleton não encontrado. Confira os Pods, labels e eventos no namespace meta."
  kubectl get pods -n meta -o wide --show-labels
  kubectl get events -n meta --sort-by=.lastTimestamp
else
  kubectl -n meta port-forward "$POD_NAME" 12345:12345
fi
```

Esse segundo comando pressupõe que `alloy-singleton` esteja definido no `k8s-monitoring-values.yml`. Se o seu arquivo encaminhar os eventos para outro collector, use os labels e o nome desse collector conforme `kubectl get pods -n meta --show-labels`.

## 12. Diagnóstico rápido

| Sintoma | O que verificar |
| --- | --- |
| `kind create cluster` falha ao mapear portas | Verifique se as portas 80 e 443 estão livres ou remova `extraPortMappings` do arquivo de configuração, já que não são usadas neste tutorial. |
| PVC fica `Pending` | Execute `kubectl get storageclass` e `kubectl get pvc -n meta`; configure uma StorageClass padrão, por exemplo com `local-path-provisioner`. |
| Pod do Loki não fica pronto | Consulte `kubectl describe pod`, eventos e logs; confira o PVC e a memória disponível no Docker Desktop/runtime. Com filesystem, não deve haver dependência de um Pod MinIO. |
| Grafana inicia, mas não mostra dados | Confirme que Loki e Alloy estão prontos, que a fonte de dados `Loki` existe e que o workload em `prod` gera logs. |
| O release `k8s` falha | Confirme que Loki e Grafana foram instalados antes e que o chart `grafana/k8s-monitoring` é v4 ou superior. |
| `kubectl port-forward` do Alloy falha ou o seletor retorna zero Pods | Execute `kubectl get pods -n meta -o wide --show-labels`. Confira se o release `k8s` criou os collectors e use `app.kubernetes.io/instance=k8s-alloy-logs` ou `k8s-alloy-singleton`, conforme o collector. Não use `instance=k8s`. |
| A consulta `{namespace="prod"}` não retorna registros | Aguarde a coleta, verifique `kubectl logs -n prod pod/log-generator` e consulte a interface do Alloy para depurar o pipeline. |

Comandos úteis:

```bash
kubectl get pods -A -o wide
kubectl get pvc -n meta
kubectl get events -n meta --sort-by=.lastTimestamp
kubectl get events -n prod --sort-by=.lastTimestamp
helm list -A
helm status loki -n meta
helm status grafana -n meta
helm status k8s -n meta
```

Para ver logs de um componente específico:

```bash
kubectl logs -n meta <nome-do-pod>
```

Substitua `<nome-do-pod>` pelo valor exibido por `kubectl get pods`; não copie literalmente os sinais `<` e `>` para o terminal.

## 13. Encerrar e limpar o laboratório

Se usou a opção leve, remova o gerador de logs e o ConfigMap de demonstração:

```bash
kubectl delete pod log-generator -n prod --ignore-not-found
kubectl delete configmap evento-demo -n prod --ignore-not-found
```

Se instalou Tempo, remova o release:

```bash
helm uninstall tempo -n prod
```

Para desinstalar os charts do stack de observabilidade:

```bash
helm uninstall k8s -n meta
helm uninstall grafana -n meta
helm uninstall loki -n meta
kubectl delete namespace prod meta
```

Se instalou o `local-path-provisioner` exclusivamente para este laboratório e deseja removê-lo, verifique primeiro se não há PVCs/PVs que ainda dependam dele:

```bash
kubectl get pvc -A
kubectl get pv
```

Depois de confirmar que nenhum outro workload depende dele, remova-o:

```bash
kubectl delete -f https://raw.githubusercontent.com/rancher/local-path-provisioner/v0.0.37/deploy/local-path-storage.yaml
```

Por fim, para excluir o cluster local inteiro:

```bash
kind delete cluster --name lab
```

> [!WARNING]
> Excluir o cluster remove os workloads e os dados persistidos nos nós kind. Não execute a limpeza em um cluster compartilhado nem enquanto houver dados que precisem ser preservados.

## Referências

> [!NOTE]
> **Para saber mais**
>
> - [Grafana Loki Documentation | Kubernetes Monitoring Helm tutorial](https://grafana.com/docs/loki/latest/send-data/k8s-monitoring-helm/)
> - [Grafana Loki Documentation | Install the monolithic Helm chart](https://grafana.com/docs/loki/latest/setup/install/helm/install-monolithic/)
> - [Grafana Loki Documentation | Filesystem object store](https://grafana.com/docs/loki/latest/operations/storage/filesystem/)
> - [Grafana Kubernetes Monitoring | Migrate between Helm chart versions](https://grafana.com/docs/grafana-cloud/observe-and-act/monitor-infrastructure/kubernetes-monitoring/configuration/helm-chart-config/helm-chart/migrate-helm-chart/)
> - [Grafana Alloy Scenarios | Kubernetes logs](https://github.com/grafana/alloy-scenarios/tree/main/k8s/logs)
> - [Grafana Documentation | Kubernetes Monitoring Helm chart](https://grafana.com/docs/grafana-cloud/monitor-infrastructure/kubernetes-monitoring/)
> - [kind | Quick Start](https://kind.sigs.k8s.io/docs/user/quick-start/)
> - [Rancher | Local Path Provisioner](https://github.com/rancher/local-path-provisioner)
> - [Helm | Documentation](https://helm.sh/docs/)
