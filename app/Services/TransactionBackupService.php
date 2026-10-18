<?php

namespace App\Services;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;
use SQLite3;
use SQLite3Exception;

class TransactionBackupService
{
    private const TABLE = 'car_transactions';

    private const JSON_COLUMNS = [
        'additional_repairs',
        'tax_items',
        'intertine_expenses',
        'leasing_options',
    ];

    public function createBackup(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sistemtoko-backup-');

        if ($path === false) {
            throw new \RuntimeException('File sementara untuk backup tidak dapat dibuat.');
        }

        $database = null;
        $completed = false;

        try {
            $database = new SQLite3($path);
            $database->enableExceptions(true);

            $columns = Schema::getColumnListing(self::TABLE);
            $definitions = array_map(
                fn (string $column): string => $this->quoteIdentifier($column)
                    .($column === 'id' ? ' INTEGER PRIMARY KEY' : ''),
                $columns,
            );

            $database->exec(sprintf(
                'CREATE TABLE %s (%s)',
                $this->quoteIdentifier(self::TABLE),
                implode(', ', $definitions),
            ));

            $columnList = implode(', ', array_map($this->quoteIdentifier(...), $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $statement = $database->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quoteIdentifier(self::TABLE),
                $columnList,
                $placeholders,
            ));

            foreach (CarTransaction::query()->orderBy('id')->cursor() as $transaction) {
                $attributes = $transaction->getAttributes();

                foreach ($columns as $index => $column) {
                    $value = $attributes[$column] ?? null;
                    $type = match (true) {
                        $value === null => SQLITE3_NULL,
                        is_int($value), is_bool($value) => SQLITE3_INTEGER,
                        is_float($value) => SQLITE3_FLOAT,
                        default => SQLITE3_TEXT,
                    };

                    $statement->bindValue($index + 1, $value, $type);
                }

                $statement->execute();
                $statement->reset();
            }

            if ($database->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new \RuntimeException('Integritas file backup SQLite tidak valid.');
            }

            $completed = true;

            return $path;
        } finally {
            if ($database instanceof SQLite3) {
                $database->close();
            }

            if (! $completed && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function import(string $path): int
    {
        if (file_get_contents($path, false, null, 0, 16) !== "SQLite format 3\0") {
            throw new InvalidArgumentException('File yang dipilih bukan backup SQLite yang valid.');
        }

        $database = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $database->enableExceptions(true);

        try {
            if ($database->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new InvalidArgumentException('Integritas file backup SQLite tidak valid.');
            }

            $columnsResult = $database->query(sprintf(
                'PRAGMA table_info(%s)',
                $this->quoteIdentifier(self::TABLE),
            ));
            $backupColumns = [];

            while ($column = $columnsResult->fetchArray(SQLITE3_ASSOC)) {
                $backupColumns[] = $column['name'];
            }

            if ($backupColumns !== Schema::getColumnListing(self::TABLE)) {
                throw new InvalidArgumentException(
                    'Struktur tabel backup tidak sesuai dengan versi aplikasi ini.',
                );
            }

            $result = $database->query(sprintf(
                'SELECT * FROM %s ORDER BY %s',
                $this->quoteIdentifier(self::TABLE),
                $this->quoteIdentifier('id'),
            ));

            $rules = (new StoreCarTransactionRequest)->rules();

            return DB::transaction(function () use ($result, $rules): int {
                $importedCount = 0;

                while ($record = $result->fetchArray(SQLITE3_ASSOC)) {
                    $transaction = new CarTransaction;
                    $fillableAttributes = array_intersect_key(
                        $record,
                        array_flip($transaction->getFillable()),
                    );

                    foreach (self::JSON_COLUMNS as $column) {
                        if ($fillableAttributes[$column] === null) {
                            continue;
                        }

                        try {
                            $value = json_decode(
                                $fillableAttributes[$column],
                                true,
                                flags: JSON_THROW_ON_ERROR,
                            );
                        } catch (JsonException $exception) {
                            throw new InvalidArgumentException(
                                "Data {$column} pada file backup tidak valid.",
                                previous: $exception,
                            );
                        }

                        if (! is_array($value)) {
                            throw new InvalidArgumentException(
                                "Data {$column} pada file backup tidak valid.",
                            );
                        }

                        $fillableAttributes[$column] = $value;
                    }

                    $validator = Validator::make($fillableAttributes, $rules);

                    if ($validator->fails()) {
                        throw new InvalidArgumentException(
                            'Data transaksi pada file backup tidak valid: '.$validator->errors()->first(),
                        );
                    }

                    $transaction->fill($validator->validated());

                    if (array_key_exists('days_held', $record)) {
                        $transaction->setAttribute('days_held', $record['days_held']);
                    }

                    $transaction->setAttribute('created_at', $record['created_at'] ?? null);
                    $transaction->setAttribute('updated_at', $record['updated_at'] ?? null);
                    $transaction->timestamps = false;
                    $transaction->save();

                    $importedCount++;
                }

                return $importedCount;
            });
        } catch (SQLite3Exception $exception) {
            throw new InvalidArgumentException('File backup SQLite tidak dapat dibaca.', previous: $exception);
        } finally {
            $database->close();
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
