# Plan — Spec 010: Interfaz accesible e internacionalizada de agenda

**Estado:** aprobado; implementación no iniciada.  
**Spec:** `specs/010-agenda-accessible-interface/spec.md`; scope hijo aprobado.  
**Base:** Spec 002 v0.4.2 aprobada, plan 002 v1.2.0 aprobado y contratos 003–009.
**Dependencias:** 003–009 definen antes las superficies y conductas; 008 proporciona el evento privado existente `SessionReminder` con `notification_id`. 010 no introduce reglas de negocio, permisos, endpoints, estados ni canales de notificación nuevos; sí implementa su consumo/presentación y el upsert visible RF-010.4.

## Trazabilidad RF/CA

| RF hija | Padre/CA exactos | Cobertura prevista |
|---|---|---|
| RF-010.1 | RF-12, CA-12.1/12.2 | Texto y errores de agenda es/en/pt, etiquetas de estado/asistencia y fallback es. |
| RF-010.2 | RF-12, CA-12.3; RNF-6, RNF-7; P16 | Teclado, foco visible, operación de controles ≥24×24 CSS salvo excepción WCAG 2.2 2.5.8. |
| RF-010.3 | RF-12, CA-12.4 | Errores junto al campo y anunciados por tecnología asistiva. |
| RF-010.4 | RF-5/CA-5.2 padre; RF-008.2/caso 43 | Consumir `SessionReminder`; upsert de presentación por `notification_id`, con IDs distintos conservados como avisos distintos; cierra la condición de aceptación de RF-008.2. |
| RNF-010.1–010.3 | RNF-6/7/8, RNF-1; P16 | WCAG 2.2 AA aplicable: contraste, estados no solo color, reflow, 320/1280px y autorización server-side. |

La cobertura abarca superficies de 003–009: selector/presentación timezone, disponibilidad, reserva, detalle y asistencia, cambios y confirmación, recordatorios visibles, calendario y vacíos/errores. RF-010.4 consume exclusivamente el evento privado existente `SessionReminder` en `user.{id}` y su `notification_id`; no se añade canal, API, bandeja persistente ni componente prescrito. No se traducen textos authored por usuario ni se desplaza authz al cliente.

## Archivos y responsabilidades previstos

- `frontend/src/i18n/messages/{es,en,pt}/common.json`: namespace `scheduling`, estados (`not_completed`, asistencia), campos, errores, cambios, disponibilidad, detalle, calendario, carga/vacío/reintento; fallback español sin clave cruda (RF-010.1).
- `frontend/src/lib/scheduling.ts` y pruebas `scheduling.test.ts`: helpers puros para presentar estados y resolver traducción/fallback, sin duplicar reglas de negocio (RF-010.1).
- `frontend/src/hooks/use-session-reminders.ts`: suscripción autenticada al evento `session.reminder` en el canal privado ya existente `user.{id}`; entrega avisos al estado de presentación transitoria y desconecta al desmontar/cambiar usuario (RF-010.4). Reusa la dependencia `pusher-js` ya instalada para el protocolo compatible de Reverb; no cambia backend ni canales.
- `frontend/src/lib/session-reminders.ts` y `session-reminders.test.ts`: tipo del payload existente y función pura `upsertReminderNotice(current, incoming)`, keyed por `notification_id`; reentrega con el mismo ID reemplaza la representación visible previa, e IDs distintos continúan separados (RF-010.4).
- La representación se conecta al mecanismo transitorio de avisos de la aplicación sin guardar una bandeja/inbox ni persistir avisos; este plan no fija un componente concreto. El contenido y locale siguen el payload/contrato vigente de 008 (RF-010.1/4).
- `frontend/src/components/scheduling/*`, `frontend/src/app/[locale]/sessions/*`, `sessions/disponibilidad/*`, `sessions/[id]/*`: superficies de agenda preexistentes/construidas por 003–009 que reciben semántica accesible; modificar solo presentación/interacción (RF-010.1–3).
- Componentes reutilizados de `frontend/src/components/ui/*`: labels, mensajes de error, focus-visible, botones y objetivos táctiles, sin inventar framework de UI (RF-010.2/3).
- Vitest en lógica pura; validación de interfaz con inspección/navegación manual WCAG, viewports 320/1280 y herramientas del proyecto existentes; no se añade framework de test nuevo (RNF-010.1/2).
- API/back-end no cambia; las policies y autorización siguen en 003 y las mutaciones siguen respondiendo con errores de dominio existentes (RNF-010.3).

## Funciones puras e interfaces

- `agendaStatusPresentation(status, attendance): {labelKey, nonColorCue}`: mapea valores ya definidos a etiqueta/indicador semántico; no estima estado de sesión (RF-010.1, RNF-010.1).
- `resolveAgendaMessage(key, locale, catalogs): string`: busca locale es/en/pt y cae a es, nunca retorna clave cruda (RF-010.1; CA-12.2).
- `fieldErrorPresentation(fieldErrors, fieldId): {describedBy, liveRegion, messageKeys}`: función pura de asignación de errores a campos/región announceable; la capa visual garantiza asociación de atributos semánticos (RF-010.3).
- Estas reglas no leen reloj ni IO. Si una función de interfaz calcula fecha/rango, debe consumir la función pura de periodo de 009 con `now` explícito; 010 no crea su propia semántica horaria.
- Interfaces: controles deben mantener nombres accesibles, orden de tabulación, foco visible, targets de 24×24 CSS o excepción documentada; mensajes de error aparecen junto al input y en live region; server sigue autorizando aunque UI oculte acción (RF-010.2/3).

## Algoritmo / pseudocódigo

```text
translate(key, activeLocale, catalogs):
  si key existe en activeLocale: devolver texto
  si key existe en es: devolver texto es
  si no existe: error de test/catálogo; jamás mostrar key cruda

renderOperation(surface, contractState):
  presentar estado + texto (no solo color)
  si acción pendiente: mantener loading/feedback según contrato base
  si validation error: conectar error al campo, mostrarlo junto a él y anunciarlo
  no inferir permiso localmente; la respuesta autorizada/denegada es del servidor

receiveSessionReminder(currentNotices, event):
  validar la forma esperada del evento SessionReminder ya aprobado
  notice = presentar payload conforme a idioma activo y semántica accesible
  return upsertReminderNotice(currentNotices, notice, key=event.notification_id)
  # mismo ID reemplaza/actualiza; otro ID sigue siendo otro aviso
  # estado transitorio visible; no persistencia ni API/bandeja nueva

verify(surface, viewport):
  teclado completo + foco visible + lector/semántica de mensajes
  comprobar contraste, target size/excepción y reflow a 320 CSS px
  verificar presentación de escritorio a 1280 CSS px
```

El calendario puede usar la excepción WCAG prevista para contenido bidimensional, pero el resto debe reflowar; no se interpreta como exención global.

## Decisiones y alternativas descartadas

1. **Reusar `next-intl`/catálogos existentes con fallback español:** sigue convención del repo y Spec 002; se descarta nueva librería i18n o mostrar claves.
2. **Mejoras semánticas y visuales en superficies consumidoras:** mantiene la fuente de comportamiento en 003–009; se descarta reimplementar permisos o reglas en el cliente.
3. **Lógica visual pura testeada en Vitest y verificación de interfaz sin framework adicional:** usa stack presente; se descarta añadir Testing Library/Playwright como decisión de producto o dependencia (el criterio de verificación sigue obligatorio).
4. **Aplicar foco/teclado/targets y reflow transversalmente, respetando excepción calendario bidimensional:** evita accesibilidad parcial; se descarta tratar color como única señal o aplicar excepción de calendario a la página entera.
5. **Consumidor en 010 del evento privado existente con upsert por `notification_id`:** asigna a la capa de presentación visible la única garantía que el backend no puede probar; reusa transporte y payload de 008. Se descarta canal/API/bandeja persistente/componentes nuevos prescritos, y también asignar entrega/reintentos a 010 (siguen en 008).

## Estrategia de tests y cierre

- Vitest: traducción es/en/pt, fallback es, ausencia de claves crudas, mapeo estado y errores al identificador del campo (RF-010.1/3).
- Vitest de `upsertReminderNotice`: dos entregas con igual `notification_id` producen un único elemento visible actualizado; IDs distintos permanecen como avisos separados; el payload relevante se actualiza sin cambiar de clave (RF-010.4/caso 43).
- Prueba de integración del consumidor: recibir/reentregar el evento privado existente `SessionReminder` con el mismo `notification_id` actualiza el único aviso visible; verificar un segundo ID como distinto, contenido/idioma y accesibilidad/anuncio. Esta prueba es la puerta de aceptación final de RF-008.2; no exige exactly-once de transporte ni crea canal/API/bandeja.
- QA manual por teclado de todas las superficies listadas: sin ratón, orden de foco, foco visible, controles disponibles, modal y retorno de foco, anuncios de error/estado (RF-010.2/3; WCAG 2.1.1/2.4.7/4.1.3/3.3.1).
- Contraste texto 1.4.3, target 2.5.8, estados no solo color, pruebas de 320 y 1280 CSS px/reflow 1.4.10; únicamente el calendario puede usar excepción de contenido bidimensional (RNF-010.1/2).
- Pruebas regresión: acciones visibles/ocultas jamás evitan respuesta 403/404 del backend (RNF-010.3, CA-10.5).
- No se considera cerrado por revisión automática solamente; conservar matriz de rutas, locale, viewport, método y resultado para evaluación de aceptación.

## Dependencias contractuales

- **De 003:** strings y conversiones de zona; autorización permanece en servidor.
- **De 004/005:** estados de ventanas/slots y conjunto de participantes tienen significado aprobado; solo se presentan.
- **De 006/007:** estado/errores de lifecycle, asistencia, reprogramación y cancelación no se redefinen ni se adivinan.
- **De 008:** reminder y Meet pendiente/activo tienen texto coherente con respuesta de API, sin nuevo canal.
- **De 009:** calendario/detalle, contenido colectivo, estados vacío/error son superficies de esta capa; componente 2D usa solo excepción WCAG aplicable.
- **De 008 (RF-010.4):** evento privado `SessionReminder`, payload vigente y `notification_id` estable. 010 upserta solo la representación visible; persistencia/confirmación del proveedor/reintentos siguen en 008. La prueba T010-07 permite a 008 completar su aceptación RF-008.2; esta validación cruzada no invierte la implementación 009→010.
