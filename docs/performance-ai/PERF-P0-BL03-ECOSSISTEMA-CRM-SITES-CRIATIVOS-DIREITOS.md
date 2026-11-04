# PERF-P0 — BL-03: Ecossistema, CRM, Sites, Criativos e Direitos

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da proposta v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-03 v0.1, BL03.01–BL03.38 e 38 critérios BL03-V**, conforme decisão expressa do usuário. **Os 13 P0 continuam ABERTOS**, as **18 deliberações BL03-D01–D18 permanecem candidatas, sem decisão específica**, e licenças, portabilidade, retenção, fornecedores, condições comerciais e homologações técnicas continuam pendentes. **Nenhuma programação ou operação real autorizada.**  
**Decisões de referência:** DC-07 (CRM, Sites, vínculos e cobrança), DC-08 (direitos de criativos, portabilidade e retenção).  
**Bases aprovadas:** PERF-ARQ-01, PERF-01–12, PERF-R01 e PERF-R02/anexo, BL-01 (estrutura de planos) e BL-02 (27 requisitos/27 critérios funcionais, com 15 decisões comerciais ainda não tomadas).  
**Pendências de responsabilidade primária:** **13 P0 ABERTOS**, conforme matriz abaixo; demais **78 P0**, 39 P1, 24 P2, divergências D-01–D-14 e 15 gates HT-R01 não homologados.  
**Contratos candidatos centrais:** CT-PERF-05 (criativos/landing), CT-PERF-07 (lead/entrega), CT-PERF-08 (feedback comercial), CT-PERF-20 (vínculos entre serviços); interfaces CT-PERF-01/03/06/09/13/14/19.  
**Restrição absoluta:** planejamento/documentação apenas, **sem programação, conexão de CRM, publicação de Sites, alterações DNS, coleta/transferência de dados reais, campanhas, contratação de ferramentas ou gastos**.

## 1. Objetivo e princípios

Detalhar a cooperação do Imagine Performance AI com CRM Imagine, CRMs externos, Sites Imagine, landing pages, anunciantes, provedores de mídia e fornecedores criativos, assegurando **fronteiras comerciais independentes**, propriedade/titularidade, licenças, aprovação formal, privacidade, portabilidade, integridade e responsabilidade técnica.

**Princípios já aprovados e aqui preservados:** CRM Imagine é opcional; Performance não cria novos planos CRM nem cortesia dos Sites; Sites mantém publicação/domínio/DNS/SSL/backup; contas publicitárias e anúncios dependem dos respectivos provedores; conteúdo do cliente e de terceiros exige licença e finalidade; qualquer mutação significativa precisa autorização conforme PERF-08. O Portal concentra identidade/comercial e o motor Performance continua separado.

## 2. Matriz de autoridade entre sistemas

| Componente | Autoridade e responsabilidades | Dependência permitida | Nunca presumir |
| --- | --- | --- | --- |
| Portal Imagine | Usuários, empresa, serviços, contratação, cobrança, Central de Aprovações e chamados | Identidade e contrato de cada produto | Ser dono dos bancos ou tokens de todos os produtos |
| Performance AI | Gestão de mídia, criativos/briefings, captação autorizada, eventos e diagnósticos | Conectores tipados e políticas PERF-08 | Acesso direto ao CRM, Workers, GitHub ou DNS |
| CRM Imagine | Contatos, leads, históricos, Lead Scoring, oportunidades/vendas | Recebimento confirmado e feedback via integração | Assinatura/cortesia concedida por Performance |
| CRM externo | Registros comerciais próprios e conectores que realmente suporta | API homologada, mapeamentos e permissões | Funcionalidade idêntica ao CRM Imagine |
| Sites Imagine | Site/landing contratados, domínio/hospedagem, publicação, formulários, backups e ajustes | Pedido autorizado com manifesto e aceite próprio | Uso de Cloudflare/GitHub/DNS por agente Performance |
| Google/Meta | Anúncios, revisão, contas e métricas oficiais | Leitura/mutação com permissões | Que request enviado signifique campanha ativa |
| Cliente/terceiros criativos | Titularidade, licenças, autorizações de imagem/voz/portfólio | Entregável licenciado conforme contrato | Transferência automática de propriedade/exclusividade |
| Universidade Imagine | Formação geral e tutoriais de produtos elegíveis | Ajuda contextual | Autorização de gasto, CRM, publicações ou certificados presumidos |

## 3. Requisitos aprovados funcionalmente BL03.01 a BL03.38

### BL03.01 — Catálogo de autoridades entre produtos
Portal é autoridade de identidade/contrato; Performance é autoridade da operação de anúncios e captação dentro de seus limites; CRM de registros e eventos comerciais; Sites de publicação/hospedagem; provedores confirmam campanhas. Não duplicar motores, credenciais, bancos ou centros de aprovação.

### BL03.02 — Ativação por contrato e direitos específicos
Cada assinatura Performance, Sites e CRM terá elegibilidade, cobrança, suspensão, dados, permissões e encerramento próprios, vinculados a empresa/serviço/marca. Ativar Performance não cria CRM nem Landing/Onepage nem concede uso de ferramentas de outro produto.

### BL03.03 — Vínculo de contas existentes e criação de novas
Na configuração do destino de leads, o cliente pode escolher CRM Imagine existente com autorização, contratar nova conta CRM separadamente ou permanecer sem CRM com destino mínimo elegível. Mesma marca e e-mail não provam titularidade; checagem formal do vínculo e MFA conforme risco.

### BL03.04 — Associação de múltiplas campanhas e projetos ao CRM
Permitir atribuir campanhas Google/Meta e, quando pertinente, sites/landings distintos a uma conta CRM já existente, mediante escolha e permissão por projeto. Origem e contrato de cada lead devem ser preservados; reutilização do CRM não implica conta nova, nem mescla automática de contatos.

### BL03.05 — CRM Imagine opcional e benefício Básico
Plano Básico do CRM e benefício comercial de cortesia ligados a Sites seguem suas condições independentes. A contratação Performance não ativa plano Básico cortesia, não altera preços/limites do CRM e não concede migração de dados ou upgrade gratuitamente.

### BL03.06 — Central Mínima de Leads sem CRM
Destinatário sem CRM pode usar Central Mínima autorizada e homologada com captação, visualização segura, exportação e feedback comercial simples. Não acrescentar funil sofisticado, automações de atendimento, base de conhecimento, equipes ou quotas ilimitadas de CRM. Retenção, preços e capacidade permanecem pendentes em PERF-09/12 e BL-04/06.

### BL03.07 — Roteamento de leads e autoridade da origem
Cada origem (Meta leadgen, formulário Sites, landing externa, etc.) deve identificar tenant, campanha, formulário, consentimentos, destino e versão de vínculo. Webhook Meta recebido não comprova dados recuperados; evento de Site não deve ser recapturado e contabilizado duas vezes.

### BL03.08 — Contrato de integração com CRM Imagine
Interface homologável versionada para criar/consultar lead, registrar origem, receber confirmação, status comercial e correções, respeitando API e permissões por tenant. Nunca dar acesso direto ao banco CRM, forçar mesclagem nem sobrescrever Lead Scoring 0–100 com palpite da Performance.

### BL03.09 — Catálogo de CRMs externos
Conectores externos devem ter inventário de fornecedor, versão/API, permissões, mapeamento de campos, estados oficiais, retentativa segura, limites, portabilidade e auditoria. Não atribuir recursos do CRM Imagine a CRMs sem suporte confirmado.

### BL03.10 — Mudança de vínculo do CRM no Performance
Alterar conta CRM ou destino do Performance requer solicitação/chamado, titular autorizado, verificação da nova conta, corte de recebimento seguro, tratamento de leads em trânsito e trilha. **Política de cobrança/gratuidade da alteração Performance segue em aberto (DC-07)**, sem herdar condições de Sites.

### BL03.11 — Regra já aprovada de mudança de vínculo Sites→CRM
Preservar regra específica dos Sites Imagine: alteração de vínculo de Site/landing a outra conta CRM mediante chamado, **nova integração gratuita para o serviço Sites**; transferência histórica de dados entre CRMs é responsabilidade do cliente por ferramentas de exportar/importar. Não estender a gratuidade à Performance.

### BL03.12 — Importação, exportação e autonomia dos dados comerciais
Cliente CRM pode usar recursos de autoatendimento para backup, exportar/importar dados estruturados conforme plano e permissões; migrações devem ser autorizadas, idempotentes e auditadas. Performance pode fornecer eventos/exportações próprias conforme contrato, mas não migrar histórico completo ou acessar CRM por atalho.

### BL03.13 — Estados de entrega e confirmação no destino
Manter diferença entre sinal recebido, payload recuperado, lead válido, enviado, aceito tecnicamente, confirmado no CRM e qualificado. Destino suspenso gera falha/contingência autorizada, não perda silenciosa nem declaração de sucesso. Reconciliação precede reenvio de resultado incerto.

### BL03.14 — Contingência por suspensão de serviço independente
Falha ou inadimplência em CRM não suspende Performance/Sites; cancelamento Performance não desativa CRM/Sites. Continuidade de leads/retentativa/avisos limitada por autorização, retenção e custos definidos; não processar PII indefinidamente sem fundamento legal.

### BL03.15 — Separação de retenções e backups
Retenção Sites IN-127 (3 dias aguardando entrega + 15 dias fila de falhas), retenção de CRM e backups de Sites são próprias de cada serviço; Performance terá regras distintas por evento, lead em trânsito, log, criativo, relatório e exclusão, com consulta jurídica e estudo de custo.

### BL03.16 — Rastreamento de origem comercial e feedback
CRM confirma estágio, oportunidade, venda/estorno e valores com timestamps/autoria. Performance envia dados elegíveis à mensuração PERF-06 e à atribuição, sem inflar vendas por dupla contagem entre Google/Meta e sem expor PII fora da finalidade.

### BL03.17 — Sites Imagine como serviço de publicação independente
Em site/landing contratado no Sites, o serviço Sites recebe pedido/manifesto autorizado, valida, produz, versiona, publica, testa e confirma URL. Performance pode propor página e consumir estado por interface homologada; **não** executar mudanças diretas em Worker, GitHub, banco ou DNS do cliente.

### BL03.18 — Página existente ou plataforma de terceiro
Se o destino é site externo, respeitar titularidade, consentimento e acesso técnico mínimo; sem acesso/contrato de produção, Performance apenas diagnostica e sugere melhorias, não publica landing nem instala pixels/códigos. Captura de leads só por integrações autorizadas.

### BL03.19 — Domínios, DNS, SSL e e-mails existentes
Titularidade e administração de DNS do cliente/registrador/Cloudflare devem ser preservadas conforme a política Sites; Imagine orienta e valida, mas não assume DNS do cliente por padrão. Nenhuma alteração pode interromper MX, e-mails, domínio, SSL ou subdomínios sem autorização competente e homologação.

### BL03.20 — Armazenamento e backups da página
Sites mantém versões, artefatos, rollback e políticas de backup próprias; Performance guarda somente manifesto e estado de publicação necessário, sem copiar repositórios e dados de páginas desnecessariamente. A recuperação de landing deve usar autoridade de Sites, não rollback autônomo da Performance.

### BL03.21 — GA4 e rastreamento sem sobreposição
Sites Imagine pode integrar GA4 opcionalmente, mas cliente consulta relatórios GA4 diretamente no Google, sem importação para o produto Sites. Qualquer aquisição de dados GA4 no Performance exige finalidade, produto/escopo e contrato próprios a aprovar; não inferir isso de integração Sites.

### BL03.22 — Manifesto versionado criativo-anúncio-landing
Objeto candidato liga ID de empresa, marca, briefing, peça, variante, URL e versão da landing, formulário, evento, público/objetivo, direitos de uso, consentimentos, aprovações e hash. Publicar mudança material invalida a aprovação afetada e exige nova validação.

### BL03.23 — Briefing, alegações e comprovação de ofertas
Cada promessa de produto, preço, desconto, comparação, depoimento ou resultado previsto exige origem verificável e aceite do anunciante. IA não inventa condição comercial, testemunho, evidência médica/financeira ou promessa garantida; categorias reguladas/restritas exigem revisão especializada.

### BL03.24 — Acervo e proveniência de materiais
Registrar origem de logo, texto, áudio, fotografia, vídeo e ilustração: fornecido pelo cliente, licenciado por terceiro, produzido pela Imagine, gerado por modelo de IA ou domínio público comprovado. Versão, autor/licenciante, restrição e período de licença devem ser preservados.

### BL03.25 — Direitos autorais, uso de imagem/voz e conteúdo sintético
Definir direitos de reprodução, alteração, adaptação, canal, território, prazo, exclusividade e distribuição para materiais de terceiros e IA. Uso de rosto, identidade, depoimento ou voz de pessoa depende de autorização aplicável; conteúdo sintético que induza a erro é bloqueado conforme políticas e lei.

### BL03.26 — Contratos de modelos de geração e dados
Homologar termos de ferramentas de geração (uso comercial, retenção de prompts, treinamento, sublicença, reutilização e confidencialidade) antes de enviar material cliente. Sem presumir exclusividade ou titularidade irrestrita de todos os outputs de IA; custos e limites a definir no BL-04.

### BL03.27 — Biblioteca multitenant de criativos
Artefatos de clientes são isolados por tenant e marca; não usar logo, vídeo ou imagem licenciada de empresa A em empresa B sem direito explícito. Templates e aprendizados agregados podem ser reutilizados apenas conforme propriedade/privacidade e prevenção de reidentificação.

### BL03.28 — Aprovação documentada por artefato e risco
Cliente revisa briefing, copy, criativo, landing e dados críticos conforme escopo; agência/Imagine verifica conformidade e direitos; publicar anúncio, landing e aumentar verba exigem autorizações distintas. **Jamais aprovação por silêncio ou aceite implícito**; versão/escopo invalidam aprovações antigas quando afetados.

### BL03.29 — Publicação, estado oficial e reversibilidade
Produzido, aprovado, enviado, publicado e veiculando são estados distintos. Para mídia, Google/Meta confirmam estado; para landing, Sites/terceiro confirma URL e integridade. Rollback não garante recuperação de gasto publicitário, dados, conversões ou aprendizagem de campanha.

### BL03.30 — Formulários, consentimentos e segurança
Campos mínimos, finalidade, aviso de privacidade, cookies/identificadores quando pertinentes, antispam, autenticação e tratamento de erro seguem dono do formulário; PII minimizada, protegida e enviada a destino elegível. Injeções de prompt em copy, páginas ou leads não modificam permissão nem acionam ferramenta.

### BL03.31 — Experimentos e variantes entre serviços
Campanhas e variantes de criativos/landing devem referenciar manifestação de hipótese, desenho experimental, amostra, verba autorizada e janela; atribuição e testes A/B cruzam PERF-06/07, sem usar site de cliente como laboratório sem consentimento comercial.

### BL03.32 — Mudanças após publicação e auditoria
Cliente pode solicitar revisão de conteúdo, URL, CTA, formulário, oferta ou direitos; determinar impacto e necessidade de novo aceite. Sites aplica alterações de página conforme contrato; Performance atualiza anúncio somente pelo conector/provedor autorizado, registrando vínculos e eventos afetados.

### BL03.33 — Portabilidade de criativos e dados no cancelamento
Distinguir entregáveis contratados, arquivos-fonte, versão final, licenças transferíveis, links, dados e registros históricos; definir formato, janela de entrega e direitos pós-cancelamento. Nem todo ativo de terceiro/IA pode ser transferido como propriedade exclusiva e nenhum conteúdo deve ser apagado sem política legal.

### BL03.34 — Cobrança separada de nova landing e extras
Performance não inclui automaticamente criação/hospedagem de nova landing ou serviço Sites; propostas adicionais requerem escopo e preço expressos, sem duplicar cobrança de site já contratado. Direitos de vídeo/áudio e novas variações dependem das franquias/add-ons de BL-01 e custos BL-04.

### BL03.35 — LGPD, autoridade e responsabilidades legais
Classificar controlador/operador e suboperadores, finalidades de lead/CRM/medição/ads, consentimentos/bases legais e pedidos dos titulares; revisar contrato entre Imagine/cliente/provedores e uso de direitos autorais, nome, imagem e voz, sem parecer de IA substituir revisão jurídica.

### BL03.36 — Governança de fornecedores e terceiros
Cadastro de fornecedores de CRM, bancos de imagem, provedores de geração, hospedagem e plataformas externas, com termos, segurança, direitos, versões, custos, aprovação contratual e transferência de dados. Não usar credenciais de terceiro sem autorização e não prometer integração não homologada.

### BL03.37 — Painel de vínculos, direitos e operações
Portal apresenta serviços contratados, destinos CRM, contas, sites/URLs, peças criativas, permissões, aprovações, status, restrições de uso e solicitações de transferência/cancelamento. Não revelar dados de outra empresa nem transformar botão de consulta em operação de publicação.

### BL03.38 — Homologação e prontidão do bloco
Associar os 13 P0 originais aos 38 requisitos e cenários BL03-V, mantendo-os abertos, com CT-PERF-05/07/08/20 candidatos, DC-07/08 ainda dependentes de regras comerciais e jurídicas específicas. Aprovação funcional não homologará fornecedores/APIs, licenças, retenção, custos ou código.

## 4. Deliberações comerciais/jurídicas BL03-D01 a BL03-D18 — propostas, NÃO aprovadas

| ID | Decisão de fronteira | Alternativa coerente com aprovações anteriores | Pontos ainda abertos |
| --- | --- | --- | --- |
| BL03-D01 | Forma de integração CRM Imagine | Opcional, com vínculo explícito a conta existente ou nova contratação independente | Permissões e conexão técnica pendentes |
| BL03-D02 | CRM Básico cortesia em Performance | Não ativar por contratar Performance; benefício de Sites não é transferido | Nenhum novo benefício aprovado |
| BL03-D03 | Central Mínima sem CRM | Manter modalidade de captação/consulta/exportação simples, sem se tornar CRM | Cotas, retenção e preço pendentes |
| BL03-D04 | Mudança do CRM ligado ao Performance | Mediante solicitação autorizada, corte seguro e tratamento de leads em trânsito | Gratuidade/tarifa Performance ainda a decidir |
| BL03-D05 | Mudança do vínculo Sites→CRM | Manter chamado e nova integração gratuita do serviço Sites; histórico migra pelo cliente via export/import | Não estender regra à Performance |
| BL03-D06 | CRM externo | Somente conector homologado por provedor/versão e acesso delegado | Preço, cobertura, SLA e API pendentes |
| BL03-D07 | Site já contratado | Sites mantém hospedagem, DNS, SSL, repositório, publicação e backup | Protocolo técnico com Performance pendente |
| BL03-D08 | Nova landing para campanhas | Não incluída implicitamente no Performance; adicional/contrato Sites específico | Escopo, preço e implantação pendentes |
| BL03-D09 | Landing externa | Propor melhorias sem alterar site de terceiro sem mandato e conector autorizado | Integração e direitos pendentes |
| BL03-D10 | Gestão DNS/registrador | Cliente administra DNS por padrão, Imagine orienta/valida conforme Sites | Homologação/autorizações específicas pendentes |
| BL03-D11 | GA4 dos Sites | Somente integração opcional, dados consultados na plataforma do Google pelo cliente | Uso próprio GA4 em Performance requer decisão separada |
| BL03-D12 | Origem dos materiais | Proveniência/versionamento obrigatórios para cliente, stock, IA e terceiros | Normas de licenças e fornecedores pendentes |
| BL03-D13 | Direitos pós-cancelamento | Definir entregáveis licenciados, transferência possível e janelas de exportação | Cláusulas, preços, retenções e direitos pendentes |
| BL03-D14 | Aprovação de peça/página/anúncio | Aceite expresso por artefato e escopo; verba aprovada separadamente | Alçadas técnicas e jurídico pendentes |
| BL03-D15 | Alterações de URL/CTA/versão | Nova avaliação de impacto, aceite e publicação no sistema dono | Contratos técnicos pendentes |
| BL03-D16 | Cobrança de criativo/vídeo adicional | Separar franquia, correção por erro e novo pedido comercial | Cotas e custos no BL-04 pendentes |
| BL03-D17 | Rastreio de leads e eventos | Origem/versão/destino oficiais, sem recaptura ou dupla contagem | Integração PERF-06/09 ainda a homologar |
| BL03-D18 | Garantias de IA sintética e publicidade | Comprovar alegações, imagem/voz, consentimento e categorias reguladas | Validação jurídica e modelos pendentes |

**Cuidado:** as regras do Sites e do CRM previamente aprovadas permanecem válidas nos respectivos produtos; a tabela distingue **reafirmação de decisão vigente** de **política nova a aprovar**. Nem todas as linhas são novidades comerciais — algumas apenas reafirmam limites do serviço.

## 5. Rastreabilidade dos 13 P0 originais (nenhum baixado)

| ID original | Texto da pendência preservado em síntese | Requisitos candidatos |
| --- | --- | --- |
| PERF-03/P0-GA-04 | Validação/aceite de anúncios, categorias restritas, política de marcas e LGPD | BL03.23–BL03.30 |
| PERF-05/P0-CRI-01 | Manifesto versionado, IDs, hashes, anúncio ↔ landing ↔ formulário ↔ evento ↔ aceite | BL03.22, BL03.28–29 |
| PERF-05/P0-CRI-02 | Comprovação de ofertas, direitos autorais/imagem/voz, IA sintética, publicidade enganosa e categorias reguladas | BL03.23–27, BL03.35 |
| PERF-05/P0-CRI-03 | Integração com Sites sem duplicar hospedagem/DNS/repositório/aprovação; homologação técnica Sites independente | BL03.17–21 |
| PERF-05/P0-CRI-04 | Autorizações por tenant/marca/recurso/ação, mudanças pós-aceite e publicação | BL03.28–29, BL03.32 |
| PERF-05/P0-CRI-05 | Formulários, rastreamento, PII/consentimentos, destino e proteção contra prompt injection | BL03.07, BL03.30, BL03.35 |
| PERF-05/P0-CRI-07 | Fronteiras comerciais Performance versus Sites/landing adicional, propriedade/licenças e terceiros | BL03.17, BL03.25–26, BL03.33–34 |
| PERF-06/P0-MED-05 | Contratos Sites/Portal/CRM externo/Imagine e retenção distinta Sites IN-127 e CRM | BL03.07–16, BL03.35 |
| PERF-09/P0-LEAD-04 | Contrato CRM Imagine (tenant, vínculo, credencial, permissão, plano, import/export, scoring, feedback) sem banco direto | BL03.03–04, BL03.08, BL03.12 |
| PERF-09/P0-LEAD-05 | CRMs externos: versões, quotas, mapeamento, garantias e exportações | BL03.09, BL03.36 |
| PERF-09/P0-LEAD-08 | Alteração de vínculo Performance, leads em trânsito, sem herdar gratuidade Sites | BL03.10–13 |
| PERF-09/P0-LEAD-10 | Suspensão/encerramento independente e continuidade, custos, armazenamento e destinação de registros | BL03.14–15, BL03.33 |
| PERF-12/P0-COM-05 | Preço/limites CRM externo, troca de vínculo e fronteira comercial Sites/CRM/Universidade | BL03.05–06, BL03.10–11, BL03.34 |

A descrição integral, histórico e IDs originais são conservados na [PERF-R02 — Anexo A](PERF-R02-ANEXO-TESTES-PENDENCIAS.md). A associação acima é **candidata** e sujeita a conferência semântica; o P0 técnico só pode ser baixado por decisão e evidência específica.

## 6. Fluxos ponta a ponta para harmonização

**Fluxo A — contratar Performance sem CRM:** cliente contrata Performance → autoriza conta publicitária → escolhe destino mínimo elegível → configura consentimentos/limites → dados de captação passam a ser recebidos futuramente somente após homologação → cliente visualiza leads confirmados e pode fornecer feedback simples.

**Fluxo B — integrar CRM Imagine existente:** cliente vincula conta CRM autorizada → valida tenant/permissões e mapeamento → evento captado passa por fila durável → confirmação do CRM determina estado de entrega → feedback comercial retorna à mensuração quando elegível. Não sobrescrever Lead Scoring nem criar conta CRM por inferência.

**Fluxo C — alterar destino CRM:** solicitação/chamado → identificação do responsável → confirmação do novo destino e data de corte → tratamento de leads em trânsito e reconciliação → desvinculação/revinculação auditadas → portabilidade histórica somente por operação separada e autorizada. A **gratuidade de nova integração Sites** não define a tarifa Performance.

**Fluxo D — criar landing em Sites:** briefing e manifesto Performance → contrato adequado de Sites/adicional → aceite documentado → serviço Sites gere código/versão/deploy/domínio de acordo com sua própria arquitetura homologada → verifica URL, SSL e formulário → Performance relaciona a URL/versão aos anúncios, com aprovação específica de mídia. Não alterar DNS do cliente diretamente.

**Fluxo E — criar e publicar anúncio/criativo:** briefing → comprovação de ofertas e direitos → produção e revisão → aceite de artefato/versionamento → aprovação de campanha e orçamento em separado → envio futuro por conector oficial e confirmação Google/Meta → medição e auditoria.

**Fluxo F — suspensão/cancelamento:** cada serviço preserva seu contrato → avisar efeitos sobre anúncios externos, acesso ao CRM e sites contratados → filas/PII/exportação/retentivas recebem tratamento específico → não apagar prova, lead nem artefato com base em suposição de fechamento.

## 7. Direitos, portabilidade e retenção: matriz de decisão

| Classe de ativo | Titularidade ou autoridade | Entrega/uso pós-contrato | Decisão que falta |
| --- | --- | --- | --- |
| Domínio/DNS | Cliente e serviço registrador/Cloudflare, conforme configuração | Cliente permanece titular, sem alteração não autorizada | Procedimentos e responsabilidade da conexão |
| Site publicado | Sites Imagine conforme contrato e materiais do cliente | URLs, arquivos e portabilidade conforme o serviço Sites | Termos finais e limites de versão/backup |
| Texto/imagem/vídeo fornecido pelo cliente | Cliente ou seu licenciante | Somente licença válida e finalidade autorizada | Licença, autorização de edição/IA e exclusividade |
| Criativo desenvolvido pela Imagine | Contrato de prestação e autoria/direitos aplicáveis | Escopo de licença/cessão acordado expressamente | Fonte editável, prazo, exclusividade e pagamento |
| Criativo com IA ou stock | Termos do modelo/licenciante/terceiro | Direitos sujeitos a restrições de uso e proveniência | Licenciamento, fornecedores, formação de custo |
| Evento de aquisição/lead | Fonte/controlador pertinente, política de tratamento | Exportação/retentiva conforme finalidade, vínculo e lei | Prazo próprio Performance, titulares e incidentes |
| Lead, contato e histórico CRM | CRM e controlador da conta | Export/import de autoatendimento do CRM e contrato específico | Integração e migração autorizada |
| Métrica e relatório Performance | Registros/eventos da operação Imagine com fontes distintas | Exportação versionada e restrições comerciais/PII | Formato, retenção e licença de uso de relatórios |

## 8. Cenários de validação documental BL03-V01 a BL03-V38 (nenhum executado)

1. **BL03-V01:** Cliente contrata Performance Meta sem criação automática de Sites ou CRM.
2. **BL03-V02:** Contrato Performance suspenso não desativa serviços Sites e CRM independentes.
3. **BL03-V03:** Cliente vincula CRM existente com permissão ou abre contratação nova sem ativar cortesia.
4. **BL03-V04:** Múltiplas campanhas usam mesmo CRM com origens separadas e sem mesclagem automática.
5. **BL03-V05:** Benefício CRM Básico vinculado a Sites não aparece automaticamente em assinatura Performance.
6. **BL03-V06:** Cliente sem CRM vê apenas a Central Mínima permitida, não funil CRM completo.
7. **BL03-V07:** Meta leadgen webhook sem payload recuperado não aparece como lead entregue e evento Site não duplica.
8. **BL03-V08:** Performance tenta gravar diretamente no banco CRM ou alterar Lead Scoring e é bloqueada.
9. **BL03-V09:** CRM externo sem suporte a confirmação de escrita não é marcado como entrega verificada.
10. **BL03-V10:** Alteração de CRM da Performance sem chamado/autorização é recusada, inclusive com leads em trânsito.
11. **BL03-V11:** Alteração de vínculo Site→CRM segue chamado e integração gratuita do Sites; transferência histórica pelo cliente.
12. **BL03-V12:** Cliente pode usar export/import do CRM conforme plano, sem migração automática entre contas.
13. **BL03-V13:** HTTP 202 do CRM não comprova lead persistido; resultado inconclusivo exige reconciliação.
14. **BL03-V14:** CRM suspenso não causa exclusão silenciosa da fila nem bloqueia Sites contratados.
15. **BL03-V15:** PERF-09 não herda automaticamente 3+15 dias de IN-127 Sites ou retenção CRM.
16. **BL03-V16:** Venda confirmada no CRM é distinguida de atribuições duplas Google/Meta.
17. **BL03-V17:** Performance tenta publicar diretamente em Cloudflare/GitHub do Sites e é bloqueada.
18. **BL03-V18:** Landing de terceiro sem mandato permanece diagnóstico, não deploy.
19. **BL03-V19:** Alterar MX/domínio/DNS sem titular/autorização é recusado e e-mail preservado.
20. **BL03-V20:** Rollback Sites não é acionado como se pudesse recuperar orçamento/anúncios.
21. **BL03-V21:** GA4 integrado ao Sites não importa seus relatórios para o Sites; consulta Performance própria pendente.
22. **BL03-V22:** Mudar CTA/URL/formulário altera manifesto e invalida aprovação material correspondente.
23. **BL03-V23:** Campanha com oferta não comprovada não é liberada para publicidade.
24. **BL03-V24:** Imagem/licença expirada é bloqueada em nova peça e proveniência preservada.
25. **BL03-V25:** Uso de voz ou foto de pessoa não autorizada é bloqueado mesmo quando IA gerou variação.
26. **BL03-V26:** Fornecedor IA sem termos comerciais/privacidade adequados não recebe material de cliente.
27. **BL03-V27:** Artefato de empresa A não aparece em biblioteca/peça da empresa B.
28. **BL03-V28:** Aprovação de copy não autoriza orçamento, e aprovação não ocorre por silêncio.
29. **BL03-V29:** Anúncio enviado e pendente de revisão não é marcado como veiculando.
30. **BL03-V30:** Formulário de landing com texto malicioso não aciona ferramenta nem exfiltra PII.
31. **BL03-V31:** Teste A/B sem orçamento autorizado não publica variante no provedor.
32. **BL03-V32:** Cliente muda texto após aceite; nova versão exige atualização controlada e rastreável.
33. **BL03-V33:** Cancelamento entrega apenas direitos/licenças contratados sem transferir direitos inexistentes.
34. **BL03-V34:** Nova landing não é incluída gratuitamente pelo Performance por inferência.
35. **BL03-V35:** Solicitação LGPD do titular não é ignorada por causa do vínculo entre ferramentas.
36. **BL03-V36:** Conector terceiro sem homologação/escopo suficiente fica indisponível.
37. **BL03-V37:** Painel mostra estados e vínculos apenas da empresa e usuário autorizados.
38. **BL03-V38:** Aprovação BL-03 não ativa conectores CRM, Sites, IA, anúncios, faturamento nem programação.

## 9. Registro da aprovação funcional, dependências e próxima etapa

- **BL-01** aprovou estrutura de três pacotes com cotas/adicionais, mas deixou quatro P0 e preços/franquias abertos.
- **BL-02** aprovou requisitos funcionais de cobrança e segurança financeira, mantendo nove P0 e 15 decisões BL02-D01–D15 separadas.
- **BL-03 aprovado funcionalmente em 03/11/2026**: 38 requisitos BL03.01–BL03.38 e 38 cenários BL03-V documentados, **não executados**. As 18 deliberações BL03-D01–D18 continuam propostas, **13 P0 originais permanecem abertos**, e DC-07 e DC-08 ainda dependem de decisões jurídicas/comerciais específicas.
- **BL-04** deverá dimensionar custos de produção multimodal, armazenamento/PII, CRMs externos, suporte, retenção, integrações, licença e capacidade para 100 a 700 clientes; não fixar valores antecipadamente.
- **BL-05 e BL-06** tratarão permissões, LGPD, contratos de dados e estados de lead/CRM; **BL-07** os agentes, comandos, incidentes e auditoria. **BL-08** fará a reconciliação final de P0, divergências D-01 a D-14 e gates HT-R01 não homologados.

**Próxima etapa autorizada exclusivamente para planejamento documental:** BL-04 — Atendimento, Custos, Capacidade e Continuidade Comercial. A aprovação funcional de BL-03 não equivale a aprovação de fornecedores, planos/valores comerciais, prazos de retenção ou contratos técnicos executáveis.

> **BL-03 v0.1 — APROVADO FUNCIONALMENTE em 03/11/2026**, preservando 13 P0, 18 deliberações candidatas e pendências jurídicas, comerciais, de privacidade, licenças e fornecedores. Nenhuma programação, conexão de serviços, alteração de domínio/DNS, geração/produção real para terceiro, publicação, coleta de leads ou cobrança autorizada.
