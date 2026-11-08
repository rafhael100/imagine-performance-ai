# PAR-02 — Matriz de segurança e isolamento multiempresa (PROPOSTA)

**Data:** 08/11/2026 · **Issue:** #2 · **Estado:** DOCUMENTAL, NÃO HOMOLOGADO  
**Escopo:** FAST-PERF-01/PARALLEL-01; modelo independente de framework. Sem testes executados, endpoints, bancos, credenciais ou integrações reais.

## 1. Fontes de autoridade e modelo de ameaça

- **Portal** é autoridade de identidade humana, organização, membership, contratos e elegibilidade; **Performance** mantém tenant operacional e autorização local por recurso/ação para seus objetos.
- O vínculo `portal_organization_id` ↔ `performance_tenant_id` é estabelecido somente por fluxo autenticado e autorizado. Identificador recebido em URL, body, header, cookie ou mensagem jamais concede acesso sozinho.
- Principais ameaças: IDOR/BOLA, elevação de privilégio, acesso cruzado via listas e contagens, tenant injetado, evento repetido/forjado, recuperação de usuário revogado, cache/logs sem contexto, aprovações inadequadas, dados sintéticos confundidos com reais e uso de tokens/contas de provedores.
- **Negar por padrão** acesso de leitura, escrita, exportação, administração, auditoria e chamadas internas sem contexto verificado. Nunca usar um modo `super-admin` implícito nas rotas de clientes.

## 2. Identidades/papéis candidatos

| Papel de contexto | Operação candidata | Negar expressamente |
| --- | --- | --- |
| Administrador da empresa | Gerir membros, escopos e contas **somente** do próprio tenant e dentro de contrato válido | Qualquer tenant vizinho; se atribuir permissões acima da própria alçada |
| Analista/marketing da empresa | Ler indicadores/campanhas autorizados; propor melhoria | Escrever no provedor, alterar limites ou aprovar gastos |
| Aprovador designado | Aprovar proposta versionada e dentro de alçada documentada | Aprovação tácita; autoaprovação quando vedada; mudança de versão/recurso após aprovação |
| Financeiro da empresa | Consultar apenas dados financeiros comerciais que o Portal permite | Contatos/PII operacionais; editar verba de Ads por perfil financeiro |
| Suporte Imagine | Ver mínimos do incidente mediante delegação específica e expiração | Impersonação silenciosa, acesso irrestrito ou ler segredos |
| Auditor autorizado | Ler eventos mínimos do tenant/escopo atribuído | Editar/apagar eventos ou listar todos os clientes |
| Workload Portal mockado | Consultar snapshot autorizado ou enviar evento elegível mockado | Compartilhar credencial humana, criar pagamento ou ampliar tenant |
| Agente IA N1 mockado | Gerar recomendações fictícias | Aprovar, executar ou elevar autonomia |

**Estados de usuário:** `active`, `suspended`, `revoked` são candidatos. Sessões e grants devem negar ações após suspensão/revogação; TTL/cancelamento devem ser especificados e validados antes de uso real. MFA e autenticação reforçada para funções elevadas são requisitos, **sem seleção de mecanismo aqui**. SSO humano não autorizado.

## 3. Matriz de autorização por recurso

| Recurso e operação | Empresa-admin | Analista | Aprovador | Suporte Imagine | Workload mock |
| --- | --- | --- | --- | --- | --- |
| Organização própria: consulta | Permitido se vínculo válido | Mínimo necessário | Mínimo necessário | Somente caso delegado | Apenas leitura contratual autorizada |
| Membros: listar/convidar | Permitido por escopo e papel | Negado | Negado por padrão | Negado por padrão | Negado |
| AdAccount fictícia: consultar | Permitido por recurso | Permitido por escopo | Metadados da proposta | Somente incidente delegado | Resumo estritamente necessário |
| Campanha/grupo próprio: consultar | Permitido por escopo | Permitido por escopo | Apenas o relacionado | Somente incidente delegado | Resumo mock autorizado |
| Campanha/grupo: alterar no provedor | **Negado nesta fase** | **Negado** | **Negado** | **Negado** | **Negado** |
| Proposta: criar | Permitido se papel/escopo | Permitido se papel/escopo | Negado por padrão | Negado por padrão | Negado |
| Proposta: aprovar | Apenas quando designado | Negado | Permitido se designado, versão/conta/alçada válidas | Negado | Negado |
| Auditoria: consultar | Somente tenant/escopo | Negado por padrão | Eventos da proposta | Incidente delegado | Metadados próprios |
| Exportação ampla ou credenciais | **Negado nesta fase** | **Negado** | **Negado** | **Negado** | **Negado** |

*“Permitido” é requisito de produto futuro, não privilégio já implementado. Toda célula é condicionada a tenant, vínculo, estado de serviço, papel, recurso, contrato e finalidade. Na ausência dessas evidências, negar.*

## 4. Controles técnicos a especificar após decisão de stack

1. **Resolução do tenant:** derivar do contexto de autorização verificado, cruzar com vínculo no backend e checar `resource.tenant_id`; não usar apenas filtro fornecido pela UI.
2. **Listas, joins e agregações:** aplicar restrições em todas as fontes, inclusive consultas de contagem, histórico, painel e exportação; checar acesso ao objeto pai e filho.
3. **Escritas:** nova linha recebe tenant derivado do contexto, não do payload; mutação de `tenant_id` não permitida; revalidação antes de commit.
4. **Banco:** chaves compostas/índices e isolamento transacional; avaliar PostgreSQL RLS como segunda camada com teste de bypass por conta de serviço, sem presumir que RLS isoladamente resolve autorização.
5. **Cache/filas:** particionar chaves por tenant/serviço/escopo; mensagem fora de prazo ou de tenant divergente é rejeitada ou colocada em revisão; idempotência sem cruzamento de escopo.
6. **Logs/auditoria:** `actor_ref`, `tenant_ref`, `resource_ref`, `action`, `decision`, `reason_code`, `correlation_id`, versão e tempo; minimização de PII, sem token, cookie ou prompt sensível. Retenção/exclusão e integridade **dependem de homologação própria Performance**.
7. **Aprovação humana:** manifesto imutável com identidade do proponente, alvo, versão, valor/risco, prazo, aprovador autorizado e revogação; editar manifesto invalida aceite. Silêncio não aprova.
8. **Integração:** autenticidade/autorização workload para produção ainda em estudo; em mocks usar identidade sintética explicitamente inválida fora de teste. Replays duplicados não provisionam duas vezes.
9. **Revogação:** suspensão de serviço ou perda de vínculo impede novas operações Imagine sem afirmar pausa de anúncios externos; status de fonte informado como `demo`, `unknown` ou `unavailable` conforme evidência.
10. **Credenciais:** segredo nunca em Git, logs, resposta do Portal ou artefatos de teste. CI futura fará varredura de segredos sem requerer novo fornecedor pago.

## 5. Cenários adversariais a encaminhar à PAR-03

| ID | Tentativa maliciosa/falha | Resultado requerido |
| --- | --- | --- |
| SEC-01 | Usuário do tenant A envia `tenant_id=B` | 403/negação tipada; nenhum dado de B |
| SEC-02 | A consulta URL de campanha de B por ID direto | Negação, inclusive ocultando existência sensível |
| SEC-03 | A filtra lista/contagem de B | Sem linhas nem inferência de B |
| SEC-04 | A referencia grupo de B dentro de campanha A | Negação e consistência referencial |
| SEC-05 | Usuário suspenso tenta usar sessão/ação antiga | Negação imediata conforme regra homologada |
| SEC-06 | Analista tenta aprovar a própria alteração financeira | Negação por alçada/segregação |
| SEC-07 | Aprovação válida tem versão/valor/alvo mudado | Invalidar aprovação; nova decisão exigida |
| SEC-08 | Evento Portal fake, expirado ou com identidade inválida | Não alterar estado do serviço |
| SEC-09 | Reenvio do mesmo evento de elegibilidade | Mesmo efeito uma vez; deduplicação |
| SEC-10 | Erro/log/cache de A revela metadados de B | Não expor; teste negativo |
| SEC-11 | Suporte sem caso ativo acessa tenant | Negar e auditar tentativa |
| SEC-12 | Serviço cancelado tenta consultar/alterar novo recurso | Negar novas ações; não falsear status Google/Meta |

**Status de SEC-01 a SEC-12: NÃO EXECUTADOS**. Resultados são expectativas, não evidência operacional.

## 6. Gates e aceite deste documento

Pendente: escolha técnica F1-01; definições de identidade, autorização, workload, papéis, MFA, sessões, revogação, LGPD e retenção; testes de isolamento/abuso; consentimentos e direito de acesso Google/Meta; política de aprovações e alçadas. Permanecem abertos os **91 P0**, os **15 gates HT-R01**, BL05-D01–D20 e contratos CT-PERF candidatos. Este documento não libera OAuth, dados reais, infraestrutura, cobrança, contas Google/Meta, anúncios, agentes nem `main`.

Aceite documental: matriz explícita, negações por padrão, cenários adversariais e rastreabilidade; somente este arquivo alterado em PR draft independente para `dev/fast-track-performance-2026-11-05`.

**Referências:** `AGENTS.md`, `STATUS-ATUAL.md`, `PARALLEL-01-COORDENACAO.md`, `PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`, `PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md`, `PERF-P0-BL07-AGENTES-COMANDOS-INCIDENTES-AUDITORIA.md`, `PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md`.
