# Reto: integrar Ruteo

**Tiempo: 15 minutos.**

## Contexto

Estamos montando un panel web para los operadores de un servicio de bicicleta compartida. Las tareas de campo (recoger bicis
averiadas, reequilibrar estaciones…) las gestiona un proveedor externo, **Ruteo**.

Negocio quiere que el panel muestre **las tareas de Ruteo de los últimos 2 días, filtrables por estado**
(*pendiente*, *en curso*, *hecha*).

Un compañero generó con IA [`src/RuteoTaskProvider.php`](src/RuteoTaskProvider.php) a partir del PDF del proveedor,
que tienes resumido en [`docs/ruteo-api.md`](docs/ruteo-api.md). Nadie lo ha probado.

Otro compañero ha hecho una primera llamada al entorno real y ha anotado esto:

- Con `Authorization: Bearer` responde `401`. Funciona poniendo la clave en la URL: `?api_key=…`.
- La respuesta real está en [`fixtures/ruteo-tasks.json`](fixtures/ruteo-tasks.json).
- Ruteo confirma por correo que las fechas van en **hora de Madrid**.
- La respuesta tardó casi 5 segundos.

Desde el reto no hay acceso a Ruteo: las pruebas y el panel usan un doble, [`tests/FakeRuteo.php`](tests/FakeRuteo.php).

## Tareas

1. Haz que pasen las pruebas:

   ```bash
   php tests/run.php
   ```

   Puedes cambiar lo que quieras de `src/`, incluida la clase `Task`. No modifiques las pruebas que hay,
   `tests/FakeRuteo.php` ni `fixtures/`. Si quieres añadir pruebas, hazlo al final de `tests/run.php`.

2. Rellena [`DECISIONES.md`](DECISIONES.md).

## Extra

Convierte [`public/index.php`](public/index.php) en la pantalla de tareas de ese panel.

```bash
php -S localhost:8000 -t public
```

Y abre http://localhost:8000. Si el puerto 8000 está ocupado, usa otro.

Puedes usar las librerías y herramientas que quieras.

## Entrega

Sube tus cambios a una rama y abre una *pull request* contra `main`.

## Herramientas

Puedes usar el asistente de IA que utilices habitualmente.

## Requisitos

PHP, sin dependencias.
