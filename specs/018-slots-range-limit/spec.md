# Spec 018 — Límite del rango de consulta de huecos libres

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada. Endurece RF-004.6 (hija 004) sin cambiar qué huecos se calculan.
**Origen:** riesgo anotado en `specs/017-booking-availability-ui/plan.md` («Riesgos»): la consulta de huecos libres de un mentor no limita el rango de fechas.

## Contexto y objetivo

La consulta de huecos libres genera candidatos cada 30 minutos entre las fechas pedidas. Sin límite, una sola petición con un rango de años obliga al servidor a evaluar cientos de miles de candidatos, lo que permite degradar el servicio con peticiones legítimamente autenticadas. La interfaz de reserva (spec 017) solo pide semanas. El objetivo es acotar el rango que acepta el servidor, sin afectar a la reserva actual.

## Usuarios

Personas que programan sesiones (consumidoras de la consulta de huecos) y operación del servicio.

## Historias de usuario

- HU-1. Como operación del servicio, quiero que una consulta de huecos no pueda pedir un rango arbitrariamente grande, para que nadie degrade el servicio con una sola petición.
- HU-2. Como persona que reserva, quiero que la consulta por semanas siga funcionando igual.

## Requisitos funcionales

- **RF-018.1 (RF-004.6):** SI la consulta de huecos libres pide un rango cuya duración (de `from` a `to`, como instantes reales) supera 31 días, ENTONCES EL SISTEMA DEBE rechazarla como error de validación en el campo `to`, con un mensaje traducido en es/en/pt que indique el máximo, y no DEBE calcular huecos.
- **RF-018.2 (RF-004.6):** MIENTRAS el rango sea de 31 días o menos, EL SISTEMA DEBE devolver exactamente los mismos huecos que antes de este cambio.
- **RF-018.3:** El máximo DEBE ser configurable en el servidor, con 31 días por defecto.

## Requisitos no funcionales

- RNF-018.1: el rechazo se decide antes de cualquier cálculo de huecos o consulta de ocupación.
- RNF-018.2: la interfaz de reserva de la 017 (consultas de una semana y búsqueda de la primera semana con huecos) no cambia de comportamiento.

## Casos límite

- Rango de exactamente 31 días: se acepta.
- Rango de 31 días y 1 minuto: se rechaza.
- Rango que cruza un cambio de horario: se mide en instantes reales, no en horas de pared.
- `from` y `to` sin desfase horario: se interpretan, como ya ocurre, en la zona del perfil de quien consulta antes de medir.
- `to` anterior o igual a `from`: sigue rechazándose como ahora.

## Fuera de alcance

- Limitación de frecuencia (rate limiting) por usuario.
- Cambios en el cálculo de huecos, en la reserva o en la interfaz.

## Criterios de finalización

- Los rangos de más de 31 días se rechazan con 422 en `to` y mensaje traducido; los de 31 días o menos devuelven lo mismo que antes; la reserva de la 017 sigue pasando sus tests.

## Dudas abiertas

- Ninguna. El máximo de 31 días es una propuesta: cubre un mes completo con margen y la 017 solo pide 7 días.
