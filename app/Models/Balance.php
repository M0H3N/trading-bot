<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange',
        'asset',
        'available',
        'locked',
        'mode',
        'synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
