# FAST-PERF F1-171 — incompatibilidade entre protocolos assinados Portal e Performance

**Base F170/PR #278, CI PostgreSQL16 descartável, status NO-GO.**

Esta etapa não cria outra implementação de assinatura. Ela exercita **as implementações que já existem** no mesmo repositório e mostra por que a pilha F169/F170 não pode ser promovida como integração Portal, apesar dos testes verdes isolados.

| Limite | Portal legado F29/F123/F124 | Protótipo novo F169/F170 |
|---|---|---|
| Estrutura externa | `version,key_id,payload,signature` | `claims,signature` |
| Dados assinados | base64url JSON de 19 campos, com prefixo criptográfico de domínio | objeto JSON canônico reduzido com 7 campos, sem prefixo legado |
| Propósito | `campaigns:read` com referência de sessão/serviço | `performance:demo:read`, sem sessão/serviço |
| Tenant | `perf-demo-a` (Portal F29) versus UUID da conexão F91, por mapeamento test-only | `perf-demo-aurora`/ `perf-demo-boreal` no banco SQL F162 |
| Revisões de autorização | membership/service/session/recovery epoch + MFA sintéticos | nenhum desses campos |
| Persistência de anti-replay | `performance_signed_portal_demo_receipts` F124, SHA-256 de nonce no banco `perf_test_campaign_registry` | `perf_f162.consumed_read_nonces` F170 no banco `perf_f162_ci` |
| Limitações | `SyntheticCurrentAuthority` é instanciável pelo chamador; F125/F140 provam rollback | emissor/chave são gerados em CI; F170 também permite replay após exclusão administrativa |

O CI F171 **assina os dois formatos usando o mesmo par Ed25519 em memória**. Isso elimina a desculpa de chaves distintas: o F29 aceita somente o envelope com os campos e o domínio originais; o F169/F170 recusam esse formato mesmo assinado corretamente. O formato F169 é aceito pelo F169/F170 e rejeitado pelo F29. O teste valida explicitamente as diferenças de tenant, permission, membership, service, MFA e epoch, inclusive negação de assinatura antiga com MFA=false. F170 deve continuar consumindo exatamente um nonce para o formato F169, sem aceitar o envelope F29.

**Decisão técnica:** NÃO marcar F169/F170 como compatível com o emissor Portal pré-existente. Não registrar uma rota Portal usando F169, nem criar uma nova fonte de identidade baseada apenas no `subject` do JSON simplificado. Antes de qualquer integração real, selecionar o protocolo canônico entre equipes Portal + Performance e implementar um único verificador, com revisão de paridade da assinatura, vínculo autenticado real user/tenant/service, autorização independente, revalidação, gestão de chaves e antirollback offsite. Só então descontinuar o contrato redundante com testes de migração/backward compatibility; nunca afrouxar F29 para aceitar envelope sem MFA/revisões.

**Novo CI:** `.github/workflows/perf-f1-171-portal-wire-compat-ci.yml` reaproveita a instância descartável e regressões F162–F170 e roda o teste F171. **Esta PR é um gate de incompatibilidade intencionalmente vermelho em termos de promoção**, mas deve ser verde em CI quando a incompatibilidade é reconhecida e recusada com segurança.

Não há produção, Portal IAM real, merge em dev/main, migração operacional, uso de VPS, clientes, anúncios ou faturamento. P0/HT-R01/SEC-R03/T3 continuam NO-GO.
