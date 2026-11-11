# FAST-PERF F1-188 — Portal F62/F63 após retirada e reativação de campanha, sem divulgar aprovação antiga

**Base F186/PR #294 (CI verde). PR filho de base aprovada, independente da prova adversarial F187 ainda pendente.**

As etapas F184–F186 corrigiram as consultas PostgreSQL e as regras de gravação para esconder vínculos e aprovações de uma gestão antiga após a sequência `managed=true→false→true`. A F188 leva a verificação **até o consumidor PHP F62 e o renderizador HTML F63 reais da candidata atual do Imagine Portal**, SHA `1427a486434f653e6873ea6f3b37294d781aa316`.

O CI executa as migrations reais de laboratório F100, os testes de contrato e HTML já aprovados, a correção F184, a geração F185 e as regras F186. Ao concluir F186, a empresa A tem campanha antiga tombada e a empresa B tem uma campanha antiga tombada **e** uma campanha nova em geração 1 com uma revisão `awaiting_approval`.

O teste F188 verifica diretamente nas views F93/F97 que a campanha B antiga e sua revisão permanecem ocultas; o leitor SQL F100 devolve **somente** a campanha nova e revisão `pending_human_demo`, sem `journaled` ou aprovação real. Em seguida o consumidor original F62 aceita os dois DTOs A/B e continua negando `hasPortalIdentity`, `canPublishAds`, `canBill` e `isRealTime`. O renderer F63 recebe o DTO B real e gera HTML offline, sem transformação de ID, onde só aparecem campanha e revisão novas, nunca os identificadores históricos. Os testes verificam número de linhas, ausência de links/forms/scripts, CSP sem rede e o aviso de demonstração.

Um HTML de empresa B **com dados inteiramente fictícios** é anexado à execução GitHub Actions por um dia para inspeção visual. O arquivo não contém conta real nem identidade Portal, e não há deploy ou rota HTTP.

**Limitações:** F100/F62/F63 é um contrato de demonstração, não IAM nem S2S. O banco e o histórico F186 permanecem sob a mesma administração e não são WORM; F187 apresenta um teste adversarial separado, ainda não aprovado, sobre essa lacuna. A testemunha externa de revogação/antirollback e o fluxo real de reautorização de gestão futura precisam de desenho independente DBA/AppSec antes de produção. A F188 não promove o branch, não oferece consentimento OAuth nem acesso a anunciantes.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Sem merge dev/main, VPS, deploy, contas reais, anúncios, pagamentos, cobrança ou despesas.**
