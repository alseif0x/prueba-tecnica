<?php

declare(strict_types=1);

namespace Reto;

use DateTimeImmutable;

/**
 * Una tarea de campo tal como la ve nuestro panel.
 */
final class Task
{
    public function __construct(
        public readonly int $id,
        public readonly TaskStatus $status,
        public readonly string $type,
        public readonly string $technician,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
