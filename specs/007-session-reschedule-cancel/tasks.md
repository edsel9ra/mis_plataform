# Tareas — Spec 007: Reprogramación y cancelación

**Estado:** aprobada; implementación en curso.  
**Spec:** `specs/007-session-reschedule-cancel/spec.md` · **Plan:** `plan.md`.

- [x] **T007-01. Añadir reglas de elegibilidad e historial de reprogramación.** RF-007.1–007.2; CA hija RF-007.1/2; CA padre CA-6.1–6.3/6.6–6.8.
  - Hecho cuando: migración/modelo y reglas puras con `now` conservan valores anteriores/nuevos y rechazan estado/aviso no elegible en unit tests.
- [x] **T007-02. Implementar reprogramación que reutiliza validación de 004.** RF-007.1–007.2; CA hija RF-007.1/2; CA padre CA-6.1–6.8.
  - Hecho cuando: Action usa 003/004/006 dentro de transacción, conserva identidad y ante cualquier rechazo rollback deja la sesión intacta.
- [x] **T007-03. Publicar evento post-commit para avisos y Meet de 008.** RF-007.3; CA hija RF-007.3; CA padre CA-6.4/6.5, CA-5.7/9.4.
  - Hecho cuando: evento de cambio incluye ID idempotente y datos previos/nuevos requeridos, se emite solo tras commit y prueba verifica una emisión por cambio.
- [x] **T007-04. Implementar cancelación trazable y tardía.** RF-007.4–007.5; CA hija RF-007.4/5; CA padre CA-7.1–7.3/7.5/7.6.
  - Hecho cuando: Action permite scheduled/in_progress, almacena motivo/autor/momento/late/lead, conserva la sesión y deniega terminales.
- [x] **T007-05. Publicar evento de cancelación y probar independencia externa.** RF-007.4, RF-007.6; CA hija RF-007.4/6; CA padre CA-7.1/7.4, CA-5.7.
  - Hecho cuando: evento único de cancelación contiene solo referencia, autor, momento, motivo y tardanza cuando corresponda (nunca `meet_link`); el valor almacenado se conserva y toda respuesta API lo omite/devuelve nulo con `status=canceled`; la prueba del contrato verifica que un fallo del consumidor no revierte el estado ni cambia/exhibe el valor histórico. 007 no elimina el evento Google: ese efecto best-effort y su fallo se implementan en 008.
