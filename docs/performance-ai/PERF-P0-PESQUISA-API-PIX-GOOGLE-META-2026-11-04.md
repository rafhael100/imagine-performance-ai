# PERF-P0 — Pesquisa de Pix para recarga de Google Ads e Meta Ads por API

**Data da pesquisa:** 04/11/2026  
**Natureza:** estudo técnico documental; NÃO é homologação, autorização de integração, decisão comercial ou desenvolvimento.  
**Vinculação:** [Central de Recargas de Mídia](PERF-P0-RECARGAS-MIDIA-BOLETOS-PROGRAMADOS-2026-11-04.md), [pesquisa de boletos](PERF-P0-PESQUISA-API-BOLETOS-GOOGLE-META-2026-11-04.md), PERF-P0 BL-02 e RES01-D12.

## 1. Pergunta exata

Uma aplicação third-party como Imagine Performance AI pode, via **API pública oficial**, escolher uma conta de anúncios Google/Meta, especificar um valor de recarga pré-paga, **criar uma cobrança Pix oficial do provedor**, receber **QR Code dinâmico/código copia e cola/link** e obter confirmação de pagamento e de crédito na conta?

**Resposta documental em 04/11/2026:** Google Ads: **não pela API pública Google Ads**, segundo resposta explícita da equipe oficial. Meta Ads: **nenhum endpoint público oficial localizado/confirmado**, permanecendo aberta investigação de programa privado/parceria. Não confundir com API de emissão de Pix por um PSP da Imagine, consulta de saldo, orçamento de campanha ou recuperação de fatura pós-paga.

## 2. Google Ads — evidência direta da equipe oficial

Em **15/09/2025**, o **Google Ads API Team** respondeu em fórum público que **não é possível adicionar fundos a uma conta Google Ads pela API**; recomendou **Make a payment** na interface do Ads.

- Resposta arquivada: https://www.mail-archive.com/adwords-api@googlegroups.com/msg114260.html
- Referência do fórum original: https://groups.google.com/d/msgid/adwords-api/d5Ulc000000000000000000000000000000000000000000000T2ML73002O0AQoQkQbeaj8yVLIVMzw%40sfdc.net

A documentação **Billing** do Google, com atualização em **30/10/2026**, exige **monthly invoicing** para `BillingSetup`, `AccountBudgetProposal` e `Invoice`, sem descrever API de **pré-pagamento Pix**. A edição de orçamento **não gera crédito na conta**.

- API de faturamento: https://developers.google.com/google-ads/api/docs/billing/overview

O **Pix é aceito no painel** para contas com pré-pagamento, endereço de faturamento brasileiro e BRL: Faturamento > Resumo > Adicionar fundos > Pix > informar valor > QR Code/código exibido. **Haver Pix no painel não significa haver API de geração**.

- Google Ads métodos de pagamento: https://support.google.com/google-ads/answer/2375433?hl=pt-BR
- Publicação Google: https://blog.google/intl/pt-br/novidades/iniciativas/pix-chega-ao-google-ads-para-tornar-pagamentos-mais-faceis-e-convenientes/

**Status técnico Google:** NÃO SUPORTADO PELA API PÚBLICA DOCUMENTADA / confirmação direta da equipe em 2025. Revalidar com suporte antes de prometer endpoint privado ou parceiro.

## 3. Meta Ads — análise do SDK e das especificações públicas

A Meta mantém SDKs oficiais (Business SDK), incluindo `AdAccountFields` com `is_prepay_account`, `funding_source_details`, `amount_spent`, `balance`; esses campos servem a **consultas e diagnósticos**, não demonstram criação de cobrança Pix ou top-up:

- https://github.com/meta/facebook-php-business-sdk/blob/main/src/FacebookAds/Object/Fields/AdAccountFields.php
- https://github.com/meta/facebook-php-business-sdk/blob/main/src/FacebookAds/Object/AdAccount.php

Na especificação Business gerada pela Meta, a operação **`business_invoices` é `GET`**, destinada a **documentos já emitidos** em cenário elegível; não é `POST` para emissão de recarga Pix:

- https://github.com/meta/facebook-business-sdk-codegen/blob/main/api_specs/specs/Business.json

**Nenhum endpoint público autenticável para `create prepaid Pix top-up` foi identificado nos materiais analisados**. Essa ausência documental **não prova impossibilidade em API privada/partnership**; só a Meta poderá confirmar elegibilidade, programa e exigências. Também não confirma que Pix esteja disponível em todas as contas, moedas ou modalidades da Meta.

**Não confundir** pagamento/recarregamento **WhatsApp Business Platform via BSP** com recarga de anúncios Meta Ads: são carteiras, cobranças e autorizações distintas. Alguns provedores oferecem Pix para WhatsApp, sem implicar API Meta Ads para Pix.

**Status técnico Meta:** NÃO DOCUMENTADO/NÃO HOMOLOGADO. Exigir resposta formal e testes autorizados, sem inferir endpoint interno.

## 4. Soluções que PODEM ser planejadas sem simular pagamento

| Fluxo | O que Imagine faria | Limitação e transparência |
| --- | --- | --- |
| Agendar recarga | Cliente escolhe conta, valor, mês, dia de aviso e destinatário | **Nenhum Pix emitido ainda** |
| Avisar sobre saldo/desempenho | Consultas autorizadas, cálculo prudente de uso e riscos | Diferenciar gasto, dívida e saldo pré-pago real |
| Acessar pagamento no provedor | Notificação com redirecionamento à interface oficial | QR Code é gerado pelo provedor, não por Imagine |
| Envio de código oficial existente | Se cliente/operador com autorização fornecer código íntegro e vigente, encaminhar/arquivar com proteção | Confirmar beneficiário, valor, validade, vinculação; não editar código nem confundir envio com pagamento |
| Recarga com faturamento automático | Cliente configura modalidade oficial disponível, diretamente na conta do provedor | Não é Pix recorrente criado pela Imagine; não representa controle financeiro do cliente |
| Futuro endpoint parceiro | Implementar APENAS após documentação, licença, autorização, confirmação e homologação | Ainda **não comprovado** |

Recomendar app/webhook/PSP bancário para criar Pix **da Imagine** não resolve recarga **direta** do Google/Meta. Seriam recebedor, conciliação, deveres e riscos contratuais distintos, além de potencial mudança de titularidade de verba. **Não oferecer isso como equivalente** sem novo desenho jurídico/financeiro explicitamente aprovado.

## 5. Controles críticos

- Recebedor/beneficiário deve ser o provedor ou beneficiário **expressamente autorizado** por ele; titularidade da conta/cliente preservada e mídia paga diretamente ao provedor (RES01-D12).
- Distinção obrigatória de **agendamento**, **Pix solicitado**, **Pix emitido**, **enviado**, **pago** e **saldo de mídia efetivamente creditado**; não inferir confirmação por screenshot/comprovante sem conciliação oficial.
- QR Code/código Pix não podem ser inventados nem alterados pela Imagine. Verificar valor e chave/dados do recebedor pelo canal oficial antes de encaminhar.
- Não usar automações de navegador para contornar CAPTCHA, MFA, controles de pagamento ou termos de plataforma.
- Não aumentar orçamento de campanhas, executar recargas, transferir valores ou pagar em nome do cliente pela mera programação mensal.

## 6. Perguntas de homologação para os provedores

**Google Ads API support:** Existe algum programa oficial privado/parceiro, fora da API pública, para iniciar uma recarga pré-paga Pix para conta brasileira, definir valor e obter QR Code/link/copia e cola com status de pagamento/creditamento? A resposta pública da equipe em 15/09/2025 ainda está vigente em 2026? Quais permissões, elegibilidade e limitações contratuais?

- https://developers.google.com/google-ads/api/support/contact

**Meta Marketing API / Business Support:** Existe API pública ou restrita que permita a terceiros autorizados criar Pix de recarga para conta de anúncios brasileira pré-paga (`act_<id>`), retornando QR Code/Pix copia e cola, expiração e eventos de confirmação/creditamento? Como solicitar acesso, quais requisitos de parceiro e termos? Distinguir de `business_invoices` (consulta GET), APIs de pagamento do WhatsApp e intermediários.

**Nenhum contato formal foi efetuado nem teste com contas reais foi realizado neste estudo.** A natureza da pendência é externa e técnica.

## 7. Decisão de planejamento conservadora

- A Central de Recargas programadas permanece funcionalmente prevista por demanda do usuário.
- **Não prometer "Pix gerado automaticamente via API Google/Meta" no lançamento**.
- Planejar notificações, elegibilidade por conta, redirecionamento oficial, fluxo assistido e registro seguro de comprovantes sem efetuar pagamentos.
- Se houver confirmação de API de terceiro oficialmente autorizada, submeter a **nova deliberação comercial e técnica**, avaliar custo e risco de fraude antes de implementação.
- **Nenhuma modificação de escopo funcional aprovado, sem baixa de P0, sem contratos ou programação.**
