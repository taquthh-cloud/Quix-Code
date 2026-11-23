<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Hanya untuk estimasi tampilan di situs publik; angka mengikat selalu memakai mata uang proyek. */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = ['base_currency', 'target_currency', 'rate', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'base_currency' => Currency::class,
            'target_currency' => Currency::class,
            'rate' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    public static function latestFor(Currency $base, Currency $target): ?self
    {
        return static::query()
            ->where('base_currency', $base->value)
            ->where('target_currency', $target->value)
            ->latest('fetched_at')
            ->first();
    }
}
