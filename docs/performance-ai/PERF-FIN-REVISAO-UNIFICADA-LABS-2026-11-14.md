# PERF — revisão unificada dos dois laboratórios financeiros (14/11/2026)

## Objetivo e escopo
Candidato **somente de revisão** que preserva as duas linhas já verificadas sem fazer merge em `main`, sem implantar VPS e sem usar credenciais reais.

- **Linha A — cotação assinada / PostgreSQL 16:** origem PRs #330, #331, #333, #336 e #337; emissor real pinado do Portal PR #767, assinatura HMAC com chave sintética, nonce único com RLS por tenant, fechamento não faturável e teste pg_dump/restore + replay.
- **Linha B — conferência visual / PostgreSQL 18:** origem PRs #329 e #332; `laboratory/postgresql` isolado, adapter PHP → SQL parametrizado, controles de duplicidade, página HTML offline somente para conferência e bloqueio de uso em produção.

## Regra comercial única
Honorários de gestão = **20% × mídia efetivamente investida** em canais autorizados. Planejamento mínimo **R$ 700 por canal/mês**, pago **diretamente às plataformas**, não um piso extra de honorários. Cotação inicial antecipada e acerto mensal sujeito a evidência e revisão financeira. Nenhum desses laboratórios emite cobranças ou créditos.

## Importante: duas alternativas de persistência
Os schemas `labs/finance-pg` (PG16) e `laboratory/postgresql` (PG18) são **alternativas de prova técnica**, não dois bancos que devam ser ativados em produção. Antes da integração operacional, decidir um único modelo canônico de ledger e idempotência, migrar/adaptar a interface HTML para esse modelo e eliminar a redundância. **Não fazer merge cego** destas duas trilhas em serviço real.

## Gates para homologação
1. Identidade vinculada ao Portal e autenticação mTLS/S2S com distribuição/rotação segura de chaves; proibir tenant escolhido livremente pelo cliente.
2. Ingestão oficial **somente leitura** de gastos Google e Meta: conta autorizada, fuso/competência, moeda, relatório de origem, estornos e atrasos de contabilização.
3. Ledger canônico por empresa, contrato/campanha, período e revisão; RLS confiável, auditoria, backup/restauração em infraestrutura operacional e resposta a incidentes.
4. Política contratual/financeira/fiscal para ajustes positivos e negativos, vencimentos e estornos, com alçadas humanas e trilha de aprovação.
5. Sandbox Asaas sob responsabilidade do Portal, com idempotência e webhooks autenticados, sem misturar compra de mídia com taxa de gestão.
6. Segurança SEG/DBA, IAM/LGPD, revisão funcional, testes E2E na VPS e procedimentos de deploy/rollback antes de GO.

**Status:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE. Sucesso de CI não comprova segurança de produção.
