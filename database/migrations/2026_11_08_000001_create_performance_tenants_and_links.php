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

        Schema::create('performance_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32)->default('inactive');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('portal_service_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('portal_organization_ref', 128);
            $table->string('portal_service_ref', 128);
            $table->string('link_status', 32)->default('pending');
            $table->string('source_revision', 128);
            $table->timestampsTz();

            $table->foreign('tenant_id', 'portal_link_tenant_fk')
                ->references('id')->on('performance_tenants')->restrictOnDelete();
            $table->unique(['tenant_id', 'portal_service_ref'], 'portal_link_tenant_service_uq');
            $table->index(['tenant_id', 'portal_organization_ref'], 'portal_link_tenant_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_service_links');
        Schema::dropIfExists('performance_tenants');
    }
};
