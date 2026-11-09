# Imagine Performance AI — Entregas de domínio e experiência do MVP (F1-52 a F1-56)

**Atualizado em:** 09/11/2026. **Ambiente:** branches isoladas, testes sintéticos, nenhum acesso a clientes ou fornecedores. A `main` e a infraestrutura não foram alteradas.

## O que foi desenvolvido e testado

| Etapa | Entrega | Evidência |
| --- | --- | --- |
| F1-52 / [PR #126](https://github.com/rafhael100/imagine-performance-ai/pull/126) | Escopo sob gestão de campanhas e grupos/conjuntos por tenant/canal; pausas contam; simulação de substituição sem tarifa e com revisão fictícia | [82 testes PASS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42672359287) |
| F1-53 / [PR #128](https://github.com/rafhael100/imagine-performance-ai/pull/128) | DTO versionado e de somente leitura para futura visualização Portal; bloqueio de referências cruzadas e de flags comerciais | [67 cenários próprios PASS + F52](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42673232973) |
| F1-54 / [PR #130](https://github.com/rafhael100/imagine-performance-ai/pull/130) | Interface HTML/CSS/JS responsiva, offline, filtros Google/Meta, gestão, revisões e relatórios não reais | [44 cenários próprios PASS + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42674543911) |
| F1-55 / [PR #132](https://github.com/rafhael100/imagine-performance-ai/pull/132) | Geração de HTML offline a partir do DTO PHP para eliminar divergência de dados | [31 verificações próprias PASS + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42675231626) |
| F1-56 / [PR #134](https://github.com/rafhael100/imagine-performance-ai/pull/134) | Exportação CSV sintética, somente leitura, sem cobrança, com proteção contra rótulos maliciosos | [49 verificações próprias PASS + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42675979293) |

**Total de verificações distintas dessas cinco etapas:** 82 + 67 + 44 + 31 + 49 = **273**. O workflow F1-56 executou todas no mesmo CI. Testes F1-30–F1-51 são evidências separadas, não reexecutadas nesta suíte rápida. Um CI verde **não é aceite de produção**.

## Como revisar offline (sem servidores)

Use PHP 8.4 e Node 20+ no ambiente de desenvolvimento isolado, sem credenciais reais:

```bash
APP_ENV=testing PERF_F52_SCOPE_LAB=1 php tests/Unit/SyntheticManagedScopeLedgerStandalone.php

APP_ENV=testing PERF_F52_SCOPE_LAB=1 PERF_F53_PORTAL_PREVIEW_LAB=1 php contracts/demo/f53-generate-scope-preview.php

APP_ENV=testing PERF_F52_SCOPE_LAB=1 PERF_F53_PORTAL_PREVIEW_LAB=1 PERF_F55_RENDER_LAB=1 php contracts/demo/f55-render-offline-preview.php > /tmp/performance-demo.html

APP_ENV=testing PERF_F52_SCOPE_LAB=1 PERF_F53_PORTAL_PREVIEW_LAB=1 PERF_F56_CSV_LAB=1 php contracts/demo/f56-export-capacity-csv.php > /tmp/performance-capacidade-demo.csv
```

Abrir `/tmp/performance-demo.html` no navegador local. Alternativamente, abrir diretamente `contracts/demo/performance-scope-preview-v1.html`. São arquivos demonstrativos, **não hospedagem de aplicação**.

## Regras respeitadas

- Google Ads e Meta são canais comerciais distintos; Instagram e Facebook pertencem ao **mesmo** canal Meta.
- Campanhas formalmente sob gestão, inclusive pausadas, ocupam capacidade. A contagem de grupos/conjuntos está **em estudo**, ainda não aprovada como regra contratual.
- Substituição ordinária recomendada e validada (em testes por flags fictícias) não gera cobrança automática nem cria anúncios. A proposta de convivência temporária de campanhas ainda não está homologada.
- Nenhum preço, orçamento, média de desempenho, métrica de Ads, cliente, contrato real ou cartão foi usado.
- Nenhuma rota pública, serviço de anúncios, cobrança, banco operacional ou trabalho de fila foi habilitado. O contrato `performance-release-no-go.json` continua com `release_state=blocked`.

## O que falta para concluir o software operacional

**Bloqueios técnicos já registrados:** restauração de bancos pode reabrir replays e autorizações antigas (F1-48), ausência de autoridade de recuperação não retrocedível, detecção automática de restore, isolamento real de workloads, mTLS/KMS/PoP, verificação da fonte Portal, RLS operacional, idempotência nas plataformas e revisão AppSec/DBA. Nenhum protocolo de laboratório foi aprovado para uso de produção.

**Bloqueios de produto/comercial:** homologar quantitativos e preços por pacote/adicionais, equivalência de grupos e Performance Max, limites de contas/marcas, aprovações humanas, políticas de orçamento e mídia paga diretamente ao provedor, contrato Portal-Performance e jornada de onboarding.

**Dependências operacionais:** infraestrutura de homologação disponível e acessível; identidades/credenciais com gestão segura; consentimento e autorização por cliente/conta de anúncios; ambiente de staging aprovado e testado. Não usar contas de clientes ou contratar fornecedores antes desses gates.

**Estado final desta rodada: desenvolvimento demonstrativo funcional validado; sistema completo de produção NÃO finalizado.** P0, HT-R01, SEC-R03/T3, Issues #102/#115/#117/#119/#121/#123 e AppSec/DBA mantidos. Todos os PRs desta trilha continuam drafts sem merge em `main`.