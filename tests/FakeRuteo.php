<?php

declare(strict_types=1);

namespace Reto\Tests;

use Reto\Http\HttpClient;
use Reto\Http\HttpResponse;

/**
 * Doble de Ruteo para las pruebas y el panel en local.
 */
final class FakeRuteo implements HttpClient
{
    /** @var list<array{url: string, query: array<string, scalar>, headers: array<string, string>}> */
    public array $requests = [];

    /**
     * @param list<int> $failures códigos de error que devolverá en las primeras peticiones
     */
    public function __construct(
        private readonly string $validKey,
        private array $failures = [],
    ) {
    }

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'query' => $query, 'headers' => $headers];

        if ($this->failures !== []) {
            return new HttpResponse(array_shift($this->failures), '{"error": "upstream"}');
        }

        if (($query['api_key'] ?? null) !== $this->validKey) {
            return new HttpResponse(401, '{"error": "unauthorized"}');
        }

        if ((int) ($query['page'] ?? 1) > 1) {
            return new HttpResponse(200, '[]');
        }

        return new HttpResponse(200, (string) file_get_contents(__DIR__ . '/../fixtures/ruteo-tasks.json'));
    }
}
