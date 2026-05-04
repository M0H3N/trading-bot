<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CircuitBreaker extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange',
        'scope',
        'failure_count',
        'opened_until',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'opened_until' => 'datetime',
        ];
    }
}
