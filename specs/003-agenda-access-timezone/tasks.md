# Tareas — Spec 003: Contrato de acceso y zona horaria

**Estado:** completado (todas las tareas marcadas; cabecera puesta al día el 2026-11-20)  
**Spec:** `specs/003-agenda-access-timezone/spec.md` · **Plan:** `plan.md`.

- [x] **T003-01. Añadir zona horaria persistida y fallback para sesiones heredadas.** RF-003.1; CA hija: RF-003.1; CA padre: CA-3.1, 3.3, 3.4.
  - Hecho cuando: migración aditiva, configuración y modelo exponen timezone no nula con default de sistema sin desplazar instantes; prueba de migración/modelo pasa.
- [x] **T003-02. Implementar y probar conversión temporal de sesión/espectador.** RF-003.1; CA hija: RF-003.1; CA padre: CA-3.2, 3.5, 3.6.
  - Hecho cuando: helper puro convierte el mismo instante entre zonas e indica si mostrar zona de sesión; tests cubren fallback, medianoche y DST con `now` explícito.
- [x] **T003-03. Aplicar autorización compartida a sesiones y disponibilidad.** RF-003.2–003.6, RF-003.8; CA hija: RF-003.2–003.6, RF-003.8; CA padre: CA-10.1–10.5, 10.7.
  - Hecho cuando: policy usa pertenencia/responsabilidad y permisos de soporte explícitos; feature tests prueban participante, responsable, miembro no responsable, admin sin permiso y distinción 404/403.
  - Nota de alcance acordada: se aplicó a los endpoints existentes de sesiones. La autorización de disponibilidad se integrará al crear su API en Spec 004; en esta tarea no existen modelo ni rutas de disponibilidad.
- [x] **T003-04. Probar el contrato de cambio de zona y auditar operaciones protegidas.** RF-003.1, RF-003.7; CA hija: RF-003.1, RF-003.7; CA padre: CA-3.7, CA-10.6.
  - Hecho cuando: una prueba contractual confirma que 007 conserva zona anterior/nueva, actor y momento; pruebas feature validan auditoría de soporte/responsable, sin implementar aquí el historial de reprogramación.
  - Resultado: contrato formal de campos para 007; auditoría de mutaciones existentes de soporte y responsable colectivo mediante activitylog, con snapshots limitados a metadatos no sensibles. No se creó historial ni acción de reprogramación.
