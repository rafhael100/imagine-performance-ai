<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-104: replace superuser-owned SECURITY DEFINER F27 trigger function
 * with a dedicated NOLOGIN / NOBYPASSRLS role allowed only to INSERT
 * strictly synthetic terminal-review rows into the journal table.
 *
 * This is a disposable PostgreSQL CI design rehearsal. Before production
 * AppSec/DBA must review role ownership, deployment grants and trigger paths.
 */
return new class extends Migration
{
    public function up():void
    {
        if(DB::connection()->getDriverName()!=='pgsql') {
            throw new RuntimeException('F104 PostgreSQL definer owner required');
        }
        DB::unprepared(<<<'SQL'
DO $f104$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_catalog.pg_roles WHERE rolname='perf_f104_journal_writer') THEN
        RAISE EXCEPTION 'F104 owner role already exists: refuse to assume ownership';
    END IF;
    CREATE ROLE perf_f104_journal_writer
      NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
END
$f104$;
SQL);
        DB::statement('GRANT USAGE ON SCHEMA public TO perf_f104_journal_writer');
        DB::statement(
            'GRANT INSERT ON TABLE public.performance_intent_review_journal '
            .'TO perf_f104_journal_writer'
        );
        DB::statement(<<<'SQL'
CREATE POLICY perf_f104_writer_synthetic_insert
ON public.performance_intent_review_journal
FOR INSERT TO perf_f104_journal_writer
WITH CHECK (
    source = 'synthetic_postgresql_only'
    AND synthetic_only = true
    AND review_state IN ('approved_internal','rejected')
)
SQL);
        DB::statement(
            'ALTER FUNCTION public.perf_f27_capture_review_transition() '
            .'OWNER TO perf_f104_journal_writer'
        );
        DB::statement(
            'ALTER FUNCTION public.perf_f27_capture_review_transition() '
            .'SET search_path = pg_catalog'
        );
        DB::statement(
            'REVOKE ALL ON FUNCTION public.perf_f27_capture_review_transition() FROM PUBLIC'
        );
    }

    public function down():void
    {
        if(DB::connection()->getDriverName()!=='pgsql'){
            throw new RuntimeException('F104 rollback requires PostgreSQL');
        }
        DB::statement(
            'ALTER FUNCTION public.perf_f27_capture_review_transition() OWNER TO postgres'
        );
        DB::statement(
            'DROP POLICY IF EXISTS perf_f104_writer_synthetic_insert '
            .'ON public.performance_intent_review_journal'
        );
        DB::statement(
            'REVOKE INSERT ON TABLE public.performance_intent_review_journal '
            .'FROM perf_f104_journal_writer'
        );
        DB::statement('REVOKE USAGE ON SCHEMA public FROM perf_f104_journal_writer');
        DB::statement('DROP ROLE perf_f104_journal_writer');
        // F103 search_path hardening is deliberately retained.
    }
};
