# Plan de implementación — Spec 015: Historial verificable de progreso

**Estado:** propuesto para implementación · **Spec:** aprobada
**Base:** Spec 001 v0.5.1 aprobada; baseline v0.3.0 implementado.
**Dependencias:** 012 antes de esta spec para eventos de retos/vínculos/completitud; 014 antes para eventos de evaluación/competencia. Por tanto, implementar 015 al final.
**Bloqueo:** retención del evento mínimo de eliminación no se implementa hasta que exista política general de conservación/eliminación identificada y documentada.

## 1. Alcance y fuentes

La cronología es una proyección de registros verificables existentes, no una tabla de métricas ni una reconstrucción. Reutilizar `spatie/laravel-activitylog` (ya instalado y usado por objetivos) como fuente inmutable ante operaciones ordinarias para cambios; leer evidencias y marcas de fecha directamente de sus modelos fuente. No duplicar el payload sensible en una tabla nueva.

| Fuente / área | Eventos que puede acreditar | Limitación que debe conservarse | RF |
|---|---|---|---|
| `Activity`/`activity_log` de `Objective` | Creación y cambios de estado/campos que hayan sido logueados, actor, instante, diff disponible | No fabricar eventos previos ausentes ni completar snapshots/campos que falten | RF-015.1–.2, .7–.8 |
| `ObjectiveEvidence` | Registro de evidencia y `created_at`, referencia/id disponible | No copiar nota/contenido/URL si no se requiere; no inferir fecha previa a propuesta | RF-015.2, .7–.8 |
| `Challenge` y ActivityLog producido por 012 | Transiciones, inicio/finalización y vínculo reto-módulo anterior/resultante | Evento ausente no demuestra que no ocurrió; no usar `progress` estimado | RF-015.3, .8 |
| completitud individual de módulo / `UserLearningProgress` | Finalización automática persistida; `started_at`/`completed_at` disponibles como hechos fuente | No interpolar transiciones intermedias; timestamps nulos van a «fecha no disponible» | RF-015.3, .7–.8 |
| `ChallengeSubmission` y `ObjectiveEvidence` | Evidencia registrada con fecha y referencia disponible | No exponer archivos/contenido fuera del permiso existente | RF-015.2–.3 |
| `CompetencyAssessmentLink` events de 014 + `UserSkill` | Creación/cambio/retiro explícitos y cambios de nivel realmente registrados desde observer | No atribuir al resultado cambios automáticos de nivel ni reconstruir niveles históricos | RF-015.4–.6, .8 |
| `AssessmentResult`/`PersonalityAssessment` events de 014 | Cambio de estado real de fuente con vínculo activo | La fuente actual representa completitud por `completed_at`; si no hay transición registrada no se crea evento | RF-015.4–.6, .8 |
| resultado eliminado | Solo evento mínimo aprobado con actor pseudonimizado audit-only | Retención **bloqueada** hasta política documentada; no almacenar ni crear pseudónimo en esta fase | RF-015.9 |
| `HistoryService`, controller/resource, policy y rutas API | Unión autorizada/paginada y normalización de fuentes | Prohibido responder con historial por vista colectiva | RF-015.1, .7–.10 |
| `frontend/src/{app,components/progress,types,hooks,lib,i18n/messages/*}` | Vista accesible de eventos fechados y grupo separado sin fecha | No usar como serie de tendencias ni exponer identidad/payload borrado | RF-015.1–.10 |

## 2. Contrato normalizado y funciones puras

`ProgressHistoryEvent` solo en memoria/response normaliza `type`, `source_type`, `source_id` (si persiste), `occurred_at|null`, `actor` disponible, `before/after` whitelisted, referencia de evidencia disponible y campos `incomplete[]`. Cada adapter conserva origen y no simula eventos. Se excluyen respuestas, puntuaciones, texto de evidencia innecesario y propiedades no whitelisted. El payload de eliminación, cuando sea legalmente habilitable en una futura tarea, queda fuera de la vista normal y no es un evento fuente implementado aquí.

```text
normalizeSourceEvent(record, whitelist): map fields only; missing fields -> incomplete[]
classifyDate(event):
  if occurred_at is null: date_unavailable + incomplete; never assign now()
  else: dated
sortHistory(events): dated events by occurred_at DESC, stable source/type/id tie-break;
                   undated events form separate group, never interleaved
mergeHistory(sourcePages): merge sorted feeds with stable key and cursor; no absent-event synthesis
```

Funciones puras no consultan DB ni reloj global. Si alguna clasificación usa fecha actual, `now` será argumento explícito; este historial no requiere `now` para posicionar eventos. Paginación por cursor opaco y tie-break estable limita memoria/tiempo de una consulta larga; el cursor no funciona como permiso.

## 3. Flujo de lectura, autorización y presentación

1. Auth middleware; resolver titular y autorizar en servidor con `ProgressAccessService`/accesos de relación vigentes: titular, mentor autorizado, admin con permiso explícito soporte/auditoría actual. No crear permiso paralelo ni habilitar agregado de cartera.
2. Ejecutar adapters filtrados por sujeto, seleccionar columnas whitelisted, consultar lotes con índices, límite de página; excluir `collective` antes de serializar. La UI no decide acceso.
3. Adaptar cada registro acreditado a DTO, conservando faltantes; nunca crear un evento si la fuente no lo registra. `started_at`/`completed_at` permiten afirmar solo ese hecho fechado, no pasos intermedios.
4. Ordenar cronológicamente eventos fechados; agrupar registros acreditados sin fecha en apartado «fecha no disponible», marcado incompleto y sin `now()` o posición fabricada.
5. Emitir log estructurado para errores/denegaciones sin respuesta/evidencia. Invalidar caché cuando 012/014/objetivos cambien.
6. Serializer de actor: actor disponible en eventos ordinarios solo dentro del ámbito autorizado; no existe camino ordinario para revelar pseudónimo de eliminación. Si política no documentada, no implementar retención ni endpoint de resolución de pseudónimo.

API propuesta: `GET /api/v1/progress/history?cursor=&limit=` propio; equivalente con sujeto para mentor y soporte mediante rutas ya protegidas. Respuesta `{data:[...], next_cursor, undated:[...]}`; registros fechados y sin fecha separados; actor/cambios disponibles solo según fuente. No se integra en agregados de colectivo.

## 4. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| Adaptadores de fuentes existentes + `activitylog` append-only ya instalado | Tabla duplicada con copia de cada fuente y cronología materializada | Mantiene una verdad, no duplica datos ni agrega una nueva persistencia PII (P1/P4/P5). |
| Whitelist de eventos/campos y actor/fecha solo si constan | Reconstruir snapshots por estado actual o inferir desde timestamps relacionados | Cumple la prohibición de inventar y minimiza exposición (P4/P6). |
| Cursor paginado y tie-break determinista | Cargar toda la vida del usuario en una respuesta | Acota memoria/query y permite operar con historial creciente. |
| «Fecha no disponible» separado, nunca sustituida por `now` | Insertar al inicio/fin o usar fecha de creación de otro registro | No crea orden temporal falso (RF-015.7–.8). |
| Reutilizar permisos actuales, ningún endpoint colectivo | Compartir timeline a través de agregado | Historial/eventos son datos individuales protegidos (RF-015.10). |
| Retención mínima de borrado se mantiene como gate | Implementarla usando `activitylog` genérico o asumir plazo | Política general pendiente y no delegable a implementación (RF-015.9). |

## 5. Observabilidad, constraints y rendimiento

Los nuevos productores usan los mecanismos de auditoría append-oriented existentes; las operaciones normales no editan/borran actividades. `activity_log` se consulta por `log_name/event/subject_type/subject_id/causer_id/created_at` y, donde corresponda, propiedad mínima de sujeto; definir índices PostgreSQL aditivos sobre combinaciones usadas si el `EXPLAIN` lo requiere. Evitar un nuevo ledger; el contrato no serializa JSON arbitrario de `properties`. UUID/FKs de fuentes quedan intactos.

Logs `progress.history_failed`/`progress.access_denied` estructurados con excepción, endpoint/tipo y sujetos autorizados mínimos; nunca notas, respuestas, puntuaciones, email o contenido de evidencia. Medir queries y benchmark lectura de panel/mapa/historial con 50 solicitudes concurrentes; p95 de API ≤2 s según ventana del padre. Cache TTL e invalidación usan los observadores existentes y fuentes de 012/014. UI 320 px, teclado, foco, ARIA para carga/error/grupo sin fecha y estado incompleto.

## 6. Estrategia de tests (planificada; no ejecutada)

- **Unit Pest:** normalizar whitelist, ausencia de fuente vs campos faltantes, fechas, sorting estable, misma fecha, cursor y no `now()` inventado; RF-015.1, .7–.8.
- **Feature Pest/PostgreSQL:** fixtures de cada fuente existente, eventos anteriores solo donde activitylog/registro los prueba, actor/diff disponible, link 012/014, evidencia, cambio estado con relación activa, eliminación gate bloqueado; RF-015.2–.9.
- **Autorización/privacidad:** titular, mentor asignado, relación terminada/sin grant, admin sin/ con permiso explícito, auditor no autorizado, IDOR y llamadas de miembro/responsable colectivo; no IDs/datos de otro sujeto ni actor pseudónimo ordinario; RF-015.9–.10, RNF-015.1.
- **Frontend Vitest y revisión a11y:** orden y sección undated, campos incompletos sin inferir, errores/carga/empty, locales es/en/pt, navegación/ARIA/320px; RNF-015.3, .4, .6.
- **Rendimiento y regresión:** índices/explain/query count, 50 concurrentes/p95 ≤2 s. Pruebas planificadas; no se ejecutan durante la redacción.

## 7. Trazabilidad RF → partes

| Parte | RF cubiertos |
|---|---|
| Adapters/normalización de registros | RF-015.1–.8 |
| Producers y eventos de 012/014 | RF-015.3–.6, .8 |
| Historial read service, cursor y fechas faltantes | RF-015.1–.8 |
| Policies, resource y endpoint | RF-015.1, .9–.10; RNF-015.1–.2 |
| UI cronológica, accesible e i18n | RF-015.1–.8; RNF-015.3–.6 |
| Retención tras eliminación | RF-015.9 **bloqueado** hasta política documentada; no se implementa aquí |
