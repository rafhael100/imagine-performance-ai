# FAST-PERF F1-80 — ensaio integrado de homologação em CI descartável

Data-base: 09/11/2026. Branch filha de F1-79 / PR #178. Não é homologação de produção.

## Mudança concreta
O novo workflow `.github/workflows/perf-f1-80-integrated-preflight-ci.yml` executa, **no mesmo job com PostgreSQL 16 real descartável**, a cadeia de migrations Laravel, RLS por tenant e papel de leitura F28, os testes SQL de campanhas e aprovações F22–F28, as verificações de recuperação F74/F75, a reconciliação offline F76, o modelo de incidente F77, o painel offline F78 e a auditoria bloqueante F79.

Reaproveita o procedimento integral do workflow F28 já versionado, adicionando a ele a regressão F74–F79, validação `route:list` vazia, ausência de referências de fixtures em roteamento/providers e `composer audit` de dependências travadas. Usa somente serviço PostgreSQL 16 efêmero de GitHub Actions; nenhuma VPS ou API de anúncios. Está explicitamente desabilitado quando o repositório é privado para não iniciar execução hospedada potencialmente paga.

## Critério de aceite técnico desta fase
- Migrations reais devem aplicar no banco descartável `perf_test_campaign_registry`; a role restrita deve passar isolamento tenant e leitura somente-SELECT.
- Testes F74–F79 passam no mesmo checkout/ciclo de validação, evitando divergência entre branches.
- A auditoria F79 precisa retornar o **exit code 3** (NO-GO reconhecido). Qualquer retorno 0 ou outra saída faz o CI falhar.
- Nenhuma rota HTTP é habilitada e a aplicação não recebe endpoints de produção.
- Composer usa `composer.lock` e valida alertas conhecidos.

## Pendências de homologação verdadeira
Esta atividade NÃO fornece identidade real do Portal, permissões de anunciantes, provedores Google/Meta, idempotência externa, contador monotônico/WORM/KMS, restauração segura, LGPD operacional, revisão independente AppSec/DBA, liberação de P0/HT-R01/SEC-R03/T3, VPS, produção ou clientes. O resultado verde demonstra apenas que o conjunto de controles de laboratório não regrediu.

**NO-GO:** nunca fazer merge na `main`, publicar campanhas, habilitar cobrança ou usar contas reais como consequência desta tarefa.
