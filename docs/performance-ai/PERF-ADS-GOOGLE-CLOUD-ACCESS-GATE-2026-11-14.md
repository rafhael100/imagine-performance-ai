# Performance AI — contrato de acesso de leitura Google/Meta (simulação)

## Mudança oficial no Google Ads API em 2026

O **developer token do Google Ads foi descontinuado em 9 de setembro de 2026**. Novas integrações usam o acesso à API administrado pelo projeto **Google Cloud**, com credenciais OAuth do mesmo projeto. Um token antigo pode ser ignorado pelos servidores. Não pedir um novo developer token como requisito de ativação.

Fonte oficial (publicada em novembro/2026): https://developers.google.com/google-ads/api/docs/api-policy/developer-token

O escopo OAuth do Google Ads continua `https://www.googleapis.com/auth/adwords`, portanto o escopo por si **não impõe read-only**. Restringir operações pelo cliente e pelo serviço autorizado, com deny de `mutate`, HTTP e auditoria. O Meta utiliza `ads_read` para esta trilha, não `ads_management`.

## O que a classe valida

`SyntheticAdsAccessGate` valida exclusivamente em **CI offline** a correspondência da empresa, competência, conta, consentimento sintético válido/não revogado, projeto Google Cloud (ou App Meta), escopo OAuth previsto, timezone e BRL. Compara **integralmente** o plano com a consulta allowlisted que `SyntheticProviderSpendAdapter` gera; qualquer host/método/path/query/campo adicional é negado. A saída nunca contém token, nem prova de propriedade ou OAuth, nem capacidade de enviar HTTP, modificar anúncios ou cobrar.

São proibidos de forma explícita: developer token como campo de contrato, Meta ads_management, URL/host do cliente, conta estrangeira, autorização expirada/revogada, fonte/identidade que se autodeclara verificada e `APP_ENV=production`.

## Gate operacional não atendido

A identidade, consentimento, conta, timezone e credenciais ainda são **fixtures controladas pelo teste**. Não há login do Portal, autorização real Google/Meta, IAM, vault, OAuth consentido, transporte seguro, coleta autenticada de relatórios ou cobrança. Uma futura integração deverá gerar a vinculação do lado do servidor por autenticação e guardar as credenciais em cofre; **não aceitar este objeto diretamente do navegador**. Níveis de acesso reais do Google Cloud devem ser conferidos no projeto habilitado, não confiados em strings de configuração.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-ADS-WRITE / NO-CHARGE.**
