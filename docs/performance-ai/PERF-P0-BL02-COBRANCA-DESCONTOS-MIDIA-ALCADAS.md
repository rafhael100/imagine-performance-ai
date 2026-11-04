# PERF-P0 — BL-02: Cobrança, Descontos, Investimento de Mídia e Alçadas Financeiras

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-02 v0.1, requisitos BL02.01–BL02.27 e 27 critérios BL02-V01–V27**, por decisão expressa do usuário. **Os 9 P0 permanecem abertos; as 15 deliberações comerciais BL02-D01–D15 são apenas propostas pendentes de decisão específica**; preços, descontos, prazos, montantes, alçadas executáveis, políticas jurídicas/fiscais e homologação técnica não foram aprovados. **Sem programação ou operação real.**  
**Decisões de referência:** DC-04, DC-05 e DC-06. **BL-01 aprovado funcionalmente**, com Canal Único, Multicanal e Operação Ampliada, cotas/adicionais explícitos e quatro P0 e valores abertos.  
**P0 do BL-02:** **9 ABERTOS**, com 82 P0 restantes, 39 P1 e 24 P2 preservados na PERF-R02. Divergências D-01–D-14 e 15 gates HT-R01 não homologados.  
**Contratos candidatos:** CT-PERF-13, CT-PERF-17, CT-PERF-18; interfaces CT-PERF-01/02/14/15/19.  
**Restrição:** exclusivamente documentação, sem programar, emitir cobrança, conectar PSP, obter Pix, alterar campanha, gastar verba, contratar provedores, ativar agentes ou usar dados reais.

## 1. Premissa central e separação de autoridades

A Imagine cobra **serviço de inteligência/gestão Performance**, enquanto **cliente paga a mídia diretamente a Google/Meta no modelo-base**. O orçamento publicitário é autorizado por recurso, conta, titular, valor/período e finalidade. Custos e quotas de IA e produção são dimensionados em separado. O Portal é autoridade da relação comercial Imagine; Google/Meta, de gasto e estado oficial das campanhas; PSP, de cobrança/liquidação; PERF-08, de políticas e aprovações executáveis futuras.

**Nenhum preço, franquia quantitativa, desconto, comissão, multa, dia de vencimento, SLA, alçada monetária ou nível autônomo foi aprovado por este documento.**

## 2. Matriz de cobranças e responsabilidades

| Rubrica | Quem cobra no modelo-base | Regra |
| --- | --- | --- |
| Assinatura Performance | Imagine | Contrato e pagamento próprio do Performance |
| Implantação/adicionais | Imagine | Escopo e preço explicitamente aprovados |
| Mídia Google Ads | Google/provedor de mídia | Conta e pagamento do cliente; gestão delegada limitada |
| Mídia Meta Ads | Meta/provedor de mídia | Conta e pagamento do cliente; gestão delegada limitada |
| IA/criativos/relatórios | Imagine, quando incluídos ou adicional aprovado | Quotas sem cobrança surpresa |
| CRM Imagine / Sites Imagine | Produtos independentes | Política própria de preço, cortesia, retenção e suspensão |

## 3. Requisitos aprovados funcionalmente BL02.01 a BL02.27

### BL02.01 — Separação dos fluxos financeiros
Separar assinatura Imagine, implantação/adicionais, orçamento de mídia Google/Meta, custos internos IA e outros contratos CRM/Sites. O mesmo valor não deve ser faturado duas vezes nem tratado como licença/honorário sem evidência.

### BL02.02 — Modelo de remuneração e honorários
Avaliar mensalidade fixa por pacote, adicionais por capacidade e eventual comissão por verba somente com contrato expresso. Não presumir valor ou percentual. BL-04 deverá validar unit economics e risco de incentivo comercial.

### BL02.03 — Implantação e primeiro período
Separar diagnóstico/onboarding inicial da mensalidade; política de taxa, isenção, parcelamento, início de cobrança e competência ainda será aprovada. Não copiar os 90 dias da primeira mensalidade de Sites.

### BL02.04 — Meios de pagamento homologáveis
Considerar cartão recorrente, Pix Automático, Pix avulso e boleto conforme gateway e contrato. Não afirmar disponibilidade até integração e homologação; credenciais e tokens do pagador ficam com PSP/gateway autorizado.

### BL02.05 — Pix Automático e autorização do pagador
O pagador deve autorizar recorrência nas jornadas do Pix/PSP, respeitando valor, periodicidade, limites e cancelamento. Uma cobrança criada não prova autorização ou liquidação; identificar status de autorização, agendamento, pagamento e revogação separadamente.

### BL02.06 — Cartão recorrente, Pix avulso e boleto
Carteira/token de cartão sob PSP; distinguir autorização, captura, liquidação, contestação/chargeback e estorno. Pix avulso/QR/boleto emitidos não são pagamento confirmado. Idempotência de cobrança e reconciliação obrigatórias.

### BL02.07 — Descontos e parcelamento exclusivos Performance
Comparar desconto zero, 5% ou outro percentual para Pix, com estudo de taxas e margem; nenhuma hipótese aprovada. Política Sites/CRM (Pix à vista 5%, Pix Automático 5% e até 3x cartão na implantação quando aplicável) continua exclusiva dos contratos próprios. Não oferecer Pix parcelado por presunção.

### BL02.08 — Tabela de preços, promoções e reajustes
Contrato com versão, moeda, vigência, preço-base real, duração de oferta, reajuste, taxas e tributos validados. Não divulgar desconto fictício, contador artificial ou reajuste não previsto.

### BL02.09 — Vencimento e dias não úteis
Definir data inicial/recorrente, dia útil, feriado local, fuso, correção ou reemissão e atraso, conforme meios de pagamento e política própria. Não transportar automaticamente prazos de Sites ou CRM.

### BL02.10 — Estados de cobrança e inadimplência
Cobrança pendente, emitida, paga, vencida, em recuperação, suspensa seletivamente, cancelada, contestada/estornada e reconciliada com evidência. Prazos de tolerância, multas/juros e cancelamento dependem de decisão própria; não herdar 7/30 dias do CRM.

### BL02.11 — Suspensão contratual sem falsa pausa de mídia
Suspender Performance impede novas operações internas elegíveis, mas não prova interrupção de anúncios ou débito no Google/Meta. Notificar cliente sobre responsabilidade e preservar Sites/CRM contratualmente independentes.

### BL02.12 — Cancelamento, portabilidade e fila de leads
Acessível no Portal conforme contrato; indicar data efetiva, exportações, retenção e destinação dos leads em trânsito. Cancelar Imagine não cancela conta publicitária externa sem comando autorizado e confirmação oficial.

### BL02.13 — Reativação, downgrade, upgrade e pró-rata
Políticas de crédito, diferença, validade e novo escopo com preço aprovado. Reativação deve conferir OAuth/Meta, aprovações, contratos, orçamento e campanha alterada pelo cliente; não retomar campanha automaticamente.

### BL02.14 — Conciliação, reembolso e NFS-e
Separar receita Imagine de mídia direta de terceiros; conciliar gateway, nota fiscal, competência, reembolsos e chargebacks. Revisar NFS-e/tributos com profissional habilitado; sem alíquotas, notas ou reembolso de mídia presumidos.

### BL02.15 — Titularidade financeira da conta Google/Meta
Modelo-base proposto: cliente é titular da conta e paga publicidade diretamente ao provedor; Imagine recebe delegação de gestão. Revenda/financiamento de mídia pela Imagine exigirá outro contrato e aprovação contábil/financeira.

### BL02.16 — Orçamento por conta, campanha e período
Definir escopos por canal/conta/campanha/objetivo, janela, moeda e titular. Diferenciar teto interno autorizado, orçamento médio diário/configuração oficial e gasto observado; dados atrasados e mecanismos de entrega impedem promessa de teto diário rígido.

### BL02.17 — Redistribuições e aumento de gasto
Aumento de teto ou nova finalidade exige alçada específica. Redistribuição dentro de valor já autorizado é candidata a N2/N3 somente se risco, frequência e limites estiverem homologados; não conceder poder de mover mídia a conta de terceiro.

### BL02.18 — Matriz de papéis financeiros
Distinguir titular/pagador, administrador da empresa, gestor de mídia, financeiro, supervisor Imagine e auditor. Aprovação de criativo não autoriza aumentar verba, nem a IA aprova sua própria proposta; MFA/dupla aprovação conforme risco futuro.

### BL02.19 — Aprovações íntegras por recurso
Vincular aprovação a conta, campanha, operação, valor antes/depois, fuso/moeda, versão, prazo, aprovador e risco; revalidar antes da mutação. Mudança material invalida autorização anterior; não aprovar por silêncio ou mensagem de agente.

### BL02.20 — N1/N2/N3 como condicionantes, não autorização
N1 consulta/recomenda; N2 executará futuramente apenas comando humano aprovado; N3 só opera dentro de política expressa, delimitada e homologada. Nenhum nível pode criar novos gastos fora de teto, autoconceder acesso ou ampliar seus limites; nenhum nível está ativado.

### BL02.21 — Kill switch e pausa de campanha
Interromper novas mutações da Imagine é diferente de confirmar campanha pausada no provedor. Pausar/retomar exige política, alçada e confirmação Google/Meta; mandate emergência previamente aprovado ainda requer rastreabilidade, efeito e reconciliação.

### BL02.22 — Falha parcial e reconciliação financeira
Timeout/HTTP de aceitação não significa orçamento aplicado ou despesa interrompida. Consultar fonte oficial antes de repetir comandos; idempotência contextual, concorrência, dependências e compensações condicionadas. Rollback não restitui gasto histórico.

### BL02.23 — Alertas de verba e transparência
Exibir gasto reportado, última atualização, limites por período, mudança externa, risco e responsável pela decisão. Aviso entregue não é aviso lido; despesas publicitárias não podem ser mascaradas por custo da assinatura.

### BL02.24 — Consumo IA versus mídia
Gerações, análises, relatórios e vídeos consomem quotas próprias com alerta prévio, adicionais apenas autorizados, sem cobrança surpresa. Esgotamento não apaga leads/arquivos existentes nem força retirada de campanhas de clientes.

### BL02.25 — Auditoria e proteção de dados financeiros
Registros imutáveis com ator, autorização, conta, versão, proposta, comando, recibo, resultado e correção, sem token/cartão/sigilo em prompt/log; segregação multiempresa e limitação de acesso.

### BL02.26 — Jurídico, fiscal e políticas de provedores
Validar contratos, CDC, autorização Pix, LGPD, NFS-e, responsabilidades por conta e gasto, políticas Google/Meta, cobranças e litígios por profissional responsável. IA não cria garantias de ROI ou aprovação de anúncio.

### BL02.27 — Homologação e critério de passagem
Aprovação do desenho BL-02 não decide valores, descontos, prazos, multas, impostos, políticas executáveis ou permissionamento técnico; 9 P0, D-01–D-14 e gates HT-R01 permanecem pendentes, sem programação ou execução.

## 4. Deliberações BL02-D01 a BL02-D15 (propostas, nenhuma aprovada)

| ID | Tema | Alternativa de referência | O que ainda está aberto |
| --- | --- | --- | --- |
| BL02-D01 | Remuneração Imagine | Mensalidade por pacote + adicionais autorizados, alternativa variável a avaliar | Valores/taxas pendentes |
| BL02-D02 | Gasto com anúncios | Cliente titular paga diretamente Google/Meta no modelo-base | Contrato de mídia e mandato pendentes |
| BL02-D03 | Formas de pagamento | Cartão recorrente e Pix Automático como candidatos prioritários; avulso/boleto condicionais | Gateway e disponibilidade pendentes |
| BL02-D04 | Desconto Pix | Zero, 5% ou próprio após custo e decisão expressa | Nenhum desconto decidido |
| BL02-D05 | Parcelamento implantação | Avaliar cartão parcelado sem presumir quantidade; não ofertar Pix parcelado | Condição comercial pendente |
| BL02-D06 | Primeiro vencimento | Definir prazo próprio após início elegível | Não copiar 90 dias de Sites |
| BL02-D07 | Inadimplência | Notificação, tolerância e suspensão seletiva próprias | Não copiar 7/30 dias CRM |
| BL02-D08 | Promoção/reajuste | Versionar contratos e vigências sem preços fictícios | Percentuais/índices pendentes |
| BL02-D09 | Autorização de aumento de mídia | Elevação fora do teto exige titular/financeiro autorizado | Alçada numérica pendente |
| BL02-D10 | Redistribuição de verba | Somente dentro de limites aprovados e governança PERF-08 | Habilitação N2/N3 pendente |
| BL02-D11 | Pausa emergencial | Mandato expressamente delimitado ou aprovação específica, com confirmação externa | Runbook e permissões pendentes |
| BL02-D12 | Cancelamento Imagine | Não equivale a parar Google/Meta; informar responsabilidades | Texto jurídico e procedimento pendentes |
| BL02-D13 | NFS-e e gateway | Adoção após revisão jurídica/fiscal e cotação | Fornecedor, tributos e integração pendentes |
| BL02-D14 | Honorários por mídia | Só sob contrato próprio, fórmula e aprovação específica | Não adotados nesta proposta |
| BL02-D15 | Reembolsos | Separar serviço Imagine de anúncios e responsabilização | Cláusulas e políticas pendentes |

**Sequência recomendada:** definir primeiro o responsável financeiro e o tipo de assinatura; depois as condições dos meios de pagamento, vencimentos, suspensão e reembolso. **Preços, quotas e desconto final dependem de BL-04** (custos reais e simulações 100–700).

## 5. P0 originais preservados (9/9)

| ID imutável de origem | Pendência ainda aberta | Requisitos candidatos |
| --- | --- | --- |
| PERF-02/P0-04 | Orçamento e aprovação em PERF-08/11 antes de escrita | BL02.16–20 |
| PERF-03/P0-GA-02 | Alçadas financeiras e teto/suspensão Google, incluindo compartilhamento e overdelivery | BL02.16–22 |
| PERF-04/P0-META-05 | Orçamento, autonomia, limites, incidentes e aprovação de ações Meta sensíveis | BL02.16–23 |
| PERF-07/P0-EXP-04 | Orçamento de experimento, autorização, pausa, retentativa e reconciliação | BL02.16–22 |
| PERF-08/P0-AGT-03 | Governança financeira Google/Meta, custos IA, cotas, aumentos, pausas e compensações | BL02.17–25 |
| PERF-11/P0-MON-02 | Alçadas emergenciais, bloqueios, pausa/retomada, orçamento e responsabilidades | BL02.20–23 |
| PERF-12/P0-COM-03 | Meios de pagamento, Pix Automático, cartão, conciliação, reembolso, chargeback, NFS-e | BL02.04–08,14 |
| PERF-12/P0-COM-04 | Vencimento, inadimplência, suspensão, cancelamento, reativação, retenção e portabilidade | BL02.09–13 |
| PERF-12/P0-COM-07 | Conformidade Google/Meta e responsabilidade de pagamento/mídia, teto e incidentes | BL02.15–26 |

A descrição completa de cada P0 continua na [PERF-R02 — Anexo A](PERF-R02-ANEXO-TESTES-PENDENCIAS.md). Agrupar ou analisar um P0 não o considera resolvido; baixa exigirá decisão, evidência e homologação pertinentes.

## 6. Estados técnicos e financeiros distintos

- **Pagamento Imagine:** autorizado pelo pagador → cobrança emitida → processada → liquidada/compensada, com contestação, estorno e conciliação em trilhas independentes.
- **Mídia no Google/Meta:** plano proposto → aprovação humana quando necessária → comando validado → enviado → aceito tecnicamente → **estado oficial confirmado** e gasto reportado separadamente.
- **Bloqueio de segurança:** bloqueio de novas ações internas não significa pausa de anúncio externo.
- **Reativação e cancelamento:** serviço Imagine, permissão de API, campanha externa e pagamento de mídia são estados distintos.
- **Erro inconclusivo:** consulta/reconciliação antes de reexecutar; não repetir cegamente alterações de gasto.

## 7. Validações futuras BL02-V01 a BL02-V27 (nenhuma executada)

1. **BL02-V01:** Valor de assinatura e gasto Google/Meta aparecem separados.
2. **BL02-V02:** Comissão por verba não é cobrada sem contrato próprio.
3. **BL02-V03:** Taxa de implantação e carência Sites não se aplicam por cópia.
4. **BL02-V04:** Meio de pagamento sem gateway homologado não aparece como ativo.
5. **BL02-V05:** Pix Automático sem autorização bancária válida não débita.
6. **BL02-V06:** Pix/QR emitido não é pagamento confirmado.
7. **BL02-V07:** 5% Pix dos outros produtos não é aplicado automaticamente ao Performance.
8. **BL02-V08:** Promoção sem preço-base real não é publicada.
9. **BL02-V09:** Data de vencimento não útil segue regra própria ainda pendente.
10. **BL02-V10:** Contrato Performance não herda 7/30 dias de suspensão do CRM.
11. **BL02-V11:** Suspensão Imagine não cancela contas externas nem Sites/CRM.
12. **BL02-V12:** Cancelamento preserva trilha e dados em trânsito segundo política futura.
13. **BL02-V13:** Reativação não reinicia campanhas pausadas externamente sem autorização.
14. **BL02-V14:** Reembolso serviço não garante devolução de gasto Google/Meta.
15. **BL02-V15:** Agente não adiciona cartão do cliente nem muda pagador.
16. **BL02-V16:** Orçamento médio diário não é tratado como hard cap instantâneo.
17. **BL02-V17:** Redistribuição não excede limite autorizado sem nova alçada.
18. **BL02-V18:** Aprovador de outra empresa não pode aprovar verba.
19. **BL02-V19:** Aceite antigo de conta A não autoriza mutação na conta B.
20. **BL02-V20:** N1 não escreve e N3 não amplia o próprio teto.
21. **BL02-V21:** Bloqueio interno não é exibido como pausa oficial de campanha.
22. **BL02-V22:** Timeout de alteração exige reconciliação antes de retry.
23. **BL02-V23:** Alerta mostra latência do gasto e não promete encerramento de cobrança.
24. **BL02-V24:** Quota de IA esgotada não gera cobrança surpresa nem perda de lead.
25. **BL02-V25:** Logs não contêm credenciais ou dados completos de pagamento.
26. **BL02-V26:** Regra tributária ainda não revisada permanece explicitamente pendente.
27. **BL02-V27:** Aceite funcional não ativa Pix, cartão, anúncios, agentes ou programação.

## 8. Registro da aprovação funcional, dependências e passagem

- BL-01 aprovado **somente como estrutura comercial inicial**; quatro P0, preços e cotas numéricas seguem abertos.
- BL-03 trata CRM, Sites, direitos e portabilidade. BL-04 estima taxas de gateway, IA, equipe, custos e limites para 100–700 clientes. BL-05/07 homologarão no futuro segurança de contas, alçadas, prompts, N1/N2/N3 e recuperação.
- Nenhum valor ou percentual de Pix para Performance foi aprovado; o desconto de 5% e outras condições dos contratos Sites/CRM **não são transferidos automaticamente**.
- Cancelar ou suspender Imagine **não garante interrupção de gastos de mídia** até operação oficial autorizada e confirmada.
- **Aprovação funcional expressa registrada em 03/11/2026:** BL02.01–BL02.27 e BL02-V01–V27 fazem parte do planejamento aprovado; nenhum dos 9 P0 foi baixado, e BL02-D01–D15 continuam como propostas a decidir separadamente. Nenhum teste foi executado e não há autorização para cobrança, comando financeiro ou programação.

## 9. Referências para homologação posterior (consultadas em 03/11/2026)

- [Banco Central — Pix Automático, recebedores](https://www.bcb.gov.br/estabilidadefinanceira/pix-automatico-recebedores)
- [Banco Central — Pix Automático, pagadores](https://www.bcb.gov.br/estabilidadefinanceira/pix-automatico-pagadores)
- [Banco Central — Instrução Normativa BCB 513 e alterações vigentes](https://www.bcb.gov.br/estabilidadefinanceira/exibenormativo?numero=513&tipo=Instru%C3%A7%C3%A3o+Normativa+BCB)
- [Google Ads — limites de gastos](https://support.google.com/google-ads/answer/10486637?hl=pt-br)
- [Google Ads API — mutações](https://developers.google.com/google-ads/api/docs/mutating/overview)
- [Meta — Marketing API](https://developers.facebook.com/docs/marketing-api/)

> **BL-02 v0.1 APROVADO FUNCIONALMENTE em 03/11/2026**, exclusivamente quanto aos 27 requisitos e aos 27 critérios de validação futura. Todos os preços, meios de pagamento executáveis, 15 deliberações BL02-D01–D15, descontos, alçadas, procedimentos legais e financeiros e os 9 P0 permanecem sujeitos à decisão e homologação futura. **Sem iniciar programação ou operações reais.**
