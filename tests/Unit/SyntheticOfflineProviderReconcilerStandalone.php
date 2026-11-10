<?php
declare(strict_types=1);
putenv('APP_ENV=testing');
putenv('PERF_F76_RECONCILIATION_LAB=1');
require_once dirname(__DIR__,2).'/src/Domain/Performance/SyntheticOfflineProviderReconciler.php';

use Imagine\Performance\Domain\SyntheticOfflineProviderReconciler;

$tenant='tenant_aurora';
$intents=[
    ['intent_id'=>str_repeat('a',32),'tenant'=>$tenant,'operation'=>'create',
     'request_hash'=>str_repeat('b',64),'epoch'=>3,'approved_mock'=>true],
    ['intent_id'=>str_repeat('c',32),'tenant'=>$tenant,'operation'=>'pause',
     'request_hash'=>str_repeat('d',64),'epoch'=>3,'approved_mock'=>true],
];
$receipts=[
    ['receipt_id'=>str_repeat('e',32),'intent_id'=>$intents[0]['intent_id'],
     'tenant'=>$tenant,'request_hash'=>$intents[0]['request_hash'],
     'epoch'=>3,'status'=>'accepted_mock'],
    ['receipt_id'=>str_repeat('f',32),'intent_id'=>$intents[1]['intent_id'],
     'tenant'=>$tenant,'request_hash'=>$intents[1]['request_hash'],
     'epoch'=>3,'status'=>'accepted_mock'],
];
$p=new SyntheticOfflineProviderReconciler();
$withIntent=static function(int $index,string $key,mixed $value)use($intents):array{
    $copy=$intents;$copy[$index][$key]=$value;return $copy;
};
$withReceipt=static function(int $index,string $key,mixed $value)use($receipts):array{
    $copy=$receipts;$copy[$index][$key]=$value;return $copy;
};
$cases=[
    ['matched',$intents,$receipts,$tenant,3,'matched_mock_only',0],
    ['pending',$intents,[$receipts[0]],$tenant,3,'awaiting_mock_receipts',1],
    ['all pending',$intents,[],$tenant,3,'awaiting_mock_receipts',2],
    ['empty',[],[],$tenant,3,'empty_mock_only',0],
    ['rejected mock',$intents,$withReceipt(1,'status','rejected_mock'),$tenant,3,'mock_rejected',0],
    ['hash mismatch',$intents,$withReceipt(1,'request_hash',str_repeat('0',64)),$tenant,3,'conflict_detected',0],
    ['epoch mismatch',$intents,$withReceipt(1,'epoch',4),$tenant,3,'conflict_detected',0],
    ['unapproved',$withIntent(0,'approved_mock',false),$receipts,$tenant,3,'conflict_detected',0],
    ['duplicate receipt ID',$intents,$withReceipt(1,'receipt_id',$receipts[0]['receipt_id']),$tenant,3,'conflict_detected',0],
    ['duplicate intent observation',$intents,$withReceipt(1,'intent_id',$intents[0]['intent_id']),$tenant,3,'conflict_detected',0],
    ['unknown intent',$intents,$withReceipt(1,'intent_id',str_repeat('1',32)),$tenant,3,'conflict_detected',0],
    ['wrong receipt tenant',$intents,$withReceipt(1,'tenant','tenant_outro'),$tenant,3,'invalid_fixture',0],
    ['wrong intent tenant',$withIntent(0,'tenant','tenant_outro'),$receipts,$tenant,3,'invalid_fixture',0],
    ['duplicated intent',$intents+[],$receipts,$tenant,3,'matched_mock_only',0],
    ['unknown operation',$withIntent(0,'operation','delete'),$receipts,$tenant,3,'invalid_fixture',0],
    ['stale epoch',$withIntent(0,'epoch',2),$receipts,$tenant,3,'invalid_fixture',0],
    ['stale receipt',$intents,$withReceipt(0,'epoch',2),$tenant,3,'invalid_fixture',0],
    ['string epoch',$withIntent(0,'epoch','3'),$receipts,$tenant,3,'invalid_fixture',0],
    ['mock flag as string',$withIntent(0,'approved_mock','true'),$receipts,$tenant,3,'invalid_fixture',0],
    ['bad intent ID',$withIntent(0,'intent_id','non-hex'),$receipts,$tenant,3,'invalid_fixture',0],
    ['additional input key',array_merge($intents,[array_merge($intents[0],['secret'=>'no'])]),$receipts,$tenant,3,'invalid_fixture',0],
    ['bad floor',$intents,$receipts,$tenant,0,'invalid_fixture',0],
    ['bad tenant',$intents,$receipts,'../../root',3,'invalid_fixture',0],
    ['non-array intent',null,$receipts,$tenant,3,'invalid_fixture',0],
    ['non-array receipt',$intents,null,$tenant,3,'invalid_fixture',0],
    ['object receipt',$intents,(object)$receipts,$tenant,3,'invalid_fixture',0],
    ['array count >100',array_fill(0,101,$intents[0]),[],$tenant,3,'invalid_fixture',0],
    ['wrong receipt status',$intents,$withReceipt(0,'status','succeeded'),$tenant,3,'invalid_fixture',0],
    ['missing receipt field',$intents,[['intent_id'=>$intents[0]['intent_id']],$receipts[1]],$tenant,3,'invalid_fixture',0],
];
$copies=$intents;$copies[1]=$copies[0];
$cases[]=['duplicate two intents',$copies,$receipts,$tenant,3,'invalid_fixture',0];
$assertions=0;
foreach($cases as [$name,$input,$observations,$wantedTenant,$floor,$expected,$pending]) {
    $out=$p->inspect($input,$observations,$wantedTenant,$floor);
    if($out['status']!==$expected) throw new RuntimeException("F76 $name: ".json_encode($out));
    ++$assertions;
    foreach(['provider_was_contacted','authenticated_provider_evidence',
        'recovery_monotonicity_proven','authorization_eligible','read_preview_eligible',
        'ready_for_production','dispatch_enabled','billing_enabled'] as $flag) {
        if($out[$flag]!==false) throw new RuntimeException("F76 $name:$flag");
        ++$assertions;
    }
    if(in_array($expected,['matched_mock_only','awaiting_mock_receipts','mock_rejected',
                             'empty_mock_only'],true)) {
        if($out['counts']['pending']!==$pending) throw new RuntimeException("F76 $name: pending");
        ++$assertions;
    }
}
putenv('PERF_F76_RECONCILIATION_LAB=0');
if($p->inspect($intents,$receipts,$tenant,3)['status']!=='lab_disabled') {
    throw new RuntimeException('F76 disabled flag failed');
}
++$assertions;
putenv('PERF_F76_RECONCILIATION_LAB=1');
putenv('APP_ENV=production');
if($p->inspect($intents,$receipts,$tenant,3)['status']!=='lab_disabled') {
    throw new RuntimeException('F76 allowed production');
}
++$assertions;
echo "PASS F76 {$assertions} assertions; no provider action permitted\n";
