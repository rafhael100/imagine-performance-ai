<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('PERF_F75_SIGNED_WITNESS_LAB=1');
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticSignedRecoveryWitness.php';
use Imagine\Performance\Domain\SyntheticSignedRecoveryWitness;

$p=new SyntheticSignedRecoveryWitness();
$key=sodium_crypto_sign_keypair();
$pub=sodium_bin2hex(sodium_crypto_sign_publickey($key));
$secret=sodium_bin2hex(sodium_crypto_sign_secretkey($key));
$tenant='synthetic_tenant_a';
$recovery=str_repeat('b',32);
$at=1800000000;
$payload=[
    'schema_version'=>'perf.recovery.witness.payload.synthetic.v1',
    'tenant'=>$tenant,'recovery_id'=>$recovery,'epoch'=>4,
    'not_before'=>$at-20,'expires_at'=>$at+40,'nonce'=>str_repeat('a',32)
];
$make=static fn(array $v):string=>SyntheticSignedRecoveryWitness::signFixture($v,$secret);
$verify=static fn(string $e,string $pubkey,string $t,string $r,int $db,int $floor,int $time):array=>
    $p->verify($e,$pubkey,$t,$r,$db,$floor,$time);
$cases=[
    ['valid signature still not authority',$make($payload),$pub,$tenant,$recovery,4,4,$at,'signed_fixture_only_no_external_trust',true],
    ['stale witness epoch',$make(array_replace($payload,['epoch'=>3])),$pub,$tenant,$recovery,4,4,$at,'rollback_suspected',false],
    ['stale database epoch',$make($payload),$pub,$tenant,$recovery,3,4,$at,'rollback_suspected',false],
    ['divergence',$make($payload),$pub,$tenant,$recovery,5,4,$at,'epoch_divergence',false],
    ['tenant substitution',$make($payload),$pub,'synthetic_tenant_b',$recovery,4,4,$at,'wrong_context',false],
    ['recovery substitution',$make($payload),$pub,$tenant,str_repeat('c',32),4,4,$at,'wrong_context',false],
    ['future issuance',$make($payload),$pub,$tenant,$recovery,4,4,$at-21,'not_current',false],
    ['expired',$make($payload),$pub,$tenant,$recovery,4,4,$at+40,'not_current',false],
    ['long validity',$make(array_replace($payload,['expires_at'=>$at+400])),$pub,$tenant,$recovery,4,4,$at,'invalid_signed_claims',false],
    ['string epoch',$make(array_replace($payload,['epoch'=>'4'])),$pub,$tenant,$recovery,4,4,$at,'invalid_signed_claims',false],
    ['reordered claims',$make(array_reverse($payload,true)),$pub,$tenant,$recovery,4,4,$at,'invalid_signed_claims',false],
    ['extra claim',$make(array_merge($payload,['enabled'=>true])),$pub,$tenant,$recovery,4,4,$at,'invalid_signed_claims',false],
    ['wrong nonce',$make(array_replace($payload,['nonce'=>'bad'])),$pub,$tenant,$recovery,4,4,$at,'invalid_signed_claims',false],
    ['invalid floor',$make($payload),$pub,$tenant,$recovery,4,0,$at,'invalid_expected_context',false],
    ['invalid public key',$make($payload),'xyz',$tenant,$recovery,4,4,$at,'invalid_expected_context',false],
    ['invalid JSON','{',$pub,$tenant,$recovery,4,4,$at,'invalid_envelope',false],
    ['triple rollback without trusted floor',$make(array_replace($payload,['epoch'=>1])),$pub,$tenant,$recovery,1,1,$at,'signed_fixture_only_no_external_trust',true],
];
$wrong=sodium_crypto_sign_keypair();
$cases[]=['different valid key',$make($payload),sodium_bin2hex(sodium_crypto_sign_publickey($wrong)),$tenant,$recovery,4,4,$at,'invalid_signature',false];
$alter=json_decode($make($payload),true,8,JSON_THROW_ON_ERROR);
$alter['signature_hex'][0]=$alter['signature_hex'][0]==='0'?'1':'0';
$cases[]=['altered signature',json_encode($alter,JSON_THROW_ON_ERROR),$pub,$tenant,$recovery,4,4,$at,'invalid_signature',false];
$n=0;
foreach($cases as [$label,$env,$keyHex,$t,$r,$db,$floor,$now,$status,$sig]) {
    $res=$verify($env,$keyHex,$t,$r,$db,$floor,$now);
    if($res['status']!==$status || $res['signature_valid']!==$sig) {
        throw new RuntimeException("FAIL F75 $label: ".json_encode($res));
    }
    $n+=2;
    foreach(['external_root_trusted','monotonicity_proven','authorization_eligible',
             'read_preview_eligible','ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
        if($res[$flag]!==false) throw new RuntimeException("FAIL F75 $label/$flag");
        ++$n;
    }
}
putenv('PERF_F75_SIGNED_WITNESS_LAB=0');
$res=$verify($make/*noop*/ ? '{}' : '{}',$pub,$tenant,$recovery,4,4,$at);
if($res['status']!=='lab_disabled') throw new RuntimeException('F75 lab flag not enforced');
++$n;
echo "PASS F75 $n assertions; mock crypto never authorizes external actions\n";
