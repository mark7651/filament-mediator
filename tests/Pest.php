<?php

use Livewire\Features\SupportTesting\Testable;
use Mediator\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * The wall of the library under test, addressed the way a Livewire component
 * is addressed everywhere else.
 */
function livewire(string $name, array $params = []): Testable
{
    return Livewire\Livewire::test($name, $params);
}
