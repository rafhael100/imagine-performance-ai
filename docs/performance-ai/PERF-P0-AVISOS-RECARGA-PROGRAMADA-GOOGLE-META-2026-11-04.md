# PERF-P0 — Avisos Inteligentes de Recarga Programada (Google Ads / Meta Ads)

**Projeto:** Imagine Performance AI  
**Data:** 04/11/2026  
**Status:** **REQUISITO FUNCIONAL SOLICITADO PELO USUÁRIO E REGISTRADO PARA PLANEJAMENTO**, sem implementação, preços ou parâmetros técnicos homologados.  
**Relacionamentos:** [Central de Recargas de Mídia](PERF-P0-RECARGAS-MIDIA-BOLETOS-PROGRAMADOS-2026-11-04.md), [estudo de boleto/API](PERF-P0-PESQUISA-API-BOLETOS-GOOGLE-META-2026-11-04.md), [estudo Pix/API](PERF-P0-PESQUISA-API-PIX-GOOGLE-META-2026-11-04.md), BL-02, governança de agentes, auditoria e RES01-D12.

## 1. Exigência do usuário

Criar uma funcionalidade de **lembretes automáticos de recarga de mídia acessível aos demais clientes**, inclusive aqueles que **não solicitam a geração de Pix/boleto pela Imagine**. Cada cliente informa **o dia do mês em que pretende fazer a recarga**, e a plataforma o lembra, por exemplo:
- **D-7:** 7 dias corridos antes da data programada;
- **D-3:** 3 dias corridos antes da data programada;
- **D0:** na própria data escolhida.

**Esses três marcos são o padrão funcional proposto pelo usuário**; permitir edição ou desativação individual de lembretes, com permissões e controles de frequência conforme desenho futuro. Exemplo: recarga no dia 15 gera lembretes nos dias 8, 12 e 15, considerando mês de calendário e fuso acordado.

**Importante:** data de **recarga programada pelo cliente** é diferente da data de **recebimento de boleto/Pix**, emissão, vencimento e crédito na conta; não usar nomes ambíguos.

## 2. Público e casos de uso

- Clientes que recarregam manualmente Google Ads ou Meta Ads com Pix, boleto ou outro meio oficialmente elegível.
- Clientes que habilitaram geração assistida de documentos; quando houver pedido de emissão, os avisos podem continuar ativos, sem se confundirem com emissão ou pagamento.
- Suporte a várias contas publicitárias da mesma empresa, com **valor estimado e dia de recarga definidos separadamente** por plataforma/conta.
- Se a conta tiver modalidade de **cobrança automática** e o cliente não fizer recargas pré-pagas, evitar sugerir “recarga obrigatória”; disponibilizar aviso de acompanhamento financeiro adequado em fluxo separado.
- Google Ads e Meta Ads no lançamento; outros canais dependerão de expansão/homologação específica.

## 3. Configuração do cliente

Campos funcionais candidatos:
1. Empresa, provedor e identificador da conta publicitária vinculada e autorizada.
2. Ativar/desativar rotina de recarga.
3. **Dia do mês** (1–31), com política futura explícita para meses curtos (candidato: último dia válido do mês); **fuso horário** configurado ou derivado do contrato.
4. **Valor planejado** da recarga (opcional para lembrete simples; não constitui ordem financeira nem autorização de mídia).
5. Avisos: padrão **7 dias, 3 dias e no dia**, com ajustes/opt-out e prevenção de frequência excessiva.
6. Destinatários autorizados, e-mail verificado e notificações autenticadas no Portal; outros canais (como WhatsApp) só após opt-in, políticas e avaliação de custos.
7. Horário preferido, pausa de ciclos, alteração ou cancelamento de programação, acessíveis por usuários com permissão.
8. Status do ciclo: previsto, lembrete enviado, cliente informou que já fez recarga, confirmação oficial se disponível, pulado/cancelado; separar declaração do cliente de evidência financeira do provedor.

**Não exigir API financeira ou Pix/boleto para agendar os lembretes**. O serviço deve funcionar mesmo quando os métodos de pagamento do provedor estiverem indisponíveis via API.

## 4. Mensagens e UX exemplificativas

**7 dias antes:** “Sua recarga do Google Ads está planejada para 15/11. Verifique se deseja manter o valor previsto de R$ 1.500 e preparar o pagamento.”

**3 dias antes:** “Faltam 3 dias para a recarga planejada da conta Google Ads. Acesse a plataforma oficial para verificar os meios de pagamento disponíveis.”

**Na data:** “Hoje é a data que você programou para a recarga do Google Ads. Se já concluiu, marque como realizada para encerrar os avisos deste ciclo.”

Esses exemplos **não implicam cobrança gerada, Pix emitido, saldo insuficiente nem pagamento efetuado**.

## 5. Regras de processamento e segurança

- Criar eventos mensais idempotentes por empresa, conta, ciclo, marco de aviso e canal de envio; não disparar duplicatas por reprocessamento.
- Programações alteradas devem cancelar/substituir eventos futuros de forma rastreável; preservar auditoria das notificações já enviadas.
- Se o cliente marcar “já fiz a recarga”, **suprimir os lembretes restantes deste ciclo**, registrando **“informado pelo cliente”**; não marcar “pagamento confirmado” ou “saldo creditado” sem informação oficial.
- Se confirmação oficial e autorização adequada estiver disponível, reconciliar a conta e o ciclo; **nunca inferir pela simples abertura de e-mail ou captura de comprovante**.
- Datas de 29, 30, 31 em meses curtos e feriados/fins de semana exigem regra comunicada e aprovada antes de implementação; como padrão **candidato**, último dia civil do mês sem adiar a notificação automaticamente, salvo escolha do cliente.
- Consentimento/preferência de canais e proteção de dados pessoais; não enviar identificadores sensíveis, códigos Pix ou documentos financeiros a destinatários sem verificação.
- Evitar sobreposição de lembrete genérico e aviso inteligente de saldo baixo: eventos distintos, consolidáveis, nunca automaticamente aumentando orçamento ou ordenando pagamento.
- Sem custo de uso extra presumido; custos de e-mail e WhatsApp devem entrar no estudo de viabilidade; clientes podem desativar o serviço segundo termos definidos.
- Preservar integridade de fila, logs, retries, monitoramento e exceções sem interrupção injustificada de proteção financeira/segurança.

## 6. Critérios de homologação futuros

1. Uma conta com recarga todo dia 15 dispara D-7, D-3 e D0 em datas corretas no fuso configurado.
2. Duas contas Google/Meta, com datas/valores diferentes, não têm eventos misturados.
3. Mudança de dia/valor durante o mês reprojeta avisos futuros sem duplicar os já enviados.
4. Ativar/desativar um marco específico funciona sem apagar preferências ou histórico.
5. Cliente marca recarga realizada: os próximos avisos são suprimidos; status não é “pago confirmado”.
6. Cliente de aviso simples não precisa de capacidade de emissão por API Google/Meta.
7. Falha no envio causa retry idempotente e alerta apropriado; não envia mais de uma vez o mesmo marco.
8. Destinatário sem e-mail verificado ou preferência revogada não recebe notificações com dados da conta.
9. Ciclo próximo de mês curto (31 de janeiro para fevereiro) e férias/feriados segue política publicada e aprovada.
10. Conta com pagamento automático recebe descrição adequada; não é induzida a pagar duas vezes.
11. Sem saldo oficial, a Imagine não afirma “saldo insuficiente” com base apenas em gasto acumulado.
12. Lembretes não autorizam novo gasto, pagamento, aumento de budget nem geração automática de Pix/boleto.

## 7. Pendências

- Determinar política definitiva de meses curtos, finais de semana/feriados, fuso horário e janela de envio;
- Definir canais de notificação por padrão, custo/opt-in de WhatsApp, frequência, limite e possíveis mensagens agrupadas;
- Definir se há avisos opcionais após o dia escolhido (ex.: D+1 se o cliente não confirmou) e se um ciclo perde sentido quando passa;
- Definir prazo e regras de histórico, retenção, permissões e eventual confirmação oficial da recarga;
- Custeio unitário de mensagens e governança comercial de franquias dos pacotes de um e dois canais.

**Este documento não baixa P0, não altera licenças, preços, descontos, orçamento, métodos de pagamento nem aprova programação ou operação real.**
