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
    | Files standing in texts
    |--------------------------------------------------------------------------
    |
    | A picture put into a text is held there by its address and not by a
    | column, so it is written down as it is put in: the table named here keeps
    | one row per file per record whose text holds it, and the library counts
    | the places of a file by reading it instead of by reading every text of
    | the project.
    |
    | The table stands empty until a project says which of its texts can hold
    | files; see standsInText() of the register.
    |
    */

    'texts_table' => 'media_in_texts',

    /*
    |--------------------------------------------------------------------------
    | The kinds of file the library holds
    |--------------------------------------------------------------------------
    |
    | Every kind the library takes, and the extension a file of that kind is
    | written with. The name a file arrives with says nothing true about it: a
    | picture may be handed over as page.html, and a disk that took that name
    | would serve a script from the domain the panel is signed in to. So the
    | kind is read out of the bytes and the extension is written from here.
    |
    | A project that needs a kind the list does not hold adds it here, and one
    | that wants a kind kept out takes it away.
    |
    */

    'types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ],

    /*
    |--------------------------------------------------------------------------
    | The wall
    |--------------------------------------------------------------------------
    |
    | How many cards stand on the wall when it opens and how many are added at
    | a time as it is scrolled.
    |
    */

    'step' => 24,

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
    | Pictures on the way in
    |--------------------------------------------------------------------------
    |
    | A photograph of a kind named in redraw is not kept as the camera wrote it:
    | it is turned the right way up, brought down to the longest side named here
    | and written as webp. Above that side nothing on a page gains anything, and
    | a phone pays for every pixel it is sent.
    |
    */

    'pictures' => [

        'longest_side' => 2560,

        'quality' => 82,

        /*
         | The kinds redrawn on the way in. A kind left out of this list is
         | written to the disk as it arrived, byte for byte, and an empty list
         | is a library that keeps every original: a studio handing photographs
         | back to the people they belong to wants exactly that, and pays for it
         | with the weight.
         |
         | Gif is left out because redrawing it would leave an animation
         | standing still, and webp because it is already what the redrawing
         | produces.
         */

        'redraw' => ['image/jpeg', 'image/png'],

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
