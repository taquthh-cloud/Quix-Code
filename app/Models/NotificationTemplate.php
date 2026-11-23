<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'locale', 'channel', 'subject', 'body', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
