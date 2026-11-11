# FAST-PERF F1-181 — integridade entre arquivo F180 e renderizador F63, com visualização GitHub Actions

**Base F180/PR #288; CI PostgreSQL16 descartável, DRAFT/NO-GO.**

F180 protegeu a consulta do PostgreSQL **após** a assinatura F29 e o recibo de nonce F124. Restava uma etapa: transferir o arquivo JSON F100 à ferramenta HTML F63 do Portal. Sem verificação de integridade, outro processo do runner poderia modificar o JSON (até com um DTO F100 de estrutura válida) entre a entrega e a renderização, exibindo dados diferentes dos materializados após assinatura.

A F181 usa `SyntheticF181BoundPortalHtml`, um invólucro **somente de laboratório**, que recebe o resultado da classe F180 **na memória do mesmo processo** (incluindo SHA256 do arquivo e `receipt_consumed_demo=true`). Confere o hash do JSON original, revalida depois de uma janela de concorrência, copia apenas os bytes conferidos a um novo arquivo temporário exclusivo, chama **o renderizador PHP original F63 do Portal pinado na candidata PR #356**, valida o HTML gerado (CSP, aviso de DEMO, ausência de recursos ativos/links/formulários), reconfere ambos os arquivos JSON após a renderização e cria o HTML final por publicação exclusiva `fsync` + `link`. Não há rotas web nem renderizador paralelo novo.

O CI reutiliza toda a sequência SQL+F29/F124+F100+F62/F63+F180, emite um **novo envelope Ed25519 pelo código original do Portal**, consome o nonce uma vez, gera o F100 e verifica a renderização. Os testes:
- comparam o HTML final byte a byte com a prévia anterior F180;
- alteram uma string de campanha no arquivo F100 entre duas verificações de hash, mantendo um JSON aparentemente válido: **nenhum HTML é produzido**;
- recusam um JSON já alterado, checksum incorreto, proof marcada sem nonce, flag Ads indevida, destino HTML existente, ambiente production ou F63 desabilitado;
- confirmam que múltiplas cópias offline de leitura no mesmo processo não consomem novos nonces, sem alegar proteção de uso único da página HTML.

**Visualização sem deploy:** quando a pipeline passa, apenas `f181-bound-preview.html` (dados inteiramente fictícios) é enviado como **artefato temporário do GitHub Actions por um dia**. Pode ser aberto pelo revisor na própria execução do workflow. Não se publica o site, a VPS não é tocada, e não são incluídos envelopes/segredos/chaves no artefato.

**Limitações críticas:** o SHA256 é um vínculo de integridade **somente dentro de um processo de CI**; não há assinatura externa do arquivo, validação autônoma pelo navegador, WORM, nem proteção contra um atacante que controle o código/ambiente de execução. O nonce F124 pode sofrer rollback do próprio banco. O issuer original é histórico e a autoridade de sessão é fictícia, sem MFA/S2S/contrato real. Um update/restore após a última checagem ainda pode tornar o snapshot desatualizado; por isso não é autorização operativa.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Sem merge dev/main, deploy, VPS, anúncios, cobrança, contas de clientes ou novas despesas.
