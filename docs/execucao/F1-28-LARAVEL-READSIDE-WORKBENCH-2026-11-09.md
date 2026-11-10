# FAST-PERF F1-28 — Componente interno Laravel para consulta da gestão de campanhas

**Data-base:** 09/11/2026. Issue #68. PR draft filho F1-27 [#67](https://github.com/rafhael100/imagine-performance-ai/pull/67).

## Código incorporado ao namespace da aplicação
Diferentemente dos contratos PHP anteriores de laboratório, este recorte cria duas classes reais com autoload **`App\\` do Laravel 13** em `app/Performance/ReadSide/`, resolvíveis pelo container da aplicação sem registrar provedores, rotas ou listeners:
- `SyntheticWorkbenchScope` é um DTO readonly com construtor privado. Só cria o escopo fictício de teste A quando PHP CLI, `APP_ENV=testing` e flag F28 explícita estão ativos; fora disso, retorna `null`. Não é identidade real nem pode ser criada a partir de input HTTP.
- `SyntheticCampaignWorkbenchReader` recebe exclusivamente uma conexão `PDO` SQL de papel `perf_f28_app_reader` (SELECT-only, não-superuser), confere database/current_user, abre transação **READ ONLY**, vincula o tenant A na transação, valida vínculo mock Portal `active_demo` e revisão `demo-revision-1`, lê campanhas, solicitações e diário F1-27, e retorna apenas refs/títulos/status/métricas **fictícias** allowlisted sem UUIDs internos ou identidades de atores. Se uma decisão final não tiver um journal correspondente, a consulta é negada; não há sucesso parcial nem cache.

O componente é preparatório para uma futura camada de aplicação real, mas **não pode ser usado operacionalmente nesta versão**: não existe login, scopes de usuário, contrato comercial real, S2S, lookup dinâmico de cliente ou endpoint API. O escopo fixo A serve para provar integração segura de classe PHP com PostgreSQL, não autoriza qualquer pessoa. O estado de lançamento e os valores comerciais seguem explicitamente negados.

## Evidência de testes
O CI executa migrações Laravel em PostgreSQL16 descartável, F1-22 (35), F1-23 (56), F1-24 (50), F1-25 (comparação byte a byte), F1-26 (50), F1-27 (50) e `test-laravel-campaign-workbench-pg.php` com **50 verificações novas**: Laravel DI e PSR4, classes escopo, conexão com role exata, transação read-only, RLS, contagens da campanha A sem duplicidade, estados aprovados e rejeitados que exigem diário, preservação de pausadas, sem dados privados na saída, flag desligada, fonte mock Portal pendente/revisão desatualizada, perda de SELECT do journal, rollback e recuperação sem vazamento do GUC de tenant.

### Restrições e próximas etapas
A política SQL protege a consulta contra corrupção de estado e vínculo fictício; autenticação de usuário e workload, autorização comercial, permissões dinâmicas por empresa, papel PostgreSQL restrito gerenciado pelo runtime, cache invalidado por revogação e auditoria/AppSec ainda exigem homologação. Não extrapolar a segurança do laboratório para clientes reais.

**Sem merges, VPS, produção, rotas públicas, dados de cliente, Google/Meta Ads, cobrança ou despesas novas.** Gates SEC-R03/T3, P0, HT-R01 abertos.
