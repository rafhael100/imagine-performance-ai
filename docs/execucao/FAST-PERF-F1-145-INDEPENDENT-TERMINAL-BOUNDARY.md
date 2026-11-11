# FAST-PERF F1-145 — fronteira terminal independente (somente simulação CI)

**Base:** F144, PR #252. **Status:** DRAFT / NO-GO. Não faz deploy, não chama Google/Meta Ads, não altera campanhas, não cobra, não move dinheiro e nunca retorna `action_authorized=true`.

## Problema e decisão de engenharia

F144 provou que o primário, witness, terceira âncora e quarto oráculo são restauráveis juntos no mesmo runner. Uma credencial com permissão direta de SQL+emissão usa um MAC HMAC legítimo capturado e registra B novamente; o diário externo F141 nunca autorizou essa segunda B. Em vez de criar uma quinta base sob o mesmo operador, F145 separa **o portão terminal de ação** da própria aceitação de SQL.

## Entrega implementada

- `F145IndependentDispatchBoundary` é uma classe **PHP de laboratório**, distinta de `F143LiveOracleRegistration`. Seu `decide` recebe o resultado de registro SQL somente como evidência complementar — nenhum retorno de banco tem autoridade para executar algo.
- A decisão final exige **assinatura Ed25519 emitida por uma identidade sintética distinta**, vinculada estritamente a `operation_id / tenant / fingerprint / epoch / issued_at`. Assinatura alterada, outro tenant ou mais de 30s são negados.
- Há um diário independente F141 dedicado **só a simulações de decisão terminal** (não o diário de registro). Usa chave de assinatura própria e reserva de operação/nonce única sob `flock`.
- Um arquivo high-watermark separado conserva `events/epoch/head`. O gateway compara esse checkpoint com o diário antes de aceitar qualquer operação; truncamento do diário até um prefixo antigo ainda assinado, perda/corrupção do checkpoint ou falha de escrita fazem a decisão falhar fechada. A gravação `fsync` é feita no laboratório. Estado inconsistente entre journal e checkpoint exige **reconciliação manual**; não existe retry cego.
- O workflow de quatro PostgreSQL16 em GitHub Actions executa a F144 end-to-end: snapshots antigos de **todos os quatro** SQL bancos, B admitida, restauração quádrupla e replay B por SQL privilegiado em laboratório. A F145 deve recusar uma **segunda simulação de dispatch** de B mesmo após SQL relatar `registered_lab`, porque o primeiro evento B já consta no seu diário separado.
- Regressões negativas: issuer Ed25519 falsificado ou expirado, troca de tenant, resultado SQL negado, replay de A/B, truncamento até prefixo válido, checkpoint ausente. Todos os retornos contêm `action_authorized=false`; `mock_dispatch_recorded=true` significa **somente arquivo local escrito**, jamais comando externo.

## Limites e NO-GO

**Isto demonstra separação lógica de domínio de autorização, NÃO independência real de infraestrutura ou segurança.** O emissor e o terminal mock estão no mesmo runner CI, com arquivos alteráveis pelo mesmo usuário. Um operador que controla as duas chaves/arquivos pode restaurar ou editar *diário e checkpoint juntos*, o que contorna a proteção. Arquivos locais não são WORM, armazenamento offsite ou autoridade resistente a rollback. O emissor de laboratório também não verifica um consentimento real do anunciante, orçamento aprovado ou identidade de workload; uma assinatura sintética emitida para operação ainda não consumida não representa permissão empresarial.

**Não existe caminho de rede real de mídia pago encapsulado exclusivamente nesse boundary**: o bloqueio é uma especificação executável sobre um mock, não uma imposição já integrada em serviços Google/Meta ou no Portal Imagine. Administradores/superusuários dos bancos e o host continuam podendo ignorá-lo. Nenhum S2S/mTLS/KMS/HSM, política de privilégios operacional, fiscalização de Ads, revisão AppSec/DBA ou transação distribuída atômica foi homologado.

Para produção, o próximo gate exige que **toda** execução externa entre exclusivamente por um serviço independente com identidade verificável (inclusive credenciais de Ads inacessíveis aos sistemas SQL), autoridade monotônica não restaurável junto à aplicação, evidência persistente WORM/offsite e validação de políticas de anúncio/human-in-the-loop. Crash/partição e perda de ACK necessitam quarentena e reconciliação robustas. P0/HT-R01/SEC-R03/T3 seguem NO-GO.

**Nenhum merge na main, alterações VPS, campanhas, cobrança ou despesas.**
