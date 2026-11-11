# FAST-PERF F1-149 — CI deny-by-default da sandbox e rotação da identidade

**Base F148 (PR #256). Laboratório 100% sintético; DRAFT / NO-GO.**

## Novas provas automatizadas

- `F149SandboxPolicy` verifica o Dockerfile, o invocador de Docker e o CLI do executor como um **contrato de segurança versionado**. Somente imagem base PHP 8.4 CLI Alpine, cinco arquivos PHP explícitos, diretório `/app` e entrypoint exato são aceitos. É proibido adicionar `RUN`, `ADD`, pacotes/SDKs de anúncio ou cópia do repositório inteiro.
- A política exige `docker run` com `--network none`, `--read-only`, `--cap-drop ALL`, `--security-opt no-new-privileges`, `--pids-limit 64`, `--user` e bind do único volume sintético. O teste negativo injeta alterações indevidas, como `--network host`, remoção do sistema de arquivos somente leitura, imagem base não autorizada, entrypoint shell e modo privilegiado, para comprovar falha de CI.
- O CLI conserva rejeição de segredos de anúncios, variáveis de conexão DB, chaves privadas do emissor de grants e identidade de workload. O auditor procura também imports/chamadas simples de clientes HTTP na entrada, mas **não é um scanner semântico completo nem protege código arbitrário em outras partes do produto**.
- O workflow inclui uma checagem efetiva **dentro do contêiner Docker em modo sem rede**: a lista de interfaces contém apenas `lo`, e o processo opera com UID não-root. Isso confirma propriedades observáveis daquela execução, além de inspecionar flags declaradas.
- O teste F148 passa a simular **rotação de chave pública do workload**: uma identidade antiga assinada é recusada após o novo public key substituir o anterior, enquanto a assinatura da nova identidade é aceita uma vez para o mesmo grant terminal `synthetic-noop`; a identidade antiga continua negada. O teste não expõe a chave privada ao contêiner.

## Limitações e próximos gates

Essa validação é uma **política de source e CI controlada pelo mesmo repositório**. Alguém com permissão de modificar workflow, scanner e Dockerfile em conjunto pode remover o bloqueio. O teste não comprova que todos os caminhos de mídia de uma futura aplicação usem exclusivamente esse executor. A versão de produção exigirá protections/branch rules e aprovação independente obrigatória antes de alterar política, além de isolamento do daemon Docker, credenciais, identidade de workload S2S real, egress firewall no ambiente de execução e registro imutável fora do domínio de restauração.

Continua faltando proteção contra rollback conjunto de diário e checkpoint; F147 demonstrou bypass do marcador de quarentena por administrador do host. Sem KMS/HSM, mTLS, IAM Portal por tenant, OAuth e orçamento de mídia, AppSec/DBA independente, reconciliação de chamadas a provedores ou capacidade real de veicular anúncios.

**P0 / HT-R01 / SEC-R03 / T3: NO-GO. Nenhum merge em `main`, deploy, VPS, cliente, Google/Meta Ads, campanha, dinheiro ou cobrança.**

Próxima prioridade F150: provas independentes de integridade e autorização do próprio pipeline (protection rules, revisores externos, segregação de identidade do executor), além de documentação de recuperação manual sem apagar evidências.
