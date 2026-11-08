<?php
declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Real PostgreSQL RLS checks, gated behind an explicitly disposable test DB.
 * NEVER run against development, staging, or production data.
 */
final class TenantIsolationPgsqlTest extends TestCase
{
    private const A = '11111111-1111-4111-8111-111111111111';
    private const B = '22222222-2222-4222-8222-222222222222';

    private function assertDisposablePostgres(): void
    {
        if (getenv('RUN_PGSQL_ISOLATION_TESTS') !== '1') {
            self::markTestSkipped('RUN_PGSQL_ISOLATION_TESTS=1 not set; real PostgreSQL validation is pending.');
        }

        self::assertSame('testing', app()->environment(), 'Refuse DB tests outside APP_ENV=testing.');
        self::assertSame('pgsql', config('database.default'), 'Refuse tests on a non-PostgreSQL connection.');
        $database = (string) config('database.connections.pgsql.database');
        self::assertMatchesRegularExpression('/^perf_test_[a-zA-Z0-9_]+$/', $database, 'Use a disposable perf_test_ database only.');
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertTrue(Schema::hasTable('performance_tenants'), 'Migrations must be applied to the test database first.');
    }

    private function setTenant(string $id): void
    {
        // The fixture is trusted only inside this test; production must authenticate
        // tenant identity before setting this transaction-local PostgreSQL GUC.
        DB::select("SELECT set_config('app.performance_tenant_id', ?, true)", [$id]);
    }

    public function test_rls_denies_visibility_between_two_tenants(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
            $this->setTenant(self::B);
            DB::table('performance_tenants')->insert(['id' => self::B, 'status' => 'active']);

            $this->setTenant(self::A);
            self::assertSame([self::A], DB::table('performance_tenants')->orderBy('id')->pluck('id')->all());
            $this->setTenant(self::B);
            self::assertSame([self::B], DB::table('performance_tenants')->orderBy('id')->pluck('id')->all());
        } finally {
            DB::rollBack();
        }
    }

    public function test_rls_denies_cross_tenant_write(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            $this->expectException(QueryException::class);
            DB::table('performance_tenants')->insert(['id' => self::B, 'status' => 'active']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rls_denies_insert_without_transaction_tenant_context(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            DB::select("SELECT set_config('app.performance_tenant_id', '', true)");
            $this->expectException(QueryException::class);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rls_separates_portal_links_by_tenant(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
            DB::table('portal_service_links')->insert([
                'id' => '33333333-3333-4333-8333-333333333333',
                'tenant_id' => self::A,
                'portal_organization_ref' => 'org-demo-a',
                'portal_service_ref' => 'svc-demo-a',
                'link_status' => 'active_demo',
                'source_revision' => 'demo-r1',
            ]);

            $this->setTenant(self::B);
            DB::table('performance_tenants')->insert(['id' => self::B, 'status' => 'active']);
            self::assertSame(0, DB::table('portal_service_links')->where('portal_service_ref', 'svc-demo-a')->count());
            $this->setTenant(self::A);
            self::assertSame(1, DB::table('portal_service_links')->where('portal_service_ref', 'svc-demo-a')->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_composite_foreign_key_rejects_service_of_another_tenant(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
            DB::table('portal_service_links')->insert([
                'id' => '33333333-3333-4333-8333-333333333333',
                'tenant_id' => self::A,
                'portal_organization_ref' => 'org-demo-a',
                'portal_service_ref' => 'svc-demo-a',
                'link_status' => 'active_demo',
                'source_revision' => 'demo-r1',
            ]);
            $this->setTenant(self::B);
            DB::table('performance_tenants')->insert(['id' => self::B, 'status' => 'active']);

            $this->expectException(QueryException::class);
            DB::table('service_eligibility_snapshots')->insert([
                'id' => '44444444-4444-4444-8444-444444444444',
                'tenant_id' => self::B,
                'portal_service_ref' => 'svc-demo-a',
                'state' => 'eligible_mock',
                'source' => 'portal_demo_fixture',
                'source_revision' => 'demo-r2',
                'observed_at' => now(),
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_replay_id_is_unique_across_tenants_for_one_producer(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
            DB::table('idempotency_receipts')->insert([
                'id' => '55555555-5555-4555-8555-555555555555',
                'tenant_id' => self::A,
                'producer_ref' => 'portal-imagine-mock',
                'event_ref' => 'evt-demo-replay-1',
                'payload_sha256' => str_repeat('a', 64),
                'processing_state' => 'accepted_demo',
                'received_at' => now(),
            ]);

            $this->setTenant(self::B);
            DB::table('performance_tenants')->insert(['id' => self::B, 'status' => 'active']);
            $this->expectException(QueryException::class);
            DB::table('idempotency_receipts')->insert([
                'id' => '66666666-6666-4666-8666-666666666666',
                'tenant_id' => self::B,
                'producer_ref' => 'portal-imagine-mock',
                'event_ref' => 'evt-demo-replay-1',
                'payload_sha256' => str_repeat('b', 64),
                'processing_state' => 'accepted_demo',
                'received_at' => now(),
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rls_prevents_reading_other_tenants_audit(): void
    {
        $this->assertDisposablePostgres();
        DB::beginTransaction();

        try {
            $this->setTenant(self::A);
            DB::table('performance_tenants')->insert(['id' => self::A, 'status' => 'active']);
            DB::table('audit_events')->insert([
                'id' => '77777777-7777-4777-8777-777777777777',
                'tenant_id' => self::A,
                'actor_ref' => 'actor-demo-a',
                'action' => 'mock.read',
                'decision' => 'allowed_demo',
                'occurred_at' => now(),
            ]);
            self::assertSame(1, DB::table('audit_events')->count());
            $this->setTenant(self::B);
            self::assertSame(0, DB::table('audit_events')->count());
        } finally {
            DB::rollBack();
        }
    }

}
