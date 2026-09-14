<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupMysqlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_test_runtime_skips_dump(): void
    {
        $this->artisan('ops:backup-mysql')
            ->expectsOutputToContain('is not mysql')
            ->assertSuccessful();
    }

    public function test_disabled_backup_is_a_successful_no_op(): void
    {
        config(['ops.backup.enabled' => false]);

        $this->artisan('ops:backup-mysql')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_mysql_dry_run_prints_path_without_dumping(): void
    {
        $previous = config('database.default');

        config([
            'database.default' => 'mysql',
            'ops.backup.enabled' => true,
            'ops.backup.path' => storage_path('app/backups-test'),
        ]);

        try {
            $exit = Artisan::call('ops:backup-mysql', ['--dry-run' => true]);
            $output = Artisan::output();
        } finally {
            config(['database.default' => $previous]);
        }

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('backups-test', $output);
        $this->assertMatchesRegularExpression('/wanderdesa-\d{8}-\d{6}\.sql/', $output);
    }
}
