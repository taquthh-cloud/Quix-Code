<?php

namespace App\Exceptions;

use RuntimeException;

/** Unggahan ditolak oleh pemeriksaan keamanan. `$reason` selalu salah satu kunci galat di berkas bahasa uploads. */
class UnsafeUploadException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Unsafe upload rejected: '.$reason);
    }

    public function translated(int $maxMegabytes = 5): string
    {
        return __('uploads.errors.'.$this->reason, ['max' => $maxMegabytes]);
    }
}
