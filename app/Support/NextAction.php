<?php

namespace App\Support;

/** Satu peringatan "aksi berikutnya" untuk klien di portal. Tidak memuat data sensitif selain nomor dan nominal invoice. */
final readonly class NextAction
{
    public const TONE_ACTION = 'action';   // klien perlu berbuat sesuatu

    public const TONE_WAITING = 'waiting'; // giliran tim kami

    public const TONE_INFO = 'info';

    public function __construct(
        public string $key,
        public string $tone,
        public int $priority,
        public string $title,
        public string $description,
        public ?string $label,
        public ?string $url,
    ) {}

    public function needsClient(): bool
    {
        return $this->tone === self::TONE_ACTION;
    }
}
