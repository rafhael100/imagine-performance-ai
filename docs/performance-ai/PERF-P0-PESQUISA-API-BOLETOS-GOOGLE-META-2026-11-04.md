# PERF-P0 — Pesquisa aprofundada: emissão de boletos de recarga via API (Google Ads e Meta Ads)

**Data:** 04/11/2026  
**Natureza:** investigação técnica documental; **não é homologação, autorização de programação nem confirmação contratual dos provedores**.  
**Vinculado a:** [Central de Recargas de Mídia](PERF-P0-RECARGAS-MIDIA-BOLETOS-PROGRAMADOS-2026-11-04.md) e PERF-P0 BL-02.  
**Estado:** PENDENTE de confirmação formal de Google e Meta quanto a recursos privados, APIs restritas ou alterações futuras.

## 1. Resposta objetiva

**Não foi encontrado endpoint público oficialmente documentado que permita criar uma recarga pré-paga por boleto bancário em conta de anúncios Google Ads ou Meta Ads, definindo valor e recebendo boleto/PDF/código de barras para envio ao anunciante.**

**Não confundir** (a) criação de boleto pré-pago, (b) consulta/download de fatura pós-paga já emitida, (c) alteração de orçamento de campanha e (d) emissão por PSP externo tendo a Imagine como beneficiária. A ausência de endpoint documentado não prova inexistência de uma integração privada reservada; dependerá de confirmação formal do provedor.

## 2. Google Ads: duas limitações independentes

### 2.1 Elegibilidade comercial de boleto no Brasil

O **suporte oficial Google Ads** declara: a modalidade boleto está **temporariamente indisponível para novas contas Google Ads brasileiras**; contas existentes com cartão, Pix ou Mercado Pago também não podem utilizá-la como alternativa. A restrição não se aplica da mesma forma a contas com **faturamento mensal**, mas **isso não significa que qualquer cliente terá um boleto de recarga pré-paga por API**.

Fontes:
- https://support.google.com/google-ads/answer/2375433?hl=pt-BR — texto oficial das restrições;
- https://support.google.com/google-ads/answer/1704434?hl=pt-BR — procedimento de criação manual no painel para contas elegíveis; leitura conjunta com as restrições acima.

### 2.2 Limitação da Google Ads API

A documentação de **Billing API** explica que os workflows de faturamento por API exigem conta com **monthly invoicing** e expõe:
- `BillingSetup`: relação entre conta Google Ads e conta de pagamentos, não emissão de boleto de recarga;
- `AccountBudgetProposal`: proposta de orçamento da conta com faturamento mensal, não transferência de recursos;
- `InvoiceService.ListInvoices`: listagem de **faturas já emitidas** (somente contas com faturamento mensal) e `Invoice.pdf_url` para download de documento existente.

**Nenhuma dessas operações cria um boleto de recarga pré-paga.** API de faturas não controla o valor arbitrário mensal pré-definido pelo cliente para recarregar saldo. Os campos `amount_spent` e budgets não equivalem a saldo efetivamente creditado.

Fontes:
- https://developers.google.com/google-ads/api/docs/billing/overview
- https://developers.google.com/google-ads/api/docs/billing/invoice

**Conclusão Google:** emissão automática de boleto de recarga via API pública: **NÃO DOCUMENTADA / NÃO HOMOLOGADA**. Emissão manual: **apenas para contas comprovadamente elegíveis**; para a maioria das novas contas, a alternativa pode ser Pix/cartão ou outro meio oficialmente disponibilizado.

## 3. Meta Ads: recursos de leitura encontrados, sem criação pública de boleto pré-pago

### 3.1 SDK oficial

SDKs da Meta para `AdAccount` disponibilizam campos como `is_prepay_account`, `balance`, `amount_spent`, `funding_source` e `funding_source_details`. Permitem **consultar informações**, conforme permissões, mas **não constituem uma operação para solicitar uma nova recarga ou criar boleto**. Não presumir que `balance` seja o saldo pré-pago disponível: sua semântica pode indicar montante devido.

Fonte (SDK mantido pela Meta):  
https://github.com/facebook/facebook-php-business-sdk/blob/main/src/FacebookAds/Object/Fields/AdAccountFields.php

### 3.2 Faturas de crédito empresarial

A Business Management API tem operação de **consulta** `GET /{BUSINESS_ID}/business_invoices` e pode apresentar `download_uri` para fatura já emitida em programas empresariais/crédito. O arquivo de especificação mantido pela Meta lista essa operação como **GET**, não como criação de boleto pré-pago.

Fontes:
- https://github.com/meta/facebook-business-sdk-codegen/blob/main/api_specs/specs/Business.json
- https://developers.facebook.com/docs/marketing-api/business-manager-api/get-started/

**Conclusão Meta:** criação de boleto pré-pago via Marketing/Graph API pública: **NÃO DOCUMENTADA / NÃO HOMOLOGADA**. O boleto, quando aparece no painel, é meio de recarga manual condicional da conta. O download de faturas pós-pagas **não** resolve a emissão recorrente do boleto de recarga.

### 3.3 Escopo de APIs que não resolvem a demanda

A **Payments API do WhatsApp** para facilitar pagamentos por pedidos/serviços é **outra solução**, e não uma API para colocar fundos em uma conta Meta Ads. Emissão via PSP da Imagine criaria recebível da Imagine, não um boleto oficial de recarga Google/Meta.

## 4. Matriz de viabilidade para o Imagine Performance AI

| Atividade | Google Ads | Meta Ads | Status |
| --- | --- | --- | --- |
| Armazenar valor mensal e dia desejado de recebimento | Imagine | Imagine | Planejável sem chamada financeira |
| Lembrete e agendamento de tarefas | Imagine | Imagine | Planejável sem emissão de boleto |
| Consultar dados de conta/campanha/gasto conforme credenciais | API própria | Marketing API | Requer autorização e homologação |
| Criar boleto de recarga pré-paga via API pública | Não documentado | Não documentado | **Não prometer** |
| Download de faturas **já emitidas** em crédito/faturamento mensal | `InvoiceService.ListInvoices` + PDF | `business_invoices` para caso elegível | **Outra funcionalidade** |
| Boleto manual no painel oficial | Apenas conta elegível (restrição Brasil) | Conta com meio pré-pago/boleto habilitado | Requer validação por conta |
| Pagamento automático por cartão ou outro meio oficial | Depende de configuração da conta | Depende de configuração da conta | Não confundir com boleto emitido e enviado |

## 5. Como manter o objetivo comercial sem prometer API inexistente

**Modo A — faturas de crédito empresarial, quando elegíveis:** recuperar documentos oficiais *já emitidos*, informar competência, valor e vencimento reais; **sem permitir que cliente escolha artificialmente valor de fatura pós-paga**.

**Modo B — boleto pré-pago assistido, somente com opção existente no painel:** registrar pedido e, com autorização válida, operador habilitado gera no painel do provedor; enviar documento confirmado ao cliente. Não automatizar autenticação, CAPTCHA, MFA nem criar documento falsificado. Confirmar custo humano e eventual cobrança dessa operação.

**Modo C — recarga pelo cliente via interface oficial:** agendar notificação com valor sugerido e data de recebimento desejada; direcionar ao provedor para adicionar saldo via Pix/cartão/boleto conforme sua conta, sem afirmar “boleto emitido”.

**Modo D — atualização futura:** se Google ou Meta confirmar API pública/privada, viabilidade legal e permissão contratual, passar por homologação, teste de segurança, validação do beneficiário, idempotência, auditoria e controle da autorização antes de implantar.

Nenhuma das modalidades transfere para Imagine o encargo de pagar a mídia; a verba continua paga diretamente pelo cliente ao provedor e separada da mensalidade de gestão.

## 6. Confirmação formal indispensável

Enviar consultas às equipes dos provedores:

**Google Ads API:** “Existe endpoint público, private API, programa partner ou permissão especial que permita, em conta brasileira de pagamento manual, iniciar recarga e gerar boleto bancário para valor escolhido, recuperando link/PDF/código de barras por API? Se sim, quais requisitos, elegibilidade, termos, limites e status de pagamento são suportados? Como a atual indisponibilidade de boleto para novas contas brasileiras afeta esse programa?”

Suporte oficial: https://developers.google.com/google-ads/api/support/contact

**Meta Marketing API:** “Existe endpoint público ou programa restrito que permita, para conta publicitária brasileira pré-paga elegível, gerar solicitação de top-up por boleto com valor escolhido e receber documento/URL oficial, status de pagamento e saldo creditado? Quais permissões, condições de parceiro e critérios de acesso? O campo `business_invoices` se limita ao crédito empresarial?”

A Meta anunciou suporte da Marketing API acessível a todos os desenvolvedores em maio/2026; contato via **Direct Support** do Business Manager ou **Developer Support**:
https://developers.meta.com/blog/whats-new-in-marketing-api-support/

**Não foi enviado pedido de suporte nem executado teste autenticado em contas reais**. Não concluir “impossível em qualquer cenário”; concluir “**não documentado e não disponível como requisito de lançamento garantido**”.

## 7. Impacto na documentação e decisões

- Preservar objetivo funcional da **Central de Recargas** aprovado pelo usuário: cliente escolhe conta/valor/dia; alertas e fluxo de documento, quando houver.
- **Restringir promessa comercial**: não anunciar “geração automática de boleto Google/Meta” antes de comprovação de API de criação e elegibilidade.
- Dar prioridade imediata ao **Google Ads com pagamento por Pix elegível** e às notificações de saldo, além dos meios de pagamento oferecidos oficialmente para cada conta.
- O fluxo de faturamento mensal das plataformas será módulo separado da **recarga antecipada**.
- Preservar RES01-D12: verba paga diretamente ao provedor; não criar boleto com PSP da Imagine em nome de terceiro.
- Status de P0/BL-02 e demais deliberações: **inalterados**, pendências técnicas/comerciais permanecem abertas. **Sem programação ou gasto.**
