# FAST-PERF F1-142 — SQL exige atestado HMAC do diário assinado (laboratório)

**Base F141 / PR #249.** Código e PostgreSQL16 sintéticos; branch isolada, PR draft, NO-GO. Sem qualquer endpoint operacional, anúncios, despesas, clientes ou cobrança.

## Falha observada na F141

Uma credencial do bridge F139 podia invocar diretamente `perf_f139_primary.record_with_locked_anchor`, sem passar pelo gateway que consultava o diário Ed25519 fora dos três bancos. A assinatura do diário, por si só, não vinculava obrigatoriamente a decisão final SQL à autoridade.

## Avanço F142

1. Revoga EXECUTE de F139 ao perfil antigo `perf_f139_primary_bridge`. O novo perfil `perf_f142_primary_bridge` recebe somente `current_generation()` e a função SECURE DEFINER `perf_f142_primary.record_with_journal_attestation`.
2. Novo gateway PHP, **restrito por APP_ENV e flags de CI**, exige geração local e da âncora iguais, consulta o diário assinado F141, reserva a operação antes de chamar qualquer banco e produz um comprovante HMAC-SHA256 de curta duração (30 s).
3. O MAC vincula `operation | tenant | fingerprint | epoch | timestamp | sequence | hash_da_cabeça_do_diário`. O PostgreSQL verifica esse HMAC **dentro da função SQL** e apenas depois invoca F139, que mantém o bloqueio remoto `FOR SHARE` da âncora e o resgate de nonce F136. Chave efêmera é sorteada pelo job Actions, mascarada e guardada em tabela SQL sem SELECT para o perfil do bridge. Sem chave fixa no Git.
4. Testes exigem que o perfil antigo não possa escrever via F139; o perfil novo não consiga ler a chave nem acessar o FDW da witness; chamadas sem MAC, com MAC falsificado ou expirado sejam negadas; registro legítimo seja aceito somente em CI, sem `action_authorized`.
5. Testes restauram primário, testemunha e terceira âncora a snapshots antigos, deixando o diário F141 fora dos snapshots. O gateway recusa tanto replay de B quanto operação nova se a geração externa diverge, e continua recusando B após realinhamento manual dos bancos.

## Falha residual esperada e importante (NO-GO)

**Um MAC verdadeiro pode ser reutilizado.** O PostgreSQL apenas verifica um comprovante de que o gateway deteve a chave e conheceu certa versão assinada do diário em um instante anterior. **O PostgreSQL não consulta diretamente o diário externo nem possui uma autoridade online independente para atestar que o estado continua atual.** Se os três bancos são restaurados, as gerações alinhadas a 9, um comprovante válido ainda estiver dentro do TTL e a testemunha puder reemitir nonce, uma chamada direta F142 pode registrar B novamente sem novo evento externo. O CI reproduz propositalmente essa vulnerabilidade. Um atacante com controle de administrador de banco ou a chave MAC também pode forjar autorizações. O HMAC é compartilhado entre emissor PHP e PostgreSQL e não representa assinatura de um serviço independente.

Outros limites: arquivo externo é apenas `RUNNER_TEMP` no **mesmo runner**; não há retenção WORM/high-watermark remoto, isolamento de administração, mTLS/KMS/HSM, identidade real do Portal, garantias 2PC/XA, garantias de recuperação após falha durante commits ou autorização de mídia real. A F141 já mostrou que truncar o diário a um prefixo antigo com assinatura válida não é detectado apenas pela cadeia Ed25519.

**Conclusão:** F142 reduz o bypass **sem atestado** dos perfis SQL restritos, mas NÃO fecha definitivamente bypass/replay após rollback nem entrega uma autoridade externa confiável. Não implantar e não fazer merge em `main`. Qualquer operação ambígua permanece em quarentena, sem retry automático.

## Próxima fronteira

F143: comparar métodos para impor **consulta e consumo online e intransferível** diretamente na decisão SQL por uma autoridade com domínio de falha e administração verdadeiramente independentes; exigir prova antirollback não restaurável e verificar a identidade do workload. Testar indisponibilidade/partição, replay de comprovantes capturados, restauração do emissor, rollback simultâneo e crash entre commits. Revisão independente AppSec/DBA, P0/HT-R01/SEC-R03/T3 continuam NO-GO.
