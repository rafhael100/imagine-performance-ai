# FAST-PERF F1-144 — restore conjunto dos quatro bancos, teste negativo esperado

**Base:** F143, PR #251. **Escopo:** CI com quatro serviços PostgreSQL16 efêmeros no mesmo runner GitHub; arquivo assinado F141 excluído desses backups. **DRAFT / NO-GO**.

## Problema

F143 acrescentou um quarto oráculo online para consumir permissões uma única vez. Quando só o primário, a witness e a terceira âncora são restaurados, o quarto oráculo preserva a linha `redeemed=true` e rejeita a repetição da operação B com MAC capturado.

F144 prova que o oráculo não pode ser considerado independente se **ele também fizer parte do conjunto de restauração**:

1. Registra A no epoch 8 e executa `pg_dump --data-only` para as tabelas de estado de **todos os quatro bancos**, sem copiar o diário JSONL assinado.
2. Avança gerações de quatro bancos e do diário para 9; B é registrada normalmente e a autorização no quarto oráculo é marcada `redeemed=true`.
3. Restaura os dados dos quatro bancos aos snapshots antigos (epoch 8). O oráculo perde a linha B e a prova de resgate. O diário em arquivo ainda conhece B e generation 9.
4. Em laboratório, um administrador realinha os pisos SQL ao epoch 9. O gateway PHP respeita o diário e recusa B, porém uma credencial com acesso à função de emissão F143 pode reutilizar o MAC legítimo capturado, recriando a linha B no oráculo restaurado. Uma credencial de witness reemite nonce B após restauração. Chamada direta à função SQL F143 consome o novo permit e **registra B novamente**.
5. O workflow deve ser verde **somente se reproduzir esse risco**. Nenhuma execução real de anúncio, cobrança, faturamento ou cliente.

## Limites e decisão

O quarto banco também é administrável e restaurável no mesmo runner, portanto não é offsite, WORM ou autoridade antirollback independente. A função SQL não consulta diretamente a versão integral atual do diário Ed25519 e aceita MAC passado dentro do TTL. Ter um quinto banco no mesmo domínio de falha não resolve o problema. A integração real exigiria isolamento de credenciais e restauro, retenção verificável fora da administração do produto, prova atômica de consumo, validação online da identidade do workload e procedimento de recuperação segura após queda ou resposta ambígua.

**Propriedades ainda ausentes:** logs imutáveis duráveis, KMS/HSM, identidade S2S do Portal, MFA/autorização empresarial, proteção contra particionamento e replay de credenciais privilegiadas, recuperação de transações sem 2PC, revisão independente AppSec/DBA. Gates P0/HT-R01/SEC-R03/T3 = **NO-GO**.

**Não realizar merge na main, deploy, VPS, anúncios, acesso a clientes, cobrança ou novos gastos.**

**Próxima fronteira (F145):** fortalecer o contrato de autoridade externa sem acoplar o produto a provedores pagos: modelar consumo online exclusivo, fail-closed e bloqueio administrativo do acesso de SQL ao emissor, acompanhado de provas negativas de comprometimento e restore. Não declarar produção segura até uma autoridade realmente independente e auditada existir.
