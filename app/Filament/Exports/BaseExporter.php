<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\Exporter;

abstract class BaseExporter extends Exporter
{
    public function getJobConnection(): ?string
    {
        $connection = config('filament-export.queue_connection');

        return filled($connection) ? (string) $connection : null;
    }
}
