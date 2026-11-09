# FAST-PERF F1-44 — Confrontar épocas de recuperação entre duas autoridades SQL

**Escopo:** laboratório sintético CI, filho de F1-43 PR #108. Dois PostgreSQL descartáveis, sem deploy, Ads/Meta/CRM/PSP ou n8n.

## Falha originadora

O F1-43 introduziu quarentena aplicada diretamente na transação SQL local, impedindo que a role de admissão `perf_f43_sql_consumer` chame o F1-38 antigo. Entretanto, ao final do CI, o estado local era `released_mock:5`, enquanto o estado externo F1-41 ainda era `released_mock:4`. A função SQL local não tem vínculo confiável com a fonte externa de recuperação.

## Avanço laboratorial

O novo adaptador PHP `SyntheticCrossAuthorityAdmission` consulta a autoridade externa F1-41 com papel somente de leitura e o estado SQL local F1-43 antes e depois de cada comando de lease fictício. O adaptador:

- Recusa `claim/ack` quando qualquer uma das fontes está em quarentena ou as épocas não são idênticas; se a autoridade externa está indisponível, falha fechada.
- Conserva o token com `outbox_id`, geração do lease e época de recuperação; o ACK exige época vigente e geração ainda válida.
- Testa divergência inicial, ajuste de época feito pelo administrador de CI, nova quarentena **apenas externa**, e nova liberação fictícia depois de sincronizar ambos os bancos para época 6.
- Mantém o SQL F1-43 como único acesso da role de laboratório ao claim/ACK, e proíbe rotas ou workers reais.
- Documenta um teste esperado-negativo: quando a autoridade externa volta a `quarantined:6`, o wrapper recusa, mas alguém portando a role F1-43 consegue continuar chamando o SQL **diretamente**, que está localmente em `released_mock:5`. A divergência está diagnosticada, não resolvida por enforcement distribuído.

## Barreiras para produção

Não há sincronização atômica, autenticação do evento de restauração, fonte institucional não restaurável, autorização real, mTLS/PoP, gestão de segredos/KMS ou detecção automática de restore. Os comandos de alinhamento são simples UPDATE feitos por superusuário fictício no CI, **não são aprovações humanas ou procedimentos seguros de recuperação**.

As duas fontes podem ser restauradas para versões antigas; checagens antes/depois em PHP não eliminam TOCTOU, falhas de rede após commit e bypass por credencial da função SQL local. Uma versão operacional exigirá autoridade única de decisão ou desenho de protocolo distribuído com fencing monotônico verificável, quarentena global obrigatória, credenciais por serviço e auditoria de segurança independente.

P0, HT-R01, SEC-R03/T3, Issue #102 e AppSec/DBA permanecem abertos. Nenhum `dispatch_enabled=true`, serviço externo, mídia paga, clientes, faturamento, VPS, merge, main, fast-track ou gastos.