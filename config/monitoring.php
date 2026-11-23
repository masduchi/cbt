<?php

return [

    /*
    | URL health check yang dipantau. Default memakai APP_URL + /health sehingga
    | pemeriksaan bisa dijalankan dari scheduler tanpa konfigurasi tambahan.
    */
    'health_url' => env('HEALTH_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/health'),

    /*
    | Webhook peringatan (Slack, Discord, atau endpoint HTTP apa pun yang menerima
    | JSON). Kosong berarti peringatan hanya dicatat di log aplikasi.
    */
    'alert_webhook' => env('ALERT_WEBHOOK_URL'),

    /*
    | Jeda minimum antar peringatan dengan kunci yang sama, agar satu error yang
    | berulang tidak membanjiri webhook.
    */
    'throttle_minutes' => (int) env('ALERT_THROTTLE_MINUTES', 10),

    /*
    | Bila diisi, detail /health hanya diberikan kepada pemanggil yang
    | membawa header X-Health-Token atau ?token=. Kosong berarti terbuka.
    */
    'health_token' => env('HEALTH_TOKEN', ''),

];
