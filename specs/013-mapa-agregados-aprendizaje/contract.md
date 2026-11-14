# Contrato de lectura — Spec 013: Mapa y agregados de aprendizaje

**Estado:** implementado en T013.1 (DTOs + Resources; los endpoints llegan en T013.7).
Las partes compartidas con Spec 012 (`learning_path`, `rate`, `modules[].id/title/position/activity_rate/completion`) mantienen exactamente las mismas claves del recurso `GET /progress/learning-paths`; el mapa solo añade `version_key` y `elements`.

## 1. Mapa individual (`GET /progress/map`, `GET /mentor/users/{user}/progress/map`)

```json
{
  "data": [
    {
      "learning_path": { "id": "01h…", "title": "Ruta Bienestar" },
      "version_key": "8f14e45fceea167a5a36dedd4bea2543",
      "rate": {
        "state": "value",
        "value": 75,
        "numerator": 3,
        "denominator": 4,
        "source": "module_completion",
        "period": null,
        "incomplete": false
      },
      "modules": [
        {
          "id": "01m…",
          "title": "Módulo 1",
          "position": 1,
          "activity_rate": {
            "state": "value",
            "value": 100,
            "numerator": 2,
            "denominator": 2,
            "source": "challenge_status",
            "period": null,
            "incomplete": false
          },
          "completion": { "state": "completed", "completed_at": "2026-11-14T10:00:00+00:00" },
          "elements": [
            {
              "type": "challenge",
              "id": "01c…",
              "title": "Reto diario",
              "source_status": "in_progress"
            }
          ]
        }
      ]
    }
  ]
}
```

- `version_key`: fingerprint estable (32 hex) de la definición vigente de la ruta — su identidad más la secuencia ordenada de módulos. La misma definición produce la misma clave; añadir/quitar módulos o cambiar su orden produce otra. **No es un snapshot histórico** ni crea versionado: sigue la definición actual, así que una ruta editada simplemente cambia de clave y no se promedia junto a la definición anterior. Los cambios no estructurales (títulos) la conservan.
- `rate` y `activity_rate` son `MetricValue` con la serialización del panel (estados `value|zero|no_data|incomplete`, `source`, `numerator/denominator` cuando aplica).
- `completion.state`: `completed|not_completed|no_data|incomplete`; `completed_at` ISO-8601 o `null`.
- `elements[]`: solo elementos con vínculo explícito registrado. `source_status` es el estado crudo grabado en la fuente (`assigned|in_progress|completed|canceled|abandoned` u otro valor registrado); nunca un porcentaje derivado. Hoy solo `type: "challenge"`.
- El mentor recibe la misma representación limitada a la vista autorizada del sujeto; no hay campos extra por rol.

## 2. Agregado colectivo de aprendizaje (bloque nuevo en `GET /progress/collectives/{type}/{id}`)

### Disponible

```json
{
  "state": "available",
  "suppression_reason": null,
  "activity_rate": { "state": "value", "value": 66, "suppression_reason": null },
  "rates": [
    {
      "path_id": "01h…",
      "version_key": "8f14e45fceea167a5a36dedd4bea2543",
      "title": "Ruta Bienestar",
      "rate": { "state": "value", "value": 80, "suppression_reason": null }
    }
  ]
}
```

### Suprimido por base general insuficiente (<3 elegibles)

```json
{ "state": "suppressed", "suppression_reason": "insufficient_base" }
```

### Métrica suprimida por contribuciones calculables insuficientes (<3)

```json
{
  "state": "available",
  "suppression_reason": null,
  "activity_rate": { "state": "suppressed", "suppression_reason": "insufficient_metric_contributions" },
  "rates": [
    {
      "path_id": "01h…",
      "version_key": "…",
      "title": "Ruta Bienestar",
      "rate": { "state": "suppressed", "suppression_reason": "insufficient_metric_contributions" }
    },
    {
      "path_id": "01h2…",
      "version_key": "…",
      "title": "Ruta 2",
      "rate": { "state": "value", "value": 40, "suppression_reason": null }
    }
  ]
}
```

La supresión de una métrica no cambia las demás que sí cumplen su umbral.

## 3. Tabla de estados y fuentes

| Campo | Valores | Fuente |
|---|---|---|
| `rate` / `activity_rate` (mapa) | `value`, `zero`, `no_data`, `incomplete` | `module_completion` (tasa de ruta), `challenge_status` (actividad de módulo) |
| `completion.state` | `completed`, `not_completed`, `no_data`, `incomplete` | `ModuleCompletionService` / `ChallengeModuleCalculator` (Spec 012) |
| `elements[].type` | `challenge` | `challenges.path_module_id` (vínculo explícito) |
| `elements[].source_status` | estado crudo de la fuente (`assigned`, `in_progress`, `completed`, `canceled`, `abandoned`, …) | `challenges.status` |
| bloque agregado `state` | `available`, `suppressed` | `EligibleParticipantsResolver` + umbral 3 |
| métrica agregada `state` | `value`, `suppressed` | media aritmética de contribuciones calculables |
| `suppression_reason` | `insufficient_base`, `insufficient_metric_contributions` | `App\Services\Progress\Support\SuppressionReason` (enum único; `insufficient_base` es el código vigente de Spec 001) |

## 4. Campos omitidos bajo supresión

| Caso | Payload |
|---|---|
| Bloque suprimido por base | únicamente `{state, suppression_reason}` — sin `activity_rate`, `rates`, ni ninguna métrica |
| Métrica suprimida por contribuciones | únicamente `{state, suppression_reason}` en esa métrica — sin `value`, `numerator`, `denominator`, conteo de contribuciones ni tamaño de base |
| Siempre | nunca IDs de miembros, mapas ni métricas individuales; los motivos son códigos traducibles, no números |
