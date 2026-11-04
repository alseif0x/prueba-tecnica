# API de Ruteo v2 (resumen del PDF del proveedor)

## Autenticación

Cabecera `Authorization: Bearer <token>`.

## Listar tareas

```
GET https://api.ruteo.example/v2/tasks
```

| Parámetro | Descripción |
|---|---|
| `status` | Filtra por estado: `PENDING`, `IN_PROGRESS` o `DONE` |
| `from` | Fecha mínima de actualización (`AAAA-MM-DD`) |
| `page` | Página, empezando por 1 |
| `page_size` | Tamaño de página, hasta 100 |

Respuesta:

```json
{
  "data": [
    { "id": 123, "status": "DONE", "type": "PICKUP", "user": { "id": 7, "full_name": "…" }, "updated": "2026-11-02T10:00:00Z" }
  ],
  "next_page": 2
}
```

`next_page` es `null` en la última página.

## Estados

`PENDING`, `IN_PROGRESS`, `DONE`.
