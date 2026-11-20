# Spec 020 — Funciones de interfaz pendientes

Estado: implementada

**Origen:** la re-auditoría de la spec 019 (T019.8, `specs/011-frontend-accessibility/audit.md`) encontró cuatro funciones que la interfaz no ofrece. El usuario pidió implementarlas el 2026-11-19.
**Relación:** son funciones de producto, fuera del alcance de las specs 011 y 019 (RNF-011.3). Su accesibilidad se verifica con la suite de la 019.

## Contexto y objetivo

Para tres de las cuatro funciones el backend ya tiene endpoints, pero no hay interfaz que los use. La cuarta necesita además un endpoint nuevo:

1. **Gestión de empleados:** el `company_admin` ve la lista de empleados, pero no puede invitar a nadie. Existen `POST /companies/{id}/employees` (crea el usuario inactivo y le envía un enlace para fijar la contraseña), `PUT /employees/{id}`, `POST /employees/{id}/activate` y `DELETE /employees/{id}` (desactiva).
2. **Check-in emocional del mentee:** el mentor ve los check-ins, pero el mentee no puede registrarlos. Existen `GET` y `POST /relationships/{id}/emotional-check-ins` (ánimo de 1 a 5 obligatorio, estrés de 1 a 5 opcional, nota y fecha), con el entitlement `emotional_check_ins` del plan.
3. **Fichero en una evidencia:** `POST /objectives/{id}/evidences` acepta `media_asset_id`, pero el mentee no tiene ningún endpoint para subir ficheros. Solo existe `/mentor/media`, reservado al mentor y a relaciones personales.
4. **Datos personales del perfil:** existen `PUT /profile` (nombre, apellidos y zona horaria) y `PUT /profile/avatar` (imagen de hasta 2 MB), pero el perfil solo permite cambiar el idioma (y las skills, si es mentor).

El objetivo es que las personas usuarias puedan hacer estas acciones desde la interfaz, con las mismas reglas de permisos, privacidad y accesibilidad que el resto de la aplicación.

## Usuarios

- `company_admin`: gestiona empleados.
- Mentee con un plan que incluya check-ins emocionales: registra check-ins.
- Titular de un objetivo: adjunta ficheros a sus evidencias.
- Cualquier persona autenticada: edita sus datos personales.

## Historias de usuario

- HU-1. Como `company_admin`, quiero invitar empleados y gestionar su estado, para dar acceso a mi equipo sin pedírselo a soporte.
- HU-2. Como mentee, quiero registrar cómo me siento, para que mi mentor lo tenga en cuenta.
- HU-3. Como titular de un objetivo, quiero adjuntar un fichero como evidencia, para justificar mi avance con documentos o capturas.
- HU-4. Como persona usuaria, quiero cambiar mi nombre, mis apellidos, mi zona horaria y mi foto, para mantener mi perfil al día.

## Requisitos funcionales

### Empleados (HU-1)
- **RF-020.1:** CUANDO un `company_admin` envíe el formulario de invitación con email, nombre y, de forma opcional, apellidos, puesto y departamento, EL SISTEMA DEBE crear al empleado con `POST /companies/{id}/employees` y mostrarlo en la lista con el estado «invitado».
- **RF-020.2:** SI la invitación falla por un email duplicado o por el límite de empleados del plan, ENTONCES EL SISTEMA DEBE mostrar el error junto al campo o como aviso, en el idioma activo, sin perder los datos introducidos.
- **RF-020.3:** EL SISTEMA DEBE permitir al `company_admin` editar el puesto y el departamento de un empleado, activarlo y desactivarlo, con los endpoints existentes. La desactivación pide confirmación en un diálogo (D-1).

### Check-in emocional (HU-2)
- **RF-020.4:** MIENTRAS el plan de una relación incluya `emotional_check_ins`, EL SISTEMA DEBE ofrecer al mentee, en una tarjeta «¿Cómo te sientes hoy?» de su panel, una por cada relación que lo permita (D-2), un formulario para elegir su ánimo (1–5, obligatorio) y su estrés (1–5, opcional) y escribir una nota opcional. Cada valor de las escalas lleva texto, no solo un icono o un color.
- **RF-020.5:** CUANDO el mentee envíe el check-in, EL SISTEMA DEBE guardarlo con `POST /relationships/{id}/emotional-check-ins`, anunciar la confirmación y mostrar debajo sus 5 check-ins más recientes de esa relación.
- **RF-020.6:** SI el plan no incluye `emotional_check_ins`, ENTONCES EL SISTEMA no DEBE mostrar el formulario ni hacer llamadas que acaben en 403.

### Fichero en una evidencia (HU-3)
- **RF-020.7:** EL SISTEMA DEBE tener un endpoint para que la persona que puede añadir evidencias a un objetivo suba un fichero con el propósito `objective_evidence`. Admite los mismos tipos que los ficheros del mentor (imágenes, PDF, texto y CSV, documentos de Office, audio y vídeo), hasta 25 MB, tanto en objetivos individuales como colectivos (D-3).
- **RF-020.8:** CUANDO se adjunte un fichero en el formulario de evidencia, EL SISTEMA DEBE subirlo, enlazarlo con `media_asset_id` y mostrar la evidencia con un enlace al fichero. La nota pasa a ser opcional si hay fichero.
- **RF-020.9:** EL SISTEMA DEBE dejar ver el fichero solo a quienes pueden ver el objetivo de la evidencia según su política actual (D-3): el titular, el mentor asignado, los miembros del colectivo, si es colectivo, y soporte. Para subirlo hace falta poder añadir evidencias a ese objetivo. Las demás reciben 403 o 404 sin ninguna información del fichero.
- **RF-020.10:** CUANDO se borre una evidencia con fichero, EL SISTEMA DEBE dejar el fichero inaccesible, sin romper los eventos de borrado ni el historial de la spec 014.

### Perfil (HU-4)
- **RF-020.11:** EL SISTEMA DEBE permitir editar en `/profile` el nombre, los apellidos y la zona horaria, con `PUT /profile`, y la foto, con `PUT /profile/avatar` (imagen de hasta 2 MB). La foto se puede cambiar, pero no quitar (D-4). Debe mostrar los errores de validación junto a cada campo y una confirmación anunciada.
- **RF-020.12:** CUANDO cambien el nombre o la foto, EL SISTEMA DEBE actualizarlos en toda la interfaz (cabecera y lateral) sin necesidad de recargar.

## Requisitos no funcionales

- **RNF-020.1:** No cambian los permisos de los endpoints existentes. El único endpoint nuevo es el de RF-020.7.
- **RNF-020.2:** Todos los textos nuevos están en es/en/pt.
- **RNF-020.3:** Cada pantalla nueva cumple las correcciones de la 011: un h1 por página, foco conservado tras los envíos, errores anunciados, contraste AA y 320 px sin scroll horizontal. Además, se añade a la suite de la 019 (axe y foco).
- **RNF-020.4:** Formularios con React Hook Form y Zod, cuyos mensajes son claves i18n con `validationMessage`, y datos con TanStack Query, invalidando las queries relacionadas.

## Casos límite

- Invitar un email que ya existe → error en el campo email.
- Llegar al límite de empleados del plan → aviso con el mensaje del servidor.
- Un empleado invitado que todavía no ha fijado su contraseña → estado «invitado». Activarlo no le da acceso hasta que la fije.
- Un mentee con varias relaciones, de las que solo algunas tienen check-ins → el formulario solo aparece en las que los tienen.
- Un fichero de evidencia de un tipo no admitido o demasiado grande → error en el campo, antes de subirlo si es posible.
- Una subida que falla a mitad → no se crea la evidencia y la nota escrita se conserva.
- Un avatar que no es una imagen o supera 2 MB → error en el campo.
- Una zona horaria inválida → error en el campo. Las opciones se eligen de una lista, no se escriben a mano.

## Fuera de alcance

- Cambiar el email o la contraseña desde el perfil: no hay endpoints.
- El check-in de retos (`/challenges/{id}/check-ins`): es otro tipo de check-in, sin hallazgo.
- Notificaciones por email distintas de la que ya envía la invitación.
- Importar empleados en lote.

## Criterios de finalización

- Las cuatro funciones se pueden usar desde la interfaz con las cuentas de auditoría, y los casos límite tienen tests.
- El endpoint nuevo tiene tests de autorización (titular, mentor asignado, soporte, terceros) y de validación.
- La suite de la 019 cubre las pantallas nuevas y pasa en el CI.
- El backend y el frontend pasan sus suites completas.

## Decisiones (usuario, 2026-11-19)

- **D-1, empleados: gestión completa.** Invitar, editar el puesto y el departamento, y activar o desactivar, con confirmación al desactivar. Alternativa descartada: solo invitar.
- **D-2, check-in emocional: una tarjeta en el panel del mentee**, una por cada relación con el entitlement, con los 5 últimos check-ins. Alternativa descartada: una página propia.
- **D-3, ficheros de evidencia: la misma visibilidad que la evidencia** (titular o colectivo, mentor de la relación y soporte), con los mismos tipos y el mismo límite de 25 MB que los ficheros del mentor, también en objetivos colectivos. Alternativas descartadas: solo titular y mentor, y solo objetivos individuales.
- **D-4, avatar: solo cambiarlo**, sin endpoint para quitarlo. Alternativa descartada: permitir quitarlo.

## Dudas abiertas

- Ninguna. Queda pendiente aprobar esta spec.
