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
}
