# FAST-PERF F1-46 — Relógio de PostgreSQL na validade de autorizações assinadas

**Data de referência:** 09/11/2026. Branch filha do F1-45 PR #112, **apenas GitHub Actions com duas bases PostgreSQL descartáveis**, sem integração ou operação externa.

## Lacuna identificada no F1-45

O mecanismo de assinatura e consumo de nonce do F1-45 aceitava o horário de emissão/consumo informado pelo cliente PHP. Mesmo com assinatura válida, um chamador que controlasse o parâmetro `now` poderia apresentar um horário anterior para fazer parecer válido um token expirado, ou futuro para antecipar uma emissão. A própria fonte de tempo é parte da segurança de uma autorização efêmera.

## Implementação de laboratório

- A função `perf_f46_issuer.mint_server` executa no PostgreSQL externo e passa exclusivamente `floor(extract(epoch from clock_timestamp()))` para o emissor SQL F1-45; ignora o horário alegado pelo chamador.
- `perf_f46_admission.consume_server` executa no PostgreSQL Portal fictício e encaminha somente o horário obtido do relógio desse banco ao consumidor F1-45. O prazo de teste continua em 30 segundos; as duas instâncias precisam ter relógios suficientemente sincronizados.
- O workflow executa o F1-45 e suas regressões primeiro, depois **revoga as permissões SQL das roles F1-45**, cria `perf_f46_issuer` e `perf_f46_consumer` e concede EXECUTE apenas às funções novas. Acesso às tabelas privadas/chaves, F38 e F43 permanece vedado às roles novas.
- A suíte F1-46 apresenta horários falsos nos dois extremos (1.000.000.000 e 2.100.000.000), verifica que os campos assinados refletem o relógio SQL, e mostra que uma autorização **intencionalmente retrodatada por administrador do CI** não pode ser recuperada com um horário falso. Permite também provar que uma autorização com data futura não é ativada prematuramente.
- Mantém testes de nonce único, ACK restrito a geração, época de recuperação, quarentena externa/local, privilégios revogados, `APP_ENV=production` fechado, `dispatch_enabled=false` e `pending_synthetic`.

## Limitações críticas

**Não significa relógio confiável de produção nem revogação imediata.** Ambos os PostgreSQL rodam no mesmo host do CI; em servidores reais haverá diferenças de relógio, NTP, indisponibilidade e manipulação de host, exigindo monitoramento, leeway controlado e fail-closed. **Token emitido antes da quarentena externa ainda pode passar no banco local até expirar**, caso a mudança não tenha sido sincronizada; isso continua como teste negativo intencional.

Continuam sem solução: identidade real do trabalhador, contratos e papéis Portal/CRM, prova de posse, KMS/HSM, mTLS, assinatura assimétrica de emissor, auditoria de negócio, contador antirollback após restauração conjunta, transações entre bancos, idempotência nos provedores, relógio atestado, proteção contra pré-consumo e autorização humana real. As chaves HMAC de teste são simétricas e compartilhadas; SQL com privilégio administrativo poderia assinar. O resultado não é homologação, e o laboratório não deve ser conectado a Laravel rotas/workers, n8n ou Google/Meta.

**Gates P0, HT-R01, SEC-R03/T3, Issue #102 e AppSec/DBA seguem abertos.** Nenhum merge, `main`, fast-track, VPS, contas, clientes, gastos ou deploy.