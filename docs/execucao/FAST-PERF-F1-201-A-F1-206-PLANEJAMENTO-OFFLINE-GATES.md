# FAST-PERF F1-201–F1-206 — Planejamento offline e gates de produção

**Data:** 11/11/2026
**Estado:** branches e PRs em DRAFT; CI requisitado, não equivale a liberação.
**Escopo:** PHP 8.4, dados sintéticos, sem clientes, anúncios, autenticação, cobrança ou deploy.

## Entregas incrementais

| Etapa | Componente | Artefato | Origem |
|---|---|---|---|
| F201 | Avisos de pacing acima/abaixo das tolerâncias | DTO e códigos synthetic_* | F200 e fixtures sintéticas |
| F202 | Consolidação de até 20 campanhas | Totais por cenário, dia e canal | F105 e inteiros em centavos |
| F203 | Exportação CSV RFC4180 | String em memória e SHA256 | DTO F202 |
| F204 | CLI demonstrativa de exportação | stdin JSON → stdout CSV | F203, modo testing |
| F205 | Relatório HTML estático do portfólio | String em memória, CSP e SHA256 | DTO F202 |
| F206 | Manifesto de consistência local | SHA256 de DTO, CSV e HTML | Recomputação local, **sem assinatura** |

## Teste demonstrativo da F204 (ambiente de desenvolvimento)

    umask 077
    APP_ENV=testing PERF_F204_CSV_CLI_LAB=1 \
      php tools/perf-export-offline-portfolio-csv.php \
      < contracts/demo/f204-offline-portfolio-request.json \
      > /tmp/performance-budget-demo.csv

A fixture é estritamente fictícia. O PHP não grava arquivos por conta própria; o redirecionamento é do shell, sob umask 077. O CSV deve conter 9 linhas com orçamentos ilustrativos R$ 1,01 e R$ 0,99 (total R$ 2,00). Não se trata de proposta comercial nem de tabela aprovada.

## Gates para avançar

1. CI: todas as PRs devem ter testes verdes no SHA de integração; sem remover condições de segurança.
2. Nenhuma execução: media_execution_allowed, automatic_pause_allowed e billing_allowed permanecem falsos. Nenhum OAuth, Ads, pagamento ou banco real é habilitado.
3. Entrada: schemas versionados, referências scenario-demo-*, datas, moeda BRL canônicas, valores inteiros em centavos e no máximo 20 cenários.
4. Consistência: todas as somas por dia/canal/cenário reconciliam; observações sintéticas não são gastos reais.
5. Artefatos: CSV e HTML construídos em memória; nenhum script, URL externo, formulário ou conteúdo real. O comando F204 é somente CLI em APP_ENV=testing e com flag.
6. Produto real: revisão independente AppSec/DBA, IAM Portal, MFA, vínculo contratual elegível e fonte independente de revogação antes de rotas HTTP operacionais.
7. Anúncios reais: OAuth dos provedores, aprovação explícita do anunciante, controle financeiro, idempotência, reconciliação, LGPD, QA e homologação externa seguem bloqueados.

## Limite de integridade

Os hashes F206 são locais, reprodutíveis e não assinados. Um agente que consiga alterar entradas e regenerar saídas pode produzir um novo manifesto consistente. Isso **não** equivale a WORM, autoridade independente, antirollback ou aprovação de campanha. Serve somente para reconciliação durante revisão.

**NÃO liberar o pacote como release**, fazer deploy na VPS, vincular clientes, ativar campanhas nem iniciar cobrança apenas por estes testes.
