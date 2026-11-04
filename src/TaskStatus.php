<?php

declare(strict_types=1);

namespace Reto;

/**
 * Los estados que entiende nuestro panel. Negocio solo quiere filtrar por estos tres.
 */
enum TaskStatus
{
    case Pendiente;
    case EnCurso;
    case Hecha;
}
