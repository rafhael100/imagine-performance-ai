# FAST-PERF F1-180 — entrega F100 pós-recibo de assinatura, recusando revogação e mudança de campanha

**Base F179/PR #287. CI PostgreSQL16 descartável. PR draft, P0/HT-R01/SEC-R03/T3 NO-GO.**

## Janela identificada

O teste F179 confrontava uma projeção SQL F100 obtida **antes** de consumir o nonce F124 com o resultado assinado F123/F124, e depois exportava a cópia inicial para renderizar HTML F63. Mesmo com dois resultados iguais naquele instante, a sessão de leitura da empresa ou o estado de campanha poderiam mudar **após o recibo F124 e antes da publicação do arquivo**. Uma assinatura válida de laboratório e um registro nonce não tornam os dados imutáveis nem conferem autorização de produção.

## Correção reutilizando componentes existentes

`contracts/lab/php/SyntheticPostReceiptF100Delivery.php` é um **único ponto de entrega experimental** que recebe o *bridge original* F123/F124, o leitor real F100, a conexão SQL A e o envelope F29 original do Portal. Não inventa assinatura, identificador nem nova representação de contas.

A ordem é: (1) ambiente CI/PHP CLI, relógio real e destino local inédito/sem symlink; (2) F29/F123 valida assinatura, escopo sintético, sessão e principal PostgreSQL; (3) F124 **consome** nonce com a role SQL `INSERT-only`; (4) somente após consumo, o leitor F100 relê **as três views SQL** e compara estritamente contas, campanhas, revisões e contagens com o relatório autorizado antes do recibo; (5) se qualquer condição mudou ou a sessão foi revogada, falha fechada e não cria arquivo; (6) se tudo confere, grava JSON seguro com `JSON_HEX_*` em arquivo temporário exclusivo, `fsync` e publicação por **hard link exclusivo que não substitui arquivo existente** na pasta privada `RUNNER_TEMP`.

**Teste adversarial real:** quatro envelopes com novos nonces são produzidos pelo **emissor PHP histórico original do Portal** na mesma execução. Primeiro, emissão e persistência positivas, verificando SHA256 e paridade F100/F62. Segundo, um hook presente somente no laboratório revoga `perf_f91_reader_a` **após** consumo F124: entrega negada, nenhum JSON, nonce permanece gasto mesmo após restaurar a sessão. Terceiro, o mesmo ocorre se `lifecycle_state` de uma campanha SQL A muda entre o recibo e a entrega: o arquivo é negado, o estado retorna ao anterior mas a assinatura não pode ser reutilizada. Quarto, destino já existente, login B e envelope adulterado recusam a operação **antes de consumir** o nonce A; o envelope válido pode ser usado uma única vez. Por fim, o HTML F63 renderizado do novo DTO é comparado byte a byte com o HTML F179.

## Limitações e segurança operacional

A função de simular mutações é injetável apenas com `PERF_F180_RACE_INJECTION_LAB=1`; não há rota Laravel nem integração Ads. A prova de nonce continua armazenada **no mesmo PostgreSQL** e é vulnerável a restauração/DBA privilegiado. O hard link oferece exclusividade e evita substituição acidental **nesse runner Linux**, mas **não** cria armazenamento WORM ou defesa contra processos com controle da pasta. O JSON permanece não assinado por uma autoridade externa. **A revogação posterior à última releitura F100 ainda é possível**, e o resultado não se torna autorização para cliente, sessão real, consentimento OAuth, mídia paga ou cobrança. O código não deve ser usado em produção.

**Sem merge dev/main, deploy VPS, provedores reais, clientes, anúncios, faturamento ou despesas.**
