# FAST-PERF F1-52 — Escopo sob gestão, campanhas e grupos (núcleo de domínio)

**Data:** 09/11/2026. **Status:** código sintético isolado, candidato ao MVP; não equivale a homologação comercial nem operação de anúncios. Esta branch deriva da F1-51 PR #124 e mantém o estado NO-GO de produção.

## Problema resolvido no modelo

Até F1-51 a maior parte da execução concentrou-se em protocolos de autorização, restauração e provas de vulnerabilidades. O MVP precisa também de regras explícitas para campanhas e grupos/conjuntos **formalmente sob gestão**, respeitando isolamento entre empresas e sem permitir que uma pausa libere capacidade automaticamente.

A classe pura PHP `SyntheticManagedScopeLedger`, em `src/Domain/Performance/`, permite apenas uma simulação em memória, com `APP_ENV=testing`, PHP CLI e `PERF_F52_SCOPE_LAB=1`:

- Capacidade fornecida **apenas como parâmetro de demonstração**, separada por empresa e canal `google_ads`/`meta`. Não define número de campanhas, preço, cota vendável, contrato ou direito adquirido. Capacidade desconhecida implica bloqueio.
- Entrada formal de campanhas, grupos Google e conjuntos Meta em escopo de gestão, com IDs históricos protegidos contra reaproveitamento e negação de acessos cruzados entre empresas.
- Contagem de campanhas ativas e pausadas dentro do escopo, segundo a diretriz aprovada RES01-D05. Para grupos/conjuntos, a contagem similar permanece **hipótese de produto em teste**, não decisão contratual.
- Pausar campanha/grupo altera somente o status interno da simulação e **não libera capacidade**. Retirada formal do escopo exige aprovação humana fictícia. Nenhuma pausa, exclusão ou publicação na plataforma de mídia é realizada.
- Substituição ordinária em um único passo da memória: requer recomendações Imagine e aprovação humana **simuladas**, encerra o escopo da campanha e dos grupos antigos e inclui a nova como `draft_demo`. Não permite sobreposição automática nem gera taxa de troca/boletos/recarga/cobrança.
- Auditoria local segregada por empresa e marcada `billing_effect=none_synthetic`. Nenhum endereço, token, cliente, verba, preço comercial ou credencial real entra no modelo.

## Verificações

O teste `tests/Unit/SyntheticManagedScopeLedgerStandalone.php` executa **82 cenários**: quota não configurada, quota cheia, quota por canal, validação de IDs, negação cross-tenant, grupos órfãos, campanhas e grupos pausados, tentativa de reduzir limite abaixo do uso, swap autorizado ou negado, IDs históricos, liberação formal, ausência de cobrança, auditoria isolada e bloqueio completo sob `APP_ENV=production`.

Workflow isolado `.github/workflows/perf-f1-52-managed-scope-ci.yml` usa PHP 8.4, não instala banco, não acessa Google/Meta e não vincula classes de simulação a Laravel/rotas. A suíte F1-51 previamente validada permanece como evidência independente de segurança, mas **este workflow F1-52 não substitui todas as regressões SQL anteriores**.

## Pendências e limites

A classe não deve ser conectada a API externa nem interpretada como autorização de gestão. A aprovação é um booleano fictício; identidade Portal, segregação real de usuários, auditoria persistente, recuperação, concorrência transacional SQL, limites comerciais e validação financeira não estão implementados. O tratamento de coexistência temporária, Performance Max, grupos de recursos, marcas, contas e adicionais não está homologado. Não criar preços a partir de AD01 e não contabilizar o status do Google/Meta como escopo contratual.

**P0, HT-R01, SEC-R03/T3, Issues #102/#115/#117/#119/#121/#123, AppSec/DBA e contratos Portal mantidos abertos.** Sem merge, `main`, VPS, deploy, Google/Meta real, CRM, n8n, pagamentos, clientes nem despesas.

## Próximos incrementos executáveis

Criar projeções de ocupação e proposta de capacidade para o painel do Portal utilizando dados sintéticos; introduzir testes de contrato versionado que não contemplem preços ou faturamento; desenhar persistência SQL transacional sob RLS sem habilitar endpoints, condicionada à revisão dos contratos e gates.