# FAST-PERF F1-73 — Checkpoint Ed25519 externo com versão mínima (laboratório)

**Data:** 09/11/2026. Filho da F1-72 PR #170. Execução exclusivamente em PHP CLI/CI com dois PostgreSQL temporários e arquivos efêmeros, **nunca** em infraestrutura de produção.

## Hipótese testada

A F1-72 comprovou que restaurar **dois PostgreSQL** à mesma versão antiga pode produzir um falso resultado de consistência, mesmo após operações novas terem sido confirmadas. F1-73 testa se uma evidência adicional, **assinada digitalmente e mantida fora dos dois conjuntos de backups**, pode revelar a perda desses registros em uma restauração dupla.

### Protocolo sintético

1. O runner GitHub Actions gera aleatoriamente um par Ed25519 efêmero. Arquivos `fixture-public.hex` e `fixture-secret.hex` ficam em pasta temporária do job, com chave privada com permissão restrita. **Não são KMS, HSM, WORM, raiz de confiança nem credenciais de clientes reais.** Os arquivos não são persistidos no repositório.
2. Após validar a consistência das duas bases pela F1-71, a ferramenta `f73-signed-external-anchor-cli.php` emite um envelope que assina, com `sodium_crypto_sign_detached`, as referências de checkpoints, tenant fixture e `epoch` crescente. O envelope tem schema `perf.anchor.envelope.synthetic.v1` e contém bytes assinados e assinatura destacados.
3. O verificador `SyntheticSignedFixtureAnchorGuard` exige assinatura válida contra chave pública recebida fora dos dois bancos, `epoch >= minimumEpoch` também informado externamente, e que o conjunto **exato** de checkpoints no PostgreSQL independente corresponda às alegações assinadas. Antes disso, chama o guard F1-71, que compara checkpoint e base principal.
4. Toda anomalia (assinatura inválida, role de empresa errada, ausência de checkpoint, restore, versão anterior ao mínimo, RLS/SQL negado ou indisponibilidade) retorna `matched_signed_fixture=false`, `read_preview_eligible=false` e **sempre** `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false`, `billing_enabled=false`.

### Teste adversarial com backup real

`tests/ui/test-f73-signed-external-anchor-negative.sh` é executado depois da F1-72 no CI:

- Cria um envelope válido de **epoch 1** para o estado de base conhecido e salva `pg_dump --data-only` real dos dois bancos.
- Confirma nova reserva fictícia e nova substituição no primário, grava os novos checkpoints no PostgreSQL independente, assina a lista atualizada como **epoch 2** e preserva fora de ambos os bancos o envelope novo, a chave pública, um arquivo com mínimo `2` e um marcador de eventos fictícios.
- Antes do restore, aceita a assinatura do epoch 2 e recusa replay de um envelope genuíno do epoch 1 com o mínimo externo em 2. Também recusa adulteração de assinatura, chave pública trocada, papel B ou superuser e uso em produção.
- Restaura **ambos os bancos** aos backups antigos. O guard F1-71, isoladamente, passa a achar os bancos consistentes; o F1-73, usando o envelope do epoch 2 que sobreviveu, precisa **recusar** porque a lista assinada de fatos novos não coincide com o banco restaurado.
- Repete o teste com um envelope antigo de assinatura válida mas mínimo externo em 2: recusa por `signed_epoch_below_external_minimum`.
- Por último, reduz artificialmente o arquivo externo de mínimo para 1 e apresenta o envelope antigo: agora o guard assinado **aceita o estado obsoleto**. Essa é a **vulnerabilidade residual esperada**, que comprova que um arquivo local editável não é uma raiz monotônica confiável, mesmo com Ed25519.

## Limitações que continuam bloqueando produção

**Assinatura válida não é prova de atualidade sem raiz de confiança e contador monotônico protegido.** Os arquivos do runner são mutáveis e não possuem custódia fora do domínio de falha; alguém que controle a chave pública, a chave de assinatura, o mínimo externo ou todos os backups pode falsificar ou retroceder o estado. O protocolo também não soluciona a janela entre commit principal e checkpoint independente, a atomicidade entre dois bancos, fraude de administrador, KMS/mTLS, atestação externa, idempotência efetiva com Meta/Google, reconciliação de ações já executadas, consentimento, contrato ou IAM real do Portal.

O esquema final de autoridade de recuperação exigirá avaliação de arquitetura, DBA/AppSec, modelo de ameaças, provedor confiável de monotonicidade, testes de restauração adversarial, persistência com resistência a rollback e trilha de auditoria LGPD.

**NO-GO:** P0/HT-R01/SEC-R03/T3, segurança de recuperação, identidade Portal, autorização humana e AppSec/DBA continuam pendentes. Sem main, merge, deploy, VPS, Google Ads/Meta, CRM, n8n, PSP, clientes, anúncios, gastos ou cobranças.