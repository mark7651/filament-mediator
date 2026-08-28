<?php

use Illuminate\Support\Facades\Route;
use Mediator\Glide\Thumbnails;
use Mediator\Http\Controllers\ThumbnailController;

Route::get(app(Thumbnails::class)->path().'/{path}', ThumbnailController::class)
    ->where('path', '.*')
    ->name('mediator.thumbnail');
