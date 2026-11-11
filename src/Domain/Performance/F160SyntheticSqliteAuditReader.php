<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use RuntimeException;

/**
 * F160: consistency checker for a fictional SQLite review/audit history.
 * It detects accidental row/event mismatches. It CANNOT detect a privileged
 * attacker editing both tables or restoring the entire database together.
 */
final class F160SyntheticSqliteAuditReader
{
    private PDO $db;

    public function __construct(string $path)
    {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F157_SQLITE_LAB')!=='1'
            ||getenv('PERF_F160_AUDIT_LAB')!=='1'
            ||!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('F160 offline SQLite audit only');
        }
        $root=realpath((string)getenv('RUNNER_TEMP'));
        $real=realpath($path);
        if($root===false||$real===false
            ||!str_starts_with($real,rtrim($root,'/').'/')
            ||!str_ends_with($real,'.sqlite')
            ||!is_file($real)) {
            throw new RuntimeException('F160 must read an existing disposable SQLite file');
        }
        $this->db=new PDO('sqlite:'.$real,null,null,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        $this->db->exec('PRAGMA busy_timeout=5000');
        $this->db->exec('PRAGMA query_only=ON');
    }

    /** @return array<string,mixed>|null */
    public function inspect(string $tenant):?array
    {
        if(!preg_match('/^perf-demo-[a-z0-9-]{1,60}$/D',$tenant))return null;
        $deny=static function(string $status) use($tenant):array{
            return [
                'schema_version'=>'perf.review-audit.sqlite-lab.v1',
                'source'=>'sqlite_runner_fixture_only',
                'tenant_ref'=>$tenant,
                'read_only'=>true,'contracted'=>false,
                'human_approval_verified'=>false,'iam_verified'=>false,
                'dispatch_enabled'=>false,'billing_enabled'=>false,
                'ready_for_production'=>false,
                'status'=>$status,
                'audit_verified_for_production'=>false,
                'can_export'=>false,'events'=>[],
                'event_count'=>0,'digest'=>null,
            ];
        };
        try {
            $this->db->exec('BEGIN');
            $stmt=$this->db->prepare('SELECT reference,status,version
                FROM f157_reviews WHERE tenant=? ORDER BY reference LIMIT 26');
            $stmt->execute([$tenant]);
            $reviews=$stmt->fetchAll();
            $eventStmt=$this->db->prepare('SELECT event_id,reference,transition,
                version,reviewer_fixture FROM f157_events
                WHERE tenant=? ORDER BY event_id LIMIT 2001');
            $eventStmt->execute([$tenant]);
            $events=$eventStmt->fetchAll();
            $this->db->exec('COMMIT');
        } catch(\Throwable $e) {
            if($this->db->inTransaction())$this->db->exec('ROLLBACK');
            return $deny('audit_unavailable_fail_closed');
        }
        if(count($reviews)>25||count($events)>2000){
            return $deny('audit_bounds_exceeded');
        }
        $byRef=[];$states=[];$seen=[];
        foreach($reviews as $review){
            $ref=$review['reference'];
            if(!is_string($ref)||!preg_match('/^rev-[a-z0-9-]{1,60}$/D',$ref)
                ||!in_array($review['status'],[
                    'pending_demo','changes_requested_demo',
                    'approved_mock_only','rejected_mock_only',
                ],true)
                ||(int)$review['version']<1||(int)$review['version']>1000){
                return $deny('audit_inconsistent_fail_closed');
            }
            $byRef[$ref]=['status'=>$review['status'],'version'=>(int)$review['version']];
        }
        $previousEventId=0;
        foreach($events as $event){
            $ref=$event['reference'];
            $id=(int)$event['event_id'];
            $version=(int)$event['version'];
            if($id<=$previousEventId || !isset($byRef[$ref])
                ||!is_string($event['reviewer_fixture'])
                ||!preg_match('/^reviewer-demo-[a-z0-9-]{1,60}$/D',
                    $event['reviewer_fixture'])
                ||$version<1||$version>1000) {
                return $deny('audit_inconsistent_fail_closed');
            }
            $previousEventId=$id;
            $prior=$states[$ref]??['status'=>null,'version'=>0];
            if($version!==$prior['version']+1){
                return $deny('audit_inconsistent_fail_closed');
            }
            $transition=$event['transition'];
            $next=null;
            if($prior['status']===null && $transition==='submitted_demo'){
                $next='pending_demo';
            } elseif($prior['status']==='pending_demo'
                &&in_array($transition,[
                    'changes_requested_demo','approved_mock_only',
                    'rejected_mock_only',
                ],true)){
                $next=$transition;
            } elseif($prior['status']==='changes_requested_demo'
                &&$transition==='resubmitted_demo'){
                $next='pending_demo';
            }
            if($next===null)return $deny('audit_inconsistent_fail_closed');
            $states[$ref]=['status'=>$next,'version'=>$version];
            $seen[$ref]=true;
        }
        foreach($byRef as $ref=>$final){
            if(!isset($seen[$ref])||$states[$ref]!==$final){
                return $deny('audit_inconsistent_fail_closed');
            }
        }
        $normalized=[];
        foreach($events as $event){
            $normalized[]=[
                'event_id'=>(int)$event['event_id'],
                'review_ref'=>$event['reference'],
                'transition'=>$event['transition'],
                'version'=>(int)$event['version'],
                'reviewer_fixture'=>$event['reviewer_fixture'],
                'action_authorized'=>false,
            ];
        }
        return [
            ...$deny('consistent_mock_only'),
            'can_export'=>true,
            'events'=>$normalized,
            'event_count'=>count($normalized),
            'digest'=>hash('sha256',json_encode([
                'tenant'=>$tenant,'reviews'=>$byRef,'events'=>$normalized,
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),
        ];
    }
}
