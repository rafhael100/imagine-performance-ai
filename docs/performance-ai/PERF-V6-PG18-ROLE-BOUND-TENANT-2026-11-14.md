# Imagine Performance AI — reforço de isolamento de empresas PostgreSQL 18 (laboratório)

**14/11/2026 — recorte P0 segurança:** a PR #365 concluiu o contrato tricanal HMAC V6 com assinatura por contrato e fechamento atômico PostgreSQL 18, mas a política RLS original se apoiava em `current_setting('app.tenant_id')` — um valor **forjável pelo cliente SQL**. Esse não é um limite de autorização de produção.

## Nova prova com identidade de LOGIN do próprio PostgreSQL
- Schema novo `perf_fin_role_lab`, sem alterar as tabelas anteriores ou o checkout.
- Dois logins de banco mutuamente independentes: `perf_v6_tenant_alpha` e `perf_v6_tenant_beta`, `NOSUPERUSER`, `NOINHERIT`, `NOBYPASSRLS`, sem memberships cruzados nem direito a criar objetos, alterar dados ou trocar de conta de outro tenant.
- Tabela `login_tenant_registry` alimentada exclusivamente pelo administrador de CI; nenhum tenant tem SELECT/INSERT/UPDATE/DELETE.
- Função `tenant_for_authenticated_session()` com `SECURITY DEFINER`, esquema explicitamente qualificado e `search_path` fixo; resolve a empresa pelo **`session_user` autenticado**, não por uma GUC controlável ou por claims do aplicativo. A função expõe somente o identificador da empresa do login.
- Tabelas de nonce e rascunhos V6 com `FORCE ROW LEVEL SECURITY` e políticas USING/WITH CHECK que exigem `tenant_id = tenant_for_authenticated_session()`. Esse desenho impede um dos dois logins não privilegiados de ler ou inserir registros da outra empresa, mesmo alterando o GUC falso `app.tenant_id`.
- `PgV6RoleBoundAgreementCloseLab` exige correspondência entre `session_user`, binding protegido, empresa esperada, acordo assinado, mês, preço e mídia sintética; realiza nonce e draft em **uma só transação**.
- Testes de ataque: `SET ROLE` e `SET SESSION AUTHORIZATION` contra o outro login, GUC forjado, tentativa direta de INSERT de nonce alheio, troca do acordo HMAC, ausência de relatório, replay, roles sem acesso ao registro protegido, dois acordos na mesma empresa/mês, backup/restore em banco novo com RLS e nonces preservados.

## Restrições importantes
Isso é um **padrão de laboratório para isolamento de acesso SQL**, não um desenho operacional completo. Uma conexão por login/empresa tem implicações de gestão de papéis, pools, provisionamento, rotação e custos. O Portal ainda precisa comprovar IAM e titularidade de contratos em transporte S2S confiável; jamais aceitar credenciais SQL do navegador. Um processo com poder de superusuário ou de administrar o mapeamento poderá contornar esses limites — segregar funções e manter segredos em vault continua obrigatório.

A CI usa senhas sintéticas, banco descartável, dados fictícios e APIs externas desligadas. Não há prova de gasto de Google/Meta/ChatGPT, aprovação financeira, Asaas Sandbox, CRM consentido ou deploy na VPS. Nenhuma tabela existente de v2/v5/v6 é migrada.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
