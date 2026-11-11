<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/SyntheticManagedScopeLedger.php';

/**
 * F157: SQLite durability lab for fictional creative reviews.
 * Database lives exclusively inside disposable RUNNER_TEMP. The caller's
 * tenant/reviewer strings are NOT Portal IAM. NO real Ads authorization.
 */
final class F157SyntheticSqliteReviewStore
{
    private PDO $db;

    public function __construct(string $path, private SyntheticManagedScopeLedger $scope)
    {
        if (PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            || getenv('PERF_F52_SCOPE_LAB')!=='1'
            || getenv('PERF_F157_SQLITE_LAB')!=='1'
            || !extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('F157 synthetic SQLite lab only');
        }
        $runner=realpath((string)getenv('RUNNER_TEMP'));
        $parent=realpath(dirname($path));
        if($runner===false||$parent===false
            ||!str_starts_with($parent.'/',rtrim($runner,'/').'/')
            ||!preg_match('/\.sqlite$/D',$path)) {
            throw new RuntimeException('F157 database must remain in runner temp');
        }
        $this->db=new PDO('sqlite:'.$path,null,null,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        $this->db->exec('PRAGMA busy_timeout=5000');
        $this->db->exec('PRAGMA journal_mode=WAL');
        $this->db->exec('PRAGMA synchronous=FULL');
        $this->db->exec('CREATE TABLE IF NOT EXISTS f157_reviews (
            tenant TEXT NOT NULL, reference TEXT NOT NULL,
            campaign TEXT NOT NULL, channel TEXT NOT NULL,
            title TEXT NOT NULL, status TEXT NOT NULL,
            version INTEGER NOT NULL CHECK(version>=1 AND version<=1000),
            PRIMARY KEY(tenant,reference),
            CHECK(channel IN ("google_ads","meta")),
            CHECK(status IN ("pending_demo","changes_requested_demo",
                "approved_mock_only","rejected_mock_only"))
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS f157_events (
            event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant TEXT NOT NULL, reference TEXT NOT NULL,
            transition TEXT NOT NULL, version INTEGER NOT NULL,
            reviewer_fixture TEXT NOT NULL,
            UNIQUE(tenant,reference,version),
            FOREIGN KEY(tenant,reference) REFERENCES f157_reviews(tenant,reference)
        )');
        $this->db->exec('PRAGMA foreign_keys=ON');
    }

    private static function ref(string $s,string $prefix):void
    {
        if(!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$s)) {
            throw new InvalidArgumentException('F157 invalid fictional reference');
        }
    }

    private static function title(string $s):void
    {
        if($s===''||strlen($s)>100||preg_match('/[<>\x00-\x1f\x7f]/',$s)) {
            throw new InvalidArgumentException('F157 invalid fictional title');
        }
    }

    private function write(callable $transaction):string
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result=$transaction();
            $this->db->exec('COMMIT');
            return $result;
        } catch(\Throwable $error) {
            if($this->db->inTransaction())$this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    public function submit(
        string $tenant,string $reference,string $campaign,
        string $channel,string $title
    ):string {
        self::ref($tenant,'perf-demo-');self::ref($reference,'rev-');
        self::ref($campaign,'cam-');self::title($title);
        if(!in_array($channel,['google_ads','meta'],true)){
            throw new InvalidArgumentException('F157 invalid channel');
        }
        if($this->scope->campaignChannel($tenant,$campaign)!==$channel){
            return 'out_of_scope_or_wrong_channel_denied';
        }
        return $this->write(function()use($tenant,$reference,$campaign,$channel,$title):string {
            $check=$this->db->prepare('SELECT 1 FROM f157_reviews WHERE tenant=? AND reference=?');
            $check->execute([$tenant,$reference]);
            if($check->fetch()!==false)return 'duplicate_review_denied';
            $total=$this->db->prepare('SELECT count(*) FROM f157_reviews WHERE tenant=?');
            $total->execute([$tenant]);
            if((int)$total->fetchColumn()>=25)return 'review_queue_capacity_denied';
            $insert=$this->db->prepare('INSERT INTO f157_reviews
                (tenant,reference,campaign,channel,title,status,version)
                VALUES(?,?,?,?,?,"pending_demo",1)');
            $insert->execute([$tenant,$reference,$campaign,$channel,$title]);
            $this->event($tenant,$reference,'submitted_demo',1,'reviewer-demo-system');
            return 'submitted_demo';
        });
    }

    private function event(
        string $tenant,string $ref,string $transition,int $version,string $reviewer
    ):void {
        $stmt=$this->db->prepare('INSERT INTO f157_events
          (tenant,reference,transition,version,reviewer_fixture) VALUES(?,?,?,?,?)');
        $stmt->execute([$tenant,$ref,$transition,$version,$reviewer]);
    }

    private function item(string $tenant,string $ref):?array
    {
        $stmt=$this->db->prepare('SELECT campaign,channel,title,status,version
            FROM f157_reviews WHERE tenant=? AND reference=?');
        $stmt->execute([$tenant,$ref]);
        $row=$stmt->fetch();
        return $row===false?null:$row;
    }

    public function decide(
        string $tenant,string $ref,int $version,string $decision,string $reviewer
    ):string {
        self::ref($tenant,'perf-demo-');self::ref($ref,'rev-');
        self::ref($reviewer,'reviewer-demo-');
        $next=[
            'approve_mock'=>'approved_mock_only',
            'reject_mock'=>'rejected_mock_only',
            'request_changes'=>'changes_requested_demo',
        ][$decision]??null;
        if($next===null)return 'unknown_decision_denied';
        return $this->write(function()use($tenant,$ref,$version,$next,$reviewer):string {
            $item=$this->item($tenant,$ref);
            if($item===null)return 'not_found';
            if((int)$item['version']!==$version)return 'stale_version_denied';
            if($item['status']!=='pending_demo')return 'review_not_pending_denied';
            $stmt=$this->db->prepare('UPDATE f157_reviews SET status=?,version=version+1
                WHERE tenant=? AND reference=? AND version=? AND status="pending_demo"');
            $stmt->execute([$next,$tenant,$ref,$version]);
            if($stmt->rowCount()!==1)return 'stale_version_denied';
            $this->event($tenant,$ref,$next,$version+1,$reviewer);
            return $next;
        });
    }

    public function revise(
        string $tenant,string $ref,int $version,string $title
    ):string {
        self::ref($tenant,'perf-demo-');self::ref($ref,'rev-');
        self::title($title);
        return $this->write(function()use($tenant,$ref,$version,$title):string {
            $item=$this->item($tenant,$ref);
            if($item===null)return 'not_found';
            if((int)$item['version']!==$version)return 'stale_version_denied';
            if($item['status']!=='changes_requested_demo')
                return 'revision_not_requested_denied';
            if($this->scope->campaignChannel($tenant,$item['campaign'])!==$item['channel'])
                return 'campaign_no_longer_in_scope_denied';
            $stmt=$this->db->prepare('UPDATE f157_reviews
                SET title=?,status="pending_demo",version=version+1
                WHERE tenant=? AND reference=? AND version=? AND status="changes_requested_demo"');
            $stmt->execute([$title,$tenant,$ref,$version]);
            if($stmt->rowCount()!==1)return 'stale_version_denied';
            $this->event($tenant,$ref,'resubmitted_demo',$version+1,'reviewer-demo-system');
            return 'resubmitted_demo';
        });
    }

    /** @return array<string,mixed> */
    public function project(string $tenant):array
    {
        self::ref($tenant,'perf-demo-');
        $stmt=$this->db->prepare('SELECT reference,campaign,channel,title,status,version
            FROM f157_reviews WHERE tenant=? ORDER BY reference LIMIT 26');
        $stmt->execute([$tenant]);
        $data=$stmt->fetchAll();
        if(count($data)>25)throw new RuntimeException('F157 projection capacity exceeded');
        $summary=array_fill_keys([
            'pending_demo','changes_requested_demo',
            'approved_mock_only','rejected_mock_only'
        ],0);
        $rows=[];
        foreach($data as $item) {
            $summary[$item['status']]++;
            $rows[]=[
                'review_ref'=>$item['reference'],
                'campaign_ref'=>$item['campaign'],
                'channel'=>$item['channel'],
                'title'=>$item['title'],
                'status'=>$item['status'],
                'version'=>(int)$item['version'],
                'action_authorized'=>false,'human_approval_verified'=>false,
                'billing_enabled'=>false,
            ];
        }
        $audit=$this->db->prepare('SELECT count(*) FROM f157_events WHERE tenant=?');
        $audit->execute([$tenant]);
        return [
            'schema_version'=>'perf.portal.creative-reviews.sqlite-lab.v1',
            'source'=>'sqlite_runner_fixture_only',
            'tenant_ref'=>$tenant,
            'read_only'=>true,'contracted'=>false,
            'human_approval_verified'=>false,'authorization_eligible'=>false,
            'dispatch_enabled'=>false,'billing_enabled'=>false,
            'ready_for_production'=>false,'review_count'=>count($rows),
            'summary'=>$summary,'reviews'=>$rows,
            'audit_entries_demo'=>(int)$audit->fetchColumn(),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function events(string $tenant):array
    {
        self::ref($tenant,'perf-demo-');
        $stmt=$this->db->prepare('SELECT reference,transition,version,reviewer_fixture
            FROM f157_events WHERE tenant=? ORDER BY event_id LIMIT 1000');
        $stmt->execute([$tenant]);
        return $stmt->fetchAll();
    }
}
