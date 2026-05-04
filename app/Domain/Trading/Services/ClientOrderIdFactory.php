<?php

namespace App\Domain\Trading\Services;

use App\Models\Market;
use Illuminate\Support\Str;

class ClientOrderIdFactory
{
    public function make(Market $market, string $side): string
    {
        return sprintf(
            'tb-%s-%s-%s-%s',
            $market->exchange,
            strtolower($market->symbol),
            $side,
            Str::lower(Str::random(12)),
        );
    }
}
