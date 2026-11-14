# Imagine Performance AI — calculadora de investimento e conciliação tricanal (demo offline)

## Entregável navegável

Abra `labs/commercial/trichannel-calculator.html` junto com `trichannel-calculator.mjs` em um servidor estático local ou baixe o artefato do GitHub Actions `perf-trichannel-calculator-offline-demo`. A interface segue o conceito visual leve da Imagine, usa a referência laranja `#F9941D` e **não faz chamadas externas, coleta de dados pessoais, cookies, rastreamento, salvamento de leads nem solicitação ao Asaas**.

A calculadora recebe três informações: competência mensal YYYY-MM, objetivo mensal de leads e CPL assumido pelo visitante em cada canal selecionado (Google, Meta e ChatGPT). Os leads são **distribuídos igualmente** entre os canais, com sobra alocada em ordem alfabética técnica. A mídia sugerida de cada canal é o máximo entre o piso proposto, o valor mensal recomendado de referência e o produto entre leads alocados e CPL assumido. Os 20% sobre a mídia **planejada** são mostrados separadamente, como ilustração de gestão.

O orçamento mínimo e recomendado é **proposta não homologada no Portal**: Meta R$700/R$1.000, Google R$1.000/R$1.500, ChatGPT R$1.200/R$1.800. Os R$1.200 do ChatGPT não são um mínimo mensal universal da plataforma. O catálogo Portal atual não é modificado por esse código.

Há testes PHP, JS (paridade em cenário matemático, valores monetários e validações), proibições de rede/formulários/PII e artefato ZIP criado na CI. A interface é estática, deliberadamente **não publica um endpoint de captação ao CRM**: a integração futura deverá implementar consentimento granular, LGPD, antifraude, origem e vínculo com o CRM Imagine em endpoint autenticado e autorizado.

## Demonstração de conciliação dos três canais

`TrichannelSpendProposalReview` agrega fixtures sintéticas por conta Google Ads, Meta Ads e ChatGPT Ads, recusando empresas, contas, meses, moedas, fusos, páginas faltantes e flags de verificação adulteradas. Calcula taxas de 20% sobre orçamento projetado e sobre gasto hipotético, com ajuste somente para revisão. Registra fingerprint determinístico por conta/canal, sem pretender prova criptográfica do provedor.

**Importante:** diferentemente da cadeia assinada Portal v2 → PostgreSQL 18 do Google/Meta, este protótipo **não entra no banco, não é cotação assinada e não gera cobrança**. Torná-lo operacional exige catálogo aprovado com o ChatGPT, permissões reais, IAM Portal, autenticação segura e comprovação de gastos.

## Condições para integração futura da isca digital ao CRM

1. Aprovação do conteúdo e condições comerciais no Portal e no Performance.
2. Consentimento de marketing separado do uso da calculadora; formulários com finalidade, retenção, política de privacidade, opt-out e prevenção de abuso.
3. Captura idempotente no CRM Imagine (tenant interno conhecido, origem/campanha, timestamp do consentimento, resumo de orçamento, mecanismo de deduplicação).
4. Mensuração com autorização e sem vazamento interempresa. O histórico de simulações **não** deve presumir intenção de contratar nem pontuar contatos sem base legal.
5. Teste UX/segurança, homologação, backups, integração com domínio público e aprovações de deploy.

**Status técnico:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE. Leads e CPL são hipóteses; resultados não são garantidos.
