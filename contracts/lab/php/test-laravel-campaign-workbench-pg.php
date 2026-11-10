<?php

declare(strict_types=1);

/**
 * F1-28 application-class workbench in disposable PG16. No HTTP endpoint.
 */
require_once dirname(__DIR__,3).'/vendor/autoload.php';

use App\Performance\ReadSide\SyntheticCampaignWorkbenchReader;
use App\Performance\ReadSide\SyntheticWorkbenchScope;

if (PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('RUN_PERF_F22_PG_LAB')!=='1'
    || getenv('PERF_F28_APP_READ_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='perf_test_campaign_registry') {
    throw new RuntimeException('F28 refuses non-disposable PostgreSQL.');
}
$connection=static fn(string $role,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=perf_test_campaign_registry;connect_timeout=3',
    $role,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]
);
$admin=$connection('postgres','ci-f22-db-only');
$reader=$connection('perf_f28_app_reader','ci-f28-app-read-only');
$app=require dirname(__DIR__,3).'/bootstrap/app.php';
$workbench=$app->makeWith(SyntheticCampaignWorkbenchReader::class,[
    'db'=>$reader
]);
$scope=SyntheticWorkbenchScope::forSyntheticLabOnly();
$n=0;
$check=static function(string $label,mixed $got,mixed $expected) use (&$n):void {
    if($got!==$expected) {
        throw new RuntimeException(
            'FAIL F28 '.$label.' got '.var_export($got,true)
            .' expected '.var_export($expected,true)
        );
    }
    ++$n;
    echo "PASS F28 $n $label\n";
};
$check('01 Laravel application resolved internal read class',
    $workbench instanceof SyntheticCampaignWorkbenchReader,true);
$check('02 scope can only be created in explicit synthetic lab',
    $scope instanceof SyntheticWorkbenchScope,true);
$check('03 scope binds known internal tenant id',
    $scope?->tenantId,'11111111-1111-4111-8111-111111111111');
$check('04 organization scope remains only demo A',
    $scope?->organizationRef,'org-demo-a');
$check('05 service scope remains only mock service',
    $scope?->serviceRef,'svc-demo-a');
$check('06 starting reader context sees zero RLS campaigns',
    (int)$reader->query(
        'SELECT count(*) FROM performance_managed_campaigns'
    )->fetchColumn(),0);
$check('07 starting reader context sees zero RLS journal records',
    (int)$reader->query(
        'SELECT count(*) FROM performance_intent_review_journal'
    )->fetchColumn(),0);
$payload=$workbench->read($scope);
if(!is_array($payload)){
    throw new RuntimeException('F28 failed to produce internal synthetic read model.');
}
$check('08 versioned internal read model',
    $payload['schema_version'],'perf.internal.workbench.synthetic.v1');
$check('09 source explicitly synthetic PG',
    $payload['source'],'synthetic_postgresql_lab_only');
$check('10 organization and read scope still bound',
    $payload['organization_ref'],'org-demo-a');
$check('11 tenant ref is non-UUID display ref',
    $payload['tenant_ref'],'perf-demo-a');
$check('12 service remains mock A',
    $payload['service_ref'],'svc-demo-a');
$check('13 workbench cannot be used to dispatch',
    $payload['dispatch_enabled'],false);
$check('14 workbench is read-only',
    $payload['read_only'],true);
$check('15 service is not a commercial contract',
    $payload['contracted'],false);
$check('16 price/capacity not homologated',
    $payload['commercial'],'not_homologated');
$check('17 two managed campaigns from SQL',
    $payload['counts']['managed_campaigns'],2);
$check('18 paused campaign still occupies managed slot',
    $payload['counts']['paused_campaigns'],1);
$check('19 all pending original requests have been resolved in labs',
    $payload['counts']['approvals_pending'],0);
$check('20 four internal approvals recorded with journal',
    $payload['counts']['approvals_approved_internal'],4);
$check('21 two rejected decisions recorded with journal',
    $payload['counts']['approvals_rejected'],2);
$check('22 two safe campaign cards',
    count($payload['campaigns']),2);
$check('23 paused campaign status preserved',
    $payload['campaigns'][0]['state'],'paused_demo');
$check('24 safe campaign channel name',
    $payload['campaigns'][0]['channel'],'google_ads');
$check('25 display label explicitly marked example',
    $payload['campaigns'][1]['title'],'F23 test draft (exemplo)');
$check('26 exactly six same-service requests',
    count($payload['requests']),6);
$check('27 every final request is journaled',
    count(array_filter($payload['requests'],static fn($r):bool=>$r['journaled']===true)),6);
$check('28 no final request silently shown as live ad',
    count(array_filter($payload['requests'],
        static fn($r):bool=>str_contains($r['status'],'execution_live'))),0);
$check('29 repeat read returns identical view',
    $workbench->read($scope),$payload);
$check('30 read transaction ended after every query',
    $reader->inTransaction(),false);
$check('31 transaction-local GUC does not leak to subsequent SQL',
    (int)$reader->query('SELECT count(*) FROM performance_managed_campaigns')
        ->fetchColumn(),0);
$json=json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$check('32 none of the internal customer UUIDs are in response',
    str_contains($json,'11111111-1111-4111-8111-111111111111'),false);
$check('33 no reviewer/proposer identities returned',
    str_contains($json,'actor-demo-')
     || str_contains($json,'reviewer-demo-'),false);
$check('34 no email, media budget or provider accounts returned',
    str_contains($json,'@')
     || str_contains($json,'access_token')
     || str_contains($json,'media_spend')
     || str_contains($json,'provider_account_id'),false);
$check('35 reader has no direct UPDATE privilege on campaigns',
    (bool)$admin->query(
        "SELECT has_table_privilege(
          'perf_f28_app_reader','performance_managed_campaigns','UPDATE')"
    )->fetchColumn(),false);
$check('36 reader has no direct UPDATE privilege on intents',
    (bool)$admin->query(
        "SELECT has_table_privilege(
          'perf_f28_app_reader','performance_campaign_intents','UPDATE')"
    )->fetchColumn(),false);
$check('37 reader has no INSERT privilege on review journal',
    (bool)$admin->query(
        "SELECT has_table_privilege(
          'perf_f28_app_reader','performance_intent_review_journal','INSERT')"
    )->fetchColumn(),false);
$check('38 reader cannot SELECT identity memberships',
    (bool)$admin->query(
        "SELECT has_table_privilege(
          'perf_f28_app_reader','operational_memberships','SELECT')"
    )->fetchColumn(),false);

putenv('PERF_F28_APP_READ_LAB=0');
$check('39 removing lab flag makes existing reader deny',
    $workbench->read($scope),null);
$check('40 scope factory also denies without lab flag',
    SyntheticWorkbenchScope::forSyntheticLabOnly(),null);
putenv('PERF_F28_APP_READ_LAB=1');
$check('41 scope restored only in offline lab',
    SyntheticWorkbenchScope::forSyntheticLabOnly() instanceof SyntheticWorkbenchScope,
    true);
$adminBench=$app->makeWith(SyntheticCampaignWorkbenchReader::class,[
    'db'=>$admin
]);
$check('42 privileged DB account is refused by internal read class',
    $adminBench->read($scope),null);

// A source link in inactive/pending state must invalidate the whole view,
// not leave a partly rendered or cached set of campaigns.
$admin->exec(
    "UPDATE portal_service_links SET link_status='pending'
     WHERE tenant_id='11111111-1111-4111-8111-111111111111'
       AND portal_service_ref='svc-demo-a'"
);
$check('43 a pending Portal service denies the entire workbench',
    $workbench->read($scope),null);
$check('44 no transaction leaked on service denial',
    $reader->inTransaction(),false);
$admin->exec(
    "UPDATE portal_service_links SET link_status='active_demo'
     WHERE tenant_id='11111111-1111-4111-8111-111111111111'
       AND portal_service_ref='svc-demo-a'"
);
$admin->exec(
    "UPDATE portal_service_links SET source_revision='demo-revision-0'
     WHERE tenant_id='11111111-1111-4111-8111-111111111111'
       AND portal_service_ref='svc-demo-a'"
);
$check('45 wrong origin revision denies entire workbench',
    $workbench->read($scope),null);
$admin->exec(
    "UPDATE portal_service_links SET source_revision='demo-revision-1'
     WHERE tenant_id='11111111-1111-4111-8111-111111111111'
       AND portal_service_ref='svc-demo-a'"
);
$check('46 restoring mock service link reenables safe read model',
    $workbench->read($scope)['counts']['managed_campaigns']??null,2);

// A lost journal privilege cannot silently present unjournaled approvals.
$admin->exec(
    'REVOKE SELECT ON performance_intent_review_journal FROM perf_f28_app_reader'
);
$check('47 missing review evidence always fails closed',
    $workbench->read($scope),null);
$check('48 no transaction leaked after source privilege failure',
    $reader->inTransaction(),false);
$admin->exec(
    'GRANT SELECT ON performance_intent_review_journal TO perf_f28_app_reader'
);
$check('49 read model recovers after journal read privilege returns',
    $workbench->read($scope)['counts']['approvals_approved_internal']??null,4);
$check('50 no provider operations were executed by read model',
    (int)$admin->query(
        "SELECT count(*) FROM performance_campaign_intents
         WHERE dispatch_state<>'disabled'"
    )->fetchColumn(),0);

echo "PASS F28 $n real Laravel-class workbench/tenant-RLS/journal checks\n";
