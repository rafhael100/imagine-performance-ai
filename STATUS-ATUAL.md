# Status técnico adicional — 09/11/2026, FAST-PERF F1-31–33 (PRs draft)

> **Apenas desenvolvimento de laboratório:** este resumo é posterior ao status histórico abaixo, que NÃO deve ser usado isoladamente para inferir prontidão operacional. Branch `dev/perf-f1-33-portal-anchor-replay-2026-11-09` e ancestrais, **sem merge**, sem VPS/deploy/Ads/CRM/PSP/clientes reais.

- **F1-31 / [PR #83](https://github.com/rafhael100/imagine-performance-ai/pull/83)**: Ed25519 emitido pelo Portal fictício, reconsulta PostgreSQL da fonte e consumo único de nonce em uma segunda instância SQL; [CI SUCCESS #42617243110](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42617243110), **27 verificações F1-31** e 50 de regressão F1-30.
- **F1-32 / [PR #85](https://github.com/rafhael100/imagine-performance-ai/pull/85)**: execução real de pg_dump/restore da tabela de recibos, demonstra que **token já consumido pode ser reutilizado depois de restaurar backup antigo**. Incrementar recovery_epoch na fonte Portal em banco separado bloqueia a assinatura antiga **somente depois** da atualização. [CI SUCCESS esperado-negativo #42617965451](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42617965451), **7+7+7 verificações F1-32**; verde significa risco reproduzido, NÃO correção.
- **F1-33 / [PR #87](https://github.com/rafhael100/imagine-performance-ai/pull/87)**: âncora sintética adicional de nonce no Portal, antes do recibo do Performance. Ao restaurar somente o banco secundário, o caminho com âncora recusa replay, **mas chamar diretamente o verificador antigo sem a âncora continua aceitando replay**; [CI SUCCESS #42618567247](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42618567247), **11+13 verificações F1-33**.
- **Bloqueadores P0**: o Portal e a âncora também são restauráveis; atualização de epoch não é automaticamente monotônica; chaves são efêmeras, a âncora não verifica Ed25519 diretamente, há possibilidade de pré-consumo/DoS e execução por caminho legado, não existe prova real de posse de recursos, identidade S2S mTLS, API operacional, commit distribuído, idempotência de efeito, recuperação nem homologação AppSec/DBA/LGPD. P0, HT-R01 e SEC-R03/T3 permanecem **abertos**.
- **Próxima prioridade técnica**: centralizar enforcement sem bypass de verificadores antigos; projetar marca monotônica de recuperação fora do domínio restaurável, outbox/idempotência de efeitos e revisão independente do protocolo antes de qualquer operação real. O CI não autoriza merge, lançamento ou acesso comercial.

---

# STATUS ATUAL — Imagine Performance AI

## Atualização técnica prioritária — 08/11/2026 (substitui status técnicos antigos abaixo)

**Decisão humana:** Laravel 13 + PostgreSQL independentes adotados para o motor Performance; Portal Imagine continua autoridade comercial/identidade e front-end do cliente; CRM opcional.

**Código em revisão, NÃO implantado:**
- [PR #16 — F1-02 A/B/C integrados](https://github.com/rafhael100/imagine-performance-ai/pull/16): domínio PHP com controles sintéticos; esqueleto Laravel; migrations PostgreSQL de seis tabelas com tenant RLS fail-closed.
- [PR #18 — F1-03, contrato mock Portal](https://github.com/rafhael100/imagine-performance-ai/pull/18): eventos idempotentes, estados de serviço `*_demo`, revisão e conflitos; **sem API, credenciais ou provisionamento real**.
- [Issue #15](https://github.com/rafhael100/imagine-performance-ai/issues/15) e [Issue #17](https://github.com/rafhael100/imagine-performance-ai/issues/17) organizam os recortes.

**Evidência automatizada efetivamente obtida:**
- [GitHub Actions run #42532992463](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42532992463) **SUCCESS**, runner Ubuntu 24.04 público padrão, sem recursos pagos/especiais.
- PHP Unit: 9 testes, 14 asserções, PASS.
- Feature da aplicação isolada: 3 testes executados PASS, 7 testes PostgreSQL intencionalmente pulados no job sem DB.
- PostgreSQL 16 descartável (`perf_test_ci`): migrations aplicadas, RLS exercitada com papel sem privilégios administrativos, 7 testes, 45 asserções, PASS.
- Testes CLI puramente sintéticos: 22 + 26 = **48 PASS**, sem banco.
- Rotas automáticas locais de armazenamento GET/PUT desativadas; Composer audit sem alertas conhecidos.
- `composer.lock` verdadeiro versionado em branch F1-03, commit `b936fbb84eae14daac97e143f1d46b1193719302`; a execução verde mais recente usou `composer install`, não atualização livre de dependências.

**Branches e restrições:** `main` e VPS/produção permanecem intocadas; os PRs documentais #6/#7/#8/#10 e trabalhos anteriores #12/#14 não foram mesclados ou encerrados. PR #18 depende do conteúdo do PR #16; revisar integração na ordem.

**PENDENTE / não inferir homologação:** API autenticada Portal–Performance, vínculo de identidade, MFA/S2S/OAuth, dados reais, autorização e execução Google/Meta, PSP/fiscal, notificações, operação do cliente, segurança operacional, filas/retentativas persistidas, testes de carga, privacidade/retenção LGPD, escolha de configuração VPS e deploy, 91 P0 e 15 HT-R01. Uma suite verde não libera serviços reais.

---

**Data:** 05/11/2026  
**FAST-PERF-01:** **APROVADA** — desenvolvimento controlado e isolado para MVP com dados fictícios.  
**Branch de integração autorizada:** `dev/fast-track-performance-2026-11-05`.  
**`main`: INALTERADA. VPS/produção: INALTERADAS.**  
**Cenário comercial:** 2 clientes potenciais interessados; nenhum contrato, valor negociado ou início efetivo de serviço comprovado no repositório.

## Estado verificável neste marco

- Repositório separado `rafhael100/imagine-performance-ai` foi criado anteriormente para documentação do produto.
- A aprovação FAST-PERF-01 autoriza preparar e programar **somente componentes isolados** com mocks e dados sintéticos, via PRs direcionados à branch `dev/fast-track-performance-2026-11-05`.
- O acervo funcional PERF-01–PERF-12, PERF-R01/R02 e BL-01–BL-08 foi aprovado **como documentação**, não como implementação homologada. **91 P0**, demais P1/P2, decisões e contratos técnicos ainda precisam de análise de acordo com cada etapa.
- **Framework/stack definitivo ainda não homologado.** Primeira tarefa: recomendação técnica clara e preparação de critérios de aceite; não inventar decisão de linguagem.
- Integrações reais Google/Meta, CRM, Portal e cobrança continuam bloqueadas até autorização e homologação específicas.
- Nada está liberado para deploy, acesso de clientes reais ou tráfego pago automatizado. Documentar toda mudança de status com evidência.

## Trilhas de execução

### Produto — MVP isolado

**F1-01 — Preparação e stack:** confirmar limites, propor linguagem/backend/DB, estrutura de testes e critérios de governança. Executar tarefas que não imponham stack final sem aprovação.  
**F1-02 — Multiempresa:** entidades e controles de isolamento, com testes negativos de acesso cruzado.  
**F1-03 — Cadastro e oferta simulados:** empresas, estados, plano ilustrativo sem preço real, canal e capacidades.  
**F1-04 — Gestão de campanhas/grupos sob gestão:** status, pausas, substituições e histórico; mocks para APIs.  
**F1-05 — Aprovações e relatórios:** decisões humanas auditáveis e painel com dados de demonstração.

Critérios mínimos: testes de tenant/segurança, ausência de segredos, sem integrações externas, PRs para branch autorizada, código revisável, documentação atualizada.

### Comercial — dois interessados

Registrar separadamente o briefing de cada cliente; se contratados antes do SaaS, operar somente por **prestação manual/assistida com proposta específica e contrato válido**, equipe capaz, autorizações formais nas contas Google/Meta e verbas custeadas diretamente pelo cliente. Não declarar acesso ao SaaS, IA autônoma ou painel implementado.

## Gates que exigem autorização específica

1. Escolha final de stack e contratos técnicos associados.
2. Credenciais reais, OAuth, API Google/Meta, dados pessoais de clientes e operações em contas reais.
3. Staging/VPS, infraestrutura paga, deploy, `main`, novas licenças ou contratação de fornecedores.
4. Precificação final, gateway, cobrança automática e go-live comercial da plataforma.

**Regra:** um gate aberto permanece aberto; a aprovação FAST-PERF-01 não equivale à sua dispensa.

Referência: `docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md`.


## Autorização complementar: três tarefas paralelas no Codex (05/11/2026)

O responsável aprovou explicitamente a execução de **três tarefas preparatórias em paralelo** dentro da FAST-PERF-01, com branches e PRs draft separados, todos direcionados a `dev/fast-track-performance-2026-11-05`: **Issue #1/F1-01** — proposta de arquitetura/stack; **Issue #2/PAR-02** — matriz de segurança e isolamento multiempresa; **Issue #3/PAR-03** — especificação de testes e dados sintéticos. A coordenação e propriedade de arquivos estão em `docs/execucao/PARALLEL-01-COORDENACAO.md`. As tarefas 2 e 3 **não implementam código** antes da homologação de stack. A aprovação do paralelismo **não comprova que as execuções Codex já começaram**. Ficam preservados todos os limites: `main` e VPS intactas, APIs e contas reais bloqueadas, sem pagamento, contratação ou publicação de campanhas.


## Mudança de prioridade: Portal-first (05/11/2026)

O responsável recusou **expressamente a contratação/atendimento manual fora do Portal**. Toda a jornada comercial e de cliente do Performance deve acontecer **no Portal Imagine**, que mantém seu login/organização e integra por contrato seguro ao motor Performance independente. A diretriz atual está em [PERF-PROD-D01](docs/performance-ai/PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md) na `main` documental do Performance e em [PORTAL-PERF-D01](https://github.com/rafhael100/imagine-portal/blob/docs/portal-f1-00-2026-11-05/docs/portal/PORTAL-PERF-D01-MVP-NATIVO-2026-11-05.md) na branch documental do Portal. A oferta manual PERF-COM-D01 foi superada e a issue #4 encerrada como não planejada.

A nova prioridade de F1-01/PAR-02/PAR-03 é viabilizar **interface técnica segura e mockada** entre os sistemas, sem adotar stack Performance antes da proposta/aprovação nem inventar valores/contratos. Seguem **sem autorização real**: API Google/Meta, mídia, Pix/Asaas/PSP, dados dos dois interessados, login real, VPS, deploy e `main`. O Portal já tem stack Laravel/PostgreSQL aprovada, mas só os segmentos de implementação expressamente liberados (ex.: Portal F1-01-B-A) podem ser codificados. Os dois interessados **não** são contratos ativos.
