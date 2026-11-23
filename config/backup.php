<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    'mysql_path' => env('MYSQL_PATH', 'mysql'),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'schedule_at' => env('BACKUP_SCHEDULE_AT', '01:00'),

    'verify_at' => env('BACKUP_VERIFY_AT', '02:00'),

    /*
    | Path penyimpanan kedua (mount jaringan, disk eksternal, atau folder
    | sinkronisasi). Kosong berarti salinan off-site tidak dijalankan.
    */
    'offsite_path' => env('BACKUP_OFFSITE_PATH', ''),
];
