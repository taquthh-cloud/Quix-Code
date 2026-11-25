<?php

namespace App\Support;

use App\Exceptions\UnsafeUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * T4-07 Upload aman. Satu pintu untuk semua unggahan klien.
 *
 * Yang dilakukan: MIME asli lewat finfo (header dari browser tidak dipercaya),
 * ekstensi bawaan harus cocok dengan isi, batas ukuran dan piksel, penolakan kode
 * tersisip (polyglot) dan konten aktif PDF, gambar dibuat ulang dengan GD sehingga
 * EXIF/GPS dan data tambahan hilang, nama diganti ULID, disimpan di disk privat,
 * dan ClamAV opsional.
 */
final class SecureUpload
{
    public const KIND_PROOF = 'proof';

    public const KIND_IMAGE = 'image';

    /** MIME asli => ekstensi baku. Hanya ini yang pernah disimpan. */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /** Penanda kode yang tidak wajar di dalam gambar atau PDF (cukup panjang agar tidak salah deteksi pada data terkompresi). */
    private const CODE_MARKERS = ['<?php', '<script'];

    /**
     * Memeriksa file tanpa menyimpannya. Mengembalikan MIME asli, atau melempar
     * UnsafeUploadException dengan alasan yang bisa ditampilkan ke pengguna.
     */
    public static function inspect(UploadedFile $file, string $kind = self::KIND_PROOF): string
    {
        $allowed = self::allowedMimes($kind);
        $path = $file->getRealPath();

        if (! $file->isValid() || $path === false || ! is_file($path)) {
            throw new UnsafeUploadException('invalid');
        }

        $size = filesize($path);

        if ($size === false || $size === 0) {
            throw new UnsafeUploadException('empty');
        }

        if ($size > self::maxBytes($kind)) {
            throw new UnsafeUploadException('too_large');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || ! in_array($mime, $allowed, true)) {
            throw new UnsafeUploadException('type');
        }

        self::assertExtensionMatches($file, $mime);

        $bytes = file_get_contents($path);

        if ($bytes === false) {
            throw new UnsafeUploadException('invalid');
        }

        foreach (self::CODE_MARKERS as $marker) {
            if (stripos($bytes, $marker) !== false) {
                throw new UnsafeUploadException('embedded_code');
            }
        }

        if ($mime === 'application/pdf') {
            self::assertSafePdf($bytes);
        } else {
            self::assertSaneImage($path, $mime);
        }

        return $mime;
    }

    /** Memeriksa lalu menyimpan ke disk privat dengan nama ULID. $directory contoh: "payments". */
    public static function store(
        UploadedFile $file,
        string $directory,
        string $kind = self::KIND_PROOF,
    ): StoredUpload {
        if (preg_match('#^[a-z0-9_]+(/[a-z0-9_]+)*$#D', $directory) !== 1) {
            throw new InvalidArgumentException('Direktori unggahan tidak valid.');
        }

        $mime = self::inspect($file, $kind);
        $path = (string) $file->getRealPath();
        $bytes = (string) file_get_contents($path);

        self::scanForMalware($path);

        if (str_starts_with($mime, 'image/')) {
            $bytes = self::reencode($bytes, $mime) ?? $bytes;
        }

        $extension = self::TYPES[$mime];
        $relative = $directory.'/'.strtolower((string) Str::ulid()).'.'.$extension;
        $disk = (string) config('uploads.disk', 'local');

        if (! Storage::disk($disk)->put($relative, $bytes)) {
            throw new UnsafeUploadException('invalid');
        }

        return new StoredUpload($disk, $relative, $mime, strlen($bytes), $extension);
    }

    public static function maxMegabytes(string $kind): int
    {
        return max(1, (int) ceil(self::maxBytes($kind) / 1048576));
    }

    /** @return list<string> */
    private static function allowedMimes(string $kind): array
    {
        return match ($kind) {
            self::KIND_PROOF => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            self::KIND_IMAGE => ['image/jpeg', 'image/png', 'image/webp'],
            default => throw new InvalidArgumentException("Jenis unggahan tidak dikenal: {$kind}"),
        };
    }

    private static function maxBytes(string $kind): int
    {
        return ((int) config("uploads.max_kb.{$kind}", 5120)) * 1024;
    }

    /** Ekstensi dari klien tidak dipercaya, tetapi bila ada, harus cocok dengan isi file. */
    private static function assertExtensionMatches(UploadedFile $file, string $mime): void
    {
        $claimed = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $claimed = $claimed === 'jpeg' ? 'jpg' : $claimed;

        if ($claimed !== self::TYPES[$mime]) {
            throw new UnsafeUploadException('extension');
        }
    }

    private static function assertSaneImage(string $path, string $mime): void
    {
        $info = @getimagesize($path);

        if ($info === false || $info['mime'] !== $mime || $info[0] < 1 || $info[1] < 1) {
            throw new UnsafeUploadException('corrupt');
        }

        if ((int) config('uploads.max_pixels', 16_000_000) < $info[0] * $info[1]) {
            throw new UnsafeUploadException('dimensions');
        }
    }

    /**
     * PDF harus diawali header di byte pertama (menolak polyglot dengan sampah di depan)
     * dan tidak memuat aksi aktif. Catatan: objek terkompresi tidak terbaca di sini,
     * jadi PDF tetap hanya boleh diunduh (attachment), tidak pernah ditampilkan inline.
     */
    private static function assertSafePdf(string $bytes): void
    {
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new UnsafeUploadException('corrupt');
        }

        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|XFA)\b#', $bytes) === 1) {
            throw new UnsafeUploadException('active_content');
        }
    }

    private static function scanForMalware(string $path): void
    {
        if (! config('uploads.clamav.enabled')) {
            return;
        }

        $result = Process::timeout((int) config('uploads.clamav.timeout', 30))
            ->run([(string) config('uploads.clamav.binary', 'clamscan'), '--no-summary', $path]);

        // 0 = bersih, 1 = terinfeksi, selain itu pemindai bermasalah: tolak.
        if ($result->exitCode() === 1) {
            throw new UnsafeUploadException('malware');
        }

        if ($result->exitCode() !== 0) {
            throw new UnsafeUploadException('scan_failed');
        }
    }

    /**
     * Membuat ulang gambar dengan GD: EXIF/GPS, komentar, dan data tambahan di
     * belakang gambar hilang. Mengembalikan null bila GD (atau encoder WebP) tidak
     * tersedia; file asli yang sudah lolos pemeriksaan tetap dipakai.
     */
    private static function reencode(string $bytes, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        if ($mime === 'image/webp' && ! function_exists('imagewebp')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new UnsafeUploadException('corrupt');
        }

        if ($mime === 'image/jpeg') {
            $image = self::applyOrientation($image, $bytes);
        }

        if ($mime !== 'image/jpeg') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image, null, 6),
            default => imagewebp($image, null, 90),
        };
        $encoded = (string) ob_get_clean();

        if (! $ok || $encoded === '') {
            throw new UnsafeUploadException('corrupt');
        }

        return $encoded;
    }

    /** Foto ponsel menyimpan arah putar di EXIF; karena EXIF dibuang, putar pikselnya dulu. */
    private static function applyOrientation(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }
}
