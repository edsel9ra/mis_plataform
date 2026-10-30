# Tareas — Spec 009: Calendario y consulta

**Estado:** aprobado; T009-01 completada; quedan T009-02–04 pendientes.  
**Spec:** `specs/009-agenda-calendar/spec.md` · **Plan:** `plan.md`.

- [x] **T009-01. Extender GET /sessions con rangos y autorización contractual.** RF-009.1, RF-009.4–009.5; CA hija RF-009.1/4/5; CA padre CA-4.1/4.6/4.7, CA-10.1/10.5/10.7.
  - Hecho cuando: consulta por rango filtra en servidor participante/responsable/soporte explícito, no revela sesiones ajenas y prueba que sigue usando fuente MIS única.
  - Resultado: `GET /sessions` acepta `from`/`to` ISO con offset, rango semiabierto y `include_collectives`; autorización soporta participantes/responsables y lectura de soporte con permiso explícito. La consulta sigue usando `sessions` MIS, preserva la lista legacy sin rango y el resource oculta el Meet cancelado. Pest focalizado: 6 tests/27 assertions; suite backend: 477 tests/2131 assertions.
- [ ] **T009-02. Implementar rangos calendario puros y hooks de lectura.** RF-009.1–009.2; CA hija RF-009.1/2; CA padre CA-3.2/3.5, CA-4.1/4.3.
  - Hecho cuando: día/semana/mes se derivan con `now` y timezone explícitos; semana fija límites lunes 00:00→lunes siguiente 00:00 en la zona del espectador, iguales en es/en/pt; convierte ambos límites a instantes absolutos considerando DST (sin sumar 7×24 h); hooks/query keys solicitan esos rangos y tests verifican semanas normales y cambios DST hacia delante/atrás (RF-009.2, CA-009.2.1, RF-003.1/CA-3.5).
- [ ] **T009-03. Integrar calendario, navegación y detalle de sesión.** RF-009.1–009.2; CA hija RF-009.1/2; CA padre CA-4.1–4.3.
  - Hecho cuando: UI muestra hora/zona, duración, participantes, estado textual y link/pending, y permite periodo día/semana/mes y abrir detalle autorizado.
- [ ] **T009-04. Añadir estados vacío/error y validar rendimiento de calendario.** RF-009.3, RNF-009.2; CA hija RF-009.3; CA padre CA-4.4/4.5 y RNF-12.
  - Hecho cuando: vacío ofrece CTA, error permite retry, feature tests cubren colectivos/soporte y benchmark representativo acredita p95 ≤2s sin N+1.
