# Plan — Spec 003: Contrato de acceso y zona horaria de agenda

**Estado:** propuesta para aprobación (sin implementación)  
**Spec:** `spec.md`; alcance aprobado como hija de Spec 002 v0.4.0.  
**Base de diseño:** `specs/002-mentorship-scheduling/plan.md` v1.2.0, subordinada a la spec normativa.  
**Dependencias:** Spec 002 y definición de responsable de `specs/001-progress-tracking-dashboard/spec.md` §3.1 D10. Es prerrequisito contractual de 004–010.

## Alcance y trazabilidad

Esta entrega establece semántica temporal y acceso común. No crea sesiones ni determina reglas de reserva, estados, asistentes, notificaciones o calendario.

| Requisito hija | Cobertura padre exacta | Decisión de diseño heredada |
|---|---|---|
| RF-003.1 | RF-3, CA-3.1–3.7; casos 25–27, 40 | Instante almacenado sin reinterpretación; zona IANA de sesión; heredadas usan la zona sistema, conforme a DT-3/DT-17 de plan 002. |
| RF-003.2–003.8 | RF-10, CA-10.1–10.7; casos 31, 37, 40 | Reutilizar `SessionPolicy`, `userCanAccessSource()`/`userCanManageSource()`; 404 recurso no visible, 403 operación no permitida para recurso visible; permisos de soporte explícitos conforme a §9.8 del plan 002. |
| RNF-003.1–003.3 | RNF-1, RNF-2, RNF-9 y RNF-5 | Autorización server-side, mínima exposición y auditoría de operaciones pertinentes. |

## Archivos y responsabilidades previstos

- `backend/database/migrations/*add_timezone_to_sessions*`: añadir zona de sesión no nula con default sistema, sin borrar datos ni alterar instantes existentes (RF-003.1).
- `backend/app/Models/Session.php`, `backend/app/Enums/SessionStatus.php`: casts/campo de zona y compatibilidad con estados acordados; no implementar aquí transiciones (RF-003.1, contratos consumidores).
- `backend/config/scheduling.php` y `backend/config/app.php`: centralizar zona predeterminada IANA usando la convención de configuración ya adoptada (RF-003.1).
- `backend/app/Policies/SessionPolicy.php` y autorización reutilizable de relaciones/colectivos; `backend/database/seeders/RolePermissionSeeder.php`: permisos explícitos de soporte, sin inferirlos solo del rol (RF-003.2–003.8).
- `backend/app/Http/Requests/Session/*`, `backend/app/Http/Resources/SessionResource.php`, `backend/app/Http/Controllers/Api/SessionController.php`, `backend/routes/api.php`: validar/exponer zona y aplicar policy; mutaciones futuras quedan delegadas a las Actions de las hijas correspondientes (RF-003.1–003.8).
- `backend/app/Services/Scheduling/Rules/TimezoneRules.php` y pruebas `backend/tests/Unit/Scheduling/TimezoneRulesTest.php`; `backend/tests/Feature/Scheduling/SessionAuthorizationTest.php` y `SessionTimezoneTest.php` (RF-003.1–003.8).
- `frontend/src/lib/scheduling.ts` (solo formateo compartido si la superficie consumidora lo requiere) y pruebas Vitest; la agenda completa corresponde a 009/010 (RF-003.1).

## Funciones puras e interfaces

Sin reloj global ni IO en reglas. Las operaciones temporales reciben el instante evaluado explícitamente (`now`); la conversión de un instante es independiente del reloj.

- `TimezoneRules::resolveProfileTimezone(?string $profileTimezone, string $systemTimezone): string`: devuelve zona IANA válida o default sistema; la validación rechaza identificadores inválidos (CA-3.3/3.6).
- `TimezoneRules::present(Instant $instant, string $sessionTimezone, string $viewerTimezone, Instant $now): PresentedTime`: devuelve el mismo instante absoluto en la zona espectador y si debe mostrarse la etiqueta de zona de sesión; `now` solo es parámetro de contexto, no cambia el instante (CA-3.2/3.4/3.5).
- `TimezoneRules::isExplicitZoneChange(SessionSnapshot $before, SessionInput $after, Actor $actor, Instant $now): AccessDecision`: el cambio se permite solo dentro de una mutación autorizada registrada; la decisión de permiso delega en el mismo contrato de acceso (CA-3.7, RF-003.4/3.6).
- Contrato de policy: consulta `view`; crear/gestionar se autoriza contra relación, responsable o permisos de soporte explícitos. Recurso ajeno se oculta como 404 y la operación denegada sobre recurso visible responde 403 (CA-10.1–10.7).
- API mantiene campos de sesión compatibles y serializa `timezone` junto al instante; cualquier endpoint futuro conserva `auth:sanctum`, middleware activo, Form Request y Resource (P17, CA-10.5).

## Algoritmo

```text
resolveTimezone(profile, system):
  if profile es zona IANA válida: return profile
  return system  # system validada en configuración

present(instant, sessionZone, viewerZone, now):
  validar las tres zonas IANA; no modificar instant
  rendered = convertir instant a viewerZone
  showSessionZone = sessionZone != viewerZone
  return {instant, rendered, sessionZone, viewerZone, showSessionZone}

authorize(session, actor, operation):
  visible = actor participa OR userCanManageSource(actor, session.source)
            OR permiso de soporte que habilita visibilidad de esa operación
  if not visible: return NOT_FOUND
  if operation no está concedida por relación/responsabilidad/permiso: return FORBIDDEN
  return ALLOWED
```

La zona se cambia únicamente junto con el cambio de agenda que registra valor previo/nuevo; el Resource no decide autorización. Las acciones de 004–008 deberán llamar el contrato común y nunca duplicar la regla.

## Decisiones y alternativas descartadas

1. **Una zona IANA persistida por sesión y conversiones de presentación:** conserva el instante a través de horario de verano; se descarta guardar hora local sin zona, que puede ser ambigua.
2. **Extender policy/autorización de relaciones existente:** evita reglas de propiedad paralelas; se descarta crear un sistema de roles nuevo o conceder acceso a admins por rol implícito.
3. **Zona heredada con default de sistema:** permite leer registros antiguos sin reinterpretarlos; se descarta dejar `timezone` nullable con fallbacks dispersos. La zona definitiva es la descrita en plan 002, no una decisión nueva de producto.

## Estrategia de tests y criterios de cierre

- Unit Pest de resolución/fallback, preservación del instante, zonas distintas, cruce de medianoche y cambios DST con `now` explícito (RF-003.1; CA-3.2–3.5).
- Feature Pest de crear/leer/cambiar zona: default creador, zona seleccionada, cambio autorizado con historial previo/nuevo y rechazo de mutación no autorizada (CA-3.1/3.6/3.7; RF-003.3/3.4/3.6).
- Matriz de acceso feature: participante, responsable correcto, miembro no responsable, soporte con/sin permiso, actor visible sin permiso de operación, actor ajeno con ID manipulado; comprobar 404/403 y ausencia de datos filtrados (RF-003.2–3.8; CA-10.1–10.7).
- Asegurar que 004–010 consumen el contrato mediante contrato compartido; no probar aquí sus reglas de negocio.

## Dependencias contractuales

- **De 002/001:** las reglas de zona y responsable son normativas; no reabre D1/D2/D9.
- **Entrega a 004–010:** sesión expone `timezone` inequívoca; conversión nunca mueve el instante; helpers de policy distinguen recurso invisible de operación denegada y la disponibilidad es editable solo por su mentor. Cada consumidora añade pruebas de autorización propias para sus endpoints.
- **Entrega a 007/008/009:** la zona previa/nueva debe quedar accesible al historial, al evento Meet y a la presentación; esos efectos pertenecen a sus respectivas hijas.
