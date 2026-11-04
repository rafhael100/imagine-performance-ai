# PERF-P0 — Central de Recargas de Mídia: Boletos e Entrega Programada

**Projeto:** Imagine Performance AI  
**Data de registro:** 04/11/2026  
**Situação:** **REQUISITO FUNCIONAL APROVADO PARA O PLANEJAMENTO** pelo usuário: oferecer opção para programar solicitações de recarga e recebimento de boletos das plataformas Google Ads e Meta Ads. **Sem homologação de APIs, garantias de emissão automática, preço, contratação, programação ou operação real.**  
**Rastreabilidade:** PERF-P0 BL-02 (cobrança, verba de mídia e alçadas), PERF-02/03/04 (contas e provedores), PERF-08 (agentes/autorizações), PERF-11 (monitoramento), PERF-12 (catálogo/custos), RES01-D11/D12 (adicionais, verba paga diretamente ao provedor).

> **Nota de pesquisa complementar (04/11/2026):** [Investigação aprofundada sobre emissão de boletos por API Google/Meta](PERF-P0-PESQUISA-API-BOLETOS-GOOGLE-META-2026-11-04.md). **O Google informa que o boleto está temporariamente indisponível para novas contas brasileiras e para contas que usam cartão, Pix ou Mercado Pago; isso não afeta da mesma forma contas com faturamento mensal.** Os recursos públicos de API encontrados em Google/Meta permitem, conforme modalidade/permissão, consulta de faturamento e download de faturas previamente emitidas, **mas não comprovam geração de boleto de recarga pré-paga por API**. Qualquer fluxo no painel exige elegibilidade real da conta. Não anunciar emissão automática para todos os clientes. Fontes oficiais e ressalvas no estudo complementar.

## 1. Objetivo da funcionalidade

O cliente poderá habilitar **opcionalmente** a Central de Recargas de Mídia no Portal Imagine Performance AI, para configurar **por plataforma e por conta publicitária**:
- Valor mensal de recarga desejado, em moeda elegível (inicialmente avaliar BRL);
- **dia do mês em que deseja receber o boleto ou solicitação de pagamento**;
- periodicidade (mensal como padrão), e-mail(s) verificados e preferência de avisos;
- conta vinculada Google Ads ou Meta Ads, se a conta **realmente aceitar recarga manual por boleto**;
- ativação, pausa, edição, suspensão e cancelamento da programação;
- consulta a histórico, documentos oficiais recebidos e estados da solicitação.

**Exemplo ilustrativo:** conta Google Ads autorizada com recarga de R$ 1.500 para envio dia 5; conta Meta Ads autorizada com R$ 1.000 para envio dia 10. Não presumir que as duas contas possuem boleto habilitado ou que a API emite esses documentos.

As datas significam **dia de envio desejado**; diferem de data de emissão, vencimento, compensação ou disponibilização do saldo da mídia. Definir regras explícitas de fuso, dias 29–31, feriados, prazos e pedidos de antecipação. Recomendam-se alertas preventivos para reduzir risco de falta de saldo, sem prometer continuidade das campanhas.

## 2. Fronteira financeira aprovada

- **Boleto de mídia:** deve ser gerado pelo **próprio Google/Meta ou beneficiário de pagamento oficialmente autorizado pelo provedor**, associado à conta do anunciante. A Imagine não emitirá boleto próprio fingindo ser o fornecedor de mídia.
- **Verba de anúncios é paga diretamente pelo cliente ao provedor**, preservando RES01-D12. Recarga prevista não é pagamento confirmado nem receita Imagine.
- Emissão/encaminhamento de boleto não autoriza a Imagine a pagar a cobrança, movimentar recursos do cliente, criar recargas financeiras, alterar orçamento publicitário, contratar serviço externo ou disponibilizar dados sensíveis.
- **Cobrança da Imagine:** honorários de gestão, créditos adicionais IA e eventuais serviços assistidos são distintos da verba de mídia e da fatura dos provedores. Preço e eventual franquia desta funcionalidade **permanecem pendentes**.
- Não utilizar serviços de boleto/PSP da Imagine (ex.: gateway de recebimento de honorários) para criar boleto de recarga no lugar do provedor.

## 3. Modalidades de operação, condicionadas à capacidade real

**A — Emissão integrada oficial (futura, apenas se tecnicamente permitida).** Validar primeiro API oficial, termos, permissão específica do cliente, comprovante de origem, conta, valor, documento, beneficiário e referência oficial. **Não foi confirmada a existência de API pública que emita boletos de recarga Google/Meta.** Não prometer emissão automática, nem vender esta modalidade antes de homologar.

**B — Emissão assistida por operador autorizado.** Caso o provedor permita boleto apenas pelo painel, registrar uma tarefa para pessoal autorizado realizar emissão **na interface oficial**, conferindo conta, valor, beneficiário, vencimento e documento. Enviar ao cliente somente após validação, com auditoria e regras de SLA e preço próprias. Requer permissão real; não usar senha pessoal do cliente compartilhada, nem burlar MFA ou CAPTCHA.

**C — Autoatendimento assistido com lembrete.** Se a Imagine não tiver API oficial ou acesso/escopo humano para emitir, enviar lembrete com acesso à área oficial do provedor e instruções; status deve dizer expressamente **“boleto não emitido”**. Não apresentar link de orientação ou lembrete como documento de pagamento.

Os modos A/B/C poderão coexistir por cliente, país, conta e provedor. Eventual mudança de modalidade precisa ser informada. A elegibilidade não deve ser presumida por existir contrato Imagine Performance.

## 4. Estados, confirmações e histórico

Estados sugeridos: **programado → elegibilidade verificada → solicitação aberta → emissão em curso → emitido pelo provedor → enviado → confirmado como entregue**, com desvios para **pendente de autorização, indisponível, erro, documento vencido, cancelado**.

Diferenciar os eventos **boleto emitido**, **boleto enviado**, **boleto recebido**, **boleto pago** e **saldo creditado**. Pagamento e saldo efetivo só podem ser marcados como confirmados mediante prova oficial compatível. Falha em envio pode demandar novo envio do **mesmo boleto válido**, e não emissão de outro boleto.

Registrar identificadores da empresa/conta/plataforma/ciclo, valor, regra versionada, usuário autorizador, horários, origem/URL oficial, validade/vencimento, comprovantes de entrega, tentativas, erros, cancelamentos e reconciliação. Adotar idempotência para evitar emissão ou encaminhamento duplicado; alertar sobre documentos já pagos/vencidos, fraudes e divergências.

## 5. Segurança e proteção contra fraude

- Verificar autenticidade, titular/beneficiário, valor, conta e origem do documento no site oficial. **Não inventar, editar ou simular linha digitável, QR Pix, código de barras ou PDF de boleto**.
- Dar preferência à entrega em **área autenticada do Portal**, com e-mail de notificação que não exponha dados sensíveis e leve ao documento verificado. Definir critérios para envio de anexo, retenção e acesso.
- Jamais armazenar credenciais pessoais ou executar automação de navegador para contornar segurança, MFA, CAPTCHA ou restrições contratuais.
- Toda alteração de valor, data, destinatário ou conta requer autorização de usuário habilitado; registrar versões; usar canal seguro e auditoria.
- Boleto é um meio de pagamento da plataforma, não prova de que a campanha recebeu verba ou permanecerá ativa.

## 6. Verificações externas em aberto (fontes para homologação)

**Google Ads:** há documentação oficial de boleto para **pagamentos manuais elegíveis** no Brasil; a API de faturamento público aborda especialmente contas com cobrança mensal e **não comprova endpoint público para geração de boleto de recarga**. Validar perfil de pagamento, moeda, acesso, mínimo/validade e prazos atualizados para cada conta.

- https://support.google.com/google-ads/answer/1704434?hl=pt-br
- https://developers.google.com/google-ads/api/docs/billing/overview

**Meta Ads:** disponibilidade de recarga via boleto depende de país, forma de pagamento e conta; **não foi homologada emissão por API**. Verificar diretamente documentação oficial, painel elegível e permissões antes de oferecer boleto emitido; não equiparar saldo pré-pago a faturamento pós-pago.

- https://www.facebook.com/business/help/350519228479855
- https://developers.facebook.com/docs/marketing-api/

**Não garantido:** geração recorrente automática nas duas plataformas, data exata de vencimento, prazo de compensação, saldo disponível, ativação de campanha ou pagamento do cliente.

## 7. Requisitos comerciais e operacionais ainda não decididos

- Definir se emissão assistida é incluída em franquia, cobrada por solicitação ou adicional recorrente; **testar custo de intervenção humana nos mínimos candidatos de R$ 290 (um canal) e R$ 390 (dois canais)**.
- Definir elegibilidade da conta e prioridade de atendimento em dias com grande volume de solicitações; suporte humano e janelas contratadas não equivalem a disponibilidade 24/7.
- Definir tratamento do dia programado em fins de semana e feriados, adiantamento em relação ao prazo de pagamento e vencimentos, reemissão por expiração e múltiplas contas.
- Definir e homologar envio, autenticação de e-mails, controle de permissão, armazenamento/retentiva LGPD, auditoria e prevenção de fraude.
- Definir resposta quando a plataforma deixar de aceitar boleto ou bloquear conta, sem cobrar recargas não executadas.

## 8. Critérios mínimos de teste futuro

1. Um cliente programa Google e Meta com valores/dias distintos, mantendo corretamente vínculos por conta.
2. Google ou Meta sem boleto elegível: funcionalidade sinaliza indisponibilidade e não inventa cobrança.
3. API não autorizada: sem emissão automática simulada ou uso de automação proibida.
4. Mês sem dia 30/31 e feriado: aplicar política publicada, sem disparo inesperado.
5. Falha de envio: retentativa do documento original válido, sem duplicidade de emissão.
6. Boleto vencido: impedir reenvio como título vigente e exigir nova emissão autorizada.
7. Destinatário não verificado: bloquear envio de dados/documentos sensíveis.
8. Beneficiário divergente: bloquear encaminhamento e sinalizar suspeita de fraude.
9. Documento enviado mas ainda não pago: não marcar como recarga concluída.
10. Pagamento declarado sem confirmação do provedor: estado inconclusivo até conciliação.
11. Cliente cancela programação: não gerar novo boleto, respeitando eventos já ocorridos.
12. Excedente, falha ou falta de licença da integração: continuar a preservar segurança, logs e avisos apropriados.

**Status final:** requisito de produto **registrado e aprovado funcionalmente em seu objetivo**, com **execução técnica, preços, políticas operacionais, APIs, permissões e homologações pendentes**. Não dá baixa em P0 nem modifica escopo de cobrança/mídia previamente aprovado. **Somente planejamento; sem programação, contas reais, emissão de boletos ou gastos.**
