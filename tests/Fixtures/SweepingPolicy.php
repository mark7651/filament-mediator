<?php

namespace Mediator\Tests\Fixtures;

/**
 * The policy of a project that answers for every ability at once instead of
 * writing a method for each: the library is open to look through and no single
 * file of it may be looked at.
 */
class SweepingPolicy
{
    public function before(Person $user, string $ability): ?bool
    {
        return $ability === 'view' ? false : null;
    }

    public function viewAny(Person $user): bool
    {
        return true;
    }
}
