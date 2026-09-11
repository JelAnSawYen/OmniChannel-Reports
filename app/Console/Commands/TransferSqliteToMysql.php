<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use RuntimeException;
use Throwable;

/**
 * One-off SQLite → MySQL data transfer. Does not modify application business logic.
 *
 * The SQLite source is opened with PDO::SQLITE_OPEN_READONLY against
 * database/database.sqlite and never uses the Laravel sqlite connection
 * (which would resolve DB_DATABASE to the MySQL database name).
 *
 * Default mode is a dry run. INSERT requires --execute plus confirmation.
 */
class TransferSqliteToMysql extends Command
{
    protected $signature = 'db:transfer-sqlite-to-mysql
        {--dry-run : Inspect source and target and print the transfer plan. Performs no writes (default if --execute is omitted).}
        {--execute : Perform the transfer. Required before any INSERT.}
        {--force : Skip the interactive confirmation prompt (still requires --execute).}';

    protected $description = 'Transfer approved application data from database/database.sqlite to MySQL omnichannel_reports';

    private const TARGET_DATABASE = 'omnichannel_reports';

    private const SESSION_TIME_ZONE = '+08:00';

    private const CHUNK_SIZE = 200;

    /** @var list<string> */
    private const TRANSFER_TABLES = [
        'user_types',
        'users',
        'media_gateways',
        'sip_channels',
        'archive_recordings',
        'globe_sims',
        'smart_sims',
        'program_inbound_numbers',
        'signal_boosters',
        'defective_gsms',
        'telco_costs',
        'channel_prefixes',
        'channel_ports',
        'network_prefixes',
        'channel_allocation_campaigns',
        'channel_allocations',
        'pdc_groups',
        'pdc_servers',
        'audit_logs',
        'login_logs',
        'mail_settings',
    ];

    /** @var list<string> */
    private const SKIP_TABLES = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /** @var array<string, list<string>> */
    private const DATE_ONLY_COLUMNS = [
        'defective_gsms' => ['reported_on'],
        'pdc_groups' => ['date_endorse'],
        'sip_channels' => ['date_activation'],
        'globe_sims' => ['contract_start', 'contract_end'],
        'smart_sims' => ['contract_start', 'contract_end'],
    ];

    private ?PDO $sqlite = null;

    private ?PDO $mysql = null;

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('Use either --dry-run or --execute, not both.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        try {
            $this->openSqliteReadOnly();
            $this->openMysql($execute);
            $this->assertReady();
            $plan = $this->buildPlan();
            $this->printBanner($execute);
            $this->printPlan($plan, $execute);

            if (! $execute) {
                $this->newLine();
                $this->info('DRY-RUN complete. No INSERT/UPDATE/DELETE/TRUNCATE/DROP was performed.');
                $this->line('To transfer data later: php artisan db:transfer-sqlite-to-mysql --execute');

                return self::SUCCESS;
            }

            if ($this->targetHasApplicationRows($plan)) {
                $this->error('MySQL already contains application rows. Refusing to insert duplicates.');
                $this->line('Empty the approved application tables first, or inspect the existing MySQL data.');

                return self::FAILURE;
            }

            $this->warn('This will INSERT records into MySQL '.self::TARGET_DATABASE.'. The SQLite source will not be modified.');

            if (! $this->option('force') && ! $this->confirm('Proceed with INSERT into MySQL '.self::TARGET_DATABASE.'?', false)) {
                $this->warn('Cancelled. No data was inserted.');

                return self::SUCCESS;
            }

            $this->transfer($plan);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->sqlite = null;
            $this->mysql = null;
        }

        return self::SUCCESS;
    }

    private function sqlitePath(): string
    {
        return database_path('database.sqlite');
    }

    private function openSqliteReadOnly(): void
    {
        $path = $this->sqlitePath();

        if (! is_file($path)) {
            throw new RuntimeException('SQLite source not found: '.$path);
        }

        if (! is_readable($path)) {
            throw new RuntimeException('SQLite source is not readable: '.$path);
        }

        $this->sqlite = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);
    }

    private function openMysql(bool $execute): void
    {
        $config = config('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            self::TARGET_DATABASE,
            $config['charset'] ?? 'utf8mb4',
        );

        $this->mysql = new PDO($dsn, (string) ($config['username'] ?? 'root'), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $actual = (string) $this->mysqlQuery('SELECT DATABASE()')->fetchColumn();
        if ($actual !== self::TARGET_DATABASE) {
            throw new RuntimeException('Expected MySQL database '.self::TARGET_DATABASE.', connected to: '.($actual !== '' ? $actual : '(none)'));
        }

        if ($execute) {
            $this->mysql->exec('SET time_zone = \''.self::SESSION_TIME_ZONE.'\'');
        }
    }

    private function assertReady(): void
    {
        $sqliteTables = $this->sqliteTableNames();
        $mysqlTables = $this->mysqlTableNames();

        foreach (self::TRANSFER_TABLES as $table) {
            if (! in_array($table, $sqliteTables, true)) {
                throw new RuntimeException("SQLite is missing required table [{$table}].");
            }
            if (! in_array($table, $mysqlTables, true)) {
                throw new RuntimeException("MySQL is missing required table [{$table}].");
            }

            $sqliteColumns = $this->sqliteColumns($table);
            $mysqlColumns = $this->mysqlColumns($table);
            $onlySqlite = array_diff($sqliteColumns, $mysqlColumns);
            $onlyMysql = array_diff($mysqlColumns, $sqliteColumns);

            if ($onlySqlite || $onlyMysql) {
                throw new RuntimeException(
                    "Column mismatch for [{$table}]. SQLite-only: ".implode(',', $onlySqlite).'; MySQL-only: '.implode(',', $onlyMysql)
                );
            }
        }
    }

    /**
     * @return list<array{table: string, sqlite: int, mysql: int, migrate: bool, reason: string}>
     */
    private function buildPlan(): array
    {
        $plan = [];

        foreach (self::TRANSFER_TABLES as $table) {
            $plan[] = [
                'table' => $table,
                'sqlite' => $this->sqliteCount($table),
                'mysql' => $this->mysqlCount($table),
                'migrate' => true,
                'reason' => 'Approved application table',
            ];
        }

        foreach (self::SKIP_TABLES as $table) {
            $sqlite = $this->tableExistsSqlite($table) ? $this->sqliteCount($table) : 0;
            $mysql = $this->tableExistsMysql($table) ? $this->mysqlCount($table) : 0;
            $plan[] = [
                'table' => $table,
                'sqlite' => $sqlite,
                'mysql' => $mysql,
                'migrate' => false,
                'reason' => $table === 'migrations'
                    ? 'MySQL already has its schema migration history'
                    : 'Runtime/cache/session/token data',
            ];
        }

        return $plan;
    }

    /**
     * @param  list<array{table: string, sqlite: int, mysql: int, migrate: bool, reason: string}>  $plan
     */
    private function printBanner(bool $execute): void
    {
        $this->newLine();
        $this->line('SOURCE: SQLite '.$this->sqlitePath().' (read-only)');
        $this->line('TARGET: MySQL '.self::TARGET_DATABASE);
        $this->line('MODE: '.($execute ? 'EXECUTE (writes to MySQL only)' : 'DRY-RUN (zero writes)'));
        $this->line('MySQL session time_zone for INSERT: '.self::SESSION_TIME_ZONE.' (Asia/Manila)');
        $this->newLine();
    }

    /**
     * @param  list<array{table: string, sqlite: int, mysql: int, migrate: bool, reason: string}>  $plan
     */
    private function printPlan(array $plan, bool $execute): void
    {
        $rows = [];
        foreach ($plan as $item) {
            $rows[] = [
                $item['table'],
                (string) $item['sqlite'],
                (string) $item['mysql'],
                $item['migrate'] ? 'YES' : 'NO',
                $item['reason'],
            ];
        }

        $this->table(['TABLE', 'SQLITE', 'MYSQL', 'MIGRATE?', 'REASON'], $rows);

        $toTransfer = array_sum(array_map(
            static fn (array $item): int => $item['migrate'] ? $item['sqlite'] : 0,
            $plan,
        ));

        $this->newLine();
        $this->line('Transfer order: '.implode(' → ', self::TRANSFER_TABLES));
        $this->line('Records that would be inserted: '.$toTransfer);
        $this->line($execute ? 'Chunk size: '.self::CHUNK_SIZE : 'Dry-run performs SELECT/SHOW only.');
    }

    /**
     * @param  list<array{table: string, sqlite: int, mysql: int, migrate: bool, reason: string}>  $plan
     */
    private function targetHasApplicationRows(array $plan): bool
    {
        foreach ($plan as $item) {
            if ($item['migrate'] && $item['mysql'] > 0) {
                $this->error("MySQL table [{$item['table']}] already has {$item['mysql']} row(s).");

                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{table: string, sqlite: int, mysql: int, migrate: bool, reason: string}>  $plan
     */
    private function transfer(array $plan): void
    {
        $expected = [];
        foreach ($plan as $item) {
            if ($item['migrate']) {
                $expected[$item['table']] = $item['sqlite'];
            }
        }

        $this->mysql->beginTransaction();

        try {
            foreach (self::TRANSFER_TABLES as $table) {
                $inserted = $this->transferTable($table);
                $mysqlCount = $this->mysqlCount($table);
                $sqliteCount = $expected[$table];

                $this->info("Migrating {$table}: {$inserted} records");

                if ($inserted !== $sqliteCount || $mysqlCount !== $sqliteCount) {
                    throw new RuntimeException(
                        "Count mismatch for [{$table}]: sqlite={$sqliteCount}, inserted={$inserted}, mysql={$mysqlCount}."
                    );
                }
            }

            $this->mysql->commit();
        } catch (Throwable $e) {
            if ($this->mysql->inTransaction()) {
                $this->mysql->rollBack();
            }

            throw new RuntimeException('Transfer stopped. MySQL transaction rolled back. SQLite was not modified. '.$e->getMessage(), 0, $e);
        }

        try {
            $this->applyAutoIncrements();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Rows were inserted and committed, but AUTO_INCREMENT could not be updated. SQLite was not modified. '.$e->getMessage(),
                0,
                $e
            );
        }

        $this->printVerification($expected);

        $this->newLine();
        $this->info('Transfer complete. SQLite source was not modified.');
    }

    private function transferTable(string $table): int
    {
        $columns = $this->mysqlColumns($table);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => '`'.$this->quoteIdentifier($column).'`', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertSql = "INSERT INTO `{$this->quoteIdentifier($table)}` ({$quotedColumns}) VALUES ({$placeholders})";
        $insert = $this->mysql->prepare($insertSql);

        $inserted = 0;
        $lastId = 0;

        while (true) {
            $select = $this->sqlitePrepare(
                'SELECT * FROM "'.$this->quoteIdentifier($table).'" WHERE "id" > ? ORDER BY "id" ASC LIMIT '.self::CHUNK_SIZE
            );
            $select->execute([$lastId]);
            $rows = $select->fetchAll();

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $index => $column) {
                    if (! array_key_exists($column, $row)) {
                        throw new RuntimeException("SQLite row for [{$table}] is missing column [{$column}].");
                    }

                    $values[$index] = $this->transformValue($table, $column, $row[$column]);
                }

                foreach ($values as $index => $value) {
                    if ($value === null) {
                        $insert->bindValue($index + 1, null, PDO::PARAM_NULL);
                    } else {
                        $insert->bindValue($index + 1, $value);
                    }
                }

                $insert->execute();

                $inserted++;
                $lastId = $row['id'];
            }
        }

        return $inserted;
    }

    private function transformValue(string $table, string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $dateColumns = self::DATE_ONLY_COLUMNS[$table] ?? [];
        if (in_array($column, $dateColumns, true)) {
            $string = trim((string) $value);
            if ($string === '') {
                return null;
            }

            if (! preg_match('/^(\d{4}-\d{2}-\d{2})/', $string, $matches)) {
                throw new RuntimeException("Cannot convert [{$table}.{$column}] value to a MySQL date: {$string}");
            }

            return $matches[1];
        }

        return $value;
    }

    private function applyAutoIncrements(): void
    {
        $sequences = $this->sqliteSequences();

        foreach (self::TRANSFER_TABLES as $table) {
            $maxId = $this->mysqlQuery('SELECT MAX(`id`) FROM `'.$this->quoteIdentifier($table).'`')->fetchColumn();
            $maxId = $maxId === null ? 0 : (int) $maxId;
            $sequence = (int) ($sequences[$table] ?? 0);
            $next = max($maxId, $sequence) + 1;

            if ($next <= 1 && $maxId === 0) {
                continue;
            }

            $this->mysql->exec('ALTER TABLE `'.$this->quoteIdentifier($table).'` AUTO_INCREMENT = '.$next);
            $this->line("AUTO_INCREMENT {$table} = {$next}");
        }
    }

    /**
     * @param  array<string, int>  $expected
     */
    private function printVerification(array $expected): void
    {
        $this->newLine();
        $rows = [];
        $failed = false;

        foreach ($expected as $table => $sqliteCount) {
            $mysqlCount = $this->mysqlCount($table);
            $difference = $mysqlCount - $sqliteCount;
            $status = $difference === 0 ? 'PASS' : 'FAIL';
            if ($status === 'FAIL') {
                $failed = true;
            }

            $rows[] = [$table, (string) $sqliteCount, (string) $mysqlCount, (string) $difference, $status];
        }

        $this->table(['TABLE', 'SQLITE', 'MYSQL', 'DIFFERENCE', 'STATUS'], $rows);

        if ($failed) {
            throw new RuntimeException('Post-transfer count verification failed.');
        }
    }

    private function sqliteCount(string $table): int
    {
        $statement = $this->sqlitePrepare('SELECT COUNT(*) FROM "'.$this->quoteIdentifier($table).'"');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function mysqlCount(string $table): int
    {
        return (int) $this->mysqlQuery('SELECT COUNT(*) FROM `'.$this->quoteIdentifier($table).'`')->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function sqliteColumns(string $table): array
    {
        $statement = $this->sqlitePrepare('PRAGMA table_info("'.$this->quoteIdentifier($table).'")');
        $statement->execute();

        return array_map(static fn (array $column): string => $column['name'], $statement->fetchAll());
    }

    /**
     * @return list<string>
     */
    private function mysqlColumns(string $table): array
    {
        $columns = $this->mysqlQuery('SHOW COLUMNS FROM `'.$this->quoteIdentifier($table).'`')->fetchAll();

        return array_map(static fn (array $column): string => $column['Field'], $columns);
    }

    /**
     * @return list<string>
     */
    private function sqliteTableNames(): array
    {
        $statement = $this->sqlitePrepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return list<string>
     */
    private function mysqlTableNames(): array
    {
        return $this->mysqlQuery('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }

    private function tableExistsSqlite(string $table): bool
    {
        return in_array($table, $this->sqliteTableNames(), true);
    }

    private function tableExistsMysql(string $table): bool
    {
        return in_array($table, $this->mysqlTableNames(), true);
    }

    /**
     * @return array<string, int>
     */
    private function sqliteSequences(): array
    {
        $statement = $this->sqlitePrepare('SELECT name, seq FROM sqlite_sequence');
        $statement->execute();

        $sequences = [];
        foreach ($statement->fetchAll() as $row) {
            $sequences[$row['name']] = (int) $row['seq'];
        }

        return $sequences;
    }

    private function sqlitePrepare(string $sql): \PDOStatement
    {
        $this->assertReadOnlySql($sql);

        return $this->sqlite->prepare($sql);
    }

    private function mysqlQuery(string $sql): \PDOStatement
    {
        return $this->mysql->query($sql);
    }

    private function assertReadOnlySql(string $sql): void
    {
        $normalized = ltrim($sql);
        if (! preg_match('/^(SELECT|PRAGMA)\b/i', $normalized)) {
            throw new RuntimeException('Refusing non-read SQL against the SQLite source: '.$sql);
        }
    }

    private function quoteIdentifier(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Unsafe identifier: '.$name);
        }

        return $name;
    }
}
