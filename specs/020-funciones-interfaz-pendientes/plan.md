# Plan — Spec 020, funciones de interfaz pendientes

Estado: aprobado

Base: `spec.md` (aprobada el 2026-11-19, decisiones D-1 a D-4).

## Hechos comprobados en el código (2026-11-19)

- **Empleados:** `EmployeeController` tiene `index`, `invite`, `update`, `activate` y `deactivate` (`DELETE /employees/{id}`).
  - `InviteEmployeeAction` crea el usuario con `is_active=false` y una contraseña aleatoria, y llama a `Password::sendResetLink`.
  - `InviteEmployeeRequest` valida email (único), nombre, apellidos, puesto y departamento; `UpdateEmployeeRequest`, puesto, departamento y `manager_name`.
- **Check-in emocional:** `MenteeEmotionalCheckInController` (`index` y `store`) usa `menteeRelationship()` y `requireEntitlement(EMOTIONAL_CHECK_INS)`. `StoreEmotionalCheckInRequest` valida `mood_score` (1–5, obligatorio), `stress_score` (1–5, opcional), `note` (hasta 10 000 caracteres) y `checked_in_at` (no futuro). Hoy `RelationshipResource` **no** expone los entitlements del plan.
- **Evidencias:**
  - `StoreObjectiveEvidenceRequest` acepta `media_asset_id` (`uuid` que exista en `media_assets`) y exige la nota si falta el fichero.
  - `ObjectivePolicy::view` permite a soporte, al titular, al mentor asignado y a los miembros del colectivo; `addEvidence` aplica sus propias reglas por `scope`.
  - **No existe ningún endpoint para borrar evidencias ni objetivos.** Solo se borran en cascada al eliminar una cuenta (política de retención).
- **Media:** `MediaAssetPolicy::view` solo contempla relaciones personales, retos, entregas y feedback. Las subidas existentes son solo para mentores (`/mentor/media`, `UploadMediaRequest`, con los tipos de los ficheros del mentor). `GET /media/{media}` sirve el fichero según la política.
- **Perfil:** `UpdateProfileRequest` (name, last_name, timezone y locale) y `UpdateAvatarRequest` (imagen de hasta 2 MB).

## Archivos y responsabilidades

| Archivo | Responsabilidad | RF |
|---|---|---|
| `backend/routes/api.php` | `POST /objectives/{objective}/media` (multipart `file`). | 020.7 |
| `backend/app/Http/Requests/Objective/UploadObjectiveEvidenceMediaRequest.php` | `file` obligatorio, con los mismos tipos MIME y el mismo máximo de 25 MB que `UploadMediaRequest` (constante compartida, sin duplicar la lista). | 020.7 |
| `backend/app/Actions/StoreObjectiveEvidenceMediaAction.php` | Guarda el fichero en el disco privado y crea el `MediaAsset` con `purpose=objective_evidence`, `mediaable=Objective`, `uploaded_by` y `relationship_id` del objetivo si lo tiene. | 020.7 |
| `backend/app/Http/Controllers/Api/ObjectiveController.php` | La acción `media`, con `authorize('addEvidence', $objective)`. En `evidences`, si llega `media_asset_id`, se valida que el asset es de ese objetivo, `purpose=objective_evidence`, subido por quien crea la evidencia y aún sin evidencia; después se reasigna `mediaable` a la `ObjectiveEvidence` creada, dentro de la misma transacción. | 020.7, 020.8 |
| `backend/app/Policies/MediaAssetPolicy.php` | Rama nueva: si el `purpose` es `objective_evidence`, `view` = `ObjectivePolicy::view` sobre el objetivo, resuelto a través de la evidencia o del objetivo pendiente. Si la evidencia ya no existe, se deniega. Esta rama va **antes** de la de relaciones, para no exigir una relación personal. | 020.9, 020.10 |
| `backend/app/Console/Commands/PurgeOrphanEvidenceMedia.php` + `routes/console.php` | Cada día borra (fichero y fila) los `objective_evidence` sin evidencia con más de 24 h, incluidos los que quedan huérfanos por una cascada. | 020.10 |
| `backend/app/Http/Resources/RelationshipResource.php` | Campo aditivo `features: { emotional_check_ins: bool }` para la persona que consulta, calculado con `PlanEntitlementService`. | 020.6 |
| `frontend/src/lib/schemas/company.ts` y `objective.ts`, `profile.ts`, `mentee.ts` (nuevo) | Esquemas Zod con claves `*.validation.*` (patrón SH-11). | todos |
| `frontend/src/hooks/use-company.ts` (nuevo) o el hook de empleados que ya exista | Queries y mutaciones de empleados que invalidan la lista. | 020.1–020.3 |
| `frontend/src/app/[locale]/dashboard/empresa/employees/page.tsx` + `components/company/EmployeeInviteDialog.tsx`, `EmployeeEditDialog.tsx` y `EmployeeDeactivateDialog.tsx` | Invitar, editar, activar y desactivar (con confirmación). Los estados se muestran como insignia con texto. | 020.1–020.3 |
| `frontend/src/components/mentee/EmotionalCheckInCard.tsx` + `hooks/use-emotional-check-ins.ts` | La tarjeta «¿Cómo te sientes hoy?», con escalas de radios con texto y los 5 últimos check-ins. Va en el panel del mentee, una por relación con `features.emotional_check_ins`. | 020.4–020.6 |
| `frontend/src/components/progress/ObjectiveEvidenceForm.tsx` | Fichero opcional con `FileUploadField` (se reutiliza desde `mentor-workspace`; se mueve a `components/ui` si hace falta). Primero se sube y luego se crea la evidencia. Si la subida falla, se conserva la nota. | 020.8 |
| Las vistas de evidencias (titular y mentor) | Enlace al fichero (`/api/v1/media/{id}`) con su nombre y tipo. | 020.8 |
| `frontend/src/app/[locale]/profile/page.tsx` + `components/profile/ProfileForm.tsx` y `AvatarField.tsx` | Nombre, apellidos, zona horaria (`Select` con `Intl.supportedValuesOf('timeZone')`) y foto. Al guardar, `refreshUser()` y un anuncio. | 020.11, 020.12 |
| `i18n/messages/{es,en,pt}/common.json` | Textos nuevos, con paridad. | RNF-020.2 |
| `backend/database/seeders/AccessibilityAuditSeeder.php`, `specs/019-…/fixtures.md` y `frontend/e2e/a11y/*` | Fixtures y rutas, y pruebas de axe y de foco de las pantallas nuevas. | RNF-020.3 |

## Algoritmo

```text
subir un fichero de evidencia:
  POST /objectives/{o}/media
    authorize addEvidence(o) → validar el fichero → Action: guardar en el disco privado → asset(purpose, mediaable=o)
    → 201 {id, name, mime, size}
  POST /objectives/{o}/evidences {note?, media_asset_id?}
    authorize addEvidence(o); si hay media_asset_id:
      asset.purpose == objective_evidence ∧ asset.mediaable == o ∧ asset.uploaded_by == user ∧ asset sin evidencia
      si no → 422 en media_asset_id
    transacción: crear la evidencia; asset.mediaable = evidencia
ver el fichero (GET /media/{id}):
  purpose == objective_evidence →
    objetivo := mediaable es Objective ? mediaable : mediaable?.objective
    si no hay objetivo → denegar; si no → ObjectivePolicy::view(user, objetivo)
purga diaria: los assets objective_evidence cuyo mediaable no sea una evidencia existente y con created_at < now − 24h
  → borrar el fichero y la fila
```

## Decisiones

1. **Subida en dos pasos (asset → evidencia), en lugar de un único multipart en `/evidences`.** Mantiene el contrato actual de `media_asset_id` que describe la spec y permite reutilizar `FileUploadField`. Alternativa descartada: añadir `file` a `/evidences`, que es más simple, pero cambia el contrato existente y la spec habla de `media_asset_id`. Los huérfanos que deja una subida sin evidencia los borra la purga diaria.
2. **La visibilidad se delega en `ObjectivePolicy::view` (D-3)**, en lugar de duplicar sus reglas en `MediaAssetPolicy`. Así siguen siempre la misma política. Alternativa descartada: copiar las reglas.
3. **`features.emotional_check_ins` en `RelationshipResource` (aditivo).** Cumple RF-020.6 sin llamadas que acaben en 403. Alternativa descartada: llamar a `GET …/emotional-check-ins` y ocultar la tarjeta si falla, que provoca justo esos 403.
4. **La tarjeta de check-in va en `/dashboard/personal`.** Comprobaremos en T020.5 si `menteeRelationship()` solo admite relaciones personales; si admite otras, también irá en los paneles familiar y grupal. Alternativa descartada: ponerla en todos los paneles sin comprobarlo.
5. **Zona horaria con `Intl.supportedValuesOf('timeZone')`.** El backend valida con `timezone:all`. Si el navegador no tiene la API, se usa la lista de zonas que ya use la agenda, si existe. Alternativa descartada: un campo de texto libre.
   - Comprobado en T020.4: ICU (Node 76.1, tz 2024b) devuelve 19 nombres antiguos que `timezone:all` (PHP, tz 2025.2) rechaza, entre ellos `America/Buenos_Aires`, `America/Cordoba`, `Asia/Calcutta` y `Europe/Kiev`. Elegirlos daría un 422. El helper del perfil (`lib/time-zones.ts`) los traduce a su nombre actual (`America/Argentina/Buenos_Aires`, `Asia/Kolkata`, `Europe/Kyiv`…), que `Intl.DateTimeFormat` también acepta. Alternativa descartada: cambiar el backend a `timezone:all_with_bc`, que amplía la validación de un endpoint existente fuera de esta spec.
   - Los diálogos de disponibilidad y de reprogramar (spec 016) usan `buildTimeZoneOptions` con la misma lista y tienen el mismo problema latente. No se tocan en esta spec; queda anotado.
6. **Foto subida con `POST /profile/avatar` y `_method=PUT`.** PHP no lee los ficheros de un `PUT` multipart; Laravel acepta el método falseado. La ruta y el contrato no cambian.
7. **La foto se sirve a través de Next (`/storage/avatars/*`).** El backend guarda la URL absoluta del disco público (`APP_URL/storage/avatars/…`), que en desarrollo es `http://` y `Avatar` descarta por no ser `https`. `Avatar` convierte las URLs con ruta `/storage/avatars/` en relativas y `next.config.ts` las reenvía a nginx, igual que `/api`. Las fotos de OAuth (`https`) no cambian. Alternativa descartada: devolver una ruta relativa desde el backend, que cambia el contrato de `avatar`.

## Estrategia de tests

- **Backend (Pest):**
  - subida: titular, mentor asignado, miembro del colectivo y soporte suben; un tercero recibe 403; tipo no admitido o más de 25 MB → 422;
  - evidencia con `media_asset_id` ajeno, de otro objetivo o ya usado → 422;
  - `GET /media/{id}`: lo ven las 4 figuras y un tercero recibe 403; si la evidencia se ha borrado en cascada, 403;
  - purga: borra los huérfanos con más de 24 h y respeta los recientes y los enlazados;
  - `features.emotional_check_ins` es `true` o `false` según el plan.
- **Frontend (Vitest):** esquemas (claves i18n y casos límite), helpers puros (formato de las escalas y de las zonas horarias) y hooks con `QueryClient`, siguiendo el patrón de `use-progress-history.test.ts`.
- **Suite de la 019:** las rutas nuevas en `routes.ts` y pruebas de foco para cada flujo: invitar (error de email duplicado y éxito), el diálogo de desactivar, enviar un check-in, una evidencia con fichero y guardar el perfil. Se sigue la regla de que las mutaciones no rompan el estado que dan por hecho otras pruebas.

## Riesgos

- `MediaAssetPolicy` es sensible: un error podría exponer ficheros. Los tests de autorización van primero y en rojo.
- Mover `FileUploadField` fuera de `mentor-workspace` puede tocar a sus consumidores. Se hace solo si es necesario, y con los tests del mentor en verde.
