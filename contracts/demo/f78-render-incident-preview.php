<?php
declare(strict_types=1);

/**
 * F1-78: static, offline, strictly synthetic incident preview.
 * There are no client accounts, logins, network requests or active actions.
 */
if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F75_SIGNED_WITNESS_LAB')!=='1'
    || getenv('PERF_F76_RECONCILIATION_LAB')!=='1'
    || getenv('PERF_F77_INCIDENT_LAB')!=='1'
    || getenv('PERF_F78_HTML_PREVIEW_LAB')!=='1') {
    fwrite(STDERR, "F78 HTML is available exclusively as an offline testing fixture\n");
    exit(2);
}

ob_start();
try {
    require __DIR__.'/f77-incident-preview.php';
    $json = ob_get_clean();
    $report = json_decode((string)$json,true,8,JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "F78 synthetic incident projection failed\n");
    exit(2);
}
if (!is_array($report)
    || ($report['schema_version']??null)!=='perf.recovery.incident.synthetic.v1'
    || ($report['simulation_only']??null)!==true
    || ($report['authorization_eligible']??null)!==false
    || ($report['dispatch_enabled']??null)!==false
    || ($report['billing_enabled']??null)!==false
    || ($report['ready_for_production']??null)!==false
    || ($report['human_review_required']??null)!==true) {
    fwrite(STDERR, "F78 denies unsafe or malformed incident snapshot\n");
    exit(2);
}
$labels=[
    'invalid_evidence_quarantine'=>['Evidência inválida','Quarentena de segurança'],
    'security_quarantine'=>['Inconsistência detectada','Quarentena de segurança'],
    'manual_reconciliation_required'=>['Reconciliação pendente','Revisão manual necessária'],
    'manual_governance_required'=>['Revisão de governança','Sem autorização operacional'],
    'lab_disabled'=>['Laboratório desativado','Visualização indisponível'],
];
$status=$report['status']??null;
if(!is_string($status) || !isset($labels[$status])) {
    fwrite(STDERR,"F78 cannot display unknown status\n");
    exit(2);
}
$e=static fn(string $text):string=>htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$title=$e($labels[$status][0]);
$subtitle=$e($labels[$status][1]);
$reason=$e((string)($report['reason']??''));
$statusCode=$e($status);
echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'">
<title>Imagine Performance AI — Incidentes demonstrativos</title>
<style>
:root{color-scheme:light;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#152438;background:#f4f7fb}
*{box-sizing:border-box}body{margin:0}main{max-width:950px;margin:auto;padding:30px 20px 55px}
header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid #cbd5e1;padding-bottom:22px}
.brand{font-size:19px;font-weight:800;letter-spacing:-.3px}.brand span{color:#2563eb}
.eyebrow{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#475569;font-weight:700}
h1{font-size:clamp(27px,5vw,39px);line-height:1.1;margin:27px 0 11px}p{line-height:1.6}
.notice{background:#fff7e8;border:1px solid #e7bb75;border-radius:12px;padding:14px 18px;margin:22px 0;font-weight:700}
.grid{display:grid;grid-template-columns:1.45fr 1fr;gap:17px}
.panel{background:#fff;border:1px solid #d7e0eb;border-radius:14px;padding:22px;box-shadow:0 2px 9px #1524380b}
.panel h2{font-size:17px;margin:0 0 14px}.status{font-size:24px;font-weight:800;margin:8px 0}
.meta{font-size:13px;color:#475569;overflow-wrap:anywhere}.reason{font-family:ui-monospace,monospace;font-size:13px;background:#f1f5f9;padding:10px;border-radius:6px;overflow-wrap:anywhere}
ul{padding-left:19px;line-height:1.85;margin:8px 0}.blocked{font-weight:700}
footer{margin-top:29px;font-size:13px;color:#475569}
@media(max-width:650px){main{padding:18px 14px 45px}.grid{grid-template-columns:1fr}header{align-items:flex-start}}
</style>
</head>
<body>
<main>
<header><div class="brand">IMAGINE <span>Performance AI</span></div><div class="eyebrow">Laboratório • Sem conexão com anúncios</div></header>
<h1>Visão de incidentes</h1>
<p>Visualização local produzida exclusivamente a partir de dados sintéticos. Nenhuma campanha foi publicada e nenhum fornecedor foi consultado.</p>
<div class="notice" role="note">DEMONSTRAÇÃO — NÃO HOMOLOGADO — EXECUÇÃO, COBRANÇA E PRODUÇÃO BLOQUEADAS</div>
<div class="grid">
  <section class="panel" aria-labelledby="state"><h2 id="state">Situação simulada</h2>
    <p class="status">{$title}</p>
    <p>{$subtitle}</p>
    <p class="meta">Código técnico: {$statusCode}</p>
    <div class="reason">{$reason}</div>
  </section>
  <section class="panel" aria-labelledby="gates"><h2 id="gates">Condições de liberação</h2>
    <ul>
      <li class="blocked">Publicidade: bloqueada</li>
      <li class="blocked">Cobrança: bloqueada</li>
      <li class="blocked">Produção: não autorizada</li>
      <li>Revisão humana: obrigatória</li>
      <li>Revisão AppSec/DBA: pendente</li>
    </ul>
  </section>
</div>
<footer>Imagine Performance AI • Esquema perf.recovery.incident.synthetic.v1 • F1-78 • Não representa dados de clientes nem o estado real de contas Google/Meta.</footer>
</main>
</body>
</html>
HTML;
