# Tareas — Spec 020, funciones de interfaz pendientes

Estado: aprobado

Plan: `plan.md`. En orden de dependencia.

- [x] **T020.1. Endpoint de subida de ficheros de evidencia, con su autorización.** RF-020.7, RF-020.9
  - Resultado (2026-11-19):
    - `POST /objectives/{objective}/media`, con `UploadObjectiveEvidenceMediaRequest` (`can('addEvidence')`) y `StoreObjectiveEvidenceMediaAction`, que guarda en el disco `local` (`objectives/{id}/{uuid}.ext`) con `mediaable=Objective`.
    - Las reglas de fichero se comparten en `App\Support\MediaUploadRules`, y `ValidatesMentorFiles` delega en ella sin cambiar ningún valor.
    - `StoreObjectiveEvidenceRequest` exige que el asset sea de ese objetivo, de propósito `objective_evidence`, subido por quien crea la evidencia y sin enlazar todavía. `AttachObjectiveEvidenceAction` reasigna el asset a la evidencia dentro de una transacción.
    - `MediaAssetPolicy::view` tiene una rama al principio que delega en `ObjectivePolicy::view`; si la evidencia ya no existe, deniega. `MediaAssetController::show` descarga el fichero como adjunto, con el MIME guardado.
    - Tests: `ObjectiveEvidenceMediaTest`, 16 en rojo primero (15 fallaban) y después en verde. Suite del backend 1191/1191.
    - Riesgo aceptado: no hay restricción única en `objective_evidences.media_asset_id`, así que dos peticiones simultáneas con el mismo asset podrían enlazarlo dos veces. Solo podría hacerlo la misma persona que lo subió y no expone nada a terceros.
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
