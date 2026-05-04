<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HttpLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange',
        'scope',
        'method',
        'url',
        'request_headers',
        'request_body',
        'status_code',
        'response_headers',
        'response_body',
        'duration_ms',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'response_headers' => 'array',
        ];
    }
}
