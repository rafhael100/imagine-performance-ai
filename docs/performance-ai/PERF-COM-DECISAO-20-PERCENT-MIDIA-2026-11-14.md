# Performance — decisão comercial vigente de 14/11/2026

## Decisão expressa do titular

“Será 20% do valor da mídia investido, com valor mínimo de investimento em compra de anúncio patrocinado de R$ 700,00 por canal.”

- Honorários Imagine: **20% da mídia investida** nos canais contratados.
- Investimento mínimo em anúncios: **R$ 700,00 por canal**. Esse valor é verba de mídia, separada dos honorários e paga diretamente pelo cliente às plataformas.
- Fórmula: `honorarios = 20% × soma(midia_investida_por_canal_contratado)`.
- A restrição de investimento se verifica por canal: orçamento agregado suficiente não compensa um canal abaixo de R$ 700.
- Canais iniciais: Google Ads e Meta (Instagram/Facebook). Instagram e Facebook integram um canal Meta; não cobrar mínimo em duplicidade por posicionamento.

| Exemplo no mesmo período de referência | Mídia | Honorários 20% | Total mídia + honorários |
| --- | ---: | ---: | ---: |
| Um canal no investimento mínimo | R$ 700 | R$ 140 | R$ 840 |
| Dois canais, cada um no mínimo | R$ 1.400 | R$ 280 | R$ 1.680 |
| Um canal com investimento maior | R$ 2.000 | R$ 400 | R$ 2.400 |

Valores dos exemplos são aritméticos, não cobranças emitidas. R$ 140 por canal no mínimo é consequência da fórmula, **não uma taxa fixa independente do investimento efetivo**. Não cobrar diferença por mídia não investida nem elevar gasto automaticamente para atingir o mínimo. Campanhas e alçadas de gasto continuam exigindo autorização própria.

## Precedência

Esta nova decisão resolve a divergência de precificação registrada na pesquisa histórica do Portal e substitui, para novas ofertas Performance, as propostas de 15% com pisos R$ 290/R$ 390 e as simulações R$ 397/R$ 697/R$ 1.197. Também não converter em cobrança os adicionais e ativações ilustrativos R$ 179/R$ 79/R$ 479 e R$ 797/R$ 1.497; eventuais serviços extraordinários exigem proposta específica aceita. Não acumular essas taxas antigas com os 20% por inferência. Contratos já firmados conservam suas condições até alteração expressamente aceita.

Preservam-se escopo contratado, regras de aprovação, separação da mídia, ausência de taxa por troca ordinária dentro do escopo e demais decisões funcionais não conflitantes. A decisão de preço não concede capacidade ilimitada ou integrações já homologadas.

## Implementação e itens ainda não definidos

Asaas é o gateway escolhido para os honorários Imagine, sob autoridade financeira do Portal. A verba de mídia permanece nas plataformas; não criar compra de mídia no Asaas por confusão com pagamento da gestão.

Ainda precisam de definição operacional antes de cobrança real: período de referência do mínimo/apuração (não explicitado nesta mensagem), datas de vencimento, antecipação versus acerto posterior, definição conciliável de mídia investida e tratamento de créditos/estornos/ajustes e de investimento realizado abaixo do mínimo. Não preencher automaticamente essas lacunas com D90 ou descontos dos sites, pisos antigos, multas, taxa de implantação ou complemento de honorários não aprovados.

O catálogo do Portal passa a registrar o modelo percentual aprovado, em vez de preço ausente. Este documento não ativa gateway, cobrança, anúncios, acesso a contas ou alterações retroativas. A integração técnica e a homologação continuam separadas da aprovação comercial.


Fonte financeira canônica de integração: [Portal PR #737](https://github.com/rafhael100/imagine-portal/pull/737); catálogo JSON de referência sob autoridade do Portal. Esta decisão posterior prevalece sobre os estados de preço em aberto ou simulados dos documentos históricos BL01, BL02, RES01, Roadmap e estudos econômicos. Esses documentos preservam o contexto histórico e não são a tabela vigente.
