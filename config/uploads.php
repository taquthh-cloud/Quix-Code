<?php

return [

    /*
    | Semua unggahan klien disimpan di disk privat ("local" = storage/app/private).
    | Tidak ada symlink atau URL publik; file hanya keluar lewat route terotorisasi.
    */
    'disk' => 'local',

    // Batas ukuran per jenis unggahan, dalam kilobyte.
    'max_kb' => [
        'proof' => 5120,  // bukti bayar: JPG, PNG, WebP, PDF
        'image' => 5120,  // tangkapan layar tiket: JPG, PNG, WebP
    ],

    // Batas piksel gambar (melindungi memori saat gambar dibaca ulang).
    'max_pixels' => 16_000_000,

    /*
    | Pemindai ClamAV opsional. Bila aktif dan pemindai gagal berjalan, unggahan
    | ditolak (fail closed).
    */
    'clamav' => [
        'enabled' => (bool) env('UPLOAD_CLAMAV', false),
        'binary' => env('UPLOAD_CLAMAV_BINARY', 'clamscan'),
        'timeout' => 30,
    ],
];
