<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-103: harden existing F27 SECURITY DEFINER trigger helper.
 * The F27 body schema-qualifies every user table; pg_catalog supplies
 * PostgreSQL builtins including gen_random_uuid on PG16.
 *
 * This is a defense-in-depth lab migration, NOT verified production safety.
 */
return new class extends Migration
{
    public function up(): void
    {
        if(DB::connection()->getDriverName()!=='pgsql'){
            throw new RuntimeException('F103 hardening requires PostgreSQL16');
        }
        DB::statement(
            'ALTER FUNCTION public.perf_f27_capture_review_transition() '
            .'SET search_path=pg_catalog'
        );
        DB::statement(
            'REVOKE ALL ON FUNCTION public.perf_f27_capture_review_transition() FROM PUBLIC'
        );
    }

    public function down(): void
    {
        if(DB::connection()->getDriverName()!=='pgsql'){
            throw new RuntimeException('F103 rollback requires PostgreSQL16');
        }
        // Deliberately do NOT revert the security hardening to the previous
        // pg_catalog,public path. A production rollback requires reviewed
        // DB authorization and must not silently weaken function lookup.
    }
};
