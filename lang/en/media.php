<?php

return [
    'label' => 'image',
    'plural_label' => 'media',
    'plural_title' => 'Media library',
    'empty' => 'The library is empty. Drag the first file here.',
    'search' => 'Search by name or description',
    'unused' => 'Standing nowhere',
    'nothing' => 'Nothing found. Try another search or take the filters off.',
    'drop' => 'Drop the files to upload them',
    'uploading' => 'Uploading',
    'more' => 'Show :count more',
    'renamed' => 'Saved',
    'copied' => 'The address is copied',
    'replaced' => 'The file is replaced',
    'refused' => [
        'type' => 'The library does not take files of this kind.',
        'weight' => 'The file :name is heavier than :limit MB.',
        'broken' => 'The file :name is broken: the image could not be read.',
    ],
    'deleted' => 'Files deleted: :count',

    'types' => [
        'all' => 'All files',
        'image' => 'Images',
        'video' => 'Video',
        'audio' => 'Audio',
        'document' => 'Documents',
    ],

    'fields' => [
        'image' => 'Image',
        'title' => 'Name',
        'alt' => 'Alt',
    ],

    'taken' => 'Uploaded :when',
    'standing' => '{0}stands nowhere|{1}stands in one record|[2,*]stands in :count records',
    'elsewhere' => '{1}and in one record more|[2,*]and in :count records more',

    'hints' => [
        'alt' => 'Read out by screen readers and shown when the image has not loaded.',
    ],

    'actions' => [
        'image' => 'Image',
        'view' => 'Open',
        'edit' => 'Rename',
        'save' => 'Save',
        'copy' => 'Copy the address',
        'download' => 'Download',
        'next' => 'Next file',
        'previous' => 'Previous file',
        'close' => 'Close',
        'choose_many' => 'Insert into the text',
        'replace' => 'Replace the file',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'delete_selected' => 'Delete the selected',
        'upload' => 'Upload',
        'upload_many' => 'Upload several',
    ],

    'field' => [
        'choose' => 'Choose from the library',
        'change' => 'Choose another file',
        'clear' => 'Take the file off',
    ],

    'delete' => [
        'heading' => 'Delete the image?',
        'heading_many' => 'Delete the selected images?',
        'unused' => 'The file will be gone from the disk. It stands nowhere on the site.',
        'in_use' => 'The file will be gone from the disk, and :count record will be left without an image.|The file will be gone from the disk, and :count records will be left without an image.',
        'unused_many' => 'Files to be gone from the disk: :count. None of them stands anywhere.',
        'in_use_many' => 'Files to be gone from the disk: :count, and :standing of them stands in records on the site.|Files to be gone from the disk: :count, and :standing of them stand in records on the site.',
    ],
];
