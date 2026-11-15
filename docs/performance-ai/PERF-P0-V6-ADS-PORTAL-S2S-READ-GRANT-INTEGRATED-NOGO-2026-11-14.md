# Imagine Performance AI — revisão integrada Portal V6 + autorização de leitura por conta

**14/11/2026. Estado: DRAFT / NO-GO em produção.** Todas as evidências são de fixtures/CI, sem contas de mídia ou usuários reais.

## Escopo integrado

1. **Comercial** — mídia mínima de referência Imagine por mês: Meta R$700, Google R$1.000 e ChatGPT R$1.200; recomendação Meta R$1.000, Google R$1.500, ChatGPT R$1.800. Honorários Imagine 20% do gasto efetivamente executado nas plataformas, sem cobrar investimento de mídia ao cliente pelo Asaas.
2. **Contrato Portal V6** — HMAC candidato assina empresa, acordo, competência, catálogo e nonce; legado Portal/checkout real continua inalterado. Múltiplos acordos da mesma empresa/mês podem ter rascunhos PG18 independentes e eventos de correção imutáveis, sem faturamento.
3. **Identidade do banco** — laboratório de RLS por `session_user` autenticado, distinto do GUC `app.tenant_id` forjável. Ainda não existe IAM real, contas SQL operacionais ou autorização Financeiro.
4. **Relatórios de anúncios** — planos read-only Google, Meta e ChatGPT; classificação de 401/403, 429 e 5xx sem retries automáticos; preflight da conta ChatGPT Ads verifica BRL, fuso IANA, conta ativa e revisão aprovada. O parser conserva valores numéricos JSON exatamente em centavos e impede arredondamento implícito. O ensaio ChatGPT → PG18 obtém R$18,72 de mídia fictícia e R$3,74 de honorários hipotéticos, sem cobrança.
5. **Nova sessão Ads #378** — duas callbacks GET simuladas com **a mesma referência opaca** de credencial, primeiro `/v1/ad_account`, depois `/v1/ad_account/insights` somente se o primeiro passou.
6. **Nova autorização S2S #380 + Portal #814** — o emissor **real de código PHP fixado no SHA da PR Portal #814**, `8760e7dbc6c6e643368f43fbb7f44d363182bf8c`, emite autorização curta de leitura para tenant/acordo/competência/conta/ref de credencial/consentimento, assinada com HMAC sob chave/domínio **distinto** da assinatura comercial V6. `PortalAdsReadS2SLabVerifier` confere audiência, prazo, assinatura, escopo `ads.insights.read`, `can_mutate_ads=false` e nonce de uso único simulado. `PortalAdsGrantedReadOnlySessionLab` verifica assinatura **junto com o vínculo exato da sessão de credencial e consentimento**, antes de permitir os dois GET simulados, impedindo aplicar uma autorização válida a outro anunciante ou referência de segredo. Um teste inicial identificou a lacuna entre verificador e sessão; foi corrigida e acrescentada ao teste adversarial.

## Bloqueios de produção

- **Não há autenticação IAM verdadeira**, contrato ativo verificado contra base operacional, registro de consentimento oficial ou verificação da titularidade da conta do anunciante. Assinar dados fornecidos pelo laboratório não os torna verdadeiros.
- **O nonce do grant S2S ainda é testado em memória**, insuficiente contra replay entre trabalhadores/processos. Implementar reivindicação transacional durável com expiração, replay e trilha de auditoria em infraestrutura autenticada.
- **Não existem identidades de workload S2S reais**, vault/rotação de chaves, transporte de rede autenticado, proteção de egress e validação TLS/DNS para Google/Meta/OpenAI Ads. O código continua offline.
- **Não há receita operacional real**: Asaas Sandbox/financeiro-fiscal, documentação/NFS-e, retificações, regras de arredondamento subcentavo e verificação de gastos contra extratos oficiais estão pendentes.
- **Nenhum deploy VPS**, CRM real/LGPD, monitoramento, backups operacionais e revisão formal SEG/DBA/LGPD/Financeiro foram executados por esta PR.

CI verde valida somente o contrato de engenharia offline. O catálogo/checkout ativo, `main`, VPS, campanhas, cobranças e dados reais não são tocados.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
