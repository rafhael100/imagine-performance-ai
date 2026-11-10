# FAST-PERF F1-63 — Integridade local do pacote offline (sem assinatura)

**Data:** 09/11/2026. PR draft filho do F1-62 #149. Somente pacote de demonstração PHP CLI, sem empresa/conta real, sem autorização ou integração de anúncios.

## Objetivo

O F1-62 reúne seis arquivos de demonstração e comprova que duas gerações produzem bytes idênticos. A F1-63 acrescenta um **registro de integridade local** para reconhecer alterações posteriores no pacote, omissões e arquivos inesperados antes de apresentá-lo.

## Arquivos novos

- `contracts/demo/f63-build-integrity-showcase.php`: exige `APP_ENV=testing`, PHP CLI, as flags F1-62 e `PERF_F63_INTEGRITY_LAB=1`; invoca o builder F1-62 em pasta nova e acrescenta `integrity.json`. Registra tamanho em bytes e SHA-256 dos seis arquivos F1-62 mais uma soma agregada calculada sobre uma lista canônica de nomes/tamanho/hash.
- `contracts/demo/f63-verify-integrity-showcase.php`: verificação estritamente de leitura; exige exatamente sete arquivos esperados, recusa diretório extra, arquivo ausente, link simbólico, tamanho inválido, SHA divergente e agregação divergente. Revalida o manifesto F1-62 com flags NO-GO, JSON de conta fictícia, dois painéis HTML com CSP sem conexões/URLs remotas, consistência de tenant e total de campanhas, CSV com BOM e aviso sem faturamento.
- `tests/ui/test-f63-integrity-adversarial.sh`: gera duas pastas para comparação, testa não sobrescrita, negação sob `APP_ENV=production` e feature flag desligada, e simula adulterações de HTML, arquivo novo, ausência de arquivo, link simbólico, manifesto inválido e troca de `billing_enabled`. **Dois testes mais fortes** recalculam deliberadamente os hashes após alterar o manifesto comercial ou inserir script externo, verificando que o controle de política recusa ambos mesmo quando os checksums batem.
- Workflow F1-63 executa as regressões F1-52–F1-62 e só então executa os ataques esperados-negativos, sem criar um endpoint ou acessar a rede.

## Limite de segurança fundamental

O SHA-256 aqui é **sem chave e sem assinatura**. Detecta corrupção e alterações não acompanhadas de recomputação, mas um agente que controla os arquivos e o próprio verificador pode substituir os dados, recalcular os hashes e modificar a política de validação. Não é artefato assinado, prova de autoria, atestação de CI confiável, cadeia de suprimentos protegida nem autorização para homologação. Mesmo uma árvore de hashes correta não atesta autenticidade de origem. Os dois testes de hash recalculado demonstram somente que algumas alterações proibidas continuam barradas pela lógica vigente do verificador.

Também não há SSO Portal, dados persistidos, contas Meta/Google, consentimento/OAuth, publicidade, pagamento, integração real com CRM, revisão de compliance, detecção de restauração/antirollback nem identidade real de operador.

**NO-GO preservado:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, contrato de produto e autorização humana ainda abertos. Sem `main`, merge, deploy, VPS, usuários reais, Google/Meta, CRM, n8n, PSP, compras ou gastos.