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

- [x] **T020.2. Purga de ficheros de evidencia huérfanos.** RF-020.10
  - Resultado (2026-11-19):
    - Comando `media:purge-orphan-evidence {--hours=24} {--dry-run}`, que se ejecuta cada día (`withoutOverlapping`).
    - La consulta es única y va por lotes (`chunkById`). Borra el fichero y luego la fila. En el log solo deja el recuento y los ids, sin `activity()`.
    - Hechos comprobados en el esquema:
      - `User` y `Objective` usan SoftDeletes, así que borrar una cuenta desde la app no borra las evidencias;
      - `MediaAsset` y `ObjectiveEvidence` no usan SoftDeletes;
      - `objective_evidences.media_asset_id` tiene `nullOnDelete`.
    - Tests: `PurgeOrphanEvidenceMediaTest`, 6 en rojo primero y después en verde. Cubren los casos (a) a (g), incluida la cascada (403 y luego la purga) y que no cambian los recuentos de `evidence_deletion_events` ni de `activity_log`. Suite del backend 1197/1197.
    - **Limitación previa a esta spec, fuera de alcance:** `media_assets.uploaded_by` y `relationship_id` tienen `cascadeOnDelete`. Si una cuenta o una relación se borra de verdad (hard delete), desaparece la fila del asset, pero el fichero se queda en el disco y la purga no lo ve. Afecta también a los ficheros del mentor. Habría que hacer un barrido del disco aparte.
  - Comando y programación en `routes/console.php`.
  - Hecho cuando:
    - los tests de la purga pasan;
    - en la cascada de borrar una cuenta, `GET /media/{id}` da 403 y la purga elimina el fichero;
    - los eventos y el historial de la spec 014 no cambian.

- [x] **T020.3. Evidencia con fichero en la interfaz.** RF-020.8
  - Resultado (2026-11-19):
    - Código:
      - `lib/media-types.ts`, con los tipos admitidos compartidos con el mentor;
      - el esquema acepta fichero, nota o los dos, con las claves `file_too_large` y `file_unsupported`;
      - `submitObjectiveEvidence`, que sube primero el fichero y luego crea la evidencia; si la subida falla, no la crea;
      - `FileUploadField` pasa a `components/ui/`;
      - el enlace «Descargar {name}» en `ObjectivesBlock` mide 24 px.
    - El recurso del backend ya incluía `media_asset` sin `path` ni `disk`, así que no se tocó.
    - En la revisión se añadió el texto de reserva `progress.evidence.error` para cuando el error no trae mensaje.
    - Vitest 406, typecheck y lint limpios.
    - En el navegador, con el build de producción y `mis_project_audit`, 9/9: ana adjunta un `.txt` y lo descarga (200); un `.html` muestra el error en el campo; `mentor.audit` descarga (200) y `kai` recibe 403.
    - Entorno: durante la prueba se apuntó `.env.backend` temporalmente a la base de auditoría y después se restauró (`mis_project` se comprobó sin cambios). El build de producción no puede convivir con `next dev`, porque comparten el volumen `.next`.
  - `ObjectiveEvidenceForm` con el fichero opcional, la subida en dos pasos y el enlace al fichero en las vistas de evidencias del titular y del mentor.
  - Hecho cuando:
    - Vitest cubre el esquema (nota o fichero) y el hook;
    - si la subida falla, se conserva la nota;
    - typecheck y lint pasan.

- [x] **T020.4. Datos personales en el perfil.** RF-020.11, RF-020.12
  - Resultado (2026-11-20):
    - Código:
      - `ProfileDetailsForm` (nombre, apellidos y zona horaria, con RHF) y `AvatarField` (foto, sin opción de quitarla), en `components/profile/`;
      - `lib/time-zones.ts` traduce los 19 nombres antiguos de ICU que `timezone:all` rechaza (plan, decisión 5);
      - `hooks/use-profile.ts`; la foto se sube con `POST` y `_method=PUT` (decisión 6);
      - `lib/avatar-src.ts` y el rewrite `/storage/avatars/*` en `next.config.ts` (decisión 7); `Avatar` muestra la foto en la tarjeta del perfil y en el lateral;
      - los errores 422 se colocan en su campo con `mapServerFieldErrors` (ahora exportado desde `lib/availability.ts`); confirmaciones en regiones `role="status"` siempre montadas.
    - En la revisión se quitó el `disabled` del botón «Cambiar foto»: al vaciar el fichero tras el éxito, el botón se desactivaba con el foco encima y el foco se perdía.
    - Vitest 437 (31 nuevos, 13 en rojo primero), typecheck y lint limpios.
    - En el navegador, con el build de producción y `mis_project_audit`, 23/23: el nombre nuevo aparece en el lateral y en la tarjeta sin recargar, con el foco en «Guardar datos»; `Asia/Kolkata` y `America/Argentina/Buenos_Aires` se guardan (200) y la lista no ofrece los nombres antiguos; un 422 simulado aparece bajo la zona horaria con `aria-invalid` y el foco en el campo; un PNG se sube y se ve desde `/storage/avatars/`; un `.txt` o un fichero de más de 2 MB se rechazan sin petición; axe sin violaciones a 1280 y 320 px.
    - Entorno: el build de prueba se hizo con `NEXT_PUBLIC_API_TIMEOUT_MS=30000` porque el host tardaba 2–6 s por petición. `.env.backend` se restauró y `mis_project` se comprobó sin cambios.
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
