<?php

declare(strict_types=1);

namespace Reto;

use DateTimeImmutable;

/**
 * Una tarea de campo tal como la ve nuestro panel.
 *
 * No incluye datos personales del técnico.
 */
final class Task
{
    /**
     * @param ?TaskStatus $status null si Ruteo devuelve un estado que no sabemos traducir
     * @param string $ruteoState estado tal cual lo manda Ruteo (para mostrarlo y depurar)
     */
    public function __construct(
        public readonly int $id,
        public readonly ?TaskStatus $status,
        public readonly string $ruteoState,
        public readonly string $type,
        public readonly ?string $station,
        public readonly ?string $truck,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
