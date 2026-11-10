# FAST-PERF F1-30 — E2E: sessão SQL do Portal → assinatura Ed25519 → verificação do Performance

**Data-base:** 09/11/2026. Issue #72. PR draft filho do F1-29 [#71](https://github.com/rafhael100/imagine-performance-ai/pull/71); emissor Portal F09 [#130](https://github.com/rafhael100/imagine-portal/pull/130).

## Cobertura
O CI F1-30 faz checkout **por SHA imutável** do Portal `fc95f4bef1b2eab8eef2fcbb16fc6815e479dd21` em diretório separado, sobe PostgreSQL16 descartável em `127.0.0.1:5432`, executa migrations Laravel F08 e cria a role SELECT-only. Executa o teste Portal F08 (60 verificações, sessões/MFA/vínculos/contratos de exemplo) e F09 (45 verificações, emissão Ed25519 a partir do DB e revogações). O emissor F09 cria nova chave privada **somente em memória**, grava a chave pública no diretório temporário e emite a declaração Ed25519 já preenchida com revisões atuais do banco.

O Performance F1-30 usa seu verificador Ed25519 F29 com **chave pública pinada no teste**, mas constrói o objeto de evidência `SyntheticCurrentAuthority` através de uma **segunda consulta real à mesma autoridade SQL Portal**, em vez de confiar apenas no estado declarado no próprio token. Testa 50 hipóteses: assinatura/issuer/audience/nonce, contexto e revisions/epoch de origem, alteração de sessão, MFA, membership e serviço depois da emissão, revogação, perda de permissão SELECT, tempo expirado, replay, cross-tenant, chave incorreta e default deny fora do laboratório.

A etapa não estabelece end-to-end operacional: o test runner consulta diretamente o PostgreSQL de laboratório do Portal no mesmo host, e não uma API S2S protegida por mTLS. Não existe produção, cliente autenticado, fonte comercial verdadeira, token firmado por KMS, nem replay/fencing distribuído. A reconsulta SQL usa a classe Portal importada via checkout pinado e entrega um objeto de laboratório com `resourceRef=demo-cam-a1`; **propriedade real de campanha não está comprovada**. O processo não pode ser usado na VPS nem converter `verified_synthetic_only` em permissão real.

## Princípios operacionais
O compromisso futuro exige: (1) fonte autenticada de sessão/MFA e membership real no Portal; (2) contrato/serviço/campanha aprovados por autoridade comercial do Portal; (3) assinatura por chave de workload em KMS e distribuição/rotação; (4) consulta online de revogação com mTLS e replay store distribuído durável; (5) bloqueio transacional em operação Ads/PSP e auditoria; (6) homologação AppSec/DBA, LGPD e rollout supervisionado. Os valores de demonstração não bastam para isso.

Sem merge/main/fast-track/VPS/deploy, sem custo de terceiros, Google/Meta/PSP e dados reais. P0, HT-R01 e SEC-R03/T3 continuam abertos.
