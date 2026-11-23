<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lokasi berkas .env
    |--------------------------------------------------------------------------
    |
    | Wizard instalasi menulis kredensial database ke berkas ini. Pada test,
    | path diarahkan ke berkas sementara agar .env proyek tidak berubah.
    |
    */

    'env_path' => env('INSTALLER_ENV_PATH', base_path('.env')),

    /*
    |--------------------------------------------------------------------------
    | Penanda instalasi selesai
    |--------------------------------------------------------------------------
    */

    'marker' => storage_path('app/installed'),

    /*
    |--------------------------------------------------------------------------
    | Pilihan jenjang & satuan pendidikan
    |--------------------------------------------------------------------------
    */

    'jenjang' => [
        1 => 'SD/MI',
        2 => 'SMP/MTs',
        3 => 'SMA/MA/SMK',
    ],

    'satuan' => [
        1 => ['SD', 'MI'],
        2 => ['SMP', 'MTs'],
        3 => ['SMA', 'MA', 'SMK'],
    ],

];
