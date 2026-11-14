# Plan — Spec 018: Límite del rango de consulta de huecos libres

**Estado:** implementado.

## Cambios

- `config/scheduling.php`: `slots_max_range_days` (por defecto 31, desde `SCHEDULING_SLOTS_MAX_RANGE_DAYS`) (RF-018.3).
- `GetAvailabilitySlotsRequest::after()`: tras comprobar `to > from` (ya existe y usa la zona del perfil para interpretar ambos valores), si `to - from` en segundos reales supera `slots_max_range_days × 86400`, añade un error en `to` con `__('validation.availability_slots_range', ['days' => N])` (RF-018.1). Al ser validación del Form Request, el controlador y `AvailabilitySlotsService` no se ejecutan (RNF-018.1).
- `lang/{es,en,pt}/validation.php`: clave `availability_slots_range` («El rango de consulta no puede superar :days días.» y equivalentes).

## Decisión

Validar en el Form Request y no en el servicio: así se rechaza antes de consultar la base de datos y el error sigue el formato 422 habitual. Descartado recortar el rango en silencio: el cliente recibiría menos huecos sin saberlo.

## Tests

`AvailabilityTest` (o un archivo nuevo `SlotsRangeLimitTest`): 31 días exactos aceptados; 31 días + 1 minuto rechazado con 422 en `to` y mensaje en es/en/pt; rango que cruza un cambio de horario medido en instantes; valor configurado distinto (p. ej. 7) respetado; el servicio no se invoca en el rechazo; los tests existentes de huecos y de la 017 (backend y Vitest) siguen en verde.
