# PAR-03 — Plano de casos e dados 100% sintéticos (NÃO EXECUTADO)

**Data:** 08/11/2026 · **Issue:** #3 · **Escopo:** FAST-PERF-01/PARALLEL-01  
**Estado global:** **TODOS OS CASOS NÃO EXECUTADOS**. Documento de especificação, não relatório de cobertura, homologação, CI ou teste de produção. Nenhuma tecnologia, conta, servidor, pagamento ou fornecedor ativado.

## 1. Massa de dados fictícia e regra de isolamento

| Campo | Tenant A | Tenant B |
| --- | --- | --- |
| Organização Portal fictícia | `org-demo-aurora` | `org-demo-brisa` |
| Tenant Performance fictício | `perf-demo-aurora` | `perf-demo-brisa` |
| Serviço sintético | `svc-demo-a-01` | `svc-demo-b-01` |
| Usuário de teste | `user-demo-a-analyst` | `user-demo-b-admin` |
| Conta Google mockada | `google-demo-a-01` | `google-demo-b-01` |
| Conta Meta mockada | `meta-demo-a-01` | `meta-demo-b-01` |
| Campanha fictícia | `camp-demo-a-01` | `camp-demo-b-01` |
| Grupo/conjunto fictício | `group-demo-a-01` | `group-demo-b-01` |

São etiquetas fabricadas, não contas reais ou endereços. Nenhum e-mail, telefone, CNPJ, documento pessoal, token ou segredo integra os dados. Os papéis e limites são **simulações para testes**, sem aprovação de planos comerciais.

Todas as expectativas abaixo exigem, quando implementadas, revalidação de tenant, ator, contrato, recurso, escopo e serviço no backend. A interface Portal nunca substitui autorização do motor.

## 2. Matriz de cenários propostos

**Legenda:** risco `CRIT`, `ALTO`, `MED`; dependência `F1` = aprovação stack, `S` = PAR-02, `I` = contrato INTEG-01/Portal, `AD` = AD01. **Todos têm status NÃO EXECUTADO.**

| ID | Pré-condição sintética / ação | Resultado esperado | Risco | Dependência | Status |
| --- | --- | --- | --- | --- | --- |
| TS-01 | Usuário A tenta ler campanha `camp-demo-b-01` | Negado; sem vazamento de B | CRIT | F1,S | NÃO EXECUTADO |
| TS-02 | A troca `tenant_id` no payload para B | Ignorado/rejeitado; tenant derivado da autoridade | CRIT | F1,S | NÃO EXECUTADO |
| TS-03 | A pede listagem com `organization=B` | Nenhuma linha de B | CRIT | F1,S | NÃO EXECUTADO |
| TS-04 | A tenta gravar grupo de B em campanha de A | Negado; integridade preservada | CRIT | F1,S | NÃO EXECUTADO |
| TS-05 | Usuário inativo reutiliza concessão antiga | Negado com auditoria | CRIT | F1,S | NÃO EXECUTADO |
| TS-06 | Consulta sem autenticação tenta acessar resumo | Negada sem dados | CRIT | F1,S | NÃO EXECUTADO |
| TS-07 | Analista tenta aprovar sua mudança de valor | Negado por alçada/segregação | ALTO | F1,S | NÃO EXECUTADO |
| TS-08 | Aprovação antiga é reutilizada após mudar manifesto | Rejeitada; nova aprovação exigida | CRIT | F1,S | NÃO EXECUTADO |
| TS-09 | Suporte sem delegação acessa tenant B | Negado e auditado | ALTO | F1,S | NÃO EXECUTADO |
| TS-10 | Consulta/log/cache de A contém identificador de B | Não revelar nenhum dado de B | CRIT | F1,S | NÃO EXECUTADO |
| TS-11 | Evento elegibilidade `evt-demo-1` recebido 2 vezes | Um único provisionamento mock e duplicata auditada | ALTO | F1,I | NÃO EXECUTADO |
| TS-12 | Mesmo `event_id` com payload divergente | Não sobrescrever estado; revisão necessária | ALTO | F1,I | NÃO EXECUTADO |
| TS-13 | Pedido `pending_payment` tenta habilitar serviço | Não habilitar nem anunciar pagamento confirmado | CRIT | F1,I | NÃO EXECUTADO |
| TS-14 | Serviço cancelado pede nova ação Performance | Ação bloqueada; não afirmar pausa oficial de ads | ALTO | F1,I | NÃO EXECUTADO |
| TS-15 | Tenant A acessa status do serviço B | Negado e sem inferência | CRIT | F1,S,I | NÃO EXECUTADO |
| TS-16 | Evento de workload simulado como inválido/expirado | Negado, sem alteração de estado | CRIT | F1,S,I | NÃO EXECUTADO |
| TS-17 | Falha temporária no adapter mock | Erro tipado, retry controlado e deduplicável | ALTO | F1,I | NÃO EXECUTADO |
| TS-18 | Campanha ativa formalmente sob gestão | Ocupa capacidade mockada | MED | F1,AD | NÃO EXECUTADO |
| TS-19 | Campanha pausada mas ainda sob gestão | Continua ocupando capacidade | ALTO | F1,AD | NÃO EXECUTADO |
| TS-20 | Proposta de retirada de campanha sob gestão | Não libera capacidade antes da retirada formal | MED | F1,AD | NÃO EXECUTADO |
| TS-21 | Retirada formal confirmada e auditada | Libera capacidade de gestão; não apaga campanha externa | ALTO | F1,AD | NÃO EXECUTADO |
| TS-22 | Substituição ordinária recomendada na capacidade existente | Não supor taxa nem bloqueio por número de swaps | MED | F1,AD | NÃO EXECUTADO |
| TS-23 | Franquia numérica do plano indefinida (`null`) | `unknown/not_for_sale`; não tratar como ilimitada ou zero | ALTO | F1,AD,I | NÃO EXECUTADO |
| TS-24 | Resumo Google/Meta mock exibido no Portal | `demo` visível, proveniência e timestamp fictícios | ALTO | F1,I | NÃO EXECUTADO |
| TS-25 | Proposta de aumento de orçamento de mídia | Apenas registro pendente; nenhum comando real ou gasto | CRIT | F1,S | NÃO EXECUTADO |
| TS-26 | Geração de relatório fictício por IA N1 | Apenas recomendação; sem mutação/publicação | ALTO | F1,S | NÃO EXECUTADO |
| TS-27 | Cálculo compara despesa Google/Meta à mensalidade Imagine | Fontes/contas separadas; nunca cobrar mídia via Imagine implicitamente | ALTO | F1,I | NÃO EXECUTADO |
| TS-28 | Suspensão do serviço e continuidade de campanha externa | Estado “desconhecido” até evidência do provedor, sem falso stop | ALTO | F1,I | NÃO EXECUTADO |
| TS-29 | Reprocessar mensagem de atualização duplicada da campanha | Resultado idempotente, trilha de tentativas | ALTO | F1,I | NÃO EXECUTADO |
| TS-30 | Tentativa de resposta contendo token, cookie ou dado fictício marcado segredo | Sanitização/negação; nenhum segredo nos logs | CRIT | F1,S | NÃO EXECUTADO |

## 3. Casos de integração e falhas não cobertos por testes locais

Os adapters Google/Meta serão `mock` até autorização específica. Nem um teste “verde” de mock autoriza OAuth ou Ads API. A interface comercial do Portal é autoridade para pedido, contrato, elegibilidade e preço. Nenhuma simulação de pagamento valida PSP, fiscal, LGPD, titularidade de contas publicitárias ou readiness.

Incluir futura matriz de falhas de rede e reconciliação, expiração de aprovações, reordenação de eventos, mudanças de assinatura, cache de status, concorrência e limites; ajustar pré-condições após F1-01, PAR-02 e INTEG-01. **Não executar nem publicar** mensagens/e-mails/WhatsApp, anúncios, mutations ou eventos em infraestrutura real nesta etapa.

## 4. CI candidata — sem execução nesta rodada

1. Validação de sintaxe/compilação e linter conforme framework a homologar.
2. Testes unitários de policies/serviços com banco de testes descartável e massa isolada.
3. Testes negativos de todos os recursos multiempresa, inclusive listas, contagens, joins e logs.
4. Checagens de migrations: isolamento, constraints, rollback/reaplicação e não exposição de valores sensíveis.
5. Detecção de segredos no diff e verificação de dados sintéticos.
6. Testes de idempotência, limites, aprovação/revogação e ausência de chamadas externas.
7. Análise de dependências e execução sem serviços pagos, quando a stack for decidida.
8. Relatórios por commit/PR com SHA, comandos, versão, ambiente e resultados **somente após execução efetiva**.

## 5. Gates e critérios para transformar plano em testes reais

Exigir decisão de stack F1-01, revisão de permissões PAR-02, autorização de lote de código, evidências de implementação, massa artificial reprodutível e matriz de expectativas versionada. Contratos de integração são candidatos, não endpoint pronto. P0 e HT-R01 seguem abertos; preço, capacidades, alçadas e políticas de retenção não homologados devem permanecer `unknown/not_for_sale`.

**Aceite documental desta rodada:** somente este arquivo criado em feature branch independente, PR draft com base `dev/fast-track-performance-2026-11-05`, sem merge e sem alteração de `main`/VPS ou testes executados.

**Referências:** `AGENTS.md`, `STATUS-ATUAL.md`, `PARALLEL-01-COORDENACAO.md`, `PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md`, `PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md`, `PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`, `PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md`.
