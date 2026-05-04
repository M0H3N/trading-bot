<?php

namespace App\Domain\Http\Services;

use App\Models\HttpLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpLogService
{
    public function record(
        ?string $exchange,
        ?string $scope,
        string $method,
        string $url,
        array $requestHeaders = [],
        mixed $requestBody = null,
        ?int $statusCode = null,
        array $responseHeaders = [],
        mixed $responseBody = null,
        ?int $durationMs = null,
        ?string $error = null,
    ): void {
        if (! (bool) config('trading.http_logging.enabled', true)) {
            return;
        }

        try {
            HttpLog::on($this->connectionForInsert())->create([
                'exchange' => $exchange,
                'scope' => $scope,
                'method' => strtoupper($method),
                'url' => $url,
                'request_headers' => $this->redactHeaders($requestHeaders),
                'request_body' => $this->normalizeBody($requestBody),
                'status_code' => $statusCode,
                'response_headers' => $this->redactHeaders($responseHeaders),
                'response_body' => $this->stringifyBody($responseBody),
                'duration_ms' => $durationMs,
                'error' => $error,
            ]);
        } catch (Throwable $exception) {
            Log::warning('HTTP log could not be persisted.', [
                'exchange' => $exchange,
                'scope' => $scope,
                'method' => $method,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Use a separate PDO from the default connection when available so HTTP audit rows
     * survive rollbacks on the default connection.
     */
    protected function connectionForInsert(): string
    {
        $explicit = config('trading.http_logging.database_connection');
        if (is_string($explicit) && $explicit !== '' && config("database.connections.{$explicit}")) {
            return $explicit;
        }

        if (config('database.connections.http_logs')) {
            return 'http_logs';
        }

        return (string) config('database.default');
    }

    protected function redactHeaders(array $headers): array
    {
        $redacted = [];
        $sensitive = collect((array) config('trading.http_logging.redact_headers', []))
            ->map(fn (string $header): string => strtolower($header))
            ->all();

        foreach ($headers as $key => $value) {
            $redacted[$key] = in_array(strtolower((string) $key), $sensitive, true) ? '[redacted]' : $value;
        }

        return $redacted;
    }

    protected function normalizeBody(mixed $body): ?array
    {
        if ($body === null) {
            return null;
        }

        if (is_array($body)) {
            return $body;
        }

        return ['value' => $body];
    }

    protected function stringifyBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        $encoded = json_encode($body, $flags);

        return $encoded !== false ? $encoded : '[response body could not be encoded as JSON]';
    }
}
