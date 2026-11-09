# FAST-PERF F1-33 — Âncora adicional no Portal em restauração isolada

**Escopo:** GitHub Actions de laboratório com PostgreSQL 16 descartável; PR filho F1-32 #85. O experimento demonstra uma mitigação limitada para a vulnerabilidade do F1-32: o PostgreSQL do Portal guarda uma segunda marca de utilização do nonce Ed25519, independente do banco de recibos restaurado.

## Implementação e teste

- O workflow executa F1-30/31/32, inclusive a restauração que prova o replay vulnerável.
- Com a fonte Portal fictícia no recovery_epoch 8, cria tabela de hashes de nonce no banco Portal sob schema privado perf_f33_anchor, e uma função de consumo com role limitada a EXECUTE.
- A classe SyntheticPortalAnchoredReplayGate confirma o estado do Portal e a assinatura Ed25519 **antes** de consumir o nonce na âncora Portal, com transação autocommit. Somente depois usa o verificador F1-31 e grava o recibo no outro PostgreSQL.
- O teste guarda um backup dos recibos do PostgreSQL secundário, consome um nonce novo com êxito, restaura esse backup e comprova que o caminho **com a âncora Portal** recusa o replay mesmo após a perda do recibo local.
- **Controle adversarial indispensável:** chamar diretamente o verificador antigo F1-31 depois de restaurar a base local ainda permite reutilização. Isso comprova que toda rota operacional precisaria obrigatoriamente passar pelo controle externo e que um recurso pronto para produção não existe neste PR.

## Limites e bloqueios

**Não aprovar deploy ou merge por causa desse teste.** A âncora Portal é apenas mais um banco restaurável. Perda ou restauração simultânea das duas bases pode reabrir o replay. Não há commit distribuído entre as duas operações; falha local após gravar a âncora implica autorização consumida sem resultado. A função SQL da âncora não verifica assinatura por si: uma role com permissão e conhecimento do nonce pode pré-consumi-lo. Um processo comprometido pode chamar o verificador antigo que não aplica âncora. A classe usa objeto de autorização de laboratório fabricável e consulta a fonte Portal por SQL direto no mesmo runner, sem mTLS, KMS ou prova de titularidade real da campanha.

Para produção: centralizar enforcement, identidade S2S autenticada, autoridade de nonce não restaurável sem high-watermark externo, revogação/epoch monotônicos, idempotência por intenção, testes de corrida/restore/recovery, auditoria, revisão AppSec/DBA e requisitos comerciais/LGPD.

Sem main, fast-track, VPS, clientes, anúncios, orçamento, cobrança, licenciamento, custos ou operação real. P0, HT-R01 e SEC-R03/T3 seguem abertos.
