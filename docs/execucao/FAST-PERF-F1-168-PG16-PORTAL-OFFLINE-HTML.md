# FAST-PERF F1-168 — demonstração HTML de revisões reais do banco de laboratório

**Base F167, PR #275. PR draft; CI PostgreSQL16 descartável; produção NO-GO.**

A primeira tela offline F55 tinha uma fonte fictícia em memória. A F168 aproxima a demonstração do sistema persistente: `contracts/demo/f168-render-pg16-offline.php` abre PostgreSQL16 usando uma das duas roles fictícias do CI e chama o leitor F166 com `security_invoker=true` e RLS. O documento HTML resultante é renderizado pelo template `f168-pg16-portal-offline-template.html` com o **snapshot do banco**, não com registros de campanha inventados diretamente no HTML.

A tela possui resumo de revisões visíveis, contagem agregada de históricos em quarentena, pendências fictícias, filtros de canal Google Ads/Meta e de quatro estados de revisão, além de histórico resumido de inclusão/retirada/reativação de campanhas. Todos os dados são renderizados via `textContent`, sem `innerHTML` ou ação que atualize banco ou anunciante. A estrutura HTML é responsiva, com controles `label` e tabela acessível.

Segurança da demonstração: CSP bloqueia fetch/conexões, envio de formulários, mídia, fontes e imagens externas; JSON é inserido com `JSON_HEX_TAG/AMP/APOS/QUOT`, impedindo fechamento malicioso de `<script>`. O único script de UI apenas filtra o snapshot local. O CLI recusa token de Meta/Google Ads, `DATABASE_URL`, usuários SQL desconhecidos e argumentos de request. O tenant vem exclusivamente da role SQL sintética, não de parâmetros do usuário.

O CI configura F162 a F167, cria duas empresas fictícias e suas campanhas, retira e reativa uma campanha de A, cria revisão nova do epoch atual e verifica que títulos antigos **não reaparecem**, enquanto as revisões atuais A e B permanecem isoladas. Valida metadados NO-GO, controles da interface, ausência de transporte, formato HTML e sintaxe do JavaScript com Node sem `npm install`. O runner pode gravar HTML apenas em `RUNNER_TEMP`; não publica site ou artefato em terceiros.

**Limites:** o HTML não é uma interface implantada no Portal real. Os papéis A/B não são autenticação de pessoas, não há sessão Portal, MFA, S2S, consentimento OAuth, orçamento, integração Google/Meta Ads, cobrança ou dados de clientes. A geração do escopo é apenas no mesmo PostgreSQL, sem antirollback externo, AppSec/DBA independente ou migração operacional.

**P0/HT-R01/SEC-R03/T3 seguem NO-GO. Sem merge main, VPS, deploy, clientes, anúncios ou despesas.**
