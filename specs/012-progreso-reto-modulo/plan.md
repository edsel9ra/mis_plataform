# Plan de implementación — Spec 012: Progreso verificable de retos y módulos

**Estado:** propuesto para implementación · **Spec:** aprobada
**Base:** Spec 001 v0.5.1 aprobada; baseline v0.3.0 ya implementado. Este plan es aditivo y no altera su plan ni su backlog históricos.
**Dependencia:** ejecutar antes de Spec 013; Spec 015 consume sus eventos.

## 1. Alcance y arquitectura

Ampliar los servicios de progreso existentes en Laravel/PostgreSQL. El servidor seguirá siendo la fuente de verdad; React solo presenta contratos autorizados. No hay backfill de vínculos reto-módulo: cualquier asociación se crea explícitamente. Las rutas, módulos y participantes se resuelven con los modelos actuales, sin inferir vínculos.

| Archivo o área | Responsabilidad | RF |
|---|---|---|
| `backend/database/migrations/*challenge_path_module*` | Añadir `challenges.path_module_id` nullable con FK a `path_modules` e índice; crear persistencia individual de completitud de módulo con UUID, FKs y unicidad `(user_id, path_module_id)`. Sin backfill inferido. | RF-012.2, .8–.12 |
| `backend/app/Models/Challenge.php`, nuevo modelo de completitud de módulo y relaciones | Vínculo explícito y lecturas/escrituras de la completitud individual. `Challenge` añade `abandoned` a estados/transiciones; el estado de módulo se conserva solo mientras cumple la regla. | RF-012.1–.7, .11–.12 |
| `backend/app/Services/Progress/Calculators/{ChallengeModule,PathProgress}Calculator.php` | Cálculo puro y conciliable de actividad, módulo y ruta; no accede a BD ni al reloj global. | RF-012.1–.6, .11–.12 |
| `backend/app/Actions/{TransitionChallengeStatus,SetChallengePathModule}Action.php`; policy/request nuevos | Validación, autorización de ambos extremos y mutación transaccional; integra los accesos existentes `ChallengePolicy`/`MentorWorkspaceAccessService` y el ámbito de rutas autorizadas. | RF-012.7–.12 |
| `backend/app/Services/Progress/{ModuleCompletion,PathProgress}Service.php` | Carga acotada, orquestación de calculadoras y persistencia/revocación atómica de completitud; emite registros auditables consumibles por Spec 015. | RF-012.1–.7, .11 |
| `backend/app/Http/{Controllers/Api,Requests,Resources}/Progress/*`, `backend/routes/api.php` | Contrato para gestionar vínculos y obtener métricas individuales; controllers delgados y recursos con `state`, fórmula, numerador/denominador y completitud. El mapa compuesto se expone en Spec 013. | RF-012.1, .8–.12 |
| `backend/tests/{Unit,Feature}/Progress/*` | Reglas puras, persistencia, acceso permitido/denegado, transiciones, reversión y consistencia transaccional. | Todos |
| `frontend/src/{types,lib,hooks,components/progress,i18n/messages/*}` | Contrato tipado, funciones de presentación puras, formulario autorizado de vínculo y estados/traducciones; sin cálculo de progreso en el cliente. | RF-012.1–.12, RNF-012.3, .4, .6 |

## 2. Datos, integridad y transiciones

- `challenges.path_module_id` nullable permite que la ausencia siga significando «sin vínculo»; FK con `ON DELETE SET NULL`, índice y ninguna inferencia/backfill. La action valida que reto, mentee, módulo y ruta correspondan al ámbito autorizado.
- Persistir la completitud automática individual con clave única `(user_id, path_module_id)` y FKs UUID. No guardar porcentajes derivados. La fila `completed` existe solo cuando hay ≥1 reto aplicable y todos están explícitamente `completed`; ante cualquier reto aplicable con estado ausente/desconocido se revoca/no se crea. El registro de actividad conserva la transición previa al retiro para la auditoría P5.
- `abandoned` se añade a `Challenge::VALID_STATUSES` y al ciclo de transiciones existente. Las transiciones y el vínculo se modifican por Actions; no aceptar actualizaciones de estado que evadan su validación y registro. En cambios de vínculo/estado, recalcular y persistir la finalización/revocación dentro de la misma transacción, con lock del módulo/sujeto para evitar carreras.
- El objetivo colectivo/individual, porcentaje global estimado de ruta, `Challenge.progress` y asistencia quedan fuera de las fuentes de la tasa; el estado registrado del reto es la única señal.

## 3. Funciones puras y contratos

Todas las funciones reciben arrays/DTOs ya normalizados; sin IO, estado global ni hora global. `now` se pasa como parámetro a cualquier clasificación temporal; las reglas de estas tasas no dependen del reloj.

```text
activityRate(challenges):
  aplicables = vinculados cuyo estado no sea template/canceled/abandoned
  si existe aplicable con estado nulo/desconocido: incomplete, sin tasa
  elegibles = aplicables con estado assigned/in_progress/completed
  si elegibles está vacío: no_data
  si no: completed(elegibles) / count(elegibles), con numerador y denominador

moduleCompletion(challenges):
  si hay estado aplicable nulo/desconocido: incomplete, no completion
  si no hay reto elegible: no_data, no completion
  completed iff todos los elegibles tienen estado completed

pathRate(modules):
  si algún módulo requerido está incompleto: incomplete, sin parcial
  elegibles = módulos con al menos un reto elegible
  si elegibles vacío: no_data
  si no: módulos con fila individual completed / módulos elegibles
```

El contrato de lectura conserva la semántica de `MetricValue` de la Spec 001: `value`/`no_data`/`incomplete`, `source`, numerador y denominador visibles cuando haya tasa. El módulo incluye estado fuente y resultado de la regla; no se inventa porcentaje por actividad. La mutación del vínculo acepta `path_module_id` UUID o `null` para retiro y responde con el vínculo persistido, nunca con datos de otra persona.

## 4. Secuencia de orquestación

1. Resolver actor y sujeto; autorizar en servidor el reto y la pertenencia vigente mentor/mentee.
2. Para asociar/cambiar, cargar módulo destino con ruta y validar que el sujeto puede consultar esa ruta por asignación/inicio. Para retirar, autorizar antes el módulo/ruta actualmente vinculados. Fallar sin revelar el vínculo si cualquier autorización falla.
3. En una transacción con lock, conservar snapshot anterior, persistir vínculo o transición permitida, recalcular módulos afectados (anterior y nuevo) y crear/retirar filas de completitud solo conforme a la regla pura.
4. Registrar actor, instante, relación/estado anterior y resultante; las transiciones automáticas identifican `system` y guardan actor/acción desencadenantes. Invalidar las claves de progreso/mapa afectadas.
5. Serializar métricas calculadas en servidor; los errores se registran estructuradamente, sin texto de reto, respuestas ni datos personales innecesarios.

## 5. Decisiones técnicas y alternativas descartadas

| Decisión | Alternativa descartada | Justificación |
|---|---|---|
| `path_module_id` nullable explícito; no backfill | Relacionar por título, orden o progreso de ruta | Evita atribuir evidencia que no existe (P6) y respeta RF-012.12. |
| Tabla individual de completitud con unicidad/FKs, sin porcentajes persistidos | Recalcular todo solo en frontend o guardar métricas derivadas | Hace persistido/auditable el `completed` individual y mantiene PostgreSQL como verdad (P1/P18). |
| Funciones puras + Actions + servicios existentes | Lógica dentro de Eloquent/controller | Permite pruebas deterministas y autorización/consistencia centralizadas (P3/P19). |
| Reutilizar `ChallengePolicy`, `MentorWorkspaceAccessService`, relación vigente y caché de progreso existente | Permisos paralelos o confiar en UI | No amplía permisos, evita IDOR y sigue arquitectura MIS. |
| Transacción/locks y FK/índice/unique en PostgreSQL | Guardar vínculo y completitud en operaciones separadas | Impide resultados parcialmente persistidos y filas duplicadas ante concurrencia. |

## 6. Autorización, observabilidad y rendimiento

Cada lectura/mutación valida identidad, estado de asignación, titularidad del reto y acceso a la ruta en servidor; se reutilizan servicios y políticas vigentes. Las denegaciones no devuelven ni alteran vínculo. Actividad de vínculo, transición y completion/revocación se registra con `spatie/laravel-activitylog` ya instalado, de forma que Spec 015 pueda consumirla; no incluir contenido sensible. Logs estructurados `progress.*` incluyen tipo de fallo e IDs mínimos, no títulos/respuestas/score.

Las lecturas usan eager loading/columnas necesarias e índices `path_module_id`, `learning_path_id`, `user_id`; la ruta se calcula por lote (sin N+1), se invalidan las claves de caché existentes ante cambio de fuente. La carga se verifica en lecturas del panel/mapa junto con Spec 013: 50 consultas simultáneas y p95 ≤2 s desde recepción en servidor hasta respuesta API. Pruebas de UI a 320 px, teclado/tacto, foco visible y estados no solo por color.

## 7. Estrategia de pruebas (planificada; no ejecutada)

- **Unit Pest:** datasets completos, estados excluidos, `abandoned`, desconocido/nulo bloqueante, conjunto vacío, módulo con 0/1/múltiples retos, ruta con módulo incompleto y conciliación exacta; cubre RF-012.1–.7, .11–.12.
- **Feature Pest/PostgreSQL:** FK/unique, asociación/cambio/retiro, denegación para participante ajeno/mentor no asignado/ruta no visible, validación de vínculo actual al retirar, no filtración/IDOR, transición permitida y rechazada, system actor y usuario desencadenante, reversión automática y rollback atómico; cubre RF-012.2, .7–.12 y RNF-012.1–.2, .5.
- **Frontend Vitest + inspección accesible:** `no_data` vs `incomplete` vs cero, mensajes de error/fallback, vínculo ausente, traducciones es/en/pt; teclado y viewport 320 px; cubre RNF-012.3, .4, .6.
- **Rendimiento:** prueba de carga planificada para el criterio padre de 50 concurrentes/p95; inspección de query count para ausencia de N+1. No se reportan resultados hasta implementación.

## 8. Trazabilidad de requisitos

| Parte | RF cubiertos |
|---|---|
| Modelo, esquema y reglas puras | RF-012.1–.6, .11–.12 |
| Ciclo `abandoned`, Actions, persistencia y auditoría | RF-012.2, .7, .11 |
| Policy, endpoints de vínculo y autorización de ruta | RF-012.8–.10, .12 |
| Contrato de lecturas e integración de tasa | RF-012.1–.6, .11 |
| UI, respuestas incompletas, i18n y accesibilidad | RF-012.1, .4–.5, .8–.12; RNF-012.3–.6 |
