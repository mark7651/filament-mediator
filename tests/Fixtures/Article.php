<?php

namespace Mediator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A record of a project that holds a file in a column of its own.
 */
class Article extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gallery' => 'array'];
    }
}
