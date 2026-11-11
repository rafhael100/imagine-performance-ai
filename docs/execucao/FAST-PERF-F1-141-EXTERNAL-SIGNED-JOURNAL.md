# FAST-PERF F1-141 — diário assinado externo aos três backups (emulador CI)

**Base:** F140, PR #248. **Estado:** DRAFT / NO-GO. Nenhum acesso à produção, banco de cliente, anunciante ou API de mídia. Nenhum fornecedor adicional ou despesa.

## Problema reproduzido na F140

No experimento F140, primário, testemunha e terceira âncora PostgreSQL foram restaurados juntos aos snapshots de geração 8. Todos perderam o histórico de B (registrada em geração 9) e B foi admitida novamente. Acrescentar bancos dentro do mesmo domínio de restauração não fornece antirollback verdadeiro.

## O que a F141 prova no laboratório

- `F141SignedJournalAuthority`: **emulador local de autoridade fora dos backups SQL**. Armazena eventos `init`, `advance` e `reserve` em um diário JSONL no `RUNNER_TEMP` do GitHub Actions. Cada evento tem sequência, `prev` SHA-256 e assinatura Ed25519; a chave privada efêmera é criada pelo CI e não é versionada.
- Usa `flock` para serializar reservas de operações/fingerprints e `fsync` na escrita. O método `reserve` não tem `unreserve`: falha/resultado incerto depois da reserva queima a operação; não tenta disparar outra vez.
- `F141JournalGatedRegistration` é um **gateway PHP apenas de laboratório**. Compara a geração primária, a terceira âncora SQL e a geração no diário externo, reserva antes de invocar o caminho F139. Nunca autoriza campanhas: `action_authorized=false`.
- O workflow cria três PostgreSQL16 efêmeros, grava A, tira backup **dos três bancos SQL**, avança a geração do diário externo e dos bancos para 9, grava B, restaura os três bancos a geração 8 sem restaurar o diário externo e verifica que o gateway recusa B e uma operação inédita D. Mesmo após alinhamento **manual em CI** da geração SQL a 9, o diário ainda recusa B porque preservou o histórico.
- Verifica autenticação da integridade da cadeia Ed25519: alterar um evento antigo invalida a cadeia e interrompe a admissão.
- Verifica **duas falhas esperadas**, ambas obrigatoriamente NO-GO:
  1. **Bypass de SQL:** o papel SQL do F139 continua podendo chamar sua função de registro sem passar pelo gateway PHP. Uma chamada direta com permit F136 válido continua criando operação com os bancos restaurados, apesar de o diário externo indicar geração 9.
  2. **Truncamento de prefixo válido:** se um operador corta a parte final do próprio diário JSONL e resta somente o evento de gênese (assinado validamente), a verificação criptográfica local NÃO sabe que existiu uma geração posterior. Precisa de high-watermark/âncora com retenção verdadeiramente independente e verificável. Ed25519 detecta modificação, **não prova que todo o histórico sobreviveu**.

## Riscos e requisitos não cumpridos

**Não existe autoridade externa real nesta entrega.** O arquivo está no mesmo runner transitório, controlado por quem executa o teste. Pode ser excluído, copiado, truncado ou restaurado e, por isso, não tem WORM, armazenamento imutável, isolamento administrativo, KMS/HSM, certificação de disponibilidade ou prova independente de monotonicidade.

O fluxo ainda não obriga o próprio SQL a verificar a autoridade do diário. O teste de bypass foi adicionado justamente para NÃO aprovar esse desenho falsamente. A solução de produção deve vincular **toda** decisão final de registro/dispatch à autoridade externa e impor que nenhuma credencial operacional tenha um caminho direto. Uma transação contra bancos distintos não tem 2PC/XA garantido. O consumo ambíguo precisa reconciliação auditada antes de reabilitar operações. Exige identidades de workload, mTLS/OAuth, isolamento de roles, revisão independente de AppSec/DBA e simulações de falha de rede/failover, além de provas imutáveis de commit.

A F141 **não implementa nem autoriza** contas de anunciantes, Google Ads, Meta Ads, valores de campanhas, clientes, billing, deploy, VPS ou produção. Todos os gates P0/HT-R01/SEC-R03/T3 estão NO-GO.

## Próxima frente sugerida

F142: fechar o bypass de SQL da F141 em um protótipo auditável de autoridade/dispatch, com comprovação online obrigatória e antirollback realmente não restaurável fora do domínio de backups. Sem fornecedor autorizado, a validação pode avançar por mocks e testes de invariantes, mas não se pode declarar aprovação de produção.
