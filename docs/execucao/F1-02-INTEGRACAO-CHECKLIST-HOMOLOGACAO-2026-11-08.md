# Execução e próximos gates — Imagine Performance AI

**Registro:** 08/11/2026, Issue #15, branch de integração F1-02.

## Resultados efetivamente verificados
- **F1-02-A (PHP puro):** 22 cenários standalone previamente executados e aprovados localmente com PHP 8.4.24.
- **Novo PR de integração:** contém arquivos do F1-02-A e F1-02-B e migrations F1-02-C; nenhuma alteração da `main`.
- **Composer:** indisponível no ambiente desta rodada; `composer install`, `composer validate`, Laravel `artisan` e PHPUnit **NÃO EXECUTADOS**.
- **PostgreSQL:** driver PDO pgsql não disponível, banco descartável não provisionado; migrations, RLS e três testes reais **NÃO EXECUTADOS**.
- A suíte standalone **não substitui** testes de autenticação real, SQL/RLS, APIs ou PSP.

## Resolução de dependências antes de qualquer merge ou deploy
1. Em ambiente aprovado, instalar PHP 8.3+ com extensões necessárias e Composer a partir de origem autenticada.
2. Em branch de desenvolvimento isolada, executar `composer validate --no-check-publish`, resolver e gerar um **composer.lock real**, instalar dependências e rodar `php artisan test` e `php tests/Unit/F102AStandalone.php`.
3. Confirmar que `phpunit.xml` executa Unit e Feature, inclusive checks sem HTTP público.
4. Em **PostgreSQL descartável**, banco nomeado `perf_test_<sufixo>`, migrar por ordem, verificar rollback e testes RLS. Rodar testes de banco apenas com `APP_ENV=testing`, `DB_CONNECTION=pgsql` e `RUN_PGSQL_ISOLATION_TESTS=1`.
5. Testar sobreposição e conflito de eventos `producer_ref + event_ref`, concorrência e expiração/revogação; implementar adaptador que valide tenant/ator antes de definir a variável SQL por transação; nunca aceitar tenant de HTTP sem verificação.
6. Revisar privilégios do papel SQL, BYPASSRLS e imutabilidade da auditoria; sem isso schema/RLS não está homologado.
7. Somente depois planejar a integração mock Portal → Performance com os contratos versionados, sem transportar sessão/segredo. Ainda não há API real, login ou checkout.
8. **Não colocar `main` em produção sem novo checklist de readiness**: 91 P0 e 15 gates HT-R01 não fecham automaticamente.

## Precauções
- A versão da stack segue Laravel 13 + PHP ^8.3 + PostgreSQL, independente do CRM e Portal.
- A função `SyntheticAuthorizationContext::forTest` é exclusivamente sintética; não confiar nela fora dos testes.
- RLS via configuração por transação é defesa adicional, não autenticação.
- Não carregar dados dos dois interessados sem contrato e LGPD aplicáveis.
- Google/Meta, PSP, OAuth, n8n, servidores e gastos seguem sem operação nesta rodada.

## Rastreabilidade
- PRs antigos: #6 #7 #8 #10 #12 #14.
- Issue da integração: #15.
- Sem merge automático e sem infraestrutura contratada nesta execução.


## Atualização de evidências verificadas em GitHub Actions — 08/11/2026

Esta seção é posterior ao registro inicial de impedimentos de validação local. Os bloqueios de Composer e PostgreSQL **foram superados em runners públicos descartáveis**, sem usar VPS.

- **CI F1-03:** [Run #42532703509](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42532703509): ambos os jobs concluídos com sucesso.
- **PHPUnit Unit:** **9 testes, 14 asserções**, PASS.
- **PHPUnit Feature (sem DB):** **3 testes executados**, 7 testes PostgreSQL pulados propositalmente nessa suíte; zero falhas.
- **PostgreSQL 16 descartável:** `php artisan migrate --database=pgsql --force` PASS; RLS testado com usuário SQL `perf_test_role` sem acesso administrativo, **7 testes e 45 asserções PASS**.
- **Testes de domínio PHP puro:** 22 + 26 casos sintéticos PASS, incluindo deduplicação, auditoria por tenant e indisponibilidade de serviço.
- **Rotas de filesystem Laravel 13:** GET/PUT `/storage/{path}` desabilitadas em `config/filesystems.php`, testes correspondentes PASS.
- **Composer:** validação do manifesto, resolução e auditoria sem alertas de vulnerabilidade conhecidos; após [Run #42532859480](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42532859480), lockfile real `composer.lock` gerado e versionado no commit `b936fbb84eae14daac97e143f1d46b1193719302`, sem cache de artefatos pagos.
- **Etapa atual:** retestar com `composer install` a partir do lockfile versionado; registrar resultado no próximo CI.

**Limites de alcance dos testes:** testes comprovam apenas o comportamento das fixtures e do esquema no ambiente descartável. Não houve autenticação serviço-a-serviço real, integração com Portal/CRM real, Google/Meta, PSP, dados pessoais de clientes, tráfego, implantação, monitoração ou teste de carga. Revisão de RBAC completo, grants SQL finos, recuperação de desastres e gates jurídicos/comerciais seguem abertos.
