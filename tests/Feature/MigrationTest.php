<?php

use Illuminate\Database\Schema\Blueprint;
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
