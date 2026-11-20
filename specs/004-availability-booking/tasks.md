# Tareas — Spec 004: Disponibilidad y reserva

**Estado:** completado (todas las tareas marcadas; cabecera puesta al día el 2026-11-20)  
**Spec:** `specs/004-availability-booking/spec.md` · **Plan:** `plan.md`.

- [x] **T004-01. Crear modelos y migraciones aditivas de disponibilidad.** RF-004.4; CA hija RF-004.4; CA padre CA-2.1/2.2.
  - Hecho cuando: ventanas/excepciones tienen constraints e índices previstos, el motivo queda privado y migración/modelos/factories pasan sus pruebas.
- [x] **T004-02. Implementar reglas puras de solape y duplicidad.** RF-004.2–004.3; CA hija RF-004.2/3; CA padre CA-1.2–1.4/1.6.
  - Hecho cuando: tests cubren intervalos [inicio, fin), estado excluido y normalización de título, todos con entradas explícitas sin reloj global.
- [x] **T004-03. Implementar reglas de disponibilidad y huecos libres.** RF-004.5–004.6; CA hija RF-004.5/6; CA padre CA-1.5, CA-2.3–2.7.
  - Hecho cuando: tests cubren sesión entera en ventana, sin ventanas con bloqueo vigente, precedencia block/open y slots sin causa de excepción, usando `now`.
- [x] **T004-04. Refactorizar creación a reserva transaccional compartida.** RF-004.1–004.3, RF-004.7; CA hija RF-004.1–3/7; CA padre CA-1.1–1.4/1.6, CA-13.1/13.5.
  - Hecho cuando: Action revalida relación, tipo, duración, cuota, participantes y conflictos bajo locks, conserva autor y momento; duplicado devuelve la sesión existente y una colisión colectiva rechaza todo el hueco.
- [x] **T004-05. Exponer CRUD propio de ventanas y excepciones.** RF-004.4; CA hija RF-004.4; CA padre CA-2.1/2.2, CA-10.4.
  - Hecho cuando: Form Requests, Resource, policy 003 y rutas permiten CRUD del mentor dueño y rechazan terceros; motivos no se filtran.
- [x] **T004-06. Exponer consulta de slots privados en la zona solicitante.** RF-004.6; CA hija RF-004.6; CA padre CA-2.6/2.7.
  - Hecho cuando: endpoint devuelve slots convertidos sin tipo/motivo de excepción y prueba feature verifica aislamiento del mentor dueño.
- [x] **T004-07. Probar reserva, autorización e idempotencia bajo concurrencia.** RF-004.1–004.3, RF-004.7; CA hija RF-004.1–3/7; CA padre CA-1.1–1.6, CA-11.3/11.4, CA-13.1/13.5.
  - Hecho cuando: Pest cubre mentee/mentor autorizados, duplicado, conflicto entre relaciones con participante común, cuota y conflicto colectivo indivisible.
