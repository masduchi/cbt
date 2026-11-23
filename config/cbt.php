<?php

return [
    /*
     * Monitor status peserta bisa memakai Server-Sent Events (realtime) atau
     * polling biasa. Nilai null berarti otomatis: SSE aktif di server yang
     * mampu menangani banyak koneksi (php-fpm/Apache), dan mati di server
     * pengembangan bawaan PHP yang hanya melayani satu permintaan sekaligus.
     */
    'realtime' => [
        'enabled' => env('CBT_SSE_ENABLED'),

        // Jeda antar kiriman data (detik) dan jumlah kiriman sebelum koneksi
        // ditutup; browser akan menyambung ulang secara otomatis.
        'interval' => (int) env('CBT_SSE_INTERVAL', 3),
        'ticks' => (int) env('CBT_SSE_TICKS', 20),
    ],

    /*
     * Penilaian esai oleh lebih dari satu korektor memakai rata-rata. Bila
     * selisih skor antar korektor mencapai persentase ini dari skor maksimal,
     * jawaban ditandai "perlu ditinjau" agar admin bisa menetapkan nilai final.
     */
    'koreksi' => [
        'selisih_persen' => (int) env('CBT_KOREKSI_SELISIH_PERSEN', 15),
    ],
];
