<?php

use Mediator\MediatorServiceProvider;

it('registers its provider and merges its config', function () {
    expect(app()->getLoadedProviders())->toHaveKey(MediatorServiceProvider::class)
        ->and(config('mediator.disk'))->toBe('public');
});

it('offers its config for publishing', function () {
    $path = config_path('mediator.php');

    @unlink($path);

    $this->artisan('vendor:publish', ['--tag' => 'mediator-config'])->assertSuccessful();

    expect($path)->toBeFile();

    unlink($path);
});
