<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Domain/Administration/AdministrativeLabEngine.php';
use Imagine\Performance\Administration\AdministrativeLabEngine as E;
$n=0;
function check(bool $ok):void{global $n;if(!$ok)throw new RuntimeException('Administrative laboratory assertion failed');$n++;}
function denied(callable $f):void{try{$f();}catch(DomainException){check(true);return;}throw new RuntimeException('Forbidden command accepted');}
$now=1800000000;$s=E::fixture('lab-alfa',$now);
$c=['tenant'=>'lab-alfa','actor'=>1,'role'=>'operations','active'=>true,'mfa_at'=>$now];
$cmd=['action'=>'agent.pause','resource'=>'analysis','revision'=>1,'confirmed'=>true];
$r=E::command($s,$c,$cmd,$now);check($r['state']['agents']['analysis']['status']==='paused');check($r['event']['external_execution']===false);
denied(fn()=>E::command($r['state'],$c,$cmd,$now));
foreach(['auditor','finance','analyst','invalid'] as $role)denied(fn()=>E::command($s,array_replace($c,['role'=>$role]),$cmd,$now));
foreach([['tenant'=>'lab-beta'],['active'=>false],['mfa_at'=>$now-901],['mfa_at'=>$now+31],['actor'=>0]] as $override)denied(fn()=>E::command($s,array_replace($c,$override),$cmd,$now));
denied(fn()=>E::command($s,$c,array_replace($cmd,['confirmed'=>false]),$now));
foreach(['review','budget','payment'] as $id){
 $a=$s['approvals'][$id];$q=['action'=>'approval.approve','resource'=>$id,'revision'=>1,'confirmed'=>true,'proposal_version'=>1,'proposal_digest'=>$a['digest']];
 denied(fn()=>E::command($s,$c,array_replace($q,['proposal_version'=>2]),$now));
 denied(fn()=>E::command($s,$c,array_replace($q,['proposal_digest'=>str_repeat('0',64)]),$now));
 denied(fn()=>E::command($s,$c,$q,$now+86401));
 $r=E::command($s,$c,$q,$now);check($r['state']['approvals'][$id]['status']==='approved');
 $q['action']='approval.consume';$q['revision']=2;
 denied(fn()=>E::command($r['state'],$c,$q,$now)); // requester/approver separation
 if($id==='review'){$z=E::command($r['state'],array_replace($c,['actor'=>2]),$q,$now);check($z['state']['approvals'][$id]['status']==='consumed');}
 else denied(fn()=>E::command($r['state'],array_replace($c,['actor'=>2]),$q,$now));
 $q['action']='approval.revoke';$revoked=E::command($r['state'],$c,$q,$now);
 denied(fn()=>E::command($revoked['state'],array_replace($c,['actor'=>2]),array_replace($q,['action'=>'approval.consume','revision'=>3]),$now));
}
foreach(['sync','task'] as $id){$q=['action'=>'incident.ack','resource'=>$id,'revision'=>1,'confirmed'=>true];$r=E::command($s,$c,$q,$now);check($r['state']['incidents'][$id]['status']==='acknowledged');$q['revision']=2;$q['action']='incident.resolve';if($id==='sync')denied(fn()=>E::command($r['state'],$c,$q,$now));else check(E::command($r['state'],$c,$q,$now)['state']['incidents'][$id]['status']==='resolved');}
$bad=$s;$bad['approvals']['review']['status']='approved';$bad['approvals']['review']['approved_by']=null;
$a=$bad['approvals']['review'];$q=['action'=>'approval.consume','resource'=>'review','revision'=>1,'confirmed'=>true,'proposal_version'=>1,'proposal_digest'=>$a['digest']];
denied(fn()=>E::command($bad,$c,$q,$now));
check($s['channels']['chatgpt_ads']['status']==='unavailable');
foreach($s['channels'] as $ch)check(!$ch['read_authorized']&&!$ch['write_authorized']&&!$ch['provider_verified']);
echo "ADMIN ENGINE PASS: $n assertions; synthetic only, zero external effects.\n";
