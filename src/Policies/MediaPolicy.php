<?php

namespace Mediator\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Mediator\Models\Media;

/**
 * The library as it stands in a project that tells nobody apart: everyone who
 * reached the panel may look through it, fill it and clear it out.
 *
 * A project with roles registers a policy of its own on the model of the
 * library, and this one is then never put in place. The library itself asks
 * the Gate either way and knows nothing of roles.
 */
class MediaPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Media $file): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Media $file): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Media $file): bool
    {
        return true;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return true;
    }
}
