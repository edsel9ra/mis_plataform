# Spec 010 — Interfaz accesible e internacionalizada de agenda

Estado: implementada

**Spec padre:** `specs/002-mentorship-scheduling/spec.md` v0.4.2, aprobada.
**Dependencias:** hijas 003–009; integra sus contratos sin redefinir reglas de negocio ni autorización. La hija 008 es propietaria de identificadores, persistencia de entrega, estado del proveedor y política de reintentos.
**Alcance:** idioma, accesibilidad y adaptación responsive de las superficies de agenda descritas por las hijas 003–009; incluye la presentación de los avisos recibidos por el evento privado existente `SessionReminder` y su actualización visible por `notification_id` estable.
**Mapa de trazabilidad padre/hija → RF hija:** RF-5/CA-5.2 → RF-010.4 (solo presentación visible del `SessionReminder`); RF-12 → RF-010.1–RF-010.3; RF-008.2/caso 43 → RF-010.4 (upsert visible, no entrega/reintento).

## Contexto y objetivo

Las funciones de agenda solo son utilizables si sus calendarios, formularios, acciones, estados y errores se presentan en el idioma activo y son accesibles en distintos dispositivos. Esta spec define el estándar de experiencia para las superficies entregadas por las hijas funcionales.

## Usuarios

Todos los usuarios de agenda, incluidas personas que usan teclado o tecnologías de asistencia y usuarios en móvil/escritorio.

## Historias de usuario

- HU-1. Como usuario, quiero usar agenda y formularios en mi idioma y con teclado para no depender del ratón ni del español.
- HU-2. Como usuario, quiero mensajes de error claros y anunciables para corregir mis datos.

## Definiciones

- **Idioma activo:** uno de es/en/pt seleccionado en la aplicación.
- **Operación de agenda:** programar, reprogramar, cancelar, registrar asistencia o navegar el calendario, de acuerdo con las hijas correspondientes.

## Requisitos funcionales

- **RF-010.1 (RF-12 padre; CA-12.1/12.2):** EL SISTEMA DEBE presentar en es/en/pt los textos, estados, motivos de rechazo y notificaciones generadas por la agenda, incluidas etiquetas de asistencia y «no completada». SI falta una traducción, DEBE recurrir a español sin mostrar claves crudas.
- **RF-010.2 (RF-12 padre; CA-12.3):** EL SISTEMA DEBE permitir programar, reprogramar, cancelar, marcar asistencia y navegar el calendario solo con teclado, con foco visible y objetivos de puntero de al menos 24 × 24 píxeles CSS; se admiten únicamente las excepciones de tamaño/espaciado previstas por WCAG 2.2, criterio 2.5.8.
- **RF-010.3 (RF-12 padre; CA-12.4):** SI hay error de validación, ENTONCES EL SISTEMA DEBE comunicarlo junto al campo afectado y en una región que tecnologías de asistencia puedan anunciar.
- **RF-010.4 (RF-5/CA-5.2 padre; RF-008.2/caso 43):** CUANDO el consumidor de agenda recibe repetidamente el evento privado existente `SessionReminder` con el mismo `notification_id`, EL SISTEMA DEBE actualizar o reemplazar el único aviso visible asociado a ese identificador, no añadir otro aviso visible; CUANDO recibe avisos con `notification_id` distintos, EL SISTEMA DEBE presentarlos como avisos distintos. La presentación DEBE respetar el payload y el contrato de contenido/idioma vigentes del evento, y cumplir los requisitos de idioma y accesibilidad de RF-010.1 y RNF-010.1–010.2, sin redefinir la semántica de entrega de 008. Este requisito cubre solo la presentación y el upsert visibles: 008 conserva la responsabilidad de persistencia, confirmación del proveedor y reintentos.

## Requisitos no funcionales

- RNF-010.1 (RNF-6/7/8 padre; P16): las superficies cumplen WCAG 2.2 nivel AA en los criterios aplicables, incluidos contraste de texto 1.4.3, reflow 1.4.10 y tamaño de objetivo 2.5.8; calendario y formularios son operables y comprensibles a anchos de ventana de 320 píxeles CSS (móvil) y 1280 píxeles CSS (escritorio). Los estados se comunican además de por color y los formularios informan carga, éxito y error según corresponda.
- RNF-010.2 (P19 padre): la verificación incluye navegación completa por teclado y foco visible (2.1.1/2.4.7), mensajes de estado y errores anunciables (4.1.3/3.3.1), textos traducidos, contraste, tamaño de objetivos y pruebas de reflow a 320 píxeles CSS y presentación a 1280 píxeles CSS. El calendario puede utilizar las excepciones de contenido bidimensional expresamente previstas por WCAG 2.2, sin eximir al resto de la interfaz de reflow.
- RNF-010.3 (RNF-1 padre): la visibilidad de acciones no sustituye autorización del servidor fijada por 003.

## Casos límite

- Falta una clave en cualquiera de es/en/pt: se muestra el fallback en español, no la clave.
- Error de una acción o de carga: se comunica sin bloquear la posibilidad de corrección/reintento definida por la hija funcional.
- Al recibir dos veces el evento privado `SessionReminder` con el mismo `notification_id`, queda un único aviso visible y la segunda recepción lo actualiza/reemplaza; dos eventos con `notification_id` distintos quedan como dos avisos visibles distintos.
- La presentación actualizada respeta el payload y el contrato de contenido/idioma del evento conforme a 008, y sigue siendo traducida, accesible y anunciable; la repetición de transporte no implica garantía de entrega exactamente una vez.
- Las señales visuales de estado no dependen únicamente del color.
- A 320 píxeles CSS se verifica reflow conforme a WCAG 2.2 AA, criterio 1.4.10; a 1280 píxeles CSS se verifica la presentación de escritorio. Los controles cumplen 24 × 24 píxeles CSS o una excepción de 2.5.8; se prueban contraste y teclado conforme a 1.4.3, 2.1.1 y 2.4.7.
- La funcionalidad sigue respetando permisos aunque la interfaz muestre u oculte acciones.

## Fuera de alcance

No redefine calendario, reglas de reserva, zonas, estados, permisos, confirmaciones, despacho/entrega de notificaciones, cambios de backend, persistencia, confirmación del proveedor, política de reintentos o proveedor Meet. RF-010.4 sí asigna a esta spec la presentación y el upsert visible de avisos recibidos por el evento privado existente `SessionReminder`, usando `notification_id`. No incorpora ni requiere un canal de aviso, API o bandeja persistente nuevos, ni prescribe un componente de interfaz; la presentación visible sí forma parte del alcance de 010.

## Criterios de finalización

- RF-010.1–RF-010.3 cubren CA-12.1–12.4 en las superficies aprobadas de 003–009; RF-010.4 cubre únicamente la presentación/upsert visible de `SessionReminder` por `notification_id`, en relación con RF-5/CA-5.2 y RF-008.2/caso 43.
- Las pruebas de T010 demuestran que una recepción repetida con el mismo `notification_id` deja un único aviso visible actualizado, que IDs distintos producen avisos distintos y que la presentación respeta el contrato vigente del evento, el idioma activo y los requisitos de accesibilidad.
- Los flujos cubiertos son operables con teclado, tienen errores anunciables y textos es/en/pt con fallback; los objetivos cumplen WCAG 2.2 AA 2.5.8 y el contraste cumple 1.4.3.
- Calendario y formularios se verifican a 320 y 1280 píxeles CSS, con reflow 1.4.10 aplicable; la autorización de API y recursos permanece en servidor y la presentación del cliente no la sustituye.

## Dudas abiertas

- La aprobación de este cambio confirma que T010 verifica la presentación y el upsert visible del evento privado existente `SessionReminder` por `notification_id`; no se añade un canal, API o bandeja persistente, ni se trasladan a 010 las responsabilidades de persistencia, envío, confirmación del proveedor o reintento de 008.
