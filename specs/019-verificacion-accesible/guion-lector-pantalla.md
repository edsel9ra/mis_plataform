# Guion de prueba con lector de pantalla — Spec 019 (RF-019.4)

Versión 1.0 · 2026-11-19

Este guion es para la persona del equipo que hace la prueba (decisión D-3 de `spec.md`). Para cada flujo describe los pasos y lo que **debería** anunciar el lector de pantalla. El objetivo es comprobar que lo corregido en la spec 011 funciona con lectores reales. Hay que anotar **lo que se oye de verdad**, aunque parezca correcto.

## 1. Antes de empezar

### Equipos y configuración

| Combinación | Sistema | Navegador | Lector |
|---|---|---|---|
| A | Windows 10/11 | Firefox (última versión estable) | NVDA (última versión estable), voz en español |
| B | macOS (o iOS) | Safari | VoiceOver, voz en español |

- **Safari en macOS:** en *Ajustes → Avanzado*, activa «Pulsar Tab para resaltar cada ítem de una página web». Sin esa opción, Tab no recorre los enlaces.
- **NVDA:** deja la configuración por defecto. NVDA pasa solo al modo foco al entrar en un campo de formulario; si no lo hace, pulsa `NVDA+Espacio`.
- Idioma de la aplicación: **español**, que es el predeterminado.
- **No uses el ratón ni el trackpad** salvo que el guion lo diga. En iOS, usa los gestos de VoiceOver.

### Entorno y cuentas

- El entorno de auditoría se monta siguiendo el procedimiento **(b) «Montar el entorno de auditoría en otro equipo»** de `specs/019-verificacion-accesible/fixtures.md`, que usa la base de datos `mis_project_audit` y te indica la **URL** a abrir. La **contraseña de prueba** de las cuentas te la entrega el equipo técnico aparte. No uses nunca el entorno de desarrollo ni el de producción.
- Cuentas que usa este guion (todas con la misma contraseña de prueba):
  - `ana.audit@audit.mis.test`: mentee con datos de sesiones, evaluaciones, mensajes y progreso;
  - `kai.audit@audit.mis.test`: mentee sin test de personalidad.
- Si una cuenta ya no está en el estado que se describe (por ejemplo, el test de personalidad ya enviado), avisa al equipo técnico para que vuelva a sembrar la base. No improvises con otra cuenta.

### Teclas de referencia

| Acción | NVDA (A) | VoiceOver macOS (B) |
|---|---|---|
| Siguiente encabezado | `H` | `VO+Cmd+H` (`VO` = `Ctrl+Opción`) |
| Lista de encabezados y regiones | `NVDA+F7` | `VO+U` (rotor) |
| Siguiente región (landmark) | `D` | rotor → «Puntos de referencia» |
| Siguiente campo de formulario | `F` o `Tab` | `Tab` o `VO+Cmd+J` |
| Activar | `Enter` o `Espacio` | `VO+Espacio` (o `Enter` o `Espacio` con el foco en el control) |
| Moverse entre opciones de un grupo | flechas | flechas (con el foco en el grupo) |
| Leer desde aquí | `NVDA+↓` | `VO+A` |

### Cómo anotar

- Rellena la tabla del §3 por flujo y por combinación.
- Usa el **Resultado** así:
  - **Cumple:** todo se anuncia como se espera;
  - **Cumple con observaciones:** se completa, pero algo se anuncia mal o de más;
  - **No cumple:** no se puede completar o falta información esencial;
  - **No probado:** di el motivo.
- En **Problemas**, escribe lo que anunció y lo que se esperaba, con el número de paso (por ejemplo, «Paso 3.4: dijo "botón" sin nombre; se esperaba "Siguiente, botón"»).
- La **gravedad** es una de estas tres: *bloquea* (no se puede terminar), *dificulta* (se puede, pero con mucho esfuerzo) o *menor*.
- No hace falta saber el criterio WCAG; el equipo técnico lo añade al clasificar.

### Orden y acciones que solo se pueden hacer una vez

- Haz primero la combinación A completa y después la B.
- **Test de personalidad (flujo 3):** en la combinación A **no** pulses «Ver mis resultados»; envíalo solo en la combinación B. Al enviarlo, `kai` deja de poder hacer el test.
- El resto de los envíos (registro, reserva, evaluación, mensaje y objetivo) se pueden repetir. Usa los datos que indica cada flujo.

## 2. Flujos

### Flujo 1 — Inicio de sesión

Cuenta: `ana.audit`. Empieza con la sesión cerrada, en `<URL>/login`.

1. Carga la página. **Esperado:** anuncia el título de la página. La lista de encabezados tiene un solo nivel 1, «Inicia sesión en tu cuenta», y hay una región principal.
2. Pulsa `Tab` hasta el primer campo. **Esperado:** «Correo electrónico», campo de edición. Siguiente: «Contraseña», campo de contraseña.
3. Sin escribir nada, ve al botón de envío y actívalo. **Esperado:**
   - se anuncia un error en cada campo vacío;
   - el foco va al campo «Correo electrónico»;
   - al recorrer los campos, cada uno se anuncia como «no válido» o «inválido», con su error.
4. Escribe el email de `ana.audit` y una contraseña **incorrecta** (una sola vez) y envía. **Esperado:**
   - se anuncia el mensaje de error del servidor;
   - **el foco se queda en el botón de envío**, no vuelve al principio de la página.
5. Escribe la contraseña correcta y envía. **Esperado:** se abre el panel y se anuncia la página nueva. El primer `Tab` lleva a «Saltar al contenido», y al activarlo el foco va al contenido principal.

### Flujo 2 — Registro

Empieza con la sesión cerrada, en `<URL>/register`.

1. **Esperado:** h1 único y las opciones de tipo de cliente como enlaces con nombre. Elige «Personal» o la opción equivalente.
2. En `/register/personal`, ve a los planes. **Esperado:**
   - es un grupo de opciones (radio) y cada una anuncia el nombre del plan, su precio y si está seleccionada;
   - las flechas cambian el plan seleccionado.

   *Si ves la pantalla:* el plan con el foco muestra un anillo y el seleccionado, un icono de check.
3. Envía el formulario vacío. **Esperado:** errores por campo, anunciados, y el foco en el primer campo inválido.
4. Rellena el formulario con el email `ana.audit@audit.mis.test`, que ya existe, y envía. **Esperado:** se anuncia el error de email duplicado y el foco no se pierde: se queda en el botón o en el campo del error.
5. Cambia el email por `registro.<tu-inicial>.<nº>@audit.mis.test` (por ejemplo, `registro.m.1@audit.mis.test`) y envía. **Esperado:** el registro se completa y se anuncia la página de destino.
6. Comprueba que el campo de fecha de nacimiento y el de sexo tienen nombre. Opcional: comprueba que el navegador ofrece autocompletar.

### Flujo 3 — Test de personalidad

Cuenta: `kai.audit`. URL: `<URL>/onboarding/test-personalidad`.

1. **Esperado:**
   - h1 «Test de Personalidad Big Five» y una región principal;
   - se puede leer el aviso de que el test es orientativo;
   - la barra de progreso se anuncia como «Progreso del test» con su valor;
   - el texto «Pregunta 1 de 120» se puede leer.
2. Ve a las respuestas. **Esperado:** al entrar en el grupo se anuncia **el texto de la pregunta** (es el nombre del grupo) y la opción con el foco, como «botón de opción».
3. Usa las flechas para cambiar de respuesta. **Esperado:**
   - cambia la opción marcada y se anuncia;
   - **no** se pasa a la siguiente pregunta: el texto sigue siendo «Pregunta 1 de 120»;
   - se puede elegir cualquiera de las opciones, incluidas las del medio.
4. Pulsa `Tab` hasta «Siguiente». **Esperado:** antes de responder estaba desactivado («no disponible»); después de responder está disponible. Actívalo. **Esperado:** el foco vuelve a las respuestas y se anuncia la pregunta 2.
5. Ve a «Anterior» y actívalo. **Esperado:** vuelve a la pregunta 1 con el foco en la respuesta que ya habías marcado.
6. Responde todas las preguntas. Con el teclado lleva unos 15–20 minutos, y no hace falta leer cada pregunta entera. Al pulsar «Siguiente» en la pregunta 120, **esperado:** aparece «Tus Resultados» y se anuncia, porque el foco va al encabezado.
7. **Solo en la combinación B:** activa «Ver mis resultados». **Esperado:** mientras procesa, el botón se anuncia como ocupado o no disponible, sin perder el foco; al terminar, se abre el panel.

### Flujo 4 — Reserva de una sesión

Cuenta: `ana.audit`. URL: `<URL>/sessions/new`.

1. **Esperado:** h1 y la relación con `mentor.audit` ya elegida, anunciada.
2. Ve a los días. **Esperado:** cada día es un botón con la fecha y el número de huecos, y su estado de pulsado («pulsado» o «no pulsado»). Elige un día que tenga huecos.
3. Ve a los huecos. **Esperado:** cada hueco anuncia la hora de inicio y fin, el día, la fecha y la zona horaria, y su estado de pulsado. Elige uno. **Esperado:** se anuncia la selección (un mensaje de estado), sin mover el foco.
4. En el formulario, deja el título vacío y activa «Confirmar reserva». **Esperado:** se anuncia el error y el foco va al campo «Título».
5. Escribe el título «Prueba lector A» (o «B») y confirma. **Esperado:** se anuncia que la reserva se ha hecho y el foco va al mensaje de éxito.

### Flujo 5 — Responder una evaluación

Cuenta: `ana.audit`. URL: `<URL>/evaluations`.

1. **Esperado:** h1 «Evaluaciones» y cada evaluación con su título como encabezado de nivel 2. Abre una que **no** tenga resultado.
2. **Esperado:**
   - h1 con el título de la evaluación;
   - la barra de progreso se anuncia con el nombre «Pregunta 1 de N»;
   - al entrar en las opciones se anuncia la pregunta como nombre del grupo.
3. Con el foco en una opción, pulsa `Enter` o `Espacio`. **Esperado:**
   - la opción se anuncia como «pulsado» o «seleccionado»;
   - **no** se pasa a la siguiente pregunta;
   - el foco se queda en la opción.
4. Activa «Siguiente». **Esperado:** el foco va a las opciones de la pregunta nueva. Comprueba «Anterior».
5. Responde todas las preguntas y activa «Validar resultado». **Esperado:** se anuncia la página de resultado, con el h1 «Resultado validado» y la barra de puntaje con el nombre «Puntaje».
6. Si hay un enlace «Volver a evaluaciones», comprueba que se anuncia con ese nombre.

### Flujo 6 — Mensajes

Cuenta: `ana.audit`. URL: `<URL>/messages`.

1. **Esperado:** h1 y la lista de conversaciones como botones con el nombre de la otra persona. La conversación activa se anuncia como «actual».
2. Abre la conversación con `mentor.audit`. **Esperado:**
   - la zona de mensajes es una región con nombre, de tipo registro o log;
   - se pueden leer los 6 mensajes, cada uno con su autor y su hora.
3. Ve al campo de escribir. **Esperado:** se anuncia con su etiqueta. Con el campo vacío, el botón de enviar («Enviar mensaje») está desactivado.
4. Escribe «Prueba lector A» (o «B») y envíalo con `Enter`. **Esperado:**
   - el mensaje se envía;
   - el lector **anuncia el mensaje nuevo** sin mover el foco;
   - el foco sigue en el campo.
5. Opcional, si el equipo técnico puede responder desde `mentor.audit` en ese momento: **esperado**, el mensaje que llega se anuncia sin interrumpir lo que estás haciendo.
6. A pantalla estrecha (móvil o ventana pequeña), comprueba el botón «Volver a conversaciones».

### Flujo 7 — Panel de progreso e historial

Cuenta: `ana.audit`. URL: `<URL>/dashboard/progreso`.

1. **Esperado:** h1 «Progreso». Las tarjetas tienen encabezados de nivel 2 y sus apartados, de nivel 3, sin saltos de nivel. Cada indicador y cada estado se anuncia con texto: no hay información que solo se vea por el color.
2. Abre «Proponer objetivo» y envía el formulario vacío. **Esperado:** errores **en español**, por ejemplo «El título debe tener al menos 3 caracteres.», «Este campo es obligatorio.» e «Indica la fecha límite.», y el foco en el primer campo inválido.
3. Rellénalo («Objetivo de prueba A» o «B», unos criterios y una fecha futura) y envía. **Esperado:** se anuncia la confirmación sin perder el foco.
4. Activa «Ver historial». **Esperado:**
   - h1 «Historial de progreso»;
   - los eventos agrupados por días, cada día con su encabezado;
   - quién hizo cada acción («Sistema», una persona o «Actor oculto»), anunciado como texto.
5. Si aparece «Cargar más», actívalo. **Esperado:** el foco se queda en el botón mientras carga y los eventos nuevos se pueden leer a continuación.
6. Vuelve al panel y activa «Ver mapa». **Esperado:** los estados de los módulos («Sin datos», «Incompleto…», «No completado») se anuncian como texto.

## 3. Resultados

Rellena una fila por flujo y combinación. Pon la fecha, las versiones (sistema, navegador y lector) y tu nombre o iniciales en la cabecera.

**Combinación A:** NVDA ____ · Firefox ____ · Windows ____ · Fecha ____ · Persona ____
**Combinación B:** VoiceOver · Safari ____ · macOS/iOS ____ · Fecha ____ · Persona ____

| Flujo | Combinación | Resultado | Problemas (paso, lo que anunció y lo que se esperaba) | Gravedad |
|---|---|---|---|---|
| 1. Inicio de sesión | A | | | |
| 1. Inicio de sesión | B | | | |
| 2. Registro | A | | | |
| 2. Registro | B | | | |
| 3. Test de personalidad | A | | | |
| 3. Test de personalidad | B | | | |
| 4. Reserva | A | | | |
| 4. Reserva | B | | | |
| 5. Evaluación | A | | | |
| 5. Evaluación | B | | | |
| 6. Mensajes | A | | | |
| 6. Mensajes | B | | | |
| 7. Progreso e historial | A | | | |
| 7. Progreso e historial | B | | | |

Entrega la tabla rellena al equipo técnico. Se copiará en `specs/011-frontend-accessibility/audit.md` (T019.10) y cada problema se clasificará según la spec 011.
