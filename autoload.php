<?php

declare(strict_types=1);

spl_autoload_register(function (string $clase): void {
    $prefijos = [
        'Reto\\Tests\\' => __DIR__ . '/tests/',
        'Reto\\' => __DIR__ . '/src/',
    ];

    foreach ($prefijos as $prefijo => $directorio) {
        if (str_starts_with($clase, $prefijo)) {
            $fichero = $directorio . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (is_file($fichero)) {
                require $fichero;
            }

            return;
        }
    }
});
