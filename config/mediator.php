<?php

use Mediator\Models\Media;

return [

    /*
    |--------------------------------------------------------------------------
    | Model and table
    |--------------------------------------------------------------------------
    |
    | The record of a file. A project that needs the record to know more than
    | the library does, the pages a file stands on for instance, puts its own
    | model here, inheriting the one of the package. The library never names
    | the class itself and always asks for it here.
    |
    | The table is named separately so that a project carrying files over from
    | another library keeps the table those files are already in.
    |
    */

    'model' => Media::class,

    'table' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Where files are written
    |--------------------------------------------------------------------------
    |
    | The disk, the folder inside it and the visibility a newly uploaded file
    | is written with. Files are laid out in folders by year and month under
    | the folder named here.
    |
    */

    'disk' => env('MEDIATOR_DISK', env('FILESYSTEM_DISK', 'public')),

    'directory' => 'media',

    'visibility' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Ceilings
    |--------------------------------------------------------------------------
    |
    | How heavy a file may be, in bytes. A picture stands on a page and is held
    | to a tenth of what a film may weigh, because a page of ten megabytes is a
    | page nobody on a phone waits for.
    |
    | Livewire has a ceiling of its own, and it is the lower of the two that
    | decides: see the readme for the setting to raise in the project.
    |
    */

    'ceilings' => [
        'image' => 10 * 1024 * 1024,
        'default' => 100 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    |
    | Pictures are redrawn to the size they are looked at by Glide, through a
    | route of this package. Every address carries a signature, so that nobody
    | outside can ask for a thousand sizes of one file and fill the disk with
    | them. Where no token is set, the key of the application signs them.
    |
    | The drawn pictures are kept on the local disk under the folder named
    | here. The address of the file itself stays what it always was: a picture
    | on a page is served straight off its disk and never through this route.
    |
    */

    'thumbnails' => [

        'path' => 'mediator/pictures',

        'token' => env('MEDIATOR_THUMBNAIL_TOKEN'),

        'cache' => storage_path('app/mediator-thumbnails'),

        'max_image_size' => 2000 * 2000,

        'sizes' => [
            'thumbnail' => ['w' => 200, 'h' => 200, 'fit' => 'crop', 'fm' => 'webp'],
            'large' => ['w' => 1024, 'h' => 1024, 'fit' => 'contain', 'fm' => 'webp'],
        ],

    ],

];
