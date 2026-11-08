<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-02-C migrations require a dedicated PostgreSQL connection.');
        }

        Schema::create('operational_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('actor_ref', 128);
            $table->string('role', 32);
            $table->string('status', 32)->default('inactive');
            $table->jsonb('scopes');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id', 'membership_tenant_fk')
                ->references('id')->on('performance_tenants')->restrictOnDelete();
            $table->unique(['tenant_id', 'actor_ref', 'role'], 'membership_tenant_actor_role_uq');
            $table->index(['tenant_id', 'status'], 'membership_tenant_status_idx');
        });

        Schema::create('service_eligibility_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_service_ref', 128);
            $table->string('state', 32);
            $table->string('source', 64);
            $table->string('source_revision', 128);
            $table->timestampTz('observed_at');
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'portal_service_ref'], 'eligibility_link_fk')
                ->references(['tenant_id', 'portal_service_ref'])
                ->on('portal_service_links')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'portal_service_ref', 'source_revision'],
                'eligibility_tenant_service_revision_uq'
            );
            $table->index(['tenant_id', 'portal_service_ref', 'observed_at'], 'eligibility_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_eligibility_snapshots');
        Schema::dropIfExists('operational_memberships');
    }
};
