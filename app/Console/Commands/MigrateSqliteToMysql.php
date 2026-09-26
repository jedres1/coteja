<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSqliteToMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:sqlite-to-mysql {--path= : Path to sqlite file (defaults to database/database.sqlite)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy data from a SQLite database file into the current MySQL connection';

    public function handle()
    {
        $path = $this->option('path') ?: database_path('database.sqlite');

        if (!file_exists($path)) {
            $this->error("SQLite file not found at: $path");
            return 1;
        }

        $this->info("Using sqlite file: $path");

        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // get table list excluding sqlite internals
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($tables)) {
            $this->info('No tables found in sqlite database.');
            return 0;
        }

        // skip tables we don't want to copy
        $skip = ['migrations'];

        // disable foreign key checks in MySQL
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            if (in_array($table, $skip, true)) {
                $this->line("Skipping table: $table");
                continue;
            }

            $this->info("Migrating table: $table");

            // fetch all rows from sqlite
            $rowsStmt = $pdo->query("SELECT * FROM \"$table\"");
            $all = $rowsStmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($all)) {
                $this->line("  no rows, skipping");
                continue;
            }

            // truncate target table to avoid duplicates
            try {
                DB::table($table)->truncate();
            } catch (\Exception $e) {
                $this->line("  could not truncate $table: " . $e->getMessage());
            }

            // insert in chunks
            $chunkSize = 200;
            $chunks = array_chunk($all, $chunkSize);
            foreach ($chunks as $chunk) {
                // convert boolean values and sqlite nulls are fine
                try {
                    DB::table($table)->insert($chunk);
                } catch (\Exception $e) {
                    $this->error("  insert failed for table $table: " . $e->getMessage());
                    return 1;
                }
            }

            $this->info("  migrated " . count($all) . " rows into $table");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('SQLite -> MySQL migration completed.');
        return 0;
    }
}
