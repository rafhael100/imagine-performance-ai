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

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('actor_ref', 128)->nullable();
            $table->string('action', 128);
            $table->string('resource_ref', 128)->nullable();
            $table->string('decision', 32);
            $table->string('reason_code', 64)->nullable();
            $table->string('correlation_ref', 128)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id', 'audit_tenant_fk')
                ->references('id')->on('performance_tenants')->restrictOnDelete();
            $table->index(['tenant_id', 'occurred_at'], 'audit_tenant_occurred_idx');
            $table->index(['tenant_id', 'correlation_ref'], 'audit_tenant_correlation_idx');
        });

        Schema::create('idempotency_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('producer_ref', 128);
            $table->string('event_ref', 128);
            $table->char('payload_sha256', 64);
            $table->string('processing_state', 32);
            $table->timestampTz('received_at');
            $table->timestampsTz();

            $table->foreign('tenant_id', 'idempotency_tenant_fk')
                ->references('id')->on('performance_tenants')->restrictOnDelete();
            // Global per producer: a replay for tenant B must not hide as a new event
            // when the event ID was previously accepted for tenant A.
            $table->unique(['producer_ref', 'event_ref'], 'idempotency_producer_event_uq');
            $table->index(['tenant_id', 'received_at'], 'idempotency_tenant_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_receipts');
        Schema::dropIfExists('audit_events');
    }
};
