# Spec 003 — Contrato de acceso y zona horaria de agenda

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.0, aprobada; fuente normativa de producto.
**Dependencias:** Spec 002; para la definición de responsable colectivo, `specs/001-progress-tracking-dashboard/spec.md` §3.1 D10. No depende de otra hija.
**Alcance:** contrato transversal de zona horaria (RF-3) y autorización de agenda (RF-10).
**Mapa RF padre → RF hija:** RF-3 → RF-003.1; RF-10 → RF-003.2–RF-003.8.

## Contexto y objetivo

La agenda requiere una interpretación temporal inequívoca y permisos consistentes en cada operación. Este contrato transversal establece cómo se identifica y presenta la zona horaria de una sesión y quién consulta o administra sesiones, disponibilidad y agenda colectiva. Las hijas funcionales dependen de él; no crea ni redefine sus propias reglas de reserva, asistencia o notificación.

## Usuarios

- Mentor, mentee y responsable autorizado de colectivo: consultan y gestionan según su relación o responsabilidad.
- Admin de plataforma: soporte solo con permiso explícito.
- Participante/espectador: interpreta la hora de la sesión desde su propia zona.

## Historias de usuario

- HU-1. Como usuario que programa, quiero que la sesión conserve una zona horaria inequívoca para que todos interpreten la misma hora.
- HU-2. Como participante o responsable, quiero acceder solo a la agenda que me corresponde para proteger la privacidad.
- HU-3. Como admin de soporte, quiero operar solo con permisos explícitos y auditoría para ayudar sin acceso implícito.

## Definiciones

- **Responsable autorizado de colectivo:** cabeza de familia, coordinador de cohorte o admin de empresa conforme a la definición y autorización ya fijadas por Spec 001 §3.1 D10.
- **Zona horaria de sesión:** zona elegida al programar, conservada con la sesión y distinta de la zona local del espectador cuando corresponda.
- **Recurso ajeno:** sesión/colectivo que el actor no puede consultar por participación, responsabilidad autorizada o permiso de soporte.

## Requisitos funcionales

- **RF-003.1 (RF-3 padre; CA-3.1–3.7):** EL SISTEMA DEBE asociar a cada sesión una zona horaria identificable junto a su inicio; CUANDO se crea una sesión, DEBE tomar por defecto la zona del creador y permitirle elegir otra; CUANDO se presenta la sesión, DEBE convertir la hora a la zona del espectador e indicar visiblemente la zona de sesión si difiere. SI el usuario carece de zona configurada, DEBE usar la zona por defecto del sistema sin hora vacía. DEBE conservar el instante absoluto ante cambios de horario local, calcular correctamente recordatorios, ventanas y calendario cerca del cambio de día, y no cambiar la zona salvo como parte explícita y registrada de un cambio autorizado, conservando la anterior.
- **RF-003.2 (RF-10 padre; CA-10.1):** CUANDO un usuario solicita sesión/calendario, EL SISTEMA DEBE permitir la consulta solo a participante, responsable de su colectivo o admin con permiso explícito de lectura.
- **RF-003.3 (RF-10 padre; CA-10.2):** CUANDO un usuario crea una sesión, EL SISTEMA DEBE permitirlo al mentor y mentee de la relación, al responsable autorizado si la relación corresponde a su colectivo y a perfiles con permiso administrativo equivalente, validándolo en servidor.
- **RF-003.4 (RF-10 padre; CA-10.3):** CUANDO un usuario reprograma o cancela, EL SISTEMA DEBE permitirlo a ambas partes de la relación, al responsable autorizado del colectivo y a perfiles con permiso administrativo, respetando la antelación y estado que definen las RF-6/RF-7 padre; DEBE denegar en servidor al resto.
- **RF-003.5 (RF-10 padre; CA-10.4):** CUANDO se modifica disponibilidad, EL SISTEMA DEBE permitirlo únicamente al mentor dueño; los demás solo pueden consultar los huecos libres resultantes, nunca modificarla.
- **RF-003.6 (RF-10 padre; CA-10.5):** SI un usuario ajeno manipula un identificador para consultar o modificar un recurso, ENTONCES EL SISTEMA DEBE responder como si no existiera, sin revelar datos. SI conoce el recurso pero carece de permiso para la operación concreta, DEBE denegarla con 403 y motivo claro.
- **RF-003.7 (RF-10 padre; CA-10.6):** CUANDO perfiles administrativos realizan operaciones relevantes o un responsable actúa sobre participantes distintos de sí mismo, EL SISTEMA DEBE dejar registro de auditoría.
- **RF-003.8 (RF-10 padre; CA-10.7):** CUANDO el solicitante es responsable autorizado, EL SISTEMA DEBE permitirle consultar y gestionar agenda del colectivo correspondiente; la pertenencia ordinaria al colectivo no concede ese acceso.

## Requisitos no funcionales

- RNF-003.1 (RNF-1/9 padre): cada decisión de acceso se valida en servidor; solo se expone agenda a quien esté autorizado.
- RNF-003.2 (RNF-2 padre): las operaciones de soporte y cambios de zona mantienen autor, momento y valores relevantes, sin almacenar secretos.
- RNF-003.3 (P17 padre): el contrato comunica de forma distinguible recurso no visible y operación no permitida sin filtrar información.

## Casos límite

- Casos padre 25–27: zona de usuario ausente; espectador en zona distinta; cruce de medianoche/cambio horario.
- Casos padre 31 y 37: usuario sin permiso o externo que manipula el identificador recibe respuesta equivalente a inexistente.
- Caso padre 40: cambio de zona solo por persona autorizada, con zona anterior y nueva registradas.
- Un admin no obtiene acceso de agenda por el mero rol; se requiere el permiso explícito aplicable (CA-10.1, CA-10.5).

## Fuera de alcance

No se redefinen reglas de solapamiento, disponibilidad, duración, ciclo de vida, asistencia, avisos, Meet ni calendario; pertenecen a las hijas indicadas en la propuesta. No se cambia la definición de responsable de Spec 001 ni se agregan roles o permisos de producto.

## Criterios de finalización

- RF-003.1–RF-003.8 están especificados y verificados frente a sus CA padre, incluidos zona predeterminada, conversión, cambio registrado, permisos y respuestas 404/403.
- El contrato es reutilizable por las hijas 004–010 sin contradicción ni duplicación de reglas.
- Se satisfacen RNF-003.1–003.3 y los criterios P3/P4/P5/P8/P17 aplicables.

## Dudas abiertas

- No hay dudas de producto; D1 y D2/D9 permanecen cerradas por Spec 002. La revisión debe confirmar consistencia y límites del contrato compartido.
