<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Portable database backup that needs no mysqldump binary (works on Windows
 * hosts too). Produces a plain SQL file under storage/app/backups.
 */
class BackupService
{
    public function create(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $file = $dir.'/gharas-'.now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            File::copy($connection->getDatabaseName(), $file.'.sqlite');

            return $file.'.sqlite';
        }

        $pdo = $connection->getPdo();
        $handle = fopen($file.'.sql', 'w');
        fwrite($handle, "-- Gharas backup ".now()->toDateTimeString()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n".$create['Create Table'].";\n\n");

            foreach (DB::table($table)->cursor() as $record) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record);
                $columns = implode('`, `', array_keys((array) $record));
                fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES (".implode(', ', $values).");\n");
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return $file.'.sql';
    }
}
