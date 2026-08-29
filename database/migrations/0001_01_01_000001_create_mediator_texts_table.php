<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Uses\Texts;

return new class extends Migration
{
    /**
     * The places a file stands in that no column of the project leads to: a
     * picture put into a text by the editor.
     *
     * The source is the record whose text was read, and the holder is the
     * record a person is shown: a text lives in the telling of a page in one
     * language, while the place the editor opens is the page itself, and a
     * page told in two languages is one place and not two.
     */
    public function up(): void
    {
        $table = Texts::table();

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('media_id')->index();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');

            $table->unique(['media_id', 'source_type', 'source_id']);
            $table->index(['holder_type', 'holder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Texts::table());
    }
};
