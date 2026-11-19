# Tareas — Spec 020, funciones de interfaz pendientes

Estado: borrador

Plan: `plan.md`. En orden de dependencia.

- [ ] **T020.1. Endpoint de subida de ficheros de evidencia, con su autorización.** RF-020.7, RF-020.9
  - Request, Action, ruta, rama de `MediaAssetPolicy` y validación de `media_asset_id` en `evidences`, con los tests de autorización en rojo primero.
  - Hecho cuando: pasan los tests del plan (subida, validación del asset y `GET /media`), y también la suite de progreso y la de media existentes.

- [ ] **T020.2. Purga de ficheros de evidencia huérfanos.** RF-020.10
  - Comando y programación en `routes/console.php`.
  - Hecho cuando:
    - los tests de la purga pasan;
    - en la cascada de borrar una cuenta, `GET /media/{id}` da 403 y la purga elimina el fichero;
    - los eventos y el historial de la spec 014 no cambian.

- [ ] **T020.3. Evidencia con fichero en la interfaz.** RF-020.8
  - `ObjectiveEvidenceForm` con el fichero opcional, la subida en dos pasos y el enlace al fichero en las vistas de evidencias del titular y del mentor.
  - Hecho cuando:
    - Vitest cubre el esquema (nota o fichero) y el hook;
    - si la subida falla, se conserva la nota;
    - typecheck y lint pasan.

- [ ] **T020.4. Datos personales en el perfil.** RF-020.11, RF-020.12
  - Formulario de nombre, apellidos y zona horaria, y campo de foto.
  - Hecho cuando:
    - Vitest cubre el esquema y el helper de zonas horarias;
    - al guardar, el nombre nuevo se ve en la cabecera y en el lateral sin recargar;
    - un error del servidor aparece en su campo.

- [ ] **T020.5. Check-in emocional del mentee.** RF-020.4, RF-020.5, RF-020.6
  - `features.emotional_check_ins` en `RelationshipResource`, con test en Pest; comprobar qué tipos de relación admite `menteeRelationship()`; la tarjeta y su hook.
  - Hecho cuando:
    - con un plan sin el entitlement no hay tarjeta ni llamada que dé 403;
    - el envío se anuncia y la lista de los 5 últimos se actualiza;
    - Vitest y Pest pasan.

- [ ] **T020.6. Invitar empleados.** RF-020.1, RF-020.2
  - Diálogo de invitación, esquema y mutación.
  - Hecho cuando:
    - un email duplicado y el límite del plan muestran su error sin perder los datos;
    - tras el éxito, el empleado aparece en la lista como «invitado»;
    - Vitest pasa.

- [ ] **T020.7. Editar, activar y desactivar empleados.** RF-020.3
  - Diálogo de edición, acciones de activar y desactivar, y confirmación de la desactivación.
  - Hecho cuando: cada acción actualiza la lista y anuncia el resultado, el foco vuelve a la fila y Vitest pasa.

- [ ] **T020.8. Fixtures y suite de accesibilidad de las pantallas nuevas.** RNF-020.3
  - El seeder deja los datos que hacen falta: una relación de ana con check-ins y un empleado invitado e inactivo para activarlo. Se añaden rutas a `routes.ts` y pruebas de foco por flujo.
  - Hecho cuando:
    - la suite completa pasa dos veces seguidas en local sin volver a sembrar;
    - el CI pasa.

- [ ] **T020.9. Cierre.**
  - Suites completas del backend y del frontend, CI, `fixtures.md` y `MEMORY.md`, y la spec marcada como implementada.
  - Hecho cuando: todo está en verde y la documentación, actualizada.
