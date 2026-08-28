<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The table is raised only where there is none.
     *
     * A project coming over from another library already holds its files in a
     * table of this shape and names it in the config of the package, and the
     * point of the move is that no row and no file is touched by it.
     */
    public function up(): void
    {
        $table = (string) config('mediator.table', 'media');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();

            $table->string('disk');
            $table->string('directory')->nullable();
            $table->string('visibility')->default('public');
            $table->string('name');
            $table->string('path')->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('type');
            $table->string('ext');
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->text('exif')->nullable();
            $table->longText('curations')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('mediator.table', 'media'));
    }
};
