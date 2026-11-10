# FAST-PERF F1-32 — Replay depois de restaurar a autoridade dos recibos

**Tipo de teste:** negativo e proposital, exclusivamente PostgreSQL 16 descartável no GitHub Actions. A branch é filha da F1-31 PR #83.

## Objetivo

Reproduzir uma falha que permanece mesmo depois de conectar o Portal (Ed25519 e reconsulta SQL) a um registro de nonce em banco separado: um **backup antigo da própria autoridade de recibos** pode apagar a comprovação de que um token já foi usado.

## Sequência de teste

1. Executa F1-30 e F1-31 integralmente (incluindo as verificações de revogação Ed25519 do Portal).
2. Com uma autorização fictícia já consumida na autoridade PostgreSQL separada, obtém backup real por pg_dump apenas dos dados da tabela de recibos, em arquivo temporário de CI.
3. Gera outra autorização Ed25519 pelo emissor original do Portal, consome a autorização e demonstra que uma tentativa repetida falha.
4. Restaura os dados do backup antigo por TRUNCATE e psql, reproduzindo perda do recibo mais novo.
5. **Teste esperado-negativo:** o mesmo token assinado volta a ser aceito após restore. Isso representa falha de segurança, e o CI considera sucesso quando a vulnerabilidade é reproduzida, não corrigida.
6. Avança a recovery_epoch persistida na fonte Portal, em banco separado, e verifica que o token anterior passa a ser negado por divergência da revisão de origem.

## O que isso comprova e o que NÃO comprova

A reconsulta do Portal com epoch divergente pode impedir reutilização de tokens antigos **após a atualização da época da fonte**. Não protege o intervalo antes da atualização nem garante segurança quando Portal e autoridade são restaurados para um estado antigo em conjunto. Um epoch igual ou restaurado não prova que nenhum recibo se perdeu. A proteção de produção precisa de high-watermark monotônico fora do domínio restaurável, detecção obrigatória de restauração, recuperação fail-closed, idempotência e revisão AppSec/DBA.

A autoridade SQL de F1-31 também não autentica o chamador do consumo de nonce; pré-consumo malicioso continua possível se o nonce for conhecido. Falta identidade de workload, mTLS, contrato comercial real, propriedade de campanhas, efeito exatamente uma vez, filas/retentativas e revogação institucional.

Nenhuma alteração em main, fast-track, VPS, conta real, Google/Meta, CRM, cobrança ou infraestrutura paga. **CI verde não resolve a vulnerabilidade:** produto permanece bloqueado para produção e P0, HT-R01, SEC-R03/T3 permanecem abertos.
