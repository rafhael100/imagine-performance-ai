# PERF-P0 — Validação preliminar de servidores, licenciamento n8n e capacidade humana

**Projeto:** Imagine Performance AI  
**Data da pesquisa:** 04/11/2026  
**Situação:** **ESTUDO ECONÔMICO / GATES PENDENTES**. Não aprova preços, franquias, infraestrutura, fornecedores, licenças, homologações ou programação.  
**Documentos-base:** [viabilidade IA](PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md), [infraestrutura e rateio](PERF-P0-ESTUDO-INFRAESTRUTURA-RATEIO-ESCALA-2026-11-04.md), [roadmap de canais](PERF-ROADMAP-CANAIS-LANCAMENTO-EXPANSAO.md).

## 1. Resumo executivo

- Lançamento planejado: **Google Ads e Meta Ads**, com TikTok/LinkedIn adiados.
- Hipóteses comerciais: **1 canal mínimo R$ 290**, **2 canais mínimo R$ 390**, **15% da mídia elegível se maior que o mínimo**; IA extra/serviços especiais com aceite e extrato. Nenhum número está aprovado como oferta definitiva.
- Custo de tokens/imagens IA estimado no cenário-base, **com reserva**: **R$ 20,85/mês por cliente de 1 canal** e **R$ 41,70/mês por cliente de 2 canais**. Não são resultados medidos.
- Infra fixa atribuída ao Performance no estudo anterior: **R$ 962,31/mês**, totalmente hipotética, incluindo rateio do n8n e do servidor de agentes. O custo real da VPS e licenciamento do n8n **não** foi confirmado; não somar novamente aos R$ 4.000 de custo fixo agregado do cenário-base sem conciliação rubrica a rubrica.
- **Novo alerta P0:** n8n não deve ser tratado automaticamente como motor gratuito de SaaS; confirmar licença e orçamento aplicáveis ou definir arquitetura alternativa antes da implementação.

## 2. Referências verificadas para servidores (não são cotações da Imagine)

| Fornecedor | Exemplo público | Preço público observado | Condições e interpretação |
| --- | --- | --- | --- |
| OVHcloud VPS 2027 (catálogo atual consultado) | VPS-2, 4 vCores/8 GB/75 GB NVMe | **a partir de US$ 8,50/mês** | Preço inicial, região e impostos podem variar. Não inclui automaticamente todos os serviços de produção, backups adicionais, banco gerenciado ou mão de obra. |
| OVHcloud VPS 2027 (catálogo atual consultado) | VPS-4, 8 vCores/24 GB/200 GB NVMe | **a partir de US$ 23,37/mês** | Mesmo cuidado com região, recursos compartilhados, backups e suporte. |
| Hetzner Cloud | Tabela de preços atualizada em 15/06/2026; p.ex. CPX32 | **US$ 41,99/mês** (Alemanha/Finlândia, excl. IPv4) | Tarifas excluem IVA e há diferença regional. Confirmar configuração e disponibilidade específicas antes de comparar com Leaseweb. |
| Leaseweb | Infra conhecida do ecossistema: VPS 6 vCPU/16 GB/200 GB | **Preço faturado não fornecido/validado** | Não inferir cobrança real de folheto de outro país nem de cotação de concorrente. Consultar fatura/contrato, região, backups, tráfego e condições vigentes. |

**Fontes oficiais consultadas:**  
- https://www.ovhcloud.com/en/vps/  
- https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/  
- https://www.leaseweb.com/ ; contrato da conta do usuário não foi acessado.

**Separação arquitetural recomendada:** aplicação Performance própria; banco/filas privados e autorizados; n8n e agentes centrais compartilhados entre produtos em serviços distintos, com isolamento por empresa, auditoria, limites de acesso e medição por workload; desenvolvimento separado de produção.

## 3. Licenciamento n8n — pendência crítica

O fornecedor distingue:
- **Community self-hosted:** edição gratuita mas com restrições de funcionalidades/licença. Não presumir permissão de usar em toda integração comercial SaaS.
- **Business self-hosted:** **€ 667 por mês, faturado anualmente** na tabela pública consultada, com 40 mil execuções mensais. **Não presumir que é a licença que autoriza o caso concreto de SaaS da Imagine.**
- **Enterprise:** preço sob proposta; página oficial OEM descreve uso n8n como backend de funcionalidades de um produto, clientes consumindo execuções pelo UI, como **caso de licença Enterprise**.
- **OEM:** se clientes puderem criar/editar workflows no editor n8n incorporado ao produto, a página oficial indica **oferta OEM específica**, sob proposta comercial, não equivalente à Enterprise básica.

**Fontes do fornecedor:**  
- https://n8n.io/oem/  
- https://n8n.io/pricing/  
- https://docs.n8n.io/deploy/host-n8n/community-edition-features.md

**Decisão necessária antes de aprovar arquitetura financeira:** obter enquadramento formal e cotação do n8n para o escopo de CRM, Portal, Sites, Performance e Universidade, com distinção do que é automação puramente interna e do que o usuário final aciona/edita. Se o licenciamento SaaS for desproporcional ao lançamento, avaliar **orquestrador próprio por filas e jobs tipados na aplicação**, deixando n8n somente nos usos internos juridicamente permitidos; preservar segurança, idempotência, recuperação, trilhas, aprovações e limites. **Não é autorização para trocar ou programar a arquitetura.**

## 4. Custo humano: números usados na simulação, NÃO medidos

Custo de pessoal integral estimado em **R$ 45/hora carregada**, somente como hipótese; **inclui encargos somente se comprovado por cálculo de folha/contrato**. A carga abaixo deve incluir onboarding apropriado, briefing, revisão de criativos, atendimento, qualidade, incidentes, finanças e supervisão.

| Cenário | 1 canal (min/mês) | Custo humano 1 canal | 2 canais (min/mês) | Custo humano 2 canais |
| --- | ---: | ---: | ---: | ---: |
| Econômico | 25 | R$ 18,75 | 40 | R$ 30,00 |
| Base | 40 | R$ 30,00 | 65 | R$ 48,75 |
| Estresse | 100 | R$ 75,00 | 160 | R$ 120,00 |

**Pendência:** coletar tempos reais por tarefa e variabilidade, picos, atividades manuais não automatizáveis, horas disponíveis/folgas e necessidade de cobertura. O custo de aquisição/relacionamento comercial não está incluído na hora acima.

## 5. Unidade econômica no preço mínimo — cenário-base

Hipóteses de cálculo: pagamento cartão com **2,99% + R$ 0,49** por transação; **10% de receita reservada para tributos apenas por hipótese**; custo IA já **com reserva de 30%**; infraestrutura incremental de **R$ 18** ou **R$ 32** por cliente/mês, respectivamente; carga humana base de **40/65 min** a **R$ 45/hora**.

| Rubrica | 1 canal | 2 canais |
| --- | ---: | ---: |
| Gestão mínima (receita) | R$ 290,00 | R$ 390,00 |
| IA com reserva | -R$ 20,85 | -R$ 41,70 |
| Infra variável | -R$ 18,00 | -R$ 32,00 |
| Mão de obra variável | -R$ 30,00 | -R$ 48,75 |
| Reserva tributária 10% (hipótese) | -R$ 29,00 | -R$ 39,00 |
| Gateway (2,99% + R$ 0,49) | -R$ 9,16 | -R$ 12,15 |
| **Contribuição antes dos custos fixos** | **R$ 182,99** | **R$ 216,40** |

**Não é lucro:** restam infraestrutura fixa, pessoal fixo e de plantão, licenciamento, CAC, inadimplência, contabilidade, segurança, jurídico e contingência não apropriados. Se custo fixo agregado fosse de **R$ 4.000/mês** e incluísse toda a infraestrutura fixa já computada, o ponto de equilíbrio puramente aritmético com mix 70% 1 canal / 30% 2 canais seria cerca de **21 clientes** *antes de CAC e outras omissões*. Não significa viabilidade comprovada.

## 6. Cenário de estresse — alerta especial para 2 canais

Hipóteses de 1/2 canais: IA **R$ 77,66 / R$ 155,31**, infraestrutura variável **R$ 50 / R$ 80**, tempo humano **100 / 160 minutos**, mesma receita mínima, gateway e reserva tributária.

| Indicador | 1 canal | 2 canais |
| --- | ---: | ---: |
| Receita mínima | R$ 290,00 | R$ 390,00 |
| IA, infra, mão de obra, tributos e gateway (hipóteses) | R$ 240,82 | R$ 406,46 |
| **Contribuição antes dos custos fixos** | **+R$ 49,18** | **-R$ 16,46** |

Com 2 canais no cenário de estresse, o piso de **R$ 390 não paga nem todos os custos variáveis estimados**. Nesse caso, nenhuma alocação de custos fixos resolveria a margem. Precisaremos validar volume de operações reais, controles de utilização e tempo de atendimento, e redesenhar franquias/preço/limites se necessário.

## 7. Sensibilidade ao custo da licença

O **preço real aplicável à Imagine é desconhecido**. Para não inventar uma cotação, testar o seguinte parâmetro de custo de licenciamento **atribuído mensalmente ao Performance**:

| Custo mensal atribuído (EXEMPLO) | 100 clientes | 300 clientes | 700 clientes |
| --- | ---: | ---: | ---: |
| R$ 1.000 | R$ 10,00/cliente | R$ 3,33/cliente | R$ 1,43/cliente |
| R$ 3.000 | R$ 30,00/cliente | R$ 10,00/cliente | R$ 4,29/cliente |
| R$ 6.000 | R$ 60,00/cliente | R$ 20,00/cliente | R$ 8,57/cliente |

Esses números NÃO são preços n8n e não substituem cotação. O compartilhamento da licença entre projetos deve ser **permitido pelo contrato e auditável**; não presumir que uma licença cobre automaticamente todas as operações/instâncias.

## 8. Gates antes da aprovação dos mínimos comerciais

1. **Confirmar com o n8n o tipo de licença aplicável e o custo real** para o backend multiempresa e eventuais construtores visuais, ou avaliar a alternativa de execução controlada sem dependência comercial.
2. **Registrar faturas e configurações reais** dos servidores já existentes, com região, RAM/vCPU, limites, backups, tráfego, níveis de segurança e custos incrementais.
3. **Cronometrar tarefas humanas em pilotos autorizados posteriormente** para obter custo por 1/2 canais e cenários de pico, sem iniciar programação nesta etapa.
4. Confirmar impostos, gateway e regime da empresa para prestação do serviço Performance; substituir reserva fiscal ilustrativa por cálculo fiscal validado.
5. Dimensionar concorrência, API quotas Google/Meta, jobs de IA, filas, retenções, logs, continuidade, segurança e recuperação, sem confundir número de clientes com capacidade instalada.
6. Rever franquias de campanhas, artes, revisões, IA interna e extras de IA, sem dupla cobrança e sem descontinuidade indevida de leads e funções críticas.
7. Recalcular 0, 100–700 clientes em cenário conservador/base/estresse após substituir todas as entradas não cotadas.

**Estado formal:** RES01-D01–D16 mantidas no escopo aprovado, RES-01, P0 e homologações permanecem abertos. **Nenhuma operação real, contratação, despesa ou desenvolvimento autorizado.**
