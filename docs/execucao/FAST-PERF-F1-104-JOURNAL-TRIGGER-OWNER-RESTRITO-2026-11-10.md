# FAST-PERF F1-104 — proprietário restrito do trigger de revisões F27

Data: 10/11/2026. Base: F1-103; experimentação em PostgreSQL 16 **descartável** de GitHub Actions.

## Código implementado

A migration `2026_11_10_000004_dedicated_review_trigger_owner.php` cria o papel PostgreSQL `perf_f104_journal_writer` com `NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS`. Esse usuário não pode fazer login, não é acessível a nenhuma das roles de teste de empresas e recebe somente `USAGE` no schema `public` e `INSERT` na tabela `performance_intent_review_journal`.

Uma política `FOR INSERT TO perf_f104_journal_writer` exige `source=synthetic_postgresql_only`, `synthetic_only=true` e estado final `approved_internal` ou `rejected`. A função `SECURITY DEFINER` F27 passa a ser propriedade dessa role de menor privilégio, mantendo `search_path=pg_catalog` (F103), e continua sem permissão de EXECUTE para PUBLIC. O trigger original de auditoria permanece instalado; não foram adicionadas rotas HTTP.

A migration `down()` restaura o owner original antes de limpar a política/role dedicada, mas **não desfaz a restrição do search path F103**. Não é uma migração de produção homologada.

## Evidência

[GitHub Actions F1-104 #42771686427 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42771686427). O CI aplica todas as migrations em PostgreSQL16 descartável, inspeciona os campos reais de `pg_roles`, `pg_proc`, políticas e grants e comprova que a role dedicada:
- Não pode fazer login, criar papéis, criar bancos ou ignorar RLS;
- Não pode ler, atualizar, excluir, truncar ou alterar o journal; nem acessar campanhas, intenções, contas ou mapeamento de empresas;
- É inacessível por `SET ROLE` a usuários de leitura;
- Continua permitindo que o trigger F27 registre uma revisão final fictícia com um único journal correto;
- Mantém leitura da revisão sob a VIEW protegida F97 apenas na empresa A, nunca na B.

O mesmo workflow reexecuta as regressões F22–F28, F74–F103, e renderiza o HTML do Portal F63 usando JSON do PostgreSQL F100. Composer audit e condições NO-GO também foram aprovados.

## Restrições importantes

Essa função ainda é um mecanismo **de journal sintético**, não um sistema de aprovação humana. A política de INSERT não autentica pessoas, contratos ou anunciantes; o owner da tabela do journal e demais funções/views com privilégios elevados continuam sujeitos à revisão independente AppSec/DBA. O desenho F91/F93/F97 não foi liberado para clientes e não resolve os papéis SQL legados dependentes de GUC. Faltam IAM/MFA/S2S real do Portal, OAuth/consentimento Google Ads e Meta, LGPD, limites financeiros, idempotência/reconciliação externa, recuperação antirollback, P0/HT-R01/SEC-R03/T3, VPS e homologação.

**Status geral:** draft/NO-GO. Nenhuma alteração na `main`, VPS, Ads, produção, clientes, cobrança ou fornecedores.
