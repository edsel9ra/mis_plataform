# Fixtures de la base de datos de auditoría — Spec 019

Los crea `database/seeders/AccessibilityAuditSeeder.php`, que solo corre contra una base de datos **vacía** cuyo nombre termine en `_audit` (guarda `App\Support\AuditTargetGuard`, RF-019.2) y exige `A11Y_SEED_PASSWORD`. Todas las cuentas usan esa contraseña y `locale = es`. Este documento nunca contiene la contraseña real.

## Cuentas

| Email | Rol / tipo | Plan | Sirve para |
|---|---|---|---|
| `admin.audit@audit.mis.test` | `admin` | — | Todas las rutas `/admin/**`; la lista de usuarios tiene 2+ páginas |
| `ana.audit@audit.mis.test` | mentee `personal` | `personal_premium` (con espacio del mentor) | Personalidad completada, objetivos, evaluaciones, sesiones, mensajes, retos, workspace completo |
| `kai.audit@audit.mis.test` | mentee `personal` | `audit_personal_locked` (**sin** `mentor_workspace`) | Bloqueo por plan del workspace e historial; test de personalidad sin completar |
| `carlos.audit@audit.mis.test` | mentee `familiar` | `family` (grupo familiar) | Dashboard familiar; relación con source `family_group` |
| `diana.audit@audit.mis.test` | mentee `grupal` | `group` (cohorte) | Dashboard grupal; source `cohort` |
| `empresa.admin.audit@audit.mis.test` | `company_admin` `empresa` | `startup` (Audit Corp) | `/dashboard/empresa` real con empleados e invitaciones |
| `empleado.audit@audit.mis.test` | `employee` `empresa` | vía Audit Corp | Dashboard limitado de empleado (inicia sesión; no desactivarlo) |
| `empleado2.audit@audit.mis.test` | `employee` `empresa` | vía Audit Corp | Segundo empleado de la lista |
| `invitado.audit@audit.mis.test` | `employee` `empresa` | vía Audit Corp | Employee `invited`, nunca activado; solo aparece en la lista, sin login |
| `inactivo.audit@audit.mis.test` | `employee` `empresa` | vía Audit Corp | Employee `inactive` (`is_active=false`); solo aparece en la lista, sin login |
| `rotativo.audit@audit.mis.test` | `employee` `empresa` | vía Audit Corp | Employee `active` con nombre fijo «Rotativo Audit»; la prueba de foco lo desactiva y reactiva (termina `active` → repetible), sin login |
| `empresa.setup.audit@audit.mis.test` | `company_admin` `empresa` | — (sin compañía) | Estado «configura tu empresa» (h1 y formulario de setup) |
| `mentor.audit@audit.mis.test` | `mentor` | — | Espacio del mentor; skills `expert` (3 etiquetas) |
| `relleno.NN.audit@audit.mis.test` (NN=01–25) | mentee `personal` | — | Relleno para que `/admin/users` supere el `per_page` de 20; `relleno.04` cambia de idioma en `/profile` y `relleno.05` solicita mentoría (T019.9) |

Además, `AdminUserSeeder` crea `admin@mis.com` (super_admin) con la contraseña por defecto del seeder base.

## Recursos creados

| Recurso | Detalle |
|---|---|
| Relaciones | `ana`→`mentor.audit` (personal), `kai`→`mentor.audit` (personal), `Familia Audit`→`mentor.audit` (familiar). Las 3 `active` |
| Sesiones de ana | Futura programada; **pasada con la ventana de asistencia abierta** (fin ≈ `now − 3 h`, dentro de las 48 h de `attendance_window_hours`; los asistentes quedan con `created_at` anterior a la sesión, como si se hubieran registrado al reservar, para que `AttendanceRules` los considere marcables — la suite la resuelve como `pastSessionId` y ana marca su propia fila); **pasada sin completar** (fin ≈ `now − 25 h`, aún completable dentro de `not_completed_after_hours`); completada (hace 7 días). Todas con asistentes ana + mentor |
| Mensajes | 6 mensajes alternados ana↔mentor en la relación, algunos leídos |
| Reto + entrega + feedback | Reto `in_progress` de `mentor.audit`; entrega `submitted` de ana; hilo de feedback con 1 entrada del mentor y un `MediaAsset` (`purpose=mentor_feedback`, fichero de texto pequeño en disco `local`) |
| Workspace de ana | 1 observación neurocognitiva, 1 check-in emocional, 1 mapa sistémico (2 nodos + 1 arista) |
| Objetivos de ana | 1 `in_progress` con evidencia; 1 con historial de estados (`not_started`→`in_progress`→`achieved`) |
| Competencia | 1 `CompetencyAssessmentLink` OCEAN (factor O) sobre la personalidad de ana |
| Disponibilidad del mentor | Ventanas semanales de `mentor.audit`: lunes–viernes 09:00–13:00 y 15:00–18:00 en su zona horaria (10 `mentor_availability_windows`), para que `/sessions/new` ofrezca huecos en la primera semana que busca |
| Ruta de aprendizaje de ana | `Ruta de auditoría de accesibilidad` con `UserLearningProgress` `in_progress` y 3 módulos: uno sin retos vinculados («Sin datos»), uno con un reto `in_progress` («No completado») y uno con un reto de estado heredado `paused` («Incompleto…») en el mapa |
| Evaluaciones | Catálogo real (`EvaluationCatalogSeeder`); ana tiene 1 resultado registrado y 3 sin responder (la primera sin responder se resuelve como `evaluationOpenId`, de solo lectura; la última como `evaluationSubmitId`, que la prueba de envío de T019.9 consume una por ejecución de la suite) |
| Colectivos | `Familia Audit` (+miembro), `Cohorte Audit` (+miembro), `Audit Corp` (5 empleados: 3 `active` —empleado, empleado2 y Rotativo Audit—, 1 `invited`, 1 `inactive`) |
| Planes | Los reales de `PlanSeeder` + `audit_personal_locked` (`entitlements.mentor_workspace=false`) |

**Lo que cambia cada pasada de la suite (spec 020).** Se puede repetir sin volver a sembrar:
- se añade un empleado invitado (`invite.t0208.*`) a Audit Corp; el plan startup admite 20 y se siembran 5;
- ana acumula check-ins y evidencias, con y sin fichero, y se le sustituye la foto;
- «Rotativo Audit» se desactiva y se vuelve a activar, así que termina `active`.

**No creado:** «bio» del mentor — no existe columna `bio` en `users` ni perfil de mentor (`MentorResource.bio` siempre resuelve `null`); solo las skills reales quedan sembradas.

## Procedimiento local

```bash
# 1. Crear la base desechable en el Postgres de Compose
docker exec mis-project-postgres-1 psql -U mis_user -d postgres \
  -c "CREATE DATABASE mis_project_audit OWNER mis_user;"

# 2. Migrar (SCOUT_DRIVER=null evita indexar en el Meilisearch de desarrollo;
#    BROADCAST_CONNECTION=log evita emitir por Reverb)
docker exec \
  -e DB_DATABASE=mis_project_audit \
  -e SCOUT_DRIVER=null -e BROADCAST_CONNECTION=log -e QUEUE_CONNECTION=sync \
  mis-project-backend-1 php artisan migrate --force

# 3. Sembrar (A11Y_SEED_PASSWORD es obligatoria; elige un valor de prueba)
docker exec \
  -e DB_DATABASE=mis_project_audit \
  -e SCOUT_DRIVER=null -e BROADCAST_CONNECTION=log -e QUEUE_CONNECTION=sync \
  -e A11Y_SEED_PASSWORD='<contraseña de prueba>' \
  mis-project-backend-1 php artisan db:seed --class=AccessibilityAuditSeeder

# 4. Apuntar el backend a la base de auditoría durante la sesión de auditoría
#    (variable DB_DATABASE en el entorno del contenedor backend).
```

Para borrarla al terminar **todas** las tareas que la usan, borra también el fichero del `MediaAsset` sembrado, que se escribe en el disco `local` (`backend/storage/app/private/audit/`, ignorado por git):

```bash
docker exec mis-project-postgres-1 psql -U mis_user -d postgres \
  -c "DROP DATABASE mis_project_audit;"
```

Notas:

- Redis no necesita aislamiento para este seeder si se usan `SCOUT_DRIVER=null` y `BROADCAST_CONNECTION=log`; si se sembrara con Scout activo, añadir `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_QUEUE_DB`, `REDIS_PREFIX` y `CACHE_PREFIX` aislados como en `specs/015-historial-progreso/k6/seed-dataset.php`.
- El seeder se niega con `RuntimeException` (código de salida ≠ 0, sin escribir nada) ante: entorno `production`, una base sin sufijo `_audit` (cubre `mis_project` y `mis_project_testing`), una `_audit` que ya tenga usuarios, y la ausencia de `A11Y_SEED_PASSWORD`.

## Ejecutar la suite en local (a)

Suite: `pnpm test:a11y` (`frontend/e2e/**/*.spec.ts`, Playwright 1.65.0 + axe 4.14.0, 3 pruebas de humo). Necesita:

1. `mis_project_audit` sembrada (sección «Procedimiento local») y la contraseña de prueba en `A11Y_PASSWORD` (la misma que se usó en `A11Y_SEED_PASSWORD`).
2. Un backend de auditoría en `:8001` dentro del contenedor backend, con los overrides por entorno (sin tocar `.env.backend`):

```bash
docker exec -d \
  -e DB_DATABASE=mis_project_audit -e SCOUT_DRIVER=null \
  -e BROADCAST_CONNECTION=log -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
  -e SANCTUM_STATEFUL_DOMAINS=mis-a11y-serve:3000,localhost:3108,localhost:3000 \
  mis-project-backend-1 sh -c \
  'cd /var/www/backend/public && php -S 0.0.0.0:8001 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
```

   **Importante:** no uses `php artisan serve` aquí — el hijo `php -S` que genera hereda el entorno original del contenedor y **no** los overrides de `docker exec -e` (verificado en T019.4; la base quedaba en `mis_project`). Ejecuta `php -S` directamente como arriba.

3. Build de producción del frontend con `API_INTERNAL_URL=http://backend:8001` y servirlo (`next start` en el alias `frontend`, o `pnpm start` publicado en `127.0.0.1:3108`). El build se hizo en un contenedor desechable `mis-frontend:dev` montando el volumen anónimo de `node_modules` del frontend y un volumen desechable para `.next`.
4. Runner: el host no puede ejecutar la suite con la imagen `mis-frontend:dev` (Alpine/musl: el Chromium de glibc no arranca). El runner documentado es `mcr.microsoft.com/playwright:v1.65.0-noble`, que coincide con `@playwright/test@1.65.0` y trae sus propios navegadores — no hace falta montar el caché del host:

```bash
docker run --rm --init --network mis-project_mis-network \
  -v "$PWD/frontend:/var/www/frontend" \
  -v "<volumen-node_modules>:/var/www/frontend/node_modules" \
  -w /var/www/frontend \
  -e A11Y_BASE_URL=http://mis-a11y-serve:3000 -e A11Y_PASSWORD='<contraseña de prueba>' \
  mcr.microsoft.com/playwright:v1.65.0-noble \
  node node_modules/@playwright/test/cli.js test
```

   Variables: `A11Y_BASE_URL` apunta al frontend a auditar; `A11Y_PASSWORD` alimenta `global-setup.ts`, que hace login real (CSRF + `POST /api/v1/auth/login`) por cada cuenta y guarda `e2e/.auth/<rol>.json`. `A11Y_ROLES` (opcional, lista por comas) limita los logins a los roles usados.

## Montar el entorno de auditoría en otro equipo (b)

Para la persona que ejecuta el guion del lector de pantalla (decisión D-3):

1. Instala **Docker Desktop** y clona el repositorio.
2. `bash setup.sh` (o `make build && make up`): levanta todo el stack y siembra la base de **desarrollo**.
3. Crea y siembra la base de auditoría con los comandos de «Procedimiento local» (pasos 1–3), eligiendo una `A11Y_SEED_PASSWORD` de prueba. **Anota esa contraseña**: el equipo técnico la entregará aparte.
4. En **ese equipo**, edita `.env.backend` y cambia `DB_DATABASE=mis_project` por `DB_DATABASE=mis_project_audit`; añade `SCOUT_DRIVER=null`, `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=sync` y `CACHE_STORE=array` si no quieres interferir con nada más. Reinicia: `docker compose up -d backend queue-worker`.

   **Ojo (verificado en T019.9):** el arranque del contenedor `backend` ejecuta `migrate --seed --force` (`RUN_DATABASE_BOOTSTRAP=true` en `docker-compose.yml`), y ese seed mete **datos de demostración** en la base a la que apunta `DB_DATABASE` — incluida una relación extra de `ana` con otro mentor, que rompe la selección automática del flujo de reserva. Si recreas el backend ya apuntando a `mis_project_audit`, vuelve a ejecutar los pasos 1–3 de «Procedimiento local» (borrar, migrar y sembrar) **después** de que el backend esté arriba, para dejar la base limpia.
5. Sirve el frontend. Para una sesión de lector de pantalla basta el modo desarrollo (`docker compose up -d frontend`, `http://localhost:3000`); si se quiere producción, `API_INTERNAL_URL` debe apuntar al backend de Compose (`http://nginx`).
6. Abre `http://localhost:3000` (o `http://app.mis-project.local` si el equipo usa el vhost de nginx) y entra con las cuentas `*.audit@audit.mis.test` y la contraseña de prueba.
7. Al terminar la auditoría, `DROP DATABASE` como en «Procedimiento local» si no se reutiliza.

**Verificado en este entorno (T019.4):** reescrituras `/api/v1` y `/sanctum` del build de producción contra el backend de auditoría (login 200 + cookie `mis_auth_token` host-only en `localhost`); los 9 logins de `global-setup` quedan muy por debajo del limitador `auth-login` (5/minuto por IP+email); suite de humo 3/3 en verde. **No verificado:** el punto 4–6 tal cual en otra máquina (aquí se reprodujo con overrides de entorno en lugar de editar `.env.backend`, que no se toca), ni Docker Desktop/instalación desde cero en otro equipo.
