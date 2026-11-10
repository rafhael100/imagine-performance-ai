# FAST-PERF F1-138 — terceira âncora de geração (simulada) após duplo restore

**Base F137 (PR #245). Estado: DRAFT / NO-GO. Ambiente exclusivo de GitHub Actions, três contêineres PostgreSQL16 efêmeros no MESMO runner.**

## O que foi implementado no laboratório

- **Terceiro domínio de geração simulado:** `perf_f138_anchor.epoch_floor` inicia no epoch 8 e somente o administrador do CI pode avançá-lo por `advance_floor`. A role `perf_f138_anchor_reader` possui apenas SELECT no registro.
- **Verificação obrigatória no banco principal:** função SQL `perf_f138_primary.record_if_anchor_current` consulta a terceira instância via `postgres_fdw`. Ela nega registro se o epoch externo não corresponder ao epoch do permit; se corresponder, chama a confirmação online F136 que redime o nonce da testemunha e persiste no primário.
- **Corte de caminho anterior:** o bridge F136 perde EXECUTE direto na função F136; a role F138 recebe apenas EXECUTE na nova função, e leitura da geração local. Um pré-check PHP `F138ThirdAnchorRegistration` compara gerações para evitar gastar o nonce em divergências óbvias; o check SQL é obrigatório e não confia somente no PHP.
- **Prova com dados reais sintéticos:** grava A na geração 8, emite C sem resgatar, tira backups de primário e testemunha; mantém terceira âncora fora de ambos backups. Eleva âncora, witness e primário para 9, registra B; restaura primário e testemunha à geração 8. Depois, B antigo e D inédito são recusados por divergência; chamada SQL direta com permit C ainda válido também é negada. A indisponibilidade do registro externo bloqueia novas inscrições. Todas as conexões, grants e restores são descartáveis.

## Limitações críticas

1. **A âncora não é realmente externa ou permanente.** Os três serviços compartilham runner, administrador e domínio de desastre. Restaurar a terceira base também para 8, modificar owner/role de função ou conectar com superusuário reabre possibilidades de repetição. Nenhuma proteção antirollback real foi implementada.
2. **Leitura da geração por FDW pode sofrer TOCTOU:** o piso externo pode mudar após SELECT e antes do commit final; não há lock distribuído nem atomicidade 2PC/XA para os três bancos. Esta implementação é ensaio de conceito e nunca uma garantia de execução única.
3. **Autoridade/segredo são CI-only.** Não existe identidade operacional Portal IAM/MFA/S2S, KMS/HSM, assinatura assimétrica de domínio independente, alta disponibilidade offsite, auditoria verdadeiramente imutável, consentimento de anunciantes ou API Google/Meta. F136 continua HMAC com segredo em bancos de teste.
4. **Admissão não é veiculação.** Toda resposta continua `action_authorized=false`, sem qualquer pipeline real de cobrança, orçamento, campanhas ou faturamento.

## Próximos gates

F139: simular indisponibilidade e avanço do piso durante a tentativa de inscrição (race/TOCTOU) e desenhar protocolo de recuperação com âncora fora da autoridade de restore. Depois validar com AppSec/DBA independente, Portal IAM/MFA/S2S, controles humanos de mídia paga, segredos em KMS, backups auditados e gates P0/HT-R01/SEC-R03/T3.

**Proibidos merge em `main`, deploy, acesso a clientes, anúncios reais, pagamentos, cobrança, gastos, VPS e alterações de produção.**
