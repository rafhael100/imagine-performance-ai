# F1-04 — Campanhas sob gestão (recorte sintético)

Data-base: 08/11/2026. [Issue #20](https://github.com/rafhael100/imagine-performance-ai/issues/20).

## Escopo e autoridade
- Domínio PHP **em memória**, com dados inteiramente fictícios e nenhum acesso HTTP, banco, Google Ads, Meta, PSP, Portal ou CRM.
- Google Ads e Meta são dois canais comerciais; Instagram e Facebook compartilham o canal `meta`.
- Apenas campanhas formalmente sob gestão contam; campanhas `active_demo` **e** `paused_demo` ocupam capacidade, conforme RES01-D05. Pausar não libera espaço.
- `release` e `replace` exigem aprovação humana **simulada** explícita; nunca alteram conta de anúncios ou realizam operações reais.
- A troca ordinária `replace` arquiva o ID antigo e cadastra o novo **atomicamente em memória**, sem contador de substituições ou tarifa e sem sobreposição simultânea. O fluxo de coexistência real continua pendente.
- Os limites 1 e 2 são valores **de teste**, não planos ou franquias homologadas. Capacidade `null` é **desconhecida** e impede novos registros; limite excedido exige revisão, sem cobrança automática.
- IDs de campanhas são opacos e não reutilizados entre tenants; operações de tenant B sobre objeto A recebem resposta genérica, sem leitura de dados de A.
- `auditFor` fornece histórico demonstrativo **não durável**, não substitui auditoria de produção.

## Testes e infraestrutura
- `php -l src/Domain/Performance/SyntheticCampaignRegistry.php`
- `php -l tests/Unit/SyntheticCampaignRegistryStandalone.php`
- `php tests/Unit/SyntheticCampaignRegistryStandalone.php` (37 verificações)
- CI candidate em `.github/workflows/perf-f1-04-ci.yml`, apenas branch da feature e repositório **público**, runner público padrão. Sem workflow com permissão de escrita, sem fornecedor pago.
- A etapa de validação não deve declarar `PASS` até haver saída real local ou CI verde do SHA do PR.

## Dependências e limites
- Branch filha de `dev/perf-f1-03-synthetic-portal-contract-2026-11-08` (PR #18 ainda draft), PR F1-04 apontando exclusivamente para essa branch.
- Não fazer merge automático; não alterar `main`, VPS, Portal, CRM, nem branch de integração.
- Integração entre entidades simuladas de F1-03 e campanha F1-04 ainda não existe; isso requer recorte explícito e contexto autenticado no futuro.
- Continua vetado qualquer efeito externo: criação de campanha, publicação, modificação de verba, API Google/Meta, cobranças ou leads.
- 91 P0, 15 HT-R01 e gates de segurança, homologação comercial, LGPD, autenticação e produção continuam abertos.
