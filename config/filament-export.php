<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Export Queue Connection
    |--------------------------------------------------------------------------
    |
    | Filament writes export files to disk before the download link is used.
    | With async queues (e.g. redis), the worker and web process must share
    | the same filesystem. On a single-server deploy, "sync" runs the export
    | in the web request (same behaviour as local) and avoids 404 downloads.
    |
    | Set to null to use the application's default queue connection.
    |
    */

    'queue_connection' => env('FILAMENT_EXPORT_QUEUE_CONNECTION', 'sync'),

];
