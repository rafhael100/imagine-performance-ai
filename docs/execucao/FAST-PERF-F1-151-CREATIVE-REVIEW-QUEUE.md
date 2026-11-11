# FAST-PERF F1-151 — fila funcional de revisões criativas (somente demonstração)

**Base:** F150 / PR #258. **DRAFT / NO-GO**.

## Funcionalidade entregue

A classe `F151SyntheticCreativeReviewQueue` adiciona ao núcleo de domínio do Performance uma fila de revisões criativas de campanhas fictícias. Ela **consulta a classe existente F52** para confirmar que a campanha pertence ao escopo sob gestão da empresa e ao canal declarado (Google Ads ou Meta). Uma string enviada pelo chamador jamais vira autorização por si só.

O ciclo implementado tem os estados `pending_demo`, `changes_requested_demo`, `approved_mock_only` e `rejected_mock_only`. Exige uma `expectedVersion` para cada decisão, impedindo reaplicação acidental e corridas óbvias no mesmo objeto em memória. Permite ressubmeter somente após pedido de ajustes; recusa após retirada formal da campanha do escopo. Registra uma trilha auditável **fictícia** com transições e versão e fornece uma projeção separada por empresa, com resumo do fluxo e sem dados operacionais.

O gerador `contracts/demo/f55-render-offline-preview.php` utiliza a fila quando a flag `PERF_F151_APPROVAL_LAB=1` estiver presente. A aba existente “Aprovações” passa a receber duas revisões fictícias verdadeiramente derivadas do domínio F151, em vez de manter somente descrições estáticas. O HTML continua sem APIs, cookies, login, rede, banco, cobrança ou publicação. O CSV F56 mantém suas limitações e canal comercial separado.

Testes cobrem submissões, duplicidade, tentativa de acesso entre empresas, canal falsificado, título HTML inválido, conflito de versão, pedido de ajustes, ressubmissão, decisão mock final, reversão indevida, rejeição de campanha retirada, auditoria por empresa e a renderização completa da prévia HTML com todos os flags NO-GO preservados.

**Importante:** `reviewer-demo-...` é uma string arbitrária de teste e **não é identidade real Portal IAM/MFA**. O status `approved_mock_only` não significa aprovação humana ou licença para veicular anúncios. A fila é in-memory, não possui concorrência transacional real, persistência SQL, aplicação cliente ou API autenticada. Cada projeção inclui `human_approval_verified=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false` e `ready_for_production=false`.

Próximas funcionalidades: relatório offline de revisões com exportação segura, estados e contagens agregadas; posteriormente contratos versionados para API read-only com identidade externa confiável. **Gates P0/HT-R01/SEC-R03/T3 = NO-GO.** Sem main merge, VPS, clientes, Ad APIs, cobrança ou gastos.
