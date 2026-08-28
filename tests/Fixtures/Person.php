<?php

namespace Mediator\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User;

/**
 * Somebody who reached the panel.
 */
class Person extends User implements FilamentUser
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'name' => 'Editor',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
