<?php

namespace Mediator\Tests\Fixtures;

/**
 * The policy of a project whose library is none of this person's work.
 */
class ShutPolicy
{
    public function viewAny(Person $user): bool
    {
        return false;
    }
}
