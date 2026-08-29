<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns a file is looked for by, in the order the index holds them.
     */
    private const WORDS = ['name', 'title', 'alt'];

    /**
     * The indexes a library of many thousands of files is looked through by.
     *
     * Written apart from the table itself so that a project carrying its files
     * over from another library, whose table this package never raised, gets
     * them all the same.
     *
     * The kind of a file is what the wall is narrowed by, and a full reading of
     * the table for it costs the same whether the library holds a hundred files
     * or a hundred thousand. The three columns a file is looked for in are
     * gathered into one full-text index, which the library asks instead of
     * reading everything, where the project says so in the config and the
     * database is one that keeps such an index.
     */
    public function up(): void
    {
        $table = (string) config('mediator.table', 'media');

        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'type') && ! $this->indexed($table, ['type'])) {
            Schema::table($table, function (Blueprint $table): void {
                $table->index('type');
            });
        }

        if ($this->keepsWords() && Schema::hasColumns($table, self::WORDS) && ! $this->indexed($table, self::WORDS)) {
            Schema::table($table, function (Blueprint $table): void {
                $table->fullText(self::WORDS);
            });
        }
    }

    public function down(): void
    {
        $table = (string) config('mediator.table', 'media');

        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ([['type'], self::WORDS] as $columns) {
            $index = $this->indexed($table, $columns);

            if ($index !== null) {
                Schema::table($table, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }

    /**
     * The name of the index standing on exactly these columns, where there is
     * one. Asked of the database rather than guessed at by name, because a
     * table carried over from another library holds its indexes under whatever
     * names that library gave them.
     *
     * @param  list<string>  $columns
     */
    private function indexed(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return (string) $index['name'];
            }
        }

        return null;
    }

    /**
     * Whether the library can ask this database for words. Only the databases
     * the search of the package knows how to ask are given the index: an index
     * nothing reads is a write on every upload paid for nothing.
     */
    private function keepsWords(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
