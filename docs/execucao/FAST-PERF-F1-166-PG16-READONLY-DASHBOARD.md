# FAST-PERF F1-166 — consultas seguras no PostgreSQL para painel de revisões

**Base F165 (PR #273); GitHub Actions PostgreSQL16 descartável. DRAFT / NO-GO.**

A F166 fornece uma projeção PHP exclusivamente de leitura `F166Pg16ReadOnlyDashboard` que usa três views PostgreSQL16 com `security_invoker=true` e `security_barrier=true`. A identidade de empresa é resolvida pela role efetiva de login SQL `session_user` via função F162, com RLS nos dados de origem. O parâmetro `tenant` não habilita acesso a outra empresa.

A primeira view oferece apenas revisões de campanhas ainda gerenciadas. A segunda oferece **contagem agregada de revisões em quarentena**, sem expor referência/título de campanhas retiradas. A terceira resume os eventos de escopo da F165 por tipo, sem métricas publicitárias reais. O contrato da camada PHP retorna `iam_verified=false`, `s2s_verified=false`, `human_approval_verified=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `contracted=false`, `ready_for_production=false`.

**Decisão de segurança importante:** o histórico F165 permite comprovar uma retirada mesmo que a campanha volte a `managed=true`. A view F166 usa a existência de qualquer evento `retired_demo` como um bloqueio **conservador permanente** para aquela campanha na demonstração, evitando que antigas aprovações `approved_mock_only` reapareçam automaticamente após reativação. Isso também oculta novas revisões da campanha reativada, até que haja um futuro fluxo explícito e independente de liberação por geração. Não se trata de restauração completa do produto, e nenhuma aprovação de mídia é inferida.

O CI usa duas roles login reais, simula inclusão de revisões A/B, aprovação fictícia, retirada administrativa de campanha, reativação e tentativa de nova revisão depois da reativação. Verifica invisibilidade de detalhes antigos e contagem em quarentena, sessão GUC falsificada, negação de SQL de escrita nas views e prova adicional de `security_invoker`: revogar SELECT na tabela base de eventos faz a leitura falhar fechada em vez de herdar permissões do owner da view. Executa F162–F165 antes dos novos testes.

**Limites:** a tabela de auditoria e a de campanhas pertencem ao mesmo administrador PostgreSQL; ele pode modificar história/ativar campanhas. A função de tenant e roles são apenas fixtures SQL, não IAM Portal/MFA/S2S. Não há OAuth, consentimento, orçamento, anúncio, pagamento, API pública ou dado de cliente. São necessárias revisão DBA/AppSec independente e autoridade externa confiável para produção.

**P0 / HT-R01 / SEC-R03 / T3 = NO-GO. Sem merge main, VPS, deploy, clientes, Ads, cobranças ou despesas.**

Próxima frente possível: definir e testar um fluxo de versão/geração do escopo que preserve quarentena anterior, permitindo apenas revisões explicitamente criadas após uma nova autorização fictícia; nunca ressuscitar automaticamente revisões anteriores.
