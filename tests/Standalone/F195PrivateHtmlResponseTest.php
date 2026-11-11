<?php
declare(strict_types=1);

/**
 * F195 CI-only: HTTP-shaped private response mapping. This does not launch
 * a web server or grant IAM. End-to-end service revocation is tested in F194.
 */
require_once dirname(__DIR__,2).'/contracts/lab/php/SyntheticServiceCheckedHtmlRetrieval.php';
require_once dirname(__DIR__,2).'/contracts/lab/php/SyntheticPrivateHtmlResponse.php';

use App\Performance\Security\SyntheticServiceCheckedHtmlRetrieval as Retrieval;
use App\Performance\Security\SyntheticPrivateHtmlResponse as Response;

$n=0;
$assert=static function(bool $ok,string $reason)use(&$n):void{
    ++$n;
    if(!$ok)throw new RuntimeException("F195 FAIL: ".$reason);
};
$required='<html lang="pt-BR"><head><meta charset="utf-8"><meta '
    .'http-equiv="Content-Security-Policy" content="default-src '
    ."'none'; script-src 'none'; connect-src 'none'; form-action 'none'">"
    .'</head><body><h1>DEMONSTRAÇÃO NÃO HOMOLOGADA</h1>'
    .str_repeat('<p>Somente demonstração sintética sem anúncios ou cobrança.</p>',42)
    .'</body></html>';
$allowed=[
    'schema_version'=>Retrieval::SCHEMA,
    'status'=>'offline_html_readonly_preview_only',
    'content_type'=>'text/html; charset=utf-8',
    'html'=>$required,
    'synthetic_only'=>true,'read_only'=>true,
    'portal_identity_authenticated'=>false,
    'authorization_eligible'=>false,
    'can_publish_ads'=>false,'can_bill'=>false,
    'ready_for_production'=>false,
];
$ok=Response::fromCheckedRetrieval($allowed);
$assert($ok['status_code']===200 && $ok['body']===$required,
    'valid synthetic F194 result maps into an exact HTML response');
$assert($ok['headers']['Content-Type']==='text/html; charset=utf-8',
    'only approved synthetic HTML is served with HTML content type');
$assert(str_contains($ok['headers']['Cache-Control'],'no-store')
    &&str_contains($ok['headers']['Cache-Control'],'private')
    &&$ok['headers']['Pragma']==='no-cache'
    &&$ok['headers']['Expires']==='0',
    'browser and intermediary caches must not retain the response');
$assert(str_contains($ok['headers']['Content-Security-Policy'],
    "frame-ancestors 'none'")
    &&str_contains($ok['headers']['Content-Security-Policy'],
    "default-src 'none'")
    &&$ok['headers']['X-Content-Type-Options']==='nosniff'
    &&$ok['headers']['Referrer-Policy']==='no-referrer',
    'deny scripts, origins, framing, MIME sniffing and referrers');
$assert($ok['ready_for_production']===false,
    'success remains a lab-only response, not real authorization');

foreach([
    ['status'=>'service_revoked_or_sql_changed'],
    ['schema_version'=>'untrusted'],
    ['portal_identity_authenticated'=>true],
    ['authorization_eligible'=>true],
    ['can_publish_ads'=>true],
    ['can_bill'=>true],
    ['ready_for_production'=>true],
    ['read_only'=>false],
    ['synthetic_only'=>false],
    ['content_type'=>'application/octet-stream'],
    ['html'=>'<script>alert(1)</script>'],
    ['html'=>str_replace('DEMONSTRAÇÃO NÃO HOMOLOGADA','PREVIEW',$required)],
    ['html'=>$required.'<a href="https://example.org">out</a>'],
] as $mutation){
    $bad=Response::fromCheckedRetrieval(array_replace($allowed,$mutation));
    $assert($bad['status_code']===403
        &&$bad['body']==='Preview unavailable'
        &&$bad['headers']['Content-Type']==='text/plain; charset=utf-8'
        &&str_contains($bad['headers']['Cache-Control'],'no-store'),
        'every invalid/revoked or unsafe payload fails closed');
    $assert(!str_contains(json_encode($bad,JSON_THROW_ON_ERROR),'<html'),
        'denied responses never leak HTML');
}
$bad=Response::fromCheckedRetrieval([
    'status'=>'service_revoked_or_sql_changed',
    'artifact_path'=>'/tmp/secret/tenant-a.html',
    'tenant_id'=>'a-secret-tenant',
]);
$assert($bad['status_code']===403
    &&!str_contains(json_encode($bad,JSON_THROW_ON_ERROR),'tenant-a')
    &&!str_contains(json_encode($bad,JSON_THROW_ON_ERROR),'a-secret'),
    'denied responses do not leak internal file paths or tenant details');
$assert(array_keys($bad)===[
    'status_code','headers','body','synthetic_only','ready_for_production'
], 'response contract exposes only enumerated safe fields');
echo "F195 private response boundary: {$n} PASS / 0 FAIL; NO HTTP ROUTE / NO-GO\n";
