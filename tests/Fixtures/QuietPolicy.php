<?php

namespace Mediator\Tests\Fixtures;

/**
 * The policy of a project that hides no file: it carries a before() for the
 * sake of somebody who is allowed everything, and stands aside for everybody
 * else.
 */
class QuietPolicy
{
    public function before(Person $user, string $ability): ?bool
    {
        return null;
    }

    public function viewAny(Person $user): bool
    {
        return true;
    }
}
