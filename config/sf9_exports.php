<?php

return [
    'disk' => env('SF9_EXPORT_DISK', 'local'),

    'directory' => env('SF9_EXPORT_DIRECTORY', 'exports/sf9'),

    'temp_directory' => env('SF9_EXPORT_TEMP_DIRECTORY', 'tmp/spreadsheets'),
];
