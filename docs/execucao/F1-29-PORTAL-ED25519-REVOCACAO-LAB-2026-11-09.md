# FAST-PERF F1-29 — Recepção de declaração Portal assinada Ed25519 com barreira de revogação

**09/11/2026.** Issue #70, PR draft filho de F1-28 [#69](https://github.com/rafhael100/imagine-performance-ai/pull/69).

## Implementação
`App\\Performance\\Security\\SignedPortalCapabilityGate` verifica envelope JSON Ed25519 **real** emitido pelo código do repositório Portal `SyntheticCapabilityIssuer`, com **chave pública entregue ao construtor por configuração fora do pacote**. Não aceita chave pública indicada no envelope recebido; exige `key_id=ephemeral-lab-key-1`, domínio específico `IMAGINE-PORTAL-CAPABILITY-SYNTHETIC-V1\n`, assinatura detached válida, Base64URL canônico, JSON sem chaves desconhecidas/duplicadas e conjunto/ordem de campos estrito.

Os campos assinados incluem `issuer=imagine.portal`, `audience=imagine.performance-ai`, organização, tenant, serviço, campanha, ator, sessão, escopo de leitura, MFA sintético, versões membership/serviço/sessão, epoch de recuperação, status, emissão/expiração e nonce aleatório de 128 bits. Prazo da declaração é de 20s, máximo tolerado 30s. Mesmo com assinatura íntegra, o verificador exige prova de origem atual (estrutura `SyntheticCurrentAuthority` do **laboratório**), com fonte disponível, observação de no máximo 5s, contexto igual, sessão/MFA/membership/serviço ativos e revisões exatamente iguais. Repetição do nonce no mesmo processo é recusada.

**Nunca gera ALLOW operacional.** O único sucesso se chama `verified_synthetic_only` e requer PHP CLI + `APP_ENV=testing` + flag `PERF_F29_SIGNED_PORTAL_LAB=1`. Não há endpoints, commands reais, jobs, serviço de anúncios, compra de mídia ou pagamentos. O `SyntheticCurrentAuthority` é construível pelo chamador e **não constitui consulta autenticada ao Portal**, nem impede fraude de origem em produção; o cache de nonce é somente em memória e não sobrevive a processos, réplicas ou restore. O envelope usa uma chave pública efêmera que só é confiada pelo runner via checkout SHA fixado — **não existem PKI/KMS de produção ou rotação de chaves**.

## Integração cruzada executável
O CI F1-29 executa a regressão PostgreSQL F1-22 a F1-28 e faz checkout do **commit fixo do Portal `739465b5cc5022aae85385e360e1b4b19ab033cd`**, cujo CI próprio comprova emissão Ed25519. Em seguida chama o emissor PHP CLI original do Portal, gera novo par de chaves em memória, salva apenas a **chave pública hex em arquivo temporário** e transmite JSON assinado via arquivo descartável. O script `test-signed-portal-capability.php` realiza **75 verificações**, incluindo assinatura/issuer/audience/chave, nonce replay, exclusão do cliente, escopo, sessão, mfa, revisão, epoch, contrato revogado, fonte indisponível, observação expirada, assinatura adulterada e até *payload malicioso corretamente assinado por outra chave*, que continua limitado a contexto e permissão de demonstração. Nenhum segredo no Git.

## O que falta para autenticação de clientes
- Autoridades **reais** do Portal para sessão autenticada, MFA, membro da organização e permissão por recurso/serviço, contratação e versão de revogação; hoje são interfaces e `DenyingPortalAuthorities`.
- Fonte de revogação independente resistente a restore, verificação online confiável/mTLS operacional, revogação distribuída e replay cache persistente/atômico.
- Registro de chaves confiáveis com rotação e revogação (KMS), proteção contra comprometimento do emissor, auditoria de emissão/consumo, retenção e LGPD.
- Vínculo de autorização operacional transacional ao banco de campanhas e ao serviço; AppSec/DBA, testes de produção e homologação.
  
Sem merge/main/fast-track/VPS/deploy, dados de cliente, contas Google/Meta ou despesa. Gates SEC-R03/T3, P0 e HT-R01 permanecem abertos.
