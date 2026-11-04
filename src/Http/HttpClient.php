<?php

declare(strict_types=1);

namespace Reto\Http;

/**
 * Cliente HTTP mínimo. En producción lo implementa un adaptador sobre Guzzle.
 */
interface HttpClient
{
    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse;
}
