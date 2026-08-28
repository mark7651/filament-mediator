<?php

use Illuminate\Support\Facades\Gate;
use Mediator\MediatorServiceProvider;
use Mediator\Models\Media;
use Mediator\Policies\MediaPolicy;
use Mediator\Tests\Fixtures\ClosedPolicy;
use Mediator\Tests\Fixtures\Person;

it('lets everyone who reached the panel into a library the project said nothing about', function () {
    $person = new Person;

    expect(Gate::getPolicyFor(Media::class))->toBeInstanceOf(MediaPolicy::class)
        ->and(Gate::forUser($person)->allows('viewAny', Media::class))->toBeTrue()
        ->and(Gate::forUser($person)->allows('create', Media::class))->toBeTrue()
        ->and(Gate::forUser($person)->allows('deleteAny', Media::class))->toBeTrue();
});

it('leaves the policy of the project where the project has one', function () {
    Gate::policy(Media::class, ClosedPolicy::class);

    (new MediatorServiceProvider(app()))->defaultPolicy();

    $file = Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'a-picture',
        'path' => 'media/2026/08/a-picture.webp',
        'type' => 'image/webp',
        'ext' => 'webp',
    ]);

    expect(Gate::getPolicyFor(Media::class))->toBeInstanceOf(ClosedPolicy::class)
        ->and(Gate::forUser(new Person)->allows('viewAny', Media::class))->toBeTrue()
        ->and(Gate::forUser(new Person)->allows('delete', $file))->toBeFalse();
});
