# Performance AI — trilha de revisão financeira assinada no PostgreSQL 18

**Situação: exclusivamente CI efêmera, não operacional.**

O fluxo de referência valida um envelope assinado **emitido pela classe real do Portal** (PR #767 em commit pinado), verifica HMAC e nonce, registra rascunho não faturável em PostgreSQL **18** e apresenta uma prévia HTML read-only. A prévia confere hashes da cotação e das evidências sintéticas, valores monetários, RLS por contexto de laboratório e flags de bloqueio antes de renderizar.

A implementação usa `labs/finance-pg` para nonce e rascunhos assinados como **proposta de ledger de referência**. A estrutura `laboratory/postgresql` permanece isolada como regressão comparativa de cálculos e UI — **não ligar dois ledgers em produção**.

Este teste não valida IAM, assinatura de transporte, provenance Google/Meta, autenticação de tenant por sessão, chave real, backup da VPS nem pagamento Asaas. `app.tenant_id` é uma variável de sessão controlável por quem tiver acesso ao banco: exige camada de confiança na homologação. Um booleano HMAC no array interno não é certificado de origem de request externo; nenhuma rota pública foi criada.

**Sem merge, sem deploy, sem cobranças e sem alterações de anúncios**.
