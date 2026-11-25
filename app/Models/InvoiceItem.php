<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'title', 'qty', 'unit_price', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_price' => 'integer', 'amount' => 'integer', 'sort_order' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
