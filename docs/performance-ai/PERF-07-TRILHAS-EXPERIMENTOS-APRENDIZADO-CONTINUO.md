# PERF-07 — Trilhas Estratégicas, Experimentos e Aprendizado Contínuo

**Produto:** Imagine Performance AI
**Data-base:** 03/11/2026
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1
**Status:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-07.01 a PERF-07.36**, conforme decisão expressa do usuário; preservados os 36 testes de homologação e todas as pendências técnicas/metodológicas. **Não homologado tecnicamente nem autorizado para programação ou operação real.**
**Dependências funcionais aprovadas:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30; PERF-05.01–05.36; PERF-06.01–06.34. Nenhuma aprovação constitui homologação técnica.
**Restrições:** apenas documentação. Não iniciar código, integrações reais, despesas, campanhas, testes em contas externas, publicação ou execução de agentes.

## 1. Objetivo e limites

PERF-07 será o **motor metodológico** que transforma problemas comerciais em hipóteses, define ensaios com comparação apropriada, documenta evidências e torna aprendizados auditáveis por meio de uma **Biblioteca Inteligente de Playbooks**.

O sistema **não pode chamar uma alteração de campanha de experimento causal sem desenho que sustente essa interpretação**. Melhorar CTR, CPL ou pontuação interna não substitui cumprir metas comerciais de PERF-01. Um teste sem amostra suficiente, observação de antes/depois ou atribuição de conversão Google/Meta não comprovam, isoladamente, aumento incremental das vendas.

**Fronteiras:**
- PERF-01: objetivo comercial, lead válido/qualificado, KPIs e ICP versionados.
- PERF-02: briefing, prontidão, autorizadores e escopo contratual.
- PERF-03/04: capacidades oficiais Google Ads/Meta Ads, campanhas e estados no provedor.
- PERF-05: material, anúncio, página, formulário, direitos, aceite e manifesto versionado.
- PERF-06: eventos, coortes, correções, atribuição, confiabilidade da medição.
- PERF-08: agentes, execução, alçadas e Central de Aprovações.
- PERF-09: lead, integração CRM e feedback comercial confirmado.
- PERF-10: painéis e relatórios; PERF-11: observabilidade, contingência, reconciliação.
- PERF-12: produtos, cotas, preço, despesas e escalabilidade.
- Portal Imagine: conta comercial e interface; motor Performance: conhecimento e operação isolados por empresa.

## 2. Requisitos aprovados funcionalmente

### PERF-07.01 — Catálogo de trilhas estratégicas
Trilhas condicionais para diagnóstico inicial, teste de oferta, captação, validação do sinal, qualificação, otimização, expansão, recuperação e manutenção. Cada trilha terá pré-requisitos, riscos, objetivo, critérios de entrada/saída, responsáveis e evidências de escolha. Nenhuma trilha universal para todos os segmentos.

### PERF-07.02 — Seleção contextual da trilha
Considerar ICP, ticket quando confirmado, verba autorizada, prazo comercial, geografia, maturidade de conversões, volume, atendimento, concorrência e conectores disponíveis. Sem dados confiáveis, selecionar diagnóstico/observação, não promessa de CPQL ou ROAS. Troca de trilha requer versão e justificativa.

### PERF-07.03 — Catálogo de problemas, causas e oportunidades
Separar sintoma observado, suspeita, causa evidenciada, problema de coleta e hipótese. Exemplos: volume baixo, formulários quebrados, CPQL alto, campanha limitada por política, lead não atendido e saturação criativa. Preservar origem, severidade e nível de incerteza.

### PERF-07.04 — Registro prévio da hipótese
A hipótese tem ID/versão, responsável, problema, intervenção, resultado esperado, contraposição, métrica principal, efeito mínimo relevante, guardrails, dados anteriores e método. Registrar antes dos resultados; achado retrospectivo deve ser rotulado exploratório.

### PERF-07.05 — Objetos e estados do experimento
Separar hipótese, desenho de teste, aprovação, execução oficial, evidência, parecer e playbook candidato. Estados candidatos: rascunho, incompleto, pronto para aprovação, autorizado, agendado, em execução, pausa autorizada, concluído, inconclusivo, invalidado, cancelado, reconciliado. O estado interno não se confunde com o estado oficial da plataforma.

### PERF-07.06 — Matriz de métodos e força da evidência
Métodos candidatos: experimento randomizado, teste nativo da plataforma, estudo incremental/lift, quase experimento com comparação, estudo observacional, comparação temporal e análise exploratória. Registrar pressupostos, vieses, limitação do controle e força da evidência. Não tratar correlação como causalidade.

### PERF-07.07 — Compatibilidade oficial Google/Meta
Google Ads API oferece fluxos **system-managed**, **intra-campaign**, **asset optimization** e **Campaign Mix**, conforme tipo de campanha/versão/permissão. **Campaign Mix exige allowlisting em determinadas condições**; não presumir disponibilidade universal. Na Meta, verificar recursos oficiais de A/B testing e estudos de lift na conta e se podem ser criados/geridos via API, UI ou somente manualmente. O suporte final é uma matriz por versão, conta, tipo, escopo e permissão.

### PERF-07.08 — Unidade de alocação e contaminação
Definir unidade de aleatorização e separação de controle/tratamento compatível com provedor e desenho: pessoa, sessão, campanha, geografia ou grupo admissível. Registrar sobreposição de público, concorrência, interferência e desequilíbrio. Não inferir que campanhas paralelas são grupos independentes.

### PERF-07.09 — Controle, tratamento e variável estudada
Cada braço possui IDs e versões de recursos, características alteradas, orçamento, distribuição de tráfego e condições de base. Quando o objetivo é efeito de uma variável, impedir alterações simultâneas não planejadas; desenho multivariado exige método e volume suficientes.

### PERF-07.10 — Métrica primária e métricas de segurança
Métrica primária deve acompanhar o objetivo do cliente (por exemplo, CPQL confirmado, oportunidade, venda) quando fontes válidas estiverem disponíveis. Métricas secundárias podem incluir CTR, CPC, CPLV, atendimento e receita atribuída. Guardrails independentes: despesa, qualidade dos leads, reclamação, disponibilidade do destino, compliance e privacidade. A IA não poderá trocar a métrica primária após olhar o resultado só para declarar sucesso.

### PERF-07.11 — Amostra, MDE, variância e poder
Planejar tamanho de amostra/poder estatístico/efeito mínimo detectável e limite comercial conforme métrica, taxa-base, variância e teste pretendido. Não impor número mágico de cliques, leads ou dias. Volume pequeno gera recomendação de observação/novo desenho; não criar falso vencedor.

### PERF-07.12 — Duração e amadurecimento comercial
Analisar sazonalidade, dia da semana, ciclos de atendimento, aprendizagem de lances, calendário, janela de atribuição e atraso do CRM. Um experimento pode encerrar veiculação e permanecer “aguardando maturação comercial” até coortes estarem aptas a avaliação. Prazo não é universal.

### PERF-07.13 — Inspeções intermediárias e paradas antecipadas
Diferenciar parada por risco/suspensão da conclusão por vitória. Inspeções repetidas de significância sem desenho sequencial aumentam chance de falso positivo. Métodos sequenciais/Bayesianos/adaptativos exigem procedimento, limite de observações, condição de parada e aprovação antes do início.

### PERF-07.14 — Multiplicidade e seleção enviesada de variantes
Considerar múltiplos anúncios, segmentos, métricas e testes paralelos. Evitar promover melhor aparente resultado de dezenas de variantes como “validado” sem ajuste metodológico ou confirmação apropriada. Achados posteriores geram hipóteses para novo teste.

### PERF-07.15 — Integridade e invalidação do ensaio
Gate antes/durante/depois: permissões, rastreamento, versões, grupos, amostra, entrega, concorrência, mudanças externas, políticas, dados tardios e compliance. Estado “invalidado” ou “inconclusivo” é resultado legítimo; deve preservar motivo e propostas para reensaio.

### PERF-07.16 — Avaliação comercial e econômica
Separar gasto de mídia, produção criativa, licenças, custos de IA, trabalho humano eventual e taxas comerciais segundo composição definida. Analisar CPQL, oportunidades, vendas e margem apenas quando fontes confiáveis. Não atribuir melhora causal por comparação de CPL sem controle.

### PERF-07.17 — Testes de criativos, ofertas e páginas
Vincular manifesto aprovado do PERF-05: criativo, anúncio, CTA, oferta/preço confirmado, landing, formulário e evento. Versão material alterada durante o teste deve ser registrada e pode invalidar a comparação ou requerer nova aprovação.

### PERF-07.18 — Testes de campanha e inteligência nativa
Respeitar Smart Bidding Google, Advantage+ Meta e seus períodos de aprendizado. Evitar mudanças de alta frequência e interferências entre campanhas. Mudanças pelo cliente/agência/plataforma precisam ser detectadas conforme capacidades e registradas; nenhuma reversão tácita.

### PERF-07.19 — Testes com CRM e qualidade comercial
Usar lead qualificado, oportunidade e venda **confirmados** no CRM Imagine/externo, quando integrado; não converter lead scoring inferido da IA em fato. Com baixa cobertura ou atraso, restringir conclusões. Dados pessoais permanecem no escopo e finalidade autorizados.

### PERF-07.20 — Causalidade, incrementalidade e atribuição
Diferenças de atribuição Google/Meta e comparações antes/depois não comprovam lift. Atribuição indica crédito sob regra; incrementalidade exige desenho que estime contrafactual. Agentes devem rotular evidência conforme método e evitar afirmações absolutas.

### PERF-07.21 — Parecer final padronizado
Parecer contém método, hipótese pré-registrada, contas/variantes, métrica primária, amostra, período, custos, resultado, incerteza, qualidade de dados, interferências, janela comercial e responsável. Estados: favorável, desfavorável, inconclusivo, invalidado e interrompido por segurança. “Sem evidência de diferença” não equivale a “efeitos iguais”.

### PERF-07.22 — Biblioteca Inteligente de Playbooks
Playbook versionado guarda nome, finalidade, contexto aplicável, ações previstas, dados exigidos, condições de entrada, riscos, evidência e restrições. Estados: conceitual, testado exploratoriamente, candidato a validação, validado no contexto, suspenso, reprovado, obsoleto/arquivado. O histórico desfavorável não é apagado.

### PERF-07.23 — Promoção, reprovação e governança do playbook
A IA pode propor promoção/rejeição, mas não publicar status de conhecimento validado nem aumentar autonomia sem governança. Exigir força mínima de evidência adequada ao risco, disponibilidade das fontes, reprodutibilidade quando necessária, revisão e responsável. Revisão de playbook não muda campanhas em produção automaticamente.

### PERF-07.24 — Transferência de aprendizados entre clientes
Aproveitar padrões metodológicos e achados abstratos, condicionados a segmento, oferta, idioma, geografia, público, orçamento e qualidade dos dados. Não compartilhar métricas nominativas, públicos, listas de clientes, mensagens privadas ou criativos licenciados entre empresas. Benchmark agregado depende de política jurídica, consentimentos/contratos e prevenção de reidentificação.

### PERF-07.25 — Memória operacional com procedência
Memória por empresa, contexto e versão; armazenar hipótese, origem, resultados, parecer, modelo utilizado e responsáveis. Agente deve citar evidências rastreáveis e reconhecer contradições. Nunca utilizar memória livre da IA como autoridade de venda ou prova experimental.

### PERF-07.26 — Obsolescência e degradação
Detectar mudança de política/API, mercado, sazonalidade, produto, audiência, orçamento e perda de eficiência; promover revalidação contextual. Playbook desatualizado fica impedido de novas ações automáticas até revisão, sem excluir histórico.

### PERF-07.27 — Estratégias proibidas
Bloquear playbooks e experimentos baseados em segmentação discriminatória, coleta indevida de dados pessoais, ofertas falsas, urgência fictícia, depoimentos inventados, claims não comprovados ou manipulação das políticas de anúncios. Eficiência aparente não supera privacidade e conformidade.

### PERF-07.28 — Backlog e priorização dos experimentos
Priorizar por impacto comercial esperado, risco, urgência, valor de aprendizado, complexidade, dados disponíveis, custo, dependências e capacidade de atendimento. Scores de priorização são heurísticas justificadas, não previsões garantidas de ROI.

### PERF-07.29 — Governança de gasto dos experimentos
Planejar gasto de controle e tratamento, mídia adicional, produção, uso de IA, duração e limite financeiro. Experimento nativo não significa tráfego gratuito. Gastos e alterações de orçamentos exigem regras PERF-08/11 e aprovação do responsável; nenhum teto numérico é presumido.

### PERF-07.30 — Execução, idempotência e reconciliação
Comandos candidatos são esquemas futuros, não código: planejar, submeter aprovação, agendar, consultar, encerrar, propor promoção e revisar playbook. Cada comando traz tenant/serviço/conta, hipótese, experimento, versão, autoridade, orçamento, correlação, idempotência e estado prévio. Resposta inconclusiva exige reconciliação oficial antes de retentativa.

### PERF-07.31 — Agentes especialistas e separação de funções
Papéis propostos: supervisor, estrategista, metodologista/estatístico, auditor de qualidade de dados, analista comercial, revisor criativo, auditor de políticas, gestor de playbooks e monitor de degradação. São **papéis**, não obrigatoriamente nove serviços. Quem propõe não deve homologar sozinho os próprios resultados.

### PERF-07.32 — Painel de experimentos no Portal Imagine
Mostrar trilha, hipótese, desenho, versão, aprovação, orçamento previsto, janela, métricas e estado oficial/derivado; cliente poderá ver resultados, ressalvas e próximos passos, conforme permissão. Diferenciar claramente rascunho, teste em execução, resultado e playbook validado.

### PERF-07.33 — Universidade Imagine e microtrilhas
Material pedagógico contextual: hipótese, controle, variável, amostra, tamanho de efeito, correlação vs. causalidade, coortes, CPQL, interpretação de A/B e feedback do CRM. Método: conceito → exemplo → simulação → prática guiada → verificação de compreensão → transferência. Curso não concede acesso a campanhas ou autorizações financeiras.

### PERF-07.34 — Métricas internas de qualidade da aprendizagem
Monitorar percentual de hipóteses com pré-registro, testes válidos/inconclusivos, custo por teste, tempo de decisão, replay de falhas, promoção/revogação de playbooks e resultados comerciais quando avaliáveis. Mais experimentos não é, isoladamente, melhor desempenho.

### PERF-07.35 — Auditoria, privacidade e revisões
Registrar fonte, versão, evidência, atores, autorização, resultados e mudanças de critérios; isolar dados multiempresa, proteger segredos e aplicar LGPD. Exclusão/retificação de dados pessoais não pode deixar cópias expostas em playbooks e logs acessíveis.

### PERF-07.36 — Homologação integrada e critérios de passagem
Testar limites efetivos Google/Meta por tipo de experimento e API, alçadas financeiras, verificação do provedor, amostra, falhas, retentativas, concorrência, segurança, custos, privacidade e auditoria. Aprovação funcional não homologa infraestrutura, estatística final, API, agentes, operação ou programação.

## 3. Níveis de evidência e critérios de promoção

| Nível | Método/força | Texto admissível | Situação do playbook |
| --- | --- | --- | --- |
| E0 — Observação | Antes/depois ou correlação | “Tendência observada; hipótese” | Conceitual/exploratório |
| E1 — Comparação condicionada | Comparador com vieses conhecidos | “Evidência favorável, com limitações” | Candidato a confirmação |
| E2 — Experimento adequado | Controle, amostra, integridade, objetivo pré-definido | “Efeito medido neste contexto, sujeito a limites” | Candidato a validado após aprovação |
| E3 — Replicação apropriada | Estudos independentes ou repetidos em contextos análogos | “Evidência replicada para contextos definidos” | Revisão de generalização, nunca regra universal |

E0–E3 são **rótulos propostos pela Imagine**, não certificações estatísticas externas. Dados insuficientes podem impedir atribuição de qualquer nível conclusivo.

## 4. Fluxo metodológico e limites operacionais

\`Problema e evidência\` → \`hipótese e contrahipótese\` → \`plano de comparação\` → \`amostra/custos/guardrails\` → \`validações técnicas\` → \`aprovação comercial e financeira\` → \`execução futura por provedor homologado\` → \`observação e maturação comercial\` → \`parecer metodológico\` → \`revisão independente\` → \`playbook candidato/validado no contexto/reprovado\`.

**Não iniciar nenhum teste real com o aceite deste arquivo.** Ausência de tempo ou amostra não pode ser contornada declarando vencedora a variante com mais cliques.

## 5. Cenários futuros de aceitação — HT-PERF07

1. **HT-PERF07-01:** empresa sem histórico confiável recebe trilha de diagnóstico, não promessa de resultado.
2. **HT-PERF07-02:** dois negócios com objetivos diferentes recebem trilhas contextualizadas.
3. **HT-PERF07-03:** falha do CRM é identificada como hipótese/falha de feedback, não como baixa qualidade comprovada de anúncios.
4. **HT-PERF07-04:** hipótese inserida depois do resultado é rotulada exploratória.
5. **HT-PERF07-05:** experimento em rascunho não aparece como ativo.
6. **HT-PERF07-06:** antes/depois sem controle não vira prova causal.
7. **HT-PERF07-07:** Campaign Mix indisponível para conta não é criado por API.
8. **HT-PERF07-08:** tipo de teste Meta sem suporte automatizado mostra limitação/alternativa autorizada.
9. **HT-PERF07-09:** sobreposição indevida entre controle/tratamento invalida interpretação de grupos independentes.
10. **HT-PERF07-10:** quatro variáveis alteradas simultaneamente não têm efeito atribuído a uma só.
11. **HT-PERF07-11:** CTR melhora, CPQL primário piora; experimento não é declarado sucesso comercial.
12. **HT-PERF07-12:** volume estatístico insuficiente gera status inconclusivo.
13. **HT-PERF07-13:** inspeções repetidas sem método sequencial não habilitam “parada por vitória”.
14. **HT-PERF07-14:** múltiplas variantes sem controle de seleção não geram vencedor validado.
15. **HT-PERF07-15:** rastreamento quebrado durante teste invalida análise dependente.
16. **HT-PERF07-16:** gastos de mídia e de IA/criativos são diferenciados.
17. **HT-PERF07-17:** página alterada após aceite invalida vínculo de versão do experimento.
18. **HT-PERF07-18:** alterações externas concorrentes geram investigação, não overwrite.
19. **HT-PERF07-19:** venda confirmada semanas depois revisa coorte sem apagar parecer anterior.
20. **HT-PERF07-20:** Google e Meta atribuem a mesma venda sem duas vendas incrementais.
21. **HT-PERF07-21:** resultado não significativo não é declarado equivalência provada.
22. **HT-PERF07-22:** playbook validado aponta para experimento e contexto.
23. **HT-PERF07-23:** agente que propôs playbook não o promove sozinho.
24. **HT-PERF07-24:** tenant A não lê público, campanha ou dados de tenant B.
25. **HT-PERF07-25:** memória do agente fornece fontes/versão ou declara desconhecimento.
26. **HT-PERF07-26:** alteração de política de canal marca playbook para revisão.
27. **HT-PERF07-27:** estratégia enganosa não é promovida apesar de CPL aparente vantajoso.
28. **HT-PERF07-28:** orçamento pequeno não gera múltiplos testes concorrentes incompatíveis.
29. **HT-PERF07-29:** teste de gasto extra fica aguardando autorização.
30. **HT-PERF07-30:** resultado incerto ao criar/promover experimento exige reconciliação antes de retentar.
31. **HT-PERF07-31:** metodologista e supervisor mantêm revisão distinta de geração.
32. **HT-PERF07-32:** painel informa amostra, período, incerteza e validade da conclusão.
33. **HT-PERF07-33:** conclusão da Universidade Imagine não concede escrita ou gasto.
34. **HT-PERF07-34:** volume de experimentos sem melhoria comercial não é tratado como sucesso.
35. **HT-PERF07-35:** revogação/retificação de PII alcança cópias e derivados conforme política, sem expor dados.
36. **HT-PERF07-36:** aprovação funcional não ativa testes, campanhas, APIs, agentes ou programação.

## 6. Pendências preservadas

**P0-EXP-01:** metodologia estatística versionada, métrica primária, MDE, poder, inspeção sequencial e multiplicidade.  
**P0-EXP-02:** matriz real por Google/Meta, tipo de campanha, nível de acesso e experimento (UI/API), com Campaign Mix e outras modalidades sujeitas a habilitação.  
**P0-EXP-03:** identidade e versão de hipótese, anúncio, landing, formulário, evento, coorte e resultado comercial.  
**P0-EXP-04:** orçamento, gastos do experimento, autorização, limites, segurança, pausa, retentativa e reconciliação PERF-08/11.  
**P0-EXP-05:** tratamento legal de aprendizados, datasets, dados multiempresa, consentimentos/licenças e benchmarking.  
**P0-EXP-06:** política de evidência mínima, promoção/reprovação, auditoria independente e obsolescência de playbooks.  
**P0-EXP-07:** fronteira atribuição versus incrementalidade e linguagem proibida sem prova causal.  
**P0-EXP-08:** contratos oficiais dos experimentos e verificação de estados inconclusivos/duplicados.

**P1-EXP-01:** catálogo de trilhas por maturidade/objetivo e adaptação por segmento.  
**P1-EXP-02:** experiência Portal para controle, orçamento, relatórios e justificativas.  
**P1-EXP-03:** avaliação de agentes, custo por teste e incidentes de metodologias.  
**P1-EXP-04:** microtrilhas pedagógicas da Universidade Imagine.

**P2-EXP-01:** experimentos avançados de incrementalidade quando elegíveis e economicamente justificáveis.  
**P2-EXP-02:** abstração/benchmarks entre clientes com autorização e avaliação jurídica.  
**P2-EXP-03:** testes adaptativos/Bayesianos, após metodologia formal e piloto.

## 7. Aprovação funcional registrada e próximo módulo

**Aprovação expressa registrada:** PERF-07.01 a PERF-07.36 aprovados conforme especificação v0.1, incluindo **36 casos HT-PERF07** de homologação futura e preservando integralmente as pendências P0/P1/P2. Nenhum teste de integração foi executado; métodos estatísticos finais, autonomia, política de gastos, sistemas, APIs e código não foram homologados nem autorizados.

Próxima etapa em elaboração: **PERF-08 — Agentes de IA, Autonomia, Governança e Central de Aprovações**, apenas planejamento.

## 8. Referências para homologação técnica posterior (consultadas em 03/11/2026)

- [Google Ads API — visão geral dos experimentos](https://developers.google.com/google-ads/api/docs/experiments/overview)
- [Google Ads API — experimentos gerenciados](https://developers.google.com/google-ads/api/docs/experiments/system-managed)
- [Google Ads API — testes intracampanha](https://developers.google.com/google-ads/api/docs/experiments/intra-campaign)
- [Google Ads API — Campaign Mix, recurso sujeito a allowlist](https://developers.google.com/google-ads/api/docs/experiments/campaign-mix)
- [Meta Business — ferramentas de testes e experimentos](https://www.facebook.com/business)
- [ANPD — materiais de privacidade e proteção de dados](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes)

> **Documento aprovado funcionalmente em 03/11/2026.** Nenhuma ação operacional, experimento real ou programação está autorizada.
