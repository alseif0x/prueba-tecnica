<?php

declare(strict_types=1);

namespace Reto;

/**
 * Los estados que entiende nuestro panel. Negocio solo quiere filtrar por estos tres.
 */
enum TaskStatus: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Hecha = 'hecha';

    /**
     * Traduce un estado de Ruteo. Devuelve null si no lo conocemos: mejor no clasificar que clasificar mal.
     */
    public static function fromRuteo(string $state): ?self
    {
        return match (true) {
            $state === 'PENDING' => self::Pendiente,
            // IN_PROGRESS_ARRIVED, IN_PROGRESS_RESERVED… (subestados de "en curso" que el PDF no documenta)
            $state === 'IN_PROGRESS', str_starts_with($state, 'IN_PROGRESS_') => self::EnCurso,
            $state === 'DONE' => self::Hecha,
            // NOT_FOUND y cualquier estado nuevo: sin clasificar hasta que Ruteo/negocio digan qué es.
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnCurso => 'En curso',
            self::Hecha => 'Hecha',
        };
    }
}
