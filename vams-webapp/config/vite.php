<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Build Directory
    |--------------------------------------------------------------------------
    |
    | The directory relative to the public path where Vite's build assets
    | are stored. This is typically "build" when using Vite's default
    | configuration.
    |
    */

    'build_directory' => 'build',

    /*
    |--------------------------------------------------------------------------
    | Hot File Path
    |--------------------------------------------------------------------------
    |
    | The path to the "hot" file. This file is created by Vite when the
    | development server is running and is used to determine if assets
    | should be served from the dev server or from the build directory.
    |
    */

    'hot_file' => public_path('hot'),

];
