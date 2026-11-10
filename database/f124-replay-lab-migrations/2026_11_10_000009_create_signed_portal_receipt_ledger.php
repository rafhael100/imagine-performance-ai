<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F124: ephemeral SQL nonce receipts for the F123 signed Portal CI bridge.
 *
 * NOT a production replay fence: cannot survive restoring an older backup,
 * is not an independent Portal authority or durable external epoch source,
 * and intentionally accepts only synthetic F29 claims.
 *
 * Ownership and access are separated. NOLOGIN owner owns receipt table;
 * authenticated writer may INSERT only (no SELECT/UPDATE/DELETE).
 * The receipt ledger is intentionally never deleted in the lab.
 */
return new class extends Migration
{
    public function up():void
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F124_SIGNED_NONCE_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'
            ||(int)DB::scalar('SHOW server_version_num')<160000
            ||(int)DB::scalar('SHOW server_version_num')>=170000){
            throw new RuntimeException('F124 receipts migration confined to disposable PG16 CI');
        }

        $present=(int)DB::scalar(
            "SELECT count(*) FROM pg_catalog.pg_roles "
            ."WHERE rolname IN ('perf_f124_receipt_owner','perf_f124_receipt_writer')"
        );
        if($present!==0){
            throw new RuntimeException('F124 role identifiers already exist before migration');
        }
        if(DB::scalar(
            "SELECT to_regclass('public.performance_signed_portal_demo_receipts')::text"
        )!==null){
            throw new RuntimeException('F124 receipt table already exists before migration');
        }

        DB::statement(
            'CREATE ROLE perf_f124_receipt_owner '
            .'NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT '
            .'NOBYPASSRLS NOREPLICATION'
        );
        DB::statement(
            "CREATE ROLE perf_f124_receipt_writer "
            ."LOGIN PASSWORD 'ci-f124-receipt-write-only' "
            ."NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT "
            ."NOBYPASSRLS NOREPLICATION"
        );
        DB::statement(
            'CREATE TABLE public.performance_signed_portal_demo_receipts ('
            .'nonce_sha256 char(64) PRIMARY KEY,'
            .'seen_epoch bigint NOT NULL CHECK (seen_epoch>=1700000000 '
            .'AND seen_epoch<=2100000000),'
            .'CONSTRAINT perf_f124_nonce_digest_hex '
            ."CHECK (nonce_sha256 ~ '^[a-f0-9]{64}$')"
            .')'
        );
        DB::statement(
            'ALTER TABLE public.performance_signed_portal_demo_receipts '
            .'OWNER TO perf_f124_receipt_owner'
        );
        DB::statement(
            'ALTER TABLE public.performance_signed_portal_demo_receipts '
            .'ENABLE ROW LEVEL SECURITY'
        );
        DB::statement(
            'ALTER TABLE public.performance_signed_portal_demo_receipts '
            .'FORCE ROW LEVEL SECURITY'
        );
        DB::statement(
            'REVOKE ALL ON TABLE public.performance_signed_portal_demo_receipts FROM PUBLIC'
        );
        DB::statement(
            'GRANT CONNECT ON DATABASE perf_test_campaign_registry '
            .'TO perf_f124_receipt_writer'
        );
        DB::statement(
            'GRANT USAGE ON SCHEMA public TO perf_f124_receipt_writer'
        );
        DB::statement(
            'GRANT INSERT ON TABLE public.performance_signed_portal_demo_receipts '
            .'TO perf_f124_receipt_writer'
        );
        DB::statement(
            'CREATE POLICY perf_f124_receipt_insert_only '
            .'ON public.performance_signed_portal_demo_receipts '
            .'AS PERMISSIVE FOR INSERT TO perf_f124_receipt_writer '
            .'WITH CHECK (true)'
        );
    }

    public function down():void
    {
        // Deleting a nonce journal enables replay of an old signed claim.
        // CI migration is forward-only and not part of operational deploy.
        throw new RuntimeException('F124 forbids automatic replay-ledger rollback');
    }
};
