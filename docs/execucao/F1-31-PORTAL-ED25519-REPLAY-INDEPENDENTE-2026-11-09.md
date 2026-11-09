# FAST-PERF F1-31 — Ed25519 do Portal com recibo de replay independente

**Objetivo:** unir, em laboratório, a verificação Ed25519 + reconsulta PostgreSQL do Portal (F1-30 PR #73) com o princípio de nonce consumido em base SQL independente (laboratório HMAC F1-29 PR #81).

## Implementação

O GitHub Actions sobe duas **instâncias físicas PostgreSQL 16 separadas**, com os respectivos `system_identifier` distintos: Portal SQL (porta local 5432) e autoridade de recibo (porta 5433). Faz checkout do Portal em SHA fixo `fc95f4bef1b2eab8eef2fcbb16fc6815e479dd21`. A fonte do Portal continua fictícia; o emissor CLI gera assinatura Ed25519 de chave privada efêmera e exporta apenas a pública. O adaptador `SyntheticPortalEd25519DurableReplayGate` exige: (1) ambiente CLI testing e flag, (2) conexão da role restrita à autoridade separada, (3) reconsulta da fonte Portal, (4) validação Ed25519/escopo/sessão/revisões/epoch com gate F1-30, (5) gravação de hash de nonce com exclusividade e COMMIT independente.

A primeira apresentação devolve somente `verified_synthetic_once`; uma instância nova de verificador rejeita replay porque o recibo fica no PostgreSQL, não em array PHP. Assinatura alterada, chave errada, revisão posterior divergente, contrato revogado e fonte indisponível negam sem gerar recibos adicionais. Papel consumidor não pode ler nem apagar a tabela privada. O CI executa antes o F1-30 integralmente, incluindo 60 verificações F08, 45 F09 e 50 verificações F30.

## Restrições

- **Esta branch é filha do PR #73; não combina, faz merge ou implanta as branches HMAC F1-26 a F1-29.** Reproduz o princípio de autoridade independente adaptado à assinatura Ed25519.
- A autoridade de nonce não autentica o chamador: quem conhece um nonce pode pré-consumi-lo usando a função SQL, causando negação de serviço. Não expor essa função como API pública. A verificação da assinatura está no PHP consumidor; sem mTLS nem attestation de workload.
- O recibo pode ser consumido antes de qualquer leitura de campanha; não há exatamente-uma-execução, prova de titularidade de campanha, controle de concorrência de efeitos, rollback global, revogação monotônica nem proteção contra restore da própria autoridade.
- `SyntheticCurrentAuthority` é fabricável no código de teste; ainda não há fonte de identidade, vínculo contratual ou autorização real.
- Não usar este gate para Google Ads/Meta, CRM, faturamento, webhook, execução de IA, contratos ou usuários reais. Não há rotas Laravel, workers, VPS ou dados pessoais.
- Nenhuma aprovação técnica, operacional, de segurança AppSec ou mudança de P0/HT-R01/SEC-R03/T3 decorre do CI. Sem alterar `main` ou a branch `fast-track`.

**Próxima prova:** restaurar uma cópia anterior da **autoridade de recibos** e demonstrar que, sem referência externa não restaurável, um nonce pode ressurgir. Desenhar fence monotônico e ledger de idempotência por intenção antes de considerar adoção.
