# FAST-PERF F1-78 — painel de incidentes offline

Data: 09/11/2026. Branch filha do PR #176. Objetivo: tornar inspecionável a F1-77 por pessoas não técnicas, **sem** simular que ela está pronta para clientes.

Executar apenas em laboratório:

```bash
APP_ENV=testing PERF_F75_SIGNED_WITNESS_LAB=1 PERF_F76_RECONCILIATION_LAB=1 PERF_F77_INCIDENT_LAB=1 PERF_F78_HTML_PREVIEW_LAB=1 \\
 php contracts/demo/f78-render-incident-preview.php > incident-preview.html
```

O HTML funciona sem conexão, scripts, formulários, imagens ou URLs externas. Possui CSP restritiva, marcação de aviso, vista responsiva e condições de liberação; status e motivo vêm do DTO F77, não de valores comerciais de clientes. A regra testada é de paridade e NO-GO.

Antes de disponibilizar o produto: contrato Portal–Performance autenticado, identidade humana, trilhas de aprovação, revisão de segurança, antirollback monotônico fora de backups, reconciliação de APIs Google/Meta, LGPD, orçamentos, contabilidade e VPS homologados.

**NO-GO.** `main`, deploy, VPS, integrações reais, dados de clientes, cobranças e gastos intocados.
