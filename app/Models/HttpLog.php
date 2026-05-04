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

    public static function formatRequestBodyForDisplay(mixed $state): string
    {
        if ($state === null || $state === []) {
            return '—';
        }

        if (is_array($state)) {
            return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $state;
    }

    public static function formatRequestBodyForTooltip(mixed $state): ?string
    {
        if ($state === null || $state === []) {
            return null;
        }

        if (is_array($state)) {
            $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return $encoded !== false ? $encoded : null;
        }

        return (string) $state;
    }
}
