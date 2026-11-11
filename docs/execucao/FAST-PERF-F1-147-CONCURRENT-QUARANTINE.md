# FAST-PERF F1-147 — concorrência e quarentena persistente do executor sintético

**Base:** F146, PR #254. **Escopo:** GitHub Actions, PHP 8.4, subprocessos isolados apenas logicamente, PostgreSQL16 efêmeros para regressões F145 e execução simulada sem rede, Ads, OAuth, contas, valores nem despesas. **PR DRAFT / NO-GO**.

## Implementação

- `F147StickyQuarantineExecutor` envolve o executor F146 (que já valida grants Ed25519 de destino exclusivamente `synthetic-noop`). Todas as solicitações do subprocesso são serializadas por `flock`, com bloqueio persistente em arquivo de incidente separado do diário de receipts.
- O comando `bin/lab/f147_quarantined_synthetic_executor.php` aceita um JSON limitado (`grant` e `fault=none|ack_lost|target_down`). Trata o retorno sintético `synthetic_noop_recorded` como único efeito; sempre `action_authorized=false`, sem qualquer dependência SDK ou API Ads.
- Se o alvo **sintético** está indisponível antes de registrar um recibo, responde `synthetic_target_unavailable` sem queimar nonce; a operação pode ser tentada após resolução dessa falha conhecida.
- Se uma resposta **sintética** é perdida **após** o receipt, ou o executor retorna resultado desconhecido, grava incidente `in_doubt` com `fsync` e entra em quarentena. A partir desse ponto, **todas** as operações seguintes, inclusive as inéditas e as que já tinham recibo, são negadas. Não há ação de liberação automática; registro informa `manual_independent_review_only`.
- Testes lançam dois processos PHP filhos simultâneos com o mesmo grant assinado para A e exigem **um único** `synthetic_noop_recorded` + **um** `executor_duplicate_denied`. Testes incluem outage anterior ao receipt e retry seguro, perda de ACK depois do receipt, arquivo de incidente persistido, recusa de C/D em quarentena, configuração de segurança inválida e ausência de tokens Google/Meta e chave privada do emissor no ambiente filtrado do subprocesso.
- Teste negativo explícito: operador que controla o mesmo runner pode apagar o arquivo da quarentena e retomar a simulação. **CI verde demonstra essa limitação, não isolamento de segurança real**. Credenciais de Ads reais e egress externo ainda não existem.

## Limitações

A F147 ainda compartilha domínio de falha, UID/credenciais e armazenamento local do mesmo runner. `flock`, arquivos high-watermark e quarentena não resistem a atacante com permissão de manipular host/arquivos ou restaurar marcador e diário juntos. A aplicação de negócio não é obrigada a usar este executor e pode, em arquitetura futura mal desenhada, acessar provedores diretamente; não há segregação efetiva de credenciais de mídia. Todo efeito permanece um receipt local, não uma campanha, um evento de Google/Meta, uma cobrança ou dinheiro.

Os exercícios anteriores de restore dos quatro bancos, recibos sintéticos e MAC capturado continuam presentes para regressão. O executor não resolve atomicidade distribuída 2PC/XA, perda real de resposta do provedor, replay físico em domínio externo, IAM/MFA/S2S, chaves KMS/HSM, WORM/offsite ou controle humano de mídia.

**P0 / HT-R01 / SEC-R03 / T3 = NO-GO. Sem merge `main`, deploy VPS, cliente real, anúncios, cobrança ou gastos.**

## Próximos gates

F148: contrato de serviço externo de execução (sem credenciais reais) com política de privilégio mínimo, deny-by-default e entrega assíncrona com reconciliação manual. Testar host indisponível, replay concorrente, credencial de workload revogada, log append-only independente e egress de rede restrito. Exigir auditoria AppSec/DBA e separação real de identidades/credenciais para passar de laboratório a homologação.
