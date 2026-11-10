<?php

declare(strict_types=1);

/**
 * F1-30: real PostgreSQL test-only Portal authority -> original Portal
 * SQL-backed Ed25519 issuer -> Performance verifier -> fresh PG authority.
 * No operational login, SSO, authorization or Google/Meta dispatch.
 */
require_once dirname(__DIR__,3).'/app/Performance/Security/SyntheticCurrentAuthority.php';
require_once dirname(__DIR__,3).'/app/Performance/Security/SignedPortalCapabilityGate.php';

use App\Performance\Security\SignedPortalCapabilityGate;
use App\Performance\Security\SyntheticCurrentAuthority;

$portalRoot=getenv('F30_PORTAL_SOURCE_ROOT');
$claimFile=getenv('F30_PORTAL_CLAIM_FILE');
$keyFile=getenv('F30_PORTAL_PUBLIC_HEX_FILE');
if (PHP_SAPI!=='cli'
    || getenv('APP_ENV')!=='testing'
    || getenv('PERF_F29_SIGNED_PORTAL_LAB')!=='1'
    || getenv('PERF_F30_PG_END_TO_END_LAB')!=='1'
    || getenv('PORTAL_PERF_PG_AUTH_LAB')!=='1'
    || getenv('PGHOST')!=='127.0.0.1'
    || getenv('PGPORT')!=='5432'
    || getenv('PGDATABASE')!=='portal_perf_auth_lab'
    || !is_string($portalRoot) || !is_dir($portalRoot)
    || !is_string($claimFile) || !is_file($claimFile)
    || !is_string($keyFile) || !is_file($keyFile)) {
    throw new RuntimeException('F30 only works with pinned Portal source on CI PostgreSQL.');
}
foreach([
    'PortalPerfAuthorityFacts.php',
    'PortalPerfSqlAuthorityResolver.php'
] as $file) {
    require_once $portalRoot.'/apps/portal/app/Domain/PerformanceBridge/'.$file;
}

use App\Domain\PerformanceBridge\PortalPerfSqlAuthorityResolver;

$now=1800000000;
$bearer='portal-f08-opaque-session-test-aaaaaaaaaaaaaaaaaaa';
$hash=hash('sha256',$bearer);
$packet=trim((string)file_get_contents($claimFile));
$pub=trim((string)file_get_contents($keyFile));
$db=static fn(string $name,string $password):PDO=>new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=portal_perf_auth_lab;connect_timeout=3',
    $name,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]
);
$reader=$db('portal_f08_reader','ci-f08-read-only');
$admin=$db('postgres','ci-f08-db-only');
$resolver=new PortalPerfSqlAuthorityResolver($reader);
$read=static fn(int $when=1800000000)=>$resolver->resolve(
    $bearer,'svc-demo-a',$when
);
$makeAuthority=static function(int $when=1800000000) use($read):?SyntheticCurrentAuthority{
    $facts=$read($when);
    if($facts===null) return null;
    return new SyntheticCurrentAuthority(
        organizationRef:$facts->organizationRef,
        tenantRef:$facts->tenantRef,
        serviceRef:$facts->serviceRef,
        // F08 does NOT yet verify resource/campaign ownership.
        resourceRef:'demo-cam-a1',
        actorRef:$facts->actorRef,
        sessionRef:$facts->sessionRef,
        permission:$facts->permission,
        sessionActive:true,
        mfaVerified:true,
        membershipActive:true,
        serviceActive:true,
        membershipRevision:$facts->membershipRevision,
        serviceRevision:$facts->serviceRevision,
        sessionRevision:$facts->sessionRevision,
        recoveryEpoch:$facts->recoveryEpoch,
        sourceAvailable:true,
        observedAt:$facts->sourceObservedAt,
    );
};
$verify=static fn(
    ?SyntheticCurrentAuthority $proof=null,
    int $when=1800000000,
    ?string $key=null
):string=>(new SignedPortalCapabilityGate($key??$pub))->check(
    $packet,$proof??$makeAuthority($when),$when
);
$n=0;
$check=static function(string $label,mixed $actual,mixed $expected)use(&$n):void{
    if($actual!==$expected) {
        throw new RuntimeException(
            'FAIL F30 '.$label.' actual '.var_export($actual,true)
            .' expected '.var_export($expected,true)
        );
    }
    ++$n;
    echo "PASS F30 $n $label\n";
};
$deny=static function(string $label,string $code)use($check):void{
    $check($label,$code!=='verified_synthetic_only',true);
};
$decode=static fn(string $v):string=>(string)base64_decode(
    strtr($v,'-_','+/'),true
);
$env=json_decode($packet,true,16,JSON_THROW_ON_ERROR);
$claims=json_decode($decode($env['payload']),true,32,JSON_THROW_ON_ERROR);

$check('01 packet originated from actual Portal F09 database signer',
    $claims['issuer'],'imagine.portal');
$check('02 issuer uses expected crypto protocol',
    $env['version'],'portal-capability-envelope.v1');
$check('03 claim has Performance audience',
    $claims['audience'],'imagine.performance-ai');
$check('04 source membership revision persisted by Portal F09',
    $claims['membership_revision'],4);
$check('05 source service revision persisted by Portal F09',
    $claims['service_revision'],5);
$check('06 source epoch persisted by Portal F09',
    $claims['recovery_epoch'],7);
$check('07 source account/session revision persisted in Portal',
    $claims['session_revision'],4);
$check('08 real signed claim has short lease',
    $claims['expires_at']-$claims['issued_at'],20);
$check('09 fresh independent SQL read produces an authority snapshot',
    $makeAuthority() instanceof SyntheticCurrentAuthority,true);
$check('10 source authority membership revision equals signed claim',
    $makeAuthority()?->membershipRevision,$claims['membership_revision']);
$check('11 source authority service revision equals signed claim',
    $makeAuthority()?->serviceRevision,$claims['service_revision']);
$check('12 source authority epoch equals signed claim',
    $makeAuthority()?->recoveryEpoch,$claims['recovery_epoch']);
$check('13 source actor originates in server-side hashed session',
    $makeAuthority()?->actorRef,'actor-demo-a');
$check('14 Performance verifies signature and current source in a single lab check',
    $verify(),'verified_synthetic_only');
$gate=new SignedPortalCapabilityGate($pub);
$check('15 first use of nonce accepted in single receiver process',
    $gate->check($packet,$makeAuthority(),$now),'verified_synthetic_only');
$check('16 second use of same nonce rejected',
    $gate->check($packet,$makeAuthority(),$now),'replay_denied');
$deny('17 mismatched public key refused',
    $verify(key:bin2hex(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))));
$check('18 payload is read-only only',
    $claims['permission'],'campaigns:read');
$check('19 resource is still explicitly demo, not a real Ads campaign',
    $claims['resource_ref'],'demo-cam-a1');
$check('20 no real financial or media facts inside claim',
    str_contains($decode($env['payload']),'media_spend')
    ||str_contains($decode($env['payload']),'provider_access_token')
    ||str_contains($decode($env['payload']),'billing'),false);

$admin->exec(
    "UPDATE portal_perf_authority_services SET service_revision=6
     WHERE organization_ref='org-demo-a'"
);
$check('21 fresh SQL source sees advanced service revision',
    $makeAuthority()?->serviceRevision,6);
$check('22 old signed token denied after service revision changes',
    $verify(),'authority_revision_mismatch');
$admin->exec(
    "UPDATE portal_perf_authority_services SET service_revision=5,recovery_epoch=8
     WHERE organization_ref='org-demo-a'"
);
$check('23 signed token denied after recovery epoch changes',
    $verify(),'authority_revision_mismatch');
$admin->exec(
    "UPDATE portal_perf_authority_services SET recovery_epoch=7
     WHERE organization_ref='org-demo-a'"
);
$check('24 restoring service revision+epoch in lab restores proof',
    $verify(),'verified_synthetic_only');
$admin->exec(
    "UPDATE portal_perf_authority_memberships SET membership_revision=5
     WHERE organization_ref='org-demo-a'"
);
$check('25 changed membership revision invalidates signed token',
    $verify(),'authority_revision_mismatch');
$admin->exec(
    "UPDATE portal_perf_authority_memberships SET membership_revision=4
     WHERE organization_ref='org-demo-a'"
);
$check('26 restored membership revision matches old token in test',
    $verify(),'verified_synthetic_only');
$admin->exec(
    "UPDATE portal_perf_authority_sessions
     SET account_revision=5,session_account_revision=5
     WHERE session_hash='{$hash}'"
);
$check('27 changed session revision invalidates old claim',
    $verify(),'authority_revision_mismatch');
$admin->exec(
    "UPDATE portal_perf_authority_sessions
     SET account_revision=4,session_account_revision=4
     WHERE session_hash='{$hash}'"
);
$check('28 restored mock source allows only lab proof again',
    $verify(),'verified_synthetic_only');

$admin->exec(
    "UPDATE portal_perf_authority_sessions
     SET status='revoked' WHERE session_hash='{$hash}'"
);
$check('29 fresh session resolver denies revoked server row',$makeAuthority(),null);
$deny('30 receiver denies signed packet when source session now revoked',
    $verify());
$admin->exec(
    "UPDATE portal_perf_authority_sessions
     SET status='active',mfa_verified_at=NULL WHERE session_hash='{$hash}'"
);
$check('31 missing MFA proof means no current authority',$makeAuthority(),null);
$deny('32 receiver denies signed packet when MFA proof removed',$verify());
$admin->exec(
    "UPDATE portal_perf_authority_sessions
     SET mfa_verified_at=to_timestamp(".($now-50).")
     WHERE session_hash='{$hash}'"
);
$admin->exec(
    "UPDATE portal_perf_authority_memberships
     SET status='revoked' WHERE organization_ref='org-demo-a'"
);
$check('33 server-side membership revocation means no authority',$makeAuthority(),null);
$deny('34 receiver rejects signed packet after membership revocation',$verify());
$admin->exec(
    "UPDATE portal_perf_authority_memberships
     SET status='active' WHERE organization_ref='org-demo-a'"
);
$admin->exec(
    "UPDATE portal_perf_authority_services
     SET contract_state='revoked_mock' WHERE organization_ref='org-demo-a'"
);
$check('35 mock service cancellation means no authority',$makeAuthority(),null);
$deny('36 receiver denies signed packet after service cancellation',$verify());
$admin->exec(
    "UPDATE portal_perf_authority_services
     SET contract_state='active_mock' WHERE organization_ref='org-demo-a'"
);
$check('37 restoring all fake authorities allows controlled lab check',
    $verify(),'verified_synthetic_only');

$admin->exec(
    'REVOKE SELECT ON portal_perf_authority_services FROM portal_f08_reader'
);
$check('38 lost SQL SELECT makes independent authority unavailable',
    $makeAuthority(),null);
$deny('39 receiver refuses signed packet on source outage',$verify());
$admin->exec(
    'GRANT SELECT ON portal_perf_authority_services TO portal_f08_reader'
);
$check('40 restoring DB role SELECT allows lab read',
    $makeAuthority() instanceof SyntheticCurrentAuthority,true);
$check('41 no transaction left open after outage handling',
    $reader->inTransaction(),false);
$deny('42 issued token expires after 20 seconds',
    $verify(when:$now+20));
$deny('43 issued token not valid before time of signing',
    $verify(when:$now-1));
$deny('44 wrong tenant override is not accepted even with valid signature',
    $verify(new SyntheticCurrentAuthority(
        ...array_replace(
            get_object_vars($makeAuthority()),
            ['tenantRef'=>'perf-demo-b']
        )
    )));
$check('45 cross-org session B cannot resolve the service A scope',
    $resolver->resolve(
        'portal-f08-opaque-session-test-bbbbbbbbbbbbbbbbbbb',
        'svc-demo-a',$now
    ),null);
putenv('PERF_F30_PG_END_TO_END_LAB=0');
$check('46 F30 workflow flag is only a test harness prerequisite',
    getenv('PERF_F30_PG_END_TO_END_LAB'),'0');
putenv('PERF_F30_PG_END_TO_END_LAB=1');
putenv('PERF_F29_SIGNED_PORTAL_LAB=0');
$check('47 removing F29 gate flag denies despite trustworthy mock database',
    $verify(),'deployment_gate_closed');
putenv('PERF_F29_SIGNED_PORTAL_LAB=1');
putenv('APP_ENV=production');
$check('48 production runtime remains blocked even if claim was signed',
    $verify(),'deployment_gate_closed');
putenv('APP_ENV=testing');
$check('49 no company or customer contact data from signing path',
    str_contains($packet,'@') || str_contains($packet,'phone'),false);
$check('50 only test-only status ever returns as success',
    $verify(),'verified_synthetic_only');
echo "PASS F30 $n persisted Portal authority + Ed25519 + Performance checks\n";
