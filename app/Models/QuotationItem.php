<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = ['quotation_id', 'title', 'description', 'hours', 'rate', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'rate' => 'integer',
            'amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
