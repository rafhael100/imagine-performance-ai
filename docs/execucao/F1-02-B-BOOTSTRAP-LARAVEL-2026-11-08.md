# FAST-PERF F1-02-B — Bootstrap Laravel 13 isolado (não homologado)

**Data-base:** 08/11/2026
**Autorização de recorte:** "todos aprovados" após fechamento do lote F1-02-A, interpretado estritamente como progresso em F1-02-B.
**Issue:** #13
**Branch:** `dev/perf-f1-02-b-laravel-bootstrap-2026-11-08`
**Status:** PR de código sob revisão, sem merge, deploy, APIs, banco ou custos.

## Escopo implementado na branch
- Declara `php:^8.3`, `laravel/framework:^13.0` e testes PHPUnit 12 em `composer.json`; nenhuma biblioteca de Ads, PSP, IA, n8n ou CRM.
- Cria entrada Artisan e bootstrap oficial candidato `Application::configure(...)`, provider sem integrações, arquivo de rotas console vazio.
- Mantém entrada `public/index.php` para estrutura **não implantada**, com **zero rotas HTTP** registradas e sem login/SSO.
- Usa `.env.example` SEM segredo; `APP_DEBUG=false`, stores em memória para cache/sessão e `DB_CONNECTION=unconfigured` para evitar conexão acidental.
- Declara testes Unit e Feature no `phpunit.xml`; testes propostos de manifesto, ausência de rotas e 404.

## Dependência bloqueadora real: Composer
O ambiente atual de preparação possui **PHP 8.4.24**, mas **não possui Composer nem acesso externo** para resolver Laravel/PHPUnit. Logo, **não há `vendor/` nem `composer.lock`**; NÃO gerar lock fictício ou afirmar `artisan test` executado.
A aplicação ainda **não está instalada nem validada como Laravel executável**. `php -l`, análise do manifesto e revisão estática verificam somente arquivos presentes.

Para homologar este lote, em ambiente autorizado com Composer:
1. Instalar dependências a partir de fontes confiáveis, com políticas de custos e segurança revisadas, gerando `composer.lock` verdadeiro.
2. Registrar `composer validate --strict`, versão do PHP, `composer install --no-interaction`, `php artisan about` e `php artisan test` para suites Unit e Feature, incluindo checks de ausência de rotas.
3. Após integração controlada do PR #12, adaptar seu script `tests/Unit/F102AAccessPolicyTest.php` à convenção PHPUnit para que a suite de testes receba efetivamente os **22 casos de domínio**. Não declarar os 22 como testes PHPUnit existentes. Evitar conflito ao reconciliar branches.
4. Só depois disso decidir CI sem custos, composer.lock e gate B. Nenhum comando remoto é ativado por este PR.

## Evidências e riscos
- Sem `routes/web.php`, `routes/api.php` ou endpoints liberados.
- Sem DB, migrations, SQL, RLS, webhooks, faturas, credenciais, Google/Meta, campanhas, Portal/CRM reais, mensagens ou IA autônoma.
- Revisar os requisitos de extensões PHP e a compatibilidade de futuras versões de pacotes antes de qualquer instalação.
- `main`, VPS, Cloudflare e produção continuam inalteradas. PRs #6/#7/#8/#10/#12 não recebem merge automático.
- **91 P0 / 15 HT-R01** mantidos. F1-02-C e INTEG-01 precisam de nova ordem de código.
