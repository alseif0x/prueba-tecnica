<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Reto\RuteoTaskProvider;
use Reto\Tests\FakeRuteo;

// En local no hay Ruteo: se usa el mismo doble que en las pruebas.
$ruteo = new RuteoTaskProvider(
    new FakeRuteo('clave-local'),
    'clave-local',
    new DateTimeImmutable('2026-11-04 10:00:00', new DateTimeZone('Europe/Madrid')),
);

$tareas = $ruteo->recentTasks();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tareas de Ruteo</title>
</head>
<body>
    <h1>Tareas de Ruteo</h1>
    <p><?= count($tareas) ?> tareas en los últimos 2 días.</p>
</body>
</html>
