<?php

it('holds the ceilings the library refuses a file above', function () {
    expect(config('mediator.ceilings.image'))->toBe(10 * 1024 * 1024)
        ->and(config('mediator.ceilings.default'))->toBe(100 * 1024 * 1024);
});

it('holds the disk, the folder and the visibility new files are written with', function () {
    expect(config('mediator.disk'))->toBe('public')
        ->and(config('mediator.directory'))->toBe('media')
        ->and(config('mediator.visibility'))->toBe('public');
});
