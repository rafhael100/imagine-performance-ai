# FAST-PERF F1-143 — consumo online obrigatório do atestado em quarta autoridade (CI)

**Base F142 / PR #250.** Somente laboratório com 4 contêineres PostgreSQL 16 no mesmo runner do GitHub Actions e diário Ed25519 efêmero no `RUNNER_TEMP`. **DRAFT / NO-GO.**

## Problema da F142 e mudança de protocolo

F142 exigia HMAC dentro do SQL, porém o atestado válido capturado podia ser reutilizado após restauração dos três bancos originais, pois o SQL não consultava estado *atual* do diário. F143 adiciona um **quarto PostgreSQL descartável, não incluído no backup dos três anteriores**, que armazena `permits` com operação, tenant, fingerprint, geração, sequência e hash do diário, hora de emissão, HMAC e flag `redeemed`.

1. Um gateway PHP exclusivamente CI exige coerência entre primário, âncora e piso do quarto banco; consulta e reserva no diário assinado F141. Depois, a credencial de **emissão** restrita da quarta instância chama `perf_f143_oracle.issue_if_current`, que verifica HMAC e piso sob lock e cria permit único por fingerprint e operação.
2. A função SQL do primário `perf_f143_primary.redeem_online_oracle` exige **UPDATE remoto condicional** pela `postgres_fdw` que muda `redeemed=false` para `true` em exatamente um permit vinculado a todas as reivindicações. Um trigger da quarta instância impede alterações arbitrárias, consumo duplicado e geração obsoleta.
3. Somente após consumo online válido a função chama os verificadores F142/F139/F136, incluindo HMAC, `FOR SHARE` na terceira âncora, witness nonce e journal primário. A credencial F142 perde `EXECUTE` da função antiga. A nova credencial F143 recebe somente acesso à nova função e leitura de geração local. A função nunca autoriza Ads, campanhas, mídia ou cobrança (`action_authorized=false`).
4. O teste tira backups **apenas do primário, witness e terceira âncora**, registra B em epoch9 e consome o permit no quarto banco. Depois restaura os três bancos para epoch8, mas deixa o quarto banco e o arquivo assinado em epoch9. Reemite-se em CI um novo nonce de witness de B após alinhar pisos SQL, e tenta-se repetir a operação com **o exato HMAC capturado e válido**. O SQL F143 deve recusar porque o quarto banco já registrou `redeemed=true`. O pipeline também testa MAC falso, ACLs legadas revogadas, indisponibilidade da quarta instância e recusa de operação nova quando há divergência de geração.

## Limites de segurança — NÃO É PRODUÇÃO

- Os quatro bancos compartilham um único domínio de desastre/administração no GitHub Actions. Se **o quarto banco também for restaurado**, sua evidência de `redeemed` desaparece, reabrindo replay. Isto não implementa WORM, offsite, monotonicidade real ou retenção auditável.
- `postgres_fdw` não oferece commit distribuído atômico 2PC/XA entre todos os bancos. Falhas de rede, queda entre commits, resposta ambígua, partição e rollback parcial exigem quarentena, reconciliação humana e validação operacional.
- O HMAC é simétrico e a chave é conhecida pelo CI e pelas tabelas de laboratório; perfis superuser/owner podem contornar ACLs. O diário Ed25519 ainda permite truncamento até prefixo antigo válido quando falta high-watermark externa.
- Nenhuma autenticação real de workload Portal/CRM, consentimento Google/Meta, IAM/MFA/S2S/mTLS, KMS/HSM, orçamento de campanhas, cobrança, configuração de VPS ou auditoria AppSec/DBA independente foi implantada.
- As permissões e funções antigas de outros cenários existem no laboratório para regressão, embora credenciais antigas **tenham perdido EXECUTE**. Nenhum produto real deve depender das funções CI F129–F143.

**Todos os gates P0/HT-R01/SEC-R03/T3 permanecem NO-GO.** Sem merge `main`, deploy, contas reais, criação de campanhas, cobrança ou despesas.

**Próxima investigação:** testar restore **dos quatro** bancos como caso esperado-negativo, cenários de crash/partição e caminho de reconciliação sem retry automático. Decisão de autoridade verdadeiramente externa e revisão independente ainda necessárias.
