# Performance AI P0 — Portal F829 SQL PG18 → HMAC comercial V7 independente

**14/11/2026. Somente CI; não é sessão nem aceite de cliente verdadeiro.**

## Objetivo

Provar que um plano comercial V7 só sai de um caminho de teste que consultou o banco do **Portal**: sessão MFA empresarial, permissão explícita `services.manage`, revisões atuais de identidade/organização/vínculo e um acordo por empresa/mês com digest estável de plano e termos. Em seguida o **Performance AI** verifica de forma independente a assinatura HMAC F419/V7 e os valores por canal, sem confiar em campos do próprio envelope como fonte da titularidade.

## Sequência testada

1. Workflow faz checkout do código Portal F829 **fixado em SHA `57f90caf04bb94e16b52d6e18195f7a364564c15`**, instala dependências e aplica as migrações F66/F76/F113 apenas numa instância PostgreSQL18 descartável do GitHub Actions.
2. Instala uma role SQL sem SELECT/UPDATE, uma role interna NOLOGIN, a função `SECURITY DEFINER` de sessão+MFA+`services.manage` e registro independente de acordo, termos e revisão com FORCE RLS.
3. Executa `test_portal_v7_pg18_identity_agreement.php` de forma real (fixtures fictícias). Só após os testes positivos, exporta para `/tmp/portal-f829-signed-plan-ci.json` uma **revisão comercial assinada e sem bearer, hash de sessão, CPF, IP, OTP, chaves ou linhas SQL**. A chave HMAC é uma constante sintética pública de CI, nunca segredo operacional.
4. `perf_portal_f829_pg18_iam_crossrepo_test.php` verifica independentemente o envelope Portal F419/V7 com expectativas independentes de empresa, acordo, mês, PSP, CRM, canais e gastos planejados. Recalcula o mesmo fingerprint estável do plano contratado e prova que alterações em mídia, tenant, acordo, orçamento, assinatura, PSP ou replay bloqueiam a revisão.
5. Compatibilidade financeira simulada: Meta R$700 + Google R$1.000 de investimento planejado, ativação R$980 e estimativa de gestão R$370, resultante de 20% por canal com mínimo R$170/canal em mês cheio.

## Limite

- **Não há autenticação real de usuário final ou aceite jurídico de cliente**. O banco contém identidade/sessão fictícias e o consentimento é inserido pelo administrador descartável.
- O HMAC V7 **não carrega certificado assinado da execução do predicado SQL**, podendo o emissor de nível inferior ainda criar um envelope do mesmo tipo em outro teste. Portanto a assinatura só comprova integridade da revisão comercial, não comprova IAM.
- Nenhum dado ou segredo C6/Asaas, ChatGPT Ads, Meta, Google Ads ou CRM é solicitado ou acessado.
- O teste de replay usa callback em memória. A versão cumulativa PR #404 adiciona nonce persistente no Performance PG18, mas essa prova entre bancos ainda é de laboratório e não transforma sessão em autorização de anúncio ou cobrança.
- Seguem obrigatórios: sessão Portal real e atestada, consentimento expresso versionado, auditoria e LGPD, canal S2S autenticado, bloqueio/rechecagem transacional de revogação, SEG/DBA/Financeiro, PSP sandbox, extratos oficiais Ads, NFS-e, backup e GO de homologação na VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
