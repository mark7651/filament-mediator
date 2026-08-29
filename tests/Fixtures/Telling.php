<?php

namespace Mediator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An article told in one language, which is where the text of it lives while
 * the record a person opens is the article itself.
 */
class Telling extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
