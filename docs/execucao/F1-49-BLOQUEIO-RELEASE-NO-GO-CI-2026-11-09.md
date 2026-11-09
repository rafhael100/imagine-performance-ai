# FAST-PERF F1-49 — Barreira NO-GO automatizada para ativação operacional

**Data-base:** 09/11/2026. PR draft filho do F1-48 PR #118. CI de desenvolvimento sintético, não substitui autorização humana ou verificação de segurança de produção.

## Problema concreto

Os testes F1-30–F1-48 evoluíram mecanismos de laboratório, mas também comprovaram lacunas graves ainda não resolvidas, como replay após `pg_dump`/restore da própria autoridade e revogação de tokens em cache. Não seria seguro que um commit de integração ativasse campanha, cobrança, envio ou API externa por engano enquanto esses gates permanecem abertos.

## Medida adicional

- Arquivo de contrato `contracts/lab/ci/performance-release-no-go.json`: registra explicitamente `release_state=blocked`, `operational_activation_allowed=false`, `external_provider_dispatch_allowed=false`, `billing_allowed=false`, `automatic_merge_allowed=false` e ausência de autoridade Portal/CRM real conectada.
- Gates P0, HT-R01, SEC-R03/T3, AppSec/DBA, antirollback pós-restore, idempotência do provedor e aprovação humana permanecem declaradamente não aprovados.
- Testador `contracts/lab/php/test-f49-release-no-go-ci.php`: valida schema/valores do contrato e simula alterações proibidas na memória, exigindo rejeição de cada tentativa de ativação ou de mudança artificial de status. Não altera arquivos do usuário nem muda nada no GitHub além da branch de trabalho.
- O testador inspeciona arquivos PHP executáveis em `app/`, `routes/`, `bootstrap/` e `database/migrations/` à procura de padrões evidentes de HTTP de escrita, clientes de Ads e ligação indevida de classes de laboratório. O resultado depende de padrões estáticos — **não garante detecção de todas as formas de ativação**.
- Consulta o PostgreSQL sintético de verdade e exige `CHECK (dispatch_enabled=false)` no schema `perf_f35_intents.outbox`, nenhuma linha habilitada, solicitações somente `pending_synthetic` e permissão somente `campaigns:read`.
- O workflow F1-49 executa todas as regressões anteriores, inclusive a prova de replay F1-48, **antes** do teste NO-GO.

## Limites e riscos

O contrato JSON e o teste PHP estão sob controle do próprio repositório; um agente com direito de alterar ambos pode mudar a política. Isso é uma barreira de regressão do CI, **não um controle de segurança inviolável**. Não há branch protection, aprovação Code Owners, assinatura de artifacts, auditoria de separação de funções nem garantia de que toda rota, dependência ou segredo operacional seja coberto por padrões simples. As permissões e os resultados só valem no runner efêmero do GitHub. Todos os papéis e backends reais continuam fora do teste.

**P0/HT-R01/SEC-R03/T3, Issues #102, #115 e #117 e AppSec/DBA ainda abertos.** Nenhuma autorização real para `main`, merge, deploy, VPS, Google/Meta, CRM, n8n, PSP, faturamento ou clientes. Um CI verde confirma que a implantação continua BLOQUEADA.

## Próximos requisitos antes da homologação

Regras de branch protegida com revisão humana de arquivos sensíveis, validação de release independente dos autores, identidade S2S e contrato de serviços reais, detecção/quarentena automática após restauração, autoridade de época não retrocedível, prova de não bypass em operações e efeitos idempotentes auditados nos fornecedores.