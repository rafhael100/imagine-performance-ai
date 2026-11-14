# Performance AI — revisão integrada V6 com sessão de leitura Ads vinculada à mesma referência de credencial

**14/11/2026 — CANDIDATO DRAFT / NO-GO.** Este artefato é código de laboratório para revisão, não uma operação habilitada.

## O que foi integrado

- Regras comerciais tricanal já aprovadas: mídia mínima mensal Meta R$700, Google R$1.000 e ChatGPT R$1.200; referências recomendadas de R$1.000, R$1.500 e R$1.800. Honorários Imagine de 20% sobre mídia efetivamente investida, paga diretamente à plataforma.
- Contrato HMAC V6 candidato do Portal PR #778 vincula empresa, contrato, competência, catálogo e nonce. Laboratórios PostgreSQL 18: um rascunho por contrato/mês, vários contratos da mesma empresa, nonce consumido transacionalmente, restauração/replay e correções de gasto não faturáveis.
- RLS baseada em identidade de login PostgreSQL autenticada por `session_user` num ensaio segregado, superior ao GUC `app.tenant_id` forjável; ainda sem IAM de produção.
- Limites de requisição e revisão de respostas HTTP fictícias para Google/Meta/ChatGPT Ads. Account preflight ChatGPT verifica conta, moeda BRL, timezone e revisão da marca. O parser ChatGPT preserva numerais JSON de `spend` e usa centavos inteiros, sem float.
- Ensaio completo OpenAI Ads JSON → contrato Portal HMAC V6 → fechamento não faturável PostgreSQL18 validado na PR #376.
- **Nova proteção #378:** `ChatgptAdsReadOnlySessionLab` exige a **mesma referência opaca da credencial** entre `GET /v1/ad_account` e `GET /v1/ad_account/insights`. O primeiro GET deve ser validado antes de executar o segundo. Escopo/conta/consentimento/revogação são conferidos de antemão. Em erros 401/403, conta estrangeira, status/revisão não aprovado ou moeda/fuso divergentes, o fluxo termina ANTES de consultar Insights. Nenhum `Authorization`, token, URL externo, pedido Ads write, retry ou redirect é permitido. O callback continua fixture, sem rede real.

## NO-GO

- O login/usuário/contrato do Portal precisa de autoridade IAM real, atestado de serviço S2S e prova de autorização do anunciante para acesso à conta Ads.
- Chaves oficiais devem ser resolvidas por cofre de segredos e worker HTTP isolado, com limites de egress DNS/TLS, rotação/revogação, request signing, audit trail e autenticação do provedor. A callback atual não é implementação de HTTP confiável.
- São necessárias coleta autenticada Google/Meta/OpenAI Ads, verificação de conta ativa e gasto oficial, paginação/quotas e precisão de moeda por fornecedor; validação financeira contra extrato da plataforma.
- Asaas Sandbox, fiscal/NFS-e, política de correções, consentimento CRM LGPD, homologação de VPS/backup/monitoramento e gates SEG/DBA/LGPD/Financeiro continuam pendentes.
- Manter todas as flags de verificação, faturamento, APIs mutáveis e envio CRM em `false` até autorização formal, com mudanças auditadas por PRs separadas.

**Nenhuma credencial, rede real, anúncio, pagamento, cobrança, lead ou alteração da `main` nesta entrega.**

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
