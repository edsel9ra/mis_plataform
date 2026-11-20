# Tareas — Spec 008: Recordatorios y Meet

**Estado:** completado (todas las tareas marcadas; cabecera puesta al día el 2026-11-20)  
**Spec:** `specs/008-session-reminders-meet/spec.md` (RF-008.2/caso 43, cambio propuesto en padre) · **Plan:** `plan.md`.

- [x] **T008-01. Añadir estado de entrega y probar claves idempotentes.** RF-008.1–008.2; CA hija RF-008.1/2; CA padre CA-5.1–5.3/5.6.
  - Hecho cuando: migración/config y reglas puras diferencian pending/sent/failed, horizonte y fecha vigente; tests fijan `now` y prueban reintento fallido.
- [x] **T008-02. Adaptar comando de recordatorios a targets de 005.** RF-008.1; CA hija RF-008.1; CA padre CA-5.1/5.5, RF-13 caso 41.
  - Hecho cuando: recipients al instante de envío excluyen baja/caso 41 y responsable no miembro, y mensaje expresa link o pendiente en fecha vigente.
- [x] **T008-03. Consumir eventos de 007 para cambio e inmediato.** RF-008.2–008.3; CA hija RF-008.2/3; CA padre CA-5.2–5.8, CA-6.5/7.1.
  - Hecho cuando: reprogramación/cancelación generan aviso único por evento con ID lógico estable en el evento y todos los intentos; <24h genera inmediato y recordatorios antiguos dejan de entregarse; la cobertura backend confirma el mismo `notification_id` en publicaciones/reintentos. El consumidor/upsert visible se asigna a 010 y no se considera satisfecho por esta tarea.
- [x] **T008-04. Automatizar creación/backfill Meet para sesiones futuras.** RF-008.4–008.5; CA hija RF-008.4/5; CA padre CA-9.1–9.3.
  - Hecho cuando: command schedule fijo procesa solo scheduled futuras, reintenta fallo sin rollback y retorna evento existente si ya hay link.
- [x] **T008-05. Actualizar/eliminar Meet respetando zona y cancelación.** RF-008.5–008.7; CA hija RF-008.5–7; CA padre CA-6.4, CA-7.4, CA-9.4–9.7.
  - Hecho cuando: service actualiza con zona explícita, nunca añade invitados, y al cancelar elimina el evento externo best-effort sin limpiar el `meet_link` persistido; API/resources lo ocultan mientras `status=canceled`; éxito deja cero eventos activos y fallo queda registrado sin reactivar ni exponer el link (RF-008.6–.7, caso 42).
- [x] **T008-06. Cubrir fallos externos, backfill y privacidad con tests.** RF-008.1–008.7; CA hija RF-008.1–7; CA padre CA-5.2/5.6/5.7, CA-7.4, CA-9.1/9.2/9.4/9.5, RNF-10.
  - Hecho cuando: fakes HTTP/broadcast prueban reintentos, cancelación con éxito y fallo Google, conservación del valor histórico y ocultación API en ambos resultados, ausencia de `meet_link` en evento/log, backfill y registros de fallo seguros. Para el caso 43, los tests backend simulan aceptación antes de persistir `sent` y redelivery; prueban ID igual, success confirmado y retryability de ambigüedad, sin afirmar upsert visible. La publicación de transporte puede repetirse; no se exige exactly-once. La casilla histórica no declara aceptada la deduplicación visible; mantener cobertura del caso 42/CA-7.4/CA-9.5.

- [x] **T008-07. Cerrar aceptación integrada de RF-008.2 tras el consumidor de 010.** RF-008.2; RF-010.4; CA hija RF-008.2/43 y RF-010.4.
  - Hecho cuando: después de implementar T010-07, la prueba integrada del evento privado existente `SessionReminder` confirma que dos entregas con el mismo `notification_id` dejan un único aviso visible actualizado y dos IDs distintos producen avisos distintos; payload/idioma y accesibilidad se mantienen; no se incorpora canal/API/bandeja persistente. Esta tarea solo valida/cierra la aceptación de 008 y no cambia el orden de implementación 009→010.
  - Resultado: aceptación superada con la prueba integrada de T010-07 contra el stack real (broadcasts por tinker procesados por el queue worker; evidencia `/tmp/audit/a11y/reminders-e2e.log` + `reminders-axe.json` + capturas 320/1280): mismo `notification_id` → un único aviso actualizado ("A"→"B") con re-anuncio; id distinto → aviso separado; payload ya localizado por el servidor se renderiza tal cual; axe 0 violaciones; sin canal/API/bandeja nuevos.

## Puerta de aceptación y dependencia de validación

- T008-01–06 permanecen marcadas como trabajo completado; T008-07 está pendiente y es la puerta de aceptación visible. Su precondición es T010-07 de `specs/010-agenda-accessible-interface/tasks.md`; hasta entonces RF-008.2 **no se acepta plenamente**.
- Esta validación posterior no impide completar 009 ni iniciar 010 conforme a la secuencia aprobada; no crea un ciclo de implementación.
