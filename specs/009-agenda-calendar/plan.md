# Plan — Spec 009: Calendario y consulta de agenda

**Estado:** aprobado (sin implementación)  
**Spec:** `specs/009-agenda-calendar/spec.md`; scope hijo aprobado.  
**Base:** Spec 002 v0.4.2 y plan 002 v1.2.0.  
**Dependencias:** 003 acceso/zona, 004–005 participantes, 006 estados, 007 cambios y 008 enlace Meet. 010 aplica accesibilidad/idioma a las superficies resultantes.

## Trazabilidad

| RF hija | Padre/CA exactos | Función |
|---|---|---|
| RF-009.1 | RF-4, CA-4.1/4.6; RNF-1/9 | Lectura de fuente MIS con acceso de 003, asistentes/status/link existentes. |
| RF-009.2 | RF-4, CA-4.2/4.3; RF-003.1, CA-3.5; CA-009.2.1 | Vistas día/semana/mes; semana lunes→lunes local en zona del espectador, sin depender del locale. |
| RF-009.3 | RF-4, CA-4.4/4.5 | Vacío, CTA y recuperación de error. |
| RF-009.4 | RF-4, CA-4.7; RF-10 CA-10.1/10.7 | Agenda de colectivos solo para responsables/participantes autorizados. |
| RF-009.5 | RF-10, CA-10.1 | Soporte solo con permiso explícito de lectura y alcance autorizado. |
| RNF-009.2 | RNF-12 (≤2 s p95) | Consulta por rango, sin N+1 y benchmark representativo. |

## Archivos y responsabilidades previstos

- Backend `SessionController@index`, `SessionResource`, query/scopes de `Session`; extender `GET /sessions` con rango, no endpoint de calendario paralelo (RF-009.1/4/5).
- `SessionPolicy`/consulta de acceso común 003 y `backend/routes/api.php`; filtrar participantes, responsable autorizado y soporte conforme a permisos; 404 para IDs no visibles (RF-009.1/4/5).
- `backend/tests/Feature/Scheduling/SessionCalendarTest.php`, `SessionAuthorizationTest.php` y prueba de conteo/tiempo de query (RF-009.1/4/5, RNF-009.2).
- Frontend `frontend/src/hooks/use-sessions.ts` o hook `use-scheduling.ts` acordado en plan 002; `frontend/src/lib/query-keys.ts`; nueva/actualizada página `frontend/src/app/[locale]/sessions/page.tsx`, `sessions/[id]/page.tsx` y `components/scheduling/Calendar{Day,Week,Month}View` (RF-009.1–4).
- `frontend/src/types/scheduling.ts`, `lib/scheduling.ts` y Vitest: cálculo puro de periodos por calendario/zona (incluida semana lunes→lunes local y conversión DST) y adaptación de estado a datos de vista, no reglas de negocio (RF-009.1–4, CA-009.2.1).
- i18n y accesibilidad normativa son 010; 009 expone etiquetas/estados con claves semánticas sin definir idioma ni nivel a11y nuevo (RF-009.2/3).

## Funciones puras e interfaces

- `CalendarRange::forPeriod(period, anchor, timezone, now): {from,to}` recibe periodo, anchor opcional, zona IANA del espectador y `now` explícito para establecer el anchor por defecto al periodo actual. Para `week`, calcula el lunes local que contiene el anchor y el lunes siguiente a las 00:00 locales; convierte ambos límites a instantes absolutos usando las reglas de la zona, nunca sumando 7 × 24 horas. La regla es fija e independiente de es/en/pt (RF-009.2, CA-009.2.1, RF-003.1/CA-3.5).
- `CalendarSessionMapper::toCalendarItem(session, viewerTimezone, now): CalendarItem` proyecta título, hora, duración, asistentes autorizados, estado, marca colectivo y enlace/pendiente; no consulta ni calcula permiso (RF-009.1/2/4).
- Interface backend: `GET /sessions?from=&to=&include_collectives=` reutiliza la fuente de sesiones MIS; paginación/filtrado permanece compatible. `from`/`to` son límites validados, definidos como rango semiabierto para evitar duplicar eventos contiguos; si el comportamiento previo del parent plan difiere, se conserva el contrato ya aprobado (CA-4.6).
- Interface frontend: `useSessionsRange(from,to, includeCollectives)` con query key estructurada; navegación de periodo actualiza rango; detalle abre registro autorizado, no copia local de sesión (RF-009.1/2).
- Errores de API, vacío y reintento se expresan en estado de vista; el componente no decide autorización ni muta reglas de los RF funcionales (RF-009.3/5).

## Algoritmo

```text
calendarRequest(actor, period, anchor, now):
  range = CalendarRange.forPeriod(period, anchor, actor.timezone, now)
  // week: local Monday 00:00 through next local Monday 00:00, then convert each boundary to an instant
  validar límites y máximo razonable de consulta según contrato existente
  query única sobre sessions MIS WHERE start dentro range
  restringir por participante OR responsable autorizado OR permiso explícito de lectura
  eager-load relaciones/autorizados; mapear zona del actor/status/link
  devolver respuesta de contrato; nunca fusionar eventos externos

calendarView(queryState, items):
  loading → indicador existente
  error → mensaje + acción retry
  éxito vacío → estado vacío + programar
  éxito con datos → vista día/semana/mes, chips con status textual y acceso al detalle
```

El área colectiva se incluye solo para actor que es responsable autorizado o participante, sin resolver membresía ad hoc ni exponer colectivos ajenos. Actualizaciones de 007 invalidan/refetch las query keys existentes conforme a la política del proyecto.

## Decisiones y alternativas descartadas

1. **Extender `GET /sessions` por rango:** mantiene una sola verdad y authz; se descarta endpoint `/calendar` con consulta/filtros paralelos.
2. **Agenda propia sobre UI existente:** minimiza dependencias y usa contratos MIS; se descarta importar/sincronizar calendarios externos o añadir librería sin requisito.
3. **Rango de consulta derivado de funciones puras y `now` explícito; semana fija lunes→lunes en zona del espectador:** cubre día/semana/mes, DST y pruebas deterministas, y mantiene los mismos límites al cambiar entre es/en/pt; se descarta usar fecha de reloj global, semana dependiente del locale o sumar una duración fija de siete días.
4. **Permisos aplicados en servidor antes de serializar:** evita IDOR y soporte implícito; se descarta confiar en ocultar eventos/acciones en frontend.
5. **Componentes presentan estados pero 010 define accesibilidad/i18n transversal:** evita duplicar reglas; se descarta mover esa responsabilidad a la API o cambiar requisitos de calendario.

## Tests y cierre

- Unit Vitest de `CalendarRange::forPeriod` con `now` inyectado: límites de día/mes, semana de lunes 00:00 a lunes siguiente 00:00 en zona del espectador, mismo límite para locale es/en/pt, cruces de fin de año y transiciones DST hacia delante/atrás (incluidas semanas de 167/169 horas cuando aplique); verificar instantes absolutos y ausencia de deslizamiento del día local (RF-009.2, CA-009.2.1, RF-003.1/CA-3.5). Complementar con pruebas Pest del filtrado de rango y autorización.
- Unit Vitest de conversión a zona, estados incluido «no completada» y enlace existente/pending con `now` inyectado (RF-009.1/2; CA-3.2/3.5).
- Feature authz: participante, responsable, participante colectivo, no responsable y soporte con/sin `scheduling.view.support`; ID ajeno no revela; resultados dentro de rango y solo fuente MIS (RF-009.1/4/5).
- Frontend: día/semana/mes, detalle, vacío con programar, error con reintento, status textual, link o pending (RF-009.2/3).
- RNF-009.2: benchmark 30 cargas tras calentamiento sobre dataset representativo y endpoint con N+1 controlado; p95 ≤2s. La prueba de query count no sustituye umbral temporal.
- Contrato 008: link/status de Meet idéntico a SessionResource; no se genera Meet desde el calendario.

## Dependencias contractuales

- **De 003:** solo acceso visible, timezone y política de soporte; no reimplementar authorization.
- **De 004/005:** solo participantes existentes/registrados y no pertenencia a colectivos ajenos.
- **De 006/007:** estado persistido actualizado; calendario no infiere estado a partir de fechas ni edita sesiones.
- **De 008:** link compartido o estado pending; no consultar Google directamente.
- **A 010:** estructura de vista, estados, botones y errores como superficies destinatarias de teclado, traducción y reflow; 010 no puede ampliar reglas/API.
