# Imagine Performance P0 — Portal F826 MFA/services.manage até contrato V7 assinado

**14/11/2026. Somente CI de código PHP e dados fictícios, sem autenticação de cliente real.**

## Caminho testado

1. Portal F826 real, fixado no SHA `afdca172afd4813b21de1c2741ef72336e05c535`, compõe uma decisão **sintética** com `PortalAuthorityBoundary` exigindo sessão empresarial ativa, MFA, vínculo da sessão à organização, revisão atual de conta/vínculo/empresa, titularidade do acordo e a permissão `services.manage`.
2. Só depois de passar o pré-teste, `PortalPerformanceV7AuthorityPreflightLab` invoca o **emissor real PHP de revisão do plano V7**, que também verifica o contrato de ativação F419, o preço por canal e o HMAC de PSP/CRM.
3. O Performance `PortalV7SignedPlanAgreementLabVerifier`, uma implementação independente, valida os mesmos bytes de assinatura e os valores comerciais enviados com expectativas de tenant, acordo, competência, canais, orçamento, método PSP e revisão CRM conhecidas **fora do envelope**. Recusa replay do nonce na simulação.
4. Matriz adversarial: MFA ausente, sessão ou vínculo revogado, permissão somente leitura, cliente de outra empresa, organização inativa, sessão de convite, origem CRM, orçamento abaixo do mínimo e nonce reutilizado.

Cenário: Meta R$700 e Google R$1.000 de mídia planejada paga a plataformas; ativação R$980 (R$490/canal); gestão ilustrativa mensal R$370 (R$170 Meta + R$200 Google). Nenhum valor é cobrado pelo teste.

## Limites críticos

A assinatura **não inclui comprovação independente de que o preflight F826 ocorreu**. O emissor de plano V7 de nível inferior pode produzir um envelope de teste com a mesma estrutura; portanto a verificação do HMAC **não prova MFA/autorização real**. Este recorte apenas garante que o *caminho de integração testado* chama o pré-teste antes da assinatura. Não disponibiliza endpoint e não conecta IAM operacional.

Para o uso real, exige-se implementar um emissor autenticado de identidade Portal e/ou recibo vinculado a sessão/grant em serviço de confiança, evidência de aceite/consentimento versionado, revogação transacional, controle de credenciais/S2S e limites legais. O `PortalBoundaryVerdict` existente mantém `canAuthorizeHttp()=false`, e Performance `real_customer_contract_authenticated=false`, `billable=false`, `ads_write_enabled=false`.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
