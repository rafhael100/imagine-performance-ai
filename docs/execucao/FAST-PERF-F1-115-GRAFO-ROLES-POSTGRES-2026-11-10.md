# FAST-PERF F1-115 — grafo de memberships e atributos de roles PostgreSQL

Data: 10/11/2026. Base F114, PostgreSQL16 efêmero.

## Entrega técnica

O auditor isolado em `contracts/lab/php/ProtectedSqlPrincipalMembershipAudit.php` estende a segurança das ACLs F112–F114 para o **grafo de membership de roles PostgreSQL** em `pg_auth_members`: nenhuma das oito roles legadas, dos dois leitores F91 ou da role de journal F104 pode **ser membro de outra role nem ser concedida a outra role** no modelo sintético. O teste observa `admin_option`, `inherit_option` e `set_option` do PostgreSQL16 e verifica `NOSUPERUSER`, `NOCREATEROLE`, `NOCREATEDB`, `NOBYPASSRLS`, `NOREPLICATION`, login e `NOINHERIT` da role de trigger F104.

O modelo bloqueia inclusive associação nominal aparentemente inócua sem herança/SET ROLE, evitando mudanças posteriores que ativem direitos latentes. O auditor é somente leitura e, por exigir ambiente local descartável, não executa no Portal ou API.

## Validação

[GitHub Actions F115 #42857610360 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42857610360): **53 verificações F115**, provocando e detectando no banco real de CI:
- login legado F28 herdando e podendo `SET ROLE` de grupo SQL não previsto;
- grupo desconhecido recebendo capacidade de `SET ROLE` para login protegido F91;
- grupo desconhecido herdando o owner restrito da função de auditoria `perf_f104_journal_writer`;
- membership de leitor protegido mesmo sem `INHERIT` ou `SET`;
- ativação de `BYPASSRLS` no leitor F91 e concessão de `LOGIN`/`INHERIT` no owner F104.

Após cada remoção, o grafo de permissões volta ao baseline. O mesmo CI reexecutou F112 (**786**), F113 (**63**), F114 (**49**), cálculo monetário F105 (**29.366**), regressões PostgreSQL/Laravel→Portal F66, Composer audit e proibição de rotas/deploy.

## Limites

O grafo de membership é **do banco efêmero**, não de uma VPS ou produção. O scanner pressupõe ausência total de memberships nas 11 roles de teste, uma política adequada ao laboratório, **não uma política já aprovada para ambientes comerciais**. Ainda são necessárias revisão de ownership de objetos, `SECURITY DEFINER`, política de conexão/pooling e identidade Portal real, inventário e aprovação independente AppSec/DBA. A migração F112 não foi aplicada a ambientes externos.

**NO-GO:** OAuth Google Ads/Meta, consentimento LGPD, orçamento e aprovações, IAM/MFA/S2S do Portal, VPS, P0/HT-R01/SEC-R03/T3 e homologação de produção pendentes. Sem merge/main, deploy, usuários reais, campanhas ou cobranças.
