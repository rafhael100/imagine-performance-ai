<?php
declare(strict_types=1);

namespace Imagine\Performance\Domain;

use PDO;
use Throwable;

/**
 * FAST-PERF F1-71 — synthetic SQLite checkpoint outside F66 PostgreSQL.
 *
 * A recorded intent is persisted BEFORE an F66 mock PG operation. This
 * fail-closed ordering avoids duplicate retries after a PG-only stale restore,
 * but may strand an intent if PG rejects/crashes and is NOT distributed atomic.
 *
 * The checkpoint itself is mutable/restorable, PostgreSQL writer functions
 * remain callable directly, and SHA-256 is NOT a trusted signature. Therefore
 * this is strictly a CI risk demonstration, NEVER a production authorization.
 */
final class SyntheticPgIndependentReplayCheckpoint
{
    private readonly PDO $pg;
    private readonly PDO $sqlite;
    private const TENANT='perf_f66_tenant_a';

    private static function enabled():bool
    {
        return PHP_SAPI==='cli'
            && getenv('APP_ENV')==='testing'
            && getenv('GITHUB_ACTIONS')==='true'
            && getenv('PERF_F66_POSTGRES_RLS_LAB')==='1'
            && getenv('PERF_F71_CHECKPOINT_LAB')==='1';
    }

    public function __construct(PDO $pg,PDO $sqlite)
    {
        if(!self::enabled()
           ||$pg->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
           ||$sqlite->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'
           ||$pg->inTransaction()||$sqlite->inTransaction()){
            throw new \RuntimeException('F71 requires isolated CI PDO connections');
        }
        $where=$sqlite->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
        if(!is_array($where)||count($where)!==1
           ||($where[0]['name']??null)!=='main'){
            throw new \RuntimeException('F71 accepts exactly one SQLite file');
        }
        $file=$where[0]['file']??null;
        $path=is_string($file)?realpath($file):false;
        $root=realpath(sys_get_temp_dir());
        if(!is_string($path)||!is_string($root)
           ||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)
           ||!preg_match('/^perf-f71-[a-z0-9-]{1,60}\.sqlite$/D',
                basename($path))
           ||is_link($file)){
            throw new \RuntimeException('F71 checkpoint must be a disposable temp SQLite file');
        }
        $identity=$pg->query(
            'SELECT session_user::text AS login,current_user::text AS effective,'
            ." current_database()::text AS db,current_setting('row_security')::text AS rls"
        )->fetch(PDO::FETCH_ASSOC);
        if(!is_array($identity)
           ||($identity['login']??null)!==self::TENANT
           ||($identity['effective']??null)!==self::TENANT
           ||($identity['db']??null)!=='perf_f66_ci'
           ||($identity['rls']??null)!=='on'){
            throw new \RuntimeException('F71 requires the fixed mock A PostgreSQL role');
        }
        $pg->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA busy_timeout=8000');
        $sqlite->exec('PRAGMA journal_mode=DELETE');
        $sqlite->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS f71_intents (
    tenant_role TEXT NOT NULL CHECK(tenant_role='perf_f66_tenant_a'),
    request_ref TEXT NOT NULL CHECK(request_ref GLOB 'req-demo-*'),
    campaign_ref TEXT NOT NULL CHECK(campaign_ref GLOB 'cam-*'),
    manifest_sha256 TEXT NOT NULL CHECK(length(manifest_sha256)=64),
    state TEXT NOT NULL CHECK(state='reserved_before_pg'),
    PRIMARY KEY(tenant_role,request_ref)
);
SQL
        );
        $this->pg=$pg;
        $this->sqlite=$sqlite;
    }

    public function reserveOnce(string $request,string $campaign,string $manifestSha):string
    {
        if(!self::enabled()||$this->pg->inTransaction()
           ||$this->sqlite->inTransaction()){
            return 'lab_or_transaction_disabled';
        }
        if(!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$request)
           ||!preg_match('/^cam-[a-z0-9-]{1,60}$/D',$campaign)
           ||!preg_match('/^[a-f0-9]{64}$/D',$manifestSha)){
            return 'invalid_synthetic_intent_denied';
        }
        try{
            $this->sqlite->exec('BEGIN IMMEDIATE');
            $q=$this->sqlite->prepare(
                'SELECT campaign_ref,manifest_sha256 FROM f71_intents'
                .' WHERE tenant_role=? AND request_ref=?'
            );
            $q->execute([self::TENANT,$request]);
            $previous=$q->fetch(PDO::FETCH_ASSOC);
            if(is_array($previous)){
                $this->sqlite->exec('COMMIT');
                return $previous['campaign_ref']===$campaign
                    &&hash_equals($previous['manifest_sha256'],$manifestSha)
                    ?'already_checkpointed_no_replay'
                    :'checkpoint_manifest_conflict_denied';
            }
            $insert=$this->sqlite->prepare(
                'INSERT INTO f71_intents'
                .'(tenant_role,request_ref,campaign_ref,manifest_sha256,state)'
                ." VALUES(?,?,?,?,'reserved_before_pg')"
            );
            $insert->execute([self::TENANT,$request,$campaign,$manifestSha]);
            // Commit checkpoint FIRST; failure after this point must not retry
            // automatically, regardless of the PostgreSQL outcome.
            $this->sqlite->exec('COMMIT');
        }catch(Throwable){
            if($this->sqlite->inTransaction()){
                try{$this->sqlite->exec('ROLLBACK');}catch(Throwable){}
            }
            return 'checkpoint_unavailable_fail_closed';
        }

        try{
            $q=$this->pg->prepare(
                'SELECT perf_f66_scope.reserve_campaign(?,?)'
            );
            $q->execute(['google_ads',$campaign]);
            $result=$q->fetchColumn();
            if($result==='included_synthetic_only'){
                return 'included_synthetic_only';
            }
            // The intent was consumed even if the PG operation failed.
            // Recovery requires human/mock reconciliation, not blind retry.
            return 'pg_denied_reconcile_required';
        }catch(Throwable){
            return 'pg_outcome_unknown_reconcile_required';
        }
    }

    public function countCheckpointed():?int
    {
        if(!self::enabled())return null;
        try{
            $q=$this->sqlite->prepare(
                'SELECT count(*) FROM f71_intents WHERE tenant_role=?'
            );
            $q->execute([self::TENANT]);
            return (int)$q->fetchColumn();
        }catch(Throwable){
            return null;
        }
    }

    public function checkpointFor(string $request):?array
    {
        if(!self::enabled()
           ||!preg_match('/^req-demo-[a-z0-9-]{1,60}$/D',$request)){
            return null;
        }
        try{
            $q=$this->sqlite->prepare(
                'SELECT request_ref,campaign_ref,state,manifest_sha256'
                .' FROM f71_intents WHERE tenant_role=? AND request_ref=?'
            );
            $q->execute([self::TENANT,$request]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            return is_array($row)?$row:null;
        }catch(Throwable){
            return null;
        }
    }
}
