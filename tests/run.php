<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Reto\RuteoTaskProvider;
use Reto\Task;
use Reto\TaskStatus;
use Reto\Tests\FakeRuteo;

const CLAVE = 'clave-de-prueba';

$madrid = new DateTimeZone('Europe/Madrid');
$ahora = new DateTimeImmutable('2026-11-04 10:00:00', $madrid);

$proveedor = fn (FakeRuteo $ruteo, string $clave = CLAVE) => new RuteoTaskProvider($ruteo, $clave, $ahora);
$ids = fn (array $tareas) => array_map(fn (Task $tarea) => $tarea->id, $tareas);
$tarea = function (array $tareas, int $id): Task {
    foreach ($tareas as $tarea) {
        if ($tarea->id === $id) {
            return $tarea;
        }
    }

    throw new RuntimeException("Falta la tarea {$id}");
};
$comprobar = function (bool $condicion, string $mensaje): void {
    if (! $condicion) {
        throw new RuntimeException($mensaje);
    }
};

$pruebas = [
    'Devuelve las tareas de la respuesta real' => function () use ($proveedor, $ids, $comprobar) {
        $encontradas = $ids($proveedor(new FakeRuteo(CLAVE))->recentTasks());
        foreach ([88213, 88219, 88225] as $id) {
            $comprobar(in_array($id, $encontradas, true), "Falta la tarea {$id}");
        }
    },

    'Traduce los estados a los nuestros' => function () use ($proveedor, $tarea, $comprobar) {
        $tareas = $proveedor(new FakeRuteo(CLAVE))->recentTasks();
        $comprobar($tarea($tareas, 88219)->status === TaskStatus::EnCurso, 'La 88219 (IN_PROGRESS_ARRIVED) debería estar en curso');
        $comprobar($tarea($tareas, 88225)->status === TaskStatus::Hecha, 'La 88225 (DONE) debería estar hecha');
    },

    'No repite tareas' => function () use ($proveedor, $ids, $comprobar) {
        $encontradas = $ids($proveedor(new FakeRuteo(CLAVE))->recentTasks());
        $comprobar($encontradas !== [], 'No ha devuelto ninguna tarea');
        $comprobar(count($encontradas) === count(array_unique($encontradas)), 'Hay tareas repetidas');
    },

    'Solo devuelve los últimos 2 días' => function () use ($proveedor, $ids, $comprobar) {
        $encontradas = $ids($proveedor(new FakeRuteo(CLAVE))->recentTasks());
        $comprobar($encontradas !== [], 'No ha devuelto ninguna tarea');
        $comprobar(! in_array(87990, $encontradas, true), 'La 87990 es del 28/10 y hoy es 04/11: no debería salir');
    },

    'Filtra por estado' => function () use ($proveedor, $ids, $comprobar) {
        $hechas = $proveedor(new FakeRuteo(CLAVE))->recentTasks(TaskStatus::Hecha);
        $comprobar(in_array(88225, $ids($hechas), true), 'Falta la 88225 entre las hechas');
        foreach ($hechas as $hecha) {
            $comprobar($hecha->status === TaskStatus::Hecha, "La {$hecha->id} no está hecha y ha salido en el filtro");
        }
    },

    'Lee bien las fechas (dd/mm/aaaa, hora de Madrid)' => function () use ($proveedor, $tarea, $comprobar, $madrid) {
        $fecha = $tarea($proveedor(new FakeRuteo(CLAVE))->recentTasks(), 88225)->updatedAt;
        $esperada = new DateTimeImmutable('2026-11-02 18:30:00', $madrid);
        $comprobar(
            $fecha->getTimestamp() === $esperada->getTimestamp(),
            'La 88225 se actualizó el 2 de noviembre a las 18:30 (Madrid); se ha leído ' . $fecha->format(DATE_ATOM),
        );
    },

    'No guarda datos personales de los técnicos' => function () use ($proveedor, $comprobar) {
        $volcado = var_export($proveedor(new FakeRuteo(CLAVE))->recentTasks(), true);
        foreach (['Jordi Ejemplo', 'Pau Ejemplo', 'Marta Ejemplo'] as $nombre) {
            $comprobar(! str_contains($volcado, $nombre), "Aparece el nombre «{$nombre}» en las tareas");
        }
    },

    'No reintenta un error de credenciales' => function () use ($proveedor, $comprobar) {
        $ruteo = new FakeRuteo(CLAVE);
        $lanzada = false;
        try {
            $proveedor($ruteo, 'clave-incorrecta')->recentTasks();
        } catch (Throwable) {
            $lanzada = true;
        }
        $comprobar($lanzada, 'Con una clave incorrecta debería fallar');
        $comprobar(count($ruteo->requests) === 1, 'Ha hecho ' . count($ruteo->requests) . ' peticiones con una clave incorrecta');
    },

    'Aguanta un 503 puntual' => function () use ($proveedor, $ids, $comprobar) {
        $encontradas = $ids($proveedor(new FakeRuteo(CLAVE, failures: [503]))->recentTasks());
        $comprobar(in_array(88219, $encontradas, true), 'Tras un 503 puntual debería devolver las tareas');
    },
];

$fallidas = 0;

foreach ($pruebas as $nombre => $prueba) {
    try {
        $prueba();
        echo "  ✔ {$nombre}\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "  ✘ {$nombre}\n      {$error->getMessage()}\n";
    }
}

$total = count($pruebas);
echo "\n" . ($total - $fallidas) . " de {$total} pruebas pasan.\n";
exit($fallidas === 0 ? 0 : 1);
