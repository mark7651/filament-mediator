<?php

namespace Mediator\Tests\Fixtures;

use Mediator\Models\Media;

/**
 * The policy of a project that tells one file from another: a library where an
 * editor may look at some of what stands in it and clear out some of what they
 * may look at.
 */
class PickyPolicy
{
    public function viewAny(Person $user): bool
    {
        return true;
    }

    public function view(Person $user, Media $file): bool
    {
        return $file->title !== 'chuzha';
    }

    public function create(Person $user): bool
    {
        return true;
    }

    public function update(Person $user, Media $file): bool
    {
        return true;
    }

    public function delete(Person $user, Media $file): bool
    {
        return $file->title !== 'zamknena';
    }

    public function deleteAny(Person $user): bool
    {
        return true;
    }
}
