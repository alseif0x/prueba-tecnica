<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Reto\RuteoException;
use Reto\RuteoTaskProvider;
use Reto\Task;
use Reto\TaskStatus;
use Reto\Tests\FakeRuteo;

$madrid = new DateTimeZone('Europe/Madrid');

// En local no hay Ruteo: se usa el mismo doble que en las pruebas.
$ruteo = new RuteoTaskProvider(
    new FakeRuteo('clave-local'),
    'clave-local',
    new DateTimeImmutable('2026-11-04 10:00:00', $madrid),
);

$e = fn (?string $texto): string => htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Filtro elegido: ?estado=pendiente|en_curso|hecha. Cualquier otro valor = todas.
$filtro = TaskStatus::tryFrom((string) ($_GET['estado'] ?? ''));

// Una sola llamada a Ruteo (tarda ~5 s): se piden todas y se filtra aquí para poder pintar también los contadores.
$tareas = [];
$error = null;
try {
    $tareas = $ruteo->recentTasks();
} catch (RuteoException) {
    $error = 'Ruteo no responde ahora mismo. Vuelve a intentarlo en unos minutos.';
}

$contar = fn (?TaskStatus $estado): int => count(array_filter($tareas, fn (Task $t) => $t->status === $estado));
$visibles = $filtro === null ? $tareas : array_values(array_filter($tareas, fn (Task $t) => $t->status === $filtro));

$tipos = [
    'bike_pick_up' => 'Recoger bici',
    'station_rebalancing' => 'Reequilibrar estación',
    'dock_maintenance_check' => 'Revisar anclajes',
    'wheel_repair' => 'Reparar rueda',
];
$tipo = fn (string $t): string => $tipos[$t] ?? ucfirst(str_replace('_', ' ', $t));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tareas de Ruteo</title>
    <style>
        :root {
            --bg: #f6f7f9; --card: #fff; --text: #1d2330; --muted: #5d6676; --line: #e1e4ea; --accent: #1f6feb;
            --pendiente: #9a6700; --pendiente-bg: #fff4d6;
            --en_curso: #0b5cad; --en_curso-bg: #ddeeff;
            --hecha: #1a7f37; --hecha-bg: #dcf5e3;
            --otro: #6e4bb5; --otro-bg: #efe8fb;
            --error: #b42318; --error-bg: #fdecea;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12151b; --card: #1b2029; --text: #e6e9ef; --muted: #9aa3b2; --line: #2c3340; --accent: #6ea8ff;
                --pendiente: #f2c14e; --pendiente-bg: #3a2f10;
                --en_curso: #8cc2ff; --en_curso-bg: #13304f;
                --hecha: #6fd38d; --hecha-bg: #13351f;
                --otro: #c4a8ff; --otro-bg: #2c2245;
                --error: #ff9b8f; --error-bg: #3d1a17;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { margin: 0 0 4px; font-size: 1.5rem; }
        .sub { margin: 0 0 20px; color: var(--muted); }
        nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        nav a { padding: 6px 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--card);
                color: var(--text); text-decoration: none; }
        nav a[aria-current="page"] { border-color: var(--accent); color: var(--accent); font-weight: 600; }
        nav a span { color: var(--muted); font-variant-numeric: tabular-nums; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--line); white-space: nowrap; }
        th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        td.num, td time { font-variant-numeric: tabular-nums; }
        .estado { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .estado small { font-weight: 400; opacity: .8; }
        .muted { color: var(--muted); }
        .vacio, .error { padding: 32px 16px; text-align: center; }
        .error { color: var(--error); background: var(--error-bg); border-radius: 10px; border: 1px solid var(--error); }
        .nota { margin-top: 12px; font-size: .85rem; color: var(--muted); }
        <?php foreach (['pendiente', 'en_curso', 'hecha', 'otro'] as $c): ?>
        .estado-<?= $c ?> { color: var(--<?= $c ?>); background: var(--<?= $c ?>-bg); }
        <?php endforeach ?>
    </style>
</head>
<body>
<main>
    <h1>Tareas de Ruteo</h1>
    <p class="sub">Tareas de campo actualizadas en las últimas 48 horas. Horas en hora de Madrid.</p>

    <?php if ($error !== null): ?>
        <p class="error" role="alert"><?= $e($error) ?></p>
    <?php else: ?>
        <nav aria-label="Filtrar por estado">
            <a href="?" <?= $filtro === null ? 'aria-current="page"' : '' ?>>Todas <span><?= count($tareas) ?></span></a>
            <?php foreach (TaskStatus::cases() as $estado): ?>
                <a href="?estado=<?= $e($estado->value) ?>" <?= $filtro === $estado ? 'aria-current="page"' : '' ?>>
                    <?= $e($estado->label()) ?> <span><?= $contar($estado) ?></span>
                </a>
            <?php endforeach ?>
        </nav>

        <?php if ($visibles === []): ?>
            <div class="card vacio muted">No hay tareas<?= $filtro ? ' en estado «' . $e($filtro->label()) . '»' : '' ?> en las últimas 48 horas.</div>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                    <tr><th>Tarea</th><th>Estado</th><th>Tipo</th><th>Estación</th><th>Vehículo</th><th>Actualizada</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visibles as $t): ?>
                        <tr>
                            <td class="num">#<?= $t->id ?></td>
                            <td>
                                <?php if ($t->status === null): ?>
                                    <span class="estado estado-otro" title="Estado de Ruteo sin clasificar">Sin clasificar <small><?= $e($t->ruteoState) ?></small></span>
                                <?php else: ?>
                                    <span class="estado estado-<?= $e($t->status->value) ?>"><?= $e($t->status->label()) ?></span>
                                <?php endif ?>
                            </td>
                            <td><?= $e($tipo($t->type)) ?></td>
                            <td><?= $t->station !== null ? $e($t->station) : '<span class="muted">—</span>' ?></td>
                            <td><?= $t->truck !== null ? $e($t->truck) : '<span class="muted">Sin asignar</span>' ?></td>
                            <td><time datetime="<?= $e($t->updatedAt->format(DATE_ATOM)) ?>"><?= $e($t->updatedAt->setTimezone($madrid)->format('d/m H:i')) ?></time></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php if ($contar(null) > 0 && $filtro === null): ?>
                <p class="nota">«Sin clasificar»: Ruteo ha devuelto un estado que todavía no sabemos traducir. No aparece en ningún filtro.</p>
            <?php endif ?>
        <?php endif ?>
    <?php endif ?>
</main>
</body>
</html>
