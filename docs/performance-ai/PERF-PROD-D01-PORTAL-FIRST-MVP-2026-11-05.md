# PERF-PROD-D01 — Diretriz Portal-first do Imagine Performance AI

**Data:** 05/11/2026  
**Decisão do responsável:** "preciso que seja tudo ja no portal, esse formato manual nao dar certo vamos focar no desenvolvimento do que ja falamos".  
**Status:** **DIRETRIZ FUNCIONAL APROVADA** para substituir a proposta de prestação comercial manual por **jornada nativa no Portal Imagine**, integrada ao backend independente Imagine Performance AI. Não é aceite de preços, pagamento real, integração Google/Meta, implantação, homologação de segurança ou ampliação irrestrita de código.  
**Dois primeiros interessados:** motivação de prioridade; **não** existem contratos nem clientes provisionados/ativos nesta autorização.

## 1. Substituição expressa da rota comercial assistida

A diretriz **PERF-COM-D01**, que sugeria contratar e atender os interessados **fora do Portal** por processo manual independente, fica **SUPERADA COMO CAMINHO DE LANÇAMENTO**. Preservar seu histórico por rastreabilidade; não utilizá-la como plano vigente e não avançar propostas/manuais individuais da Issue #4 como alternativa de lançamento. O usuário quer contratar somente quando a jornada estiver operacional no Portal, **não** um serviço substituto externo. Isto não impede supervisão e aprovações humanas **dentro do fluxo digital do Portal**.

Nenhuma mensagem foi enviada aos clientes, nenhum pagamento ou campanha foi efetuado, nenhuma data de go-live está garantida.

## 2. Experiência mínima exigida no Portal

O cliente deverá:
1. Acessar catálogo e página do Imagine Performance AI no Portal; conhecer Canal Único (Google ou Meta), Multicanal (Google+Meta), Operação Ampliada quando elegível, entregas, exclusões, capacidades, preço e período contratual **aprovados**.
2. Criar/acessar uma **conta do Portal**, com organização/empresa, usuários autorizados e permissões; **sem reutilizar login/sessão do CRM Imagine**, conforme PORTAL-TEC-D02. A possibilidade futura de SSO Portal/Performance/Universidade permanece questão não homologada.
3. Selecionar plano, canais, contas publicitárias e adicionais disponíveis, com **simulação determinística** dos valores, condições, impostos, vigências e aprovadores; não inventar preço nem cobrar excedentes silenciosamente.
4. Revisar proposta e contrato versionados no Portal, declarar aceites explícitos e aprovar condições de assinatura e eventuais alçadas; **nunca aceitar por silêncio**.
5. Pagar **a Imagine pelo serviço** por meio homologado e rastreável e consultar suas faturas/assinatura no Portal; lançamento real só após validação de PSP, impostos, emissão fiscal e política própria Performance.
6. Realizar **onboarding no Portal**: objetivos, negócio, orçamento de mídia, conta publicitária de titularidade do anunciante, autorizações delegadas Google/Meta, materiais e política de privacidade; somente conectar recursos com autorização e provedores homologados.
7. Acompanhar pelo Portal andamento, campanha(s) sob gestão, status, indicadores efetivamente disponíveis, limites, consumos/avisos, relatórios, propostas extraordinárias, aprovações e chamados, com auditoria.
8. Gerenciar renovação, alterações contratadas, solicitações, suspensão/cancelamento, faturas e acesso à informação de encerramento, sem confundir cancelamento Imagine com pausa real Google/Meta.

O lançamento do MVP exige uma versão **funcional e homologada** deste fluxo para clientes reais, ainda que módulos avançados de agentes/IA, criativos e multiunidade sejam disponibilizados depois, com comunicação transparente. **Não vender antecipadamente como pronto** o que permanecer em demonstração, mock ou ainda não homologado.

## 3. Fronteiras entre os sistemas

- **Portal Imagine = autoridade comercial, experiência e identidade humana do cliente para este fluxo.** Cadastro, organização/membership, catálogo, preços, proposta, pedido, contrato, cobrança, faturas, aceite, atendimento comercial e habilitação comercial do serviço.
- **Imagine Performance AI = autoridade operacional específica.** Contas/canais de anúncio, campanhas/grupos/conjuntos sob gestão, limites operacionais, integrações Google/Meta, agentes internos, análise, indicadores, tarefas e auditoria de operação.
- **Acesso pela interface do Portal:** backend do Portal encaminha consultas e comandos autorizados ao Performance por **contrato servidor-a-servidor candidato**, com escopos mínimos, vínculo estável organização/tenant, autenticação técnica, eventos idempotentes e auditoria. Não compartilhar banco, sessão, senha ou token de usuário entre produtos; não concluir SSO humano sem aprovação específica.
- **CRM Imagine é opcional**, com login separado. Universidade e Sites são produtos próprios e não condicionam contratação Performance.
- **Verba de mídia** é despesa do anunciante paga diretamente Google/Meta, independente das cobranças Imagine. Não presumir mecanismos de Pix/boleto ou recarga automatizada que não foram homologados.

## 4. Contratos de integração candidatos (não homologados)

| Contrato | Origem/autoridade | Evento/ação | Observações de segurança |
| --- | --- | --- | --- |
| PERF-PORT-01 | Portal | `performance.order.created`, `order.accepted`, `payment.confirmed`, `subscription.status.changed` | Versão, `event_id`, `tenant_id`, `order_id`, `correlation_id`, idempotência e assinatura |
| PERF-PORT-02 | Portal → Performance | Provisionar serviço somente após aceite contratual e estados financeiros elegíveis | Retry/reconciliação sem ativação duplicada e sem cobrar por chamada |
| PERF-PORT-03 | Performance → Portal | Status de onboarding, conexão, campanhas geridas, limites, relatórios e incidentes | Resposta mínima por organização/usuário autorizado, sem exposição de secrets |
| PERF-PORT-04 | Portal → Performance | Solicitação e aprovação de mudança material/extra, com alçada humana | Não publicar/mutar/anunciar antes de checar autorização atual e provedor |
| PERF-PORT-05 | Performance → Portal | Alertas de recarga programada, incidentes, consumo, falhas, expiração | Sinalizar estado de entrega e origem; sem presumir execução de pagamento |
| PERF-PORT-06 | Portal e Performance | Suspensão, cancelamento, revogação, exportação e retenção por serviço | Estado de serviço distinto da conta/anúncios externos; reconciliação |

Essas são **interfaces candidatas para planejamento e mocks**, não APIs públicas já existentes. Sem código/SDK real de integração até decisão específica e testes de segurança, acesso, contratos técnicos e falhas.

## 5. Sequência de implementação com foco em contratação pelo Portal

**FASE P-0 — Já pode continuar no fast-track isolado:** PR #11 da fundação Laravel Portal revisado sem merge; F1-01-B-A organização/permissões com dados fictícios conforme autorização específica; F1-01 do Performance para proposta de stack; PAR-02 e PAR-03 documentação paralela de segurança/testes. Não confundir dados legados com produto novo.

**FASE P-1 — Próxima frente a preparar para Codex:** desenhar e submeter para autorização de código do catálogo Performance no Portal, estados de produto/plano/preço, seleção de canal e **checkout simulado em fluxo Portal**, com dados sintéticos, sem PSP real, login de cliente real ou cobranças. Reusar os modelos de organização e ofertas homologados no Portal, não duplicar motores de preço.

**FASE P-2 — Coordenação Portal/Performance com mocks:** cadastro/autorização interna de tenant e ofertas, ciclo de contrato e habilitação simulada, serviço Performance provisionado em mock, onboarding de canal, lista de campanhas sob gestão com mock e painel básico integrado. Testes de `tenant isolation`, idempotência e negação de recursos cruzados.

**FASE P-3 — Homologação real de identidade, PSP, fiscal e conexão Google/Meta:** contrato comercial e oferta aprovados, identidade segura do Portal, integração de pagamento/assinatura e dados reais, API dos provedores/permissões, segurança/LGPD, logs, auditoria, consentimentos, operações de anúncio e contingência. Cada etapa exige gate e autorização específicos; **não** implica execução imediata.

**FASE P-4 — Primeiro cliente real via Portal:** fluxo ponta a ponta testado com clientes fictícios, readiness de segurança, contrato e preço publicados corretamente, pagamentos/PSP homologados, consentimentos, operação e suporte habilitados, liberação humana expressa do go-live. Não definir data até evidência de gates.

## 6. Prioridade operacional e restrições atuais

1. **Prioridade máxima** = jornada contratável e acompanhável no Portal com backend Performance acoplado apenas por interfaces oficiais.
2. Não prosseguir com propostas de **gestão manual fora do Portal**. Issue #4 anterior deve ser encerrada como **não planejada/superada**, preservando a trilha.
3. **Não reimplementar código antigo** em Node/SQLite como solução final: o legado é protótipo e apenas ideias/fluxos de negócio são reaproveitáveis; os dados são testes.
4. Portal: stack **Laravel + PostgreSQL** já aprovada, mas UI final, identidade, contratos e cobrança ainda não homologados. Performance: stack própria ainda sob avaliação F1-01; tecnologia semelhante não é aprovação implícita.
5. A `main` de ambos os repositórios, VPS, ambientes externos e operações de clientes permanecem inalterados. Somente branches e escopos já autorizados podem receber código; novos blocos de implementação exigem gate específico.
6. Nada nesta mudança autoriza migração de clientes reais, testes com credenciais, criação de anúncios, cobrança, gastos ou registro de informações pessoais de interessados no GitHub.

**Próxima ação:** alinhar backlogs e Codex com duas questões prioritárias: F1-01-B-A no Portal (domínio organização/permissões) e F1-01/PAR-02/PAR-03 no Performance; elaborar contratos e aceites do fluxo Portal-first para o próximo ciclo controlado de programação.
