# FAST-PERF F1-122 — revogação concorrente nos leitores Laravel de campanhas e revisões

Data: 10/11/2026. Continuação F120–F121. **Somente demonstrativos PostgreSQL16 no GitHub Actions**, sem integração publicitária ou dados de clientes.

## Implementação efetiva

Classes `app/Performance/ReadSide/CredentialBoundDemoCampaignReader.php` (F94) e `app/Performance/ReadSide/CredentialBoundDemoReviewReader.php` (F98) agora:
- Rejeitam transações externas de escrita, `REPEATABLE READ` ou `SERIALIZABLE`, por risco de visão obsoleta da revogação;
- Aceitam explicitamente somente transações `READ COMMITTED READ ONLY`, usadas pelo workbench F100 para compor contas, campanhas e revisões, sem assumir controle/commit da transação alheia;
- Consultam novamente `current_database()`, `session_user` e `perf_f91_tenant_for_login()` após carregar e validar cada projeção;
- Se a associação do login autenticado for revogada ou movida para outro tenant durante o SELECT, retornam `credential_changed_during_read` com listas **vazias** e todos os campos de autorização de mídia e faturamento falsos.

Esta segunda validação corresponde à proteção aplicada no leitor de orçamento F107/F111 e no leitor de contas F92/F121, uniformizando o comportamento dos três componentes operados pelo workbench offline F100. Ela **não elimina a corrida após a última consulta**, nem fornece autenticação real do Portal.

## Testes comprovados no PostgreSQL

[GitHub Actions F122 #42873849184 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42873849184): **139 verificações F122**.

- SQL login A e B produzem apenas os próprios candidatos de campanha/revisão sob VIEWs F93/F97, sem IDs ou tokens de anunciantes reais;
- F103 e F104 já criam duas revisões de terminal adicionais **sintéticas** para A nos testes anteriores. O F122 valida exatamente três referências esperadas em A e uma rejeição em B, evitando mascarar alterações de dados com checagens genéricas;
- Para ambos os leitores, conexão `READ COMMITTED READ ONLY` de F100 aceita consulta; transações externas `READ WRITE` e `REPEATABLE READ` recusam;
- PDO instrumentado faz revogação e reassociação A→B por conexão administradora **após a SELECT e antes do retorno**; a resposta é rejeitada, limpa, e a empresa B continua isolada;
- Tentativas de forjar `app.performance_tenant_id` A↔B não alteram as projeções após F119;
- Ambiente de produção é recusado; o manifesto de release continua `open_no_go`.

O mesmo CI validou F100 e F121 (96 verificações), F120 (81), F119 e todas as regressões PostgreSQL/Laravel/Portal F64–F66, Composer audit e ausência de novos endpoints/deploy. Uma expectativa inicial de apenas uma revisão A foi corrigida **após constatar as duas revisões sintéticas válidas F103/F104 e checar suas referências exatas**, sem relaxar o isolamento.

## Dependências comerciais pendentes

Permanece necessário vincular o contexto de usuário/empresa/serviço a identidade IAM/MFA/S2S verificada independentemente do cliente, revisar conexões PostgreSQL, pooling, grants e owners por AppSec/DBA externo, aplicar migrações só após inventário e homologação na VPS, integrar OAuth/permissões Google Ads e Meta, LGPD, alçadas humanas e limites de orçamento, antifraude, idempotência/reconciliação, backup/recuperação e gates P0/HT-R01/SEC-R03/T3.

**PR draft. NÃO lançar, mesclar em main, instalar migrations de laboratório na VPS, anunciar ou cobrar.**
