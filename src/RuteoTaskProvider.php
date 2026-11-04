<?php

declare(strict_types=1);

namespace Reto;

use DateTimeImmutable;
use Reto\Http\HttpClient;
use RuntimeException;

/**
 * Trae las tareas de campo de Ruteo para el panel de operadores.
 *
 * Borrador generado con un asistente de IA a partir del PDF de Ruteo (docs/ruteo-api.md).
 * No se ha probado contra el entorno real.
 */
final class RuteoTaskProvider
{
    private const BASE_URL = 'https://api.ruteo.example/v2';
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey,
        private readonly DateTimeImmutable $now = new DateTimeImmutable(),
    ) {
    }

    /**
     * Tareas de los últimos 2 días, opcionalmente filtradas por estado.
     *
     * @return list<Task>
     */
    public function recentTasks(?TaskStatus $status = null): array
    {
        $query = [
            'from' => $this->now->modify('-2 days')->format('Y-m-d'),
            'page_size' => 100,
        ];

        if ($status !== null) {
            $query['status'] = $this->toRuteoStatus($status);
        }

        $tasks = [];
        $page = 1;

        do {
            $body = $this->request('/tasks', $query + ['page' => $page]);

            foreach ($body['data'] ?? [] as $row) {
                $tasks[] = new Task(
                    id: $row['id'],
                    status: $this->fromRuteoStatus($row['status']),
                    type: $row['type'],
                    technician: $row['user']['full_name'],
                    updatedAt: new DateTimeImmutable($row['updated']),
                );
            }

            $page = $body['next_page'] ?? null;
        } while ($page !== null);

        return $tasks;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<mixed>
     */
    private function request(string $path, array $query): array
    {
        for ($attempt = 1; ; $attempt++) {
            $response = $this->http->get(
                self::BASE_URL . $path,
                $query,
                ['Authorization' => 'Bearer ' . $this->apiKey],
            );

            if ($response->status < 400) {
                return json_decode($response->body, true);
            }

            if ($attempt >= self::MAX_ATTEMPTS) {
                error_log('Ruteo request failed: ' . self::BASE_URL . $path . '?' . http_build_query($query));

                throw new RuntimeException("Ruteo respondió {$response->status}");
            }
        }
    }

    private function toRuteoStatus(TaskStatus $status): string
    {
        return match ($status) {
            TaskStatus::Pendiente => 'PENDING',
            TaskStatus::EnCurso => 'IN_PROGRESS',
            TaskStatus::Hecha => 'DONE',
        };
    }

    private function fromRuteoStatus(string $status): TaskStatus
    {
        return match ($status) {
            'IN_PROGRESS' => TaskStatus::EnCurso,
            'DONE' => TaskStatus::Hecha,
            default => TaskStatus::Pendiente,
        };
    }
}
