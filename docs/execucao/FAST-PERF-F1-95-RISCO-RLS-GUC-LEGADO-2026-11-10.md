# FAST-PERF F1-95 — evidência reproduzível de risco legado de RLS por variável de sessão

Data: 10/11/2026. Código de laboratório executado em PostgreSQL16 **descartável**. Este relatório registra um risco **real do desenho de SQL legado**, sem testes contra contas ou servidores de clientes.

## Constatação

A role de teste `perf_f90_projection_reader` tem acesso SELECT direto às tabelas de contas, campanhas e vínculos (concedido por antigos workflows de homologação). As respectivas políticas RLS usam o tenant armazenado em `app.performance_tenant_id`. Como **qualquer sessão SQL com essas credenciais pode definir esse valor**, um único login consegue alternar A→B e ler as linhas de ambas as empresas fictícias.

O teste F95 comprova com o **mesmo `session_user`**:
- sem GUC, zero linhas;
- definindo GUC=A, 2 contas A + 1 vínculo e 1 campanha A;
- definindo GUC=B, 1 conta B + 1 vínculo e 1 campanha B;
- portanto o RLS legado **não autentica o tenant** de uma conexão à qual se concedeu SELECT direto.

O controle oposto também foi executado: na mesma base, o leitor F94 com `session_user=perf_f91_reader_a` recebe apenas sua campanha apesar da GUC forjada para B. O risco foi registrado em `contracts/lab/ci/performance-legacy-guc-risk-v1.json` com `risk_state=open_no_go` e `release_allowed=false`.

O workflow [F95 #42753531660](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753531660) **SUCCESS** significa que a falha **foi reproduzida** e a nova VIEW resistiu, não que a falha esteja corrigida em todos os papéis.

## Ação obrigatória antes de produção

**Não fornecer credenciais SQL diretamente a clientes ou conectores que possam escolher tenant.** Mapear todos os grants e roles da aplicação; retirar SELECT de tabelas-base dos papéis antigos e substituí-los por caminhos revisados com identidade independente (e provas AppSec/DBA de revogação/pooling). Migrar/remover F22–F28 legados antes de liberar a plataforma; testar lado a lado dados de A/B sem poder alterar a identidade da conexão. O IAM do Portal precisa autenticar e autorizar a organização e o serviço antes de qualquer consulta; F91/F93 são protótipos com proprietários elevados de VIEW e funções que precisam ser revisados.

**NO-GO:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, Portal/MFA/S2S, Google/Meta OAuth, LGPD, orçamento, idempotência e recuperação pós-restore, VPS e homologação. Main, VPS, produção, contas, anúncios, clientes e cobrança inalterados.
