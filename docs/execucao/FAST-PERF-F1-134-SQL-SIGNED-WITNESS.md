# FAST-PERF F1-134 — atestado HMAC da witness exigido pelo próprio SQL (laboratório)

**Base F133 / PR #241. Status: DRAFT / NO-GO. Código exclusivamente para duas instâncias PostgreSQL 16 descartáveis em GitHub Actions. Nenhuma migração operacional.**

## Problema corrigido neste cenário de CI

F133 provou que `perf_f132_primary.record_with_generation` podia ser chamada diretamente por quem possuía a credencial `perf_f132_primary_bridge`, sem consultar a testemunha externa. F134 **revoga EXECUTE dessa função** ao papel F132 do laboratório e cria `perf_f134_primary.record_signed`. A nova função exige que o banco principal verifique o HMAC emitido pela outra instância antes de chamar o journal F129.

- `perf_f134_witness.issue_permit` vincula `operation_id | tenant | fingerprint | epoch | issued_at` a um HMAC-SHA256 com chave gerada aleatoriamente pelo job. A witness primeiro grava o nonce via F132 na mesma transação SQL que produz o atestado.
- `perf_f134_primary.record_signed` confere formato, expiração (30 segundos), tolerância de relógio futuro (5 segundos) e HMAC com chave armazenada em tabela privada. Se válido, aplica guardas de geração/quarentena F132/F129 no banco principal.
- As tabelas de chaves **não concedem SELECT** aos usuários SQL de laboratório. Nenhuma chave real ou chave de produção é colocada no repositório.
- O novo bridge PHP usa apenas os papéis restritos F134. Seus retornos sempre têm `action_authorized=false`.
- Testes confirmam falhas por identidade/tenant/fingerprint/epoch/horário modificados, assinatura falsificada, token vencido, grants legados revogados, nonce duplicado, testemunha indisponível, falha no primário após o nonce ser queimado.

## Limitações materiais: NO-GO

1. **Apenas laboratório:** contas, CNPJ, serviços, chaves e roles são sintéticos; não há IAM/MFA/S2S real do Portal, mTLS, KMS/vault, pool seguro nem consentimento.
2. **Chave simétrica replicada em dois bancos:** administradores/owner privilegiados conseguem ler ou substituir segredo. HMAC prova conhecimento da chave, **não independência criptográfica forte** do domínio emissor; em produção, considerar assinatura assimétrica e autoridade distinta.
3. **Replay após rollback:** o nonce ainda pode ser readmitido por um primário restaurado usando um atestado HMAC válido emitido antes do rollback, dentro da janela de validade. Se **ambas** as bases forem restauradas, os gaps de F133 permanecem.
4. **TOCTOU:** o piso externo pode avançar depois de emitir um token e antes do primário registrar. São duas transações e não existe consenso/XA/2PC. Não há barreira de dispacho obrigatório nem autorização de provedores Ads.
5. **Consumo ambíguo:** erro do banco principal depois de gravar na witness queima o nonce; retomada segura exige reconciliar autoridade, geração e recibos de forma independente.

Próxima prioridade: testes de rollback com atestado ainda válido, alteração do epoch depois da emissão, credencial privilegiada e substituição de HMAC simétrico por atestado assimétrico verificável; plano auditado para recuperação sem replay. Todos os gates P0/HT-R01/SEC-R03/T3, revisão AppSec/DBA e homologação seguem abertos.

**Sem merge main, deploy, clientes, contas Ads, cobranças, anúncios, VPS ou gastos.**
