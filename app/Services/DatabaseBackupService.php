<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public const DISK_DIRECTORY = 'backups';

    /**
     * Create a timestamped backup of the active database connection.
     */
    public function create(): string
    {
        Storage::makeDirectory(self::DISK_DIRECTORY);

        $driver = (string) config('database.default');
        if ($driver === 'sqlite') {
            return $this->createSqliteBackup();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->createMysqlBackup();
        }

        throw new RuntimeException('Database backups are not configured for the '.$driver.' connection.');
    }

    /**
     * @return Collection<int, string>
     */
    public function list(): Collection
    {
        return collect(Storage::files(self::DISK_DIRECTORY))
            ->filter(fn (string $path) => (bool) preg_match('/\/(?:backup|pre_restore)_[0-9_-]+\.(sqlite|sql)$/', str_replace('\\', '/', $path)))
            ->sortDesc()
            ->values();
    }

    public function isDownloadable(string $filename): bool
    {
        return (bool) preg_match('/^(?:backup|pre_restore)_[0-9_-]+\.(sqlite|sql)$/', $filename);
    }

    public function restoreUploaded(string $sourcePath, string $originalName): string
    {
        $driver = (string) config('database.default');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($driver === 'sqlite') {
            if (! in_array($extension, ['sqlite', 'db'], true)) {
                throw new RuntimeException('The uploaded file is not a valid SQLite database.');
            }

            return $this->restoreSqliteFile($sourcePath);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if ($extension !== 'sql') {
                throw new RuntimeException('The live database is MySQL. Restore a .sql backup created by this application.');
            }

            return $this->restoreMysqlFile($sourcePath);
        }

        throw new RuntimeException('Database restore is not configured for the '.$driver.' connection.');
    }

    public function driverLabel(): string
    {
        return match ((string) config('database.default')) {
            'mysql', 'mariadb' => 'MySQL',
            'sqlite' => 'SQLite',
            default => (string) config('database.default'),
        };
    }

    private function createSqliteBackup(): string
    {
        $source = (string) config('database.connections.sqlite.database');
        if ($source === ':memory:' || $source === '') {
            throw new RuntimeException('The in-memory SQLite database cannot be copied as a file.');
        }
        if (! is_file($source)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $name = self::DISK_DIRECTORY.'/backup_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        Storage::put($name, (string) file_get_contents($source));

        return $name;
    }

    private function createMysqlBackup(): string
    {
        $name = self::DISK_DIRECTORY.'/backup_'.now()->format('Y-m-d_H-i-s').'.sql';
        $binary = $this->mysqlBinary('mysqldump');
        if ($binary !== null) {
            try {
                Storage::put($name, $this->runMysqlClient($binary, true));

                return $name;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Storage::put($name, $this->phpMysqlDump());

        return $name;
    }

    private function restoreSqliteFile(string $sourcePath): string
    {
        $header = (string) file_get_contents($sourcePath, false, null, 0, 16);
        if ($header !== "SQLite format 3\0") {
            throw new RuntimeException('The uploaded file is not a valid SQLite database.');
        }

        $current = (string) config('database.connections.sqlite.database');
        if ($current === ':memory:' || $current === '' || ! is_file($current)) {
            throw new RuntimeException('The current SQLite database file could not be replaced.');
        }

        Storage::makeDirectory(self::DISK_DIRECTORY);
        $safety = self::DISK_DIRECTORY.'/pre_restore_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        Storage::put($safety, (string) file_get_contents($current));

        DB::disconnect('sqlite');
        if (! copy($sourcePath, $current)) {
            throw new RuntimeException('Database restore failed.');
        }

        return $safety;
    }

    private function restoreMysqlFile(string $sourcePath): string
    {
        $safety = $this->createMysqlBackup();
        $safetyName = str_replace(
            '/backup_',
            '/pre_restore_',
            $safety,
        );
        if ($safetyName !== $safety && Storage::exists($safety)) {
            Storage::move($safety, $safetyName);
            $safety = $safetyName;
        }

        $binary = $this->mysqlBinary('mysql');
        if ($binary === null) {
            throw new RuntimeException('MySQL restore requires the mysql client on the server. The safety backup was kept. REQUIRES SERVER VERIFICATION.');
        }

        $this->runMysqlClient($binary, false, $sourcePath);

        return $safety;
    }

    private function mysqlBinary(string $name): ?string
    {
        $configured = trim((string) config($name === 'mysqldump' ? 'database.mysqldump_path' : 'database.mysql_client_path', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $name]);
        $finder->setTimeout(10);
        $finder->run();
        if ($finder->isSuccessful()) {
            $path = trim(strtok($finder->getOutput(), "\n"));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function runMysqlClient(string $binary, bool $dump, ?string $inputFile = null): string
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection);
        $cnf = $this->writeMysqlDefaultsFile($config);

        try {
            $arguments = [
                $binary,
                '--defaults-extra-file='.$cnf,
            ];
            if ($dump) {
                $arguments[] = '--single-transaction';
                $arguments[] = '--no-tablespaces';
                $arguments[] = (string) ($config['database'] ?? '');
            } else {
                $arguments[] = (string) ($config['database'] ?? '');
            }

            $process = new Process($arguments);
            $process->setTimeout(300);
            if ($inputFile !== null) {
                $process->setInput((string) file_get_contents($inputFile));
            }
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException($dump ? 'mysqldump failed.' : 'mysql restore failed.');
            }

            return $process->getOutput();
        } finally {
            @unlink($cnf);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeMysqlDefaultsFile(array $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mycnf');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary MySQL client configuration file.');
        }

        $password = str_replace(['\\', '"'], ['\\\\', '\"'], (string) ($config['password'] ?? ''));
        $contents = "[client]\n"
            .'host="'.str_replace('"', '\"', (string) ($config['host'] ?? '127.0.0.1'))."\"\n"
            .'port='.((int) ($config['port'] ?? 3306))."\n"
            .'user="'.str_replace('"', '\"', (string) ($config['username'] ?? ''))."\"\n"
            .'password="'.$password."\"\n";

        file_put_contents($path, $contents);

        return $path;
    }

    private function phpMysqlDump(): string
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $lines = [
            '-- OmniChannel MySQL backup',
            '-- Database: '.$database,
            'SET FOREIGN_KEY_CHECKS=0;',
        ];

        foreach (Schema::getTableListing() as $table) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
                continue;
            }
            $create = DB::select('SHOW CREATE TABLE `'.$table.'`');
            $statement = $create[0]->{'Create Table'} ?? ($create[0]->{'Create View'} ?? null);
            if (! is_string($statement) || $statement === '') {
                continue;
            }
            $lines[] = 'DROP TABLE IF EXISTS `'.$table.'`;';
            $lines[] = $statement.';';

            $columns = Schema::getColumnListing($table);
            $quotedColumns = implode(',', array_map(fn (string $column) => '`'.$column.'`', $columns));
            $writeRows = function ($rows) use (&$lines, $table, $columns, $quotedColumns): void {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        $values[] = $value === null ? 'NULL' : DB::getPdo()->quote((string) $value);
                    }
                    $lines[] = 'INSERT INTO `'.$table.'` ('.$quotedColumns.') VALUES ('.implode(',', $values).');';
                }
            };

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id')->chunkById(100, $writeRows);
            } else {
                $writeRows($query->get());
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines)."\n";
    }
}
