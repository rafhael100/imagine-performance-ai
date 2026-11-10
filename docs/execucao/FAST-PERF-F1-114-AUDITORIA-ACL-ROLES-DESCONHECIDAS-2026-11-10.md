# FAST-PERF F1-114 — auditoria de ACLs para roles SQL desconhecidas

Data: 10/11/2026. Base F113. Uso **exclusivo no PostgreSQL descartável do CI**.

## Entrega técnica

O scanner `contracts/lab/php/UnknownPrincipalAclAudit.php` consulta `pg_class.relacl`, `pg_attribute.attacl` e `aclexplode` e compara o grafo de grants efetivamente registrados para **13 tabelas-base e quatro views** com uma política explícita de ACLs esperadas.

Apenas as duas roles credencial-bound podem ter SELECT nas quatro views sintéticas; a role sem login `perf_f104_journal_writer` pode ter INSERT no journal fictício; qualquer outra concessão explícita é reportada e impede o fechamento do laboratório. Inclui **novas roles SQL não previstas** pelas verificações F112/F113, `PUBLIC`, grants por coluna, permissões de INSERT no journal e UPDATE inesperado de views.

O teste gera uma role desconhecida NOLOGIN, concede e revoga SELECT na tabela de campanhas, SELECT em uma coluna de orçamento, INSERT no journal, SELECT na view de orçamento, SELECT ao PUBLIC e UPDATE ao leitor F91. A auditoria precisa falhar em todos os casos e voltar ao estado anterior após a revogação. O código permanece somente em `contracts/lab`, sem API ou conexão com clientes.

[GitHub Actions F114 #42850987297 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42850987297): **49 verificações F114**, além das F112 (786), F113 (63), F105 (29.366), regressões PostgreSQL/Laravel/Portal F66/Composer e proteções NO-GO.

## Limites relevantes

Esse inventário inspeciona **ACLs registradas em objetos**, não resolve sozinho a segurança dos membros de roles, capacidade de `SET ROLE`, herança, ownership, superusuários, funções `SECURITY DEFINER`, permissões default futuras ou usuários operacionais desconhecidos fora do laboratório. A revisão F115 aborda a associação de roles SQL, ainda apenas em CI.

**NO-GO:** migração de grants F112 só em CI, AppSec/DBA independente, IAM/MFA/S2S Portal, tokens e autorização real Google/Meta, LGPD, orçamento, VPS e homologação pendentes. `main`, anúncios, produção e cobranças não alterados.
