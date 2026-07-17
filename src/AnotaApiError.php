<?php

declare(strict_types=1);

namespace Anota;

/**
 * Thrown for any non-2xx response from the anota API.
 *
 * Carries the HTTP status code and the human-readable message extracted from
 * the response body (the ASP.NET problem-details `detail`, falling back to
 * `title`, then the raw body). Network-level failures are NOT wrapped in this
 * type — they surface as PHP's native {@see \RuntimeException}.
 */
class AnotaApiError extends \Exception
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
