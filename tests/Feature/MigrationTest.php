<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('raises the table of the library', function () {
    expect(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasColumns('media', ['disk', 'directory', 'visibility', 'name', 'path', 'width', 'height', 'size', 'type', 'ext', 'alt', 'title']))->toBeTrue()
        // The library says two things about a file, its name and what it shows
        // for those who cannot see it, and holds no column it never writes.
        ->and(Schema::hasColumn('media', 'curations'))->toBeFalse();
});

it('leaves a table that is already there alone', function () {
    Schema::drop('media');

    Schema::create('media', function (Blueprint $table): void {
        $table->id();
        $table->string('path');
        $table->string('carried_over_from_elsewhere')->nullable();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/0001_01_01_000000_create_mediator_table.php';
    $migration->up();

    expect(Schema::hasColumn('media', 'carried_over_from_elsewhere'))->toBeTrue()
        ->and(Schema::hasColumn('media', 'disk'))->toBeFalse();
});

it('hands its migrations to the project rather than running them itself', function () {
    $published = fn (): array => File::glob(database_path('migrations/*_mediator_*table.php'));

    foreach ($published() as $file) {
        File::delete($file);
    }

    $this->artisan('vendor:publish', ['--tag' => 'mediator-migrations'])->assertSuccessful();

    $files = $published();

    expect($files)->toHaveCount(3);

    // Handed over under the names the package holds them by, and never under
    // the hour of the publishing: the table of the library is pointed at by
    // the records of the project, so it has to be raised before them, and a
    // file dated today would land after every table that already carries a key
    // to it.
    expect(array_map('basename', $files))->toBe([
        '0001_01_01_000000_create_mediator_table.php',
        '0001_01_01_000001_create_mediator_texts_table.php',
        '0001_01_01_000002_index_mediator_table.php',
    ]);

    foreach ($files as $file) {
        File::delete($file);
    }
});

it('indexes the column the wall is narrowed by', function () {
    $indexes = collect(Schema::getIndexes('media'))->pluck('columns');

    expect($indexes)->toContain(['type']);
});

it('indexes a table it never raised itself', function () {
    Schema::drop('media');

    Schema::create('media', function (Blueprint $table): void {
        $table->id();
        $table->string('path');
        $table->string('type');
        $table->string('name');
        $table->string('title')->nullable();
        $table->string('alt')->nullable();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/0001_01_01_000002_index_mediator_table.php';
    $migration->up();

    expect(collect(Schema::getIndexes('media'))->pluck('columns'))->toContain(['type']);

    // Run a second time over the same table, as it is whenever a project
    // publishes the migrations of the package anew: an index already standing
    // is left where it is rather than raised again under another name.
    $migration->up();

    expect(collect(Schema::getIndexes('media'))->pluck('columns')->filter(fn (array $columns): bool => $columns === ['type']))
        ->toHaveCount(1);
});

it('asks for words of a database that keeps none only where there is one', function () {
    // Sqlite holds no full-text index, so the three columns a file is looked
    // for by are left unindexed and the library reads them instead.
    expect(collect(Schema::getIndexes('media'))->pluck('columns'))->not->toContain(['name', 'title', 'alt']);
});
