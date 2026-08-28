<?php

namespace Mediator\Tests\Fixtures;

use Mediator\Models\Media;

/**
 * The policy of a project that lets its editors fill the library but not empty
 * it.
 */
class ClosedPolicy
{
    public function viewAny(Person $user): bool
    {
        return true;
    }

    public function delete(Person $user, Media $file): bool
    {
        return false;
    }

    public function deleteAny(Person $user): bool
    {
        return false;
    }
}
