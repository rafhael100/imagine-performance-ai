# Performance AI P0 — Portal F832 e comprovante de apresentação do plano V7

**15/11/2026. CI PostgreSQL18 com dados fictícios, NO-GO para produção.**

O Portal F831 prova em testes que um evento `synthetic_accepted` foi vinculado ao ator derivado da sessão SQL empresarial com MFA. O novo Portal F832 exige uma etapa adicional **antes** desse evento: recibo de apresentação temporário, vinculado à versão de termos e ao mesmo resumo comercial do plano V7.

## Prova cross-repository

O workflow faz checkout do Portal [PR #836](https://github.com/rafhael100/imagine-portal/pull/836) no SHA imutável `df50af02ddb28edb85fb33aacb1dcb4adf7edfb2`, instala o esquema F66/F76/F113 e os SQL de laboratório F829/F831/F832 em PostgreSQL18 descartável. O teste do Portal executa uma sequência sintética de sete eventos: aceite sem recibo → revogação → reaceite sem recibo → revogação → novo aceite com recibo → revogação → reaceite com **outro recibo**, todos versionados.

O Performance, em `perf_portal_f832_terms_receipt_crossrepo_test.php`, verifica os HMACs comerciais F419/V7 independentemente, o contrato de Meta (R$700) e Google (R$1.000) de mídia planejada, ativação R$980, gestão estimada R$370 e o histórico PostgreSQL18 sem consumir credenciais de sessão, identidade ou OTP. Confirma que as revisões 5 e 7 possuem recibos distintos, em prazo, anteriores ao evento de aceite, derivados da mesma sessão e pertencentes ao mesmo ator/organização, e que os aceites antigos não ganham vínculo retroativo.

Mesmo que o HMAC comercial continue válido, a sessão revogada e as revisões do acordo alteradas no fim do ensaio fazem a consulta de consentimento do Portal retornar **falso**. Fraude de empresa, alteração de termos ou mídia, outra sessão e revisões obsoletas não conferem autorização. Roles SQL da aplicação não têm SELECT/INSERT/UPDATE/DELETE no histórico de apresentações.

## Limitações

A apresentação é **simulada por um script CLI**, não por tela realmente aberta no navegador. Não há registro de texto efetivamente exibido, usuário real, clique autêntico, validade jurídica ou credencial de produção. Nenhuma autorização de HTTP/Ads/CRM/PSP é emitida. A função SQL não deve ser oferecida como RPC pública; hashes de sessão e desafios são sensíveis. Falta canal S2S autenticado com revogação transacional até a borda da ação, revisão SEG/DBA/LGPD/Financeiro, sandbox, backups e GO.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
