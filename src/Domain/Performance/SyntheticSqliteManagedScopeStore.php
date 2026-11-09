<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use RuntimeException;
use Throwable;
use InvalidArgumentException;

/**
 * FAST-PERF F1-64. Durable SQLITE demo of capacity accounting in transactions.
 *
 * Local disposable CI only. Not PostgreSQL/RLS, Portal auth, real quotas,
 * human approval, money, Google/Meta integration, multi-region durability,
 * nor any production access control. All approvals are booleans supplied by
 * untrusted test fixtures and MUST NEVER be wired to a live service.
 */
final class SyntheticSqliteManagedScopeStore
{
    private readonly PDO $db;

    public function __construct(PDO $db)
    {
        if(!self::enabled()
           ||$db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'){
            throw new RuntimeException('F64 only supports disposable sqlite CLI under explicit flags');
        }
        $rows=$db->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if(!is_array($rows)||count($rows)!==1
           ||($rows[0]['name']??null)!=='main'){
            throw new RuntimeException('F64 requires one isolated SQLite database');
        }
        $file=$rows[0]['file']??null;
        if(!is_string($file))throw new RuntimeException('Missing SQLite file info');
        if($file!==''){
            $path=realpath($file);
            $root=realpath(sys_get_temp_dir());
            if(!is_string($path)||!is_string($root)
               ||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)
               ||!preg_match('/^perf-f64-[a-z0-9-]{1,60}\.sqlite$/D',basename($path))){
                throw new RuntimeException('F64 accepts only disposable temp perf-f64-*.sqlite');
            }
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys=ON');
        $db->exec('PRAGMA busy_timeout=5000');
        if((int)$db->query('PRAGMA foreign_keys')->fetchColumn()!==1){
            throw new RuntimeException('F64 requires SQLite foreign-key enforcement');
        }
        $this->db=$db;
        $this->install();
    }

    private static function enabled():bool
    {
        return PHP_SAPI==='cli' && getenv('APP_ENV')==='testing'
            && getenv('PERF_F64_SQLITE_SCOPE_LAB')==='1';
    }

    private static function id(string $ref,string $prefix):void
    {
        if(!preg_match('/^'.preg_quote($prefix,'/').'[a-z0-9-]{1,60}$/D',$ref)){
            throw new InvalidArgumentException('Invalid F64 synthetic identifier');
        }
    }

    private static function channel(string $c):void
    {
        if(!in_array($c,['google_ads','meta'],true)){
            throw new InvalidArgumentException('Invalid F64 demo channel');
        }
    }

    private function install():void
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS f64_limits (
    tenant TEXT NOT NULL CHECK(tenant GLOB 'perf-demo-*'),
    channel TEXT NOT NULL CHECK(channel IN ('google_ads','meta')),
    campaign_limit INTEGER NOT NULL CHECK(campaign_limit BETWEEN 0 AND 100),
    group_limit INTEGER NOT NULL CHECK(group_limit BETWEEN 0 AND 1000),
    PRIMARY KEY(tenant,channel)
);
CREATE TABLE IF NOT EXISTS f64_campaigns (
    ref TEXT PRIMARY KEY CHECK(ref GLOB 'cam-*'),
    tenant TEXT NOT NULL CHECK(tenant GLOB 'perf-demo-*'),
    channel TEXT NOT NULL CHECK(channel IN ('google_ads','meta')),
    state TEXT NOT NULL CHECK(state IN (
        'active_demo','paused_demo','draft_demo','outside_scope_demo'
    )),
    managed INTEGER NOT NULL CHECK(managed IN (0,1)),
    FOREIGN KEY(tenant,channel) REFERENCES f64_limits(tenant,channel)
);
CREATE INDEX IF NOT EXISTS f64_campaign_tenant_channel
    ON f64_campaigns(tenant,channel,managed);
CREATE UNIQUE INDEX IF NOT EXISTS f64_campaign_tenant_ref
    ON f64_campaigns(tenant,ref);
CREATE TABLE IF NOT EXISTS f64_groups (
    ref TEXT PRIMARY KEY CHECK(ref GLOB 'grp-*'),
    tenant TEXT NOT NULL CHECK(tenant GLOB 'perf-demo-*'),
    campaign_ref TEXT NOT NULL,
    state TEXT NOT NULL CHECK(state IN (
        'active_demo','paused_demo','outside_scope_demo'
    )),
    managed INTEGER NOT NULL CHECK(managed IN (0,1)),
    FOREIGN KEY(tenant,campaign_ref) REFERENCES f64_campaigns(tenant,ref)
);
CREATE INDEX IF NOT EXISTS f64_group_tenant
    ON f64_groups(tenant,managed);
CREATE TABLE IF NOT EXISTS f64_swaps (
    request_ref TEXT PRIMARY KEY CHECK(request_ref GLOB 'req-demo-*'),
    tenant TEXT NOT NULL CHECK(tenant GLOB 'perf-demo-*'),
    old_ref TEXT NOT NULL REFERENCES f64_campaigns(ref),
    new_ref TEXT NOT NULL REFERENCES f64_campaigns(ref),
    result TEXT NOT NULL CHECK(result='replaced_synthetic_no_fee'),
    FOREIGN KEY(tenant,old_ref) REFERENCES f64_campaigns(tenant,ref),
    FOREIGN KEY(tenant,new_ref) REFERENCES f64_campaigns(tenant,ref)
);
SQL
        );
    }

    /** @template T @param callable():T $work @return T */
    private function transaction(callable $work):mixed
    {
        if(!self::enabled())return 'lab_disabled';
        if($this->db->inTransaction())return 'nested_transaction_denied';
        $this->db->exec('BEGIN IMMEDIATE');
        try{
            $result=$work();
            $this->db->exec('COMMIT');
            return $result;
        }catch(Throwable $e){
            try{$this->db->exec('ROLLBACK');}catch(Throwable){}
            throw $e;
        }
    }

    private function used(string $table,string $tenant,string $channel):int
    {
        if($table==='f64_campaigns'){
            $sql='SELECT count(*) FROM f64_campaigns WHERE tenant=? AND channel=? AND managed=1';
        }elseif($table==='f64_groups'){
            $sql='SELECT count(*) FROM f64_groups AS g'
                .' JOIN f64_campaigns AS c ON g.campaign_ref=c.ref'
                .' WHERE g.tenant=? AND c.channel=? AND g.managed=1';
        }else{
            throw new RuntimeException('Invalid test-only table');
        }
        $stmt=$this->db->prepare($sql);
        $stmt->execute([$tenant,$channel]);
        return (int)$stmt->fetchColumn();
    }

    private function limit(string $tenant,string $channel):?array
    {
        $stmt=$this->db->prepare(
            'SELECT campaign_limit,group_limit FROM f64_limits'
            .' WHERE tenant=? AND channel=?'
        );
        $stmt->execute([$tenant,$channel]);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function configure(string $tenant,string $channel,int $campaigns,int $groups):string
    {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::channel($channel);
        if($campaigns<0||$campaigns>100||$groups<0||$groups>1000){
            throw new InvalidArgumentException('Invalid synthetic capacity');
        }
        return $this->transaction(function()use($tenant,$channel,$campaigns,$groups):string{
            if($this->used('f64_campaigns',$tenant,$channel)>$campaigns
               ||$this->used('f64_groups',$tenant,$channel)>$groups){
                return 'reduction_below_usage_denied';
            }
            $q=$this->db->prepare(
                'INSERT INTO f64_limits(tenant,channel,campaign_limit,group_limit)'
                .' VALUES(?,?,?,?)'
                .' ON CONFLICT(tenant,channel) DO UPDATE SET'
                .' campaign_limit=excluded.campaign_limit,group_limit=excluded.group_limit'
            );
            $q->execute([$tenant,$channel,$campaigns,$groups]);
            return 'capacity_configured_demo';
        });
    }

    public function addCampaign(string $tenant,string $channel,string $campaign):string
    {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::channel($channel);self::id($campaign,'cam-');
        return $this->transaction(function()use($tenant,$channel,$campaign):string{
            $q=$this->db->prepare('SELECT 1 FROM f64_campaigns WHERE ref=?');
            $q->execute([$campaign]);
            if($q->fetchColumn())return 'already_exists_or_out_of_scope';
            $limits=$this->limit($tenant,$channel);
            if($limits===null)return 'capacity_unapproved_denied';
            if($this->used('f64_campaigns',$tenant,$channel)>=(int)$limits['campaign_limit']){
                return 'capacity_review_required';
            }
            $q=$this->db->prepare(
                'INSERT INTO f64_campaigns(ref,tenant,channel,state,managed)'
                ." VALUES(?,?,?,'active_demo',1)"
            );
            $q->execute([$campaign,$tenant,$channel]);
            return 'included_synthetic_only';
        });
    }

    public function addGroup(string $tenant,string $campaign,string $group):string
    {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::id($campaign,'cam-');self::id($group,'grp-');
        return $this->transaction(function()use($tenant,$campaign,$group):string{
            $q=$this->db->prepare('SELECT 1 FROM f64_groups WHERE ref=?');
            $q->execute([$group]);
            if($q->fetchColumn())return 'already_exists_or_out_of_scope';
            $q=$this->db->prepare(
                'SELECT channel FROM f64_campaigns WHERE ref=? AND tenant=? AND managed=1'
            );
            $q->execute([$campaign,$tenant]);
            $channel=$q->fetchColumn();
            if(!is_string($channel))return 'campaign_not_found';
            $limits=$this->limit($tenant,$channel);
            if($limits===null)return 'capacity_unapproved_denied';
            if($this->used('f64_groups',$tenant,$channel)>=(int)$limits['group_limit']){
                return 'capacity_review_required';
            }
            $q=$this->db->prepare(
                'INSERT INTO f64_groups(ref,tenant,campaign_ref,state,managed)'
                ." VALUES(?,?,?,'active_demo',1)"
            );
            $q->execute([$group,$tenant,$campaign]);
            return 'included_synthetic_only';
        });
    }

    public function pauseCampaign(string $tenant,string $campaign):string
    {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::id($campaign,'cam-');
        return $this->transaction(function()use($tenant,$campaign):string{
            $q=$this->db->prepare(
                'SELECT state FROM f64_campaigns WHERE ref=? AND tenant=? AND managed=1'
            );
            $q->execute([$campaign,$tenant]);
            $row=$q->fetchColumn();
            if(!is_string($row))return 'not_found';
            if($row==='paused_demo')return 'already_paused_demo';
            $q=$this->db->prepare(
                "UPDATE f64_campaigns SET state='paused_demo' WHERE ref=? AND tenant=?"
            );
            $q->execute([$campaign,$tenant]);
            return 'paused_synthetic_only';
        });
    }

    public function replaceCampaign(
        string $tenant,string $request,string $old,string $new,
        bool $recommendedMock,bool $approvedMock
    ):string {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::id($request,'req-demo-');
        self::id($old,'cam-');self::id($new,'cam-');
        if(!$recommendedMock)return 'recommendation_required';
        if(!$approvedMock)return 'human_review_required';
        return $this->transaction(function()use($tenant,$request,$old,$new):string{
            $q=$this->db->prepare(
                'SELECT tenant,old_ref,new_ref FROM f64_swaps WHERE request_ref=?'
            );
            $q->execute([$request]);
            $prev=$q->fetch();
            if(is_array($prev)){
                if($prev['tenant']===$tenant&&$prev['old_ref']===$old
                   &&$prev['new_ref']===$new){
                    return 'already_applied_synthetic';
                }
                return 'request_conflict_or_out_of_scope';
            }
            $q=$this->db->prepare(
                'SELECT channel FROM f64_campaigns WHERE ref=? AND tenant=? AND managed=1'
            );
            $q->execute([$old,$tenant]);
            $channel=$q->fetchColumn();
            if(!is_string($channel))return 'managed_campaign_not_found';
            $q=$this->db->prepare('SELECT 1 FROM f64_campaigns WHERE ref=?');
            $q->execute([$new]);
            if($q->fetchColumn())return 'already_exists_or_out_of_scope';
            $this->db->prepare(
                "UPDATE f64_campaigns SET managed=0,state='outside_scope_demo'"
                ." WHERE ref=? AND tenant=?"
            )->execute([$old,$tenant]);
            $this->db->prepare(
                "UPDATE f64_groups SET managed=0,state='outside_scope_demo'"
                ." WHERE campaign_ref=? AND tenant=?"
            )->execute([$old,$tenant]);
            $this->db->prepare(
                "INSERT INTO f64_campaigns(ref,tenant,channel,state,managed)"
                ." VALUES(?,?,?,'draft_demo',1)"
            )->execute([$new,$tenant,$channel]);
            $this->db->prepare(
                "INSERT INTO f64_swaps(request_ref,tenant,old_ref,new_ref,result)"
                ." VALUES(?,?,?,?,'replaced_synthetic_no_fee')"
            )->execute([$request,$tenant,$old,$new]);
            return 'replaced_synthetic_no_fee';
        });
    }

    public function capacity(string $tenant,string $channel):array
    {
        if(!self::enabled())return ['status'=>'lab_disabled','dispatch_enabled'=>false];
        self::id($tenant,'perf-demo-');self::channel($channel);
        $limits=$this->limit($tenant,$channel);
        $campaigns=$this->used('f64_campaigns',$tenant,$channel);
        $groups=$this->used('f64_groups',$tenant,$channel);
        return [
            'status'=>$limits===null?'unconfigured_blocked_demo':'configured_synthetic_only',
            'tenant_ref'=>$tenant,
            'channel'=>$channel,
            'campaigns'=>[
                'used'=>$campaigns,
                'limit'=>$limits===null?null:(int)$limits['campaign_limit'],
                'remaining'=>$limits===null?null:(int)$limits['campaign_limit']-$campaigns,
            ],
            'groups'=>[
                'used'=>$groups,
                'limit'=>$limits===null?null:(int)$limits['group_limit'],
                'remaining'=>$limits===null?null:(int)$limits['group_limit']-$groups,
            ],
            'read_only'=>true,'contracted'=>false,
            'ready_for_production'=>false,'billing_enabled'=>false,
            'dispatch_enabled'=>false,'commercial'=>'not_homologated',
        ];
    }

    public function campaignState(string $tenant,string $campaign):string
    {
        if(!self::enabled())return 'lab_disabled';
        self::id($tenant,'perf-demo-');self::id($campaign,'cam-');
        $q=$this->db->prepare(
            'SELECT state FROM f64_campaigns WHERE ref=? AND tenant=?'
        );
        $q->execute([$campaign,$tenant]);
        $v=$q->fetchColumn();
        return is_string($v)?$v:'not_found';
    }
}
