<?php

return [
    'disk' => env('SF10_EXPORT_DISK', 'local'),

    'directory' => env('SF10_EXPORT_DIRECTORY', 'exports/sf10'),

    'temp_directory' => env('SF10_EXPORT_TEMP_DIRECTORY', 'tmp/spreadsheets'),
];
