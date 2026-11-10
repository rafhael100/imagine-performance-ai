# FAST-PERF F1-127 — circuit breaker para resultado ambíguo da witness (somente laboratório)

**Base:** F1-126. **Estado operacional:** DRAFT / NO-GO. Este commit introduz uma máquina de estados PHP puro, isolada dos serviços e sem acesso a banco, anúncios, faturamento, credenciais ou rede.

## Garantias locais demonstráveis

- Toda tentativa começa como `pending` com identificador e fingerprint; duplicatas nunca abrem uma segunda operação.
- Apenas resposta literal `committed` para o ID/fingerprint pendente pode marcar `confirmed`. `rejected` não autoriza execução.
- Falha de conexão, timeout, estado desconhecido, resposta contraditória, callback repetido ou recibo desconhecido bloqueiam o fluxo; incerteza mantém quarentena global.
- Não existe método de reabertura local; `requestLocalRecovery()` sempre nega. Nenhum resultado `confirmed` autoriza lançamento, dispatch ou gastos.
- Executar: `php tests/Standalone/F127AmbiguousWitnessQuarantineTest.php`. Casos são sintéticos e não substituem testes SQL de F125/F126.

## Limitação de segurança deliberada

A máquina está **em memória** e é apenas prova de política. Reinício do processo perde estado; não é uma barreira contra restore/replay, falha simultânea, fraude de origem ou ataque adversarial. Não integra a função Laravel F123 nem grava recibos F124/F125. **Não tratar como solução definitiva.**

## Próxima integração, dependente de revisão

Persistir operação + identidade/tenant/fingerprint/epoch em ledger imutável e fora do domínio de rollback; usar confirmação autenticada e durável do witness; reconciliar timeout sem refazer operação ou reutilizar nonce; criar procedimento de quarentena com recuperação por autoridade independente e observabilidade. Modelar cenários de falha entre preflight, gravação local, escrita externa e retorno. Ensaiar perda simultânea/restore, concorrência e spoofing; revisão AppSec/DBA obrigatória.

Manter abertos: Portal IAM/MFA/S2S real, chaves/secret vault, RLS/roles F112/F117/F119, OAuth Google/Meta, consentimento, limites e aprovações humanas, idempotência e reconciliação de mídia, QA/VPS, P0/HT-R01/SEC-R03/T3. Sem merge main, deploy ou despesas.
