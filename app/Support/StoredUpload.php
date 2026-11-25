<?php

namespace App\Support;

/** Hasil penyimpanan aman: nilai inilah yang boleh dicatat ke database. */
final readonly class StoredUpload
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mime,
        public int $size,
        public string $extension,
    ) {}
}
