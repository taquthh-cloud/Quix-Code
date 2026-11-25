<?php

namespace App\Rules;

use App\Exceptions\UnsafeUploadException;
use App\Support\SecureUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/** Aturan validasi untuk komponen Livewire/FormRequest: `new SafeUpload('proof')`. */
final class SafeUpload implements ValidationRule
{
    public function __construct(private readonly string $kind = SecureUpload::KIND_PROOF) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(__('uploads.errors.invalid'));

            return;
        }

        try {
            SecureUpload::inspect($value, $this->kind);
        } catch (UnsafeUploadException $e) {
            $fail($e->translated(SecureUpload::maxMegabytes($this->kind)));
        }
    }
}
