# Constitución Técnica y Funcional de MIS

**Ruta:** `docs/constitution.md`  
**Versión:** 1.1.0
**Ámbito:** Toda funcionalidad, módulo, API, integración y cambio de datos de MIS.

## 1. Propósito

MIS debe proporcionar una experiencia de mentoría personalizada, medible, segura y verificable.

Toda decisión técnica y funcional DEBE respetar estos principios. Ninguna nueva funcionalidad puede comprometer la seguridad, integridad de los datos, trazabilidad o compatibilidad de la plataforma.

## 2. Principios innegociables

### P1. Una única fuente de verdad

PostgreSQL DEBE ser la fuente persistente de verdad para usuarios, mentorías, objetivos, evaluaciones, actividades, sesiones, resultados y certificados.

- Ningún servicio DEBE mantener una versión persistente contradictoria de los datos de negocio.
- Redis DEBE utilizarse para caché, colas u operaciones temporales, no como sustituto de los registros persistentes.
- Los cambios de estado DEBEN persistirse antes de notificarse como exitosos.

**Verificación:** pruebas de persistencia, integridad referencial y consistencia entre servicios.

### P2. Arquitectura existente primero

Toda funcionalidad DEBE implementarse aprovechando la arquitectura vigente de MIS.

- Next.js/React: interfaz y experiencia de usuario.
- Laravel: lógica de negocio, autorización y API principal.
- PostgreSQL: persistencia y relaciones.
- FastAPI/Python: capacidades especializadas de análisis y matching.
- Redis, colas y Reverb: procesamiento asíncrono y comunicación en tiempo real, cuando estén disponibles y configurados.

No se DEBE introducir una nueva tecnología, servicio o base de datos sin justificar su necesidad y coste operativo.

**Verificación:** toda nueva dependencia arquitectónica debe documentar su justificación, responsabilidades y alternativas consideradas.

### P3. Autorización en cada operación

Toda operación protegida DEBE validar la identidad, el rol, los permisos y la pertenencia del usuario al recurso solicitado.

- Ocultar un botón en el frontend NO constituye autorización.
- Los recursos de familias, grupos y empresas DEBEN respetar sus límites de acceso.
- Un usuario NO DEBE consultar ni modificar información ajena mediante identificadores manipulados.
- Los privilegios administrativos DEBEN concederse explícitamente.

**Verificación:** pruebas de acceso autorizado y denegado para cada endpoint protegido.

### P4. Privacidad desde el diseño

MIS DEBE recopilar únicamente los datos necesarios para prestar sus servicios y protegerlos durante todo su ciclo de vida.

- Las evaluaciones, mensajes y resultados individuales DEBEN limitarse a usuarios autorizados.
- Los registros técnicos NO DEBEN exponer contraseñas, tokens ni información sensible innecesaria.
- Los datos personales NO DEBEN enviarse a servicios externos sin autorización y justificación.
- Las políticas de conservación y eliminación DEBEN estar documentadas.

**Verificación:** pruebas de autorización, revisión de registros y controles de exposición de datos.

### P5. Trazabilidad de las decisiones

Toda modificación relevante de una mentoría DEBE permitir identificar quién la realizó, cuándo y qué cambió.

Se consideran relevantes los cambios en objetivos, evaluaciones, sesiones, asistencia, planes, resultados y certificados.

- Los registros de auditoría NO DEBEN poder modificarse mediante las operaciones ordinarias de negocio.
- Los cambios de estado DEBEN conservar sus transiciones relevantes.
- La auditoría NO DEBE almacenar secretos ni copias innecesarias de datos personales.

**Verificación:** pruebas de auditoría para operaciones de creación, actualización, cancelación y cierre.

### P6. Progreso basado en evidencias

El avance de una persona DEBE derivarse de objetivos, actividades, evaluaciones y evidencias registradas.

- Cada indicador DEBE tener una definición, una fuente de datos y una regla de cálculo documentadas.
- Una sesión programada NO DEBE contabilizarse automáticamente como completada.
- El progreso NO DEBE modificarse únicamente para mejorar indicadores visuales.
- Los datos incompletos DEBEN identificarse como tales.

**Verificación:** pruebas de cálculo con datos conocidos, casos límite y registros incompletos.

### P7. Planes personalizados, pero explicables

Toda recomendación de mentoría DEBE relacionarse con objetivos, necesidades o resultados identificables.

- Los planes DEBEN definir objetivos, actividades y criterios de seguimiento.
- Las recomendaciones automatizadas DEBEN explicar los factores relevantes que las sustentan.
- Los resultados de personalidad NO DEBEN determinar por sí solos el acceso a oportunidades ni sustituir el criterio del mentor.
- Los cambios importantes del plan DEBEN conservar su historial.

**Verificación:** pruebas de reglas de recomendación y registro de la justificación de los cambios.

### P8. Agenda consistente y sin duplicidades

Las sesiones DEBEN gestionarse mediante estados y reglas de disponibilidad explícitos.

- Toda sesión DEBE identificar participantes, fecha, duración, zona horaria y estado.
- El sistema DEBE impedir reservas incompatibles para un mismo participante, según las reglas de disponibilidad.
- Las cancelaciones y reprogramaciones DEBEN actualizar el calendario y conservar la trazabilidad.
- Los reintentos de una misma solicitud NO DEBEN crear sesiones duplicadas.

**Verificación:** pruebas de solapamiento, concurrencia, cambios de horario e idempotencia.

### P9. Evaluaciones reproducibles

Toda evaluación DEBE identificar su instrumento, versión, fecha, respuestas y reglas de interpretación aplicables.

- Las versiones utilizadas NO DEBEN cambiar retroactivamente los resultados históricos.
- Las puntuaciones DEBEN calcularse mediante reglas documentadas.
- Los resultados DEBEN distinguirse de las recomendaciones derivadas de ellos.
- Las comparaciones temporales DEBEN considerar cambios de instrumento o metodología.

**Verificación:** pruebas con respuestas de referencia y comparación entre versiones.

### P10. Recursos y actividades con control de acceso

Todo recurso DEBE tener un propietario o responsable, una categoría, un estado y reglas de acceso definidas.

- Las actividades asignadas DEBEN vincularse a objetivos o planes cuando corresponda.
- Los archivos privados NO DEBEN exponerse mediante rutas públicas predecibles.
- Los cambios de versión de un recurso DEBEN preservar la referencia necesaria para interpretar actividades históricas.
- Los recursos retirados NO DEBEN invalidar evidencias previamente registradas.

**Verificación:** pruebas de permisos, disponibilidad de archivos y conservación de referencias.

### P11. Indicadores y reportes coherentes

Todos los informes DEBEN utilizar definiciones de métricas compartidas y fuentes de datos verificables.

- Los filtros de periodo, mentor, cliente y organización DEBEN aplicarse consistentemente.
- Los totales DEBEN poder contrastarse con los registros de origen.
- Los reportes DEBEN distinguir datos reales, estimaciones y datos incompletos.
- La exportación DEBE respetar los permisos del usuario que la solicita.

**Verificación:** pruebas de conciliación entre consultas, dashboards y archivos exportados.

### P12. Certificados respaldados por evidencias

Un certificado DEBE emitirse únicamente cuando se cumplan los criterios de finalización establecidos para el programa correspondiente.

- Los criterios DEBEN ser explícitos, verificables y aplicarse en el servidor.
- Cada certificado DEBE tener un identificador único y una relación persistente con su titular y programa.
- La verificación pública DEBE revelar solo la información necesaria para comprobar su autenticidad.
- La revocación, cuando corresponda, DEBE quedar registrada y reflejarse en la verificación.
- La tecnología blockchain o IPFS NO DEBE sustituir la validación de los requisitos de emisión.

**Verificación:** pruebas de emisión válida, rechazo por incumplimiento, verificación y revocación.

### P13. Evidencias íntegras y verificables

Toda evidencia que respalde un resultado DEBE poder vincularse a su origen y conservar su integridad.

- Los archivos relevantes DEBEN contar con metadatos de procedencia y controles de integridad adecuados.
- Las modificaciones DEBEN distinguirse de las versiones originales cuando afecten la validez de una evidencia.
- Los registros externos, incluidos los almacenados en IPFS o referenciados en blockchain, DEBEN validarse antes de aceptarse como prueba.
- Una referencia externa NO DEBE considerarse prueba suficiente si su contenido no puede verificarse.

**Verificación:** pruebas de integridad, correspondencia de identificadores y detección de alteraciones.

### P14. Automatizaciones seguras e idempotentes

Los recordatorios, alertas, tareas programadas y procesos en segundo plano DEBEN tolerar reintentos y fallos.

- Una misma operación lógica NO DEBE producir efectos duplicados.
- Los fallos DEBEN registrarse y permitir reintentos controlados.
- Las notificaciones DEBEN generarse a partir de eventos de negocio válidos.
- Las automatizaciones NO DEBEN cambiar estados críticos sin validar las reglas de negocio.

**Verificación:** pruebas de reintentos, fallos simulados y ejecución repetida.

### P15. IA como asistencia, no como autoridad

Las funciones de inteligencia artificial DEBEN apoyar decisiones humanas y respetar las reglas de negocio de MIS.

- La IA NO DEBE eludir los permisos ni acceder a información no autorizada.
- Las recomendaciones DEBEN poder revisarse y rechazarse.
- Las acciones críticas, los cambios de permisos y la emisión de certificados NO DEBEN depender exclusivamente de una respuesta generada por IA.
- Las recomendaciones DEBEN evaluarse para detectar errores y resultados discriminatorios.

**Verificación:** pruebas de autorización, validación de salidas y escenarios adversos.

### P16. Experiencia accesible y consistente

Toda interfaz DEBE ofrecer una experiencia comprensible, adaptable y accesible.

- Los formularios DEBEN validar los datos y comunicar los errores de forma clara.
- Las operaciones asíncronas DEBEN mostrar estados de carga, éxito y error.
- Las acciones principales DEBEN ser utilizables con teclado y tecnologías de asistencia.
- Los dashboards DEBEN distinguir visualmente estados, tendencias y ausencia de datos.

**Verificación:** pruebas de interfaz, navegación por teclado y revisión de accesibilidad.

### P17. Contratos de API explícitos

Toda API DEBE definir sus entradas, salidas, errores, autenticación y reglas de autorización.

- Los endpoints Laravel y FastAPI DEBEN validar los datos recibidos.
- Los errores NO DEBEN exponer trazas internas, credenciales ni información sensible.
- Los cambios incompatibles DEBEN versionarse o acompañarse de una migración controlada.
- Las integraciones DEBEN gestionar tiempos de espera y errores de servicios externos.

**Verificación:** pruebas de contrato, validación de errores y compatibilidad entre consumidores.

### P18. Cambios de datos seguros

Toda migración de PostgreSQL DEBE preservar la integridad de los datos existentes y permitir una transición controlada.

- Las restricciones de unicidad, integridad y consistencia DEBEN implementarse en la base de datos cuando corresponda.
- Los cambios incompatibles NO DEBEN desplegarse sin un plan de migración.
- Las operaciones destructivas DEBEN contar con respaldo y un procedimiento de recuperación apropiado.
- Los cambios de esquema DEBEN acompañarse de pruebas.

**Verificación:** ejecución de migraciones sobre una base de prueba con datos representativos y validación posterior.

### P19. Calidad demostrable

Toda funcionalidad nueva DEBE incluir pruebas acordes con su riesgo y comportamiento esperado.

- Las reglas de negocio DEBEN contar con pruebas automatizadas.
- Las operaciones críticas DEBEN probar sus permisos y escenarios de error.
- Los defectos corregidos DEBEN incorporar pruebas de regresión cuando sea viable.
- Ningún cambio DEBE considerarse listo para producción si sus pruebas obligatorias fallan.

**Verificación:** suite de pruebas ejecutada en integración continua y resultados disponibles para revisión.

### P20. Observabilidad y operación sostenible

Toda funcionalidad crítica DEBE permitir detectar fallos y diagnosticar su causa.

- Los errores DEBEN generar registros estructurados y útiles.
- Los procesos asíncronos DEBEN exponer su estado de ejecución y sus fallos.
- Las operaciones críticas DEBEN contar con métricas o mecanismos equivalentes de supervisión.
- Los registros NO DEBEN incluir secretos ni datos personales innecesarios.

**Verificación:** pruebas de registro de errores, seguimiento de tareas y revisión de alertas operativas.

## 3. Reglas de implementación por módulo

| Módulo | Condición obligatoria de aceptación |
|---|---|
| Panel de progreso y seguimiento | Los indicadores se calculan desde registros verificables y respetan los permisos. |
| Agenda y gestión de mentorías | Se controlan disponibilidad, estados, zona horaria y duplicidades. |
| Evaluaciones y planes personalizados | Los resultados son reproducibles y los cambios del plan son trazables. |
| Biblioteca de recursos y actividades | Los recursos respetan los permisos y las actividades pueden vincularse con objetivos. |
| Informes y analítica | Las métricas están definidas y los reportes concuerdan con sus fuentes. |
| Certificados y evidencias | La emisión se basa en requisitos comprobados y la verificación no expone datos innecesarios. |
| Alertas y seguimiento automatizado | Las tareas toleran reintentos y no generan efectos duplicados. |
| Comunidad y colaboración | La pertenencia a grupos y organizaciones determina el acceso a contenidos y participantes. |
| Inteligencia artificial | Las recomendaciones son revisables, justificables y subordinadas a las reglas de negocio. |

### 3.1 Decisiones de producto — Panel de progreso y seguimiento

Las siguientes decisiones del cliente son vinculantes para el módulo **Panel de progreso y seguimiento** y complementan su condición obligatoria de aceptación (spec de referencia: `specs/001-progress-tracking-dashboard/spec.md`):

1. **Permisos de objetivos.** El mentor administra los objetivos individuales; el mentee propone objetivos y aporta evidencias; el responsable autorizado administra los objetivos colectivos.
2. **Ámbito de objetivos.** DEBEN admitirse objetivos individuales y colectivos, con titular o colectivo responsable obligatorio según corresponda.
3. **Sesiones realizadas.** Una sesión cuenta como realizada únicamente cuando su estado es `completed` **y** su asistencia está confirmada, de modo que la asistencia individual se mida a partir de la asistencia registrada.
4. **Periodos predeterminados.** Los próximos compromisos cubren los próximos **7 días**; los objetivos se evalúan en su **estado actual**; las sesiones realizadas se cuentan por el **mes calendario actual**.
5. **Privacidad colectiva.** Los agregados derivados de datos individuales se muestran a partir de un **umbral inicial de 3 participantes elegibles**, aplicado de forma **uniforme a toda vista** (miembro, responsable o mentor), con reglas explícitas de pertenencia y tratamiento explícito de datos faltantes. El responsable del colectivo conserva la administración de los objetivos colectivos, pero no accede a agregados suprimidos por base insuficiente.

## 4. Criterios obligatorios para cada cambio

Antes de aprobar una funcionalidad o modificación, el responsable DEBE confirmar:

- [ ] Cumple los principios aplicables de esta constitución.
- [ ] Respeta la arquitectura existente o justifica formalmente cualquier excepción.
- [ ] Implementa autorización en el servidor y protección de datos.
- [ ] Define sus reglas de negocio y criterios de aceptación.
- [ ] Incluye pruebas automatizadas para los comportamientos críticos.
- [ ] Conserva la integridad y trazabilidad de los datos.
- [ ] Documenta las migraciones, dependencias y cambios de API.
- [ ] Permite diagnosticar fallos sin exponer información sensible.

Una casilla sin cumplir implica que el cambio no está listo para aprobación, salvo una excepción formal conforme a la sección siguiente.

## 5. Excepciones y evolución

Los principios de seguridad, privacidad, integridad de datos y autorización son obligatorios y no pueden omitirse por conveniencia.

Cualquier otra excepción DEBE:

1. Identificar el principio afectado.
2. Justificar la necesidad y las alternativas evaluadas.
3. Documentar los riesgos y las medidas compensatorias.
4. Identificar al responsable de aprobarla.
5. Establecer una fecha de revisión y, cuando corresponda, de expiración.

Toda modificación de esta constitución DEBE registrarse mediante control de versiones y revisión explícita.

## 6. Definición de cumplimiento

Una funcionalidad se considera conforme con esta constitución únicamente cuando:

- Cumple los principios aplicables.
- Supera las pruebas requeridas.
- Respeta los permisos y las restricciones de datos.
- Cuenta con documentación suficiente para su mantenimiento.
- Puede verificarse su comportamiento en un entorno de pruebas.

**Regla final:** ninguna funcionalidad de MIS se considera terminada únicamente porque funciona en la interfaz; debe ser segura, consistente, comprobable y mantenible.

## 7. Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | — | Versión inicial. |
| 1.1.0 | 2026-10-11 | Se incorporan las decisiones de producto del Panel de progreso y seguimiento (§3.1): permisos de objetivos, ámbito de objetivos, sesiones realizadas, periodos predeterminados y privacidad colectiva (umbral de 3 participantes elegibles aplicado de forma uniforme a miembros, responsables y mentores). |