<?php

declare(strict_types=1);

namespace Reto;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use Reto\Http\HttpClient;

/**
 * Trae las tareas de campo de Ruteo para el panel de operadores.
 *
 * Adaptado a lo que devuelve el entorno real (fixtures/ruteo-tasks.json), que no coincide con el PDF:
 * - la clave va en la URL (`api_key`); con `Authorization: Bearer` responde 401;
 * - la respuesta es una lista plana, sin `data` ni `next_page`;
 * - los campos son `task_id`, `state`, `action_type` y `updated` (dd/mm/aaaa hh:mm:ss, hora de Madrid);
 * - hay estados que el PDF no menciona y tareas repetidas.
 */
final class RuteoTaskProvider
{
    private const BASE_URL = 'https://api.ruteo.example/v2';
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 50;
    private const MAX_ATTEMPTS = 3;
    private const RECENT_WINDOW = '-2 days';
    private const DATE_FORMAT = 'd/m/Y H:i:s';
    private const TIMEZONE = 'Europe/Madrid';

    private readonly DateTimeZone $zone;

    /**
     * @param int $retryDelayMs espera base entre reintentos (se multiplica por el número de intento)
     */
    public function __construct(
        private readonly HttpClient $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly DateTimeImmutable $now = new DateTimeImmutable(),
        private readonly int $retryDelayMs = 200,
    ) {
        $this->zone = new DateTimeZone(self::TIMEZONE);
    }

    /**
     * Tareas actualizadas en las últimas 48 horas, opcionalmente filtradas por estado.
     *
     * Si una tarea aparece varias veces, se queda la versión con la fecha de actualización más reciente.
     * Las tareas con un estado que no sabemos traducir salen sin filtro (status null) pero no en ningún filtro.
     *
     * @return list<Task>
     * @throws RuteoException si Ruteo no responde bien
     */
    public function recentTasks(?TaskStatus $status = null): array
    {
        $since = $this->now->modify(self::RECENT_WINDOW);

        /** @var array<int, Task> $byId */
        $byId = [];

        foreach ($this->fetchRows($since) as $row) {
            $task = $this->toTask($row);
            if ($task === null || $task->updatedAt < $since) {
                continue;
            }

            $previous = $byId[$task->id] ?? null;
            if ($previous === null || $task->updatedAt >= $previous->updatedAt) {
                $byId[$task->id] = $task;
            }
        }

        // El filtro se hace aquí: el `status` del PDF no coincide con los estados reales (IN_PROGRESS_ARRIVED…).
        $tasks = array_values(array_filter(
            $byId,
            fn (Task $task) => $status === null || $task->status === $status,
        ));

        usort($tasks, fn (Task $a, Task $b) => $b->updatedAt <=> $a->updatedAt);

        return $tasks;
    }

    /**
     * @return list<array<mixed>>
     */
    private function fetchRows(DateTimeImmutable $since): array
    {
        $query = [
            // No consta que Ruteo respete `from` (la respuesta real trae una tarea del 28/10); filtramos también aquí.
            'from' => $since->setTimezone($this->zone)->format('Y-m-d'),
            'page_size' => self::PAGE_SIZE,
        ];

        $rows = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = $this->request('/tasks', $query + ['page' => $page]);

            // Formato real: lista plana. Por si acaso, aceptamos también el del PDF ({data, next_page}).
            $pageRows = array_is_list($body) ? $body : ($body['data'] ?? []);
            if (! is_array($pageRows)) {
                throw new RuteoException('Ruteo devolvió una respuesta con un formato inesperado');
            }

            foreach ($pageRows as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            $hasMore = array_is_list($body)
                ? count($pageRows) >= self::PAGE_SIZE
                : ($body['next_page'] ?? null) !== null;

            if (! $hasMore) {
                return $rows;
            }
        }

        error_log(sprintf('Ruteo: se ha llegado al máximo de %d páginas; la lista puede estar incompleta', self::MAX_PAGES));

        return $rows;
    }

    /**
     * @param array<mixed> $row
     */
    private function toTask(array $row): ?Task
    {
        $id = $row['task_id'] ?? null;
        $state = $row['state'] ?? null;
        $updated = $row['updated'] ?? null;

        if (! is_int($id) || ! is_string($state) || ! is_string($updated)) {
            error_log('Ruteo: se descarta una tarea con campos que faltan o con tipo inesperado');

            return null;
        }

        $updatedAt = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $updated, $this->zone);
        if ($updatedAt === false) {
            error_log("Ruteo: se descarta la tarea {$id} por una fecha ilegible");

            return null;
        }

        $status = TaskStatus::fromRuteo($state);
        if ($status === null) {
            error_log("Ruteo: estado desconocido «{$state}» en la tarea {$id}");
        }

        $station = $row['station']['name'] ?? null;
        $truck = $row['truck'] ?? null;
        $type = $row['action_type'] ?? null;

        // A propósito no se guarda nada de `user`: el panel no necesita datos personales del técnico.
        return new Task(
            id: $id,
            status: $status,
            ruteoState: $state,
            type: is_string($type) ? $type : 'desconocido',
            station: is_string($station) ? $station : null,
            truck: is_string($truck) ? $truck : null,
            updatedAt: $updatedAt,
        );
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
                $query + ['api_key' => $this->apiKey],
            );

            if ($response->status >= 200 && $response->status < 300) {
                try {
                    $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $error) {
                    throw new RuteoException('Ruteo devolvió un JSON no válido', previous: $error);
                }

                if (! is_array($body)) {
                    throw new RuteoException('Ruteo devolvió una respuesta con un formato inesperado');
                }

                return $body;
            }

            // Solo se reintentan los errores pasajeros. Un 401/403/400 no se arregla reintentando.
            $transient = $response->status >= 500 || $response->status === 429;

            if (! $transient || $attempt >= self::MAX_ATTEMPTS) {
                // Nunca se registra la query completa: lleva la clave.
                error_log(sprintf(
                    'Ruteo: GET %s (página %s) respondió %d tras %d intento(s)',
                    $path,
                    (string) ($query['page'] ?? '?'),
                    $response->status,
                    $attempt,
                ));

                throw new RuteoException("Ruteo respondió {$response->status}", $response->status);
            }

            if ($this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000 * $attempt);
            }
        }
    }
}
