# FAST-PERF F1-154 — atenção operacional fictícia por canal no painel

**Base F153, PR #261. Estado: branch isolada / DRAFT / NO-GO.**

A F154 adiciona ao modelo do painel offline uma seção de **pendências e atenção por canal**, alimentada exclusivamente pelos DTOs de escopo F52/F53 e revisões F151/F153. `F154SyntheticAttentionProjector` não inventa cliques, impressões, conversões, gasto, retorno ou SLA verificado; usa somente contadores que já existem nos protótipos.

Cada canal Google Ads ou Meta recebe suas próprias revisões pendentes, solicitações de ajustes e alertas de capacidade ilustrativa. Campanhas **pausadas permanecem sob gestão** e consomem capacidade, conforme diretriz comercial, até remoção formal. Nenhuma condição gera aumento de franquia ou custo automático. Cada cartão de atenção contém `action_enabled=false` e o DTO conserva `contracted=false`, `authorization_eligible=false`, `human_approval_verified=false`, `dispatch_enabled=false`, `billing_enabled=false`, `ready_for_production=false` e `real_metrics_available=false`.

O gerador F55 incorpora a projeção apenas com `PERF_F154_ATTENTION_LAB=1`; o HTML offline ganhou uma seção em Relatórios que usa `textContent` e respeita filtros de canal, sem rede, HTTP, cookies, login, assinatura, gastos ou métricas reais. A versão estática sem flag mantém mensagem de que indicadores adicionais não estão disponíveis.

O CI cria dados fictícios com duas campanhas Google, uma Meta, uma campanha Google pausada, uma revisão pendente, uma revisão com ajustes solicitados e capacidade de campanhas esgotada nos dois canais. O resultado esperado inclui **quatro cartões de atenção fictícios**, dois itens no backlog e dois alertas de capacidade. Testes negativos recusam diferenças de tenant, versões, canais duplicados, contagem de capacidade inconsistente, aprovadores reais, despacho ativo e status de aprovação operacional. O CI também preserva F151/F152/F153.

**Limites:** não há notificação ao cliente, SLA real, workflows de pagamento/Ads, aprovação humana autenticada ou dados reais do provedor. Não foi demonstrada autorização de empresas por IAM Portal. A integração de produção depende de Portal MFA/S2S, revisão DBA/AppSec, OAuth/consentimento Google/Meta, políticas de orçamento, VPS homologada, logs imutáveis e recuperação de incidentes.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Sem merge main, deploy, anúncios, cobrança, clientes ou despesas.**
