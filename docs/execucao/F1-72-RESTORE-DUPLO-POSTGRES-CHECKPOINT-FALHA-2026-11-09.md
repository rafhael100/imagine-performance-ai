# FAST-PERF F1-72 — Teste negativo de restauração simultânea de dois PostgreSQL

**Data:** 09/11/2026. Continuação F1-71 PR #168. Laboratório de segurança em duas instâncias PostgreSQL 16 descartáveis do GitHub Actions. Nenhuma conexão com dados pessoais, anunciantes, infra real ou mídia paga.

## Vulnerabilidade delimitada

A F1-71 detectou corretamente a restauração de **apenas** o banco principal quando a base independente manteve registros de checkpoints. A F1-72 demonstra o limite dessa abordagem: se um backup antigo for restaurado **também no banco independente**, os dois serviços podem apresentar um estado **mutuamente consistente, porém historicamente obsoleto**.

Comparar dois bancos não prova que a comparação representa a informação mais recente conhecida no mundo externo. Essa vulnerabilidade importa quando operações irreversíveis — anúncios, despesas, campanhas ou cobranças — podem ter sido executadas **depois** do ponto de backup. Em F1-72 não existe qualquer efeito real externo; apenas um arquivo de texto local comprova, dentro do laboratório, que dois commits posteriores haviam sido feitos antes do restore simultâneo.

## Passos do teste real

1. Após regressões F1-52–F1-71, o PostgreSQL primário já está na versão antiga da F1-70 e o secundário ainda possui dois checkpoints F1-71, demonstrando divergência. O teste prepara uma nova **linha de base fictícia** em ambos: a campanha `cam-f70-late` existe e está gerenciada no primário, e um checkpoint independente aponta para essa reserva.
2. Gera **dois backups reais** `pg_dump --data-only --column-inserts`, um das quatro tabelas de negócio do primário, outro da tabela de checkpoints independente.
3. O administrador de CI reabilita temporariamente as funções SQL legadas de laboratório (que F1-71 havia revogado), exclusivamente para simular operações adicionais. O papel A registra uma campanha nova `cam-f72-late` e uma troca `req-demo-f72-swap`; somente depois dos commits, o administrador do PostgreSQL independente grava mais dois checkpoints fictícios. Com três registros e os estados atuais, o guard F1-71 retorna `independent_fixture_matches`.
4. Um arquivo texto fora dos dois bancos registra os dois IDs de operações recentes. É **simples evidência de teste**, não uma fonte WORM, autoridade criptográfica, assinatura de provedor ou solução real de auditoria.
5. O teste executa `TRUNCATE` e importa os backups antigos **dos dois bancos**. Agora a campanha recente e o recibo de troca desaparecem de ambos; o primário retorna à capacidade antiga e o banco independente conserva apenas o checkpoint original.
6. O guard F1-71 observa um registro antigo corretamente correspondente ao primário e devolve `independent_fixture_matches` com `consistent_mock_only=true`. **Esse sucesso de consistência é precisamente a falha de detecção que o teste precisa demonstrar**. O arquivo texto ainda contém os dois fatos que não estão mais presentes em nenhum PostgreSQL.
7. Os testes verificam que `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false` e `billing_enabled=false` permanecem bloqueados, que o papel B não acessa dados de A e que as duas funções legadas voltam a ficar sem EXECUTE ao término do laboratório.

## Conclusão de arquitetura

A solução completa precisará de uma **fonte de verdade com monotonicidade verificável resistente a restauração conjunta**, protocolo seguro de confirmação que trate falha entre bancos, identificadores/fencing associados ao fornecedor e reconciliação de efeitos antes de executar novos comandos. Apenas adicionar uma terceira base sem garantir independência do domínio de falha ou um checkpoint assinado/imutável também não resolve automaticamente o problema. Devem existir especificação de recuperação, IAM/autorizações Portal, auditoria LGPD, revisões DBA/AppSec e testes contra restauração obsoleta simultânea.

**Este PR demonstra uma falha da F1-71, não a corrige.** Todos os testes continuam sintéticos e sem provedor. `release_state=blocked` é obrigatório. Permanecem NO-GO P0/HT-R01/SEC-R03/T3, antirollback, S2S, login real do Portal, autorização de anunciante e revisão AppSec/DBA. Nenhum merge, alteração em `main`, deploy, VPS, Google/Meta, CRM, n8n, PSP, clientes, cobranças ou gastos.