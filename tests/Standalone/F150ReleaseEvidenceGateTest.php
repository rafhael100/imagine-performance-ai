<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Domain/Performance/F150ReleaseEvidenceGate.php';

use Imagine\Performance\Domain\F150ReleaseEvidenceGate;

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
   ||getenv('PERF_F150_RELEASE_LAB')!=='1') {
 throw new RuntimeException('F150 cannot run outside lab');
}
$source=dirname(__DIR__,2).'/contracts/release/f150-lab-gate-evidence.json';
$manifest=json_decode((string)file_get_contents($source),true,32,JSON_THROW_ON_ERROR);
$gate=new F150ReleaseEvidenceGate();
$n=0;
$check=static function(bool $ok,string $why)use(&$n):void{
 ++$n;if(!$ok)throw new RuntimeException('F150 FAILED: '.$why);
};
$rejects=static function(array $payload)use($gate):bool{
 try{$gate->evaluate($payload);return false;}
 catch(InvalidArgumentException $e){return true;}
};
$result=$gate->evaluate($manifest);
$check($result['release']==='NO_GO','default CI result NO-GO');
$check($result['gate_count']===12&&count($result['gates'])===12,'all 12 release prerequisites present');
$check(!$result['can_merge_main']&&!$result['can_deploy'],'no merge/deploy permission');
$check(!$result['can_enable_ads']&&!$result['can_bill'],'no Ads or billing permission');
$check(!$result['external_evidence_verified']&&!$result['independent_review_completed'],
 'self-asserted CI evidence is never independent approval');
$check(count(array_filter($result['gates'],
 static fn(array $row):bool=>$row['production_approved']===true))===0,
 'no individual gate becomes production approved from lab state');
$m=$manifest;$m['gates'][0]['state']='approved';
$check($rejects($m),'self-asserted approved state rejected');
$m=$manifest;$m['gates'][0]['state']='lab_passed';
$r=$gate->evaluate($m);
$check($r['release']==='NO_GO'&&!$r['can_enable_ads'],
 'even fabricated all-passed lab proof cannot turn on Ads');
$m=$manifest;
foreach($m['gates'] as &$row)$row['state']='lab_passed';
unset($row);
$r=$gate->evaluate($m);
$check($r['release']==='NO_GO'&&!$r['can_deploy'],
 'all twelve lab PASS states do not replace external production approval');
$m=$manifest;array_pop($m['gates']);
$check($rejects($m),'missing independent prerequisite denied');
$m=$manifest;$m['gates'][1]['id']=$m['gates'][0]['id'];
$check($rejects($m),'duplicate gate identifier denied');
$m=$manifest;$m['gates'][0]['id']='unknown_release_backdoor';
$check($rejects($m),'unknown gate denied');
$m=$manifest;$m['requested_action']='deploy';
$check($rejects($m),'CI request to deploy denied');
$m=$manifest;$m['environment']='production';
$check($rejects($m),'production environment cannot invoke self-report manifest');
$m=$manifest;$m['gates'][0]['evidence']=str_repeat('A',181);
$check($rejects($m),'unbounded evidence string denied');
$m=$manifest;$m['gates'][0]['evidence']="Injected\nline";
$check($rejects($m),'newline evidence injection denied');
echo "F150 release gate: {$n} PASS / 0 FAIL — NO-GO\n";
