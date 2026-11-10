# FAST-PERF F1-126 — preflight da testemunha independente antes de consumir nonce

Data: 10/11/2026. Continuação F125 com duas instâncias PostgreSQL16 descartáveis no CI e declaração Ed25519 DEMO do Portal pinada.

## Correção implementada

O método `SyntheticExternalSignedNonceWitness::preflight()` verifica **sem ler ou gravar recibos** a sessão SQL na segunda base: usuário `perf_f125_witness_writer`, banco `perf_f125_witness_ci`, ausência de transação externa, sessão não somente leitura e permissões catalogadas **INSERT=true e SELECT=false** para a tabela de witness. Recusa banco/role errados, SELECT excessivo, falta de INSERT e falha de conexão.

O `SyntheticExternallyWitnessedPortalPreview` consulta esse estado **antes** de chamar F124 para consumir nonce local. Em caso de preflight negativo, devolve `independent_witness_not_ready` sem ler ou devolver dados e **sem consumir a assinatura** no banco principal. O comportamento é ativado exclusivamente pelo feature flag de laboratório `PERF_F126_WITNESS_PREFLIGHT_LAB=1`; o teste F125 anterior permanece reproduzível sem essa flag.

## Evidências

[GitHub Actions F126 #42889699983 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42889699983): **129 verificações F126** e regressões F125 (**202**), F124 (**181**), F123 (**368**), F22–F122, Portal Laravel/HTML/CSV, Composer audit.

Casos reais PostgreSQL:
- preflight correto consulta catálogo ACL/role mas não insere nonce nem acessa os dados da witness;
- `REVOKE INSERT` externo é detectado **antes** de queimar assinatura local; após `GRANT INSERT`, o **mesmo token** pode ser consumido normalmente uma vez pelas duas bases;
- transação externa `READ ONLY` bloqueia o consumo e não é encerrada pela biblioteca; após rollback, o mesmo token funciona;
- reutilização do nonce já consumido é negada;
- **janela de corrida confirmada:** após preflight aprovado, outro administrador de teste remove INSERT externo enquanto a leitura Laravel F100 ocorre; o bridge retorna negação com dados vazios, mas o nonce F124 local já foi consumido. Não se apagou nem compensou o recibo artificialmente;
- flag de testes desativada e `APP_ENV=production` impedem o uso.

## Limite técnico ainda aberto

A F126 é um **preflight de disponibilidade**, não uma transação distribuída atômica nem acordo assinado de duas fases. `TOCTOU` entre consulta de privilégio e `INSERT` externo ainda existe e foi comprovado no teste adversarial. Uma recuperação segura exige protocolo projetado e auditado de consumo, estados de pendência, reparação idempotente e testemunha externa com epochs protegidos de restore (não apenas acrescentar mais roles e mocks).

Para uso comercial faltam ainda Portal IAM/MFA/S2S real, vínculo independente de empresa/serviço e sessão, OAuth e consentimentos Google Ads/Meta, orçamento aprovado por pessoa competente, LGPD, homologação VPS, segurança DBA/AppSec independente e gates P0/HT-R01/SEC-R03/T3.

**DRAFT/NO-GO; main, produção, VPS, clientes e mídia paga sem alterações.**
