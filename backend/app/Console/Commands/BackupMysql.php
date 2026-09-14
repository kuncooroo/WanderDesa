<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Full MySQL dump for the WanderDesa ledger (SRS-BK-01 / SRS-BK-05).
 * Laravel remains the authority; this command only copies the store.
 */
class BackupMysql extends Command
{
    protected $signature = 'ops:backup-mysql {--dry-run : Print the target dump path without running mysqldump}';

    protected $description = 'Dump the MySQL database (no-op unless DB_CONNECTION=mysql)';

    public function handle(): int
    {
        if (! config('ops.backup.enabled')) {
            $this->warn('MySQL backups are disabled (BACKUP_MYSQL_ENABLED=false).');

            return self::SUCCESS;
        }

        $connection = (string) config('database.default');
        if ($connection !== 'mysql') {
            $this->warn("Skip backup: database connection [{$connection}] is not mysql.");

            return self::SUCCESS;
        }

        $directory = rtrim((string) config('ops.backup.path'), DIRECTORY_SEPARATOR);
        $filename = 'wanderdesa-'.now()->format('Ymd-His').'.sql';
        $target = $directory.DIRECTORY_SEPARATOR.$filename;

        if ($this->option('dry-run')) {
            $this->info($target);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($directory);

        $bin = (string) config('ops.backup.mysqldump_bin');
        $mysql = config('database.connections.mysql');
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (string) ($mysql['port'] ?? '3306');
        $database = (string) ($mysql['database'] ?? '');
        $username = (string) ($mysql['username'] ?? '');
        $password = (string) ($mysql['password'] ?? '');

        if ($database === '' || $username === '') {
            $this->error('MySQL database name and username are required for backups.');

            return self::FAILURE;
        }

        $result = Process::env([
            'MYSQL_PWD' => $password,
        ])->timeout(600)->run([
            $bin,
            '--single-transaction',
            '--routines',
            '--triggers',
            '-h'.$host,
            '-P'.$port,
            '-u'.$username,
            '--result-file='.$target,
            $database,
        ]);

        if ($result->failed()) {
            $this->error('mysqldump failed.');
            if (File::exists($target)) {
                File::delete($target);
            }

            return self::FAILURE;
        }

        $this->pruneOldDumps($directory);
        $this->info('Backup written: '.$target);

        return self::SUCCESS;
    }

    private function pruneOldDumps(string $directory): void
    {
        $days = (int) config('ops.backup.retention_days');
        $cutoff = now()->subDays($days)->getTimestamp();

        try {
            foreach (File::files($directory) as $file) {
                $name = $file->getFilename();
                if (! str_starts_with($name, 'wanderdesa-') || ! str_ends_with($name, '.sql')) {
                    continue;
                }

                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());
                }
            }
        } catch (Throwable) {
            $this->warn('Backup prune skipped (directory not readable).');
        }
    }
}
